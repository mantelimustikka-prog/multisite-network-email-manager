<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Postmark email provider (REST API).
 */
class PostmarkProvider extends EmailProvider
{
    public function get_provider_name(): string
    {
        return 'postmark';
    }

    public function get_config_fields(): array
    {
        return array(
            'server_token' => array('label' => 'Server Token', 'type' => 'password', 'required' => 'true'),
        );
    }

    public function validate_config(): bool
    {
        return (string) $this->config('server_token') !== '';
    }

    public function test_connection(): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('Postmark Server Token is required.');
        }

        $response = wp_remote_get(
            'https://api.postmarkapp.com/server',
            array(
                'headers' => array(
                    'X-Postmark-Server-Token' => (string) $this->config('server_token'),
                    'Accept'                  => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            return $this->success_result('Postmark connection successful.');
        }

        return $this->error_result('Postmark returned HTTP ' . $code . '.');
    }

    public function send(string $to, string $subject, string $body, array $headers = array()): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('Postmark Server Token is required.');
        }

        $from_email = (string) $this->config('from_email', '');
        $from_name  = (string) $this->config('from_name', '');

        if ($from_email === '') {
            return $this->error_result('A From Email address is required for Postmark.');
        }

        $from = $from_name !== '' ? "{$from_name} <{$from_email}>" : $from_email;

        $payload = array(
            'From'     => $from,
            'To'       => $to,
            'Subject'  => $subject,
            'HtmlBody' => $body,
            'TextBody' => wp_strip_all_tags($body),
        );

        try {
            $response = wp_remote_post(
                'https://api.postmarkapp.com/email',
                array(
                    'headers' => array(
                        'X-Postmark-Server-Token' => (string) $this->config('server_token'),
                        'Content-Type'            => 'application/json',
                        'Accept'                  => 'application/json',
                    ),
                    'body'    => wp_json_encode($payload),
                    'timeout' => 30,
                )
            );

            if (is_wp_error($response)) {
                return $this->error_result('HTTP error: ' . $response->get_error_message());
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $raw  = (string) wp_remote_retrieve_body($response);
            $data = json_decode($raw, true);
            $msg_id = isset($data['MessageID']) ? (string) $data['MessageID'] : '';

            if ($code === 200) {
                return $this->success_result('Email accepted by Postmark.', $msg_id, array('response' => $data));
            }

            $http_labels = array(
                401 => 'Unauthorized - Check your Server Token',
                403 => 'Forbidden - Sender signature not found or inactive',
                422 => 'Unprocessable Entity - Check sender email is a verified Signature',
            );
            $http_label = isset($http_labels[$code]) ? $http_labels[$code] : '';
            $provider_detail = isset($data['Message']) ? $data['Message'] : substr($raw, 0, 200);
            $error_msg = 'Postmark returned HTTP ' . $code . ($http_label !== '' ? ' ' . $http_label : '') . '. ' . $provider_detail;

            Logger::error('Postmark send failed', array(
                'recipient'          => $to,
                'http_code'          => $code,
                'response_body'      => substr($raw, 0, 500),
                'server_token_length' => strlen((string) $this->config('server_token')),
            ));

            return $this->error_result($error_msg, array('http_code' => $code, 'body' => $data));
        } catch (\Exception $e) {
            return $this->error_result('Exception: ' . $e->getMessage());
        }
    }
}
