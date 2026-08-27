<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * TextMagic SMS Provider
 *
 * Requires:
 * - Username
 * - API Key
 *
 * Webhook Configuration:
 * Set the callback URL in TextMagic Account > API Settings.
 *
 * Supported Statuses: sent, delivered, failed
 */
class SmsTextmagic extends SmsBaseProvider
{
    private const API_BASE = 'https://rest.textmagic.com/api/v2';

    public function get_config_schema(): array
    {
        return array(
            array('key' => 'username',  'label' => 'Username',  'type' => 'text'),
            array('key' => 'api_key',   'label' => 'API Key',   'type' => 'password'),
            array('key' => 'sender_id', 'label' => 'Sender ID', 'type' => 'text', 'maxlength' => 1),
        );
    }

    public static function get_provider_key(): string
    {
        return 'textmagic';
    }

    public function test_connection(): array
    {
        $username = isset($this->config['username']) ? trim((string) $this->config['username']) : '';
        $api_key  = isset($this->config['api_key'])  ? trim((string) $this->config['api_key'])  : '';

        if ($username === '' || $api_key === '') {
            return $this->error_result('TextMagic Username and API Key are required.');
        }

        $response = $this->http_get(self::API_BASE . '/user', array(
            'X-TM-Username' => $username,
            'X-TM-Key'      => $api_key,
            'Accept'        => 'application/json',
        ));

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        if ($response['code'] === 200) {
            $data       = json_decode($response['body'], true);
            $first_name = isset($data['firstName']) ? (string) $data['firstName'] : '';
            return $this->success_result('TextMagic connected successfully.' . ($first_name !== '' ? ' Account: ' . $first_name : ''));
        }

        if ($response['code'] === 401) {
            return $this->error_result('TextMagic authentication failed. Check your credentials.');
        }

        return $this->error_result('TextMagic returned HTTP ' . $response['code'] . '.');
    }

    public function send(string $phone, string $message): array
    {
        $username  = isset($this->config['username'])  ? trim((string) $this->config['username'])  : '';
        $api_key   = isset($this->config['api_key'])   ? trim((string) $this->config['api_key'])   : '';
        $sender_id = isset($this->config['sender_id']) ? trim((string) $this->config['sender_id']) : '';

        if ($username === '' || $api_key === '') {
            return $this->error_result('TextMagic Username and API Key are required.');
        }

        $body = array(
            'text'   => $message,
            'phones' => $phone,
        );

        if ($sender_id !== '') {
            $body['from'] = $sender_id;
        }

        $payload  = wp_json_encode($body);

        $response = $this->http_post(self::API_BASE . '/messages', array(
            'X-TM-Username' => $username,
            'X-TM-Key'      => $api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ), (string) $payload);

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $data = json_decode($response['body'], true);

        if ($response['code'] === 201) {
            $msg_id = isset($data['id']) ? (string) $data['id'] : '';
            return $this->success_result('SMS sent via TextMagic.', $msg_id);
        }

        $detail = isset($data['message']) ? (string) $data['message'] : substr($response['body'], 0, 200);
        return $this->error_result('TextMagic send failed (HTTP ' . $response['code'] . '): ' . $detail);
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
            'status'     => isset($data['status'])    ? (string) $data['status']    : '',
            'message_id' => isset($data['id'])        ? (string) $data['id']        : '',
            'phone'      => isset($data['receiver'])  ? (string) $data['receiver']  : '',
        );
    }
}
