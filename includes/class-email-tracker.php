<?php

namespace MNEM;

defined('ABSPATH') || exit;

class EmailTracker
{
    public static function add_tracking_pixel(string $body, int $email_id): string
    {
        if ($email_id <= 0 || $body === '') {
            return $body;
        }

        $token = self::generate_token($email_id);
        $pixel_url = self::build_url(self::rest_endpoint('track-open'), array('token' => $token));
        $pixel = '<img src="' . esc_url($pixel_url) . '" width="1" height="1" alt="" />';

        $position = stripos($body, '</body>');
        if ($position !== false) {
            return substr_replace($body, $pixel . '</body>', $position, 7);
        }

        return $body . $pixel;
    }

    public static function rewrite_links_for_tracking(string $body, int $email_id): string
    {
        if ($email_id <= 0 || $body === '') {
            return $body;
        }

        $token = self::generate_token($email_id);
        $endpoint = self::rest_endpoint('track-click');

        return (string) preg_replace_callback('/href=(["\'])(.*?)\1/i', static function (array $matches) use ($token, $endpoint) {
            $quote = isset($matches[1]) ? (string) $matches[1] : '"';
            $url = html_entity_decode(isset($matches[2]) ? (string) $matches[2] : '', ENT_QUOTES, 'UTF-8');
            $url = trim($url);
            if ($url === '' || strpos($url, '#') === 0 || stripos($url, 'mailto:') === 0 || stripos($url, 'tel:') === 0 || stripos($url, 'javascript:') === 0 || stripos($url, 'track-click') !== false) {
                return $matches[0];
            }

            $tracked_url = self::build_url($endpoint, array(
                'token' => $token,
                'url' => rawurlencode(base64_encode($url)),
            ));

            return 'href=' . $quote . esc_url($tracked_url) . $quote;
        }, $body);
    }

    public static function handle_pixel_request(string $token): string
    {
        $email_id = self::resolve_email_id_from_token($token);
        if ($email_id > 0) {
            TrackingEvents::record_event($email_id, 'open');
            self::mark_as_opened($email_id);
        }

        return base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
    }

    public static function handle_link_click(string $token, string $redirect_url): string
    {
        $email_id = self::resolve_email_id_from_token($token);
        $decoded = base64_decode(rawurldecode($redirect_url), true);
        $decoded = $decoded === false ? '' : trim($decoded);

        if (function_exists('wp_http_validate_url')) {
            $decoded = (string) wp_http_validate_url($decoded);
        } elseif (!filter_var($decoded, FILTER_VALIDATE_URL)) {
            $decoded = '';
        }

        if ($email_id > 0 && $decoded !== '') {
            TrackingEvents::record_event($email_id, 'click', $decoded);
            self::mark_as_opened($email_id);
        }

        return $decoded;
    }

    public static function get_open_count(int $email_id): int
    {
        return TrackingEvents::get_open_count($email_id);
    }

    public static function get_click_count(int $email_id): int
    {
        return TrackingEvents::get_click_count($email_id);
    }

    public static function get_email_status(int $email_id): string
    {
        global $wpdb;

        if ($email_id <= 0) {
            return 'Failed';
        }

        $opens = self::get_open_count($email_id);
        $clicks = self::get_click_count($email_id);
        if ($opens > 0 || $clicks > 0) {
            return 'Opened';
        }

        $tracking_table = $wpdb->base_prefix . 'mnem_email_tracking';
        $delivery_status = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT delivery_status FROM {$tracking_table} WHERE queue_id = %d ORDER BY created_at DESC LIMIT %d",
            $email_id,
            1
        ));
        if ($delivery_status === 'delivered' || $delivery_status === 'opened') {
            return 'Delivered';
        }

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $sent_at = (string) $wpdb->get_var($wpdb->prepare("SELECT sent_at FROM {$queue_table} WHERE id = %d LIMIT %d", $email_id, 1));
        if ($sent_at !== '') {
            return 'Sent';
        }

        return 'Failed';
    }

    private static function mark_as_opened(int $email_id): void
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_email_tracking';
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET delivery_status = %s, updated_at = %s WHERE queue_id = %d AND delivery_status IN (%s, %s)",
                'opened',
                gmdate('Y-m-d H:i:s'),
                $email_id,
                'pending',
                'delivered'
            )
        );

        if (class_exists(__NAMESPACE__ . '\\Queue')) {
            Queue::refresh_tracking_data($email_id);
        }
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
