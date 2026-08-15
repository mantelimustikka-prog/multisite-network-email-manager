<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Installer
{
    private static $migrations_ran = false;

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

        $schema = self::get_table_schema($wpdb->base_prefix, $charset_collate);
        foreach ($schema as $table_definition) {
            if (!isset($table_definition['create_sql'])) {
                continue;
            }
            dbDelta($table_definition['create_sql']);
        }

        self::update_db_version();
        self::run_migrations();
        if ((int) get_site_option(EmailTracking::OPTION_KEEP_PREVIEWS, -1) === -1) {
            update_site_option(EmailTracking::OPTION_KEEP_PREVIEWS, 1);
        }
        if ((int) get_site_option(EmailTracking::OPTION_RETENTION_DAYS, -1) === -1) {
            update_site_option(EmailTracking::OPTION_RETENTION_DAYS, 30);
        }
    }

    public static function run_migrations()
    {
        if (self::$migrations_ran) {
            return;
        }

        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'query')) {
            return;
        }

        self::$migrations_ran = true;

        $tracking_prefix = (property_exists($wpdb, 'base_prefix') && !empty($wpdb->base_prefix))
            ? (string) $wpdb->base_prefix
            : (string) $wpdb->prefix;

        // Migration: ensure site_id column exists in all central tables.
        $central_tables = array(
            $tracking_prefix . 'mnem_email_tracking',
            $tracking_prefix . 'mnem_queue',
            $tracking_prefix . 'mnem_campaigns',
        );

        foreach ($central_tables as $table_name) {
            $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
            if ($table_exists !== $table_name) {
                continue;
            }

            $column_exists = $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                $table_name,
                'site_id'
            ));

            if (!$column_exists) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN site_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER id, ADD KEY site_id (site_id)");
                // Pre-existing rows have no site context; default them to the main site (ID 1).
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("UPDATE `{$table_name}` SET site_id = 1 WHERE site_id = 0");
            }
        }

        // Migration: consolidate old site-based tables (wp_N_mnem_*) into central tables.
        if (function_exists('get_sites')) {
            $sites = get_sites(array('number' => 0, 'fields' => 'ids'));
            foreach ((array) $sites as $blog_id) {
                $blog_id = (int) $blog_id;
                if ($blog_id <= 1) {
                    continue;
                }
                $site_prefix = $tracking_prefix . $blog_id . '_';
                foreach (array('mnem_queue', 'mnem_campaigns') as $table_suffix) {
                    $old_table = $site_prefix . $table_suffix;
                    $new_table = $tracking_prefix . $table_suffix;
                    $old_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $old_table));
                    if ($old_exists !== $old_table) {
                        continue;
                    }
                    // Set site_id on the old table's rows first, then copy into the central table.
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("UPDATE `{$old_table}` SET site_id = {$blog_id} WHERE site_id = 0 OR site_id IS NULL");
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("INSERT IGNORE INTO `{$new_table}` SELECT * FROM `{$old_table}`");
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("DROP TABLE IF EXISTS `{$old_table}`");
                    Logger::info('Migrated old site-based table to central table.', array('old_table' => $old_table, 'new_table' => $new_table, 'blog_id' => $blog_id));
                }
            }
        }
    }

    public static function get_table_schema($prefix = null, $charset_collate = '')
    {
        global $wpdb;

        if ($prefix === null) {
            $prefix = (isset($wpdb) && is_object($wpdb) && property_exists($wpdb, 'prefix')) ? (string) $wpdb->prefix : 'wp_';
        }
        $tracking_prefix = (isset($wpdb) && is_object($wpdb) && property_exists($wpdb, 'base_prefix') && !empty($wpdb->base_prefix))
            ? (string) $wpdb->base_prefix
            : $prefix;

        $charset_suffix = trim((string) $charset_collate);
        $charset_suffix = $charset_suffix !== '' ? ' ' . $charset_suffix : '';

        return array(
            'mnem_logs' => array(
                'name' => $tracking_prefix . 'mnem_logs',
                'columns' => array(
                    'id' => 'bigint(20) unsigned',
                    'site_id' => 'bigint(20) unsigned',
                    'blog_id' => 'bigint(20) unsigned',
                    'user_id' => 'bigint(20) unsigned',
                    'level' => 'varchar(20)',
                    'message' => 'text',
                    'context' => 'longtext',
                    'created_at' => 'datetime',
                ),
                'indexes' => array(
                    'PRIMARY' => array('id'),
                    'site_id' => array('site_id'),
                    'level' => array('level'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_logs (
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
                ){$charset_suffix};",
            ),
            'mnem_queue' => array(
                'name' => $tracking_prefix . 'mnem_queue',
                'columns' => array(
                    'id' => 'bigint(20) unsigned',
                    'site_id' => 'bigint(20) unsigned',
                    'blog_id' => 'bigint(20) unsigned',
                    'campaign_id' => 'bigint(20) unsigned',
                    'recipient_email' => 'varchar(190)',
                    'subject' => 'text',
                    'body' => 'longtext',
                    'from_email' => 'varchar(190)',
                    'from_name' => 'varchar(255)',
                    'headers' => 'longtext',
                    'attachments' => 'longtext',
                    'metadata' => 'longtext',
                    'source' => "enum('campaign','user_event','plugin','core')",
                    'status' => "enum('pending','processing','sent','failed')",
                    'attempts' => 'int(11)',
                    'scheduled_at' => 'datetime',
                    'processed_at' => 'datetime',
                    'sent_at' => 'datetime',
                    'created_at' => 'datetime',
                    'provider_type' => 'varchar(20)',
                    'provider_message_id' => 'varchar(255)',
                    'provider_metadata' => 'longtext',
                ),
                'indexes' => array(
                    'PRIMARY' => array('id'),
                    'site_status' => array('site_id', 'status'),
                    'blog_status_created' => array('blog_id', 'status', 'created_at'),
                    'campaign_status' => array('campaign_id', 'status'),
                    'scheduled_at' => array('scheduled_at'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_queue (
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
                ){$charset_suffix};",
            ),
            'mnem_suppression' => array(
                'name' => $tracking_prefix . 'mnem_suppression',
                'columns' => array(
                    'id' => 'bigint(20) unsigned',
                    'site_id' => 'bigint(20) unsigned',
                    'email' => 'varchar(190)',
                    'reason' => 'text',
                    'created_at' => 'datetime',
                ),
                'indexes' => array(
                    'PRIMARY' => array('id'),
                    'site_email' => array('site_id', 'email'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_suppression (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    site_id bigint(20) unsigned NOT NULL DEFAULT 0,
                    email varchar(190) NOT NULL,
                    reason text NULL,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    UNIQUE KEY site_email (site_id, email)
                ){$charset_suffix};",
            ),
            'mnem_campaigns' => array(
                'name' => $tracking_prefix . 'mnem_campaigns',
                'columns' => array(
                    'id' => 'bigint(20) unsigned',
                    'site_id' => 'bigint(20) unsigned',
                    'name' => 'varchar(190)',
                    'subject' => 'text',
                    'body' => 'longtext',
                    'body_type' => "enum('html')",
                    'template_id' => 'varchar(190)',
                    'status' => "enum('draft','scheduled','sending','sent','cancelled')",
                    'scheduled_at' => 'datetime',
                    'recipient_scope' => 'varchar(20)',
                    'recipient_list' => 'longtext',
                    'target_lists' => 'longtext',
                    'total_recipients' => 'int(11)',
                    'sent_count' => 'int(11)',
                    'failed_count' => 'int(11)',
                    'enqueue_failed_count' => 'int(11)',
                    'last_send_attempt_at' => 'datetime',
                    'sent_at' => 'datetime',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                ),
                'indexes' => array(
                    'PRIMARY' => array('id'),
                    'site_status' => array('site_id', 'status'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_campaigns (
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
                ){$charset_suffix};",
            ),
            'mnem_subscriber_lists' => array(
                'name' => $tracking_prefix . 'mnem_subscriber_lists',
                'columns' => array(
                    'id' => 'bigint(20) unsigned',
                    'name' => 'varchar(255)',
                    'description' => 'longtext',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                ),
                'indexes' => array(
                    'PRIMARY' => array('id'),
                    'created_at' => array('created_at'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_subscriber_lists (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    name varchar(255) NOT NULL,
                    description longtext NULL,
                    created_at datetime NOT NULL,
                    updated_at datetime NOT NULL,
                    PRIMARY KEY  (id),
                    KEY created_at (created_at)
                ){$charset_suffix};",
            ),
            'mnem_list_subscribers' => array(
                'name' => $tracking_prefix . 'mnem_list_subscribers',
                'columns' => array(
                    'id' => 'bigint(20) unsigned',
                    'list_id' => 'bigint(20) unsigned',
                    'user_id' => 'bigint(20) unsigned',
                    'subscription_status' => "enum('subscribed','unsubscribed')",
                    'subscribed_at' => 'datetime',
                    'unsubscribed_at' => 'datetime',
                    'unsubscribed_reason' => 'varchar(255)',
                ),
                'indexes' => array(
                    'PRIMARY' => array('id'),
                    'list_id' => array('list_id'),
                    'user_id' => array('user_id'),
                    'subscription_status' => array('subscription_status'),
                    'list_user' => array('list_id', 'user_id'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_list_subscribers (
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
                ){$charset_suffix};",
            ),
            'mnem_email_tracking' => array(
                'name' => $tracking_prefix . 'mnem_email_tracking',
                'columns' => array(
                    'email_id' => 'bigint(20) unsigned',
                    'site_id' => 'bigint(20) unsigned',
                    'queue_id' => 'bigint(20) unsigned',
                    'provider_message_id' => 'varchar(255)',
                    'recipient_email' => 'varchar(190)',
                    'subject' => 'text',
                    'body' => 'longtext',
                    'headers' => 'longtext',
                    'delivery_status' => "enum('pending','delivered','bounced','failed')",
                    'open_count' => 'int(11)',
                    'open_timestamps' => 'longtext',
                    'click_count' => 'int(11)',
                    'click_timestamps' => 'longtext',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                ),
                'indexes' => array(
                    'PRIMARY' => array('email_id'),
                    'site_id' => array('site_id'),
                    'queue_id' => array('queue_id'),
                    'recipient_email' => array('recipient_email'),
                    'provider_message_id' => array('provider_message_id'),
                    'delivery_status' => array('delivery_status'),
                    'created_at' => array('created_at'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_email_tracking (
                    email_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    site_id bigint(20) unsigned NOT NULL DEFAULT 0,
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
                    KEY site_id (site_id),
                    KEY queue_id (queue_id),
                    KEY recipient_email (recipient_email),
                    KEY provider_message_id (provider_message_id),
                    KEY delivery_status (delivery_status),
                    KEY created_at (created_at)
                ){$charset_suffix};",
            ),
        );
    }

    public static function update_db_version()
    {
        update_site_option('mnem_db_version', MNEM_DB_VERSION);
    }
}
