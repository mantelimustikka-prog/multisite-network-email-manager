<?php

defined('ABSPATH') || exit;

use MNEM\EmailTracking;
use PHPUnit\Framework\TestCase;

class EmailTrackingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options'] = array(
            EmailTracking::OPTION_KEEP_PREVIEWS => 1,
            EmailTracking::OPTION_RETENTION_DAYS => 30,
        );
    }

    public function test_map_event_to_update_detects_delivery_and_engagement()
    {
        $delivered = EmailTracking::map_event_to_update('sendgrid', 'delivered');
        $opened = EmailTracking::map_event_to_update('sendgrid', 'open');
        $clicked = EmailTracking::map_event_to_update('mailgun', 'clicked');

        $this->assertSame('delivered', $delivered['status']);
        $this->assertTrue($opened['open']);
        $this->assertTrue($clicked['click']);
    }

    public function test_handle_webhook_event_updates_record_status_and_counts()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;

                if (strpos($query, 'SHOW TABLES LIKE') !== false) {
                    return 'wp_mnem_email_tracking';
                }

                return null;
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'email_id' => 15,
                    'delivery_status' => 'pending',
                    'open_count' => 1,
                    'open_timestamps' => '["2026-08-10 10:00:00"]',
                    'click_count' => 0,
                    'click_timestamps' => '[]',
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        EmailTracking::handle_webhook_event('sendgrid', 'open', 'user@example.com', 'message-123', '2026-08-11 12:00:00');

        $queries = $GLOBALS['wpdb']->queries;
        $this->assertNotEmpty($queries);
        $joined_queries = implode("\n", $queries);
        $this->assertStringContainsString("SELECT email_id, delivery_status, open_count, open_timestamps, click_count, click_timestamps FROM wp_mnem_email_tracking WHERE provider_message_id = 'message-123'", $joined_queries);
        $this->assertStringContainsString("open_count = 2", $joined_queries);
        $this->assertStringContainsString("delivery_status = 'pending'", $joined_queries);
    }

    public function test_save_settings_allows_zero_day_retention()
    {
        EmailTracking::save_settings(true, 0);

        $this->assertSame(0, EmailTracking::get_retention_days());
    }

    public function test_cleanup_old_records_skips_deletes_when_retention_disabled()
    {
        $GLOBALS['mnem_site_options'][EmailTracking::OPTION_RETENTION_DAYS] = 0;
        $GLOBALS['mnem_site_options'][EmailTracking::OPTION_LAST_CLEANUP_AT] = 0;
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        EmailTracking::cleanup_old_records();

        $this->assertSame(array(), $GLOBALS['wpdb']->queries);
        $this->assertGreaterThan(0, (int) $GLOBALS['mnem_site_options'][EmailTracking::OPTION_LAST_CLEANUP_AT]);
    }

    public function test_cleanup_old_records_throttles_zero_retention_path()
    {
        $GLOBALS['mnem_site_options'][EmailTracking::OPTION_RETENTION_DAYS] = 0;
        $GLOBALS['mnem_site_options'][EmailTracking::OPTION_LAST_CLEANUP_AT] = 0;
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        EmailTracking::cleanup_old_records();
        $first_cleanup = (int) $GLOBALS['mnem_site_options'][EmailTracking::OPTION_LAST_CLEANUP_AT];
        EmailTracking::cleanup_old_records();

        $this->assertGreaterThan(0, $first_cleanup);
        $this->assertSame($first_cleanup, (int) $GLOBALS['mnem_site_options'][EmailTracking::OPTION_LAST_CLEANUP_AT]);
        $this->assertSame(array(), $GLOBALS['wpdb']->queries);
    }
}
