<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Suppression
{
    public static function add(int $site_id, string $email, string $reason = '')
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_suppression';
        $email = strtolower(trim(sanitize_email($email)));
        $reason = sanitize_text_field($reason);

        if ($email === '' || !is_email($email)) {
            return false;
        }

        return $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, email, reason, created_at) VALUES (%d, %s, %s, %s)",
                $site_id,
                $email,
                $reason,
                gmdate('Y-m-d H:i:s')
            )
        );
    }

    public static function remove(int $site_id, string $email)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_suppression';
        $email = strtolower(trim(sanitize_email($email)));

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE site_id = %d AND email = %s",
                $site_id,
                $email
            )
        );
    }

    public static function is_suppressed(int $site_id, string $email)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_suppression';
        $email = strtolower(trim(sanitize_email($email)));
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE site_id = %d AND email = %s",
                $site_id,
                $email
            )
        );

        return (int) $count > 0;
    }

    public static function get_list(int $site_id, int $limit = 100, int $offset = 0)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_suppression';

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, site_id, email, reason, created_at FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $site_id,
                max(1, $limit),
                max(0, $offset)
            ),
            ARRAY_A
        );
    }

    public static function count(int $site_id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_suppression';
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE site_id = %d",
                $site_id
            )
        );

        return (int) $count;
    }
}
