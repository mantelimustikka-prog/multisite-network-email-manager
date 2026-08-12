<?php

class MNEM_SMTP_Diagnostics
{
    protected $settings;
    protected $service;

    public function __construct(MNEM_SMTP_Settings $settings = null, MNEM_SMTP_Service $service = null)
    {
        $this->settings = $settings ?: new MNEM_SMTP_Settings();
        $this->service = $service ?: new MNEM_SMTP_Service($this->settings);
    }

    public function test_connection($phpmailer = null)
    {
        if (! $phpmailer || ! is_object($phpmailer)) {
            if (! class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return array('success' => false, 'message' => 'PHPMailer is unavailable in this environment.');
            }
            $phpmailer = new PHPMailer\PHPMailer\PHPMailer(true);
        }

        $configured = $this->service->configure_phpmailer($phpmailer);
        if (! $configured) {
            return array('success' => false, 'message' => 'SMTP is not enabled or not configured.');
        }

        if (method_exists($phpmailer, 'smtpConnect')) {
            try {
                return array(
                    'success' => (bool) $phpmailer->smtpConnect(),
                    'message' => 'SMTP connection test completed.',
                );
            } catch (Exception $exception) {
                return array('success' => false, 'message' => $exception->getMessage());
            }
        }

        return array('success' => true, 'message' => 'SMTP settings applied to mailer.');
    }

    public function send_test_email($to, $subject = 'MNEM Test Email', $body = 'This is a test email.', callable $sender = null)
    {
        $to = (string) $to;
        if (function_exists('sanitize_email')) {
            $to = sanitize_email($to);
        }

        if ('' === $to) {
            return array('success' => false, 'message' => 'A valid recipient email address is required.');
        }

        if ($sender) {
            $result = (bool) call_user_func($sender, $to, $subject, $body);
        } elseif (function_exists('wp_mail')) {
            $result = (bool) wp_mail($to, $subject, $body);
        } else {
            $result = false;
        }

        return array(
            'success' => $result,
            'message' => $result ? 'Test email sent.' : 'Test email failed to send.',
        );
    }
}
