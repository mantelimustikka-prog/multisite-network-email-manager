<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Brevo (formerly Sendinblue) email provider (REST API v3).
 */
class BrevoProvider extends EmailProvider
{
    public function get_provider_name(): string
    {
        return 'brevo';
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
            return $this->error_result('Brevo API key is required.');
        }

        $response = wp_remote_get(
            'https://api.brevo.com/v3/account',
            array(
                'headers' => array(
                    'api-key' => (string) $this->config('api_key'),
                    'accept'  => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            return $this->success_result('Brevo connection successful.');
        }

        return $this->error_result('Brevo returned HTTP ' . $code . '.');
    }

    public function send(string $to, string $subject, string $body, array $headers = array()): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('Brevo API key is required.');
        }

        $from_email = (string) $this->config('from_email', '');
        $from_name  = (string) $this->config('from_name', '');

        if ($from_email === '') {
            return $this->error_result('A From Email address is required for Brevo.');
        }

        $sender = array('email' => $from_email);
        if ($from_name !== '') {
            $sender['name'] = $from_name;
        }

        $payload = array(
            'sender'      => $sender,
            'to'          => array(array('email' => $to)),
            'subject'     => $subject,
            'htmlContent' => $body,
            'textContent' => wp_strip_all_tags($body),
        );

        try {
            $response = wp_remote_post(
                'https://api.brevo.com/v3/smtp/email',
                array(
                    'headers' => array(
                        'api-key'      => (string) $this->config('api_key'),
                        'Content-Type' => 'application/json',
                        'accept'       => 'application/json',
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
            $msg_id = isset($data['messageId']) ? (string) $data['messageId'] : '';

            if ($code === 201) {
                return $this->success_result('Email accepted by Brevo.', $msg_id, array('response' => $data));
            }

            return $this->error_result('Brevo returned HTTP ' . $code . '.', array('body' => $data));
        } catch (\Exception $e) {
            return $this->error_result('Exception: ' . $e->getMessage());
        }
    }
}
