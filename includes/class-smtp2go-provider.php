<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * SMTP2GO email provider (REST API v3).
 */
class Smtp2goProvider extends EmailProvider
{
    public function get_provider_name(): string
    {
        return 'smtp2go';
    }

    public function get_config_fields(): array
    {
        return array(
            'api_key' => array('label' => 'API Key', 'type' => 'password', 'required' => 'true'),
        );
    }

    public function validate_config(): bool
    {
        return (string) $this->config('api_key') !== '';
    }

    public function test_connection(): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('SMTP2GO API key is required.');
        }

        $payload = array('api_key' => (string) $this->config('api_key'));

        $response = wp_remote_post(
            'https://api.smtp2go.com/v3/apikey/view',
            array(
                'headers' => array('Content-Type' => 'application/json'),
                'body'    => wp_json_encode($payload),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code === 200 && isset($data['data']['results'])) {
            return $this->success_result('SMTP2GO connection successful.');
        }

        return $this->error_result('SMTP2GO returned HTTP ' . $code . '.');
    }

    public function send(string $to, string $subject, string $body, array $headers = array()): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('SMTP2GO API key is required.');
        }

        $from_email = (string) $this->config('from_email', '');
        $from_name  = (string) $this->config('from_name', '');

        if ($from_email === '') {
            return $this->error_result('A From Email address is required for SMTP2GO.');
        }

        $from = $from_name !== '' ? "{$from_name} <{$from_email}>" : $from_email;

        $payload = array(
            'api_key'   => (string) $this->config('api_key'),
            'to'        => array($to),
            'sender'    => $from,
            'subject'   => $subject,
            'html_body' => $body,
            'text_body' => wp_strip_all_tags($body),
        );

        try {
            $response = wp_remote_post(
                'https://api.smtp2go.com/v3/email/send',
                array(
                    'headers' => array('Content-Type' => 'application/json'),
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
            $request_id = isset($data['data']['request_id']) ? (string) $data['data']['request_id'] : '';

            if ($code === 200 && isset($data['data']['succeeded']) && (int) $data['data']['succeeded'] > 0) {
                return $this->success_result('Email accepted by SMTP2GO.', $request_id, array('response' => $data));
            }

            $http_labels = array(
                401 => 'Unauthorized - Check your API key',
                403 => 'Forbidden - API key lacks permissions',
            );
            $http_label = isset($http_labels[$code]) ? $http_labels[$code] : '';
            $provider_detail = isset($data['data']['error']) ? $data['data']['error'] : substr($raw, 0, 200);
            $error_msg = 'SMTP2GO returned HTTP ' . $code . ($http_label !== '' ? ' ' . $http_label : '') . '. ' . $provider_detail;

            Logger::error('SMTP2GO send failed', array(
                'recipient'      => $to,
                'http_code'      => $code,
                'response_body'  => substr($raw, 0, 500),
                'api_key_length' => strlen((string) $this->config('api_key')),
            ));

            return $this->error_result($error_msg, array('http_code' => $code, 'body' => $data));
        } catch (\Exception $e) {
            return $this->error_result('Exception: ' . $e->getMessage());
        }
    }
}
