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

    public function test_add_subscriber_restores_unsubscribed_user()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
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

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsSubscriberLists::add_subscriber(5, 7);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['added']);
        $this->assertSame('restored', $result['action']);
        $this->assertStringContainsString('restored', strtolower($result['message']));
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

    public function test_add_standalone_subscriber_restores_unsubscribed()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
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

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsSubscriberLists::add_standalone_subscriber(1, 'Jane', '+1234567890');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['added']);
        $this->assertSame('restored', $result['action']);
        $this->assertStringContainsString('restored', strtolower($result['message']));
    }

    public function test_search_subscribers_returns_paginated_results()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    array(
                        'user_id'             => 0,
                        'subscriber_name'     => 'Jane',
                        'phone_number'        => '+1234567890',
                        'subscribed_at'       => '2025-01-01 00:00:00',
                        'unsubscribed_at'     => null,
                        'unsubscribed_reason' => '',
                        'user_login'          => '',
                    ),
                );
            }
        };

        $result = SmsSubscriberLists::search_subscribers(1, 'Jane');

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('standalone', $result['rows'][0]['subscriber_type']);
        $this->assertSame('', $result['rows'][0]['display_name']);
        $this->assertSame(1, $result['current_page']);
        $this->assertSame(1, $result['total_pages']);
    }

    public function test_search_subscribers_includes_display_name_for_user_subscribers()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    array(
                        'id'                  => 12,
                        'list_id'             => 1,
                        'user_id'             => 7,
                        'subscriber_name'     => '',
                        'phone_number'        => '+1234567890',
                        'subscribed_at'       => '2025-01-01 00:00:00',
                        'unsubscribed_at'     => null,
                        'unsubscribed_reason' => '',
                        'user_login'          => 'alice_user',
                        'display_name'        => 'Alice Wonder',
                    ),
                );
            }
        };

        $result = SmsSubscriberLists::search_subscribers(1, 'Alice');

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('user', $result['rows'][0]['subscriber_type']);
        $this->assertSame('alice_user', $result['rows'][0]['user_login']);
        $this->assertSame('Alice Wonder', $result['rows'][0]['display_name']);

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('LOWER(u.display_name) LIKE', $queries);
    }

    public function test_search_subscribers_filters_by_status()
    {
        $queries_made = array();
        $GLOBALS['wpdb'] = new class($queries_made) extends wpdb {
            private $captured;
            public function __construct(&$ref)
            {
                $this->captured = &$ref;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                $this->captured[] = $query;
                return 0;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                $this->captured[] = $query;
                return array();
            }
        };

        SmsSubscriberLists::search_subscribers(2, '', 'unsubscribed', 50, 1);

        $found = false;
        foreach ($queries_made as $q) {
            if (strpos($q, 'unsubscribed') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Expected an SQL query containing "unsubscribed".');
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

    public function test_get_subscriber_by_phone_and_list_returns_matching_row()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 5,
                    'list_id' => 2,
                    'user_id' => 9,
                    'subscriber_name' => 'Jane',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                    'subscribed_at' => '2024-01-01 00:00:00',
                    'unsubscribed_at' => null,
                    'unsubscribed_reason' => '',
                );
            }
        };

        $result = SmsSubscriberLists::get_subscriber_by_phone_and_list(2, '+1234567890');

        $this->assertIsArray($result);
        $this->assertSame(5, $result['id']);
        $this->assertSame(9, $result['user_id']);
        $this->assertSame('subscribed', $result['subscription_status']);
    }

    public function test_get_subscriber_by_phone_and_list_returns_null_when_not_found()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }
        };

        $result = SmsSubscriberLists::get_subscriber_by_phone_and_list(2, '+1234567890');

        $this->assertNull($result);
    }

    public function test_get_subscriber_by_phone_and_list_returns_null_for_invalid_input()
    {
        $this->assertNull(SmsSubscriberLists::get_subscriber_by_phone_and_list(0, '+1234567890'));
        $this->assertNull(SmsSubscriberLists::get_subscriber_by_phone_and_list(2, ''));
    }

    public function test_unsubscribe_by_phone_and_list_marks_subscriber_unsubscribed()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public string $lastQuery = '';

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 5,
                    'list_id' => 2,
                    'user_id' => 9,
                    'subscriber_name' => 'Jane',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                    'subscribed_at' => '2024-01-01 00:00:00',
                    'unsubscribed_at' => null,
                    'unsubscribed_reason' => '',
                );
            }

            public function query($query)
            {
                $this->lastQuery = $query;
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsSubscriberLists::unsubscribe_by_phone_and_list(2, '+1234567890', 'Admin action');

        $this->assertTrue($result);
        $this->assertStringContainsString("'unsubscribed'", $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString('5', $GLOBALS['wpdb']->lastQuery);
    }

    public function test_unsubscribe_by_phone_and_list_returns_false_when_subscriber_missing()
    {
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

        $result = SmsSubscriberLists::unsubscribe_by_phone_and_list(2, '+1234567890');

        $this->assertFalse($result);
    }

    public function test_update_subscriber_normalises_to_e164_with_country_hint()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public string $lastQuery = '';

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'id <>') !== false) {
                    return null;
                }
                return array(
                    'id' => 5,
                    'list_id' => 1,
                    'user_id' => 7,
                    'subscriber_name' => '',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                );
            }

            public function query($query)
            {
                $this->lastQuery = $query;
                $this->queries[] = $query;
                return 1;
            }
        };

        // Finnish local number with FI hint should be stored as E.164.
        $result = SmsSubscriberLists::update_subscriber(5, '0401234567', '', 'FI');

        $this->assertTrue($result['success']);
        // The stored number should start with +358 (Finnish dial code).
        $this->assertStringContainsString('+358', $GLOBALS['wpdb']->lastQuery);
    }

    public function test_update_subscriber_updates_user_phone_number()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public string $lastQuery = '';

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'id <>') !== false) {
                    return null;
                }
                return array(
                    'id' => 5,
                    'list_id' => 1,
                    'user_id' => 7,
                    'subscriber_name' => '',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                );
            }

            public function query($query)
            {
                $this->lastQuery = $query;
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsSubscriberLists::update_subscriber(5, '+1987654321');

        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['action']);
        $this->assertStringContainsString('+1987654321', $GLOBALS['wpdb']->lastQuery);
        $this->assertStringNotContainsString('subscriber_name', $GLOBALS['wpdb']->lastQuery);
    }

    public function test_update_subscriber_updates_standalone_name_and_phone()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public string $lastQuery = '';

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'id <>') !== false) {
                    return null;
                }
                return array(
                    'id' => 9,
                    'list_id' => 1,
                    'user_id' => 0,
                    'subscriber_name' => 'Old Name',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                );
            }

            public function query($query)
            {
                $this->lastQuery = $query;
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsSubscriberLists::update_subscriber(9, '+1987654321', 'New Name');

        $this->assertTrue($result['success']);
        $this->assertSame('updated', $result['action']);
        $this->assertStringContainsString('+1987654321', $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString('New Name', $GLOBALS['wpdb']->lastQuery);
    }

    public function test_update_subscriber_rejects_invalid_phone_number()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 5,
                    'list_id' => 1,
                    'user_id' => 7,
                    'subscriber_name' => '',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsSubscriberLists::update_subscriber(5, 'not-a-phone-number');

        $this->assertFalse($result['success']);
        $this->assertSame('error', $result['action']);
    }

    public function test_update_subscriber_rejects_duplicate_phone_number()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'id <>') !== false) {
                    return array('id' => 3, 'user_id' => 8, 'phone_number' => '+1987654321', 'subscription_status' => 'subscribed');
                }
                return array(
                    'id' => 5,
                    'list_id' => 1,
                    'user_id' => 7,
                    'subscriber_name' => '',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                );
            }
        };

        $result = SmsSubscriberLists::update_subscriber(5, '+1987654321');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already subscribed', strtolower($result['message']));
    }

    public function test_update_subscriber_rejects_blocked_phone_number()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 5,
                    'list_id' => 1,
                    'user_id' => 7,
                    'subscriber_name' => '',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                );
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsSubscriberLists::update_subscriber(5, '+1987654321');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('blocked', strtolower($result['message']));
    }

    public function test_update_subscriber_rejects_country_not_in_allowed_list()
    {
        $GLOBALS['mnem_site_options']['mnem_allowed_countries'] = wp_json_encode(array('GB'));

        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 5,
                    'list_id' => 1,
                    'user_id' => 7,
                    'subscriber_name' => '',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                );
            }
        };

        $result = SmsSubscriberLists::update_subscriber(5, '+1987654321');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not in the allowed countries list', $result['message']);
    }

    public function test_update_subscriber_returns_error_when_not_found()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }
        };

        $result = SmsSubscriberLists::update_subscriber(999, '+1987654321');

        $this->assertFalse($result['success']);
        $this->assertSame('error', $result['action']);
        $this->assertStringContainsString('not found', strtolower($result['message']));
    }

    public function test_update_subscriber_requires_name_for_standalone()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 9,
                    'list_id' => 1,
                    'user_id' => 0,
                    'subscriber_name' => 'Old Name',
                    'phone_number' => '+1234567890',
                    'subscription_status' => 'subscribed',
                );
            }
        };

        $result = SmsSubscriberLists::update_subscriber(9, '+1987654321', '');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('name is required', strtolower($result['message']));
    }

    public function test_convert_standalone_to_users_converts_matching_subscriber()
    {
        $GLOBALS['mnem_user_data'][7]->display_name = 'Alice Example';

        $GLOBALS['wpdb'] = new class extends wpdb {
            public $update_queries = array();

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, "subscription_status IN ('subscribed', 'unsubscribed')") !== false) {
                    return array(array(
                        'id' => 1,
                        'list_id' => 5,
                        'user_id' => 0,
                        'subscriber_name' => 'Standalone Alice',
                        'phone_number' => '+12345678901',
                        'subscription_status' => 'subscribed',
                        'unsubscribed_reason' => '',
                    ));
                }
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
                if (strpos($query, 'usermeta') !== false) {
                    return 7;
                }
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'UPDATE') !== false && strpos($query, 'subscriber_name') !== false) {
                    $this->update_queries[] = $query;
                }
                $this->insert_id = 99;
                return 1;
            }
        };

        $result = SmsSubscriberLists::convert_standalone_to_users(5);

        $this->assertSame(1, $result['converted']);
        $this->assertSame(0, $result['not_found']);
        $this->assertSame(0, $result['errors']);
        $this->assertCount(1, $result['details']);
        $this->assertSame(7, $result['details'][0]['user_id']);
        $this->assertSame('Alice Example', $result['details'][0]['display_name']);
        $this->assertNotEmpty($GLOBALS['wpdb']->update_queries);
        $this->assertStringContainsString('Alice Example', $GLOBALS['wpdb']->update_queries[0]);
    }

    public function test_convert_standalone_to_users_reports_not_found_when_no_matching_user()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, "subscription_status IN ('subscribed', 'unsubscribed')") !== false) {
                    return array(array(
                        'id' => 2,
                        'list_id' => 5,
                        'user_id' => 0,
                        'subscriber_name' => 'Unknown Person',
                        'phone_number' => '+19999999999',
                        'subscription_status' => 'subscribed',
                        'unsubscribed_reason' => '',
                    ));
                }
                return array();
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                // No matching user found for the phone number.
                return 0;
            }
        };

        $result = SmsSubscriberLists::convert_standalone_to_users(5);

        $this->assertSame(0, $result['converted']);
        $this->assertSame(1, $result['not_found']);
        $this->assertSame(0, $result['errors']);
        $this->assertSame(array(), $result['details']);
    }

    public function test_convert_standalone_to_users_preserves_unsubscribed_status()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $unsubscribe_queries = array();

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, "subscription_status IN ('subscribed', 'unsubscribed')") !== false) {
                    return array(array(
                        'id' => 3,
                        'list_id' => 5,
                        'user_id' => 0,
                        'subscriber_name' => 'Standalone Bob',
                        'phone_number' => '+13456789012',
                        'subscription_status' => 'unsubscribed',
                        'unsubscribed_reason' => 'Opted out',
                    ));
                }
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
                if (strpos($query, 'usermeta') !== false) {
                    return 8;
                }
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                if (strpos($query, "'unsubscribed'") !== false) {
                    $this->unsubscribe_queries[] = $query;
                }
                $this->insert_id = 100;
                return 1;
            }
        };

        $result = SmsSubscriberLists::convert_standalone_to_users(5);

        $this->assertSame(1, $result['converted']);
        $this->assertSame(8, $result['details'][0]['user_id']);
        $this->assertNotEmpty($GLOBALS['wpdb']->unsubscribe_queries);
    }

    public function test_convert_standalone_to_users_restores_standalone_when_add_subscriber_fails()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $insert_call_count = 0;
            public $insert_queries = array();

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, "subscription_status IN ('subscribed', 'unsubscribed')") !== false) {
                    return array(array(
                        'id' => 4,
                        'list_id' => 5,
                        'user_id' => 0,
                        'subscriber_name' => 'Standalone Carl',
                        'phone_number' => '+14567890123',
                        'subscription_status' => 'subscribed',
                        'unsubscribed_reason' => '',
                    ));
                }
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
                if (strpos($query, 'usermeta') !== false) {
                    return 9;
                }
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'INSERT INTO') !== false) {
                    $this->insert_call_count++;
                    $this->insert_queries[] = $query;
                    if ($this->insert_call_count === 1) {
                        // Simulate the first INSERT (add_subscriber) failing.
                        return false;
                    }
                    $this->insert_id = 55;
                }
                return 1;
            }
        };

        $result = SmsSubscriberLists::convert_standalone_to_users(5);

        $this->assertSame(0, $result['converted']);
        $this->assertSame(1, $result['errors']);
        $this->assertSame(0, $result['not_found']);
        // The standalone subscriber should have been re-inserted after the failed conversion.
        $this->assertCount(2, $GLOBALS['wpdb']->insert_queries);
        $this->assertStringContainsString('Standalone Carl', $GLOBALS['wpdb']->insert_queries[1]);
    }

    public function test_convert_standalone_to_users_restores_unsubscribed_status_on_add_failure()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $insert_call_count = 0;
            public $queries_seen = array();

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, "subscription_status IN ('subscribed', 'unsubscribed')") !== false) {
                    return array(array(
                        'id' => 6,
                        'list_id' => 5,
                        'user_id' => 0,
                        'subscriber_name' => 'Standalone Dana',
                        'phone_number' => '+15678901234',
                        'subscription_status' => 'unsubscribed',
                        'unsubscribed_reason' => 'Opted out',
                    ));
                }
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
                if (strpos($query, 'usermeta') !== false) {
                    return 10;
                }
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                $this->queries_seen[] = $query;
                if (strpos($query, 'INSERT INTO') !== false) {
                    $this->insert_call_count++;
                    if ($this->insert_call_count === 1) {
                        // Simulate the first INSERT (add_subscriber) failing.
                        return false;
                    }
                    $this->insert_id = 77;
                }
                return 1;
            }
        };

        $result = SmsSubscriberLists::convert_standalone_to_users(5);

        $this->assertSame(0, $result['converted']);
        $this->assertSame(1, $result['errors']);

        $unsubscribed_queries = array_filter($GLOBALS['wpdb']->queries_seen, static function ($query) {
            return strpos($query, "'unsubscribed'") !== false;
        });
        $this->assertNotEmpty($unsubscribed_queries, 'Restored standalone subscriber should preserve unsubscribed status.');
    }

    public function test_convert_standalone_to_users_logs_error_when_restore_also_fails()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $insert_call_count = 0;

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, "subscription_status IN ('subscribed', 'unsubscribed')") !== false) {
                    return array(array(
                        'id' => 9,
                        'list_id' => 5,
                        'user_id' => 0,
                        'subscriber_name' => 'Standalone Erin',
                        'phone_number' => '+16789012345',
                        'subscription_status' => 'subscribed',
                        'unsubscribed_reason' => '',
                    ));
                }
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
                if (strpos($query, 'usermeta') !== false) {
                    return 11;
                }
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'INSERT INTO') !== false) {
                    $this->insert_call_count++;
                    // Simulate both the add_subscriber INSERT and the
                    // add_standalone_subscriber restore INSERT failing.
                    return false;
                }
                return 1;
            }
        };

        $result = SmsSubscriberLists::convert_standalone_to_users(5);

        $this->assertSame(0, $result['converted']);
        $this->assertSame(1, $result['errors']);
        $this->assertSame(0, $result['not_found']);
        // Both the original conversion attempt and the restore attempt should
        // have tried to INSERT, even though both failed.
        $this->assertSame(2, $GLOBALS['wpdb']->insert_call_count);
    }

    public function test_convert_standalone_to_users_matches_via_php_fallback_scan()
    {
        $GLOBALS['mnem_user_data'][7]->display_name = 'Fiona Fallback';

        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, "subscription_status IN ('subscribed', 'unsubscribed')") !== false) {
                    return array(array(
                        'id' => 10,
                        'list_id' => 5,
                        'user_id' => 0,
                        'subscriber_name' => 'Standalone Fiona',
                        // Phone stored in valid E.164 format; the candidate usermeta
                        // value below uses dots, which the SQL-side REPLACE()
                        // normalization does not strip, forcing the PHP fallback.
                        'phone_number' => '+12345678901',
                        'subscription_status' => 'subscribed',
                        'unsubscribed_reason' => '',
                    ));
                }
                if (strpos($query, 'FROM wp_usermeta') !== false) {
                    // Candidate rows for the last-resort PHP fallback scan; the
                    // meta value uses different formatting (dashes) than the
                    // subscriber's stored phone number.
                    return array(
                        array('user_id' => 7, 'meta_value' => '1.234.567.8901'),
                        array('user_id' => 12, 'meta_value' => '999-999-9999'),
                    );
                }
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
                // Both the exact-match and SQL-side normalized queries miss,
                // forcing the code to fall back to the PHP candidate scan.
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                $this->insert_id = 99;
                return 1;
            }
        };

        $result = SmsSubscriberLists::convert_standalone_to_users(5);

        $this->assertSame(1, $result['converted']);
        $this->assertSame(0, $result['not_found']);
        $this->assertSame(0, $result['errors']);
        $this->assertCount(1, $result['details']);
        $this->assertSame(7, $result['details'][0]['user_id']);
    }

    public function test_convert_standalone_to_users_rejects_short_phone_numbers_in_fallback()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $fallback_query_run = false;

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, "subscription_status IN ('subscribed', 'unsubscribed')") !== false) {
                    return array(array(
                        'id' => 11,
                        'list_id' => 5,
                        'user_id' => 0,
                        'subscriber_name' => 'Standalone Gina',
                        // Too short to satisfy MIN_PHONE_MATCH_DIGITS once normalized.
                        'phone_number' => '12345',
                        'subscription_status' => 'subscribed',
                        'unsubscribed_reason' => '',
                    ));
                }
                if (strpos($query, 'FROM wp_usermeta') !== false) {
                    $this->fallback_query_run = true;
                    return array(array('user_id' => 7, 'meta_value' => '12345'));
                }
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

        $result = SmsSubscriberLists::convert_standalone_to_users(5);

        $this->assertSame(0, $result['converted']);
        $this->assertSame(1, $result['not_found']);
        $this->assertSame(0, $result['errors']);
        // The short phone number should be rejected before the fallback
        // usermeta scan even runs.
        $this->assertFalse($GLOBALS['wpdb']->fallback_query_run);
    }
}
