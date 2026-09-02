<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Queue
{
    public const STATUS_REFRESH_HOOK = 'mnem_refresh_provider_status_once';
    private const STATUS_REFRESH_DELAY_SECONDS = 120;
    public const MAX_ATTEMPTS = 3;
    public const BACKOFF_BASE = 300;
    public const DELETABLE_STATUSES = array('pending', 'failed');
    public const SUCCESS_STATUSES = array('sent', 'delivered', 'opened', 'clicked');
    // Deferred remains recoverable, so it is excluded here even though we still count it as a non-success final-ish state in dashboards.
    public const TERMINAL_ISSUE_STATUSES = array('bounce', 'soft_bounce', 'invalid_email', 'complaint', 'unsubscribed', 'suppressed', 'failed', 'rejected');
    public const NON_SUCCESS_FINAL_STATUSES = array('bounce', 'soft_bounce', 'invalid_email', 'deferred', 'complaint', 'unsubscribed', 'suppressed', 'failed', 'rejected');
    public const WEBHOOK_STATUSES = array('pending', 'processing', 'sent', 'delivered', 'opened', 'clicked', 'bounce', 'soft_bounce', 'invalid_email', 'deferred', 'complaint', 'unsubscribed', 'suppressed', 'failed', 'rejected');
    public const SOURCE_CORE = 'core';
    public const SOURCE_CAMPAIGN = 'campaign';
    public const SOURCE_USER_EVENT = 'user_event';
    public const SOURCE_PLUGIN = 'plugin';

    public static function enqueue(int $site_id, string $email, string $subject, string $body, int $campaign_id = 0, array $options = array())
    {
        global $wpdb;

        $recipient = trim((string) $email);
        if ($recipient === '') {
            return false;
        }

        $suppression_email = self::extract_first_email($recipient);
        if ($suppression_email !== '' && self::is_suppressed($site_id, $suppression_email)) {
            Logger::info('Skipped queue insert for suppressed recipient.', array('site_id' => $site_id, 'email' => $suppression_email, 'campaign_id' => $campaign_id));
            return false;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $scheduled_at = self::current_time_mysql();
        $created_at = self::current_time_mysql();
        $from_email = isset($options['from_email']) ? sanitize_email((string) $options['from_email']) : '';
        $from_name = isset($options['from_name']) ? sanitize_text_field((string) $options['from_name']) : '';
        $headers = isset($options['headers']) && is_array($options['headers']) ? array_values($options['headers']) : array();
        $attachments = isset($options['attachments']) && is_array($options['attachments']) ? array_values($options['attachments']) : array();
        $metadata = isset($options['metadata']) && is_array($options['metadata']) ? $options['metadata'] : array();
        $source = isset($options['source']) ? sanitize_text_field((string) $options['source']) : '';
        $valid_sources = array(self::SOURCE_CORE, self::SOURCE_CAMPAIGN, self::SOURCE_USER_EVENT, self::SOURCE_PLUGIN);
        if (!in_array($source, $valid_sources, true)) {
            $source = $campaign_id > 0 ? self::SOURCE_CAMPAIGN : self::SOURCE_CORE;
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, campaign_id, recipient_email, blog_id, subject, body, from_email, from_name, headers, attachments, metadata, source, status, attempts, scheduled_at, created_at) VALUES (%d, %d, %s, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %s, %s)",
                $site_id,
                $campaign_id,
                $recipient,
                $site_id,
                $subject,
                $body,
                $from_email,
                $from_name,
                wp_json_encode($headers),
                wp_json_encode($attachments),
                wp_json_encode($metadata),
                $source,
                'pending',
                0,
                $scheduled_at,
                $created_at
            )
        );

        if ($result === false) {
            Logger::error('Failed to enqueue email.', array('site_id' => $site_id, 'email' => $email, 'campaign_id' => $campaign_id));
            return false;
        }

        return isset($wpdb->insert_id) ? (int) $wpdb->insert_id : true;
    }

    /**
     * Reset queue rows that have been stuck in "processing" for more than 1 hour back to "pending".
     * This recovers from server crashes, fatal errors, or other mid-process failures.
     */
    public static function recover_stuck_processing_rows(): int
    {
        global $wpdb;

        $table     = $wpdb->base_prefix . 'mnem_queue';
        $threshold = gmdate('Y-m-d H:i:s', time() - (defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600));

        $recovered = (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s WHERE status = %s AND scheduled_at < %s",
                'pending',
                'processing',
                $threshold
            )
        );

        if ($recovered > 0) {
            Logger::warning('Recovered stuck queue rows back to pending.', array('count' => $recovered));
        }

        return $recovered;
    }

    public static function process_batch(int $limit = 20)
    {
        global $wpdb;

        self::recover_stuck_processing_rows();

        $table = $wpdb->base_prefix . 'mnem_queue';
        $now = self::current_time_mysql();
        $limit = max(1, $limit);

        $rate_limit_per_minute = SmtpSettings::get_campaign_rate_limit_per_minute();
        $rate_limit_per_hour = SmtpSettings::get_campaign_rate_limit_per_hour();
        $rate_limit_per_day = SmtpSettings::get_campaign_rate_limit_per_day();

        $identifier_minute = 'campaign_send_' . gmdate('Y-m-d-H-i');
        $identifier_hour = 'campaign_send_' . gmdate('Y-m-d-H');
        $identifier_day = 'campaign_send_' . gmdate('Y-m-d');

        $processed = 0;

        $remaining = $limit;
        $transactional_ids = self::get_pending_ids_by_source($table, $now, $remaining, self::get_transactional_sources());
        foreach ($transactional_ids as $id) {
            $result = self::process_item((int) $id);
            if (!empty($result['processed'])) {
                ++$processed;
            }
        }

        $remaining = $limit - $processed;
        if ($remaining < 1) {
            return $processed;
        }

        if ((int) get_site_option('mnem_campaign_sends_paused', 0) === 1) {
            Logger::info('Campaign queue processing skipped because sending is paused.');
            return $processed;
        }

        if (!RateLimiter::is_allowed($identifier_minute, $rate_limit_per_minute, 60)) {
            Logger::warning('Campaign send rate limit exceeded (per minute)');
            return $processed;
        }

        if (!RateLimiter::is_allowed($identifier_hour, $rate_limit_per_hour, 3600)) {
            Logger::warning('Campaign send rate limit exceeded (per hour)');
            return $processed;
        }

        if (!RateLimiter::is_allowed($identifier_day, $rate_limit_per_day, 86400)) {
            Logger::warning('Campaign send rate limit exceeded (per day)');
            return $processed;
        }

        $campaign_ids = self::get_pending_ids_by_source($table, $now, $remaining, array(self::SOURCE_CAMPAIGN));
        if (empty($campaign_ids)) {
            return $processed;
        }

        $total_campaign_ids = count($campaign_ids);
        $delay_ms = SmtpSettings::get_campaign_delay_between_sends();

        foreach ($campaign_ids as $index => $id) {
            if ($rate_limit_per_minute > 0 && !RateLimiter::is_allowed($identifier_minute, $rate_limit_per_minute, 60)) {
                Logger::info('Campaign send stopped due to per-minute rate limit');
                break;
            }

            if ($rate_limit_per_hour > 0 && !RateLimiter::is_allowed($identifier_hour, $rate_limit_per_hour, 3600)) {
                Logger::info('Campaign send stopped due to per-hour rate limit');
                break;
            }

            if ($rate_limit_per_day > 0 && !RateLimiter::is_allowed($identifier_day, $rate_limit_per_day, 86400)) {
                Logger::info('Campaign send stopped due to per-day rate limit');
                break;
            }

            $result = self::process_item((int) $id);
            if (!empty($result['processed'])) {
                ++$processed;
                RateLimiter::record_action($identifier_minute, 60);
                RateLimiter::record_action($identifier_hour, 3600);
                RateLimiter::record_action($identifier_day, 86400);

                if ($delay_ms > 0 && $index < ($total_campaign_ids - 1)) {
                    usleep($delay_ms * 1000);
                }
            }
        }

        return $processed;
    }

    /**
     * Reset SMS queue rows stuck in "processing" back to "pending".
     */
    public static function recover_stuck_sms_processing_rows(): int
    {
        global $wpdb;

        $table     = $wpdb->base_prefix . 'mnem_sms_queue';
        $threshold = gmdate('Y-m-d H:i:s', time() - (defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600));

        $recovered = (int) $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s WHERE status = %s AND updated_at < %s",
                'pending',
                'processing',
                $threshold
            )
        );

        if ($recovered > 0) {
            Logger::warning('Recovered stuck SMS queue rows back to pending.', array('count' => $recovered));
        }

        return $recovered;
    }

    /**
     * Process a batch of pending SMS items from mnem_sms_queue independently of email processing.
     *
     * @param int $limit Maximum number of SMS items to process.
     * @return int Number of SMS items processed.
     */
    public static function process_sms_batch(int $limit = 20): int
    {
        global $wpdb;

        self::recover_stuck_sms_processing_rows();

        // 1. No SMS Hours check: stop processing entirely if inside the blackout window.
        if (SmsSettings::is_in_no_sms_hours()) {
            $no_sms_hours = SmsSettings::get_no_sms_hours();
            Logger::info(
                'SMS batch processing skipped: currently in no-SMS hours window.',
                array(
                    'current_time'  => function_exists('wp_date') ? (string) wp_date('H:i:s') : gmdate('H:i:s'),
                    'no_sms_window' => $no_sms_hours,
                )
            );
            return 0;
        }

        $table = $wpdb->base_prefix . 'mnem_sms_queue';
        $limit = max(1, $limit);

        // 2. Enforce max SMS per day: query today's sent count directly from DB (no cache).
        $max_per_day = SmsSettings::get_max_sms_per_day();
        $today_date  = function_exists('wp_date') ? (string) wp_date('Y-m-d') : gmdate('Y-m-d');
        $sent_today  = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s AND sent_at >= %s",
                'sent',
                $today_date . ' 00:00:00'
            )
        );

        if ($sent_today >= $max_per_day) {
            Logger::warning(
                'SMS batch processing stopped: daily send limit reached.',
                array(
                    'sent_today'  => $sent_today,
                    'max_per_day' => $max_per_day,
                )
            );
            SmsCampaigns::auto_update_sending_campaign_statuses();
            return 0;
        }

        // Cap the batch to the remaining daily quota.
        $remaining_quota = $max_per_day - $sent_today;
        $limit           = min($limit, $remaining_quota);

        $campaigns_table = $wpdb->base_prefix . 'mnem_sms_campaigns';

        $sms_ids = (array) $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE status = %s"
                    . " AND (sms_campaign_id = 0 OR sms_campaign_id NOT IN ("
                    . "SELECT id FROM {$campaigns_table} WHERE status = %s"
                    . "))"
                    . " ORDER BY id ASC LIMIT %d",
                'pending',
                'paused',
                $limit
            )
        );

        // 3. Get configured inter-message delay in milliseconds.
        $delay_ms        = SmsSettings::get_sms_delay();
        $total_ids       = count($sms_ids);
        $processed       = 0;
        $send_index      = 0; // sequential counter used for delay logic
        $quota_remaining = $remaining_quota; // local decrement; re-validated periodically

        foreach ($sms_ids as $sms_id) {
            // Re-check no-SMS hours before each send to handle window crossings mid-batch.
            if (SmsSettings::is_in_no_sms_hours()) {
                Logger::info(
                    'SMS batch processing paused mid-batch: entered no-SMS hours window.',
                    array(
                        'current_time'       => function_exists('wp_date') ? (string) wp_date('H:i:s') : gmdate('H:i:s'),
                        'no_sms_window'      => SmsSettings::get_no_sms_hours(),
                        'remaining_in_batch' => $total_ids - $send_index,
                    )
                );
                break;
            }

            // Guard against concurrent processes: re-query the DB every 10 sends instead of
            // every single send to avoid excessive DB load while still catching quota overruns.
            if ($quota_remaining <= 0 || ($send_index % 10 === 0 && $send_index > 0)) {
                $current_sent_today = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table} WHERE status = %s AND sent_at >= %s",
                        'sent',
                        $today_date . ' 00:00:00'
                    )
                );
                $quota_remaining = $max_per_day - $current_sent_today;
                if ($quota_remaining <= 0) {
                    Logger::warning(
                        'SMS batch processing stopped mid-batch: daily send limit reached.',
                        array(
                            'sent_today'  => $current_sent_today,
                            'max_per_day' => $max_per_day,
                        )
                    );
                    break;
                }
            }

            if (self::process_sms_item((int) $sms_id)) {
                ++$processed;
                --$quota_remaining;
            }

            // Apply inter-message delay after every send except the last one.
            if ($delay_ms > 0 && $send_index < ($total_ids - 1)) {
                usleep($delay_ms * 1000);
            }

            ++$send_index;
        }

        if ($processed === 0) {
            SmsCampaigns::auto_update_sending_campaign_statuses();
        }

        return $processed;
    }

    /**
     * Process a single SMS queue item: validate, send via the active SMS provider, and update status.
     *
     * @param int  $id    Queue row ID.
     * @param bool $force Whether to force processing for non-processing rows.
     * @return bool True if the SMS was sent successfully, false otherwise.
     */
    private static function process_sms_item(int $id, bool $force = false): bool
    {
        $result = self::process_sms_item_result($id, $force);

        $campaign_id = isset($result['campaign_id']) ? (int) $result['campaign_id'] : 0;
        if ($campaign_id > 0 && !empty($result['processed']) && in_array((string) $result['status'], array('sent', 'failed'), true)) {
            SmsCampaigns::auto_update_campaign_status($campaign_id);
        }

        return !empty($result['success']);
    }

    /**
     * @param int  $id    Queue row ID.
     * @param bool $force Whether to force processing for non-processing rows.
     * @return array{processed:bool,success:bool,status:string,message:string,queue_id:int,provider:string,message_id:string,campaign_id:int}
     */
    private static function process_sms_item_result(int $id, bool $force = false): array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_queue';

        // Claim the row by moving it from 'pending' to 'processing'.
        $claim_time = self::current_time_mysql();
        $claimed = $force
            ? $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, scheduled_at = %s WHERE id = %d AND status <> %s",
                    'processing',
                    $claim_time,
                    $id,
                    'processing'
                )
            )
            : $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d AND status = %s",
                    'processing',
                    $id,
                    'pending'
                )
            );

        if (!$claimed) {
            return array(
                'processed' => false,
                'success' => false,
                'status' => 'not_claimed',
                'message' => $force ? 'Queue item is already processing or unavailable.' : 'Queue item is not ready to process.',
                'queue_id' => $id,
                'provider' => '',
                'message_id' => '',
                'campaign_id' => 0,
            );
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, phone_number, body, sms_campaign_id FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d",
                    'pending',
                    $id
                )
            );
            return array(
                'processed' => false,
                'success' => false,
                'status' => 'pending',
                'message' => 'Queue item could not be loaded.',
                'queue_id' => $id,
                'provider' => '',
                'message_id' => '',
                'campaign_id' => 0,
            );
        }

        $phone   = trim((string) ($row['phone_number'] ?? ''));
        $message = trim((string) ($row['body'] ?? ''));
        $campaign_id = (int) ($row['sms_campaign_id'] ?? 0);

        if ($phone === '' || $message === '') {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d",
                    'failed',
                    $id
                )
            );
            Logger::warning('SMS queue item skipped: missing phone number or message body.', array('queue_id' => $id));
            return array(
                'processed' => true,
                'success' => false,
                'status' => 'failed',
                'message' => 'SMS queue item is missing phone number or message body.',
                'queue_id' => $id,
                'provider' => '',
                'message_id' => '',
                'campaign_id' => $campaign_id,
            );
        }

        if (!class_exists('\\MNEM\\SmsProviderManager')) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d",
                    'pending',
                    $id
                )
            );
            Logger::warning('SMS processing skipped: SmsProviderManager class not available.', array('queue_id' => $id));
            return array(
                'processed' => true,
                'success' => false,
                'status' => 'pending',
                'message' => 'SMS provider manager is not available.',
                'queue_id' => $id,
                'provider' => '',
                'message_id' => '',
                'campaign_id' => $campaign_id,
            );
        }

        $provider = SmsProviderManager::get_active_provider();

        if (!$provider) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d",
                    'pending',
                    $id
                )
            );
            Logger::warning('SMS processing skipped: no active SMS provider configured.', array('queue_id' => $id));
            return array(
                'processed' => true,
                'success' => false,
                'status' => 'pending',
                'message' => 'No active SMS provider configured.',
                'queue_id' => $id,
                'provider' => '',
                'message_id' => '',
                'campaign_id' => $campaign_id,
            );
        }

        $provider_type = is_callable(array(get_class($provider), 'get_provider_key'))
            ? (string) $provider::get_provider_key()
            : '';
        $provider_result = $provider->send($phone, $message);
        $provider_message_id = isset($provider_result['message_id']) ? (string) $provider_result['message_id'] : '';
        $success = !empty($provider_result['success']);

        if ($success) {
            $sent_at = self::current_time_mysql();
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, sent_at = %s, provider_type = %s, provider_message_id = %s WHERE id = %d",
                    'sent',
                    $sent_at,
                    $provider_type,
                    $provider_message_id,
                    $id
                )
            );
            Logger::info('SMS queue item sent successfully.', array('queue_id' => $id, 'phone' => $phone));
            return array(
                'processed' => true,
                'success' => true,
                'status' => 'sent',
                'message' => isset($provider_result['message']) ? (string) $provider_result['message'] : '',
                'queue_id' => $id,
                'provider' => $provider_type,
                'message_id' => $provider_message_id,
                'campaign_id' => $campaign_id,
            );
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, provider_type = %s, provider_message_id = %s WHERE id = %d",
                'failed',
                $provider_type,
                $provider_message_id,
                $id
            )
        );
        Logger::error('SMS queue item failed to send.', array('queue_id' => $id, 'phone' => $phone));
        return array(
            'processed' => true,
            'success' => false,
            'status' => 'failed',
            'message' => isset($provider_result['message']) ? (string) $provider_result['message'] : 'SMS queue item failed to send.',
            'queue_id' => $id,
            'provider' => $provider_type,
            'message_id' => $provider_message_id,
            'campaign_id' => $campaign_id,
        );
    }

    /**
     * @return array{processed:bool,success:bool,status:string,message:string,queue_id:int,provider:string,message_id:string}
     */
    public static function send_now(int $id): array
    {
        if ($id <= 0) {
            return array(
                'processed' => false,
                'success' => false,
                'status' => 'invalid',
                'message' => 'Invalid queue item.',
                'queue_id' => $id,
                'provider' => '',
                'message_id' => '',
            );
        }

        return self::process_item($id, true);
    }

    public static function process_item(int $id, bool $force = false): array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_queue';
        $claim_time = self::current_time_mysql();
        $claimed = $force
            ? $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, scheduled_at = %s WHERE id = %d AND status <> %s",
                    'processing',
                    $claim_time,
                    $id,
                    'processing'
                )
            )
            : $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, scheduled_at = %s WHERE id = %d AND status = %s",
                    'processing',
                    $claim_time,
                    $id,
                    'pending'
                )
            );

        if (!$claimed) {
            return array(
                'processed' => false,
                'success' => false,
                'status' => 'not_claimed',
                'message' => $force ? 'Queue item is already processing or unavailable.' : 'Queue item is not ready to process.',
                'queue_id' => $id,
                'provider' => '',
                'message_id' => '',
            );
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, site_id, blog_id, campaign_id, recipient_email, subject, body, from_email, from_name, headers, attachments, metadata, attempts FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (empty($row)) {
            return array(
                'processed' => false,
                'success' => false,
                'status' => 'processing',
                'message' => 'Queue item could not be loaded.',
                'queue_id' => $id,
                'provider' => '',
                'message_id' => '',
            );
        }

        $blog_id = isset($row['blog_id']) ? (int) $row['blog_id'] : (int) $row['site_id'];
        if ($blog_id <= 0) {
            $blog_id = (int) $row['site_id'];
        }

        $sent = false;
        $status = 'processing';
        $provider_type = '';
        $provider_message_id = '';
        $result = array();

        try {
            $headers = json_decode(isset($row['headers']) ? (string) $row['headers'] : '[]', true);
            $headers = is_array($headers) ? $headers : array();
            $attachments = json_decode(isset($row['attachments']) ? (string) $row['attachments'] : '[]', true);
            $attachments = is_array($attachments) ? $attachments : array();

            $from_header = '';
            if (!empty($row['from_email'])) {
                $from_name = !empty($row['from_name']) ? (string) $row['from_name'] : '';
                $from_header = $from_name !== ''
                    ? 'From: ' . $from_name . ' <' . (string) $row['from_email'] . '>'
                    : 'From: ' . (string) $row['from_email'];
            }

            if (SmtpSettings::is_force_sender_enabled()) {
                $forced_from_email = SmtpSettings::get_sender_email();
                $forced_from_name = SmtpSettings::get_sender_name();
                if ($forced_from_email === '') {
                    $result = array(
                        'success' => false,
                        'message' => 'Force sender is enabled but sender email is not configured. Please configure it in Settings > Sender Settings.',
                        'provider' => '',
                        'message_id' => '',
                        'metadata' => array(),
                    );
                } else {
                    $from_header = $forced_from_name !== ''
                        ? 'From: ' . $forced_from_name . ' <' . $forced_from_email . '>'
                        : 'From: ' . $forced_from_email;
                    Logger::info('Queue email sender overridden by force sender setting.', array(
                        'queue_id' => $id,
                        'original_from' => isset($row['from_email']) ? (string) $row['from_email'] : '',
                        'forced_from' => $forced_from_email,
                    ));
                }
            }

            $headers = array_values(array_filter($headers, static function ($header_line) {
                return stripos((string) $header_line, 'From:') !== 0;
            }));
            if ($from_header !== '') {
                $headers[] = $from_header;
            }

            $headers['__attachments'] = $attachments;

            $body = EmailFormatter::apply_global_header_footer((string) $row['body']);
            $body = EmailTracker::add_tracking_pixel($body, $id);
            $body = EmailTracker::rewrite_links_for_tracking($body, $id);

            $send = static function () use ($row, $headers, $body) {
                return ProviderManager::send_email($row['recipient_email'], $row['subject'], $body, $headers);
            };

            if (empty($result)) {
                $result = class_exists('\\MNEM\\MailInterceptor')
                    ? MailInterceptor::run_without_interception($send)
                    : $send();
            }
            $attempts = (int) $row['attempts'] + 1;
            $attempted_at = self::current_time_mysql();
            $provider_type = isset($result['provider']) ? (string) $result['provider'] : '';
            $provider_message_id = isset($result['message_id']) ? (string) $result['message_id'] : '';
            $provider_metadata = !empty($result['metadata']) ? wp_json_encode($result['metadata']) : null;
            $sent = !empty($result['success']);

            if ($sent) {
                // Persist status and the fully-formatted body (header/footer, tracking pixel, rewritten links)
                // in a single query so the queue preview shows the exact email that was sent.
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET status = %s, attempts = %d, sent_at = %s, provider_type = %s, provider_message_id = %s, provider_metadata = %s, body = %s WHERE id = %d",
                        'sent',
                        $attempts,
                        $attempted_at,
                        $provider_type,
                        $provider_message_id,
                        $provider_metadata,
                        $body,
                        $id
                    )
                );
                $metadata = json_decode(isset($row['metadata']) ? (string) $row['metadata'] : '[]', true);
                $metadata = is_array($metadata) ? $metadata : array();
                $metadata['message_id'] = $provider_message_id;
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET metadata = %s WHERE id = %d",
                        wp_json_encode($metadata),
                        $id
                    )
                );

                Logger::info('Queue email sent.', array('queue_id' => $id, 'blog_id' => $blog_id, 'campaign_id' => (int) $row['campaign_id'], 'recipient_email' => $row['recipient_email'], 'provider' => $provider_type, 'message_id' => $provider_message_id));
                $status = 'sent';

                // Wait briefly for the provider to process the message before polling.
                if ($provider_type !== '' && $provider_message_id !== '') {
                    sleep(2);
                }

                $resolved_status = self::refresh_provider_status($id, $provider_type, $provider_message_id, (string) $row['recipient_email']);
                if ($resolved_status !== '') {
                    $status = $resolved_status;
                }
            } else {
                $status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
                $next_scheduled = $status === 'failed' ? $attempted_at : self::calculate_next_attempt($attempts);

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET status = %s, attempts = %d, scheduled_at = %s, provider_type = %s, provider_message_id = %s, provider_metadata = %s WHERE id = %d",
                        $status,
                        $attempts,
                        $next_scheduled,
                        $provider_type,
                        $provider_message_id,
                        $provider_metadata,
                        $id
                    )
                );

                if ($status === 'failed') {
                    Logger::error('Queue email permanently failed.', array('queue_id' => $id, 'blog_id' => $blog_id, 'campaign_id' => (int) $row['campaign_id'], 'recipient_email' => $row['recipient_email'], 'attempts' => $attempts, 'provider' => $provider_type, 'error' => $result['message']));
                } else {
                    Logger::warning('Queue email send failed; retry scheduled.', array('queue_id' => $id, 'blog_id' => $blog_id, 'campaign_id' => (int) $row['campaign_id'], 'recipient_email' => $row['recipient_email'], 'attempts' => $attempts, 'next_scheduled' => $next_scheduled, 'provider' => $provider_type));
                }
            }

        } catch (\Throwable $e) {
            $status = 'failed';
            $result = array('success' => false, 'message' => $e->getMessage(), 'provider' => '', 'message_id' => '', 'metadata' => array());
            Logger::error('Exception during queue email processing.', array('queue_id' => $id, 'exception' => $e->getMessage()));
            // Mark the item failed so it won't loop forever.
            $attempts = isset($row['attempts']) ? (int) $row['attempts'] + 1 : 1;
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, attempts = %d WHERE id = %d",
                    'failed',
                    $attempts,
                    $id
                )
            );
        } finally {
        }

        if (!empty($row['campaign_id'])) {
            Campaigns::refresh_delivery_stats((int) $row['campaign_id']);
        }

        return array(
            'processed' => true,
            'success' => $sent,
            'status' => $status,
            'message' => isset($result['message']) ? (string) $result['message'] : '',
            'queue_id' => $id,
            'provider' => $provider_type,
            'message_id' => $provider_message_id,
        );
    }

    /**
     * @return array<int,string>
     */
    private static function get_transactional_sources(): array
    {
        return array(
            self::SOURCE_CORE,
            self::SOURCE_PLUGIN,
            self::SOURCE_USER_EVENT,
        );
    }

    /**
     * @param array<int,string> $sources
     * @return array<int,mixed>
     */
    private static function get_pending_ids_by_source(string $table, string $now, int $limit, array $sources): array
    {
        global $wpdb;

        if ($limit < 1 || empty($sources)) {
            return array();
        }

        $placeholders = implode(', ', array_fill(0, count($sources), '%s'));
        $query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                array(
                    "SELECT id FROM {$table} WHERE status = %s AND scheduled_at <= %s AND attempts < %d AND COALESCE(NULLIF(message_type, ''), %s) = %s AND source IN ({$placeholders}) ORDER BY created_at ASC, id ASC LIMIT %d",
                    'pending',
                    $now,
                    self::MAX_ATTEMPTS,
                    'email',
                    'email',
                ),
                $sources,
                array($limit)
            )
        );

        return (array) $wpdb->get_col($query);
    }

    public static function get_stats(?int $site_id = null)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_queue';
        $success_placeholders = implode(', ', array_fill(0, count(self::SUCCESS_STATUSES), '%s'));
        $final_issue_placeholders = implode(', ', array_fill(0, count(self::NON_SUCCESS_FINAL_STATUSES), '%s'));
        if ($site_id === null) {
            $pending = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE status = %s", 'pending'));
            $processing = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE status = %s", 'processing'));
            $sent = (int) $wpdb->get_var(call_user_func_array(array($wpdb, 'prepare'), array_merge(array("SELECT COUNT(1) FROM {$table} WHERE status IN ({$success_placeholders})"), self::SUCCESS_STATUSES)));
            $failed = (int) $wpdb->get_var(call_user_func_array(array($wpdb, 'prepare'), array_merge(array("SELECT COUNT(1) FROM {$table} WHERE status IN ({$final_issue_placeholders})"), self::NON_SUCCESS_FINAL_STATUSES)));
            $next_retry = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT scheduled_at, attempts FROM {$table} WHERE status = %s AND attempts > %d ORDER BY scheduled_at ASC LIMIT %d",
                    'pending',
                    0,
                    1
                ),
                ARRAY_A
            );
        } else {
            $pending = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE site_id = %d AND status = %s", $site_id, 'pending'));
            $processing = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE site_id = %d AND status = %s", $site_id, 'processing'));
            $sent = (int) $wpdb->get_var(call_user_func_array(array($wpdb, 'prepare'), array_merge(array("SELECT COUNT(1) FROM {$table} WHERE site_id = %d AND status IN ({$success_placeholders})", $site_id), self::SUCCESS_STATUSES)));
            $failed = (int) $wpdb->get_var(call_user_func_array(array($wpdb, 'prepare'), array_merge(array("SELECT COUNT(1) FROM {$table} WHERE site_id = %d AND status IN ({$final_issue_placeholders})", $site_id), self::NON_SUCCESS_FINAL_STATUSES)));
            $next_retry = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT scheduled_at, attempts FROM {$table} WHERE site_id = %d AND status = %s AND attempts > %d ORDER BY scheduled_at ASC LIMIT %d",
                    $site_id,
                    'pending',
                    0,
                    1
                ),
                ARRAY_A
            );
        }

        return array(
            'pending' => $pending,
            'processing' => $processing,
            'sent' => $sent,
            'failed' => $failed,
            'next_retry_at' => !empty($next_retry['scheduled_at']) ? $next_retry['scheduled_at'] : '',
            'next_retry_attempts' => !empty($next_retry['attempts']) ? (int) $next_retry['attempts'] : 0,
        );
    }

    public static function retry_failed(int $site_id)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_queue';
        $campaign_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT campaign_id FROM {$table} WHERE site_id = %d AND status = %s AND campaign_id > %d",
                $site_id,
                'failed',
                0
            )
        );
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, attempts = %d, scheduled_at = %s WHERE site_id = %d AND status = %s",
                'pending',
                0,
                self::current_time_mysql(),
                $site_id,
                'failed'
            )
        );

        if ($result !== false && $result > 0) {
            foreach ((array) $campaign_ids as $campaign_id) {
                Campaigns::refresh_delivery_stats((int) $campaign_id);
            }

            Logger::info('Retried failed queue items.', array('site_id' => $site_id, 'count' => (int) $result));
        }

        return $result === false ? 0 : (int) $result;
    }

    public static function delete_item(int $id)
    {
        global $wpdb;

        if ($id <= 0) {
            return false;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, site_id, campaign_id, recipient_email, status FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        if (!is_array($item) || empty($item['id'])) {
            Logger::warning('Queue item deletion skipped because item was not found.', array('queue_id' => $id, 'deleted_by' => get_current_user_id()));
            return false;
        }

        $status = isset($item['status']) ? (string) $item['status'] : '';

        $deleted = $wpdb->query(
            self::prepare_delete_query(
                "DELETE FROM {$table} WHERE id = %d",
                array($id),
                false
            )
        );

        if ($deleted === false || (int) $deleted < 1) {
            Logger::error('Queue item deletion failed.', array('queue_id' => $id, 'status' => $status, 'deleted_by' => get_current_user_id()));
            return false;
        }

        Logger::info('Queue item deleted.', array(
            'queue_id' => $id,
            'site_id' => isset($item['site_id']) ? (int) $item['site_id'] : 0,
            'campaign_id' => isset($item['campaign_id']) ? (int) $item['campaign_id'] : 0,
            'recipient_email' => isset($item['recipient_email']) ? (string) $item['recipient_email'] : '',
            'status' => $status,
            'deleted_by' => get_current_user_id(),
        ));

        return true;
    }

    public static function delete_items(array $ids)
    {
        $queue_ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));

        if (empty($queue_ids)) {
            return 0;
        }

        $deleted = 0;
        $deleted_ids = array();

        foreach ($queue_ids as $queue_id) {
            if (self::delete_item($queue_id)) {
                ++$deleted;
                $deleted_ids[] = $queue_id;
            }
        }

        if ($deleted > 0) {
            Logger::info('Queue items deleted.', array(
                'queue_ids' => $deleted_ids,
                'requested_ids' => $queue_ids,
                'deleted_count' => $deleted,
                'deleted_by' => get_current_user_id(),
            ));
        }

        return $deleted;
    }

    public static function get_recipient_emails(array $ids)
    {
        global $wpdb;

        $queue_ids = array_values(array_unique(array_filter(array_map('intval', $ids), static function ($id) {
            return $id > 0;
        })));

        if (empty($queue_ids)) {
            return array();
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $placeholders = implode(', ', array_fill(0, count($queue_ids), '%d'));
        $emails = (array) $wpdb->get_col(
            call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(
                    array("SELECT DISTINCT recipient_email FROM {$table} WHERE id IN ({$placeholders})"),
                    $queue_ids
                )
            )
        );

        $unique = array();
        foreach ($emails as $email) {
            $email = trim((string) $email);
            if ($email === '') {
                continue;
            }

            $key = strtolower($email);
            if (!isset($unique[$key])) {
                $unique[$key] = $email;
            }
        }

        return array_values($unique);
    }

    public static function delete_by_status(int $site_id, string $status)
    {
        global $wpdb;

        $status = sanitize_text_field($status);
        if (!in_array($status, self::DELETABLE_STATUSES, true)) {
            Logger::warning('Queue deletion by status was rejected.', array('site_id' => $site_id, 'status' => $status, 'deleted_by' => get_current_user_id()));
            return 0;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        if ($site_id > 0) {
            $delete_query = $wpdb->prepare(
                "DELETE FROM {$table} WHERE site_id = %d AND status = %s",
                $site_id,
                $status
            );
        } else {
            $delete_query = $wpdb->prepare(
                "DELETE FROM {$table} WHERE status = %s",
                $status
            );
        }

        $deleted = $wpdb->query($delete_query);

        if ($deleted === false) {
            Logger::error('Queue deletion by status failed.', array('site_id' => $site_id, 'status' => $status, 'deleted_by' => get_current_user_id()));
            return 0;
        }

        Logger::info('Queue items deleted by status.', array(
            'site_id' => $site_id,
            'status' => $status,
            'deleted_count' => (int) $deleted,
            'deleted_by' => get_current_user_id(),
        ));

        return (int) $deleted;
    }

    public static function delete_by_campaign(int $campaign_id)
    {
        global $wpdb;

        if ($campaign_id <= 0) {
            return 0;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $deleted = $wpdb->query(
            self::prepare_delete_query(
                "DELETE FROM {$table} WHERE campaign_id = %d",
                array($campaign_id)
            )
        );

        if ($deleted === false) {
            Logger::error('Queue deletion by campaign failed.', array('campaign_id' => $campaign_id, 'deleted_by' => get_current_user_id()));
            return 0;
        }

        Logger::info('Queue items deleted by campaign.', array(
            'campaign_id' => $campaign_id,
            'deleted_count' => (int) $deleted,
            'deleted_by' => get_current_user_id(),
        ));

        return (int) $deleted;
    }

    public static function calculate_next_attempt(int $attempts)
    {
        $delay = self::BACKOFF_BASE * (2 ** $attempts);

        return gmdate('Y-m-d H:i:s', time() + $delay);
    }

    public static function record_local_event(int $queue_id, string $status, array $metadata = array()): void
    {
        global $wpdb;

        if ($queue_id <= 0) {
            return;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $timestamp = self::current_time_mysql();
        $row = $wpdb->get_row($wpdb->prepare("SELECT status, opened, clicked, provider_metadata FROM {$table} WHERE id = %d", $queue_id), ARRAY_A);
        if (!is_array($row)) {
            Logger::warning('Local tracking event skipped because queue row was not found.', array(
                'queue_id' => $queue_id,
                'status' => $status,
            ));
            return;
        }

        $current_status = isset($row['status']) ? (string) $row['status'] : 'pending';
        $opened = isset($row['opened']) ? (string) $row['opened'] : '';
        $clicked = isset($row['clicked']) ? (string) $row['clicked'] : '';
        $opens_increment = 0;
        $clicks_increment = 0;
        if ($status === 'opened') {
            if ($opened === '') {
                $opened = $timestamp;
            }
            $opens_increment = 1;
        }
        if ($status === 'clicked') {
            if ($opened === '') {
                $opened = $timestamp;
            }
            if ($clicked === '') {
                $clicked = $timestamp;
            }
            $clicks_increment = 1;
        }

        $final_status = self::resolve_status_update($current_status, $status);
        $provider_metadata = self::merge_provider_metadata(isset($row['provider_metadata']) ? (string) $row['provider_metadata'] : '', array(
            'last_local_tracking_event' => array(
                'status' => $status,
                'metadata' => $metadata,
                'recorded_at' => $timestamp,
            ),
        ));

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, opened = %s, clicked = %s, opens_count = COALESCE(opens_count, 0) + %d, clicks_count = COALESCE(clicks_count, 0) + %d, provider_metadata = %s WHERE id = %d",
                $final_status,
                $opened !== '' ? $opened : null,
                $clicked !== '' ? $clicked : null,
                $opens_increment,
                $clicks_increment,
                $provider_metadata,
                $queue_id
            )
        );

        Logger::info('Local tracking event recorded.', array(
            'queue_id' => $queue_id,
            'input_status' => $status,
            'resolved_status' => $final_status,
            'opens_increment' => $opens_increment,
            'clicks_increment' => $clicks_increment,
            'updated' => $updated !== false,
        ));
    }

    public static function get_display_status(array $item): string
    {
        $queue_status = isset($item['status']) ? strtolower((string) $item['status']) : '';
        if ($queue_status === '') {
            $queue_status = !empty($item['sent_at']) ? 'sent' : 'failed';
        }

        return ucwords(str_replace('_', ' ', $queue_status));
    }

    public static function update_status_from_webhook(string $provider, string $message_id, string $status, array $payload = array(), string $recipient = '', string $timestamp = ''): bool
    {
        global $wpdb;

        $status = sanitize_text_field($status);
        if (!in_array($status, self::WEBHOOK_STATUSES, true)) {
            return false;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $recipient = sanitize_email($recipient);
        $timestamp = $timestamp !== '' ? sanitize_text_field($timestamp) : self::current_time_mysql();
        $row = null;

        if ($message_id !== '') {
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, status, opened, clicked, provider_metadata FROM {$table} WHERE provider_type = %s AND provider_message_id = %s ORDER BY id DESC LIMIT %d",
                    $provider,
                    $message_id,
                    1
                ),
                ARRAY_A
            );
        }

        if (!is_array($row) && $recipient !== '') {
            $fallback_statuses = array_merge(self::SUCCESS_STATUSES, array('deferred'));
            $fallback_placeholders = implode(', ', array_fill(0, count($fallback_statuses), '%s'));
            $row = $wpdb->get_row(
                call_user_func_array(
                    array($wpdb, 'prepare'),
                    array_merge(
                        array(
                            "SELECT id, status, opened, clicked, provider_metadata FROM {$table} WHERE provider_type = %s AND recipient_email = %s AND status IN ({$fallback_placeholders}) ORDER BY id DESC LIMIT %d",
                            $provider,
                            $recipient,
                        ),
                        $fallback_statuses,
                        array(1)
                    )
                ),
                ARRAY_A
            );
        }

        if (!is_array($row) || empty($row['id'])) {
            Logger::warning('Webhook status update skipped because queue row was not found.', array(
                'provider' => $provider,
                'message_id' => $message_id,
                'recipient' => $recipient,
                'status' => $status,
            ));
            return false;
        }

        $current_status = isset($row['status']) ? (string) $row['status'] : 'pending';
        $opened = isset($row['opened']) ? (string) $row['opened'] : '';
        $clicked = isset($row['clicked']) ? (string) $row['clicked'] : '';
        $opens_increment = 0;
        $clicks_increment = 0;
        if ($status === 'opened') {
            if ($opened === '') {
                $opened = $timestamp;
            }
            $opens_increment = 1;
        }
        if ($status === 'clicked') {
            if ($opened === '') {
                $opened = $timestamp;
            }
            if ($clicked === '') {
                $clicked = $timestamp;
            }
            $clicks_increment = 1;
        }

        $provider_metadata = self::merge_provider_metadata(isset($row['provider_metadata']) ? (string) $row['provider_metadata'] : '', array(
            'last_webhook_event' => array(
                'provider' => $provider,
                'status' => $status,
                'payload' => $payload,
                'received_at' => $timestamp,
                'message_id' => $message_id,
                'recipient' => $recipient,
            ),
        ));

        $resolved_status = self::resolve_status_update($current_status, $status);
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, opened = %s, clicked = %s, opens_count = COALESCE(opens_count, 0) + %d, clicks_count = COALESCE(clicks_count, 0) + %d, provider_metadata = %s WHERE id = %d",
                $resolved_status,
                $opened !== '' ? $opened : null,
                $clicked !== '' ? $clicked : null,
                $opens_increment,
                $clicks_increment,
                $provider_metadata,
                (int) $row['id']
            )
        );

        Logger::info('Webhook status update processed.', array(
            'queue_id' => (int) $row['id'],
            'provider' => $provider,
            'message_id' => $message_id,
            'received_status' => $status,
            'resolved_status' => $resolved_status,
            'opens_increment' => $opens_increment,
            'clicks_increment' => $clicks_increment,
            'updated' => $updated !== false,
        ));

        return $updated !== false;
    }

    public static function map_webhook_status(string $provider, string $event_type, array $payload = array()): string
    {
        $provider = strtolower(trim($provider));
        $event_type = strtolower(trim($event_type));

        switch ($provider) {
            case 'sendgrid':
                $reason = strtolower((string) ($payload['reason'] ?? ''));
                $map = array(
                    'processed' => 'sent',
                    'delivered' => 'delivered',
                    'open' => 'opened',
                    'click' => 'clicked',
                    'deferred' => 'deferred',
                    'bounce' => 'bounce',
                    'spamreport' => 'complaint',
                    'unsubscribe' => 'unsubscribed',
                    'group_unsubscribe' => 'unsubscribed',
                    'dropped' => strpos($reason, 'invalid') !== false ? 'invalid_email' : 'failed',
                );
                break;
            case 'mailgun':
                $severity = strtolower((string) ($payload['severity'] ?? ($payload['delivery-status']['severity'] ?? '')));
                $map = array(
                    'accepted' => 'sent',
                    'delivered' => 'delivered',
                    'opened' => 'opened',
                    'clicked' => 'clicked',
                    'complained' => 'complaint',
                    'unsubscribed' => 'unsubscribed',
                    'failed' => $severity === 'temporary' ? 'soft_bounce' : 'bounce',
                );
                break;
            case 'brevo':
                $map = array(
                    'sent' => 'sent',
                    'delivered' => 'delivered',
                    'opened' => 'opened',
                    'unique_opened' => 'opened',
                    'click' => 'clicked',
                    'hard_bounce' => 'bounce',
                    'soft_bounce' => 'soft_bounce',
                    'invalid_email' => 'invalid_email',
                    'deferred' => 'deferred',
                    'spam' => 'complaint',
                    'complaint' => 'complaint',
                    'unsubscribed' => 'unsubscribed',
                    'blocked' => 'suppressed',
                    'error' => 'failed',
                );
                break;
            case 'postmark':
                $bounce_type = strtolower((string) ($payload['Type'] ?? ''));
                $map = array(
                    'delivery' => 'delivered',
                    'open' => 'opened',
                    'click' => 'clicked',
                    'spamcomplaint' => 'complaint',
                    'subscriptionchange' => 'unsubscribed',
                    'bounce' => $bounce_type === 'transient' ? 'soft_bounce' : 'bounce',
                );
                break;
            case 'smtp2go':
                $map = array(
                    'processed' => 'sent',
                    'sent' => 'sent',
                    'delivered' => 'delivered',
                    'open' => 'opened',
                    'click' => 'clicked',
                    'bounce' => 'bounce',
                    'soft_bounce' => 'soft_bounce',
                    'invalid' => 'invalid_email',
                    'deferred' => 'deferred',
                    'spam' => 'complaint',
                    'unsubscribe' => 'unsubscribed',
                    'blocked' => 'suppressed',
                    'rejected' => 'rejected',
                    'failed' => 'failed',
                );
                break;
            default:
                $map = array();
                break;
        }

        return isset($map[$event_type]) ? $map[$event_type] : '';
    }

    public static function sync_recent_provider_statuses(int $limit = 500, int $days = 30): int
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_queue';
        $limit = max(1, $limit);
        $days = max(1, $days);
        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * (defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400)));
        $syncable_statuses = array('sent', 'processing', 'deferred', 'soft_bounce');
        $status_placeholders = implode(', ', array_fill(0, count($syncable_statuses), '%s'));

        $rows = (array) $wpdb->get_results(
            call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(
                    array(
                        "SELECT id, provider_type, provider_message_id, recipient_email, status FROM {$table} WHERE status IN ({$status_placeholders}) AND provider_type <> '' AND provider_message_id <> '' AND sent_at >= %s ORDER BY sent_at DESC LIMIT %d",
                    ),
                    $syncable_statuses,
                    array($threshold, $limit)
                )
            ),
            ARRAY_A
        );

        $updated = 0;
        foreach ($rows as $row) {
            $queue_id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($queue_id <= 0) {
                continue;
            }

            $current_status = isset($row['status']) ? (string) $row['status'] : '';
            $actual_status = self::retrieve_message_status(
                isset($row['provider_type']) ? (string) $row['provider_type'] : '',
                isset($row['provider_message_id']) ? (string) $row['provider_message_id'] : '',
                isset($row['recipient_email']) ? (string) $row['recipient_email'] : ''
            );

            if ($actual_status === '' || $actual_status === $current_status) {
                continue;
            }

            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d",
                    self::resolve_status_update($current_status, $actual_status),
                    $queue_id
                )
            );
            ++$updated;
        }

        Logger::info('Provider status sync run completed.', array(
            'checked' => count($rows),
            'updated' => $updated,
        ));

        return $updated;
    }

    public static function retrieve_message_status(string $provider_type, string $message_id, string $recipient_email = ''): string
    {
        $provider_type = strtolower(trim($provider_type));
        $message_id = trim($message_id);
        if ($provider_type === '' || $message_id === '') {
            Logger::warning('Provider status lookup skipped because provider or message id is missing.', array(
                'provider' => $provider_type,
                'message_id' => $message_id,
            ));
            return '';
        }

        switch ($provider_type) {
            case 'sendgrid':
                $status = self::retrieve_sendgrid_message_status($message_id);
                break;
            case 'brevo':
                $status = self::retrieve_brevo_message_status($message_id);
                break;
            case 'mailgun':
                $status = self::retrieve_mailgun_message_status($message_id);
                break;
            case 'aws':
            case 'aws_ses':
                $status = self::retrieve_aws_ses_message_status($message_id);
                break;
            case 'postmark':
                $status = self::retrieve_postmark_message_status($message_id);
                break;
            case 'smtp2go':
                $status = self::retrieve_smtp2go_message_status($message_id);
                break;
            default:
                $status = '';
        }

        if (function_exists('apply_filters')) {
            $status = (string) apply_filters('mnem_provider_message_status', $status, $provider_type, $message_id, $recipient_email);
        }

        $status = sanitize_text_field($status);
        $valid_status = in_array($status, self::WEBHOOK_STATUSES, true) ? $status : '';

        Logger::info('Provider status lookup completed.', array(
            'provider' => $provider_type,
            'message_id' => $message_id,
            'recipient' => $recipient_email,
            'result_status' => $valid_status,
        ));

        return $valid_status;
    }

    public static function is_suppressed(int $site_id, string $email)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_suppression';
        $email = strtolower(trim(sanitize_email($email)));
        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE site_id = %d AND email = %s",
                $site_id,
                $email
            )
        );

        return (int) $found > 0;
    }

    private static function current_time_mysql()
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }

    private static function refresh_provider_status(int $queue_id, string $provider_type, string $message_id, string $recipient_email): string
    {
        global $wpdb;

        if ($queue_id <= 0 || trim($provider_type) === '' || trim($message_id) === '') {
            return '';
        }

        $actual_status = self::retrieve_message_status($provider_type, $message_id, $recipient_email);
        if ($actual_status === '') {
            return '';
        }

        if ($actual_status === 'sent') {
            // "sent" means no status upgrade yet; queue a delayed re-check instead of blocking send flow.
            self::schedule_delayed_status_refresh($queue_id);
            return '';
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $current_status = (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$table} WHERE id = %d LIMIT %d", $queue_id, 1));
        if ($current_status === '') {
            return '';
        }

        $resolved_status = self::resolve_status_update($current_status, $actual_status);
        if ($resolved_status !== $current_status) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d",
                    $resolved_status,
                    $queue_id
                )
            );
        }

        return $resolved_status;
    }

    public static function refresh_single_item_status(int $queue_id): void
    {
        global $wpdb;

        if ($queue_id <= 0) {
            return;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT status, provider_type, provider_message_id, recipient_email FROM {$table} WHERE id = %d LIMIT %d",
                $queue_id,
                1
            ),
            ARRAY_A
        );

        if (!is_array($row) || empty($row['provider_type']) || empty($row['provider_message_id'])) {
            return;
        }

        $current_status = isset($row['status']) ? (string) $row['status'] : '';
        $actual_status = self::retrieve_message_status(
            (string) $row['provider_type'],
            (string) $row['provider_message_id'],
            isset($row['recipient_email']) ? (string) $row['recipient_email'] : ''
        );
        if ($actual_status === '' || $actual_status === $current_status) {
            return;
        }

        $resolved_status = self::resolve_status_update($current_status, $actual_status);
        if ($resolved_status === $current_status) {
            return;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s WHERE id = %d",
                $resolved_status,
                $queue_id
            )
        );
    }

    private static function retrieve_sendgrid_message_status(string $message_id): string
    {
        $api_key = self::get_provider_secret('sendgrid', 'api_key');
        if ($api_key === '') {
            return '';
        }

        $response = wp_remote_get(
            'https://api.sendgrid.com/v3/messages/' . rawurlencode($message_id),
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return '';
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return '';
        }

        $raw_status = '';
        foreach (array('status', 'event', 'last_event') as $key) {
            if (!empty($body[$key])) {
                $raw_status = (string) $body[$key];
                break;
            }
        }
        if ($raw_status === '' && !empty($body['events']) && is_array($body['events'])) {
            $latest_event = end($body['events']);
            if (is_array($latest_event) && !empty($latest_event['event'])) {
                $raw_status = (string) $latest_event['event'];
            }
        }

        return self::map_provider_lookup_status('sendgrid', $raw_status);
    }

    private static function retrieve_brevo_message_status(string $message_id): string
    {
        $api_key = self::get_provider_secret('brevo', 'api_key');
        if ($api_key === '') {
            return '';
        }

        $response = wp_remote_post(
            'https://api.brevo.com/v3/smtp/email-events',
            array(
                'headers' => array(
                    'api-key'      => $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode(array(
                    'messageId' => $message_id,
                    'limit'     => 1,
                )),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return '';
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['events'])) {
            return '';
        }

        $latest_event = reset($body['events']);
        if (!is_array($latest_event) || empty($latest_event['event'])) {
            return '';
        }

        return self::map_provider_lookup_status('brevo', (string) $latest_event['event']);
    }

    private static function retrieve_mailgun_message_status(string $message_id): string
    {
        $api_key = self::get_provider_secret('mailgun', 'api_key');
        $domain  = self::get_provider_config('mailgun', 'domain');

        if ($api_key === '' || $domain === '') {
            return '';
        }

        $response = wp_remote_get(
            'https://api.mailgun.net/v3/' . urlencode($domain) . '/events',
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode('api:' . $api_key),
                ),
                'body'    => array('message-id' => $message_id),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return '';
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['items'])) {
            return '';
        }

        $latest = reset($body['items']);
        if (!is_array($latest)) {
            return '';
        }

        // Mailgun uses event='failed' with severity='permanent' (hard bounce) or severity='temporary' (soft bounce).
        if (isset($latest['event']) && (string) $latest['event'] === 'failed') {
            $severity = isset($latest['severity']) ? strtolower((string) $latest['severity']) : 'permanent';
            $event    = $severity === 'temporary' ? 'temporary_failed' : 'failed';
        } else {
            $event = isset($latest['event']) ? (string) $latest['event'] : '';
        }

        return self::map_provider_lookup_status('mailgun', $event);
    }

    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
    private static function retrieve_aws_ses_message_status(string $message_id): string
    {
        // AWS SES status is webhook-only (SNS); direct API lookup is not available.
        return '';
    }

    private static function retrieve_postmark_message_status(string $message_id): string
    {
        $api_key = self::get_provider_secret('postmark', 'api_key');
        if ($api_key === '') {
            return '';
        }

        $response = wp_remote_get(
            'https://api.postmarkapp.com/messages/outbound/' . urlencode($message_id) . '/events',
            array(
                'headers' => array(
                    'X-Postmark-Server-Token' => $api_key,
                    'Content-Type'            => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return '';
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['Events'])) {
            return '';
        }

        $latest = reset($body['Events']);
        $event  = isset($latest['Type']) ? (string) $latest['Type'] : '';

        return self::map_provider_lookup_status('postmark', $event);
    }

    private static function retrieve_smtp2go_message_status(string $message_id): string
    {
        $api_key = self::get_provider_secret('smtp2go', 'api_key');
        if ($api_key === '') {
            return '';
        }

        $response = wp_remote_post(
            'https://api.smtp2go.com/v3/message_status',
            array(
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode(array(
                    'api_key'    => $api_key,
                    'message_id' => $message_id,
                )),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return '';
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['status'])) {
            return '';
        }

        return self::map_provider_lookup_status('smtp2go', (string) $body['status']);
    }

    private static function map_provider_lookup_status(string $provider, string $status): string
    {
        $provider = strtolower(trim($provider));
        $status   = strtolower(trim($status));
        if ($status === '') {
            return '';
        }

        if ($provider === 'sendgrid') {
            $map = array(
                'processed'   => 'sent',
                'delivered'   => 'delivered',
                'deferred'    => 'deferred',
                'bounce'      => 'bounce',
                'dropped'     => 'failed',
                'spamreport'  => 'complaint',
                'unsubscribe' => 'unsubscribed',
                'open'        => 'opened',
                'click'       => 'clicked',
            );
        } elseif ($provider === 'brevo') {
            $map = array(
                'sent'           => 'sent',
                'delivered'      => 'delivered',
                'opened'         => 'opened',
                'unique_opened'  => 'opened',
                'click'          => 'clicked',
                'hard_bounce'    => 'bounce',
                'soft_bounce'    => 'soft_bounce',
                'invalid_email'  => 'invalid_email',
                'deferred'       => 'deferred',
                'spam'           => 'complaint',
                'complaint'      => 'complaint',
                'unsubscribed'   => 'unsubscribed',
                'blocked'        => 'suppressed',
                'error'          => 'failed',
            );
        } elseif ($provider === 'mailgun') {
            $map = array(
                'accepted'         => 'sent',
                'delivered'        => 'delivered',
                'opened'           => 'opened',
                'clicked'          => 'clicked',
                'complained'       => 'complaint',
                'unsubscribed'     => 'unsubscribed',
                'failed'           => 'bounce',
                'temporary_failed' => 'soft_bounce',
            );
        } elseif ($provider === 'postmark') {
            $map = array(
                'delivery'           => 'delivered',
                'open'               => 'opened',
                'click'              => 'clicked',
                'spamcomplaint'      => 'complaint',
                'subscriptionchange' => 'unsubscribed',
                'bounce'             => 'bounce',
            );
        } elseif ($provider === 'smtp2go') {
            $map = array(
                'processed'   => 'sent',
                'sent'        => 'sent',
                'delivered'   => 'delivered',
                'open'        => 'opened',
                'click'       => 'clicked',
                'bounce'      => 'bounce',
                'soft_bounce' => 'soft_bounce',
                'invalid'     => 'invalid_email',
                'deferred'    => 'deferred',
                'spam'        => 'complaint',
                'unsubscribe' => 'unsubscribed',
                'blocked'     => 'suppressed',
                'rejected'    => 'rejected',
                'failed'      => 'failed',
            );
        } else {
            $map = array();
        }

        return isset($map[$status]) ? $map[$status] : '';
    }

    private static function get_provider_secret(string $provider_type, string $field): string
    {
        $settings = SmtpSettings::get_all();
        $provider_configs = isset($settings['provider_config']) && is_array($settings['provider_config'])
            ? $settings['provider_config']
            : array();
        $provider_config = isset($provider_configs[$provider_type]) && is_array($provider_configs[$provider_type])
            ? $provider_configs[$provider_type]
            : array();
        $secret = isset($provider_config[$field]) ? (string) $provider_config[$field] : '';
        if ($secret === '') {
            return '';
        }

        $decoded = base64_decode($secret, true);
        return $decoded === false ? $secret : $decoded;
    }

    private static function get_provider_config(string $provider_type, string $field): string
    {
        $settings = SmtpSettings::get_all();
        $provider_configs = isset($settings['provider_config']) && is_array($settings['provider_config'])
            ? $settings['provider_config']
            : array();
        $provider_config = isset($provider_configs[$provider_type]) && is_array($provider_configs[$provider_type])
            ? $provider_configs[$provider_type]
            : array();

        return isset($provider_config[$field]) ? (string) $provider_config[$field] : '';
    }

    private static function schedule_delayed_status_refresh(int $queue_id): void
    {
        if ($queue_id <= 0) {
            return;
        }

        if (!function_exists('wp_schedule_single_event')) {
            return;
        }

        wp_schedule_single_event(time() + self::STATUS_REFRESH_DELAY_SECONDS, self::STATUS_REFRESH_HOOK, array($queue_id));
    }

    public static function resolve_status_update(string $current_status, string $new_status): string
    {
        if ($new_status === '') {
            return $current_status;
        }

        // Terminal issue statuses (bounce, unsubscribed, complaint, suppressed, etc.)
        // are authoritative, final states reported by the provider. Always allow the
        // transition to one of these, even from a previously recorded success status
        // (sent/delivered/opened/clicked), since the provider may only learn of a
        // bounce, unsubscribe, or block after an earlier success event was recorded.
        if (in_array($new_status, self::TERMINAL_ISSUE_STATUSES, true)) {
            return $new_status;
        }

        // Once a terminal issue status has been recorded it is final; don't let a
        // later success status downgrade it back into the success lifecycle.
        if (in_array($current_status, self::TERMINAL_ISSUE_STATUSES, true) && in_array($new_status, self::SUCCESS_STATUSES, true)) {
            return $current_status;
        }

        // Within the success lifecycle (sent -> delivered -> opened -> clicked), only
        // allow forward movement; block nonsensical backward transitions such as
        // delivered -> sent or clicked -> opened.
        $success_order = array('sent' => 1, 'delivered' => 2, 'opened' => 3, 'clicked' => 4);
        if (isset($success_order[$new_status], $success_order[$current_status]) && $success_order[$new_status] < $success_order[$current_status]) {
            return $current_status;
        }

        return $new_status;
    }

    private static function merge_provider_metadata(string $existing, array $updates): string
    {
        $metadata = json_decode($existing, true);
        if (!is_array($metadata)) {
            $metadata = array();
        }

        foreach ($updates as $key => $value) {
            $metadata[$key] = $value;
        }

        return wp_json_encode($metadata);
    }

    private static function extract_first_email(string $recipient)
    {
        $parts = preg_split('/[,;]+/', $recipient);
        if (!is_array($parts)) {
            return '';
        }

        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/<([^>]+)>/', $part, $matches)) {
                $candidate = sanitize_email($matches[1]);
            } else {
                $candidate = sanitize_email($part);
            }

            if ($candidate !== '' && is_email($candidate)) {
                return strtolower($candidate);
            }
        }

        return '';
    }

    private static function prepare_delete_query(string $query, array $args, bool $add_status_filter = true)
    {
        global $wpdb;

        if ($add_status_filter) {
            $has_where = preg_match('/\bWHERE\b/i', $query) === 1;
            $query_ends_with_where = preg_match('/\bWHERE\s*$/i', rtrim($query)) === 1;
            if ($has_where) {
                $query .= $query_ends_with_where ? ' ' : ' AND ';
            } else {
                $query .= ' WHERE ';
            }
            $query .= 'status IN (' . implode(', ', array_fill(0, count(self::DELETABLE_STATUSES), '%s')) . ')';
            $args = array_merge($args, self::DELETABLE_STATUSES);
        }

        return call_user_func_array(array($wpdb, 'prepare'), array_merge(array($query), $args));
    }

    /**
     * Update the queue status for an SMS message based on a provider delivery webhook.
     *
     * Looks up the queue row by provider_message_id, maps the provider-specific status
     * to a canonical queue status via SmsProviderStatusMap, then updates the row.
     *
     * @param string               $message_id      Provider-issued message ID.
     * @param string               $provider        Provider key (e.g. 'twilio').
     * @param string               $queue_status    Already-mapped canonical queue status.
     * @param string               $provider_status Raw provider status.
     * @param array<string,mixed>  $metadata        Raw webhook payload for logging.
     */
    public static function update_sms_status_from_provider(
        string $message_id,
        string $provider,
        string $queue_status,
        string $provider_status,
        array $metadata = array()
    ): bool {
        global $wpdb;

        if ($message_id === '' || $queue_status === '') {
            return false;
        }

        if (!in_array($queue_status, self::WEBHOOK_STATUSES, true)) {
            return false;
        }

        $table = $wpdb->base_prefix . 'mnem_sms_queue';
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, status, provider_metadata FROM {$table} WHERE provider_message_id = %s AND provider_type = %s ORDER BY id DESC LIMIT %d",
                $message_id,
                $provider,
                1
            ),
            ARRAY_A
        );

        if (!is_array($row) || empty($row['id'])) {
            Logger::warning('SMS webhook: queue row not found.', array(
                'provider'   => $provider,
                'message_id' => $message_id,
                'status'     => $queue_status,
            ));
            return false;
        }

        // Idempotency: never move backward in status lifecycle.
        $status_order = array_flip(array('pending', 'processing', 'sent', 'delivered', 'opened', 'clicked', 'bounce', 'soft_bounce', 'invalid_email', 'deferred', 'complaint', 'unsubscribed', 'suppressed', 'failed', 'rejected'));
        $current_order = isset($status_order[$row['status']]) ? $status_order[$row['status']] : -1;
        $new_order     = isset($status_order[$queue_status])  ? $status_order[$queue_status]  : -1;
        if ($new_order < $current_order && $new_order !== -1) {
            return $wpdb->query($wpdb->prepare(
                "UPDATE {$table}
                SET provider_status = %s, provider_status_checked_at = %s,
                    last_sync_error = NULL, sync_attempts = 0
                WHERE id = %d AND status = %s",
                $provider_status,
                self::current_time_mysql(),
                (int) $row['id'],
                (string) $row['status']
            )) !== false;
        }

        $merged_meta = self::merge_provider_metadata(
            isset($row['provider_metadata']) ? (string) $row['provider_metadata'] : '',
            array(
                'sms_webhook' => array(
                    'provider'        => $provider,
                    'provider_status' => $provider_status,
                    'received_at'     => self::current_time_mysql(),
                    'payload'         => $metadata,
                ),
            )
        );

        $timestamp = self::current_time_mysql();
        $updated   = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET status = %s, provider_status = %s, provider_status_checked_at = %s,
                    last_sync_error = NULL, sync_attempts = 0,
                    sent_at = COALESCE(sent_at, %s), provider_metadata = %s
                WHERE id = %d AND status = %s",
                $queue_status,
                $provider_status,
                $timestamp,
                $timestamp,
                $merged_meta,
                (int) $row['id'],
                (string) $row['status']
            )
        );

        if ($updated !== false) {
            Logger::info('SMS queue status updated from provider webhook.', array(
                'id'          => (int) $row['id'],
                'provider'    => $provider,
                'message_id'  => $message_id,
                'old_status'  => $row['status'],
                'new_status'  => $queue_status,
            ));
            return true;
        }

        return false;
    }

    public static function record_sms_sync_failure(string $message_id, string $provider, string $error): bool
    {
        global $wpdb;

        if ($message_id === '') {
            return false;
        }

        $table = $wpdb->base_prefix . 'mnem_sms_queue';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, sync_attempts FROM {$table}
            WHERE provider_message_id = %s AND provider_type = %s
            ORDER BY id DESC LIMIT %d",
            $message_id,
            $provider,
            1
        ), ARRAY_A);
        if (!is_array($row) || empty($row['id'])) {
            return false;
        }

        $attempts = (int) $row['sync_attempts'] + 1;
        if ($attempts >= 3) {
            $query = $wpdb->prepare(
                "UPDATE {$table}
                SET sync_attempts = %d, provider_status_checked_at = %s, last_sync_error = %s
                WHERE id = %d",
                $attempts,
                self::current_time_mysql(),
                $error,
                (int) $row['id']
            );
        } else {
            $query = $wpdb->prepare(
                "UPDATE {$table}
                SET sync_attempts = %d, provider_status_checked_at = %s, last_sync_error = NULL
                WHERE id = %d",
                $attempts,
                self::current_time_mysql(),
                (int) $row['id']
            );
        }

        return $wpdb->query($query) !== false;
    }
}
