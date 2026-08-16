<?php

defined('ABSPATH') || exit;

use MNEM\Campaigns;
use PHPUnit\Framework\TestCase;

class CampaignsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options']['mnem_campaign_sends_paused'] = 0;
        $GLOBALS['mnem_users'] = array();
    }

    public function test_valid_statuses_contains_expected_values()
    {
        $this->assertContains('draft', Campaigns::VALID_STATUSES);
        $this->assertContains('scheduled', Campaigns::VALID_STATUSES);
        $this->assertContains('sending', Campaigns::VALID_STATUSES);
        $this->assertContains('sent', Campaigns::VALID_STATUSES);
        $this->assertContains('cancelled', Campaigns::VALID_STATUSES);
    }

    public function test_valid_transition_logic()
    {
        $this->assertTrue(Campaigns::is_valid_transition('draft', 'scheduled'));
        $this->assertTrue(Campaigns::is_valid_transition('scheduled', 'sending'));
        $this->assertFalse(Campaigns::is_valid_transition('draft', 'sent'));
        $this->assertFalse(Campaigns::is_valid_transition('sent', 'draft'));
    }

    public function test_get_recipients_supports_custom_scope()
    {
        $recipients = Campaigns::get_recipients(
            array(
                'recipient_scope' => 'custom',
                'recipient_list' => "user@example.com\nUSER@example.com\ninvalid-email\nadmin@example.com",
            )
        );

        $this->assertSame(array('user@example.com', 'admin@example.com'), $recipients);
    }

    public function test_send_campaign_enqueues_recipients_and_updates_tracking()
    {
        $GLOBALS['mnem_users'] = array(
            (object) array(
                'user_email' => 'one@example.com',
                'display_name' => 'One User',
            ),
        );
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $campaign = array(
                'id' => 5,
                'site_id' => 1,
                'name' => 'Launch',
                'subject' => 'Hello {user_name} from {site_name}',
                'body' => 'Emailing {user_name} at {user_email} on {date}',
                'status' => 'scheduled',
                'recipient_scope' => 'custom',
                'recipient_list' => "one@example.com\ntwo@example.com",
                'sent_at' => '',
                'last_send_attempt_at' => '',
                'enqueue_failed_count' => 0,
            );

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;

                if (strpos($query, 'FROM wp_mnem_campaigns') !== false) {
                    return $this->campaign;
                }

                return array();
            }

            public function get_var($query)
            {
                $this->queries[] = $query;

                if (strpos($query, 'SHOW TABLES LIKE') !== false) {
                    return 'wp_mnem_logs';
                }

                if (strpos($query, 'FROM wp_mnem_suppression') !== false) {
                    return 0;
                }

                return 0;
            }
        };

        $result = Campaigns::send_campaign(5);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['queued']);
        $this->assertSame(0, $result['failed']);
        $this->assertNotEmpty(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, "INSERT INTO wp_mnem_queue (site_id, campaign_id") !== false;
        }));
        $insert_queries = array_values(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, "INSERT INTO wp_mnem_queue (site_id, campaign_id") !== false;
        }));
        $this->assertCount(2, $insert_queries);
        $this->assertStringContainsString("Hello One User from Test Site", $insert_queries[0]);
        $this->assertStringContainsString("Emailing One User at one@example.com on ", $insert_queries[0]);
        $this->assertStringContainsString("Hello two from Test Site", $insert_queries[1]);
        $this->assertStringContainsString("Emailing two at two@example.com on ", $insert_queries[1]);
        $this->assertStringNotContainsString('{user_name}', $insert_queries[0]);
        $this->assertStringNotContainsString('{user_email}', $insert_queries[0]);
        $this->assertNotEmpty(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, "UPDATE wp_mnem_campaigns SET total_recipients = 2") !== false;
        }));
    }
}
