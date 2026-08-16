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
            $settings      = SmtpSettings::get_all();
            $provider_type = isset($settings['provider_type']) ? (string) $settings['provider_type'] : 'smtp';

            Logger::info('Testing provider connection.', array('provider' => $provider_type));

            // For API-based providers, delegate to the provider's own test_connection().
            if ($provider_type !== 'smtp') {
                if (!SmtpSettings::is_active_provider_configured()) {
                    $result = array(
                        'success' => false,
                        'message' => ucfirst($provider_type) . ' is not fully configured. Please save your API key.',
                        'details' => array('provider' => $provider_type),
                    );
                    self::store_result('connection', $result);
                    return $result;
                }

                $provider = ProviderManager::get_provider($provider_type);
                if ($provider === null) {
                    $result = array(
                        'success' => false,
                        'message' => 'Unknown provider type: ' . $provider_type,
                        'details' => array('provider' => $provider_type),
                    );
                    self::store_result('connection', $result);
                    return $result;
                }

                $result = $provider->test_connection();
                $result['details'] = array_merge(
                    isset($result['details']) && is_array($result['details']) ? $result['details'] : array(),
                    array('provider' => $provider_type)
                );
                self::store_result('connection', $result);
                Logger::info('Provider connection test result.', array('provider' => $provider_type, 'success' => !empty($result['success']), 'message' => isset($result['message']) ? $result['message'] : ''));
                return $result;
            }

            // SMTP: validate settings first.
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

            $smtp_settings = SmtpSettings::get_all();
            $from_email = '';
            $from_name = '';
            if (SmtpSettings::is_force_sender_enabled()) {
                $from_email = SmtpSettings::get_sender_email();
                $from_name = SmtpSettings::get_sender_name();
                if ($from_email === '') {
                    $result = array(
                        'success' => false,
                        'message' => 'Force sender is enabled but sender email is not configured. Please configure it in Settings > Sender Settings.',
                        'details' => array(),
                    );
                    self::store_result('send_test_email', $result, $user_id);
                    return $result;
                }
            } else {
                $from_email = isset($smtp_settings['from_email']) ? (string) $smtp_settings['from_email'] : '';
                if ($from_email === '') {
                    $from_email = SmtpSettings::get_sender_email();
                }
                if ($from_email === '') {
                    $result = array(
                        'success' => false,
                        'message' => 'Sender email address is not configured. Please configure it in Settings > Sender Settings.',
                        'details' => array(),
                    );
                    self::store_result('send_test_email', $result, $user_id);
                    return $result;
                }
            }

            $provider_type = (string) SmtpSettings::get('provider_type', 'smtp');
            if (!SmtpSettings::is_active_provider_configured()) {
                $result = array(
                    'success' => false,
                    'message' => 'Email provider is not properly configured. Please configure ' . $provider_type . ' settings.',
                    'details' => array('provider' => $provider_type),
                );
                self::store_result('send_test_email', $result, $user_id);
                return $result;
            }

            $subject = 'MNEM SMTP Test Email';
            $body    = EmailFormatter::apply_global_header_footer('<p>This is a test email from Multisite Network Email Manager.</p>');

            if ($from_name === '') {
                $from_name = isset($smtp_settings['from_name']) ? (string) $smtp_settings['from_name'] : '';
                if ($from_name === '') {
                    $from_name = SmtpSettings::get_sender_name();
                }
            }

            $headers = array('Content-Type: text/html; charset=UTF-8');
            $headers[] = $from_name !== ''
                ? 'From: ' . $from_name . ' <' . $from_email . '>'
                : 'From: ' . $from_email;

            Logger::info('Sending test email via configured provider.', array(
                'to'         => $to,
                'user_id'    => $user_id,
                'provider'   => $provider_type,
                'from_email' => $from_email,
                'from_name'  => $from_name,
            ));

            $send_result = ProviderManager::send_email($to, $subject, $body, $headers);

            $sent       = !empty($send_result['success']);
            $provider   = isset($send_result['provider'])   ? (string) $send_result['provider']   : '';
            $message_id = isset($send_result['message_id']) ? (string) $send_result['message_id'] : '';

            if ($sent) {
                Logger::info('Test email sent successfully.', array('to' => $to, 'provider' => $provider, 'message_id' => $message_id, 'user_id' => $user_id));
            } else {
                $error = isset($send_result['message']) ? (string) $send_result['message'] : 'Unknown error.';
                Logger::error('Test email send failed.', array('to' => $to, 'provider' => $provider !== '' ? $provider : $provider_type, 'provider_message' => $error, 'user_id' => $user_id));

                if ($provider === '') {
                    Logger::error('Test email failed - no provider returned in result.', array(
                        'expected_provider' => $provider_type,
                        'result'            => $send_result,
                    ));
                }
            }

            // Always record the attempt - success or failure.
            $site_id = function_exists('get_current_blog_id') ? max(1, (int) get_current_blog_id()) : 1;
            $tracking_row = array(
                'site_id'         => $site_id,
                'recipient_email' => $to,
                'subject'         => $subject,
                'body'            => $body,
            );
            EmailTracking::store_sent_email(0, $tracking_row, $send_result, $headers, $site_id, 'test');

            $provider_label = $provider !== '' ? $provider : $provider_type;
            $message = $sent
                ? 'Test email sent successfully via ' . $provider_label . '.'
                : 'Test email sending failed via ' . $provider_label . '.';

            if (!$sent && isset($send_result['message']) && (string) $send_result['message'] !== '') {
                $error = (string) $send_result['message'];
                $message .= ' Error: ' . $error;

                if (strpos($error, '401') !== false || strpos($error, 'Unauthorized') !== false) {
                    $message .= ' (Check your API key in Settings > Email Service Provider)';
                } elseif (strpos($error, 'From') !== false || strpos($error, 'sender') !== false) {
                    $message .= ' (Configure sender email in Settings > Sender Settings)';
                }
            }

            $result = array(
                'success' => $sent,
                'message' => $message,
                'details' => array(
                    'to'         => $to,
                    'provider'   => $provider,
                    'message_id' => $message_id,
                ),
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
