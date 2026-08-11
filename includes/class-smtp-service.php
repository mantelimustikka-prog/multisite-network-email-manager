<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmtpService
{
    public function init()
    {
        add_action('phpmailer_init', array($this, 'configure_phpmailer'));
        add_filter('wp_mail_from', array($this, 'filter_mail_from'));
        add_filter('wp_mail_from_name', array($this, 'filter_mail_from_name'));
    }

    public function configure_phpmailer($phpmailer)
    {
        $settings = SmtpSettings::get_all();

        if (empty($settings['host'])) {
            return;
        }

        if (method_exists($phpmailer, 'isSMTP')) {
            $phpmailer->isSMTP();
        } elseif (method_exists($phpmailer, 'IsSMTP')) {
            $phpmailer->IsSMTP();
        }

        $phpmailer->Host = $settings['host'];
        $phpmailer->Port = (int) $settings['port'];
        $phpmailer->SMTPAuth = !empty($settings['username']) || SmtpSettings::get_password_decoded() !== '';
        $phpmailer->Username = $settings['username'];
        $phpmailer->Password = SmtpSettings::get_password_decoded();
        $phpmailer->SMTPSecure = $settings['encryption'] === 'none' ? '' : $settings['encryption'];

        if (!empty($settings['from_email'])) {
            $phpmailer->From = $settings['from_email'];
        }

        if (!empty($settings['from_name'])) {
            $phpmailer->FromName = $settings['from_name'];
        }
    }

    public function filter_mail_from($from)
    {
        $configured = SmtpSettings::get('from_email', '');

        return $configured !== '' ? $configured : $from;
    }

    public function filter_mail_from_name($from_name)
    {
        $configured = SmtpSettings::get('from_name', '');

        return $configured !== '' ? $configured : $from_name;
    }
}
