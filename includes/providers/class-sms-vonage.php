<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * Vonage (Nexmo) SMS Provider
 *
 * Requires:
 * - API Key
 * - API Secret
 * - From (sender name or number)
 *
 * Webhook Configuration:
 * 1. Log into Vonage Dashboard
 * 2. Go to Settings > Default SMS Settings
 * 3. Set Delivery Receipts URL to the webhook URL shown in SMS Settings.
 *
 * Supported Statuses: submitted, delivered, expired, failed, rejected, unknown
 */
class SmsVonage extends SmsBaseProvider
{
    private const API_BASE = 'https://rest.nexmo.com';

    public function get_config_schema(): array
    {
        return array(
            array('key' => 'api_key',    'label' => 'API Key',    'type' => 'text'),
            array('key' => 'api_secret', 'label' => 'API Secret', 'type' => 'password'),
            array('key' => 'from',       'label' => 'From',       'type' => 'text'),
        );
    }

    public static function get_provider_key(): string
    {
        return 'vonage';
    }

    public function test_connection(): array
    {
        $api_key    = isset($this->config['api_key'])    ? trim((string) $this->config['api_key'])    : '';
        $api_secret = isset($this->config['api_secret']) ? trim((string) $this->config['api_secret']) : '';

        if ($api_key === '' || $api_secret === '') {
            return $this->error_result('Vonage API Key and API Secret are required.');
        }

        $url = self::API_BASE . '/account/get-balance?api_key=' . rawurlencode($api_key) . '&api_secret=' . rawurlencode($api_secret);

        $response = $this->http_get($url, array('Accept' => 'application/json'));

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        if ($response['code'] === 200) {
            $data    = json_decode($response['body'], true);
            $balance = isset($data['value']) ? (string) $data['value'] : '';
            return $this->success_result('Vonage connected successfully.' . ($balance !== '' ? ' Balance: ' . $balance : ''));
        }

        if ($response['code'] === 401) {
            return $this->error_result('Vonage authentication failed. Check your API Key and Secret.');
        }

        return $this->error_result('Vonage returned HTTP ' . $response['code'] . '.');
    }

    public function send(string $phone, string $message): array
    {
        $api_key    = isset($this->config['api_key'])    ? trim((string) $this->config['api_key'])    : '';
        $api_secret = isset($this->config['api_secret']) ? trim((string) $this->config['api_secret']) : '';
        $from       = isset($this->config['from'])       ? trim((string) $this->config['from'])       : '';

        if ($api_key === '' || $api_secret === '') {
            return $this->error_result('Vonage API Key and API Secret are required.');
        }

        $payload = wp_json_encode(array(
            'from'       => $from !== '' ? $from : 'Vonage',
            'to'         => ltrim($phone, '+'),
            'text'       => $message,
            'api_key'    => $api_key,
            'api_secret' => $api_secret,
        ));

        $response = $this->http_post(self::API_BASE . '/sms/json', array(
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ), (string) $payload);

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $data = json_decode($response['body'], true);

        if ($response['code'] === 200) {
            $msg    = isset($data['messages'][0]) ? $data['messages'][0] : array();
            $status = isset($msg['status']) ? (string) $msg['status'] : '';
            if ($status === '0') {
                $msg_id = isset($msg['message-id']) ? (string) $msg['message-id'] : '';
                return $this->success_result('SMS sent via Vonage.', $msg_id);
            }
            $error_text = isset($msg['error-text']) ? (string) $msg['error-text'] : 'Unknown error';
            return $this->error_result('Vonage send failed: ' . $error_text);
        }

        return $this->error_result('Vonage returned HTTP ' . $response['code'] . '.');
    }

    public static function get_webhook_signature_key(): string
    {
        $config = \MNEM\SmsSettings::get_provider_config('vonage');
        return isset($config['api_secret']) ? (string) $config['api_secret'] : '';
    }

    public static function verify_webhook_signature(string $payload, string $signature): bool
    {
        // Vonage signs webhooks with HMAC-SHA256 of the payload.
        $key = self::get_webhook_signature_key();
        if ($key === '' || $signature === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $payload, $key);
        return hash_equals($expected, $signature);
    }

    public static function parse_delivery_status(array $data): array
    {
        return array(
            'status'     => isset($data['status'])     ? strtolower((string) $data['status']) : '',
            'message_id' => isset($data['messageId'])  ? (string) $data['messageId']          : '',
            'phone'      => isset($data['to'])         ? (string) $data['to']                 : '',
        );
    }
}
