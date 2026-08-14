<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmtpDiagnostics
{
    public const OPTION_LAST_RESULT = 'mnem_smtp_last_test_result';
    public const OPTION_RATE_LIMIT = 'mnem_smtp_test_rate_limit';

    public static function validate_settings()
    {
        $settings = SmtpSettings::get_all();
        $errors = array();

        if (empty($settings['host'])) {
            $errors[] = 'SMTP host is required.';
        }

        if (empty($settings['port']) || (int) $settings['port'] <= 0) {
            $errors[] = 'SMTP port must be a positive integer.';
        }

        if (!in_array($settings['encryption'], array('tls', 'ssl', 'none'), true)) {
            $errors[] = 'SMTP encryption must be tls, ssl, or none.';
        }

        if (!empty($settings['from_email']) && !is_email($settings['from_email'])) {
            $errors[] = 'From email format is invalid.';
        }

        return array(
            'success' => empty($errors),
            'message' => empty($errors) ? 'SMTP settings look valid.' : 'SMTP settings validation failed.',
            'details' => array(
                'errors' => $errors,
                'settings' => self::masked_settings($settings),
            ),
        );
    }

    public static function test_connection()
    {
        try {
            $validation = self::validate_settings();
            if (empty($validation['success'])) {
                self::store_result('connection', $validation);
                return $validation;
            }

            if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                $result = array(
                    'success' => false,
                    'message' => 'PHPMailer is not available.',
                    'details' => array(),
                );
                self::store_result('connection', $result);
                return $result;
            }

            $settings = SmtpSettings::get_all();
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = $settings['host'];
            $mailer->Port = (int) $settings['port'];
            $mailer->SMTPAuth = !empty($settings['username']) || SmtpSettings::get_password_decoded() !== '';
            $mailer->Username = $settings['username'];
            $mailer->Password = SmtpSettings::get_password_decoded();
            $mailer->SMTPSecure = $settings['encryption'] === 'none' ? '' : $settings['encryption'];
            $mailer->Timeout = 10;

            if (method_exists($mailer, 'smtpConnect')) {
                $mailer->smtpConnect();
            }

            if (method_exists($mailer, 'smtpClose')) {
                $mailer->smtpClose();
            }

            $result = array(
                'success' => true,
                'message' => 'SMTP connection successful.',
                'details' => array('settings' => self::masked_settings($settings)),
            );
            self::store_result('connection', $result);
            return $result;
        } catch (\Throwable $throwable) {
            $result = array(
                'success' => false,
                'message' => $throwable->getMessage(),
                'details' => array(),
            );
            self::store_result('connection', $result);
            return $result;
        }
    }

    public static function send_test_email(string $to)
    {
        try {
            $to = sanitize_email($to);
            $user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

            if (!self::check_rate_limit($user_id)) {
                $result = array(
                    'success' => false,
                    'message' => 'Rate limit exceeded. Max 5 test emails per 5 minutes.',
                    'details' => array(),
                );
                self::store_result('send_test_email', $result, $user_id);
                return $result;
            }

            if (!is_email($to)) {
                $result = array(
                    'success' => false,
                    'message' => 'Invalid email address.',
                    'details' => array(),
                );
                self::store_result('send_test_email', $result, $user_id);
                return $result;
            }

            $sent = wp_mail($to, 'MNEM SMTP Test Email', 'This is a test email from Multisite Network Email Manager.');

            $result = array(
                'success' => (bool) $sent,
                'message' => $sent ? 'Test email sent successfully.' : 'Failed to send test email.',
                'details' => array('to' => $to),
            );
            self::store_result('send_test_email', $result, $user_id);
            return $result;
        } catch (\Throwable $throwable) {
            $result = array(
                'success' => false,
                'message' => $throwable->getMessage(),
                'details' => array(),
            );
            self::store_result('send_test_email', $result);
            return $result;
        }
    }

    public static function get_last_result()
    {
        $result = get_site_option(self::OPTION_LAST_RESULT, array());

        return is_array($result) ? $result : array();
    }

    private static function store_result(string $type, array $result, int $user_id = 0)
    {
        $payload = array(
            'type' => $type,
            'timestamp' => gmdate('Y-m-d H:i:s'),
            'success' => !empty($result['success']),
            'message' => isset($result['message']) ? (string) $result['message'] : '',
            'details' => isset($result['details']) ? $result['details'] : array(),
        );

        update_site_option(self::OPTION_LAST_RESULT, $payload);
        Logger::info('SMTP diagnostics attempt logged.', array('type' => $type, 'user_id' => $user_id, 'success' => $payload['success']));
    }

    private static function check_rate_limit(int $user_id)
    {
        $key = self::OPTION_RATE_LIMIT;
        $attempts = get_site_option($key, array());
        $attempts = is_array($attempts) ? $attempts : array();

        $now = time();
        $window_start = $now - 300;
        $bucket = isset($attempts[$user_id]) && is_array($attempts[$user_id]) ? $attempts[$user_id] : array();
        $bucket = array_values(array_filter($bucket, static function ($timestamp) use ($window_start) {
            return (int) $timestamp >= $window_start;
        }));

        if (count($bucket) >= 5) {
            $attempts[$user_id] = $bucket;
            update_site_option($key, $attempts);
            return false;
        }

        $bucket[] = $now;
        $attempts[$user_id] = $bucket;
        update_site_option($key, $attempts);
        return true;
    }

    private static function masked_settings(array $settings)
    {
        $settings['password'] = $settings['password'] !== '' ? '*****' : '';
        return $settings;
    }
}
