<?php

defined('ABSPATH') || exit;

use MNEM\SmtpSettings;
use PHPUnit\Framework\TestCase;

class SmtpSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mnem_site_options'] = array();
    }

    public function test_default_settings_have_expected_keys()
    {
        $defaults = SmtpSettings::DEFAULT_SETTINGS;

        $this->assertArrayHasKey('host', $defaults);
        $this->assertArrayHasKey('port', $defaults);
        $this->assertArrayHasKey('encryption', $defaults);
        $this->assertArrayHasKey('username', $defaults);
        $this->assertArrayHasKey('password', $defaults);
        $this->assertArrayHasKey('from_email', $defaults);
        $this->assertArrayHasKey('from_name', $defaults);
    }

    public function test_save_encodes_password()
    {
        SmtpSettings::save(array('password' => 'plain-text-secret'));
        $stored = get_site_option(SmtpSettings::OPTION_KEY, array());

        $this->assertSame(base64_encode('plain-text-secret'), $stored['password']);
    }

    public function test_get_password_decoded_returns_original_password()
    {
        SmtpSettings::save(array('password' => 'plain-text-secret'));

        $this->assertSame('plain-text-secret', SmtpSettings::get_password_decoded());
    }

    public function test_is_active_provider_configured_for_smtp()
    {
        SmtpSettings::save(array(
            'provider_type' => 'smtp',
            'host' => 'smtp.example.com',
        ));

        $this->assertTrue(SmtpSettings::is_active_provider_configured());
    }

    public function test_is_active_provider_configured_for_api_provider()
    {
        SmtpSettings::save(array(
            'provider_type' => 'brevo',
            'provider_config' => array(
                'brevo' => array(
                    'api_key' => 'abc123',
                ),
            ),
        ));

        $this->assertTrue(SmtpSettings::is_active_provider_configured());
    }

    public function test_is_active_provider_not_configured_when_active_provider_credentials_missing()
    {
        SmtpSettings::save(array(
            'provider_type' => 'sendgrid',
            'host' => 'smtp.example.com',
            'provider_config' => array(),
        ));

        $this->assertFalse(SmtpSettings::is_active_provider_configured());
    }
}
