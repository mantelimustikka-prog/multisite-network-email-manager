<?php

namespace MNEM;

defined('ABSPATH') || exit;

class PhoneValidator
{
    /**
     * lengths = expected national-number digit counts after optional trunk-zero removal
     * and before the country dial code is prefixed for E.164 formatting.
     *
     * @var array<string,array<string,mixed>>
     */
    private const COUNTRY_RULES = array(
        'US' => array('code' => '1', 'lengths' => array(10), 'strip_trunk_zero' => false),
        'CA' => array('code' => '1', 'lengths' => array(10), 'strip_trunk_zero' => false),
        'GB' => array('code' => '44', 'lengths' => array(10), 'strip_trunk_zero' => true),
        'AU' => array('code' => '61', 'lengths' => array(9), 'strip_trunk_zero' => true),
        'IN' => array('code' => '91', 'lengths' => array(10), 'strip_trunk_zero' => true),
        'FI' => array('code' => '358', 'lengths' => array(9, 10), 'strip_trunk_zero' => true),
    );

    /**
     * @return array<string,mixed>
     */
    public static function validate_phone_number($phone_number, $country_code = 'US'): array
    {
        $phone_number = trim((string) $phone_number);
        $country_code = strtoupper(trim((string) $country_code));

        if ($phone_number === '') {
            return array(
                'valid' => false,
                'formatted' => '',
                'error' => 'Phone number is required.',
            );
        }

        if (!preg_match('/^\+?[\d\s\-\(\)]+$/', $phone_number)) {
            return array(
                'valid' => false,
                'formatted' => '',
                'error' => 'Phone number contains invalid characters.',
            );
        }

        if (substr_count($phone_number, '+') > 1 || (strpos($phone_number, '+') !== false && strpos($phone_number, '+') !== 0)) {
            return array(
                'valid' => false,
                'formatted' => '',
                'error' => 'Phone number has an invalid international prefix.',
            );
        }

        $digits = preg_replace('/\D+/', '', $phone_number);
        $digits = is_string($digits) ? $digits : '';

        if ($digits === '' || strlen($digits) < 7 || strlen($digits) > 15) {
            return array(
                'valid' => false,
                'formatted' => '',
                'error' => 'Phone number must contain between 7 and 15 digits.',
            );
        }

        $rule = self::COUNTRY_RULES[$country_code] ?? null;
        $country_dial_code = is_array($rule) && isset($rule['code']) ? (string) $rule['code'] : '';
        $national_number = $digits;

        if (strpos($phone_number, '+') === 0) {
            if ($country_dial_code !== '' && strpos($digits, $country_dial_code) === 0 && strlen($digits) > strlen($country_dial_code)) {
                $national_number = substr($digits, strlen($country_dial_code));
            }
        } else {
            if (is_array($rule) && !empty($rule['strip_trunk_zero'])) {
                $digits = ltrim($digits, '0');
            }

            $national_number = $digits;

            if ($country_dial_code !== '') {
                $lengths = isset($rule['lengths']) && is_array($rule['lengths']) ? $rule['lengths'] : array();
                if (!empty($lengths) && in_array(strlen($digits), array_map('intval', $lengths), true)) {
                    $digits = $country_dial_code . $digits;
                } elseif (strpos($digits, $country_dial_code) !== 0) {
                    return array(
                        'valid' => false,
                        'formatted' => '',
                        'error' => sprintf('Phone number does not match the expected %s format.', $country_code),
                    );
                }

                if (strpos($digits, $country_dial_code) === 0 && strlen($digits) > strlen($country_dial_code)) {
                    $national_number = substr($digits, strlen($country_dial_code));
                }
            }
        }

        $should_check_leading_digit = is_array($rule) || strpos($phone_number, '+') !== 0;
        if ($national_number === '' || ($should_check_leading_digit && preg_match('/^[01]/', $national_number))) {
            return array(
                'valid' => false,
                'formatted' => '',
                'error' => 'Phone number cannot start with 0 or 1.',
            );
        }

        $formatted = '+' . $digits;

        return array(
            'valid' => true,
            'formatted' => $formatted,
            'error' => '',
        );
    }

    public static function is_valid_mobile_number($phone_number): bool
    {
        $result = self::validate_phone_number($phone_number);

        return !empty($result['valid']);
    }

    public static function format_phone_number($phone_number, $country_code = 'US'): string
    {
        $result = self::validate_phone_number($phone_number, $country_code);

        return !empty($result['valid']) ? (string) $result['formatted'] : '';
    }
}
