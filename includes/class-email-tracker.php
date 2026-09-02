<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Class EmailTracker
 *
 * @deprecated 1.0.0 Custom email tracking removed in favor of provider webhooks.
 */
class EmailTracker
{
    public static function add_tracking_pixel(string $body, int $email_id): string
    {
        return $body;
    }

    public static function rewrite_links_for_tracking(string $body, int $email_id): string
    {
        return $body;
    }

    public static function handle_pixel_request(string $token): string
    {
        return base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
    }

    public static function handle_link_click(string $token, string $redirect_url): string
    {
        $decoded = base64_decode(rawurldecode($redirect_url), true);
        $decoded = $decoded === false ? '' : trim($decoded);

        if (function_exists('wp_http_validate_url')) {
            $decoded = (string) wp_http_validate_url($decoded);
        } elseif (!filter_var($decoded, FILTER_VALIDATE_URL)) {
            $decoded = '';
        }

        return $decoded;
    }

    public static function get_open_count(int $email_id): int
    {
        global $wpdb;

        if ($email_id <= 0) {
            return 0;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $opens_count = $wpdb->get_var($wpdb->prepare("SELECT opens_count FROM {$table} WHERE id = %d LIMIT %d", $email_id, 1));
        if ($opens_count === null || $opens_count === '') {
            $opened = (string) $wpdb->get_var($wpdb->prepare("SELECT opened FROM {$table} WHERE id = %d LIMIT %d", $email_id, 1));
            return $opened !== '' ? 1 : 0;
        }

        return max(0, (int) $opens_count);
    }

    public static function get_click_count(int $email_id): int
    {
        global $wpdb;

        if ($email_id <= 0) {
            return 0;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $clicks_count = $wpdb->get_var($wpdb->prepare("SELECT clicks_count FROM {$table} WHERE id = %d LIMIT %d", $email_id, 1));
        if ($clicks_count === null || $clicks_count === '') {
            $clicked = (string) $wpdb->get_var($wpdb->prepare("SELECT clicked FROM {$table} WHERE id = %d LIMIT %d", $email_id, 1));
            return $clicked !== '' ? 1 : 0;
        }

        return max(0, (int) $clicks_count);
    }

    public static function get_email_status(int $email_id): string
    {
        global $wpdb;

        if ($email_id <= 0) {
            return 'Failed';
        }

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $status = (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$queue_table} WHERE id = %d LIMIT %d", $email_id, 1));
        if ($status !== '') {
            return Queue::get_display_status(array('status' => $status));
        }

        $opens = self::get_open_count($email_id);
        $clicks = self::get_click_count($email_id);
        if ($opens > 0 || $clicks > 0) {
            return 'Opened';
        }

        return 'Failed';
    }

    private static function generate_token(int $email_id): string
    {
        $secret = function_exists('wp_salt') ? (string) wp_salt('auth') : (defined('AUTH_KEY') ? AUTH_KEY : 'mnem');
        $hash = hash_hmac('sha256', (string) $email_id, $secret);
        return $email_id . '.' . $hash;
    }

    private static function resolve_email_id_from_token(string $token): int
    {
        $token = trim($token);
        if ($token === '' || strpos($token, '.') === false) {
            return 0;
        }

        [$id_part, $hash] = array_pad(explode('.', $token, 2), 2, '');
        $email_id = (int) $id_part;
        if ($email_id <= 0 || $hash === '') {
            return 0;
        }

        $expected = self::generate_token($email_id);
        $expected_hash = substr($expected, strpos($expected, '.') + 1);

        if (!hash_equals($expected_hash, $hash)) {
            return 0;
        }

        return $email_id;
    }

    private static function rest_endpoint(string $path): string
    {
        if (function_exists('rest_url')) {
            return (string) rest_url('mnem/v1/' . ltrim($path, '/'));
        }

        return '/wp-json/mnem/v1/' . ltrim($path, '/');
    }

    /**
     * @param array<string,string> $args
     */
    private static function build_url(string $base, array $args): string
    {
        $query = http_build_query($args, '', '&', PHP_QUERY_RFC3986);
        if ($query === '') {
            return $base;
        }

        return strpos($base, '?') === false ? ($base . '?' . $query) : ($base . '&' . $query);
    }
}
