<?php

defined('ABSPATH') || exit;

use MNEM\Suppression;
use PHPUnit\Framework\TestCase;

class SuppressionTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $last_query = '';
            public $var = 0;

            public function query($query)
            {
                $this->last_query = $query;
                return 1;
            }

            public function get_var($query)
            {
                $this->last_query = $query;
                return $this->var;
            }
        };
    }

    public function test_add_inserts_record()
    {
        $result = Suppression::add(1, 'USER@example.com', 'Bounce');

        $this->assertSame(1, $result);
        $this->assertStringContainsString('INSERT INTO wp_mnem_suppression', $GLOBALS['wpdb']->last_query);
        $this->assertStringContainsString("'user@example.com'", $GLOBALS['wpdb']->last_query);
    }

    public function test_is_suppressed_returns_true_when_record_exists()
    {
        $GLOBALS['wpdb']->var = 1;

        $this->assertTrue(Suppression::is_suppressed(1, 'user@example.com'));
    }

    public function test_remove_deletes_record()
    {
        $result = Suppression::remove(1, 'user@example.com');

        $this->assertSame(1, $result);
        $this->assertStringContainsString('DELETE FROM wp_mnem_suppression', $GLOBALS['wpdb']->last_query);
    }
}
