<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Generic SMTP provider using PHPMailer via WordPress wp_mail().
 */
class SmtpProvider extends EmailProvider
{
    public function get_provider_name(): string
    {
        return 'smtp';
    }

    public function get_config_fields(): array
    {
        return array(
            'host'       => array('label' => 'SMTP Host',       'type' => 'text',     'required' => 'true'),
            'port'       => array('label' => 'SMTP Port',       'type' => 'number',   'required' => 'true'),
            'encryption' => array('label' => 'Encryption',      'type' => 'select',   'required' => 'true', 'options' => 'tls,ssl,none'),
            'username'   => array('label' => 'Username',        'type' => 'text',     'required' => 'false'),
            'password'   => array('label' => 'Password',        'type' => 'password', 'required' => 'false'),
        );
    }

    public function validate_config(): bool
    {
        return (string) $this->config('host') !== '';
    }

    public function test_connection(): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('SMTP host is not configured.');
        }

        return $this->success_result('SMTP configuration is present.');
    }

    public function send(string $to, string $subject, string $body, array $headers = array()): array
    {
        if (!$this->validate_config()) {
            return $this->error_result('SMTP host is not configured.');
        }

        try {
            $sent = wp_mail($to, $subject, $body, $headers);

            if ($sent) {
                return $this->success_result('Email sent via SMTP.', '', array('to' => $to));
            }

            return $this->error_result('wp_mail() returned false.');
        } catch (\Exception $e) {
            return $this->error_result('Exception: ' . $e->getMessage());
        }
    }
}
