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
        $GLOBALS['mnem_transients'] = array();
        $GLOBALS['mnem_user_data'] = array(
            7 => (object) array('ID' => 7, 'user_login' => 'alice', 'user_email' => 'alice@example.com'),
            8 => (object) array('ID' => 8, 'user_login' => 'bob', 'user_email' => 'bob@example.com'),
        );
        $GLOBALS['mnem_user_meta'] = array(
            7 => array('phone_number' => '2345678901'),
            8 => array('phone_number' => '3456789012'),
        );
        unset($GLOBALS['mnem_users']);
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
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'phone_number') !== false) {
                    return array('user_id' => 7, 'phone_number' => '+1234567890', 'subscription_status' => 'subscribed');
                }

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

    public function test_import_from_csv_supports_standalone_name_phone_syntax()
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

        $result = SmsSubscriberLists::import_from_csv(1, "Jane Smith:+1234567890");

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['added_standalone']);
        $this->assertStringContainsString('Jane Smith', $GLOBALS['wpdb']->last_query);
    }

    public function test_export_to_csv_contains_subscribers()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    array('user_id' => 7, 'subscriber_name' => '', 'phone_number' => '+1234567890', 'subscribed_at' => '2026-01-01 00:00:00'),
                    array('user_id' => 0, 'subscriber_name' => 'Vendor Contact', 'phone_number' => '+358401234567', 'subscribed_at' => '2026-01-02 00:00:00'),
                );
            }
        };

        $csv = SmsSubscriberLists::export_to_csv(1);

        $this->assertStringContainsString('type,user_id,username,subscriber_name,phone_number,subscribed_at', $csv);
        $this->assertStringContainsString('user,7', $csv);
        $this->assertStringContainsString('standalone,0', $csv);
        $this->assertStringContainsString('"alice"', $csv);
        $this->assertStringContainsString('"Vendor Contact"', $csv);
        $this->assertStringContainsString('"+1234567890"', $csv);
    }

    public function test_add_standalone_subscriber_inserts_record()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $last_query = '';
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

        $result = SmsSubscriberLists::add_standalone_subscriber(1, 'External Partner', '+1234567890');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['added']);
        $this->assertStringContainsString('External Partner', $GLOBALS['wpdb']->last_query);
    }

    public function test_delete_cascade_removes_related_sms_records_in_transaction()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_sms_subscriber_lists WHERE id = 3') !== false) {
                    return array('id' => 3, 'name' => 'Launch List');
                }

                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_invalid_phone_numbers'") !== false) {
                    return 'wp_mnem_invalid_phone_numbers';
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_logs'") !== false) {
                    return 'wp_mnem_logs';
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_queue'") !== false) {
                    return 'wp_mnem_queue';
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_sms_campaign_list_map'") !== false) {
                    return 0;
                }
                if (strpos($query, 'COLUMN_NAME = \'list_id\'') !== false && strpos($query, 'wp_mnem_queue') !== false) {
                    return 0;
                }
                if (strpos($query, 'FROM wp_mnem_sms_list_subscribers WHERE list_id = 3') !== false) {
                    return 45;
                }
                if (strpos($query, 'FROM wp_mnem_invalid_phone_numbers WHERE list_id = 3') !== false) {
                    return 12;
                }

                return 0;
            }

            public function get_col($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT id FROM wp_mnem_logs') !== false) {
                    return array(91, 92);
                }

                return array();
            }

            public function query($query)
            {
                $this->queries[] = $query;
                if ($query === 'START TRANSACTION' || $query === 'COMMIT') {
                    return 1;
                }
                if (strpos($query, 'DELETE FROM wp_mnem_invalid_phone_numbers WHERE list_id = 3') !== false) {
                    return 12;
                }
                if (strpos($query, 'DELETE FROM wp_mnem_logs WHERE id IN (91, 92)') !== false) {
                    return 2;
                }
                if (strpos($query, 'DELETE FROM wp_mnem_sms_list_subscribers WHERE list_id = 3') !== false) {
                    return 45;
                }
                if (strpos($query, 'DELETE FROM wp_mnem_sms_subscriber_lists WHERE id = 3') !== false) {
                    return 1;
                }

                return 1;
            }
        };

        $result = SmsSubscriberLists::delete(3);

        $this->assertTrue($result['success']);
        $this->assertSame(45, $result['deleted_counts']['subscribers']);
        $this->assertSame(12, $result['deleted_counts']['invalid_phones']);
        $this->assertSame(2, $result['deleted_counts']['logs']);
        $this->assertStringContainsString('START TRANSACTION', implode("\n", $GLOBALS['wpdb']->queries));
        $this->assertStringContainsString('COMMIT', implode("\n", $GLOBALS['wpdb']->queries));
        $this->assertContains('Queue table does not currently store SMS list_id references.', $result['notes']);
    }

    public function test_check_data_integrity_reports_sms_orphans()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_sms_list_subscribers s LEFT JOIN wp_mnem_sms_subscriber_lists l') !== false) {
                    return 1;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_invalid_phone_numbers'") !== false) {
                    return 'wp_mnem_invalid_phone_numbers';
                }
                if (strpos($query, 'FROM wp_mnem_invalid_phone_numbers p LEFT JOIN wp_mnem_sms_subscriber_lists l') !== false) {
                    return 2;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_queue'") !== false) {
                    return 'wp_mnem_queue';
                }
                if (strpos($query, 'COLUMN_NAME = \'list_id\'') !== false && strpos($query, 'wp_mnem_queue') !== false) {
                    return 1;
                }
                if (strpos($query, 'FROM wp_mnem_queue q LEFT JOIN wp_mnem_sms_subscriber_lists l') !== false) {
                    return 3;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_logs'") !== false) {
                    return 'wp_mnem_logs';
                }

                return 0;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT id, context FROM wp_mnem_logs') !== false) {
                    return array(
                        array('id' => 33, 'context' => '{"list_id":999}'),
                    );
                }

                return array();
            }
        };

        $result = SmsSubscriberLists::check_data_integrity();

        $this->assertSame(4, $result['issues_found']);
        $this->assertSame(7, $result['orphaned_records']);
    }

    public function test_cleanup_orphaned_records_removes_detected_orphans()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_sms_list_subscribers s LEFT JOIN wp_mnem_sms_subscriber_lists l') !== false) {
                    return 4;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_invalid_phone_numbers'") !== false) {
                    return 'wp_mnem_invalid_phone_numbers';
                }
                if (strpos($query, 'FROM wp_mnem_invalid_phone_numbers p LEFT JOIN wp_mnem_sms_subscriber_lists l') !== false) {
                    return 2;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_queue'") !== false) {
                    return 'wp_mnem_queue';
                }
                if (strpos($query, 'COLUMN_NAME = \'list_id\'') !== false && strpos($query, 'wp_mnem_queue') !== false) {
                    return 0;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_logs'") !== false) {
                    return 'wp_mnem_logs';
                }

                return 0;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT id, context FROM wp_mnem_logs') !== false) {
                    return array(
                        array('id' => 77, 'context' => '{"list_id":999}'),
                    );
                }

                return array();
            }

            public function query($query)
            {
                $this->queries[] = $query;
                if ($query === 'START TRANSACTION' || $query === 'COMMIT') {
                    return 1;
                }
                if (strpos($query, 'DELETE s FROM wp_mnem_sms_list_subscribers s LEFT JOIN wp_mnem_sms_subscriber_lists l') !== false) {
                    return 4;
                }
                if (strpos($query, 'DELETE p FROM wp_mnem_invalid_phone_numbers p LEFT JOIN wp_mnem_sms_subscriber_lists l') !== false) {
                    return 2;
                }
                if (strpos($query, 'DELETE FROM wp_mnem_logs WHERE id IN (77)') !== false) {
                    return 1;
                }

                return 1;
            }
        };

        $result = SmsSubscriberLists::cleanup_orphaned_records();

        $this->assertSame(7, $result['found']);
        $this->assertSame(7, $result['cleaned']);
        $this->assertSame(4, $result['details']['subscribers']);
        $this->assertSame(2, $result['details']['invalid_phones']);
        $this->assertSame(1, $result['details']['logs']);
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

    // -------------------------------------------------------------------------
    // Multi-country: country hint passed to add_subscriber
    // -------------------------------------------------------------------------

    public function test_add_subscriber_accepts_explicit_country_hint()
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
                $this->insert_id = 20;
                return 1;
            }
        };

        // Pass a Swedish E.164 number directly; it should be accepted.
        $result = SmsSubscriberLists::add_subscriber(1, 7, '+46701234567', 'SE');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['added']);
        $this->assertStringContainsString('+46701234567', $GLOBALS['wpdb']->last_query);
    }

    public function test_add_subscriber_with_country_hint_normalises_to_e164()
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
                $this->insert_id = 21;
                return 1;
            }
        };

        // Finnish local number with FI hint should be stored as E.164.
        $result = SmsSubscriberLists::add_subscriber(1, 7, '0401234567', 'FI');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['added']);
        // The stored number should start with +358 (Finnish dial code).
        $this->assertStringContainsString('+358', $GLOBALS['wpdb']->last_query);
    }
}
