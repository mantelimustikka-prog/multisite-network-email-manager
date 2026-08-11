<?php

defined('ABSPATH') || exit;

use MNEM\Queue;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    public function test_calculate_next_attempt_returns_expected_values()
    {
        $base = time();

        $attempt0 = strtotime(Queue::calculate_next_attempt(0));
        $attempt1 = strtotime(Queue::calculate_next_attempt(1));
        $attempt2 = strtotime(Queue::calculate_next_attempt(2));

        $this->assertGreaterThanOrEqual($base + 300 - 2, $attempt0);
        $this->assertLessThanOrEqual($base + 300 + 2, $attempt0);
        $this->assertGreaterThanOrEqual($base + 600 - 2, $attempt1);
        $this->assertLessThanOrEqual($base + 600 + 2, $attempt1);
        $this->assertGreaterThanOrEqual($base + 1200 - 2, $attempt2);
        $this->assertLessThanOrEqual($base + 1200 + 2, $attempt2);
    }

    public function test_enqueue_skips_if_suppressed()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $queries = array();
            public $var = 1;

            public function get_var($query)
            {
                $this->queries[] = $query;
                return $this->var;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = Queue::enqueue(1, 'user@example.com', 'Subject', 'Body');

        $this->assertFalse($result);
        $this->assertStringContainsString('SELECT COUNT(1) FROM wp_mnem_suppression', $GLOBALS['wpdb']->queries[0]);
        $this->assertSame(0, count(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, 'INSERT INTO wp_mnem_queue') !== false;
        })));
    }
}
