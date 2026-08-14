<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Manages email provider instances and orchestrates sending with fallback.
 */
class ProviderManager
{
    /** @var array<string,EmailProvider> */
    private static $instances = array();

    /** @var array<string,string> */
    private static $provider_classes = array(
        'smtp'     => SmtpProvider::class,
        'mailgun'  => MailgunProvider::class,
        'sendgrid' => SendgridProvider::class,
        'brevo'    => BrevoProvider::class,
        'postmark' => PostmarkProvider::class,
        'smtp2go'  => Smtp2goProvider::class,
    );

    /**
     * Get all available providers with metadata.
     *
     * @return array<string,array<string,mixed>>
     */
    public static function get_available_providers(): array
    {
        return array(
            'smtp'     => array('name' => 'SMTP (Generic)',  'description' => 'Send via any SMTP server using PHPMailer.'),
            'mailgun'  => array('name' => 'Mailgun',         'description' => 'Send via Mailgun REST API.'),
            'sendgrid' => array('name' => 'SendGrid',        'description' => 'Send via SendGrid REST API v3.'),
            'brevo'    => array('name' => 'Brevo',           'description' => 'Send via Brevo (formerly Sendinblue) REST API.'),
            'postmark' => array('name' => 'Postmark',        'description' => 'Send via Postmark REST API.'),
            'smtp2go'  => array('name' => 'SMTP2GO',         'description' => 'Send via SMTP2GO REST API.'),
        );
    }

    /**
     * Instantiate a provider by type with appropriate config.
     *
     * @param string              $type
     * @param array<string,mixed> $config
     * @return EmailProvider|null
     */
    public static function get_provider(string $type, array $config = array()): ?EmailProvider
    {
        if (!isset(self::$provider_classes[$type])) {
            return null;
        }

        $class = self::$provider_classes[$type];

        if (empty($config)) {
            $config = self::build_config($type);
        }

        // Cache by type + config hash to avoid rebuilding on each call.
        $cache_key = $type . ':' . md5(serialize($config));
        if (!isset(self::$instances[$cache_key])) {
            self::$instances[$cache_key] = new $class($config);
        }

        return self::$instances[$cache_key];
    }

    /**
     * Flush provider instance cache (used in tests or after settings change).
     */
    public static function flush_instances(): void
    {
        self::$instances = array();
    }

    /**
     * Send an email using the configured primary provider, with optional fallback.
     *
     * @param string              $to
     * @param string              $subject
     * @param string              $body
     * @param array<string,mixed> $headers
     * @return array<string,mixed>
     */
    public static function send_email(string $to, string $subject, string $body, array $headers = array()): array
    {
        $settings         = SmtpSettings::get_all();
        $primary_type     = isset($settings['provider_type']) ? (string) $settings['provider_type'] : 'smtp';
        $fallback_type    = isset($settings['fallback_provider']) ? (string) $settings['fallback_provider'] : '';
        $fallback_enabled = !empty($settings['fallback_enabled']);

        $primary = self::get_provider($primary_type);
        if ($primary === null) {
            return array(
                'success'    => false,
                'message'    => 'Unknown provider type: ' . $primary_type,
                'provider'   => $primary_type,
                'message_id' => '',
                'metadata'   => array(),
            );
        }

        Logger::info('Sending email via provider.', array('provider' => $primary_type, 'to' => $to));
        $result = $primary->send($to, $subject, $body, $headers);

        if ($result['success']) {
            return $result;
        }

        Logger::warning('Primary provider failed.', array('provider' => $primary_type, 'error' => $result['message'], 'to' => $to));

        // Try fallback provider if configured.
        if ($fallback_enabled && $fallback_type !== '' && $fallback_type !== $primary_type) {
            $fallback = self::get_provider($fallback_type);
            if ($fallback !== null) {
                Logger::info('Attempting fallback provider.', array('provider' => $fallback_type, 'to' => $to));
                $fallback_result = $fallback->send($to, $subject, $body, $headers);
                $fallback_result['metadata']['fallback_from'] = $primary_type;
                $fallback_result['metadata']['primary_error'] = $result['message'];

                if ($fallback_result['success']) {
                    Logger::info('Fallback provider succeeded.', array('provider' => $fallback_type, 'to' => $to));
                } else {
                    Logger::error('Fallback provider also failed.', array('provider' => $fallback_type, 'error' => $fallback_result['message'], 'to' => $to));
                }

                return $fallback_result;
            }
        }

        return $result;
    }

    /**
     * Build provider config from SmtpSettings for the given type.
     *
     * @param string $type
     * @return array<string,mixed>
     */
    private static function build_config(string $type): array
    {
        $settings = SmtpSettings::get_all();

        // Common from address.
        $base = array(
            'from_email' => isset($settings['from_email']) ? (string) $settings['from_email'] : '',
            'from_name'  => isset($settings['from_name'])  ? (string) $settings['from_name']  : '',
        );

        if ($type === 'smtp') {
            return array_merge($base, array(
                'host'       => isset($settings['host'])       ? (string) $settings['host']       : '',
                'port'       => isset($settings['port'])       ? (int)    $settings['port']        : 587,
                'encryption' => isset($settings['encryption']) ? (string) $settings['encryption'] : 'tls',
                'username'   => isset($settings['username'])   ? (string) $settings['username']   : '',
                'password'   => SmtpSettings::get_password_decoded(),
            ));
        }

        $provider_configs = isset($settings['provider_config']) && is_array($settings['provider_config'])
            ? $settings['provider_config']
            : array();

        $provider_specific = isset($provider_configs[$type]) && is_array($provider_configs[$type])
            ? $provider_configs[$type]
            : array();

        // Decode stored API keys (stored as base64 for obfuscation).
        if (isset($provider_specific['api_key']) && (string) $provider_specific['api_key'] !== '') {
            $decoded = base64_decode($provider_specific['api_key'], true);
            $provider_specific['api_key'] = $decoded !== false ? $decoded : $provider_specific['api_key'];
        }

        if (isset($provider_specific['server_token']) && (string) $provider_specific['server_token'] !== '') {
            $decoded = base64_decode($provider_specific['server_token'], true);
            $provider_specific['server_token'] = $decoded !== false ? $decoded : $provider_specific['server_token'];
        }

        return array_merge($base, $provider_specific);
    }
}
