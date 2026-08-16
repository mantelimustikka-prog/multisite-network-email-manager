<?php

namespace MNEM;

defined('ABSPATH') || exit;

class TrackingEvents
{
    private const TABLE_SUFFIX = 'mnem_email_tracking_events';

    public static function record_event(int $queue_id, string $event_type, ?string $link_url = null): bool
    {
        global $wpdb;

        $table = self::get_table_name();
        if ($table === '' || $queue_id <= 0) {
            return false;
        }

        $event_type = strtolower(trim($event_type));
        if (!in_array($event_type, array('open', 'click'), true)) {
            return false;
        }

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $site_id = (int) $wpdb->get_var($wpdb->prepare("SELECT site_id FROM {$queue_table} WHERE id = %d LIMIT %d", $queue_id, 1));
        if ($site_id <= 0) {
            $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        }

        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field((string) wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 500) : '';
        $ip_address = isset($_SERVER['REMOTE_ADDR']) ? substr(sanitize_text_field((string) wp_unslash($_SERVER['REMOTE_ADDR'])), 0, 45) : '';
        $link_url = $link_url !== null ? substr((string) $link_url, 0, 2048) : null;

        $result = false;
        $base_time = time();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $event_time = gmdate('Y-m-d H:i:s', $base_time + $attempt);
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (site_id, email_id, event_type, link_url, timestamp, user_agent, ip_address) VALUES (%d, %d, %s, %s, %s, %s, %s)",
                    $site_id,
                    $queue_id,
                    $event_type,
                    $link_url,
                    $event_time,
                    $user_agent,
                    $ip_address
                )
            );

            if ($result !== false || strpos((string) $wpdb->last_error, 'Duplicate entry') === false) {
                break;
            }
        }

        if ((int) $result > 0 && class_exists(__NAMESPACE__ . '\\Queue')) {
            Queue::refresh_tracking_data($queue_id);
        }

        return $result !== false;
    }

    public static function get_open_count(int $queue_id): int
    {
        return self::get_count($queue_id, 'open');
    }

    public static function get_click_count(int $queue_id): int
    {
        return self::get_count($queue_id, 'click');
    }

    /**
     * @return array{opens:int,clicks:int}
     */
    public static function get_counts_for_email(int $queue_id): array
    {
        return array(
            'opens' => self::get_open_count($queue_id),
            'clicks' => self::get_click_count($queue_id),
        );
    }

    private static function get_count(int $queue_id, string $event_type): int
    {
        global $wpdb;

        if ($queue_id <= 0) {
            return 0;
        }

        $table = self::get_table_name();
        if ($table === '') {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE email_id = %d AND event_type = %s",
            $queue_id,
            $event_type
        ));
    }

    private static function get_table_name(): string
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !property_exists($wpdb, 'prefix')) {
            return '';
        }

        $network_prefix = (property_exists($wpdb, 'base_prefix') && !empty($wpdb->base_prefix))
            ? (string) $wpdb->base_prefix
            : (string) $wpdb->prefix;

        return $network_prefix . self::TABLE_SUFFIX;
    }
}
