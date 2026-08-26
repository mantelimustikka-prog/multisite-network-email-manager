<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * SlickText SMS Provider
 *
 * Requires:
 * - Public Key
 * - Private Key
 *
 * Webhook Configuration:
 * Set the delivery webhook URL in SlickText dashboard under Account > API Settings.
 *
 * Supported Statuses: SENT, DELIVERED, FAILED
 */
class SmsSlicktext extends SmsBaseProvider
{
    private const API_BASE = 'https://api.slicktext.com/v1';

    public function get_config_schema(): array
    {
        return array(
            array('key' => 'public_key',  'label' => 'Public Key',  'type' => 'text'),
            array('key' => 'private_key', 'label' => 'Private Key', 'type' => 'password'),
        );
    }

    public static function get_provider_key(): string
    {
        return 'slicktext';
    }

    public function test_connection(): array
    {
        $public_key  = isset($this->config['public_key'])  ? trim((string) $this->config['public_key'])  : '';
        $private_key = isset($this->config['private_key']) ? trim((string) $this->config['private_key']) : '';

        if ($public_key === '' || $private_key === '') {
            return $this->error_result('SlickText Public Key and Private Key are required.');
        }

        $response = $this->http_get(self::API_BASE . '/account', array(
            'Authorization' => 'Basic ' . base64_encode($public_key . ':' . $private_key),
            'Accept'        => 'application/json',
        ));

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        if ($response['code'] === 200) {
            return $this->success_result('SlickText connected successfully.');
        }

        if ($response['code'] === 401) {
            return $this->error_result('SlickText authentication failed. Check your keys.');
        }

        return $this->error_result('SlickText returned HTTP ' . $response['code'] . '.');
    }

    public function send(string $phone, string $message): array
    {
        $public_key  = isset($this->config['public_key'])  ? trim((string) $this->config['public_key'])  : '';
        $private_key = isset($this->config['private_key']) ? trim((string) $this->config['private_key']) : '';

        if ($public_key === '' || $private_key === '') {
            return $this->error_result('SlickText Public Key and Private Key are required.');
        }

        $payload  = wp_json_encode(array(
            'recipient' => $phone,
            'message'   => $message,
        ));

        $response = $this->http_post(self::API_BASE . '/messages', array(
            'Authorization' => 'Basic ' . base64_encode($public_key . ':' . $private_key),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ), (string) $payload);

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $data = json_decode($response['body'], true);

        if ($response['code'] === 200 || $response['code'] === 201) {
            $msg_id = isset($data['id']) ? (string) $data['id'] : '';
            return $this->success_result('SMS sent via SlickText.', $msg_id);
        }

        $detail = isset($data['message']) ? (string) $data['message'] : substr($response['body'], 0, 200);
        return $this->error_result('SlickText send failed (HTTP ' . $response['code'] . '): ' . $detail);
    }

    public static function get_webhook_signature_key(): string
    {
        return '';
    }

    public static function verify_webhook_signature(string $payload, string $signature): bool
    {
        return true;
    }

    public static function parse_delivery_status(array $data): array
    {
        return array(
            'status'     => isset($data['status'])    ? strtolower((string) $data['status']) : '',
            'message_id' => isset($data['messageId']) ? (string) $data['messageId']          : '',
            'phone'      => isset($data['recipient']) ? (string) $data['recipient']           : '',
        );
    }
}
