<?php

namespace MNEM;

defined('ABSPATH') || exit;

class StatusSyncCron
{
    public const HOOK = 'mnem_sync_provider_statuses';
    private const VALID_INTERVALS = array(5, 10, 15, 20, 30, 60);
    private const SYNCABLE_STATUSES = array('sent', 'processing', 'deferred', 'soft_bounce');
    private const SYNC_LIMIT = 100;

    public function init(): void
    {
        add_filter('cron_schedules', array(__CLASS__, 'register_intervals'));
        add_action(self::HOOK, array(__CLASS__, 'sync_last_100_emails'));
        add_action(Queue::STATUS_REFRESH_HOOK, array(Queue::class, 'refresh_single_item_status'));
        add_action('mnem_status_update_interval_changed', array(__CLASS__, 'reschedule'));

        self::reschedule();
    }

    public static function register_intervals($schedules): array
    {
        if (!is_array($schedules)) {
            $schedules = array();
        }

        foreach (self::VALID_INTERVALS as $minutes) {
            $key = self::get_interval_key($minutes);
            $minute_in_seconds = defined('MINUTE_IN_SECONDS') ? MINUTE_IN_SECONDS : 60;
            $schedules[$key] = array(
                'interval' => $minutes * $minute_in_seconds,
                'display' => sprintf(__('Every %d Minutes', 'multisite-network-email-manager'), $minutes),
            );
        }

        return $schedules;
    }

    public static function reschedule(): void
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (function_exists('add_filter') && (!function_exists('has_filter') || !has_filter('cron_schedules', array(__CLASS__, 'register_intervals')))) {
            add_filter('cron_schedules', array(__CLASS__, 'register_intervals'));
        }

        $existing = wp_next_scheduled(self::HOOK);
        if ($existing) {
            wp_unschedule_event($existing, self::HOOK);
        }

        $minutes = SmtpSettings::get_status_update_interval();
        $interval_key = self::get_interval_key($minutes);
        $schedules = function_exists('wp_get_schedules') ? wp_get_schedules() : array();
        if (!isset($schedules[$interval_key])) {
            $schedules = self::register_intervals($schedules);
        }

        if (isset($schedules[$interval_key])) {
            wp_schedule_event(time(), $interval_key, self::HOOK);
        }
    }

    public static function sync_last_100_emails(): int
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_queue';
        $status_placeholders = implode(', ', array_fill(0, count(self::SYNCABLE_STATUSES), '%s'));
        $rows = (array) $wpdb->get_results(
            call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(
                    array(
                        "SELECT id, provider_type, provider_message_id, recipient_email, status
                        FROM {$table}
                        WHERE status IN ({$status_placeholders})
                        AND provider_type <> ''
                        AND provider_message_id <> ''
                        ORDER BY sent_at DESC, id DESC
                        LIMIT %d",
                    ),
                    self::SYNCABLE_STATUSES,
                    array(self::SYNC_LIMIT)
                )
            ),
            ARRAY_A
        );

        $updated = 0;
        foreach ($rows as $row) {
            $queue_id = isset($row['id']) ? (int) $row['id'] : 0;
            if ($queue_id <= 0) {
                continue;
            }

            $provider_type = isset($row['provider_type']) ? (string) $row['provider_type'] : '';
            $provider_message_id = isset($row['provider_message_id']) ? (string) $row['provider_message_id'] : '';
            $recipient_email = isset($row['recipient_email']) ? (string) $row['recipient_email'] : '';
            $current_status = isset($row['status']) ? (string) $row['status'] : '';
            $actual_status = Queue::retrieve_message_status($provider_type, $provider_message_id, $recipient_email);

            Logger::info('Status sync lookup attempt.', array(
                'queue_id' => $queue_id,
                'provider' => $provider_type,
                'provider_message_id' => $provider_message_id,
                'current_status' => $current_status,
                'actual_status' => $actual_status,
            ));

            if ($actual_status === '' || $actual_status === $current_status) {
                continue;
            }

            $resolved_status = Queue::resolve_status_update($current_status, $actual_status);
            if ($resolved_status === $current_status) {
                continue;
            }

            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d",
                    $resolved_status,
                    $queue_id
                )
            );

            if ($result !== false) {
                ++$updated;
                Logger::info('Status updated via cron.', array(
                    'queue_id' => $queue_id,
                    'old_status' => $current_status,
                    'new_status' => $resolved_status,
                ));
            }
        }

        Logger::info('Status sync cron completed.', array(
            'checked' => count($rows),
            'updated' => $updated,
            'interval_minutes' => SmtpSettings::get_status_update_interval(),
        ));

        return $updated;
    }

    public static function deactivate(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::HOOK);
        }
    }

    private static function get_interval_key(int $minutes): string
    {
        if (!in_array($minutes, self::VALID_INTERVALS, true)) {
            $minutes = 30;
        }

        return 'mnem_status_sync_' . $minutes . '_minutes';
    }
}
