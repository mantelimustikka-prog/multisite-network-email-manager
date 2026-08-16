<?php

namespace MNEM;

defined('ABSPATH') || exit;

class StatusSyncCron
{
    public const HOOK = 'mnem_sync_provider_statuses';

    public function init(): void
    {
        add_action(self::HOOK, array(__CLASS__, 'sync_statuses'));
        add_action(Queue::STATUS_REFRESH_HOOK, array(Queue::class, 'refresh_single_item_status'));

        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return;
        }

        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 120, 'hourly', self::HOOK);
        }
    }

    public static function sync_statuses(): int
    {
        return Queue::sync_recent_provider_statuses(500, 30);
    }

    public static function deactivate(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::HOOK);
        }
    }
}
