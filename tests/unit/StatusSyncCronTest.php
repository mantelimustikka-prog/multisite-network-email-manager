<?php

defined('ABSPATH') || exit;

use MNEM\StatusSyncCron;
use PHPUnit\Framework\TestCase;

class StatusSyncCronTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_cron_events'] = array();
    }

    public function test_init_schedules_hourly_sync_event()
    {
        $cron = new StatusSyncCron();
        $cron->init();

        $this->assertArrayHasKey(StatusSyncCron::HOOK, $GLOBALS['mnem_cron_events']);
        $this->assertSame('hourly', $GLOBALS['mnem_cron_events'][StatusSyncCron::HOOK]['recurrence']);
    }

    public function test_deactivate_clears_sync_hook()
    {
        $GLOBALS['mnem_cron_events'][StatusSyncCron::HOOK] = array(
            'timestamp' => time(),
            'recurrence' => 'hourly',
            'args' => array(),
        );

        StatusSyncCron::deactivate();

        $this->assertArrayNotHasKey(StatusSyncCron::HOOK, $GLOBALS['mnem_cron_events']);
    }
}
