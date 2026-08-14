<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Queue
{
    public const MAX_ATTEMPTS = 3;
    public const BACKOFF_BASE = 300;
    public const DELETABLE_STATUSES = array('pending', 'failed');
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

        $table = $wpdb->prefix . 'mnem_queue';
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

    public static function process_batch(int $limit = 20)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_queue';
        $now = self::current_time_mysql();
        $limit = max(1, $limit);

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE status = %s AND scheduled_at <= %s AND attempts < %d ORDER BY blog_id ASC, scheduled_at ASC LIMIT %d",
                'pending',
                $now,
                self::MAX_ATTEMPTS,
                $limit
            )
        );

        if (empty($ids)) {
            return 0;
        }

        $processed = 0;

        foreach ($ids as $id) {
            $result = self::process_item((int) $id);
            if (!empty($result['processed'])) {
                ++$processed;
            }
        }

        return $processed;
    }

    /**
     * @return array{processed:bool,success:bool,status:string,message:string,queue_id:int,provider:string,message_id:string}
     */
    public static function process_item(int $id): array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_queue';
        $claimed = $wpdb->query(
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
                'status' => 'pending',
                'message' => 'Queue item is not ready to process.',
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

        if ($blog_id > 0 && function_exists('switch_to_blog')) {
            switch_to_blog($blog_id);
        }

        try {
            $headers = json_decode(isset($row['headers']) ? (string) $row['headers'] : '[]', true);
            $headers = is_array($headers) ? $headers : array();
            $attachments = json_decode(isset($row['attachments']) ? (string) $row['attachments'] : '[]', true);
            $attachments = is_array($attachments) ? $attachments : array();

            if (!empty($row['from_email'])) {
                $from_name = !empty($row['from_name']) ? (string) $row['from_name'] : '';
                $headers[] = $from_name !== ''
                    ? 'From: ' . $from_name . ' <' . (string) $row['from_email'] . '>'
                    : 'From: ' . (string) $row['from_email'];
            }

            $headers['__attachments'] = $attachments;

            $send = static function () use ($row, $headers) {
                return ProviderManager::send_email($row['recipient_email'], $row['subject'], $row['body'], $headers);
            };

            $result = class_exists('\\MNEM\\MailInterceptor')
                ? MailInterceptor::run_without_interception($send)
                : $send();
            $attempts = (int) $row['attempts'] + 1;
            $processed_at = self::current_time_mysql();
            $provider_type = isset($result['provider']) ? (string) $result['provider'] : '';
            $provider_message_id = isset($result['message_id']) ? (string) $result['message_id'] : '';
            $provider_metadata = !empty($result['metadata']) ? wp_json_encode($result['metadata']) : null;
            $sent = !empty($result['success']);

            if ($sent) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET status = %s, attempts = %d, processed_at = %s, sent_at = %s, provider_type = %s, provider_message_id = %s, provider_metadata = %s WHERE id = %d",
                        'sent',
                        $attempts,
                        $processed_at,
                        $processed_at,
                        $provider_type,
                        $provider_message_id,
                        $provider_metadata,
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

                $tracking_headers = $headers;
                unset($tracking_headers['__attachments']);
                EmailTracking::store_sent_email($id, $row, $result, $tracking_headers);

                Logger::info('Queue email sent.', array('queue_id' => $id, 'blog_id' => $blog_id, 'campaign_id' => (int) $row['campaign_id'], 'recipient_email' => $row['recipient_email'], 'provider' => $provider_type, 'message_id' => $provider_message_id));
                $status = 'sent';
            } else {
                $status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
                $next_scheduled = $status === 'failed' ? $processed_at : self::calculate_next_attempt($attempts);

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET status = %s, attempts = %d, scheduled_at = %s, processed_at = %s, provider_type = %s, provider_message_id = %s, provider_metadata = %s WHERE id = %d",
                        $status,
                        $attempts,
                        $next_scheduled,
                        $processed_at,
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
        } finally {
            if ($blog_id > 0 && function_exists('restore_current_blog')) {
                restore_current_blog();
            }
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

    public static function get_stats(?int $site_id = null)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_queue';
        if ($site_id === null) {
            $pending = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE status = %s", 'pending'));
            $processing = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE status = %s", 'processing'));
            $sent = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE status = %s", 'sent'));
            $failed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE status = %s", 'failed'));
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
            $sent = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE site_id = %d AND status = %s", $site_id, 'sent'));
            $failed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE site_id = %d AND status = %s", $site_id, 'failed'));
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

        $table = $wpdb->prefix . 'mnem_queue';
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
                "UPDATE {$table} SET status = %s, attempts = %d, scheduled_at = %s, processed_at = %s WHERE site_id = %d AND status = %s",
                'pending',
                0,
                self::current_time_mysql(),
                null,
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

        $table = $wpdb->prefix . 'mnem_queue';
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
        if (!in_array($status, self::DELETABLE_STATUSES, true)) {
            Logger::warning('Queue item deletion skipped because status is not deletable.', array('queue_id' => $id, 'status' => $status, 'deleted_by' => get_current_user_id()));
            return false;
        }

        $deleted = $wpdb->query(
            self::prepare_delete_query(
                "DELETE FROM {$table} WHERE id = %d",
                array($id)
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

    public static function delete_by_status(int $site_id, string $status)
    {
        global $wpdb;

        $status = sanitize_text_field($status);
        if (!in_array($status, self::DELETABLE_STATUSES, true)) {
            Logger::warning('Queue deletion by status was rejected.', array('site_id' => $site_id, 'status' => $status, 'deleted_by' => get_current_user_id()));
            return 0;
        }

        $table = $wpdb->prefix . 'mnem_queue';
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

        $table = $wpdb->prefix . 'mnem_queue';
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

    public static function is_suppressed(int $site_id, string $email)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_suppression';
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
}
