<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Plugin
{
    public function init()
    {
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
            $user_events = new UserEvents();
            $user_events->init();
        } catch (\Throwable $throwable) {
            error_log('MNEM user events loader failed: ' . $throwable->getMessage());
        }
    }
}
