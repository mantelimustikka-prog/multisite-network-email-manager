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
        'US' => array('code' => '1',   'lengths' => array(10),     'strip_trunk_zero' => false),
        'CA' => array('code' => '1',   'lengths' => array(10),     'strip_trunk_zero' => false),
        'GB' => array('code' => '44',  'lengths' => array(10),     'strip_trunk_zero' => true),
        'AU' => array('code' => '61',  'lengths' => array(9),      'strip_trunk_zero' => true),
        'IN' => array('code' => '91',  'lengths' => array(10),     'strip_trunk_zero' => true),
        'FI' => array('code' => '358', 'lengths' => array(9, 10),  'strip_trunk_zero' => true),
        'SE' => array('code' => '46',  'lengths' => array(7, 8, 9, 10), 'strip_trunk_zero' => true),
        'NO' => array('code' => '47',  'lengths' => array(8),      'strip_trunk_zero' => false),
        'DK' => array('code' => '45',  'lengths' => array(8),      'strip_trunk_zero' => false),
        'DE' => array('code' => '49',  'lengths' => array(10, 11), 'strip_trunk_zero' => true),
        'FR' => array('code' => '33',  'lengths' => array(9),      'strip_trunk_zero' => true),
        'ES' => array('code' => '34',  'lengths' => array(9),      'strip_trunk_zero' => false),
        'IT' => array('code' => '39',  'lengths' => array(10),     'strip_trunk_zero' => false),
        'NL' => array('code' => '31',  'lengths' => array(9),      'strip_trunk_zero' => true),
        'BE' => array('code' => '32',  'lengths' => array(9),      'strip_trunk_zero' => true),
        'PL' => array('code' => '48',  'lengths' => array(9),      'strip_trunk_zero' => false),
        'BR' => array('code' => '55',  'lengths' => array(10, 11), 'strip_trunk_zero' => false),
        'MX' => array('code' => '52',  'lengths' => array(10),     'strip_trunk_zero' => false),
        'JP' => array('code' => '81',  'lengths' => array(10),     'strip_trunk_zero' => true),
        'CN' => array('code' => '86',  'lengths' => array(11),     'strip_trunk_zero' => false),
        'SG' => array('code' => '65',  'lengths' => array(8),      'strip_trunk_zero' => false),
        'ZA' => array('code' => '27',  'lengths' => array(9),      'strip_trunk_zero' => true),
        'NG' => array('code' => '234', 'lengths' => array(10),     'strip_trunk_zero' => true),
        'AE' => array('code' => '971', 'lengths' => array(9),      'strip_trunk_zero' => true),
        'SA' => array('code' => '966', 'lengths' => array(9),      'strip_trunk_zero' => true),
        'EE' => array('code' => '372', 'lengths' => array(7, 8),   'strip_trunk_zero' => false),
        'LV' => array('code' => '371', 'lengths' => array(8),      'strip_trunk_zero' => false),
        'LT' => array('code' => '370', 'lengths' => array(8),      'strip_trunk_zero' => true),
    );

    /**
     * Return all supported country codes.
     *
     * @return string[]
     */
    public static function get_supported_countries(): array
    {
        return array_keys(self::COUNTRY_RULES);
    }

    /**
     * Detect country ISO2 from an E.164 phone number (must start with +).
     * Returns null when the prefix is not recognised.
     *
     * When multiple countries share the same calling code (e.g. US/CA share +1),
     * returns the first match – callers that need full ambiguity info should call
     * detect_possible_countries_from_e164() instead.
     *
     * @param string $phone_number E.164 string starting with +
     * @return string|null
     */
    public static function detect_country_from_e164(string $phone_number): ?string
    {
        $matches = self::detect_possible_countries_from_e164($phone_number);
        return !empty($matches) ? $matches[0] : null;
    }

    /**
     * Return all countries whose calling code matches the given E.164 prefix.
     *
     * @param string $phone_number
     * @return string[]  ISO2 codes
     */
    public static function detect_possible_countries_from_e164(string $phone_number): array
    {
        if (strpos($phone_number, '+') !== 0) {
            return array();
        }
        $digits = preg_replace('/\D+/', '', $phone_number);
        $digits = is_string($digits) ? $digits : '';

        // Sort rules longest code first so +358 matches before +35 (hypothetical).
        $sorted = self::COUNTRY_RULES;
        uasort($sorted, function ($a, $b) {
            return strlen((string) $b['code']) - strlen((string) $a['code']);
        });

        $found = array();
        foreach ($sorted as $iso2 => $rule) {
            $code = (string) $rule['code'];
            if (strpos($digits, $code) === 0 && strlen($digits) > strlen($code)) {
                $found[] = $iso2;
            }
        }
        return $found;
    }

    /**
     * Validate with an explicit country hint.
     *
     * Behaviour:
     *  - If $country_code is provided (non-empty) it is used directly.
     *  - If $phone_number starts with + (E.164) the country is auto-detected.
     *  - Otherwise the validator falls back to $default_country.
     *
     * Returns the same rich result array as validate_phone_number(), plus:
     *   country_iso2, country_calling_code, national_number,
     *   input_format, ambiguous, possible_countries, reason_code.
     *
     * @param string      $phone_number
     * @param string|null $country_code  ISO2 hint, or null for auto-detection
     * @param string      $default_country  Fallback ISO2 when no hint and not E.164
     * @return array<string,mixed>
     */
    public static function validate_with_country_hint(string $phone_number, ?string $country_code = null, string $default_country = 'US'): array
    {
        $phone_number = trim($phone_number);
        $hint = $country_code !== null ? strtoupper(trim($country_code)) : null;

        if ($phone_number === '') {
            return self::build_rich_failure('', 'empty_input', 'Phone number is required.');
        }

        $is_e164 = strpos($phone_number, '+') === 0;

        if ($is_e164) {
            // Auto-detect country from E.164 prefix.
            $possible = self::detect_possible_countries_from_e164($phone_number);
            $detected = !empty($possible) ? $possible[0] : null;
            $effective_country = ($hint !== null && $hint !== '') ? $hint : ($detected ?? $default_country);
            $input_format = 'e164';
        } else {
            $effective_country = ($hint !== null && $hint !== '') ? $hint : $default_country;
            $input_format = 'national';
            $possible = array();
        }

        $base = self::validate_phone_number($phone_number, $effective_country);

        if (!$base['valid']) {
            $reason_code = self::classify_reason_code($phone_number, $base['error']);
            return self::build_rich_failure($phone_number, $reason_code, $base['error'], $input_format, $possible);
        }

        $rule = self::COUNTRY_RULES[$effective_country] ?? null;
        $calling_code = is_array($rule) ? (string) $rule['code'] : '';
        $formatted_digits = preg_replace('/\D+/', '', (string) $base['formatted']);
        $formatted_digits = is_string($formatted_digits) ? $formatted_digits : '';
        $national = ($calling_code !== '' && strpos($formatted_digits, $calling_code) === 0)
            ? substr($formatted_digits, strlen($calling_code))
            : $formatted_digits;

        return array(
            'valid'                => true,
            'formatted'            => $base['formatted'],
            'error'                => '',
            'country_iso2'         => $effective_country,
            'country_calling_code' => $calling_code,
            'national_number'      => $national,
            'input_format'         => $input_format,
            'ambiguous'            => false,
            'possible_countries'   => $possible,
            'reason_code'          => null,
        );
    }

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

        $should_check_leading_digit = !( !is_array($rule) && strpos($phone_number, '+') === 0 );
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * @param string   $phone_number
     * @param string   $reason_code
     * @param string   $error
     * @param string   $input_format
     * @param string[] $possible_countries
     * @return array<string,mixed>
     */
    private static function build_rich_failure(string $phone_number, string $reason_code, string $error, string $input_format = 'unknown', array $possible_countries = array()): array
    {
        return array(
            'valid'                => false,
            'formatted'            => '',
            'error'                => $error,
            'country_iso2'         => null,
            'country_calling_code' => null,
            'national_number'      => null,
            'input_format'         => $input_format,
            'ambiguous'            => false,
            'possible_countries'   => $possible_countries,
            'reason_code'          => $reason_code,
        );
    }

    private static function classify_reason_code(string $phone_number, string $error): string
    {
        if (strpos($error, 'required') !== false) {
            return 'empty_input';
        }
        if (strpos($error, 'invalid characters') !== false) {
            return 'invalid_characters';
        }
        if (strpos($error, 'international prefix') !== false) {
            return 'invalid_country_code';
        }
        if (strpos($error, '7 and 15') !== false) {
            return 'invalid_length';
        }
        if (strpos($error, 'does not match') !== false) {
            return 'format_invalid';
        }
        return 'format_invalid';
    }
}
