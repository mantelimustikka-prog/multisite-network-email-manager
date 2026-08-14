<?php

defined('ABSPATH') || exit;

use MNEM\Campaigns;
use PHPUnit\Framework\TestCase;

class CampaignCancellationTest extends TestCase
{
    public function test_cancel_campaign_removes_pending_queue_items_and_updates_status()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $campaign = array(
                'id' => 11,
                'status' => 'scheduled',
                'site_id' => 1,
                'sent_at' => '',
            );

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_campaigns') !== false) {
                    return $this->campaign;
                }
                return null;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 2;
            }
        };

        $result = Campaigns::cancel_campaign(11);

        $this->assertTrue($result);
        $this->assertNotEmpty(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, 'DELETE FROM wp_mnem_queue') !== false;
        }));
        $this->assertNotEmpty(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, "SET status = 'cancelled'") !== false;
        }));
    }

    public function test_get_target_recipients_returns_distinct_user_ids()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array('id' => 3, 'target_lists' => '[1,2]');
            }

            public function get_col($query)
            {
                $this->queries[] = $query;
                return array(4, 7, 4);
            }
        };

        $recipients = Campaigns::get_target_recipients(3);

        $this->assertSame(array(4, 7), $recipients);
    }
}
