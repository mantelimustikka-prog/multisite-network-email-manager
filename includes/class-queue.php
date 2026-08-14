<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Queue
{
    public const MAX_ATTEMPTS = 3;
    public const BACKOFF_BASE = 300;

    public static function enqueue(int $site_id, string $email, string $subject, string $body, int $campaign_id = 0)
    {
        global $wpdb;

        $email = strtolower(trim(sanitize_email($email)));
        if ($email === '' || !is_email($email)) {
            return false;
        }

        if (self::is_suppressed($site_id, $email)) {
            Logger::info('Skipped queue insert for suppressed recipient.', array('site_id' => $site_id, 'email' => $email, 'campaign_id' => $campaign_id));
            return false;
        }

        $table = $wpdb->prefix . 'mnem_queue';
        $scheduled_at = self::current_time_mysql();
        $created_at = self::current_time_mysql();

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, campaign_id, recipient_email, subject, body, status, attempts, scheduled_at, created_at) VALUES (%d, %d, %s, %s, %s, %s, %d, %s, %s)",
                $site_id,
                $campaign_id,
                $email,
                $subject,
                $body,
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
                "SELECT id FROM {$table} WHERE status = %s AND scheduled_at <= %s AND attempts < %d ORDER BY scheduled_at ASC LIMIT %d",
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
            $claimed = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d AND status = %s",
                    'processing',
                    (int) $id,
                    'pending'
                )
            );

            if (!$claimed) {
                continue;
            }

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, site_id, campaign_id, recipient_email, subject, body, attempts FROM {$table} WHERE id = %d",
                    (int) $id
                ),
                ARRAY_A
            );

            if (empty($row)) {
                continue;
            }

            $result = ProviderManager::send_email($row['recipient_email'], $row['subject'], $row['body']);
            $attempts = (int) $row['attempts'] + 1;
            $processed_at = self::current_time_mysql();
            $provider_type = isset($result['provider']) ? (string) $result['provider'] : '';
            $provider_message_id = isset($result['message_id']) ? (string) $result['message_id'] : '';
            $provider_metadata = !empty($result['metadata']) ? wp_json_encode($result['metadata']) : null;
            $sent = !empty($result['success']);

            if ($sent) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET status = %s, attempts = %d, processed_at = %s, provider_type = %s, provider_message_id = %s, provider_metadata = %s WHERE id = %d",
                        'sent',
                        $attempts,
                        $processed_at,
                        $provider_type,
                        $provider_message_id,
                        $provider_metadata,
                        (int) $id
                    )
                );
                Logger::info('Queue email sent.', array('queue_id' => (int) $id, 'campaign_id' => (int) $row['campaign_id'], 'recipient_email' => $row['recipient_email'], 'provider' => $provider_type));
            } else {
                $next_status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
                $next_scheduled = $attempts >= self::MAX_ATTEMPTS ? $processed_at : self::calculate_next_attempt($attempts);

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET status = %s, attempts = %d, scheduled_at = %s, processed_at = %s, provider_type = %s, provider_message_id = %s, provider_metadata = %s WHERE id = %d",
                        $next_status,
                        $attempts,
                        $next_scheduled,
                        $processed_at,
                        $provider_type,
                        $provider_message_id,
                        $provider_metadata,
                        (int) $id
                    )
                );

                if ($next_status === 'failed') {
                    Logger::error('Queue email permanently failed.', array('queue_id' => (int) $id, 'campaign_id' => (int) $row['campaign_id'], 'recipient_email' => $row['recipient_email'], 'attempts' => $attempts, 'provider' => $provider_type, 'error' => $result['message']));
                } else {
                    Logger::warning('Queue email send failed; retry scheduled.', array('queue_id' => (int) $id, 'campaign_id' => (int) $row['campaign_id'], 'recipient_email' => $row['recipient_email'], 'attempts' => $attempts, 'next_scheduled' => $next_scheduled, 'provider' => $provider_type));
                }
            }

            if (!empty($row['campaign_id'])) {
                Campaigns::refresh_delivery_stats((int) $row['campaign_id']);
            }

            ++$processed;
        }

        return $processed;
    }

    public static function get_stats(int $site_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_queue';
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
}
