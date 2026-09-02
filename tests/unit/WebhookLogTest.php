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

    public function test_get_provider_stats_groups_totals_per_provider()
    {
        $GLOBALS['wpdb']->results = array(
            array('provider' => 'brevo', 'total' => 4, 'success_count' => 3, 'last_received_at' => '2026-09-01 10:00:00'),
            array('provider' => 'sendgrid', 'total' => 2, 'success_count' => 2, 'last_received_at' => '2026-09-02 08:00:00'),
        );

        $stats = WebhookLog::get_provider_stats();

        $this->assertSame(4, $stats['brevo']['total']);
        $this->assertSame(1, $stats['brevo']['failed']);
        $this->assertSame(0, $stats['sendgrid']['failed']);
        $this->assertSame('2026-09-02 08:00:00', $stats['sendgrid']['last_received_at']);
        $this->assertStringContainsString('GROUP BY provider', implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_get_recent_errors_only_selects_processed_failures()
    {
        $GLOBALS['wpdb']->results = array(
            array('id' => 9, 'provider' => 'brevo', 'error_message' => 'No matching queue row was updated.'),
        );

        $rows = WebhookLog::get_recent_errors(5);

        $this->assertCount(1, $rows);
        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('WHERE success = 0 AND processed_at IS NOT NULL', $queries);
        $this->assertStringContainsString('LIMIT 5', $queries);
    }

    public function test_calculate_success_rate_returns_percentage()
    {
        $this->assertSame(75.0, WebhookLog::calculate_success_rate(array('total' => 4, 'success' => 3)));
        $this->assertSame(0.0, WebhookLog::calculate_success_rate(array('total' => 0, 'success' => 0)));
    }
}
