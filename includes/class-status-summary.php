<?php

namespace MNEM;

defined('ABSPATH') || exit;

class StatusSummary
{
    /**
     * Success-category statuses.
     */
    private const SUCCESS_STATUSES = array('sent', 'delivered', 'opened', 'clicked');

    /**
     * Processing-category statuses.
     */
    private const PROCESSING_STATUSES = array('pending', 'processing');

    /**
     * Issue-category statuses.
     */
    private const ISSUE_STATUSES = array('bounce', 'soft_bounce', 'invalid_email', 'deferred', 'complaint', 'unsubscribed', 'suppressed', 'failed', 'rejected', 'blocked');

    /**
     * Get dynamic status summary from database grouped by category.
     * Automatically detects all statuses present and counts them.
     */
    public static function get_summary(?int $site_id = null): array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_queue';

        if ($site_id === null) {
            $counts = (array) $wpdb->get_results(
                "SELECT LOWER(status) as status, COUNT(*) as count FROM {$table} WHERE status IS NOT NULL AND status <> '' GROUP BY LOWER(status)",
                ARRAY_A
            );
        } else {
            $counts = (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT LOWER(status) as status, COUNT(*) as count FROM {$table} WHERE site_id = %d AND status IS NOT NULL AND status <> '' GROUP BY LOWER(status)",
                    $site_id
                ),
                ARRAY_A
            );
        }

        $summary = array();
        foreach ($counts as $row) {
            $status = sanitize_text_field((string) ($row['status'] ?? ''));
            if ($status === '') {
                continue;
            }
            $summary[$status] = (int) ($row['count'] ?? 0);
        }

        return self::categorize_statuses($summary);
    }

    /**
     * Categorize statuses for dashboard display.
     */
    private static function categorize_statuses(array $summary): array
    {
        $categories = array(
            'success'    => array(),
            'processing' => array(),
            'issue'      => array(),
        );

        foreach ($summary as $status => $count) {
            if (in_array($status, self::SUCCESS_STATUSES, true)) {
                $categories['success'][$status] = $count;
            } elseif (in_array($status, self::PROCESSING_STATUSES, true)) {
                $categories['processing'][$status] = $count;
            } else {
                // Issue statuses and any unknown statuses.
                $categories['issue'][$status] = $count;
            }
        }

        return $categories;
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
        $status = sanitize_text_field(strtolower($status));

        if ($site_id === null) {
            return (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE LOWER(status) = %s", $status)
            );
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE site_id = %d AND LOWER(status) = %s",
                $site_id,
                $status
            )
        );
    }
}
