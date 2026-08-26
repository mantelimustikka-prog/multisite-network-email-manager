<?php

defined('ABSPATH') || exit;

use MNEM\SmsSettings;
use PHPUnit\Framework\TestCase;

class SmsSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mnem_site_options'] = array();
    }

    // -------------------------------------------------------------------------
    // validate_no_sms_hours
    // -------------------------------------------------------------------------

    public function test_validate_no_sms_hours_valid_overnight()
    {
        $this->assertTrue(SmsSettings::validate_no_sms_hours('21:00:00-07:00:00'));
    }

    public function test_validate_no_sms_hours_valid_same_day()
    {
        $this->assertTrue(SmsSettings::validate_no_sms_hours('09:00:00-17:00:00'));
    }

    public function test_validate_no_sms_hours_invalid_format()
    {
        $this->assertFalse(SmsSettings::validate_no_sms_hours('9pm-7am'));
        $this->assertFalse(SmsSettings::validate_no_sms_hours('21:00-07:00'));
        $this->assertFalse(SmsSettings::validate_no_sms_hours(''));
        $this->assertFalse(SmsSettings::validate_no_sms_hours('25:00:00-07:00:00'));
        $this->assertFalse(SmsSettings::validate_no_sms_hours('21:60:00-07:00:00'));
        $this->assertFalse(SmsSettings::validate_no_sms_hours('21:00:60-07:00:00'));
    }

    // -------------------------------------------------------------------------
    // validate_settings
    // -------------------------------------------------------------------------

    public function test_validate_settings_returns_empty_for_valid_data()
    {
        $errors = SmsSettings::validate_settings(array(
            'no_sms_hours' => '21:00:00-07:00:00',
            'max_per_day'  => 500,
            'delay'        => 100,
        ));
        $this->assertSame(array(), $errors);
    }

    public function test_validate_settings_catches_invalid_no_sms_hours()
    {
        $errors = SmsSettings::validate_settings(array(
            'no_sms_hours' => 'not-valid',
            'max_per_day'  => 500,
            'delay'        => 100,
        ));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('No SMS Hours', $errors[0]);
    }

    public function test_validate_settings_catches_max_per_day_less_than_one()
    {
        $errors = SmsSettings::validate_settings(array(
            'max_per_day' => 0,
            'delay'       => 100,
        ));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Max SMS per day', $errors[0]);
    }

    public function test_validate_settings_catches_negative_delay()
    {
        $errors = SmsSettings::validate_settings(array(
            'max_per_day' => 100,
            'delay'       => -1,
        ));
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('delay', $errors[0]);
    }

    public function test_validate_settings_catches_invalid_country_code()
    {
        $errors = SmsSettings::validate_settings(array(
            'max_per_day' => 100,
            'delay' => 0,
            'validation_country_code' => 'USA',
        ));

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('country code', strtolower($errors[0]));
    }

    public function test_validate_settings_no_sms_hours_empty_is_valid()
    {
        $errors = SmsSettings::validate_settings(array(
            'no_sms_hours' => '',
            'max_per_day'  => 100,
            'delay'        => 0,
        ));
        $this->assertSame(array(), $errors);
    }

    // -------------------------------------------------------------------------
    // save / getters
    // -------------------------------------------------------------------------

    public function test_save_and_get_provider()
    {
        SmsSettings::save(array(
            'provider'    => 'twilio',
            'max_per_day' => 500,
            'delay'       => 100,
        ));
        $this->assertSame('twilio', SmsSettings::get_provider());
    }

    public function test_save_enabled_flag()
    {
        SmsSettings::save(array('enabled' => true, 'max_per_day' => 100, 'delay' => 0));
        $this->assertTrue(SmsSettings::is_sms_enabled());

        SmsSettings::save(array('enabled' => false, 'max_per_day' => 100, 'delay' => 0));
        $this->assertFalse(SmsSettings::is_sms_enabled());
    }

    public function test_save_max_per_day()
    {
        SmsSettings::save(array('max_per_day' => 750, 'delay' => 0));
        $this->assertSame(750, SmsSettings::get_max_sms_per_day());
    }

    public function test_save_no_sms_hours()
    {
        SmsSettings::save(array('no_sms_hours' => '22:00:00-06:00:00', 'max_per_day' => 100, 'delay' => 0));
        $this->assertSame('22:00:00-06:00:00', SmsSettings::get_no_sms_hours());
    }

    public function test_save_delay()
    {
        SmsSettings::save(array('delay' => 250, 'max_per_day' => 100));
        $this->assertSame(250, SmsSettings::get_sms_delay());
    }

    public function test_save_returns_false_for_invalid_data()
    {
        $result = SmsSettings::save(array(
            'no_sms_hours' => 'invalid',
            'max_per_day'  => 100,
            'delay'        => 0,
        ));
        $this->assertFalse($result);
    }

    public function test_save_returns_false_for_max_per_day_zero()
    {
        $result = SmsSettings::save(array('max_per_day' => 0, 'delay' => 0));
        $this->assertFalse($result);
    }

    public function test_save_ignores_unknown_provider()
    {
        SmsSettings::save(array('provider' => 'nonexistent', 'max_per_day' => 100, 'delay' => 0));
        $this->assertSame('', SmsSettings::get_provider());
    }

    public function test_default_max_per_day()
    {
        $this->assertSame(SmsSettings::DEFAULT_MAX_PER_DAY, SmsSettings::get_max_sms_per_day());
    }

    public function test_default_delay()
    {
        $this->assertSame(SmsSettings::DEFAULT_DELAY, SmsSettings::get_sms_delay());
    }

    public function test_get_all_returns_expected_keys()
    {
        $all = SmsSettings::get_all();
        $this->assertArrayHasKey('provider', $all);
        $this->assertArrayHasKey('enabled', $all);
        $this->assertArrayHasKey('config', $all);
        $this->assertArrayHasKey('max_per_day', $all);
        $this->assertArrayHasKey('no_sms_hours', $all);
        $this->assertArrayHasKey('delay', $all);
        $this->assertArrayHasKey('fallback_provider', $all);
        $this->assertArrayHasKey('tracking_enabled', $all);
        $this->assertArrayHasKey('phone_validation_enabled', $all);
        $this->assertArrayHasKey('validation_country_code', $all);
        $this->assertArrayHasKey('allow_duplicate_numbers', $all);
        $this->assertArrayHasKey('auto_block_failed_attempts', $all);
        $this->assertArrayHasKey('notify_invalid_numbers', $all);
    }

    public function test_save_and_get_phone_validation_settings()
    {
        SmsSettings::save(array(
            'max_per_day' => 100,
            'delay' => 0,
            'phone_validation_enabled' => true,
            'validation_country_code' => 'FI',
            'allow_duplicate_numbers' => true,
            'auto_block_failed_attempts' => 3,
            'notify_invalid_numbers' => true,
        ));

        $this->assertTrue(SmsSettings::is_phone_validation_enabled());
        $this->assertSame('FI', SmsSettings::get_validation_country_code());
        $this->assertTrue(SmsSettings::allow_duplicate_numbers());
        $this->assertSame(3, SmsSettings::get_auto_block_failed_attempts());
        $this->assertTrue(SmsSettings::notify_invalid_numbers());
    }

    // -------------------------------------------------------------------------
    // Provider config (base64 encoding)
    // -------------------------------------------------------------------------

    public function test_save_stores_provider_config_base64_encoded()
    {
        SmsSettings::save(array(
            'provider'    => 'twilio',
            'max_per_day' => 100,
            'delay'       => 0,
            'config'      => array(
                'twilio' => array(
                    'account_sid' => 'ACTEST123',
                    'auth_token'  => 'secret',
                ),
            ),
        ));

        $stored_raw = get_site_option(SmsSettings::OPTION_CONFIG, '');
        $decoded    = json_decode($stored_raw, true);
        $this->assertSame(base64_encode('ACTEST123'), $decoded['twilio']['account_sid']);
        $this->assertSame(base64_encode('secret'), $decoded['twilio']['auth_token']);
    }

    public function test_get_provider_config_decodes_values()
    {
        SmsSettings::save(array(
            'provider'    => 'twilio',
            'max_per_day' => 100,
            'delay'       => 0,
            'config'      => array(
                'twilio' => array(
                    'account_sid' => 'ACTEST123',
                ),
            ),
        ));

        $config = SmsSettings::get_provider_config('twilio');
        $this->assertSame('ACTEST123', $config['account_sid']);
    }

    // -------------------------------------------------------------------------
    // is_in_no_sms_hours — uses a fixed "now" via a wrapper if testable, or
    // we just test that it returns bool and doesn't throw.
    // -------------------------------------------------------------------------

    public function test_is_in_no_sms_hours_returns_false_when_no_hours_set()
    {
        $this->assertFalse(SmsSettings::is_in_no_sms_hours());
    }
}
