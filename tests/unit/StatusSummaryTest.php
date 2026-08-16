<?php

defined('ABSPATH') || exit;

use MNEM\StatusSummary;
use PHPUnit\Framework\TestCase;

class StatusSummaryTest extends TestCase
{
    public function test_get_summary_returns_only_statuses_from_database(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    array('status' => 'blocked', 'count' => 2),
                    array('status' => 'sent', 'count' => 5),
                );
            }
        };

        $summary = StatusSummary::get_summary(null);

        $this->assertSame(array('blocked' => 2, 'sent' => 5), $summary);
    }

    public function test_get_status_count_uses_exact_status_value(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 3;
            }
        };

        $count = StatusSummary::get_status_count('Blocked');

        $this->assertSame(3, $count);
        $this->assertStringContainsString("status = 'Blocked'", implode("\n", $GLOBALS['wpdb']->queries));
    }
}
