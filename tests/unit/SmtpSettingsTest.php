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

    public function test_get_provider_api_key_decoded_returns_decoded_key()
    {
        $raw_key = 'xkeysib-abc123def456-test';
        // save() base64-encodes the api_key before storing, so get_provider_api_key_decoded()
        // must decode it back to the original value.
        SmtpSettings::save(array(
            'provider_type' => 'brevo',
            'provider_config' => array(
                'brevo' => array('api_key' => $raw_key),
            ),
        ));

        $this->assertSame($raw_key, SmtpSettings::get_provider_api_key_decoded('brevo'));
    }

    public function test_get_provider_api_key_decoded_returns_empty_for_missing_provider()
    {
        SmtpSettings::save(array('provider_config' => array()));

        $this->assertSame('', SmtpSettings::get_provider_api_key_decoded('brevo'));
    }

    public function test_validate_api_key_accepts_valid_key()
    {
        $this->assertTrue(SmtpSettings::validate_api_key('xkeysib-abc123def456'));
    }

    public function test_validate_api_key_rejects_short_key()
    {
        $this->assertFalse(SmtpSettings::validate_api_key('short'));
    }

    public function test_validate_api_key_rejects_empty_string()
    {
        $this->assertFalse(SmtpSettings::validate_api_key(''));
    }

    public function test_force_sender_defaults_to_disabled()
    {
        $this->assertFalse(SmtpSettings::is_force_sender_enabled());
    }

    public function test_set_force_sender_updates_option()
    {
        SmtpSettings::set_force_sender(true);
        $this->assertTrue(SmtpSettings::is_force_sender_enabled());

        SmtpSettings::set_force_sender(false);
        $this->assertFalse(SmtpSettings::is_force_sender_enabled());
    }

    public function test_status_update_interval_defaults_to_30_minutes()
    {
        $this->assertSame(30, SmtpSettings::get_status_update_interval());
    }

    public function test_status_update_interval_accepts_only_valid_values()
    {
        SmtpSettings::set_status_update_interval(20);
        $this->assertSame(20, SmtpSettings::get_status_update_interval());

        SmtpSettings::set_status_update_interval(7);
        $this->assertSame(20, SmtpSettings::get_status_update_interval());
    }
}
