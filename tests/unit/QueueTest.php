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

                if (strpos($query, "status IN ('sent', 'delivered', 'opened', 'clicked')") !== false) {
                    return 7;
                }

                if (strpos($query, "status IN ('bounce', 'soft_bounce', 'invalid_email', 'deferred', 'complaint', 'unsubscribed', 'suppressed', 'failed', 'rejected')") !== false) {
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

    public function test_process_batch_does_not_switch_blog_context()
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
        // Network plugin: no blog switching required since all tables are centralized.
        $this->assertSame(array(), $GLOBALS['mnem_switched_blogs']);
        $this->assertSame(0, $GLOBALS['mnem_restore_blog_calls']);
        $this->assertSame('user@example.com', $GLOBALS['mnem_last_wp_mail']['to']);
    }

    public function test_process_batch_overrides_from_header_when_force_sender_enabled()
    {
        $GLOBALS['mnem_site_options']['mnem_smtp_settings'] = array(
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_email' => 'smtp-from@example.test',
            'from_name' => 'SMTP From',
            'provider_type' => 'smtp',
            'provider_config' => array(),
            'fallback_provider' => '',
            'fallback_enabled' => false,
        );
        $GLOBALS['mnem_site_options']['mnem_force_sender_settings'] = 1;
        $GLOBALS['mnem_site_options']['mnem_sender_email'] = 'forced@example.com';
        $GLOBALS['mnem_site_options']['mnem_sender_name'] = 'Forced Sender';

        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_col($query)
            {
                $this->queries[] = $query;
                return array(11);
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 11,
                    'site_id' => 1,
                    'blog_id' => 1,
                    'campaign_id' => 0,
                    'recipient_email' => 'user@example.com',
                    'subject' => 'Subject',
                    'body' => 'Body',
                    'from_email' => 'row@example.com',
                    'from_name' => 'Row Name',
                    'headers' => '["From: Header Name <header@example.com>","X-Test: test"]',
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
        };

        $processed = Queue::process_batch(1);
        $headers = implode("\n", $GLOBALS['mnem_last_wp_mail']['headers']);

        $this->assertSame(1, $processed);
        $this->assertStringContainsString('From: Forced Sender <forced@example.com>', $headers);
        $this->assertStringNotContainsString('header@example.com', $headers);
        $this->assertStringNotContainsString('row@example.com', $headers);
    }

    public function test_process_batch_stops_when_minute_rate_limit_is_exceeded()
    {
        $GLOBALS['mnem_transients'] = array();
        $identifier = 'campaign_send_' . gmdate('Y-m-d-H-i');
        set_transient('mnem_rate_limit_' . $identifier, 1, 60);
        $GLOBALS['mnem_site_options']['mnem_campaign_rate_limit_per_minute'] = 1;

        $was_get_col_called = false;

        $GLOBALS['wpdb'] = new class($was_get_col_called) extends wpdb {
            private $was_get_col_called;

            public function __construct(&$was_get_col_called)
            {
                $this->was_get_col_called = &$was_get_col_called;
            }

            public function get_col($query)
            {
                $this->was_get_col_called = true;
                $this->queries[] = $query;
                return array(11);
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $processed = Queue::process_batch(1);

        $this->assertSame(0, $processed);
        $this->assertTrue($was_get_col_called);
    }

    public function test_process_batch_prioritizes_transactional_emails_when_campaign_rate_limit_is_exceeded()
    {
        $GLOBALS['mnem_transients'] = array();
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
        $GLOBALS['mnem_site_options']['mnem_campaign_rate_limit_per_minute'] = 1;
        $GLOBALS['mnem_site_options']['mnem_campaign_rate_limit_per_hour'] = 0;
        $GLOBALS['mnem_site_options']['mnem_campaign_rate_limit_per_day'] = 0;
        $GLOBALS['mnem_site_options']['mnem_campaign_delay_between_sends'] = 0;
        $identifier = 'campaign_send_' . gmdate('Y-m-d-H-i');
        set_transient('mnem_rate_limit_' . $identifier, 1, 60);

        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_col($query)
            {
                $this->queries[] = $query;
                if (strpos($query, "source IN ('core', 'plugin', 'user_event')") !== false) {
                    return array(21);
                }
                if (strpos($query, "source IN ('campaign')") !== false) {
                    return array(22);
                }

                return array();
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'WHERE id = 21') !== false) {
                    return array(
                        'id' => 21,
                        'site_id' => 1,
                        'blog_id' => 1,
                        'campaign_id' => 0,
                        'recipient_email' => 'transactional@example.com',
                        'subject' => 'Transactional Subject',
                        'body' => 'Transactional Body',
                        'from_email' => 'from@example.com',
                        'from_name' => 'From Name',
                        'headers' => '[]',
                        'attachments' => '[]',
                        'metadata' => '{}',
                        'attempts' => 0,
                    );
                }
                if (strpos($query, 'WHERE id = 22') !== false) {
                    return array(
                        'id' => 22,
                        'site_id' => 1,
                        'blog_id' => 1,
                        'campaign_id' => 90,
                        'recipient_email' => 'campaign@example.com',
                        'subject' => 'Campaign Subject',
                        'body' => 'Campaign Body',
                        'from_email' => 'from@example.com',
                        'from_name' => 'From Name',
                        'headers' => '[]',
                        'attachments' => '[]',
                        'metadata' => '{}',
                        'attempts' => 0,
                    );
                }

                return null;
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

        $processed = Queue::process_batch(5);

        $this->assertSame(1, $processed);
        $this->assertSame('transactional@example.com', $GLOBALS['mnem_last_wp_mail']['to']);
        $this->assertStringContainsString("source IN ('core', 'plugin', 'user_event')", implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_send_now_processes_failed_queue_item_immediately()
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

        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 12,
                    'site_id' => 1,
                    'blog_id' => 1,
                    'campaign_id' => 0,
                    'recipient_email' => 'user@example.com',
                    'subject' => 'Subject',
                    'body' => 'Body',
                    'from_email' => 'from@example.com',
                    'from_name' => 'From Name',
                    'headers' => '[]',
                    'attachments' => '[]',
                    'metadata' => '{}',
                    'attempts' => 2,
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = Queue::send_now(12);

        $this->assertTrue($result['processed']);
        $this->assertTrue($result['success']);
        $this->assertSame('sent', $result['status']);
        $this->assertSame('user@example.com', $GLOBALS['mnem_last_wp_mail']['to']);
        $this->assertStringContainsString("status <> 'processing'", implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_get_display_status_uses_engagement_and_delivery_priority()
    {
        $this->assertSame('Opened', Queue::get_display_status(array('status' => 'opened')));
        $this->assertSame('Delivered', Queue::get_display_status(array('status' => 'delivered')));
        $this->assertSame('Soft Bounce', Queue::get_display_status(array('status' => 'soft_bounce')));
        $this->assertSame('Sent', Queue::get_display_status(array('sent_at' => '2026-08-01 00:00:00')));
        $this->assertSame('Pending', Queue::get_display_status(array('status' => 'pending')));
        $this->assertSame('Processing', Queue::get_display_status(array('status' => 'processing')));
    }

    public function test_update_status_from_webhook_updates_queue_timestamps_and_status()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, "provider_message_id = 'message-123'") === false) {
                    return null;
                }
                return array(
                    'id' => 44,
                    'status' => 'delivered',
                    'opened' => '',
                    'clicked' => '',
                    'opens_count' => 0,
                    'clicks_count' => 0,
                    'provider_metadata' => '{}',
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $updated = Queue::update_status_from_webhook('sendgrid', 'message-123', 'clicked', array('event' => 'click'), 'user@example.com', '2026-08-11 12:00:00');

        $this->assertTrue($updated);
        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("provider_message_id = 'message-123'", $queries);
        $this->assertStringContainsString("status = 'clicked'", $queries);
        $this->assertStringContainsString("opened = '2026-08-11 12:00:00'", $queries);
        $this->assertStringContainsString("clicked = '2026-08-11 12:00:00'", $queries);
        $this->assertStringContainsString('opens_count = COALESCE(opens_count, 0) + 0', $queries);
        $this->assertStringContainsString('clicks_count = COALESCE(clicks_count, 0) + 1', $queries);
        $this->assertStringNotContainsString("recipient_email = 'user@example.com'", $queries);
    }

    public function test_record_local_event_increments_open_count()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'status' => 'delivered',
                    'opened' => '',
                    'clicked' => '',
                    'opens_count' => 2,
                    'clicks_count' => 1,
                    'provider_metadata' => '{}',
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        Queue::record_local_event(10, 'opened');

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("status = 'opened'", $queries);
        $this->assertStringContainsString('opens_count = COALESCE(opens_count, 0) + 1', $queries);
        $this->assertStringContainsString('clicks_count = COALESCE(clicks_count, 0) + 0', $queries);
    }

    public function test_map_webhook_status_maps_provider_events()
    {
        $this->assertSame('bounce', Queue::map_webhook_status('sendgrid', 'bounce'));
        $this->assertSame('soft_bounce', Queue::map_webhook_status('mailgun', 'failed', array('severity' => 'temporary')));
        $this->assertSame('complaint', Queue::map_webhook_status('postmark', 'SpamComplaint'));
    }
}
