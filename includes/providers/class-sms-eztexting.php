<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * EZTexting SMS Provider
 *
 * Requires:
 * - Username
 * - Password
 *
 * Webhook Configuration:
 * Set delivery notification URL in EZTexting account settings.
 *
 * Supported Statuses: sent, delivered, failed, bounce
 */
class SmsEztexting extends SmsBaseProvider
{
    private const API_BASE = 'https://app.eztexting.com';

    public function get_config_schema(): array
    {
        return array(
            array('key' => 'username', 'label' => 'Username', 'type' => 'text'),
            array('key' => 'password', 'label' => 'Password', 'type' => 'password'),
        );
    }

    public static function get_provider_key(): string
    {
        return 'eztexting';
    }

    public function test_connection(): array
    {
        $username = isset($this->config['username']) ? trim((string) $this->config['username']) : '';
        $password = isset($this->config['password']) ? trim((string) $this->config['password']) : '';

        if ($username === '' || $password === '') {
            return $this->error_result('EZTexting Username and Password are required.');
        }

        $response = $this->http_get(
            self::API_BASE . '/credits/get?user=' . rawurlencode($username) . '&pass=' . rawurlencode($password) . '&format=json',
            array('Accept' => 'application/json')
        );

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        if ($response['code'] === 200) {
            $data    = json_decode($response['body'], true);
            $credits = isset($data['Response']['Entry']) ? (string) $data['Response']['Entry'] : '';
            return $this->success_result('EZTexting connected successfully.' . ($credits !== '' ? ' Credits: ' . $credits : ''));
        }

        if ($response['code'] === 401) {
            return $this->error_result('EZTexting authentication failed. Check your credentials.');
        }

        return $this->error_result('EZTexting returned HTTP ' . $response['code'] . '.');
    }

    public function send(string $phone, string $message): array
    {
        $username = isset($this->config['username']) ? trim((string) $this->config['username']) : '';
        $password = isset($this->config['password']) ? trim((string) $this->config['password']) : '';

        if ($username === '' || $password === '') {
            return $this->error_result('EZTexting Username and Password are required.');
        }

        $body = http_build_query(array(
            'user'    => $username,
            'pass'    => $password,
            'phonenumber' => $phone,
            'subject' => $message,
            'format'  => 'json',
        ));

        $response = $this->http_post(
            self::API_BASE . '/sending/messages',
            array('Content-Type' => 'application/x-www-form-urlencoded'),
            $body
        );

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $data = json_decode($response['body'], true);

        if ($response['code'] === 200 || $response['code'] === 201) {
            $msg_id = isset($data['Response']['ID']) ? (string) $data['Response']['ID'] : '';
            return $this->success_result('SMS sent via EZTexting.', $msg_id);
        }

        $detail = isset($data['Response']['Errors'][0]) ? (string) $data['Response']['Errors'][0] : substr($response['body'], 0, 200);
        return $this->error_result('EZTexting send failed (HTTP ' . $response['code'] . '): ' . $detail);
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
            'status'     => isset($data['status'])     ? strtolower((string) $data['status']) : '',
            'message_id' => isset($data['message_id']) ? (string) $data['message_id']         : '',
            'phone'      => isset($data['to'])         ? (string) $data['to']                 : '',
        );
    }
}
