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
                status enum('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
                scheduled_at datetime NULL,
                recipient_scope varchar(20) NOT NULL DEFAULT 'all_users',
                recipient_list longtext NULL,
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
        );

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        self::update_db_version();
    }

    public static function update_db_version()
    {
        update_site_option('mnem_db_version', MNEM_DB_VERSION);
    }
}
