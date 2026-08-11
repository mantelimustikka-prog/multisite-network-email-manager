<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Logger
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const WARNING = 'warning';
    public const ERROR = 'error';

    public const SCRUB_KEYS = array('password', 'smtp_password', 'secret', 'token', 'key', 'auth');

    public static function log(string $level, string $message, array $context = array())
    {
        $scrubbed_context = self::scrub_context($context);

        self::write_to_db($level, $message, $scrubbed_context);

        if (!defined('WP_ENV') || WP_ENV !== 'production') {
            $line = sprintf('[MNEM][%s] %s', strtoupper($level), $message);

            if (!empty($scrubbed_context)) {
                $line .= ' ' . wp_json_encode($scrubbed_context);
            }

            error_log($line);
        }
    }

    public static function scrub_context(array $ctx)
    {
        $scrubbed = array();

        foreach ($ctx as $key => $value) {
            $normalized_key = is_string($key) ? strtolower($key) : '';

            if ($normalized_key !== '' && self::should_scrub_key($normalized_key)) {
                $scrubbed[$key] = '***REDACTED***';
                continue;
            }

            if (is_array($value)) {
                $scrubbed[$key] = self::scrub_context($value);
                continue;
            }

            $scrubbed[$key] = $value;
        }

        return $scrubbed;
    }

    public static function debug(string $message, array $context = array())
    {
        self::log(self::DEBUG, $message, $context);
    }

    public static function info(string $message, array $context = array())
    {
        self::log(self::INFO, $message, $context);
    }

    public static function warning(string $message, array $context = array())
    {
        self::log(self::WARNING, $message, $context);
    }

    public static function error(string $message, array $context = array())
    {
        self::log(self::ERROR, $message, $context);
    }

    private static function should_scrub_key(string $key)
    {
        foreach (self::SCRUB_KEYS as $scrub_key) {
            if (strpos($key, $scrub_key) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function write_to_db(string $level, string $message, array $context)
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !property_exists($wpdb, 'prefix')) {
            return;
        }

        $table = $wpdb->prefix . 'mnem_logs';

        if (method_exists($wpdb, 'get_var')) {
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

            if ($table_exists !== $table) {
                return;
            }
        }

        if (!method_exists($wpdb, 'query') || !method_exists($wpdb, 'prepare')) {
            return;
        }

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $created_at = gmdate('Y-m-d H:i:s');
        $encoded_context = wp_json_encode($context);

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, level, message, context, created_at) VALUES (%d, %s, %s, %s, %s)",
                $site_id,
                $level,
                $message,
                $encoded_context,
                $created_at
            )
        );
    }
}
