<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * MessageDesk SMS Provider
 *
 * Requires:
 * - API Key
 *
 * Webhook Configuration:
 * Configure delivery webhook URL in MessageDesk dashboard under Integrations > Webhooks.
 *
 * Supported Statuses: sent, delivered, failed
 */
class SmsMessagedesk extends SmsBaseProvider
{
    private const API_BASE = 'https://api.messagedesk.com/v1';

    public function get_config_schema(): array
    {
        return array(
            array('key' => 'api_key', 'label' => 'API Key', 'type' => 'password'),
        );
    }

    public static function get_provider_key(): string
    {
        return 'messagedesk';
    }

    public function test_connection(): array
    {
        $api_key = isset($this->config['api_key']) ? trim((string) $this->config['api_key']) : '';

        if ($api_key === '') {
            return $this->error_result('MessageDesk API Key is required.');
        }

        $response = $this->http_get(self::API_BASE . '/account', array(
            'Authorization' => 'Bearer ' . $api_key,
            'Accept'        => 'application/json',
        ));

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        if ($response['code'] === 200) {
            return $this->success_result('MessageDesk connected successfully.');
        }

        if ($response['code'] === 401) {
            return $this->error_result('MessageDesk authentication failed. Check your API Key.');
        }

        return $this->error_result('MessageDesk returned HTTP ' . $response['code'] . '.');
    }

    public function send(string $phone, string $message): array
    {
        $api_key = isset($this->config['api_key']) ? trim((string) $this->config['api_key']) : '';

        if ($api_key === '') {
            return $this->error_result('MessageDesk API Key is required.');
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
            return $this->success_result('SMS sent via MessageDesk.', $msg_id);
        }

        $detail = isset($data['message']) ? (string) $data['message'] : substr($response['body'], 0, 200);
        return $this->error_result('MessageDesk send failed (HTTP ' . $response['code'] . '): ' . $detail);
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
