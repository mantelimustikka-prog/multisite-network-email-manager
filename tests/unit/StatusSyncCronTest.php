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
        unset($GLOBALS['mnem_site_options']['mnem_status_update_interval']);
    }

    public function test_init_schedules_status_sync_using_default_interval()
    {
        $cron = new StatusSyncCron();
        $cron->init();

        $this->assertArrayHasKey(StatusSyncCron::HOOK, $GLOBALS['mnem_cron_events']);
        $this->assertSame('mnem_status_sync_30_minutes', $GLOBALS['mnem_cron_events'][StatusSyncCron::HOOK]['recurrence']);
    }

    public function test_reschedule_uses_selected_interval()
    {
        $GLOBALS['mnem_site_options']['mnem_status_update_interval'] = 10;

        StatusSyncCron::reschedule();

        $this->assertArrayHasKey(StatusSyncCron::HOOK, $GLOBALS['mnem_cron_events']);
        $this->assertSame('mnem_status_sync_10_minutes', $GLOBALS['mnem_cron_events'][StatusSyncCron::HOOK]['recurrence']);
    }

    public function test_sync_last_100_emails_includes_sent_and_delivered_within_recent_window()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array();
            }
        };

        StatusSyncCron::sync_last_100_emails();

        $query = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("'pending'", $query);
        $this->assertStringContainsString("'sent'", $query);
        $this->assertStringContainsString("'delivered'", $query);
        $this->assertStringContainsString("'deferred'", $query);
        $this->assertStringContainsString('sent_at >=', $query);
    }

    public function test_deactivate_clears_sync_hook()
    {
        $GLOBALS['mnem_cron_events'][StatusSyncCron::HOOK] = array(
            'timestamp' => time(),
            'recurrence' => 'mnem_status_sync_30_minutes',
            'args' => array(),
        );

        StatusSyncCron::deactivate();

        $this->assertArrayNotHasKey(StatusSyncCron::HOOK, $GLOBALS['mnem_cron_events']);
    }
}
