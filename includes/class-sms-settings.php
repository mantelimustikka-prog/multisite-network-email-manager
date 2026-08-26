<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmsSettings
{
    public const OPTION_PROVIDER          = 'mnem_sms_provider';
    public const OPTION_ENABLED           = 'mnem_sms_enabled';
    public const OPTION_CONFIG            = 'mnem_sms_config';
    public const OPTION_MAX_PER_DAY       = 'mnem_sms_max_per_day';
    public const OPTION_NO_HOURS          = 'mnem_sms_no_hours';
    public const OPTION_DELAY             = 'mnem_sms_delay';
    public const OPTION_FALLBACK_PROVIDER = 'mnem_sms_fallback_provider';
    public const OPTION_TRACKING_ENABLED  = 'mnem_sms_tracking_enabled';
    public const OPTION_PHONE_VALIDATION_ENABLED = 'mnem_sms_phone_validation_enabled';
    public const OPTION_VALIDATION_COUNTRY_CODE = 'mnem_sms_validation_country_code';
    public const OPTION_ALLOW_DUPLICATES = 'mnem_sms_allow_duplicate_numbers';
    public const OPTION_AUTO_BLOCK_FAILED_ATTEMPTS = 'mnem_sms_auto_block_failed_attempts';
    public const OPTION_NOTIFY_INVALID_NUMBERS = 'mnem_sms_notify_invalid_numbers';

    // Multi-country validation options.
    public const OPTION_VALIDATION_MODE      = 'mnem_validation_mode';
    public const OPTION_ALLOWED_COUNTRIES    = 'mnem_allowed_countries';
    public const OPTION_AMBIGUOUS_POLICY     = 'mnem_ambiguous_policy';
    public const OPTION_DEFAULT_VALIDATION_COUNTRY = 'mnem_default_validation_country';

    public const VALIDATION_MODE_SINGLE   = 'single-country';
    public const VALIDATION_MODE_MULTI    = 'multi-country';
    public const AMBIGUOUS_POLICY_REJECT  = 'reject';
    public const AMBIGUOUS_POLICY_REVIEW  = 'review';
    public const AMBIGUOUS_POLICY_REQUIRE = 'require-country';

    public const DEFAULT_MAX_PER_DAY = 1000;
    public const DEFAULT_DELAY       = 100;

    // -------------------------------------------------------------------------
    // Bulk get / set
    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function get_all(): array
    {
        return array(
            'provider'          => self::get_provider(),
            'enabled'           => self::is_sms_enabled(),
            'config'            => self::get_provider_config(self::get_provider()),
            'max_per_day'       => self::get_max_sms_per_day(),
            'no_sms_hours'      => self::get_no_sms_hours(),
            'delay'             => self::get_sms_delay(),
            'fallback_provider' => (string) get_site_option(self::OPTION_FALLBACK_PROVIDER, ''),
            'tracking_enabled'  => (int) get_site_option(self::OPTION_TRACKING_ENABLED, 0) === 1,
            'phone_validation_enabled' => self::is_phone_validation_enabled(),
            'validation_country_code' => self::get_validation_country_code(),
            'allow_duplicate_numbers' => self::allow_duplicate_numbers(),
            'auto_block_failed_attempts' => self::get_auto_block_failed_attempts(),
            'notify_invalid_numbers' => self::notify_invalid_numbers(),
            'validation_mode'            => self::get_validation_mode(),
            'allowed_countries'          => self::get_allowed_countries(),
            'ambiguous_policy'           => self::get_ambiguous_policy(),
            'default_validation_country' => self::get_default_validation_country(),
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function save(array $data): bool
    {
        $errors = self::validate_settings($data);
        if (!empty($errors)) {
            return false;
        }

        $valid_providers = SmsProviderManager::get_available_providers();

        $provider = isset($data['provider']) ? sanitize_text_field((string) $data['provider']) : '';
        if ($provider !== '' && !array_key_exists($provider, $valid_providers)) {
            $provider = '';
        }
        update_site_option(self::OPTION_PROVIDER, $provider);

        update_site_option(self::OPTION_ENABLED, !empty($data['enabled']) ? 1 : 0);

        $max_per_day = isset($data['max_per_day']) ? max(1, (int) $data['max_per_day']) : self::DEFAULT_MAX_PER_DAY;
        update_site_option(self::OPTION_MAX_PER_DAY, $max_per_day);

        $no_hours = isset($data['no_sms_hours']) ? sanitize_text_field((string) $data['no_sms_hours']) : '';
        update_site_option(self::OPTION_NO_HOURS, $no_hours);

        $delay = isset($data['delay']) ? max(0, (int) $data['delay']) : self::DEFAULT_DELAY;
        update_site_option(self::OPTION_DELAY, $delay);

        $fallback = isset($data['fallback_provider']) ? sanitize_text_field((string) $data['fallback_provider']) : '';
        if ($fallback !== '' && !array_key_exists($fallback, $valid_providers)) {
            $fallback = '';
        }
        update_site_option(self::OPTION_FALLBACK_PROVIDER, $fallback);

        update_site_option(self::OPTION_TRACKING_ENABLED, !empty($data['tracking_enabled']) ? 1 : 0);
        update_site_option(self::OPTION_PHONE_VALIDATION_ENABLED, !empty($data['phone_validation_enabled']) ? 1 : 0);
        update_site_option(self::OPTION_VALIDATION_COUNTRY_CODE, self::sanitize_country_code(isset($data['validation_country_code']) ? (string) $data['validation_country_code'] : 'US'));
        update_site_option(self::OPTION_ALLOW_DUPLICATES, !empty($data['allow_duplicate_numbers']) ? 1 : 0);
        update_site_option(self::OPTION_AUTO_BLOCK_FAILED_ATTEMPTS, max(0, isset($data['auto_block_failed_attempts']) ? (int) $data['auto_block_failed_attempts'] : 0));
        update_site_option(self::OPTION_NOTIFY_INVALID_NUMBERS, !empty($data['notify_invalid_numbers']) ? 1 : 0);

        // Multi-country settings.
        if (isset($data['validation_mode'])) {
            $mode = sanitize_key((string) $data['validation_mode']);
            if (!in_array($mode, array(self::VALIDATION_MODE_SINGLE, self::VALIDATION_MODE_MULTI), true)) {
                $mode = self::VALIDATION_MODE_SINGLE;
            }
            update_site_option(self::OPTION_VALIDATION_MODE, $mode);
        }
        if (isset($data['allowed_countries'])) {
            $countries = is_array($data['allowed_countries']) ? $data['allowed_countries'] : array();
            $sanitized = array();
            foreach ($countries as $c) {
                $c = strtoupper(trim((string) $c));
                if (preg_match('/^[A-Z]{2}$/', $c)) {
                    $sanitized[] = $c;
                }
            }
            update_site_option(self::OPTION_ALLOWED_COUNTRIES, wp_json_encode(array_unique($sanitized)));
        }
        if (isset($data['ambiguous_policy'])) {
            $policy = sanitize_key((string) $data['ambiguous_policy']);
            $valid_policies = array(self::AMBIGUOUS_POLICY_REJECT, self::AMBIGUOUS_POLICY_REVIEW, self::AMBIGUOUS_POLICY_REQUIRE);
            if (!in_array($policy, $valid_policies, true)) {
                $policy = self::AMBIGUOUS_POLICY_REJECT;
            }
            update_site_option(self::OPTION_AMBIGUOUS_POLICY, $policy);
        }
        if (isset($data['default_validation_country'])) {
            update_site_option(self::OPTION_DEFAULT_VALIDATION_COUNTRY, self::sanitize_country_code((string) $data['default_validation_country']));
        }

        // Merge new provider config over existing (obfuscate credentials with base64).
        if (isset($data['config']) && is_array($data['config'])) {
            $existing_raw = get_site_option(self::OPTION_CONFIG, '');
            $existing     = array();
            if (is_string($existing_raw) && $existing_raw !== '') {
                $decoded = json_decode($existing_raw, true);
                $existing = is_array($decoded) ? $decoded : array();
            } elseif (is_array($existing_raw)) {
                $existing = $existing_raw;
            }

            foreach ($data['config'] as $prov => $fields) {
                if (!is_array($fields)) {
                    continue;
                }
                $prov = sanitize_key((string) $prov);
                if (!isset($existing[$prov])) {
                    $existing[$prov] = array();
                }
                foreach ($fields as $k => $v) {
                    $k = sanitize_key((string) $k);
                    if ((string) $v !== '') {
                        $existing[$prov][$k] = base64_encode((string) $v);
                    }
                }
            }
            update_site_option(self::OPTION_CONFIG, wp_json_encode($existing));
        }

        return true;
    }

    /** @param mixed $default */
    public static function get(string $key, $default = null)
    {
        return get_site_option('mnem_sms_' . $key, $default);
    }

    /** @param mixed $value */
    public static function set(string $key, $value): bool
    {
        return (bool) update_site_option('mnem_sms_' . $key, $value);
    }

    // -------------------------------------------------------------------------
    // Provider-specific getters
    // -------------------------------------------------------------------------

    public static function get_provider(): string
    {
        return (string) get_site_option(self::OPTION_PROVIDER, '');
    }

    /** @return array<string,mixed> */
    public static function get_provider_config(string $provider): array
    {
        $raw = get_site_option(self::OPTION_CONFIG, '');
        $all = array();
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $all     = is_array($decoded) ? $decoded : array();
        } elseif (is_array($raw)) {
            $all = $raw;
        }

        $provider = sanitize_key($provider);
        $config   = isset($all[$provider]) && is_array($all[$provider]) ? $all[$provider] : array();

        // Decode base64-obfuscated values for callers.
        $decoded_config = array();
        foreach ($config as $k => $v) {
            $decoded = base64_decode((string) $v, true);
            $decoded_config[$k] = $decoded !== false ? $decoded : (string) $v;
        }

        return $decoded_config;
    }

    public static function is_sms_enabled(): bool
    {
        return (int) get_site_option(self::OPTION_ENABLED, 0) === 1;
    }

    public static function get_max_sms_per_day(): int
    {
        return max(1, (int) get_site_option(self::OPTION_MAX_PER_DAY, self::DEFAULT_MAX_PER_DAY));
    }

    public static function get_no_sms_hours(): string
    {
        return (string) get_site_option(self::OPTION_NO_HOURS, '');
    }

    public static function get_sms_delay(): int
    {
        return max(0, (int) get_site_option(self::OPTION_DELAY, self::DEFAULT_DELAY));
    }

    public static function is_phone_validation_enabled(): bool
    {
        return (int) get_site_option(self::OPTION_PHONE_VALIDATION_ENABLED, 1) === 1;
    }

    public static function get_validation_country_code(): string
    {
        return self::sanitize_country_code((string) get_site_option(self::OPTION_VALIDATION_COUNTRY_CODE, 'US'));
    }

    public static function allow_duplicate_numbers(): bool
    {
        return (int) get_site_option(self::OPTION_ALLOW_DUPLICATES, 0) === 1;
    }

    public static function get_auto_block_failed_attempts(): int
    {
        return max(0, (int) get_site_option(self::OPTION_AUTO_BLOCK_FAILED_ATTEMPTS, 0));
    }

    public static function notify_invalid_numbers(): bool
    {
        return (int) get_site_option(self::OPTION_NOTIFY_INVALID_NUMBERS, 0) === 1;
    }

    public static function get_validation_mode(): string
    {
        $mode = (string) get_site_option(self::OPTION_VALIDATION_MODE, self::VALIDATION_MODE_SINGLE);
        return in_array($mode, array(self::VALIDATION_MODE_SINGLE, self::VALIDATION_MODE_MULTI), true) ? $mode : self::VALIDATION_MODE_SINGLE;
    }

    public static function is_multi_country_mode(): bool
    {
        return self::get_validation_mode() === self::VALIDATION_MODE_MULTI;
    }

    /**
     * @return string[]  ISO2 country codes, empty = all countries allowed
     */
    public static function get_allowed_countries(): array
    {
        $raw = get_site_option(self::OPTION_ALLOWED_COUNTRIES, '');
        if (!is_string($raw) || $raw === '') {
            return array();
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : array();
    }

    public static function get_ambiguous_policy(): string
    {
        $policy = (string) get_site_option(self::OPTION_AMBIGUOUS_POLICY, self::AMBIGUOUS_POLICY_REJECT);
        $valid = array(self::AMBIGUOUS_POLICY_REJECT, self::AMBIGUOUS_POLICY_REVIEW, self::AMBIGUOUS_POLICY_REQUIRE);
        return in_array($policy, $valid, true) ? $policy : self::AMBIGUOUS_POLICY_REJECT;
    }

    public static function get_default_validation_country(): string
    {
        $stored = (string) get_site_option(self::OPTION_DEFAULT_VALIDATION_COUNTRY, '');
        if ($stored !== '') {
            return self::sanitize_country_code($stored);
        }
        // Fall back to legacy single-country setting.
        return self::get_validation_country_code();
    }

    // -------------------------------------------------------------------------
    // Time-window helper
    // -------------------------------------------------------------------------

    /**
     * Check whether the current time falls within the "no SMS hours" window.
     * The format is "HH:MM:SS-HH:MM:SS" (start-end); if the start is later
     * than the end the window wraps around midnight.
     */
    public static function is_in_no_sms_hours(): bool
    {
        $hours = self::get_no_sms_hours();
        if ($hours === '' || !self::validate_no_sms_hours($hours)) {
            return false;
        }

        list($start_str, $end_str) = explode('-', $hours, 2);

        $now_seconds   = self::time_to_seconds(function_exists('wp_date') ? (string) wp_date('H:i:s') : date('H:i:s'));
        $start_seconds = self::time_to_seconds($start_str);
        $end_seconds   = self::time_to_seconds($end_str);

        if ($start_seconds === false || $end_seconds === false || $now_seconds === false) {
            return false;
        }

        if ($start_seconds < $end_seconds) {
            // Same-day window: e.g., 09:00:00-17:00:00
            return $now_seconds >= $start_seconds && $now_seconds < $end_seconds;
        }

        // Overnight window: e.g., 21:00:00-07:00:00
        return $now_seconds >= $start_seconds || $now_seconds < $end_seconds;
    }

    /**
     * Validate the "no SMS hours" format: HH:MM:SS-HH:MM:SS with valid time values.
     */
    public static function validate_no_sms_hours(string $hours): bool
    {
        if (!preg_match('/^\d{2}:\d{2}:\d{2}-\d{2}:\d{2}:\d{2}$/', $hours)) {
            return false;
        }

        list($start, $end) = explode('-', $hours, 2);

        return self::is_valid_time($start) && self::is_valid_time($end);
    }

    // -------------------------------------------------------------------------
    // Validation helper
    // -------------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $data
     * @return string[]
     */
    public static function validate_settings(array $data): array
    {
        $errors = array();

        if (!empty($data['no_sms_hours']) && !self::validate_no_sms_hours((string) $data['no_sms_hours'])) {
            $errors[] = 'Invalid No SMS Hours format';
        }

        if (isset($data['max_per_day']) && (int) $data['max_per_day'] < 1) {
            $errors[] = 'Max SMS per day must be at least 1';
        }

        if (isset($data['delay']) && (int) $data['delay'] < 0) {
            $errors[] = 'SMS delay cannot be negative';
        }

        if (isset($data['validation_country_code']) && !preg_match('/^[A-Z]{2}$/', strtoupper(trim((string) $data['validation_country_code'])))) {
            $errors[] = 'Validation country code must be a two-letter country code';
        }

        if (isset($data['auto_block_failed_attempts']) && (int) $data['auto_block_failed_attempts'] < 0) {
            $errors[] = 'Auto block failed attempts cannot be negative';
        }

        return $errors;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** @return int|false */
    private static function time_to_seconds(string $time)
    {
        $parts = explode(':', $time);
        if (count($parts) !== 3) {
            return false;
        }
        return (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (int) $parts[2];
    }

    private static function is_valid_time(string $time): bool
    {
        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return false;
        }
        list($h, $m, $s) = explode(':', $time);
        return (int) $h < 24 && (int) $m < 60 && (int) $s < 60;
    }

    private static function sanitize_country_code(string $country_code): string
    {
        $country_code = strtoupper(trim($country_code));
        $country_code = preg_replace('/[^A-Z]/', '', $country_code);

        return is_string($country_code) && $country_code !== '' ? $country_code : 'US';
    }
}
