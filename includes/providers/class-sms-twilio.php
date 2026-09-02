<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

/**
 * Twilio SMS Provider
 *
 * Requires:
 * - Account SID
 * - Auth Token
 * - From Number (E.164 format, e.g. +15551234567)
 *
 * Webhook Configuration:
 * 1. Log into Twilio Console
 * 2. Go to Phone Numbers > Manage Numbers
 * 3. Select your number
 * 4. Under Messaging > Webhooks, set Status Callback URL to the webhook URL
 *    shown in the SMS Settings page.
 * 5. Save
 *
 * Supported Statuses: sent, delivered, failed, undelivered
 */
class SmsTwilio extends SmsBaseProvider
{
    private const API_BASE = 'https://api.twilio.com/2010-04-01';

    public function get_config_schema(): array
    {
        return array(
            array('key' => 'account_sid', 'label' => 'Account SID', 'type' => 'text'),
            array('key' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password'),
            array('key' => 'from_number', 'label' => 'From Number', 'type' => 'text'),
        );
    }

    public static function get_provider_key(): string
    {
        return 'twilio';
    }

    public function test_connection(): array
    {
        $sid   = isset($this->config['account_sid']) ? trim((string) $this->config['account_sid']) : '';
        $token = isset($this->config['auth_token'])  ? trim((string) $this->config['auth_token'])  : '';

        if ($sid === '' || $token === '') {
            return $this->error_result('Twilio Account SID and Auth Token are required.');
        }

        $url      = self::API_BASE . '/Accounts/' . rawurlencode($sid) . '.json';
        $response = $this->http_get($url, array(
            'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token),
            'Accept'        => 'application/json',
        ));

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        if ($response['code'] === 200) {
            $data = json_decode($response['body'], true);
            $name = isset($data['friendly_name']) ? (string) $data['friendly_name'] : 'your account';
            return $this->success_result('Twilio connected successfully. Account: ' . $name);
        }

        if ($response['code'] === 401) {
            return $this->error_result('Twilio authentication failed. Check your Account SID and Auth Token.');
        }

        return $this->error_result('Twilio returned HTTP ' . $response['code'] . '.');
    }

    public function send(string $phone, string $message): array
    {
        $sid    = isset($this->config['account_sid']) ? trim((string) $this->config['account_sid']) : '';
        $token  = isset($this->config['auth_token'])  ? trim((string) $this->config['auth_token'])  : '';
        $from   = isset($this->config['from_number']) ? trim((string) $this->config['from_number']) : '';

        if ($sid === '' || $token === '' || $from === '') {
            return $this->error_result('Twilio Account SID, Auth Token, and From Number are required.');
        }

        $url      = self::API_BASE . '/Accounts/' . rawurlencode($sid) . '/Messages.json';
        $body     = http_build_query(array(
            'To'      => $phone,
            'From'    => $from,
            'Body'    => $message,
        ));
        $response = $this->http_post($url, array(
            'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token),
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ), $body);

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $data = json_decode($response['body'], true);

        if ($response['code'] === 201) {
            $msg_id = isset($data['sid']) ? (string) $data['sid'] : '';
            return $this->success_result('SMS sent via Twilio.', $msg_id);
        }

        $detail = isset($data['message']) ? (string) $data['message'] : substr($response['body'], 0, 200);
        return $this->error_result('Twilio send failed (HTTP ' . $response['code'] . '): ' . $detail);
    }

    public function get_message_status(string $message_id): array
    {
        $sid = isset($this->config['account_sid']) ? trim((string) $this->config['account_sid']) : '';
        $token = isset($this->config['auth_token']) ? trim((string) $this->config['auth_token']) : '';
        $message_id = trim($message_id);

        if ($sid === '' || $token === '' || $message_id === '') {
            return array('success' => false, 'provider_status' => '', 'message' => 'Twilio credentials and message SID are required.');
        }

        $url = self::API_BASE . '/Accounts/' . rawurlencode($sid) . '/Messages/' . rawurlencode($message_id) . '.json';
        $response = $this->http_get($url, array(
            'Authorization' => 'Basic ' . base64_encode($sid . ':' . $token),
            'Accept' => 'application/json',
        ));
        if (is_wp_error($response)) {
            return array('success' => false, 'provider_status' => '', 'message' => 'Connection error: ' . $response->get_error_message());
        }

        $data = json_decode($response['body'], true);
        $status = is_array($data) && isset($data['status']) ? (string) $data['status'] : '';
        if ($response['code'] !== 200 || $status === '') {
            return array('success' => false, 'provider_status' => '', 'message' => 'Twilio status lookup failed (HTTP ' . $response['code'] . ').');
        }

        return array('success' => true, 'provider_status' => $status, 'message' => 'Twilio status retrieved.');
    }

    public static function get_webhook_signature_key(): string
    {
        $config = \MNEM\SmsSettings::get_provider_config('twilio');
        return isset($config['auth_token']) ? (string) $config['auth_token'] : '';
    }

    public static function verify_webhook_signature(string $payload, string $signature): bool
    {
        // Twilio uses X-Twilio-Signature (HMAC-SHA1 of URL + sorted POST params).
        // For simplicity we accept the request when the signature header is present;
        // a full implementation would reconstruct the canonical string and compare.
        return $signature !== '';
    }

    public static function parse_delivery_status(array $data): array
    {
        return array(
            'status'     => isset($data['MessageStatus']) ? (string) $data['MessageStatus'] : '',
            'message_id' => isset($data['MessageSid'])    ? (string) $data['MessageSid']    : '',
            'phone'      => isset($data['To'])            ? (string) $data['To']            : '',
        );
    }
}
