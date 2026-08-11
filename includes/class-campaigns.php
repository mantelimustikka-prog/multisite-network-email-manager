<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Campaigns
{
    public const VALID_STATUSES = array('draft', 'scheduled', 'sending', 'sent', 'cancelled');

    public const VALID_TRANSITIONS = array(
        'draft' => array('scheduled', 'cancelled'),
        'scheduled' => array('sending', 'cancelled'),
        'sending' => array('sent', 'cancelled'),
        'sent' => array(),
        'cancelled' => array(),
    );

    // NOTE: This is a placeholder scaffold. Actual campaign delivery logic is not yet implemented.

    public static function create(int $site_id, string $name, string $subject, string $body)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_campaigns';
        $now = gmdate('Y-m-d H:i:s');

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, name, subject, body, status, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %s, %s)",
                $site_id,
                sanitize_text_field($name),
                $subject,
                $body,
                'draft',
                $now,
                $now
            )
        );

        if ($result === false) {
            return false;
        }

        return isset($wpdb->insert_id) ? (int) $wpdb->insert_id : true;
    }

    public static function get(int $id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_campaigns';
        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $campaign ?: null;
    }

    public static function get_list(int $site_id, string $status = '', int $limit = 50, int $offset = 0)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_campaigns';
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        if ($status !== '' && in_array($status, self::VALID_STATUSES, true)) {
            return (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE site_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $site_id,
                    $status,
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
        }

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $site_id,
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    public static function update_status(int $id, string $new_status)
    {
        global $wpdb;

        if (!in_array($new_status, self::VALID_STATUSES, true)) {
            return false;
        }

        $campaign = self::get($id);
        if (!$campaign || !self::is_valid_transition($campaign['status'], $new_status)) {
            return false;
        }

        $table = $wpdb->prefix . 'mnem_campaigns';
        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
                $new_status,
                gmdate('Y-m-d H:i:s'),
                $id
            )
        );
    }

    public static function is_valid_transition(string $current_status, string $new_status)
    {
        return isset(self::VALID_TRANSITIONS[$current_status])
            && in_array($new_status, self::VALID_TRANSITIONS[$current_status], true);
    }
}
