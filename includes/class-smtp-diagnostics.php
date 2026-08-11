<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmtpDiagnostics
{
    public static function test_connection()
    {
        try {
            if (!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                return array(
                    'success' => false,
                    'message' => 'PHPMailer is not available.',
                );
            }

            $settings = SmtpSettings::get_all();
            if (empty($settings['host'])) {
                return array(
                    'success' => false,
                    'message' => 'SMTP host is not configured.',
                );
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

            return array(
                'success' => true,
                'message' => 'SMTP connection successful.',
            );
        } catch (\Throwable $throwable) {
            return array(
                'success' => false,
                'message' => $throwable->getMessage(),
            );
        }
    }

    public static function send_test_email(string $to)
    {
        try {
            $to = sanitize_email($to);

            if (!is_email($to)) {
                return array(
                    'success' => false,
                    'message' => 'Invalid email address.',
                );
            }

            $sent = wp_mail($to, 'MNEM SMTP Test Email', 'This is a test email from Multisite Network Email Manager.');

            return array(
                'success' => (bool) $sent,
                'message' => $sent ? 'Test email sent successfully.' : 'Failed to send test email.',
            );
        } catch (\Throwable $throwable) {
            return array(
                'success' => false,
                'message' => $throwable->getMessage(),
            );
        }
    }
}
