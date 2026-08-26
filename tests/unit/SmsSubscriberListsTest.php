<?php

defined('ABSPATH') || exit;

use MNEM\SmsSubscriberLists;
use PHPUnit\Framework\TestCase;

class SmsSubscriberListsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options'] = array();
        $GLOBALS['mnem_user_data'] = array(
            7 => (object) array('ID' => 7, 'user_login' => 'alice', 'user_email' => 'alice@example.com'),
            8 => (object) array('ID' => 8, 'user_login' => 'bob', 'user_email' => 'bob@example.com'),
        );
        $GLOBALS['mnem_user_meta'] = array(
            7 => array('phone_number' => '2345678901'),
            8 => array('phone_number' => '3456789012'),
        );
    }

    public function test_create_sms_subscriber_list_returns_insert_id()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                $this->insert_id = 42;
                return 1;
            }
        };

        $id = SmsSubscriberLists::create('SMS Launch List', 'Desc');

        $this->assertSame(42, $id);
    }

    public function test_add_subscriber_hard_blocks_unsubscribed_user()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array();
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array('subscription_status' => 'unsubscribed');
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }
        };

        $result = SmsSubscriberLists::add_subscriber(5, 7);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('unsubscribed', $result['message']);
    }

    public function test_add_subscriber_skips_already_subscribed()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array();
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array('subscription_status' => 'subscribed');
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }
        };

        $result = SmsSubscriberLists::add_subscriber(5, 7);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['added']);
        $this->assertTrue($result['is_duplicate']);
    }

    public function test_add_subscriber_inserts_with_phone_number()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $last_query = '';
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array();
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }

            public function query($query)
            {
                $this->last_query = $query;
                $this->queries[] = $query;
                $this->insert_id = 10;
                return 1;
            }
        };

        $result = SmsSubscriberLists::add_subscriber(1, 7, '+1234567890');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['added']);
        $this->assertStringContainsString('+1234567890', $GLOBALS['wpdb']->last_query);
    }

    public function test_add_subscriber_rejects_invalid_phone_numbers()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                $this->insert_id = 11;
                return 1;
            }
        };

        $result = SmsSubscriberLists::add_subscriber(1, 7, 'abc');

        $this->assertFalse($result['success']);
        $this->assertFalse($result['phone_valid']);
        $this->assertStringContainsString('invalid characters', strtolower($result['phone_error']));
    }

    public function test_add_subscriber_rejects_duplicate_phone_number_in_same_list()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    array('user_id' => 7, 'phone_number' => '+1234567890', 'subscription_status' => 'subscribed'),
                );
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }
        };

        $result = SmsSubscriberLists::add_subscriber(1, 8, '+1234567890');

        $this->assertFalse($result['success']);
        $this->assertTrue($result['is_duplicate']);
        $this->assertSame(7, $result['duplicate_user_id']);
    }

    public function test_import_from_csv_adds_valid_identifiers()
    {
        $GLOBALS['mnem_users'] = array(
            (object) array('ID' => 8),
        );
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array();
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsSubscriberLists::import_from_csv(1, "7\nbob");

        $this->assertSame(2, $result['added']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_import_from_csv_supports_phone_colon_syntax()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $last_query = '';
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array();
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }

            public function query($query)
            {
                $this->last_query = $query;
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsSubscriberLists::import_from_csv(1, "7:+9876543210");

        $this->assertSame(1, $result['added']);
        $this->assertStringContainsString('+9876543210', $GLOBALS['wpdb']->last_query);
    }

    public function test_export_to_csv_contains_subscribers()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    array('user_id' => 7, 'phone_number' => '+1234567890', 'subscribed_at' => '2026-01-01 00:00:00'),
                );
            }
        };

        $csv = SmsSubscriberLists::export_to_csv(1);

        $this->assertStringContainsString('user_id,username,phone_number,subscribed_at', $csv);
        $this->assertStringContainsString('"alice"', $csv);
        $this->assertStringContainsString('"+1234567890"', $csv);
    }

    public function test_get_list_subscribers_count_returns_int()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return '5';
            }
        };

        $count = SmsSubscriberLists::get_list_subscribers_count(1);

        $this->assertSame(5, $count);
    }

    public function test_is_subscribed_returns_true_when_count_positive()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return '1';
            }
        };

        $result = SmsSubscriberLists::is_subscribed(1, 7);

        $this->assertTrue($result);
    }

    public function test_unsubscribe_user_updates_existing_record()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $query_count = 0;
            public function get_var($query)
            {
                $this->queries[] = $query;
                return '1';
            }

            public function query($query)
            {
                $this->queries[] = $query;
                ++$this->query_count;
                return 1;
            }
        };

        $result = SmsSubscriberLists::unsubscribe_user(1, 7, 'Opted out');

        $this->assertTrue($result);
    }
}
