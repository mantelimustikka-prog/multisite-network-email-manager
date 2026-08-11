<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmtpSettings
{
    public const OPTION_KEY = 'mnem_smtp_settings';

    public const DEFAULT_SETTINGS = array(
        'host' => '',
        'port' => 587,
        'encryption' => 'tls',
        'username' => '',
        'password' => '',
        'from_email' => '',
        'from_name' => '',
    );

    public static function get_all()
    {
        $settings = get_site_option(self::OPTION_KEY, self::DEFAULT_SETTINGS);
        $settings = is_array($settings) ? $settings : array();

        return array_merge(self::DEFAULT_SETTINGS, $settings);
    }

    public static function get(string $key, $default = null)
    {
        $settings = self::get_all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function save(array $data)
    {
        $current = self::get_all();
        $sanitized = self::DEFAULT_SETTINGS;

        $sanitized['host'] = isset($data['host']) ? sanitize_text_field($data['host']) : $current['host'];
        $sanitized['port'] = isset($data['port']) ? max(1, (int) $data['port']) : (int) $current['port'];

        $encryption = isset($data['encryption']) ? sanitize_text_field($data['encryption']) : $current['encryption'];
        if (!in_array($encryption, array('tls', 'ssl', 'none'), true)) {
            $encryption = 'tls';
        }
        $sanitized['encryption'] = $encryption;

        $sanitized['username'] = isset($data['username']) ? sanitize_text_field($data['username']) : $current['username'];

        // Password is stored as base64-encoded text for obfuscation only. This is NOT encryption.
        if (array_key_exists('password', $data) && $data['password'] !== '') {
            $sanitized['password'] = base64_encode((string) $data['password']);
        } else {
            $sanitized['password'] = isset($current['password']) ? (string) $current['password'] : '';
        }

        $sanitized['from_email'] = isset($data['from_email']) ? sanitize_email($data['from_email']) : $current['from_email'];
        $sanitized['from_name'] = isset($data['from_name']) ? sanitize_text_field($data['from_name']) : $current['from_name'];

        return update_site_option(self::OPTION_KEY, $sanitized);
    }

    public static function get_password_decoded()
    {
        $password = (string) self::get('password', '');

        if ($password === '') {
            return '';
        }

        $decoded = base64_decode($password, true);

        return $decoded === false ? '' : $decoded;
    }
}
