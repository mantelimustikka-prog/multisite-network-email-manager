<?php

defined('ABSPATH') || exit;

use MNEM\QueueCleanupCron;
use PHPUnit\Framework\TestCase;

class QueueCleanupCronTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options']['mnem_queue_retention_days'] = 30;
    }

    public function test_cleanup_old_records_deletes_email_and_sms_queue_rows()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;

                if (strpos($query, 'DELETE FROM wp_mnem_queue') !== false) {
                    return 2;
                }

                if (strpos($query, 'DELETE FROM wp_mnem_sms_queue') !== false) {
                    return 3;
                }

                return 1;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return '';
            }
        };

        $deleted = QueueCleanupCron::cleanup_old_records();

        $this->assertSame(5, $deleted);

        $joined_queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('DELETE FROM wp_mnem_queue', $joined_queries);
        $this->assertStringContainsString('DELETE FROM wp_mnem_sms_queue', $joined_queries);
        $this->assertStringContainsString("status IN ('sent'", $joined_queries);
    }

    public function test_cleanup_old_records_returns_zero_when_deletes_fail()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return false;
            }
        };

        $this->assertSame(0, QueueCleanupCron::cleanup_old_records());
    }
}
