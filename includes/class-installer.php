<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Installer
{
    public static function activate($network_wide = false)
    {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            error_log('MNEM requires PHP 7.4 or newer.');
            return;
        }

        if (!function_exists('is_multisite') || !is_multisite()) {
            error_log('MNEM requires WordPress multisite.');
            return;
        }

        if (!$network_wide) {
            error_log('MNEM requires network activation.');
            return;
        }

        self::install();
    }

    public static function deactivate()
    {
        Cron::deactivate();
    }

    public static function install()
    {
        global $wpdb;

        if (!isset($wpdb) || !property_exists($wpdb, 'prefix')) {
            return;
        }

        $charset_collate = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';

        if (defined('ABSPATH')) {
            $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
            if (file_exists($upgrade_file)) {
                require_once $upgrade_file;
            }
        }

        if (!function_exists('dbDelta')) {
            return;
        }

        $logs_table = $wpdb->prefix . 'mnem_logs';
        $queue_table = $wpdb->prefix . 'mnem_queue';
        $suppression_table = $wpdb->prefix . 'mnem_suppression';
        $campaigns_table = $wpdb->prefix . 'mnem_campaigns';
        $subscriber_lists_table = $wpdb->prefix . 'mnem_subscriber_lists';
        $list_subscribers_table = $wpdb->prefix . 'mnem_list_subscribers';
        $email_tracking_table = $wpdb->prefix . 'mnem_email_tracking';
        $error_logs_table = $wpdb->prefix . 'mnem_error_logs';

        $sql = array(
            "CREATE TABLE {$logs_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) unsigned NOT NULL DEFAULT 0,
                blog_id bigint(20) unsigned NOT NULL DEFAULT 0,
                user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                level varchar(20) NOT NULL,
                message text NOT NULL,
                context longtext NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY site_id (site_id),
                KEY level (level)
            ) {$charset_collate};",
            "CREATE TABLE {$queue_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) unsigned NOT NULL DEFAULT 0,
                blog_id bigint(20) unsigned NOT NULL DEFAULT 0,
                campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
                recipient_email varchar(190) NOT NULL,
                subject text NOT NULL,
                body longtext NOT NULL,
                from_email varchar(190) NOT NULL DEFAULT '',
                from_name varchar(255) NOT NULL DEFAULT '',
                headers longtext NULL,
                attachments longtext NULL,
                metadata longtext NULL,
                source enum('campaign','user_event','plugin','core') NOT NULL DEFAULT 'core',
                status enum('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
                attempts int(11) NOT NULL DEFAULT 0,
                scheduled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_at datetime NULL,
                sent_at datetime NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                provider_type varchar(20) NOT NULL DEFAULT '',
                provider_message_id varchar(255) NOT NULL DEFAULT '',
                provider_metadata longtext NULL,
                PRIMARY KEY  (id),
                KEY site_status (site_id, status),
                KEY blog_status_created (blog_id, status, created_at),
                KEY campaign_status (campaign_id, status),
                KEY scheduled_at (scheduled_at)
            ) {$charset_collate};",
            "CREATE TABLE {$suppression_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) unsigned NOT NULL DEFAULT 0,
                email varchar(190) NOT NULL,
                reason text NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY site_email (site_id, email)
            ) {$charset_collate};",
            "CREATE TABLE {$campaigns_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) unsigned NOT NULL DEFAULT 0,
                name varchar(190) NOT NULL,
                subject text NOT NULL,
                body longtext NOT NULL,
                body_type enum('html') NOT NULL DEFAULT 'html',
                template_id varchar(190) NOT NULL DEFAULT '',
                status enum('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
                scheduled_at datetime NULL,
                recipient_scope varchar(20) NOT NULL DEFAULT 'all_users',
                recipient_list longtext NULL,
                target_lists longtext NULL,
                total_recipients int(11) NOT NULL DEFAULT 0,
                sent_count int(11) NOT NULL DEFAULT 0,
                failed_count int(11) NOT NULL DEFAULT 0,
                enqueue_failed_count int(11) NOT NULL DEFAULT 0,
                last_send_attempt_at datetime NULL,
                sent_at datetime NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY site_status (site_id, status)
            ) {$charset_collate};",
            "CREATE TABLE {$subscriber_lists_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                description longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY created_at (created_at)
            ) {$charset_collate};",
            "CREATE TABLE {$list_subscribers_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                list_id bigint(20) unsigned NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                subscription_status enum('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
                subscribed_at datetime NOT NULL,
                unsubscribed_at datetime NULL,
                unsubscribed_reason varchar(255) NULL,
                PRIMARY KEY  (id),
                KEY list_id (list_id),
                KEY user_id (user_id),
                KEY subscription_status (subscription_status),
                UNIQUE KEY list_user (list_id, user_id)
            ) {$charset_collate};",
            "CREATE TABLE {$email_tracking_table} (
                email_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                queue_id bigint(20) unsigned NOT NULL DEFAULT 0,
                provider_message_id varchar(255) NOT NULL DEFAULT '',
                recipient_email varchar(190) NOT NULL DEFAULT '',
                subject text NOT NULL,
                body longtext NOT NULL,
                headers longtext NULL,
                delivery_status enum('pending','delivered','bounced','failed') NOT NULL DEFAULT 'pending',
                open_count int(11) NOT NULL DEFAULT 0,
                open_timestamps longtext NULL,
                click_count int(11) NOT NULL DEFAULT 0,
                click_timestamps longtext NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (email_id),
                KEY queue_id (queue_id),
                KEY recipient_email (recipient_email),
                KEY provider_message_id (provider_message_id),
                KEY delivery_status (delivery_status),
                KEY created_at (created_at)
            ) {$charset_collate};",
            "CREATE TABLE {$error_logs_table} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                site_id bigint(20) unsigned NOT NULL DEFAULT 0,
                blog_id bigint(20) unsigned NOT NULL DEFAULT 0,
                error_level varchar(20) NOT NULL DEFAULT 'error',
                error_type varchar(100) NOT NULL,
                error_code varchar(50) NULL,
                error_message longtext NOT NULL,
                system_error longtext NULL,
                stack_trace longtext NULL,
                queue_id bigint(20) unsigned NULL,
                campaign_id bigint(20) unsigned NULL,
                recipient_email varchar(255) NULL,
                sender_email varchar(255) NULL,
                subject varchar(255) NULL,
                provider_type varchar(50) NULL,
                provider_error_code varchar(50) NULL,
                provider_error_message longtext NULL,
                http_status_code int(11) NULL,
                api_response longtext NULL,
                context longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY site_id (site_id),
                KEY error_level (error_level),
                KEY error_type (error_type),
                KEY created_at (created_at),
                KEY recipient_email (recipient_email),
                KEY queue_id (queue_id),
                KEY campaign_id (campaign_id),
                KEY provider_type (provider_type)
            ) {$charset_collate};",
        );

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        self::update_db_version();
        if ((int) get_site_option(EmailTracking::OPTION_KEEP_PREVIEWS, -1) === -1) {
            update_site_option(EmailTracking::OPTION_KEEP_PREVIEWS, 1);
        }
        if ((int) get_site_option(EmailTracking::OPTION_RETENTION_DAYS, -1) === -1) {
            update_site_option(EmailTracking::OPTION_RETENTION_DAYS, 30);
        }
    }

    public static function update_db_version()
    {
        update_site_option('mnem_db_version', MNEM_DB_VERSION);
    }
}
