<?php

defined('ABSPATH') || exit;

use MNEM\Queue;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    public function test_calculate_next_attempt_returns_expected_values()
    {
        $base = time();

        $attempt0 = strtotime(Queue::calculate_next_attempt(0));
        $attempt1 = strtotime(Queue::calculate_next_attempt(1));
        $attempt2 = strtotime(Queue::calculate_next_attempt(2));

        $this->assertGreaterThanOrEqual($base + 300 - 2, $attempt0);
        $this->assertLessThanOrEqual($base + 300 + 2, $attempt0);
        $this->assertGreaterThanOrEqual($base + 600 - 2, $attempt1);
        $this->assertLessThanOrEqual($base + 600 + 2, $attempt1);
        $this->assertGreaterThanOrEqual($base + 1200 - 2, $attempt2);
        $this->assertLessThanOrEqual($base + 1200 + 2, $attempt2);
    }

    public function test_enqueue_skips_if_suppressed()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $queries = array();

            public function get_var($query)
            {
                $this->queries[] = $query;

                if (strpos($query, 'SHOW TABLES LIKE') !== false) {
                    return 'wp_mnem_logs';
                }

                return 1;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = Queue::enqueue(1, 'user@example.com', 'Subject', 'Body');

        $this->assertFalse($result);
        $this->assertStringContainsString('SELECT COUNT(1) FROM wp_mnem_suppression', $GLOBALS['wpdb']->queries[0]);
        $this->assertSame(0, count(array_filter($GLOBALS['wpdb']->queries, static function ($query) {
            return strpos($query, 'INSERT INTO wp_mnem_queue') !== false;
        })));
    }

    public function test_get_stats_returns_retry_information()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;

                if (strpos($query, "status = 'pending'") !== false) {
                    return 4;
                }

                if (strpos($query, "status = 'processing'") !== false) {
                    return 1;
                }

                if (strpos($query, "status = 'sent'") !== false) {
                    return 7;
                }

                if (strpos($query, "status = 'failed'") !== false) {
                    return 2;
                }

                return 0;
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;

                return array(
                    'scheduled_at' => '2026-08-13 23:00:00',
                    'attempts' => 2,
                );
            }
        };

        $stats = Queue::get_stats(1);

        $this->assertSame(4, $stats['pending']);
        $this->assertSame(1, $stats['processing']);
        $this->assertSame(7, $stats['sent']);
        $this->assertSame(2, $stats['failed']);
        $this->assertSame('2026-08-13 23:00:00', $stats['next_retry_at']);
        $this->assertSame(2, $stats['next_retry_attempts']);
    }

    public function test_process_batch_switches_blog_context_and_restores()
    {
        $GLOBALS['mnem_site_options']['mnem_smtp_settings'] = array(
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_email' => 'sender@example.test',
            'from_name' => 'Sender',
            'provider_type' => 'smtp',
            'provider_config' => array(),
            'fallback_provider' => '',
            'fallback_enabled' => false,
        );
        $GLOBALS['mnem_switched_blogs'] = array();
        $GLOBALS['mnem_restore_blog_calls'] = 0;

        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_col($query)
            {
                $this->queries[] = $query;
                return array(10);
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 10,
                    'site_id' => 2,
                    'blog_id' => 2,
                    'campaign_id' => 0,
                    'recipient_email' => 'user@example.com',
                    'subject' => 'Subject',
                    'body' => 'Body',
                    'from_email' => 'from@example.com',
                    'from_name' => 'From Name',
                    'headers' => '[]',
                    'attachments' => '[]',
                    'metadata' => '{}',
                    'attempts' => 0,
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
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

        $processed = Queue::process_batch(1);

        $this->assertSame(1, $processed);
        $this->assertSame(array(2), $GLOBALS['mnem_switched_blogs']);
        $this->assertSame(1, $GLOBALS['mnem_restore_blog_calls']);
        $this->assertSame('user@example.com', $GLOBALS['mnem_last_wp_mail']['to']);
    }
}
