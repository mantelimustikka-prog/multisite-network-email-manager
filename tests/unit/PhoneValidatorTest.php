<?php

defined('ABSPATH') || exit;

use MNEM\PhoneValidator;
use PHPUnit\Framework\TestCase;

class PhoneValidatorTest extends TestCase
{
    public function test_validate_phone_number_accepts_e164_numbers()
    {
        $result = PhoneValidator::validate_phone_number('+12345678901');

        $this->assertTrue($result['valid']);
        $this->assertSame('+12345678901', $result['formatted']);
    }

    public function test_validate_phone_number_formats_local_us_numbers()
    {
        $result = PhoneValidator::validate_phone_number('(234) 567-8901', 'US');

        $this->assertTrue($result['valid']);
        $this->assertSame('+12345678901', $result['formatted']);
    }

    public function test_validate_phone_number_rejects_invalid_characters()
    {
        $result = PhoneValidator::validate_phone_number('123-abc-7890');

        $this->assertFalse($result['valid']);
        $this->assertSame('', $result['formatted']);
    }

    public function test_validate_phone_number_accepts_unknown_country_e164_number()
    {
        $result = PhoneValidator::validate_phone_number('+12345678901', 'ZZ');

        $this->assertTrue($result['valid']);
        $this->assertSame('+12345678901', $result['formatted']);
    }

    // -------------------------------------------------------------------------
    // Sweden (SE +46)
    // -------------------------------------------------------------------------

    public function test_validate_swedish_e164_accepted()
    {
        $result = PhoneValidator::validate_phone_number('+46701234567', 'SE');
        $this->assertTrue($result['valid']);
        $this->assertSame('+46701234567', $result['formatted']);
    }

    public function test_validate_swedish_local_with_se_country_code()
    {
        // Local number with leading zero stripped.
        $result = PhoneValidator::validate_phone_number('0701234567', 'SE');
        $this->assertTrue($result['valid']);
        $this->assertStringStartsWith('+46', $result['formatted']);
    }

    public function test_validate_swedish_local_without_country_hint_rejects()
    {
        // Without a country hint (defaulting to US), a 10-digit number starting
        // with 0 will fail the leading-digit check.
        $result = PhoneValidator::validate_phone_number('0701234567', 'US');
        $this->assertFalse($result['valid']);
    }

    // -------------------------------------------------------------------------
    // detect_country_from_e164
    // -------------------------------------------------------------------------

    public function test_detect_country_from_e164_swedish()
    {
        $iso2 = PhoneValidator::detect_country_from_e164('+46701234567');
        $this->assertSame('SE', $iso2);
    }

    public function test_detect_country_from_e164_finnish()
    {
        $iso2 = PhoneValidator::detect_country_from_e164('+358401234567');
        $this->assertSame('FI', $iso2);
    }

    public function test_detect_country_from_e164_us()
    {
        $iso2 = PhoneValidator::detect_country_from_e164('+12125551234');
        $this->assertSame('US', $iso2);
    }

    public function test_detect_country_from_e164_returns_null_for_unknown()
    {
        $iso2 = PhoneValidator::detect_country_from_e164('+999123456');
        $this->assertNull($iso2);
    }

    public function test_detect_country_returns_null_without_plus()
    {
        $iso2 = PhoneValidator::detect_country_from_e164('46701234567');
        $this->assertNull($iso2);
    }

    // -------------------------------------------------------------------------
    // detect_possible_countries_from_e164 – shared calling code (+1)
    // -------------------------------------------------------------------------

    public function test_detect_possible_countries_for_shared_code()
    {
        $possible = PhoneValidator::detect_possible_countries_from_e164('+12125551234');
        $this->assertContains('US', $possible);
        $this->assertContains('CA', $possible);
    }

    // -------------------------------------------------------------------------
    // validate_with_country_hint – rich result
    // -------------------------------------------------------------------------

    public function test_rich_result_contains_expected_keys()
    {
        $result = PhoneValidator::validate_with_country_hint('+12125551234', null, 'US');
        foreach (array('valid', 'formatted', 'error', 'country_iso2', 'country_calling_code', 'national_number', 'input_format', 'ambiguous', 'possible_countries', 'reason_code') as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    public function test_rich_result_for_e164_swedish_number()
    {
        $result = PhoneValidator::validate_with_country_hint('+46701234567');
        $this->assertTrue($result['valid']);
        $this->assertSame('SE', $result['country_iso2']);
        $this->assertSame('46', $result['country_calling_code']);
        $this->assertSame('e164', $result['input_format']);
        $this->assertFalse($result['ambiguous']);
        $this->assertNull($result['reason_code']);
    }

    public function test_rich_result_explicit_country_hint()
    {
        $result = PhoneValidator::validate_with_country_hint('0701234567', 'SE');
        $this->assertTrue($result['valid']);
        $this->assertSame('SE', $result['country_iso2']);
        $this->assertSame('national', $result['input_format']);
    }

    public function test_rich_result_invalid_returns_reason_code()
    {
        $result = PhoneValidator::validate_with_country_hint('');
        $this->assertFalse($result['valid']);
        $this->assertSame('empty_input', $result['reason_code']);
    }

    public function test_get_supported_countries_includes_se_and_fi()
    {
        $countries = PhoneValidator::get_supported_countries();
        $this->assertContains('SE', $countries);
        $this->assertContains('FI', $countries);
        $this->assertContains('US', $countries);
    }
}
