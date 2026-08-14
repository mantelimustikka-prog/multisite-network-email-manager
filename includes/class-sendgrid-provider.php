<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * SendGrid email provider (REST API v3).
 */
class SendgridProvider extends EmailProvider
{
    public function get_provider_name(): string
    {
        return 'sendgrid';
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
            return $this->error_result('SendGrid API key is required.');
        }

        $response = wp_remote_get(
            'https://api.sendgrid.com/v3/user/profile',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . (string) $this->config('api_key'),
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return $this->error_result('Connection error: ' . $response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code === 200) {
            return $this->success_result('SendGrid connection successful.');
        }

        return $this->error_result('SendGrid returned HTTP ' . $code . '.');
    }

    public function send(string $to, string $subject, string $body, array $headers = array()): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('SendGrid API key is required.');
        }

        $from_email = (string) $this->config('from_email', '');
        $from_name  = (string) $this->config('from_name', '');

        if ($from_email === '') {
            return $this->error_result('A From Email address is required for SendGrid.');
        }

        $from_obj = array('email' => $from_email);
        if ($from_name !== '') {
            $from_obj['name'] = $from_name;
        }

        $payload = array(
            'personalizations' => array(
                array(
                    'to' => array(array('email' => $to)),
                ),
            ),
            'from'    => $from_obj,
            'subject' => $subject,
            'content' => array(
                array('type' => 'text/plain', 'value' => wp_strip_all_tags($body)),
                array('type' => 'text/html',  'value' => $body),
            ),
        );

        try {
            $response = wp_remote_post(
                'https://api.sendgrid.com/v3/mail/send',
                array(
                    'headers' => array(
                        'Authorization' => 'Bearer ' . (string) $this->config('api_key'),
                        'Content-Type'  => 'application/json',
                    ),
                    'body'    => wp_json_encode($payload),
                    'timeout' => 30,
                )
            );

            if (is_wp_error($response)) {
                return $this->error_result('HTTP error: ' . $response->get_error_message());
            }

            $code   = (int) wp_remote_retrieve_response_code($response);
            $msg_id = (string) wp_remote_retrieve_header($response, 'x-message-id');

            if ($code === 202) {
                return $this->success_result('Email accepted by SendGrid.', $msg_id);
            }

            $raw  = (string) wp_remote_retrieve_body($response);
            $data = json_decode($raw, true);

            return $this->error_result('SendGrid returned HTTP ' . $code . '.', array('body' => $data));
        } catch (\Exception $e) {
            return $this->error_result('Exception: ' . $e->getMessage());
        }
    }
}
