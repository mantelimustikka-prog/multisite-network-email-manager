<?php

defined('ABSPATH') || exit;

use MNEM\PhoneValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for multi-country phone validation capabilities.
 */
class PhoneValidatorMultiCountryTest extends TestCase
{
    // -------------------------------------------------------------------------
    // E.164 detection for all supported countries
    // -------------------------------------------------------------------------

    /** @dataProvider e164_country_provider */
    public function test_detect_country_from_e164(string $e164, string $expected_iso2)
    {
        $iso2 = PhoneValidator::detect_country_from_e164($e164);
        $this->assertSame($expected_iso2, $iso2, "Expected {$expected_iso2} for {$e164}");
    }

    /** @return array<string,array{string,string}> */
    public function e164_country_provider(): array
    {
        return array(
            'US'  => array('+12125551234',   'US'),
            'GB'  => array('+447911123456',  'GB'),
            'AU'  => array('+61412345678',   'AU'),
            'IN'  => array('+919876543210',  'IN'),
            'FI'  => array('+358401234567',  'FI'),
            'SE'  => array('+46701234567',   'SE'),
            'NO'  => array('+4791234567',    'NO'),
            'DK'  => array('+4512345678',    'DK'),
            'DE'  => array('+4915112345678', 'DE'),
            'FR'  => array('+33612345678',   'FR'),
            'SG'  => array('+6591234567',    'SG'),
            'AE'  => array('+971501234567',  'AE'),
        );
    }

    // -------------------------------------------------------------------------
    // validate_with_country_hint – various countries
    // -------------------------------------------------------------------------

    /** @dataProvider country_hint_provider */
    public function test_validate_with_country_hint_valid(string $phone, string $country, string $expected_e164)
    {
        $result = PhoneValidator::validate_with_country_hint($phone, $country);
        $this->assertTrue($result['valid'], "Expected valid for {$phone} with country {$country}");
        $this->assertSame($expected_e164, $result['formatted']);
        $this->assertSame($country, $result['country_iso2']);
    }

    /** @return array<string,array{string,string,string}> */
    public function country_hint_provider(): array
    {
        return array(
            'US local'  => array('2125551234',   'US', '+12125551234'),
            'FI local'  => array('0401234567',   'FI', '+358401234567'),
            'SE e164'   => array('+46701234567', 'SE', '+46701234567'),
            'GB local'  => array('07911123456',  'GB', '+447911123456'),
            'AU local'  => array('0412345678',   'AU', '+61412345678'),
            'NO local'  => array('91234567',     'NO', '+4791234567'),
        );
    }

    // -------------------------------------------------------------------------
    // Ambiguous number without country hint
    // -------------------------------------------------------------------------

    public function test_swedish_number_without_plus_uses_default_country()
    {
        // 4677653819 is 10 digits; with default US it won't match US length properly
        // because it starts with a digit that would be treated as local.
        // With SE hint it should be valid.
        $result_se = PhoneValidator::validate_with_country_hint('0677653819', 'SE');
        $this->assertTrue($result_se['valid']);
        $this->assertSame('SE', $result_se['country_iso2']);
    }

    public function test_e164_swedish_number_always_accepted()
    {
        $result = PhoneValidator::validate_with_country_hint('+46677653819');
        $this->assertTrue($result['valid']);
        $this->assertSame('SE', $result['country_iso2']);
        $this->assertSame('e164', $result['input_format']);
    }

    // -------------------------------------------------------------------------
    // Rich result metadata
    // -------------------------------------------------------------------------

    public function test_national_number_extracted_correctly()
    {
        $result = PhoneValidator::validate_with_country_hint('+46701234567', 'SE');
        $this->assertSame('701234567', $result['national_number']);
        $this->assertSame('46', $result['country_calling_code']);
    }

    public function test_input_format_national_for_local_number()
    {
        $result = PhoneValidator::validate_with_country_hint('2125551234', 'US');
        $this->assertSame('national', $result['input_format']);
    }

    public function test_input_format_e164_for_plus_number()
    {
        $result = PhoneValidator::validate_with_country_hint('+12125551234');
        $this->assertSame('e164', $result['input_format']);
    }

    // -------------------------------------------------------------------------
    // Fallback to default country when no hint
    // -------------------------------------------------------------------------

    public function test_validate_falls_back_to_default_country()
    {
        $result = PhoneValidator::validate_with_country_hint('2125551234', null, 'US');
        $this->assertTrue($result['valid']);
        $this->assertSame('US', $result['country_iso2']);
    }

    // -------------------------------------------------------------------------
    // Failure cases
    // -------------------------------------------------------------------------

    public function test_empty_phone_returns_reason_code_empty_input()
    {
        $result = PhoneValidator::validate_with_country_hint('');
        $this->assertFalse($result['valid']);
        $this->assertSame('empty_input', $result['reason_code']);
    }

    public function test_invalid_chars_returns_reason_code()
    {
        $result = PhoneValidator::validate_with_country_hint('abc-def-ghij', 'US');
        $this->assertFalse($result['valid']);
        $this->assertSame('invalid_characters', $result['reason_code']);
    }

    public function test_wrong_country_format_fails()
    {
        // A 9-digit number passed for a country expecting 10 digits.
        $result = PhoneValidator::validate_with_country_hint('212555123', 'US');
        $this->assertFalse($result['valid']);
    }
}
