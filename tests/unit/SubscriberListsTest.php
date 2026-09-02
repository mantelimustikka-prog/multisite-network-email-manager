<?php

defined('ABSPATH') || exit;

use MNEM\SubscriberLists;
use PHPUnit\Framework\TestCase;

class SubscriberListsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_user_data'] = array(
            7 => (object) array('ID' => 7, 'user_login' => 'alice', 'user_email' => 'alice@example.com'),
            8 => (object) array('ID' => 8, 'user_login' => 'bob', 'user_email' => 'bob@example.com'),
        );
    }

    public function test_create_subscriber_list_returns_insert_id()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                $this->insert_id = 99;
                return 1;
            }
        };

        $id = SubscriberLists::create('Launch List', 'Desc');

        $this->assertSame(99, $id);
    }

    public function test_add_subscriber_hard_blocks_unsubscribed_user()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array('subscription_status' => 'unsubscribed');
            }
        };

        $result = SubscriberLists::add_subscriber(5, 7);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mnem_user_unsubscribed', $result->get_error_code());
    }

    public function test_import_from_csv_adds_valid_identifiers()
    {
        $GLOBALS['mnem_users'] = array(
            (object) array('ID' => 8),
        );
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SubscriberLists::import_from_csv(1, "7\nbob");

        $this->assertSame(2, $result['added']);
        $this->assertSame(0, $result['skipped']);
    }

    public function test_export_to_csv_contains_subscribers()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    array('user_id' => 7, 'subscribed_at' => '2026-01-01 00:00:00'),
                );
            }
        };

        $csv = SubscriberLists::export_to_csv(1);

        $this->assertStringContainsString('user_id,username,email,subscribed_at', $csv);
        $this->assertStringContainsString('"alice"', $csv);
    }
    public function test_remove_subscriber_by_email_deletes_all_list_rows()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return 3;
            }
        };

        $removed = SubscriberLists::remove_subscriber_by_email('alice@example.com');

        $this->assertSame(3, $removed);
        $this->assertStringContainsString('DELETE FROM wp_mnem_list_subscribers WHERE user_id = 7', $GLOBALS['wpdb']->queries[0]);
    }

    public function test_remove_subscriber_by_email_returns_zero_for_unknown_email()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $removed = SubscriberLists::remove_subscriber_by_email('nobody@example.com');

        $this->assertSame(0, $removed);
        $this->assertSame(array(), array_values(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, 'mnem_list_subscribers') !== false;
        })));
    }
}
