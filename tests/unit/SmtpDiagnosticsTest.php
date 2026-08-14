<?php

defined('ABSPATH') || exit;

use MNEM\SmtpDiagnostics;
use PHPUnit\Framework\TestCase;

class SmtpDiagnosticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options']['mnem_smtp_settings'] = array(
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'mailer',
            'password' => '',
            'from_email' => 'noreply@example.com',
            'from_name' => 'Mailer',
            'provider_type' => 'smtp',
            'provider_config' => array(),
            'fallback_provider' => '',
            'fallback_enabled' => false,
        );
        $GLOBALS['mnem_site_options'][SmtpDiagnostics::OPTION_RATE_LIMIT] = array();
        $GLOBALS['mnem_site_options']['mnem_keep_email_previews'] = 1;
        $GLOBALS['mnem_site_options']['mnem_email_preview_retention_days'] = 30;
        $GLOBALS['mnem_site_options']['mnem_sender_email'] = 'noreply@example.com';
        $GLOBALS['mnem_site_options']['mnem_sender_name']  = 'Mailer';
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
                    $this->insert_id = 77;
                }
                return 1;
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 77,
                    'site_id' => 1,
                    'blog_id' => 1,
                    'campaign_id' => 0,
                    'recipient_email' => 'admin@example.com',
                    'subject' => 'MNEM SMTP Test Email',
                    'body' => \MNEM\EmailFormatter::apply_global_header_footer('<p>This is a test email from Multisite Network Email Manager.</p>'),
                    'from_email' => 'noreply@example.com',
                    'from_name' => 'Mailer',
                    'headers' => '["Content-Type: text/html; charset=UTF-8"]',
                    'attachments' => '[]',
                    'metadata' => '{}',
                    'attempts' => 0,
                );
            }
        };
        unset($GLOBALS['mnem_wp_mail_return']);
    }

    public function test_validate_settings_returns_success_for_valid_data()
    {
        $result = SmtpDiagnostics::validate_settings();

        $this->assertTrue($result['success']);
        $this->assertSame('SMTP settings look valid.', $result['message']);
    }

    public function test_send_test_email_enforces_rate_limit()
    {
        for ($i = 0; $i < 5; $i++) {
            $result = SmtpDiagnostics::send_test_email('admin@example.com');
            $this->assertTrue($result['success']);
        }

        $blocked = SmtpDiagnostics::send_test_email('admin@example.com');
        $this->assertFalse($blocked['success']);
        $this->assertStringContainsString('Rate limit exceeded', $blocked['message']);
    }

    public function test_test_connection_returns_validation_failure_when_host_missing()
    {
        $settings = $GLOBALS['mnem_site_options']['mnem_smtp_settings'];
        $settings['host'] = '';
        $GLOBALS['mnem_site_options']['mnem_smtp_settings'] = $settings;

        $result = SmtpDiagnostics::test_connection();

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('validation failed', strtolower($result['message']));
    }

    public function test_send_test_email_returns_failure_when_mail_fails()
    {
        $GLOBALS['mnem_wp_mail_return'] = false;

        $result = SmtpDiagnostics::send_test_email('admin@example.com');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('sending failed', strtolower($result['message']));
    }

    public function test_send_test_email_fails_when_sender_email_not_configured()
    {
        $settings = $GLOBALS['mnem_site_options']['mnem_smtp_settings'];
        $settings['from_email'] = '';
        $GLOBALS['mnem_site_options']['mnem_smtp_settings'] = $settings;
        $GLOBALS['mnem_site_options']['mnem_sender_email'] = '';
        $GLOBALS['mnem_site_options']['admin_email'] = '';

        $result = SmtpDiagnostics::send_test_email('admin@example.com');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Sender email address is not configured', $result['message']);
        $this->assertStringContainsString('Sender Settings', $result['message']);
    }

    public function test_send_test_email_fails_when_provider_not_configured()
    {
        $settings = $GLOBALS['mnem_site_options']['mnem_smtp_settings'];
        $settings['provider_type'] = 'brevo';
        $settings['provider_config'] = array('brevo' => array('api_key' => ''));
        $GLOBALS['mnem_site_options']['mnem_smtp_settings'] = $settings;

        $result = SmtpDiagnostics::send_test_email('admin@example.com');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not properly configured', $result['message']);
        $this->assertStringContainsString('brevo', $result['message']);
    }

    public function test_send_test_email_error_message_includes_hint_for_auth_failure()
    {
        // Use brevo provider with a valid-format API key so it passes the "configured" check,
        // then wire up the HTTP mock to return a 401 response so the provider fails.
        $settings = $GLOBALS['mnem_site_options']['mnem_smtp_settings'];
        $settings['provider_type'] = 'brevo';
        $api_key = base64_encode('xkeysib-test-key-12345678901234567890');
        $settings['provider_config'] = array('brevo' => array('api_key' => $api_key));
        $GLOBALS['mnem_site_options']['mnem_smtp_settings'] = $settings;
        $GLOBALS['mnem_http_response'] = array(
            'response' => array('code' => 401),
            'body'     => '{"message":"Key not found","code":"unauthorized"}',
            'headers'  => array(),
        );

        $result = SmtpDiagnostics::send_test_email('admin@example.com');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('API key', $result['message']);

        unset($GLOBALS['mnem_http_response']);
    }


    public function test_send_test_email_uses_provider_tracking_and_global_header_footer()
    {
        $GLOBALS['mnem_site_options']['mnem_force_global_header_footer'] = 1;
        $GLOBALS['mnem_site_options']['mnem_global_header'] = '<p>Header</p>';
        $GLOBALS['mnem_site_options']['mnem_global_footer'] = '<p>Footer</p>';

        $result = SmtpDiagnostics::send_test_email('admin@example.com');

        $queries = implode("\n", $GLOBALS['wpdb']->queries);

        $this->assertTrue($result['success']);
        $this->assertSame('admin@example.com', $GLOBALS['mnem_last_wp_mail']['to']);
        $this->assertStringContainsString('<p>Header</p>', $GLOBALS['mnem_last_wp_mail']['message']);
        $this->assertStringContainsString('<p>Footer</p>', $GLOBALS['mnem_last_wp_mail']['message']);
        $this->assertStringNotContainsString('INSERT INTO wp_mnem_queue', $queries);
        $this->assertStringContainsString('INSERT INTO wp_mnem_email_tracking', $queries);
        $this->assertSame('smtp', $result['details']['provider']);
        $this->assertArrayHasKey('message_id', $result['details']);
    }
}
