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
}
