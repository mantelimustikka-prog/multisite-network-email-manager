<?php

namespace MNEM;

defined('ABSPATH') || exit;

class QueueCleanupCron
{
    const HOOK = 'mnem_cleanup_old_queue_records';

    public static function init(): void
    {
        add_action(self::HOOK, array(__CLASS__, 'cleanup_old_records'));
        self::schedule();
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time(), 'daily', self::HOOK);
        }
    }

    public static function cleanup_old_records(): int
    {
        global $wpdb;

        $retention_days = SmtpSettings::get_queue_retention_days();
        $threshold = gmdate('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));

        $terminal_statuses = array('sent', 'delivered', 'opened', 'clicked', 'bounce', 'soft_bounce', 'invalid_email', 'deferred', 'complaint', 'unsubscribed', 'suppressed', 'failed', 'rejected', 'blocked');

        $status_placeholders = implode(', ', array_fill(0, count($terminal_statuses), '%s'));
        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $sms_table = $wpdb->base_prefix . 'mnem_sms_queue';

        $queue_deleted = $wpdb->query(
            call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(
                    array(
                        "DELETE FROM {$queue_table} WHERE created_at < %s AND status IN ({$status_placeholders})",
                        $threshold,
                    ),
                    $terminal_statuses
                )
            )
        );

        $sms_deleted = $wpdb->query(
            call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(
                    array(
                        "DELETE FROM {$sms_table} WHERE created_at < %s AND status IN ({$status_placeholders})",
                        $threshold,
                    ),
                    $terminal_statuses
                )
            )
        );

        if ($queue_deleted === false) {
            Logger::warning('Email queue cleanup failed.', array(
                'table'          => $queue_table,
                'days_before'    => $retention_days,
                'threshold_date' => $threshold,
            ));
        }

        if ($sms_deleted === false) {
            Logger::warning('SMS queue cleanup failed.', array(
                'table'          => $sms_table,
                'days_before'    => $retention_days,
                'threshold_date' => $threshold,
            ));
        }

        $queue_deleted = $queue_deleted === false ? 0 : (int) $queue_deleted;
        $sms_deleted = $sms_deleted === false ? 0 : (int) $sms_deleted;
        $total_deleted = $queue_deleted + $sms_deleted;

        WebhookLog::prune();

        if ($total_deleted > 0) {
            Logger::info('Old queue records cleaned up.', array(
                'days_before'    => $retention_days,
                'deleted_count'  => $total_deleted,
                'email_deleted'  => $queue_deleted,
                'sms_deleted'    => $sms_deleted,
                'threshold_date' => $threshold,
            ));
        }

        return $total_deleted;
    }

    public static function deactivate(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::HOOK);
        }
    }
}
