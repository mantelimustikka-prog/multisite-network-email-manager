<?php

defined('ABSPATH') || exit;

use MNEM\UserEventsCampaign;
use PHPUnit\Framework\TestCase;

class UserEventsCampaignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options'][UserEventsCampaign::OPTION_RULES] = '[]';
    }

    public function test_rule_matches_checks_event_role_and_site()
    {
        $rule = array(
            'event_type' => 'user_register',
            'campaign_id' => 5,
            'enabled' => true,
            'conditions' => array(
                'role' => 'subscriber',
                'site_id' => '1',
            ),
        );
        $user = (object) array(
            'roles' => array('subscriber'),
            'user_email' => 'user@example.com',
        );

        $this->assertTrue(UserEventsCampaign::rule_matches($rule, 'user_register', $user, 1));
        $this->assertFalse(UserEventsCampaign::rule_matches($rule, 'user_delete', $user, 1));
        $this->assertFalse(UserEventsCampaign::rule_matches($rule, 'user_register', $user, 2));
    }

    public function test_save_rules_stores_json_encoded_rules()
    {
        $saved = UserEventsCampaign::save_rules(
            array(
                array(
                    'event_type' => 'user_register',
                    'campaign_id' => 11,
                    'enabled' => true,
                    'conditions' => array('role' => 'any', 'site_id' => 'any'),
                ),
            )
        );

        $this->assertTrue($saved);
        $rules = UserEventsCampaign::get_rules();
        $this->assertCount(1, $rules);
        $this->assertSame('user_register', $rules[0]['event_type']);
    }

    public function test_trigger_event_sends_matching_campaign_to_registered_user()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $campaign = array(
                'id' => 11,
                'site_id' => 1,
                'name' => 'Welcome',
                'subject' => 'Hello',
                'body' => 'Welcome aboard',
                'status' => 'scheduled',
                'recipient_scope' => 'custom',
                'recipient_list' => '',
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

                if (strpos($query, 'FROM wp_mnem_queue') !== false) {
                    return array();
                }

                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SHOW TABLES LIKE') !== false) {
                    return 'wp_mnem_logs';
                }

                return 0;
            }
        };

        UserEventsCampaign::save_rules(
            array(
                array(
                    'event_type' => 'user_register',
                    'campaign_id' => 11,
                    'enabled' => true,
                    'conditions' => array('role' => 'subscriber', 'site_id' => '1'),
                ),
            )
        );

        $user = (object) array(
            'ID' => 99,
            'user_email' => 'newuser@example.com',
            'roles' => array('subscriber'),
        );
        $count = UserEventsCampaign::trigger_event('user_register', 99, array('site_id' => 1, 'user' => $user));

        $this->assertSame(1, $count);
        $this->assertNotEmpty(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, "INSERT INTO wp_mnem_queue (site_id, campaign_id, recipient_email") !== false
                && strpos($query, "'newuser@example.com'") !== false;
        }));
    }
}
