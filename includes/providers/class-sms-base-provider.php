<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * Abstract base for SMS providers.
 */
abstract class SmsBaseProvider implements \MNEM\Interfaces\SmsProviderInterface
{
    /** @var array<string,string> */
    protected array $config;

    /** @param array<string,string> $config */
    public function __construct(array $config = array())
    {
        $this->config = $config;
    }

    public function send(string $phone, string $message): array
    {
        return array('success' => false, 'message' => 'Not implemented.');
    }

    public function test_connection(): array
    {
        return array('success' => false, 'message' => 'Not implemented.');
    }

    /**
     * Look up a provider-issued message's current delivery status.
     *
     * @return array{success: bool, provider_status: string, message: string}
     */
    public function get_message_status(string $message_id): array
    {
        return array(
            'success'         => false,
            'provider_status' => '',
            'message'         => 'Message status lookup is not supported by this provider.',
        );
    }

    public function supports_message_status_lookup(): bool
    {
        return false;
    }

    /**
     * Return the provider key (slug) used in webhook URLs and status maps.
     * Override in each concrete provider.
     */
    public static function get_provider_key(): string
    {
        return '';
    }

    /**
     * Return the webhook secret key used to verify incoming requests.
     * Providers should override this to return the actual secret from config/options.
     */
    public static function get_webhook_signature_key(): string
    {
        return '';
    }

    /**
     * Verify a webhook signature from the provider.
     * Default: no verification (override in each provider).
     */
    public static function verify_webhook_signature(string $payload, string $signature): bool
    {
        return true;
    }

    /**
     * Parse provider webhook payload into a normalised delivery status array.
     *
     * @param  array<string,mixed> $data
     * @return array{status: string, message_id: string, phone: string}
     */
    public static function parse_delivery_status(array $data): array
    {
        return array('status' => '', 'message_id' => '', 'phone' => '');
    }

    /**
     * Return the REST endpoint URL where this provider should send delivery callbacks.
     */
    public static function get_webhook_url(): string
    {
        $key = static::get_provider_key();
        if ($key === '') {
            return '';
        }
        if (function_exists('rest_url')) {
            return rest_url('mnem/v1/sms-webhooks/' . $key);
        }
        return '/wp-json/mnem/v1/sms-webhooks/' . $key;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return array{success: bool, message: string, message_id: string} */
    protected function success_result(string $message, string $message_id = ''): array
    {
        return array('success' => true, 'message' => $message, 'message_id' => $message_id);
    }

    /** @return array{success: bool, message: string, message_id: string} */
    protected function error_result(string $message): array
    {
        return array('success' => false, 'message' => $message, 'message_id' => '');
    }

    /**
     * Make a GET request via wp_remote_get() with standard timeout.
     *
     * @param  string               $url
     * @param  array<string,mixed>  $headers
     * @return array{code: int, body: string}|\WP_Error
     */
    protected function http_get(string $url, array $headers = array())
    {
        $response = wp_remote_get(
            $url,
            array(
                'headers' => $headers,
                'timeout' => 10,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return array(
            'code' => (int) wp_remote_retrieve_response_code($response),
            'body' => (string) wp_remote_retrieve_body($response),
        );
    }

    /**
     * Make a POST request via wp_remote_post() with standard timeout.
     *
     * @param  string               $url
     * @param  array<string,mixed>  $headers
     * @param  string               $body
     * @return array{code: int, body: string}|\WP_Error
     */
    protected function http_post(string $url, array $headers = array(), string $body = '')
    {
        $response = wp_remote_post(
            $url,
            array(
                'headers' => $headers,
                'body'    => $body,
                'timeout' => 10,
            )
        );

        if (is_wp_error($response)) {
            return $response;
        }

        return array(
            'code' => (int) wp_remote_retrieve_response_code($response),
            'body' => (string) wp_remote_retrieve_body($response),
        );
    }
}
