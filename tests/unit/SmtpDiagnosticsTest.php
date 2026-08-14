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
        );
        $GLOBALS['mnem_site_options'][SmtpDiagnostics::OPTION_RATE_LIMIT] = array();
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
}
