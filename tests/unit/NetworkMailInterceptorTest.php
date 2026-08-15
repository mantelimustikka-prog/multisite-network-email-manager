<?php

defined('ABSPATH') || exit;

use MNEM\MailInterceptor;
use PHPUnit\Framework\TestCase;

class NetworkMailInterceptorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_hooks'] = array();
        $GLOBALS['mnem_site_options'] = array(
            'mnem_sender_name' => 'Network Sender',
            'mnem_sender_email' => 'network@example.com',
            'mnem_force_global_header_footer' => 1,
            'mnem_global_header' => '<p>Header</p>',
            'mnem_global_footer' => '<p>Footer</p>',
        );
    }

    public function test_init_registers_pre_wp_mail_filter()
    {
        MailInterceptor::init();

        $this->assertArrayHasKey('pre_wp_mail', $GLOBALS['mnem_hooks']);
        $hook = $GLOBALS['mnem_hooks']['pre_wp_mail'][0];
        $this->assertSame(-999, $hook['args'][0]);
        $this->assertSame(2, $hook['args'][1]);
    }

    public function test_intercept_mail_queues_email_and_returns_true()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
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
                if (strpos($query, 'INSERT INTO wp_mnem_queue') !== false) {
                    $this->insert_id = 123;
                }
                return 1;
            }
        };

        $result = MailInterceptor::intercept_mail(
            null,
            array(
                'to' => 'recipient@example.com',
                'subject' => 'Hello',
                'message' => 'Body',
                'headers' => array('From: Header Name <header@example.com>'),
                'attachments' => array('/tmp/file.pdf'),
            )
        );

        $queries = implode("\n", $GLOBALS['wpdb']->queries);

        $this->assertTrue($result);
        $this->assertStringContainsString('INSERT INTO wp_mnem_queue', $queries);
        $this->assertStringContainsString('blog_id', $queries);
        $this->assertStringContainsString('header@example.com', $queries);
        $this->assertStringContainsString('Header', $queries);
        $this->assertStringContainsString('Footer', $queries);
    }

    public function test_intercept_mail_uses_forced_sender_when_enabled()
    {
        $GLOBALS['mnem_site_options']['mnem_force_sender_settings'] = 1;
        $GLOBALS['mnem_site_options']['mnem_sender_name'] = 'Forced Sender';
        $GLOBALS['mnem_site_options']['mnem_sender_email'] = 'forced@example.com';

        $GLOBALS['wpdb'] = new class extends wpdb {
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
                if (strpos($query, 'INSERT INTO wp_mnem_queue') !== false) {
                    $this->insert_id = 124;
                }
                return 1;
            }
        };

        $result = MailInterceptor::intercept_mail(
            null,
            array(
                'to' => 'recipient@example.com',
                'subject' => 'Hello',
                'message' => 'Body',
                'headers' => array('From: Header Name <header@example.com>'),
            )
        );

        $queries = implode("\n", $GLOBALS['wpdb']->queries);

        $this->assertTrue($result);
        $this->assertStringContainsString('forced@example.com', $queries);
        $this->assertStringContainsString('Forced Sender', $queries);
    }
}
