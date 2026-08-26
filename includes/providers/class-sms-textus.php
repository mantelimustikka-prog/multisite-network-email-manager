<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * TextUs SMS Provider
 *
 * Requires:
 * - API Key
 *
 * Webhook Configuration:
 * Set the delivery webhook URL in TextUs dashboard under Account > API.
 *
 * Supported Statuses: sent, delivered, failed
 */
class SmsTextus extends SmsBaseProvider
{
    private const API_BASE = 'https://api.textus.com/v3';

    public function get_config_schema(): array
    {
        return array(
            array('key' => 'api_key', 'label' => 'API Key', 'type' => 'password'),
        );
    }

    public static function get_provider_key(): string
    {
        return 'textus';
    }

    public function test_connection(): array
    {
        $api_key = isset($this->config['api_key']) ? trim((string) $this->config['api_key']) : '';

        if ($api_key === '') {
            return $this->error_result('TextUs API Key is required.');
        }

        $response = $this->http_get(self::API_BASE . '/users/me', array(
            'Authorization' => 'Bearer ' . $api_key,
            'Accept'        => 'application/json',
        ));

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        if ($response['code'] === 200) {
            $data  = json_decode($response['body'], true);
            $email = isset($data['email']) ? (string) $data['email'] : '';
            return $this->success_result('TextUs connected successfully.' . ($email !== '' ? ' Account: ' . $email : ''));
        }

        if ($response['code'] === 401) {
            return $this->error_result('TextUs authentication failed. Check your API Key.');
        }

        return $this->error_result('TextUs returned HTTP ' . $response['code'] . '.');
    }

    public function send(string $phone, string $message): array
    {
        $api_key = isset($this->config['api_key']) ? trim((string) $this->config['api_key']) : '';

        if ($api_key === '') {
            return $this->error_result('TextUs API Key is required.');
        }

        $payload  = wp_json_encode(array(
            'to'   => $phone,
            'body' => $message,
        ));

        $response = $this->http_post(self::API_BASE . '/messages', array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ), (string) $payload);

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $data = json_decode($response['body'], true);

        if ($response['code'] === 200 || $response['code'] === 201) {
            $msg_id = isset($data['id']) ? (string) $data['id'] : '';
            return $this->success_result('SMS sent via TextUs.', $msg_id);
        }

        $detail = isset($data['message']) ? (string) $data['message'] : substr($response['body'], 0, 200);
        return $this->error_result('TextUs send failed (HTTP ' . $response['code'] . '): ' . $detail);
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
            'status'     => isset($data['status']) ? (string) $data['status'] : '',
            'message_id' => isset($data['id'])     ? (string) $data['id']     : '',
            'phone'      => isset($data['to'])     ? (string) $data['to']     : '',
        );
    }
}
