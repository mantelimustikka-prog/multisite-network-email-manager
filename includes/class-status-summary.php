<?php

namespace MNEM;

defined('ABSPATH') || exit;

class StatusSummary
{
    /**
     * Get status summary from database.
     * Returns only statuses that actually exist in the queue table.
     */
    public static function get_summary(?int $site_id = null): array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_queue';

        if ($site_id === null) {
            $counts = (array) $wpdb->get_results(
                "SELECT status, COUNT(*) AS count FROM {$table} WHERE status IS NOT NULL AND status <> '' GROUP BY status ORDER BY status ASC",
                ARRAY_A
            );
        } else {
            $counts = (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT status, COUNT(*) AS count FROM {$table} WHERE site_id = %d AND status IS NOT NULL AND status <> '' GROUP BY status ORDER BY status ASC",
                    $site_id
                ),
                ARRAY_A
            );
        }

        $summary = array();
        foreach ($counts as $row) {
            $status = sanitize_text_field((string) ($row['status'] ?? ''));
            if ($status !== '') {
                $summary[$status] = (int) ($row['count'] ?? 0);
            }
        }

        return $summary;
    }

    /**
     * Get total count across all statuses.
     */
    public static function get_total_count(?int $site_id = null): int
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_queue';

        if ($site_id === null) {
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE site_id = %d", $site_id)
        );
    }

    /**
     * Get count for a specific status.
     */
    public static function get_status_count(string $status, ?int $site_id = null): int
    {
        global $wpdb;

        $table  = $wpdb->base_prefix . 'mnem_queue';
        $status = sanitize_text_field((string) $status);
        if ($status === '') {
            return 0;
        }

        if ($site_id === null) {
            return (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s", $status)
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE site_id = %d AND status = %s",
                $site_id,
                $status
            )
        );
    }
}
