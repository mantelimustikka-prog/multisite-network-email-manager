<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Mailgun email provider (REST API).
 */
class MailgunProvider extends EmailProvider
{
    public function get_provider_name(): string
    {
        return 'mailgun';
    }

    public function get_config_fields(): array
    {
        return array(
            'api_key' => array('label' => 'API Key',    'type' => 'password', 'required' => 'true'),
            'domain'  => array('label' => 'Domain',     'type' => 'text',     'required' => 'true'),
        );
    }

    public function validate_config(): bool
    {
        return (string) $this->config('api_key') !== '' && (string) $this->config('domain') !== '';
    }

    public function test_connection(): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('Mailgun API key and domain are required.');
        }

        $domain  = (string) $this->config('domain');
        $api_key = (string) $this->config('api_key');
        $url     = 'https://api.mailgun.net/v3/' . rawurlencode($domain);

        $response = wp_remote_get(
            $url,
            array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode('api:' . $api_key),
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            return $this->success_result('Mailgun connection successful.');
        }

        return $this->error_result('Mailgun returned HTTP ' . $code . '.');
    }

    public function send(string $to, string $subject, string $body, array $headers = array()): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('Mailgun API key and domain are required.');
        }

        $domain     = (string) $this->config('domain');
        $api_key    = (string) $this->config('api_key');
        $from_email = (string) $this->config('from_email', '');
        $from_name  = (string) $this->config('from_name', '');
        $from       = $from_email !== '' ? ($from_name !== '' ? "{$from_name} <{$from_email}>" : $from_email) : "no-reply@{$domain}";

        $url = 'https://api.mailgun.net/v3/' . rawurlencode($domain) . '/messages';

        try {
            $response = wp_remote_post(
                $url,
                array(
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode('api:' . $api_key),
                    ),
                    'body'    => array(
                        'from'    => $from,
                        'to'      => $to,
                        'subject' => $subject,
                        'text'    => wp_strip_all_tags($body),
                        'html'    => $body,
                    ),
                    'timeout' => 30,
                )
            );

            if (is_wp_error($response)) {
                return $this->error_result('HTTP error: ' . $response->get_error_message());
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            $raw  = (string) wp_remote_retrieve_body($response);
            $data = json_decode($raw, true);
            $msg_id = isset($data['id']) ? (string) $data['id'] : '';

            if ($code === 200) {
                return $this->success_result('Email accepted by Mailgun.', $msg_id, array('response' => $data));
            }

            return $this->error_result('Mailgun returned HTTP ' . $code . ': ' . (isset($data['message']) ? $data['message'] : $raw), array('http_code' => $code));
        } catch (\Exception $e) {
            return $this->error_result('Exception: ' . $e->getMessage());
        }
    }
}
