<?php

defined('ABSPATH') || exit;

use MNEM\SmsCampaigns;
use PHPUnit\Framework\TestCase;

class SmsCampaignsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options'] = array();
        $GLOBALS['mnem_transients']   = array();
        unset($GLOBALS['mnem_current_user_id']);
    }

    // ---------------------------------------------------------------------------
    // CRUD - create
    // ---------------------------------------------------------------------------

    public function test_create_returns_insert_id()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[]  = $query;
                $this->insert_id  = 5;
                return 1;
            }
        };

        $id = SmsCampaigns::create(1, array(
            'name'         => 'Test Campaign',
            'message_body' => 'Hello world',
            'sms_list_id'  => 3,
            'created_by'   => 1,
        ));

        $this->assertSame(5, $id);
    }

    public function test_create_returns_false_on_invalid_data()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {};

        // Empty name
        $result = SmsCampaigns::create(1, array(
            'name'         => '',
            'message_body' => 'Hello',
            'sms_list_id'  => 3,
            'created_by'   => 1,
        ));
        $this->assertFalse($result);

        // Empty message body
        $result = SmsCampaigns::create(1, array(
            'name'         => 'Campaign',
            'message_body' => '',
            'sms_list_id'  => 3,
            'created_by'   => 1,
        ));
        $this->assertFalse($result);

        // Invalid sms_list_id
        $result = SmsCampaigns::create(1, array(
            'name'         => 'Campaign',
            'message_body' => 'Hello',
            'sms_list_id'  => 0,
            'created_by'   => 1,
        ));
        $this->assertFalse($result);
    }

    public function test_create_returns_false_on_db_error()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return false;
            }
        };

        $result = SmsCampaigns::create(1, array(
            'name'         => 'Test',
            'message_body' => 'Message',
            'sms_list_id'  => 2,
            'created_by'   => 1,
        ));
        $this->assertFalse($result);
    }

    public function test_create_defaults_created_by_to_current_user()
    {
        $GLOBALS['mnem_current_user_id'] = 42;
        $GLOBALS['wpdb']                 = new class extends wpdb {
            public string $lastQuery = '';
            public function query($query)
            {
                $this->lastQuery = $query;
                $this->insert_id = 9;
                return 1;
            }
        };

        $id = SmsCampaigns::create(1, array(
            'name'         => 'Default User Campaign',
            'message_body' => 'Hello world',
            'sms_list_id'  => 3,
        ));

        $this->assertSame(9, $id);
        $this->assertRegExp('/,\s*42,\s*/', $GLOBALS['wpdb']->lastQuery);
    }

    // ---------------------------------------------------------------------------
    // CRUD - get
    // ---------------------------------------------------------------------------

    public function test_get_returns_campaign_array()
    {
        $expected = array('id' => 7, 'name' => 'My Campaign', 'status' => 'draft');

        $GLOBALS['wpdb'] = new class($expected) extends wpdb {
            private $expectedRow;
            public function __construct($row) { $this->expectedRow = $row; }
            public function get_row($query, $output = OBJECT)
            {
                return $this->expectedRow;
            }
        };

        $result = SmsCampaigns::get(7);
        $this->assertSame($expected, $result);
    }

    public function test_get_returns_null_for_missing_campaign()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT) { return null; }
        };

        $this->assertNull(SmsCampaigns::get(999));
    }

    // ---------------------------------------------------------------------------
    // CRUD - get_list
    // ---------------------------------------------------------------------------

    public function test_get_list_returns_array()
    {
        $rows = array(
            array('id' => 1, 'name' => 'A', 'status' => 'draft'),
            array('id' => 2, 'name' => 'B', 'status' => 'sending'),
        );

        $GLOBALS['wpdb'] = new class($rows) extends wpdb {
            private $rows;
            public function __construct($rows) { $this->rows = $rows; }
            public function get_results($query, $output = OBJECT) { return $this->rows; }
        };

        $result = SmsCampaigns::get_list(1);
        $this->assertCount(2, $result);
    }

    public function test_get_list_with_status_filter()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public string $lastQuery = '';
            public function get_results($query, $output = OBJECT)
            {
                $this->lastQuery = $query;
                return array();
            }
        };

        SmsCampaigns::get_list(1, 'draft');
        $this->assertStringContainsString('draft', $GLOBALS['wpdb']->lastQuery);
    }

    // ---------------------------------------------------------------------------
    // CRUD - update
    // ---------------------------------------------------------------------------

    public function test_update_returns_true_on_success()
    {
        $campaign = array('id' => 3, 'name' => 'Old', 'description' => '', 'message_body' => 'Old body', 'sms_list_id' => 1, 'status' => 'draft', 'scheduled_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
            public function query($query) { $this->queries[] = $query; return 1; }
        };

        $result = SmsCampaigns::update(3, array('name' => 'New Name'));
        $this->assertTrue($result);
    }

    public function test_update_returns_false_for_missing_campaign()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT) { return null; }
        };

        $this->assertFalse(SmsCampaigns::update(99, array('name' => 'X')));
    }

    public function test_update_returns_false_for_cancelled_campaign()
    {
        $campaign = array('id' => 5, 'name' => 'C', 'description' => '', 'message_body' => 'B', 'sms_list_id' => 1, 'status' => 'cancelled', 'scheduled_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $this->assertFalse(SmsCampaigns::update(5, array('name' => 'X')));
    }

    public function test_duplicate_returns_insert_id_for_completed_campaign()
    {
        $campaign = array(
            'id' => 9,
            'site_id' => 1,
            'name' => 'Q4 Promotion',
            'description' => 'Seasonal campaign',
            'message_body' => 'Promo text',
            'sms_list_id' => 3,
            'status' => 'completed',
            'created_by' => 11,
        );

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public string $lastQuery = '';

            public function __construct($campaign)
            {
                $this->campaign = $campaign;
            }

            public function get_row($query, $output = OBJECT)
            {
                return $this->campaign;
            }

            public function query($query)
            {
                $this->lastQuery = $query;
                $this->insert_id = 44;
                return 1;
            }
        };

        $id = SmsCampaigns::duplicate(9);

        $this->assertSame(44, $id);
        $this->assertStringContainsString("'Q4 Promotion [Copy]'", $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString("'Seasonal campaign'", $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString("'Promo text'", $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString("'draft'", $GLOBALS['wpdb']->lastQuery);
    }

    public function test_duplicate_returns_false_for_non_terminal_campaign()
    {
        $campaign = array(
            'id' => 10,
            'site_id' => 1,
            'name' => 'Active',
            'description' => '',
            'message_body' => 'Body',
            'sms_list_id' => 2,
            'status' => 'draft',
            'created_by' => 1,
        );

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public string $lastQuery = '';

            public function __construct($campaign)
            {
                $this->campaign = $campaign;
            }

            public function get_row($query, $output = OBJECT)
            {
                return $this->campaign;
            }

            public function query($query)
            {
                $this->lastQuery = $query;
                return 1;
            }
        };

        $this->assertFalse(SmsCampaigns::duplicate(10));
        $this->assertSame('', $GLOBALS['wpdb']->lastQuery);
    }

    // ---------------------------------------------------------------------------
    // CRUD - delete
    // ---------------------------------------------------------------------------

    public function test_delete_returns_true_on_success()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query) { $this->queries[] = $query; return 1; }
        };

        $this->assertTrue(SmsCampaigns::delete(3));
    }

    public function test_delete_returns_false_on_db_error()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query) { return false; }
        };

        $this->assertFalse(SmsCampaigns::delete(3));
    }

    // ---------------------------------------------------------------------------
    // Status helpers
    // ---------------------------------------------------------------------------

    public function test_valid_transitions()
    {
        $this->assertTrue(SmsCampaigns::is_valid_transition('draft', 'scheduled'));
        $this->assertTrue(SmsCampaigns::is_valid_transition('draft', 'cancelled'));
        $this->assertTrue(SmsCampaigns::is_valid_transition('sending', 'paused'));
        $this->assertTrue(SmsCampaigns::is_valid_transition('paused', 'sending'));
        $this->assertFalse(SmsCampaigns::is_valid_transition('completed', 'sending'));
        $this->assertFalse(SmsCampaigns::is_valid_transition('cancelled', 'draft'));
        $this->assertFalse(SmsCampaigns::is_valid_transition('draft', 'completed'));
    }

    public function test_update_status_invalid_status_returns_false()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {};
        $this->assertFalse(SmsCampaigns::update_status(1, 'nonexistent'));
    }

    public function test_update_status_invalid_transition_returns_false()
    {
        $campaign = array('id' => 1, 'status' => 'completed', 'started_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $this->assertFalse(SmsCampaigns::update_status(1, 'sending'));
    }

    public function test_auto_update_campaign_status_marks_sending_campaign_completed()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                if (strpos($query, "FROM wp_mnem_sms_campaigns WHERE id = 11") !== false) {
                    return array('id' => 11, 'status' => 'sending', 'total_recipients' => 3, 'started_at' => null);
                }

                if (strpos($query, "FROM wp_mnem_sms_queue WHERE sms_campaign_id = 11") !== false) {
                    return array('total_recipients' => 3, 'sent_count' => 2, 'failed_count' => 1);
                }

                return null;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsCampaigns::auto_update_campaign_status(11);

        $this->assertTrue($result);
        $joined = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("UPDATE wp_mnem_sms_campaigns SET total_recipients = 3, sent_count = 2, failed_count = 1", $joined);
        $this->assertStringContainsString("status = 'completed'", $joined);
    }

    public function test_auto_update_campaign_status_keeps_sending_when_pending_items_remain()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                if (strpos($query, "FROM wp_mnem_sms_campaigns WHERE id = 12") !== false) {
                    return array('id' => 12, 'status' => 'sending', 'total_recipients' => 3, 'started_at' => null);
                }

                if (strpos($query, "FROM wp_mnem_sms_queue WHERE sms_campaign_id = 12") !== false) {
                    return array('total_recipients' => 3, 'sent_count' => 1, 'failed_count' => 1);
                }

                return null;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = SmsCampaigns::auto_update_campaign_status(12);

        $this->assertFalse($result);
        $joined = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("UPDATE wp_mnem_sms_campaigns SET total_recipients = 3, sent_count = 1, failed_count = 1", $joined);
        $this->assertStringNotContainsString("status = 'completed'", $joined);
    }

    // ---------------------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------------------

    public function test_send_now_returns_error_for_missing_campaign()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT) { return null; }
        };

        $result = SmsCampaigns::send_now(999);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    public function test_send_now_fails_for_completed_campaign()
    {
        $campaign = array('id' => 2, 'status' => 'completed', 'sms_list_id' => 1, 'message_body' => 'Hi', 'site_id' => 1, 'started_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $result = SmsCampaigns::send_now(2);
        $this->assertFalse($result['success']);
    }

    public function test_queue_recipients_inserts_only_existing_queue_columns()
    {
        $campaign = array(
            'id' => 3,
            'status' => 'scheduled',
            'sms_list_id' => 7,
            'message_body' => 'Available variables: {user_name}',
            'site_id' => 12,
            'started_at' => null,
        );
        $subscribers = array(
            array(
                'user_id' => 0,
                'subscriber_name' => 'Test User',
                'phone_number' => '+351911969387',
            ),
        );

        $GLOBALS['wpdb'] = new class($campaign, $subscribers) extends wpdb {
            private $campaign;
            private $subscribers;

            public function __construct($campaign, $subscribers)
            {
                $this->campaign    = $campaign;
                $this->subscribers = $subscribers;
            }

            public function get_row($query, $output = OBJECT)
            {
                return $this->campaign;
            }

            public function get_results($query, $output = OBJECT)
            {
                return $this->subscribers;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $queued = SmsCampaigns::queue_recipients(3);
        $insert_query = $GLOBALS['wpdb']->queries[0];

        $this->assertSame(1, $queued);
        $this->assertStringContainsString(
            'INSERT INTO wp_mnem_sms_queue (site_id, phone_number, body, status, sms_campaign_id)',
            $insert_query
        );
        $this->assertStringContainsString("'+351911969387'", $insert_query);
        $this->assertStringContainsString("'pending'", $insert_query);
        $this->assertStringNotContainsString("'sms'", $insert_query);
        $this->assertStringNotContainsString('message_type', $insert_query);
        $this->assertStringNotContainsString('created_at', $insert_query);
        $this->assertStringNotContainsString('updated_at', $insert_query);
    }

    public function test_pause_calls_update_status()
    {
        $campaign = array('id' => 1, 'status' => 'sending', 'started_at' => '2024-01-01 00:00:00');

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public bool $updated = false;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
            public function query($query) { $this->queries[] = $query; $this->updated = true; return 1; }
        };

        $result = SmsCampaigns::pause(1);
        $this->assertTrue($result);
    }

    public function test_resume_fails_for_draft_campaign()
    {
        $campaign = array('id' => 1, 'status' => 'draft', 'started_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $result = SmsCampaigns::resume(1);
        $this->assertFalse($result);
    }

    public function test_cancel_valid_campaign()
    {
        $campaign = array('id' => 3, 'status' => 'sending', 'started_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
            public function query($query) { $this->queries[] = $query; return 1; }
        };

        $result = SmsCampaigns::cancel(3);
        $this->assertTrue($result);
    }

    public function test_can_cancel_sending_campaign()
    {
        $campaign = array('id' => 4, 'status' => 'sending', 'started_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $this->assertTrue(SmsCampaigns::can_cancel(4));
    }

    public function test_can_cancel_completed_campaign_returns_false()
    {
        $campaign = array('id' => 5, 'status' => 'completed', 'started_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $this->assertFalse(SmsCampaigns::can_cancel(5));
    }

    public function test_schedule_draft_campaign()
    {
        $campaign = array('id' => 6, 'status' => 'draft', 'started_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
            public function query($query) { $this->queries[] = $query; return 1; }
        };

        $result = SmsCampaigns::schedule(6, '2025-12-25 10:00:00');
        $this->assertTrue($result);
    }

    public function test_schedule_non_draft_returns_false()
    {
        $campaign = array('id' => 7, 'status' => 'sending', 'started_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $this->assertFalse(SmsCampaigns::schedule(7, '2025-12-25 10:00:00'));
    }

    // ---------------------------------------------------------------------------
    // Delivery stats
    // ---------------------------------------------------------------------------

    public function test_get_delivery_stats_returns_correct_keys()
    {
        $campaign = array(
            'id'               => 8,
            'status'           => 'sending',
            'total_recipients' => '100',
            'sent_count'       => '60',
            'failed_count'     => '5',
            'bounce_count'     => '2',
        );

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $stats = SmsCampaigns::get_delivery_stats(8);

        $this->assertSame(100, $stats['total_recipients']);
        $this->assertSame(60, $stats['sent_count']);
        $this->assertSame(5, $stats['failed_count']);
        $this->assertSame(2, $stats['bounce_count']);
        $this->assertSame(35, $stats['pending_count']);
        $this->assertSame('sending', $stats['status']);
    }

    public function test_get_delivery_stats_returns_empty_for_missing()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT) { return null; }
        };

        $this->assertSame(array(), SmsCampaigns::get_delivery_stats(999));
    }

    // ---------------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------------

    public function test_validate_campaign_data_valid()
    {
        $result = SmsCampaigns::validate_campaign_data(array(
            'name'         => 'My Campaign',
            'message_body' => 'Hello {user_name}',
            'sms_list_id'  => 1,
        ));

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    public function test_validate_campaign_data_missing_name()
    {
        $result = SmsCampaigns::validate_campaign_data(array(
            'name'         => '',
            'message_body' => 'Hello',
            'sms_list_id'  => 1,
        ));

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_validate_campaign_data_missing_body()
    {
        $result = SmsCampaigns::validate_campaign_data(array(
            'name'         => 'Valid',
            'message_body' => '',
            'sms_list_id'  => 1,
        ));

        $this->assertFalse($result['valid']);
    }

    public function test_validate_campaign_data_invalid_list()
    {
        $result = SmsCampaigns::validate_campaign_data(array(
            'name'         => 'Valid',
            'message_body' => 'Hello',
            'sms_list_id'  => 0,
        ));

        $this->assertFalse($result['valid']);
    }

    public function test_validate_campaign_data_name_too_long()
    {
        $result = SmsCampaigns::validate_campaign_data(array(
            'name'         => str_repeat('x', 256),
            'message_body' => 'Hello',
            'sms_list_id'  => 1,
        ));

        $this->assertFalse($result['valid']);
    }

    // ---------------------------------------------------------------------------
    // Segment calculation
    // ---------------------------------------------------------------------------

    public function test_calculate_segments_single_segment()
    {
        $result = SmsCampaigns::calculate_segments('Hello world');
        $this->assertSame(1, $result['segments']);
        $this->assertSame(160, $result['chars_per_segment']);
    }

    public function test_calculate_segments_exactly_160_chars()
    {
        $msg    = str_repeat('a', 160);
        $result = SmsCampaigns::calculate_segments($msg);
        $this->assertSame(1, $result['segments']);
        $this->assertSame(160, $result['chars']);
    }

    public function test_calculate_segments_multi_segment()
    {
        $msg    = str_repeat('a', 161);
        $result = SmsCampaigns::calculate_segments($msg);
        $this->assertSame(2, $result['segments']);
    }

    public function test_calculate_segments_unicode_single()
    {
        $msg    = 'Héllo'; // accented char triggers unicode path
        $result = SmsCampaigns::calculate_segments($msg);
        $this->assertSame(1, $result['segments']);
        $this->assertSame(70, $result['chars_per_segment']);
    }

    public function test_calculate_segments_unicode_multi()
    {
        $msg    = str_repeat('é', 71);
        $result = SmsCampaigns::calculate_segments($msg);
        $this->assertSame(2, $result['segments']);
    }

    // ---------------------------------------------------------------------------
    // Format for display
    // ---------------------------------------------------------------------------

    public function test_format_for_display_adds_pending_count()
    {
        $campaign = array(
            'total_recipients' => '10',
            'sent_count'       => '4',
            'failed_count'     => '2',
            'bounce_count'     => '1',
        );

        $result = SmsCampaigns::format_for_display($campaign);

        $this->assertSame(10, $result['total_recipients']);
        $this->assertSame(4, $result['sent_count']);
        $this->assertSame(4, $result['pending_count']);
    }

    // ---------------------------------------------------------------------------
    // Preview & test send
    // ---------------------------------------------------------------------------

    public function test_get_recipient_count_returns_integer()
    {
        $campaign = array('id' => 1, 'sms_list_id' => 2);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
            public function get_var($query) { return '42'; }
        };

        $this->assertSame(42, SmsCampaigns::get_recipient_count(1));
    }

    public function test_get_recipient_count_returns_zero_for_missing_campaign()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT) { return null; }
        };

        $this->assertSame(0, SmsCampaigns::get_recipient_count(99));
    }

    public function test_send_test_returns_error_for_missing_campaign()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT) { return null; }
        };

        $result = SmsCampaigns::send_test(99, '+15550001234');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    public function test_send_test_returns_error_for_empty_phone()
    {
        $campaign = array('id' => 1, 'message_body' => 'Hi', 'status' => 'draft');

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $result = SmsCampaigns::send_test(1, '');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('required', $result['message']);
    }

    // ---------------------------------------------------------------------------
    // update_delivery_tracking
    // ---------------------------------------------------------------------------

    public function test_update_delivery_tracking_returns_true()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query) { $this->queries[] = $query; return 1; }
        };

        $result = SmsCampaigns::update_delivery_tracking(1, 100, 50, 5, '2024-01-01 00:00:00');
        $this->assertTrue($result);
    }

    public function test_update_delivery_tracking_without_started_at()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query) { $this->queries[] = $query; return 1; }
        };

        $result = SmsCampaigns::update_delivery_tracking(1, 10, 5, 1);
        $this->assertTrue($result);
    }

    // ---------------------------------------------------------------------------
    // Queue cleanup on pause/cancel/delete
    // ---------------------------------------------------------------------------

    public function test_update_returns_false_for_sending_campaign()
    {
        $campaign = array('id' => 8, 'name' => 'C', 'description' => '', 'message_body' => 'B', 'sms_list_id' => 1, 'status' => 'sending', 'scheduled_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
        };

        $this->assertFalse(SmsCampaigns::update(8, array('name' => 'New Name')));
    }

    public function test_cancel_queued_items_marks_pending_items_cancelled()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public string $lastQuery = '';
            public function query($query) { $this->lastQuery = $query; return 3; }
        };

        $affected = SmsCampaigns::cancel_queued_items(5);

        $this->assertSame(3, $affected);
        $this->assertStringContainsString('mnem_sms_queue', $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString("'cancelled'", $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString("'pending'", $GLOBALS['wpdb']->lastQuery);
    }

    public function test_delete_queued_items_deletes_all_items()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public string $lastQuery = '';
            public function query($query) { $this->lastQuery = $query; return 4; }
        };

        $deleted = SmsCampaigns::delete_queued_items(5);

        $this->assertSame(4, $deleted);
        $this->assertStringContainsString('DELETE FROM', $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString('mnem_sms_queue', $GLOBALS['wpdb']->lastQuery);
    }

    public function test_pause_cancels_queued_items()
    {
        $campaign = array('id' => 1, 'status' => 'sending', 'started_at' => '2024-01-01 00:00:00');

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public array $allQueries = array();
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
            public function query($query) { $this->allQueries[] = $query; return 1; }
        };

        $this->assertTrue(SmsCampaigns::pause(1));

        $queue_queries = array_filter($GLOBALS['wpdb']->allQueries, function ($q) {
            return strpos($q, 'mnem_sms_queue') !== false;
        });
        $this->assertNotEmpty($queue_queries);
    }

    public function test_cancel_cancels_queued_items()
    {
        $campaign = array('id' => 3, 'status' => 'sending', 'started_at' => null);

        $GLOBALS['wpdb'] = new class($campaign) extends wpdb {
            private $campaign;
            public array $allQueries = array();
            public function __construct($campaign) { $this->campaign = $campaign; }
            public function get_row($query, $output = OBJECT) { return $this->campaign; }
            public function query($query) { $this->allQueries[] = $query; return 1; }
        };

        $this->assertTrue(SmsCampaigns::cancel(3));

        $queue_queries = array_filter($GLOBALS['wpdb']->allQueries, function ($q) {
            return strpos($q, 'mnem_sms_queue') !== false;
        });
        $this->assertNotEmpty($queue_queries);
    }

    public function test_delete_removes_queued_items()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public array $allQueries = array();
            public function query($query) { $this->allQueries[] = $query; return 1; }
        };

        $this->assertTrue(SmsCampaigns::delete(3));

        $queue_queries = array_filter($GLOBALS['wpdb']->allQueries, function ($q) {
            return strpos($q, 'mnem_sms_queue') !== false;
        });
        $this->assertNotEmpty($queue_queries);
    }
}
