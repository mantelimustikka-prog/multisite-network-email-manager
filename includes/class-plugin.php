<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Plugin
{
    public function init()
    {
        $db_version = (string) get_site_option('mnem_db_version', '');
        if ($db_version !== (string) MNEM_DB_VERSION) {
            Installer::install();
        }

        CliCommand::register_commands();

        if (function_exists('is_admin') && is_admin()) {
            try {
                $admin = new \MNEM\Admin\NetworkAdmin();
                $admin->init();
            } catch (\Throwable $throwable) {
                error_log('MNEM admin loader failed: ' . $throwable->getMessage());
            }
        }

        try {
            $rest_api = new RestApi();
            $rest_api->init();
        } catch (\Throwable $throwable) {
            error_log('MNEM REST API loader failed: ' . $throwable->getMessage());
        }

        try {
            $smtp_service = new SmtpService();
            $smtp_service->init();
        } catch (\Throwable $throwable) {
            error_log('MNEM SMTP service loader failed: ' . $throwable->getMessage());
        }

        try {
            MailInterceptor::init();
        } catch (\Throwable $throwable) {
            error_log('MNEM mail interceptor loader failed: ' . $throwable->getMessage());
        }

        try {
            $user_events = new UserEvents();
            $user_events->init();
        } catch (\Throwable $throwable) {
            error_log('MNEM user events loader failed: ' . $throwable->getMessage());
        }

        try {
            $cron = new Cron();
            $cron->init();
        } catch (\Throwable $throwable) {
            error_log('MNEM cron loader failed: ' . $throwable->getMessage());
        }
    }
}
