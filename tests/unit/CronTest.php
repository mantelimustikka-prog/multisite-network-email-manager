<?php

defined('ABSPATH') || exit;

use MNEM\Cron;
use PHPUnit\Framework\TestCase;

class CronTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options'][Cron::OPTION_INTERVAL] = 'hourly';
        $GLOBALS['mnem_site_options'][Cron::OPTION_FAILED_RUNS] = 0;
        $GLOBALS['mnem_cron_events'] = array();
    }

    public function test_schedule_queue_processing_creates_cron_event()
    {
        $result = Cron::schedule_queue_processing();

        $this->assertTrue($result);
        $this->assertArrayHasKey(Cron::HOOK, $GLOBALS['mnem_cron_events']);
        $this->assertSame('hourly', $GLOBALS['mnem_cron_events'][Cron::HOOK]['recurrence']);
        $this->assertArrayNotHasKey('mnem_cleanup_error_logs', $GLOBALS['mnem_cron_events']);
    }

    public function test_process_queue_batch_tracks_last_run()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_col($query)
            {
                $this->queries[] = $query;
                return array();
            }
        };

        $processed = Cron::process_queue_batch();

        $this->assertSame(0, $processed);
        $this->assertNotSame('', get_site_option(Cron::OPTION_LAST_RUN, ''));
        $this->assertSame(0, (int) get_site_option(Cron::OPTION_FAILED_RUNS, 0));
    }

    public function test_set_interval_persists_preference()
    {
        Cron::set_interval('mnem_15_minutes');

        $this->assertSame('mnem_15_minutes', get_site_option(Cron::OPTION_INTERVAL, ''));
    }

    public function test_schedule_queue_processing_creates_fast_track_cron_event()
    {
        Cron::schedule_queue_processing();

        $this->assertArrayHasKey(Cron::FAST_TRACK_HOOK, $GLOBALS['mnem_cron_events']);
        $this->assertSame(Cron::FAST_TRACK_INTERVAL, $GLOBALS['mnem_cron_events'][Cron::FAST_TRACK_HOOK]['recurrence']);
    }

    public function test_register_intervals_includes_one_minute_schedule()
    {
        $cron = new Cron();
        $schedules = $cron->register_intervals(array());

        $this->assertArrayHasKey(Cron::FAST_TRACK_INTERVAL, $schedules);
        $this->assertSame(60, $schedules[Cron::FAST_TRACK_INTERVAL]['interval']);
    }

    public function test_process_transactional_queue_batch_only_queries_transactional_sources()
    {
        $GLOBALS['mnem_site_options'][Cron::OPTION_FAST_TRACK_LOCK_UNTIL] = 0;
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_col($query)
            {
                $this->queries[] = $query;
                return array();
            }
        };

        $processed = Cron::process_transactional_queue_batch();
        $joined = implode("\n", $GLOBALS['wpdb']->queries);

        $this->assertSame(0, $processed);
        $this->assertStringContainsString("source IN ('core', 'plugin', 'user_event')", $joined);
        $this->assertStringNotContainsString("source IN ('campaign')", $joined);
        $this->assertSame(0, (int) get_site_option(Cron::OPTION_FAST_TRACK_LOCK_UNTIL, 0));
    }

    public function test_process_transactional_queue_batch_skips_when_locked()
    {
        $GLOBALS['mnem_site_options'][Cron::OPTION_FAST_TRACK_LOCK_UNTIL] = time() + 60;

        $processed = Cron::process_transactional_queue_batch();

        $this->assertSame(0, $processed);
        $GLOBALS['mnem_site_options'][Cron::OPTION_FAST_TRACK_LOCK_UNTIL] = 0;
    }
}
