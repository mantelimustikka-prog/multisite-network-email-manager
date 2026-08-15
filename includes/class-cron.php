<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Cron
{
    public const HOOK = 'mnem_process_queue_batch';
    public const OPTION_INTERVAL = 'mnem_cron_interval';
    public const OPTION_LAST_RUN = 'mnem_last_cron_run';
    public const OPTION_FAILED_RUNS = 'mnem_failed_cron_runs';
    public const OPTION_LOCK_UNTIL = 'mnem_cron_lock_until';
    public const DEFAULT_INTERVAL = 'hourly';
    public const LOCK_TTL = 300;

    public function init()
    {
        add_filter('cron_schedules', array($this, 'register_intervals'));
        add_action(self::HOOK, array($this, 'process_queue_batch'));

        self::schedule_queue_processing();
    }

    public function register_intervals($schedules)
    {
        if (!is_array($schedules)) {
            $schedules = array();
        }

        $schedules['mnem_5_minutes'] = array('interval' => 5 * 60, 'display' => 'Every 5 Minutes');
        $schedules['mnem_15_minutes'] = array('interval' => 15 * 60, 'display' => 'Every 15 Minutes');
        $schedules['mnem_30_minutes'] = array('interval' => 30 * 60, 'display' => 'Every 30 Minutes');
        $schedules['mnem_6_hours'] = array('interval' => 6 * 3600, 'display' => 'Every 6 Hours');
        $schedules['mnem_12_hours'] = array('interval' => 12 * 3600, 'display' => 'Every 12 Hours');

        return $schedules;
    }

    public static function get_interval()
    {
        $interval = (string) get_site_option(self::OPTION_INTERVAL, self::DEFAULT_INTERVAL);

        return self::is_valid_interval($interval) ? $interval : self::DEFAULT_INTERVAL;
    }

    public static function schedule_queue_processing()
    {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            return false;
        }

        $interval = self::get_interval();
        $next = wp_next_scheduled(self::HOOK);

        if (!$next) {
            wp_schedule_event(time() + 60, $interval, self::HOOK);
            Logger::info('Scheduled queue cron worker.', array('interval' => $interval));
        } elseif (function_exists('wp_get_scheduled_event') && function_exists('wp_unschedule_event')) {
            $event = wp_get_scheduled_event(self::HOOK);
            if (is_object($event) && isset($event->schedule) && $event->schedule !== $interval) {
                wp_unschedule_event($next, self::HOOK);
                wp_schedule_event(time() + 60, $interval, self::HOOK);
            }
        }

        return true;
    }

    public static function process_queue_batch()
    {
        $now = time();
        $locked_until = (int) get_site_option(self::OPTION_LOCK_UNTIL, 0);
        if ($locked_until > $now) {
            Logger::warning('Queue cron run skipped due to active lock.', array('locked_until' => $locked_until));
            return 0;
        }

        update_site_option(self::OPTION_LOCK_UNTIL, $now + self::LOCK_TTL);

        try {
            $processed = Queue::process_batch(50);
            update_site_option(self::OPTION_LAST_RUN, gmdate('Y-m-d H:i:s'));
            update_site_option(self::OPTION_FAILED_RUNS, 0);
            Logger::info('Queue cron batch processed.', array('processed' => (int) $processed));
            return (int) $processed;
        } catch (\Throwable $throwable) {
            $failed = (int) get_site_option(self::OPTION_FAILED_RUNS, 0) + 1;
            update_site_option(self::OPTION_FAILED_RUNS, $failed);
            Logger::error('Queue cron batch failed.', array('error' => $throwable->getMessage(), 'failed_runs' => $failed));
            return 0;
        } finally {
            update_site_option(self::OPTION_LOCK_UNTIL, 0);
        }
    }

    public static function get_status()
    {
        $next_run = function_exists('wp_next_scheduled') ? (int) wp_next_scheduled(self::HOOK) : 0;

        return array(
            'interval' => self::get_interval(),
            'last_run' => (string) get_site_option(self::OPTION_LAST_RUN, ''),
            'failed_runs' => (int) get_site_option(self::OPTION_FAILED_RUNS, 0),
            'next_run' => $next_run > 0 ? gmdate('Y-m-d H:i:s', $next_run) : '',
        );
    }

    public static function set_interval(string $interval)
    {
        $interval = self::is_valid_interval($interval) ? $interval : self::DEFAULT_INTERVAL;
        update_site_option(self::OPTION_INTERVAL, $interval);

        return self::schedule_queue_processing();
    }

    public static function deactivate()
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::HOOK);
        }

        update_site_option(self::OPTION_LOCK_UNTIL, 0);
    }

    private static function is_valid_interval(string $interval)
    {
        return in_array(
            $interval,
            array('mnem_5_minutes', 'mnem_15_minutes', 'mnem_30_minutes', 'hourly', 'mnem_6_hours', 'mnem_12_hours', 'daily'),
            true
        );
    }
}
