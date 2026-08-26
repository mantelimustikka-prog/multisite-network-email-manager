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
}
