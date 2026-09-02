<?php

defined('ABSPATH') || exit;

use MNEM\WebhookLog;
use PHPUnit\Framework\TestCase;

class WebhookLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wpdb'] = new wpdb();
    }

    public function test_record_inserts_row_into_webhook_log_table()
    {
        $log_id = WebhookLog::record('brevo', 'delivered', 'jane@example.com', 'msg-1', 'delivered', array('event' => 'delivered'));

        $this->assertSame(1, $log_id);
        $this->assertStringContainsString('INSERT INTO wp_mnem_webhook_log', implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_mark_processed_updates_success_flag_and_error_message()
    {
        WebhookLog::mark_processed(7, false, 'No matching queue row.');

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('UPDATE wp_mnem_webhook_log', $queries);
        $this->assertStringContainsString('success = 0', $queries);
        $this->assertStringContainsString("error_message = 'No matching queue row.'", $queries);
        $this->assertStringContainsString('WHERE id = 7', $queries);
    }

    public function test_mark_processed_ignores_invalid_log_id()
    {
        WebhookLog::mark_processed(0, true);

        $this->assertSame(array(), $GLOBALS['wpdb']->queries);
    }

    public function test_get_recent_limits_results_and_orders_by_received_at()
    {
        $GLOBALS['wpdb']->results = array(
            array('id' => 3, 'provider' => 'brevo', 'event_type' => 'delivered'),
        );

        $rows = WebhookLog::get_recent(10);

        $this->assertCount(1, $rows);
        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('FROM wp_mnem_webhook_log', $queries);
        $this->assertStringContainsString('ORDER BY received_at DESC', $queries);
        $this->assertStringContainsString('LIMIT 10', $queries);
    }

    public function test_get_stats_returns_totals_and_failure_count()
    {
        $GLOBALS['wpdb']->row = array(
            'total' => 5,
            'success_count' => 3,
            'last_received_at' => '2026-09-01 10:00:00',
        );

        $stats = WebhookLog::get_stats();

        $this->assertSame(5, $stats['total']);
        $this->assertSame(3, $stats['success']);
        $this->assertSame(2, $stats['failed']);
        $this->assertSame('2026-09-01 10:00:00', $stats['last_received_at']);
    }

    public function test_prune_deletes_rows_older_than_retention_window()
    {
        WebhookLog::prune(30);

        $this->assertStringContainsString('DELETE FROM wp_mnem_webhook_log WHERE received_at <', implode("\n", $GLOBALS['wpdb']->queries));
    }
}
