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

    public function test_sync_sms_statuses_queries_syncable_statuses_within_recent_window()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array();
            }
        };

        StatusSyncCron::sync_sms_statuses();

        $query = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("'pending'", $query);
        $this->assertStringContainsString("'sent'", $query);
        $this->assertStringContainsString("'bounce'", $query);
        $this->assertStringContainsString('created_at >=', $query);
        $this->assertStringContainsString('mnem_sms_queue', $query);
    }

    public function test_sync_sms_statuses_groups_by_provider_and_syncs_each(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    array(
                        'id' => 1,
                        'provider_type' => 'textmagic',
                        'status' => 'sent',
                        'provider_status' => '',
                        'provider_message_id' => 'tm-1',
                        'sync_attempts' => 0,
                    ),
                    array(
                        'id' => 2,
                        'provider_type' => 'twilio',
                        'status' => 'sent',
                        'provider_status' => '',
                        'provider_message_id' => 'tw-2',
                        'sync_attempts' => 0,
                    ),
                    array(
                        'id' => 3,
                        'provider_type' => 'textmagic',
                        'status' => 'pending',
                        'provider_status' => '',
                        'provider_message_id' => 'tm-3',
                        'sync_attempts' => 0,
                    ),
                );
            }
        };

        $updated = StatusSyncCron::sync_sms_statuses();

        // No provider credentials are configured in the test environment, so the
        // provider lookup is skipped for every row and nothing is updated.
        $this->assertSame(0, $updated);

        // SmsProviderSyncManager::sync_statuses_from_provider() issues one SELECT per
        // distinct provider group; verify each group was dispatched with the correct
        // provider and id list.
        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertRegExp(
            "/provider_type = 'textmagic'.*id IN \\(1,3\\)/s",
            $queries
        );
        $this->assertRegExp(
            "/provider_type = 'twilio'.*id IN \\(2\\)/s",
            $queries
        );
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
