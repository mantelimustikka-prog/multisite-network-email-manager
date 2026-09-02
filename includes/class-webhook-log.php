<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Stores a log of incoming provider webhook receipts so administrators can
 * verify that provider callbacks (for example Brevo) actually reach the site.
 */
class WebhookLog
{
    public const RETENTION_DAYS = 30;
    private const PAYLOAD_MAX_LENGTH = 20000;

    public static function get_table_name(): string
    {
        global $wpdb;

        $prefix = (isset($wpdb) && is_object($wpdb) && property_exists($wpdb, 'base_prefix') && !empty($wpdb->base_prefix))
            ? (string) $wpdb->base_prefix
            : 'wp_';

        return $prefix . 'mnem_webhook_log';
    }

    /**
     * Record an incoming webhook event.
     *
     * @param array<string,mixed> $payload
     * @return int Inserted log row id (0 when the insert failed).
     */
    public static function record(string $provider, string $event_type, string $recipient_email = '', string $message_id = '', string $status = '', array $payload = array()): int
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'insert')) {
            return 0;
        }

        $encoded_payload = function_exists('wp_json_encode') ? wp_json_encode($payload) : json_encode($payload);
        if (!is_string($encoded_payload)) {
            $encoded_payload = '';
        }
        if (strlen($encoded_payload) > self::PAYLOAD_MAX_LENGTH) {
            $encoded_payload = substr($encoded_payload, 0, self::PAYLOAD_MAX_LENGTH);
        }

        $inserted = $wpdb->insert(
            self::get_table_name(),
            array(
                'provider' => substr($provider, 0, 50),
                'event_type' => substr($event_type, 0, 100),
                'recipient_email' => substr($recipient_email, 0, 255),
                'message_id' => substr($message_id, 0, 255),
                'status' => substr($status, 0, 50),
                'received_at' => self::now(),
                'success' => 0,
                'payload' => $encoded_payload,
            )
        );

        if ($inserted === false) {
            return 0;
        }

        return isset($wpdb->insert_id) ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Mark a previously recorded webhook receipt as processed.
     */
    public static function mark_processed(int $log_id, bool $success, string $error_message = ''): void
    {
        global $wpdb;

        if ($log_id <= 0 || !isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'query')) {
            return;
        }

        $table = self::get_table_name();
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET processed_at = %s, success = %d, error_message = %s WHERE id = %d",
                self::now(),
                $success ? 1 : 0,
                $error_message,
                $log_id
            )
        );
    }

    /**
     * Return the most recent webhook receipts, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_recent(int $limit = 10, string $provider = ''): array
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_results')) {
            return array();
        }

        $limit = max(1, min(100, $limit));
        $table = self::get_table_name();

        if ($provider !== '') {
            $query = $wpdb->prepare(
                "SELECT id, provider, event_type, recipient_email, message_id, status, received_at, processed_at, success, error_message FROM {$table} WHERE provider = %s ORDER BY received_at DESC, id DESC LIMIT %d",
                $provider,
                $limit
            );
        } else {
            $query = $wpdb->prepare(
                "SELECT id, provider, event_type, recipient_email, message_id, status, received_at, processed_at, success, error_message FROM {$table} ORDER BY received_at DESC, id DESC LIMIT %d",
                $limit
            );
        }

        $rows = $wpdb->get_results($query, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    /**
     * Aggregate webhook health information for the given lookback window.
     *
     * @return array{total:int,success:int,failed:int,last_received_at:string}
     */
    public static function get_stats(int $days = 7): array
    {
        global $wpdb;

        $stats = array(
            'total' => 0,
            'success' => 0,
            'failed' => 0,
            'last_received_at' => '',
        );

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_row')) {
            return $stats;
        }

        $days = max(1, $days);
        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * (defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400)));
        $table = self::get_table_name();

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(1) AS total, SUM(success = 1) AS success_count, MAX(received_at) AS last_received_at FROM {$table} WHERE received_at >= %s",
                $threshold
            ),
            ARRAY_A
        );

        if (!is_array($row)) {
            return $stats;
        }

        $stats['total'] = isset($row['total']) ? (int) $row['total'] : 0;
        $stats['success'] = isset($row['success_count']) ? (int) $row['success_count'] : 0;
        $stats['failed'] = max(0, $stats['total'] - $stats['success']);
        $stats['last_received_at'] = isset($row['last_received_at']) ? (string) $row['last_received_at'] : '';

        return $stats;
    }

    /**
     * Aggregate webhook health information per provider.
     *
     * @return array<string,array{total:int,success:int,failed:int,last_received_at:string}>
     */
    public static function get_provider_stats(int $days = 7): array
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_results')) {
            return array();
        }

        $days = max(1, $days);
        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * (defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400)));
        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT provider, COUNT(1) AS total, SUM(success = 1) AS success_count, MAX(received_at) AS last_received_at FROM {$table} WHERE received_at >= %s GROUP BY provider",
                $threshold
            ),
            ARRAY_A
        );

        if (!is_array($rows)) {
            return array();
        }

        $stats = array();
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['provider'])) {
                continue;
            }

            $total = isset($row['total']) ? (int) $row['total'] : 0;
            $success = isset($row['success_count']) ? (int) $row['success_count'] : 0;

            $stats[(string) $row['provider']] = array(
                'total' => $total,
                'success' => $success,
                'failed' => max(0, $total - $success),
                'last_received_at' => isset($row['last_received_at']) ? (string) $row['last_received_at'] : '',
            );
        }

        return $stats;
    }

    /**
     * Return the most recent webhook receipts that failed to process.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function get_recent_errors(int $limit = 10): array
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_results')) {
            return array();
        }

        $limit = max(1, min(100, $limit));
        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, provider, event_type, recipient_email, message_id, status, received_at, error_message FROM {$table} WHERE success = 0 AND processed_at IS NOT NULL ORDER BY received_at DESC, id DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * Success rate (0-100) for the supplied stats array.
     *
     * @param array<string,mixed> $stats
     */
    public static function calculate_success_rate(array $stats): float
    {
        $total = isset($stats['total']) ? (int) $stats['total'] : 0;
        if ($total <= 0) {
            return 0.0;
        }

        $success = isset($stats['success']) ? (int) $stats['success'] : 0;

        return round(($success / $total) * 100, 1);
    }

    /**
     * Public REST URL that must be configured in the provider dashboard.
     */
    public static function get_webhook_url(string $provider): string
    {
        $provider = sanitize_key($provider);
        if ($provider === '') {
            return '';
        }

        if (function_exists('rest_url')) {
            return (string) rest_url('mnem/v1/webhooks/' . $provider);
        }

        return '';
    }

    /**
     * Delete log rows older than the retention window.
     */
    public static function prune(int $days = self::RETENTION_DAYS): int
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'query')) {
            return 0;
        }

        $days = max(1, $days);
        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * (defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400)));
        $table = self::get_table_name();

        return (int) $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE received_at < %s", $threshold)
        );
    }

    private static function now(): string
    {
        return function_exists('current_time') ? (string) current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }
}
