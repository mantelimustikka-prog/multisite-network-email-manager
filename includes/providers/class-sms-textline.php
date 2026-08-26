<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * Textline SMS Provider
 *
 * Requires:
 * - API Key
 *
 * Webhook Configuration:
 * Add the webhook URL in Textline dashboard under Account > Integrations > Webhooks.
 *
 * Supported Statuses: sent, delivered, failed
 */
class SmsTextline extends SmsBaseProvider
{
    private const API_BASE = 'https://application.textline.com/api';

    public function get_config_schema(): array
    {
        return array(
            array('key' => 'api_key', 'label' => 'API Key', 'type' => 'password'),
        );
    }

    public static function get_provider_key(): string
    {
        return 'textline';
    }

    public function test_connection(): array
    {
        $api_key = isset($this->config['api_key']) ? trim((string) $this->config['api_key']) : '';

        if ($api_key === '') {
            return $this->error_result('Textline API Key is required.');
        }

        $response = $this->http_get(self::API_BASE . '/account.json', array(
            'X-Api-Token' => $api_key,
            'Accept'      => 'application/json',
        ));

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        if ($response['code'] === 200) {
            return $this->success_result('Textline connected successfully.');
        }

        if ($response['code'] === 401) {
            return $this->error_result('Textline authentication failed. Check your API Key.');
        }

        return $this->error_result('Textline returned HTTP ' . $response['code'] . '.');
    }

    public function send(string $phone, string $message): array
    {
        $api_key = isset($this->config['api_key']) ? trim((string) $this->config['api_key']) : '';

        if ($api_key === '') {
            return $this->error_result('Textline API Key is required.');
        }

        $payload  = wp_json_encode(array(
            'conversation' => array(
                'phone_number' => $phone,
                'comment'      => array('body' => $message),
            ),
        ));

        $response = $this->http_post(self::API_BASE . '/conversations.json', array(
            'X-Api-Token'  => $api_key,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ), (string) $payload);

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $data = json_decode($response['body'], true);

        if ($response['code'] === 200 || $response['code'] === 201) {
            $msg_id = isset($data['uuid']) ? (string) $data['uuid'] : '';
            return $this->success_result('SMS sent via Textline.', $msg_id);
        }

        $detail = isset($data['error']) ? (string) $data['error'] : substr($response['body'], 0, 200);
        return $this->error_result('Textline send failed (HTTP ' . $response['code'] . '): ' . $detail);
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
        $status = '';
        if (isset($data['type'])) {
            $type_map = array('message.sent' => 'sent', 'message.delivered' => 'delivered', 'message.failed' => 'failed');
            $status   = isset($type_map[(string) $data['type']]) ? $type_map[(string) $data['type']] : '';
        }
        return array(
            'status'     => $status,
            'message_id' => isset($data['uuid']) ? (string) $data['uuid'] : '',
            'phone'      => isset($data['phone_number']) ? (string) $data['phone_number'] : '',
        );
    }
}
