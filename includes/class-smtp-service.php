<?php

class MNEM_SMTP_Service
{
    protected $settings;

    public function __construct(MNEM_SMTP_Settings $settings = null)
    {
        $this->settings = $settings ?: new MNEM_SMTP_Settings();
    }

    public function register()
    {
        if (function_exists('add_action')) {
            add_action('phpmailer_init', array($this, 'configure_phpmailer'));
        }
    }

    public function configure_phpmailer($phpmailer)
    {
        $settings = $this->settings->export();
        if (empty($settings['enabled']) || empty($settings['host']) || ! is_object($phpmailer)) {
            return false;
        }

        if (method_exists($phpmailer, 'isSMTP')) {
            $phpmailer->isSMTP();
        }

        $phpmailer->Host = $settings['host'];
        $phpmailer->Port = (int) $settings['port'];
        $phpmailer->SMTPAuth = ! empty($settings['username']);
        $phpmailer->Username = $settings['username'];
        $phpmailer->Password = $settings['password'];
        $phpmailer->SMTPSecure = $settings['secure'];

        if (! empty($settings['from_email']) && method_exists($phpmailer, 'setFrom')) {
            $phpmailer->setFrom($settings['from_email'], $settings['from_name']);
        }

        return true;
    }
}
