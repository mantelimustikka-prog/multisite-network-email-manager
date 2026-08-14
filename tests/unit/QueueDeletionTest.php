<?php

defined('ABSPATH') || exit;

use MNEM\Queue;
use PHPUnit\Framework\TestCase;

class QueueDeletionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_current_user_id'] = 77;
    }

    public function test_delete_item_deletes_pending_item()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $item = array(
                'id' => 12,
                'site_id' => 1,
                'campaign_id' => 0,
                'recipient_email' => 'pending@example.com',
                'status' => 'pending',
            );

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return strpos($query, 'FROM wp_mnem_queue WHERE id = 12') !== false ? $this->item : null;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return strpos($query, 'SHOW TABLES LIKE') !== false ? 'wp_mnem_logs' : 0;
            }
        };

        $this->assertTrue(Queue::delete_item(12));
        $this->assertNotEmpty(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, "DELETE FROM wp_mnem_queue WHERE id = 12 AND status IN ('pending', 'failed')") !== false;
        }));
    }

    public function test_delete_item_rejects_processing_item()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $item = array(
                'id' => 19,
                'site_id' => 1,
                'campaign_id' => 0,
                'recipient_email' => 'processing@example.com',
                'status' => 'processing',
            );

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return $this->item;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return strpos($query, 'SHOW TABLES LIKE') !== false ? 'wp_mnem_logs' : 0;
            }
        };

        $this->assertFalse(Queue::delete_item(19));
        $this->assertSame(0, count(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, 'DELETE FROM wp_mnem_queue') !== false;
        })));
    }

    public function test_delete_items_counts_only_deleted_items()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'WHERE id = 5') !== false) {
                    return array('id' => 5, 'site_id' => 1, 'campaign_id' => 0, 'recipient_email' => 'one@example.com', 'status' => 'pending');
                }
                if (strpos($query, 'WHERE id = 6') !== false) {
                    return array('id' => 6, 'site_id' => 1, 'campaign_id' => 0, 'recipient_email' => 'two@example.com', 'status' => 'sent');
                }
                if (strpos($query, 'WHERE id = 7') !== false) {
                    return array('id' => 7, 'site_id' => 1, 'campaign_id' => 0, 'recipient_email' => 'three@example.com', 'status' => 'failed');
                }
                return null;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return strpos($query, 'id = 5') !== false || strpos($query, 'id = 7') !== false ? 1 : 0;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return strpos($query, 'SHOW TABLES LIKE') !== false ? 'wp_mnem_logs' : 0;
            }
        };

        $this->assertSame(2, Queue::delete_items(array(5, 6, 7)));
    }

    public function test_delete_by_status_deletes_pending_items_for_site()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_col($query)
            {
                $this->queries[] = $query;
                return array(4, 8);
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_campaigns') !== false) {
                    return array('id' => 4, 'status' => 'sending', 'enqueue_failed_count' => 0);
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

            public function query($query)
            {
                $this->queries[] = $query;
                return 3;
            }
        };

        $this->assertSame(3, Queue::delete_by_status(1, 'pending'));
        $this->assertNotEmpty(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, "DELETE FROM wp_mnem_queue WHERE site_id = 1 AND status = 'pending'") !== false;
        }));
    }

    public function test_delete_by_status_rejects_processing_status()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return strpos($query, 'SHOW TABLES LIKE') !== false ? 'wp_mnem_logs' : 0;
            }
        };

        $this->assertSame(0, Queue::delete_by_status(1, 'processing'));
        $this->assertSame(0, count(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, 'DELETE FROM wp_mnem_queue') !== false;
        })));
    }

    public function test_delete_by_status_with_zero_site_id_deletes_all_sites()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return 5;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return strpos($query, 'SHOW TABLES LIKE') !== false ? 'wp_mnem_logs' : 0;
            }
        };

        $this->assertSame(5, Queue::delete_by_status(0, 'failed'));
        $this->assertNotEmpty(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, "DELETE FROM wp_mnem_queue WHERE status = 'failed'") !== false;
        }));
    }

    public function test_delete_by_campaign_deletes_pending_and_failed_items()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_campaigns') !== false) {
                    return array('id' => 22, 'status' => 'sending', 'enqueue_failed_count' => 0);
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

            public function query($query)
            {
                $this->queries[] = $query;
                return 2;
            }
        };

        $this->assertSame(2, Queue::delete_by_campaign(22));
        $this->assertNotEmpty(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, "DELETE FROM wp_mnem_queue WHERE campaign_id = 22 AND status IN ('pending', 'failed')") !== false;
        }));
    }
}
