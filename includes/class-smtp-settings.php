<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmtpSettings
{
    public const OPTION_KEY = 'mnem_smtp_settings';

    public const DEFAULT_SETTINGS = array(
        // Legacy SMTP fields (also used when provider_type = 'smtp').
        'host'             => '',
        'port'             => 587,
        'encryption'       => 'tls',
        'username'         => '',
        'password'         => '',
        'from_email'       => '',
        'from_name'        => '',
        // Multi-provider fields.
        'provider_type'    => 'smtp',
        'provider_config'  => array(),
        'fallback_provider' => '',
        'fallback_enabled'  => false,
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

        // Multi-provider fields.
        $valid_providers = array('smtp', 'mailgun', 'sendgrid', 'brevo', 'postmark', 'smtp2go');
        $provider_type = isset($data['provider_type']) ? sanitize_text_field($data['provider_type']) : $current['provider_type'];
        if (!in_array($provider_type, $valid_providers, true)) {
            $provider_type = 'smtp';
        }
        $sanitized['provider_type'] = $provider_type;

        $fallback_provider = isset($data['fallback_provider']) ? sanitize_text_field($data['fallback_provider']) : $current['fallback_provider'];
        if (!in_array($fallback_provider, array_merge($valid_providers, array('')), true)) {
            $fallback_provider = '';
        }
        $sanitized['fallback_provider'] = $fallback_provider;
        $sanitized['fallback_enabled'] = !empty($data['fallback_enabled']);

        // Provider-specific configs (API keys stored base64 for obfuscation).
        $current_provider_config = is_array($current['provider_config']) ? $current['provider_config'] : array();
        $new_provider_config = isset($data['provider_config']) && is_array($data['provider_config']) ? $data['provider_config'] : array();
        $sanitized_provider_config = $current_provider_config;

        foreach ($valid_providers as $ptype) {
            if (!isset($new_provider_config[$ptype])) {
                continue;
            }
            $pdata = is_array($new_provider_config[$ptype]) ? $new_provider_config[$ptype] : array();
            $current_pdata = isset($current_provider_config[$ptype]) && is_array($current_provider_config[$ptype]) ? $current_provider_config[$ptype] : array();

            // api_key.
            if (isset($pdata['api_key']) && $pdata['api_key'] !== '') {
                $sanitized_provider_config[$ptype]['api_key'] = base64_encode((string) $pdata['api_key']);
            } elseif (isset($current_pdata['api_key'])) {
                $sanitized_provider_config[$ptype]['api_key'] = $current_pdata['api_key'];
            }

            // server_token (Postmark).
            if (isset($pdata['server_token']) && $pdata['server_token'] !== '') {
                $sanitized_provider_config[$ptype]['server_token'] = base64_encode((string) $pdata['server_token']);
            } elseif (isset($current_pdata['server_token'])) {
                $sanitized_provider_config[$ptype]['server_token'] = $current_pdata['server_token'];
            }

            // domain (Mailgun).
            if (isset($pdata['domain'])) {
                $sanitized_provider_config[$ptype]['domain'] = sanitize_text_field($pdata['domain']);
            } elseif (isset($current_pdata['domain'])) {
                $sanitized_provider_config[$ptype]['domain'] = $current_pdata['domain'];
            }
        }
        $sanitized['provider_config'] = $sanitized_provider_config;

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

    public static function is_active_provider_configured(): bool
    {
        return self::is_provider_configured((string) self::get('provider_type', 'smtp'), self::get_all());
    }

    /**
     * @param string              $provider_type
     * @param array<string,mixed> $settings
     */
    public static function is_provider_configured(string $provider_type, array $settings): bool
    {
        $provider_type = sanitize_text_field($provider_type);

        if ($provider_type === 'smtp') {
            return isset($settings['host']) && trim((string) $settings['host']) !== '';
        }

        $provider_config = isset($settings['provider_config']) && is_array($settings['provider_config'])
            ? $settings['provider_config']
            : array();
        $config = isset($provider_config[$provider_type]) && is_array($provider_config[$provider_type])
            ? $provider_config[$provider_type]
            : array();

        if ($provider_type === 'mailgun') {
            return !empty($config['api_key']) && !empty($config['domain']);
        }

        if ($provider_type === 'postmark') {
            return !empty($config['server_token']);
        }

        return in_array($provider_type, array('sendgrid', 'brevo', 'smtp2go'), true) && !empty($config['api_key']);
    }

    /**
     * Get the sender name. Falls back to the site name if not set.
     */
    public static function get_sender_name()
    {
        $name = (string) get_site_option('mnem_sender_name', '');
        if ($name !== '') {
            return $name;
        }

        return function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
    }

    /**
     * Get the sender email. Falls back to admin_email if not set.
     */
    public static function get_sender_email()
    {
        $email = (string) get_site_option('mnem_sender_email', '');
        if ($email !== '') {
            return $email;
        }

        if (function_exists('get_option')) {
            return (string) get_option('admin_email', '');
        }

        return '';
    }

    /**
     * Whether the global header/footer feature is enabled.
     */
    public static function is_global_header_footer_enabled()
    {
        return (int) get_site_option('mnem_force_global_header_footer', 0) === 1;
    }
}
