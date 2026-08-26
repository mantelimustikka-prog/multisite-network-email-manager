<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * ClickSend SMS Provider
 *
 * Requires:
 * - Username
 * - API Key
 *
 * Webhook Configuration:
 * 1. Log into ClickSend dashboard
 * 2. Go to Account > Subaccounts & API
 * 3. Set delivery receipt URL to the webhook URL shown in SMS Settings.
 *
 * Supported Statuses: submitted, delivered, failed, bounce
 */
class SmsClicksend extends SmsBaseProvider
{
    private const API_BASE = 'https://rest.clicksend.com/v3';

    public function get_config_schema(): array
    {
        return array(
            array('key' => 'username', 'label' => 'Username', 'type' => 'text'),
            array('key' => 'api_key',  'label' => 'API Key',  'type' => 'password'),
        );
    }

    public static function get_provider_key(): string
    {
        return 'clicksend';
    }

    public function test_connection(): array
    {
        $username = isset($this->config['username']) ? trim((string) $this->config['username']) : '';
        $api_key  = isset($this->config['api_key'])  ? trim((string) $this->config['api_key'])  : '';

        if ($username === '' || $api_key === '') {
            return $this->error_result('ClickSend Username and API Key are required.');
        }

        $response = $this->http_get(self::API_BASE . '/account', array(
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key),
            'Accept'        => 'application/json',
        ));

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        if ($response['code'] === 200) {
            $data  = json_decode($response['body'], true);
            $email = isset($data['data']['email']) ? (string) $data['data']['email'] : '';
            return $this->success_result('ClickSend connected successfully.' . ($email !== '' ? ' Account: ' . $email : ''));
        }

        if ($response['code'] === 401) {
            return $this->error_result('ClickSend authentication failed. Check your credentials.');
        }

        return $this->error_result('ClickSend returned HTTP ' . $response['code'] . '.');
    }

    public function send(string $phone, string $message): array
    {
        $username = isset($this->config['username']) ? trim((string) $this->config['username']) : '';
        $api_key  = isset($this->config['api_key'])  ? trim((string) $this->config['api_key'])  : '';

        if ($username === '' || $api_key === '') {
            return $this->error_result('ClickSend Username and API Key are required.');
        }

        $payload  = wp_json_encode(array(
            'messages' => array(
                array(
                    'source' => 'wordpress',
                    'body'   => $message,
                    'to'     => $phone,
                ),
            ),
        ));

        $response = $this->http_post(self::API_BASE . '/sms/send', array(
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $api_key),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ), (string) $payload);

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $data = json_decode($response['body'], true);

        if ($response['code'] === 200) {
            $msg_id = isset($data['data']['messages'][0]['message_id']) ? (string) $data['data']['messages'][0]['message_id'] : '';
            return $this->success_result('SMS sent via ClickSend.', $msg_id);
        }

        $detail = isset($data['response_msg']) ? (string) $data['response_msg'] : substr($response['body'], 0, 200);
        return $this->error_result('ClickSend send failed (HTTP ' . $response['code'] . '): ' . $detail);
    }

    public static function get_webhook_signature_key(): string
    {
        return '';
    }

    public static function verify_webhook_signature(string $payload, string $signature): bool
    {
        // ClickSend does not use a signature; rely on HTTPS.
        return true;
    }

    public static function parse_delivery_status(array $data): array
    {
        return array(
            'status'     => isset($data['status'])     ? (string) $data['status']     : '',
            'message_id' => isset($data['message_id']) ? (string) $data['message_id'] : '',
            'phone'      => isset($data['to'])         ? (string) $data['to']         : '',
        );
    }
}
