<?php

defined('ABSPATH') || exit;

use MNEM\InvalidPhoneNumbers;
use PHPUnit\Framework\TestCase;

class InvalidPhoneNumbersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_user_data'] = array(
            3 => (object) array('ID' => 3, 'user_login' => 'admin'),
            7 => (object) array('ID' => 7, 'user_login' => 'alice'),
        );
        $GLOBALS['mnem_current_user_id'] = 3;
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $last_query = '';
            public $results = array();
            public $var = 0;

            public function query($query)
            {
                $this->last_query = $query;
                $this->queries[] = $query;
                $this->insert_id = 15;
                return 1;
            }

            public function get_var($query)
            {
                $this->last_query = $query;
                $this->queries[] = $query;
                return $this->var;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->last_query = $query;
                $this->queries[] = $query;
                return $this->results;
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->last_query = $query;
                $this->queries[] = $query;
                return array('id' => 15, 'phone_number' => '+12345678901', 'user_id' => 7);
            }
        };
    }

    public function test_log_invalid_number_inserts_record()
    {
        $result = InvalidPhoneNumbers::log_invalid_number('+12345678901', 'format_invalid', 5, 7);

        $this->assertGreaterThan(0, $result);
        $this->assertStringContainsString('INSERT INTO wp_mnem_invalid_phone_numbers', implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_log_invalid_number_uses_zero_list_id_for_global_entries()
    {
        InvalidPhoneNumbers::log_invalid_number('+12345678901', 'format_invalid', null, 7);

        $this->assertStringContainsString('list_id = 0', implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_is_blocked_returns_true_when_matching_blocked_record_exists()
    {
        $GLOBALS['wpdb']->var = 1;

        $this->assertTrue(InvalidPhoneNumbers::is_blocked('+12345678901'));
    }

    public function test_get_invalid_numbers_adds_user_and_admin_logins()
    {
        $GLOBALS['wpdb']->results = array(
            array(
                'id' => 15,
                'phone_number' => '+12345678901',
                'reason' => 'duplicate',
                'list_id' => 5,
                'user_id' => 7,
                'blocked' => 1,
                'created_at' => '2026-08-26 10:00:00',
                'action_taken' => 'blocked',
                'taken_by' => 3,
                'taken_at' => '2026-08-26 11:00:00',
            ),
        );

        $items = InvalidPhoneNumbers::get_invalid_numbers(5, 'blocked');

        $this->assertSame('alice', $items[0]['user_login']);
        $this->assertSame('admin', $items[0]['admin_login']);
        $this->assertSame('blocked', $items[0]['status']);
    }
}
