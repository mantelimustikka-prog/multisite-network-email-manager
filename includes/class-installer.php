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
        global $wpdb;

        Cron::deactivate();
        StatusSyncCron::deactivate();

        $logs_table = $wpdb->base_prefix . 'mnem_logs';
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query("DROP TABLE IF EXISTS `{$logs_table}`");
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

        // Migration: queue schema updates.
        $queue_table = $tracking_prefix . 'mnem_queue';
        $queue_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $queue_table));
        if ($queue_exists === $queue_table) {
            $status_type = (string) $wpdb->get_var($wpdb->prepare(
                'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                $queue_table,
                'status'
            ));
            if ($status_type !== '' && strpos($status_type, "'delivered'") === false) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE `{$queue_table}` MODIFY COLUMN status enum('pending','processing','sent','delivered','opened','clicked','bounce','soft_bounce','invalid_email','deferred','complaint','unsubscribed','suppressed','failed','rejected') NOT NULL DEFAULT 'pending'");
            }

            $processed_exists = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                $queue_table,
                'processed_at'
            ));
            if ($processed_exists > 0) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE `{$queue_table}` DROP COLUMN processed_at");
            }

            foreach (array('opens', 'clicks') as $column_name) {
                $column_exists = (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                    $queue_table,
                    $column_name
                ));
                if ($column_exists > 0) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("ALTER TABLE `{$queue_table}` DROP COLUMN `{$column_name}`");
                }
            }

            foreach (array('opened', 'clicked') as $column_name) {
                $column_exists = (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                    $queue_table,
                    $column_name
                ));
                if ($column_exists === 0) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("ALTER TABLE `{$queue_table}` ADD COLUMN `{$column_name}` datetime NULL AFTER sent_at");
                }
            }

            foreach (array('opens_count', 'clicks_count') as $column_name) {
                $column_exists = (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                    $queue_table,
                    $column_name
                ));
                if ($column_exists === 0) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("ALTER TABLE `{$queue_table}` ADD COLUMN `{$column_name}` int(11) NOT NULL DEFAULT 0 AFTER clicked");
                }
            }
        }

        foreach (array('mnem_email_tracking_events', 'mnem_email_tracking') as $obsolete_table_suffix) {
            $obsolete_table = $tracking_prefix . $obsolete_table_suffix;
            $obsolete_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $obsolete_table));
            if ($obsolete_exists === $obsolete_table) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("DROP TABLE IF EXISTS `{$obsolete_table}`");
            }
        }

        // Migration: add multi-country phone validation columns.
        $subs_table = $tracking_prefix . 'mnem_sms_list_subscribers';
        $subs_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $subs_table));
        if ($subs_exists === $subs_table) {
            foreach (array(
                'phone_country_iso2'      => "ADD COLUMN phone_country_iso2 varchar(2) NULL",
                'phone_raw_input'         => "ADD COLUMN phone_raw_input varchar(50) NULL",
                'phone_detection_source'  => "ADD COLUMN phone_detection_source varchar(20) NULL",
                'subscriber_name'         => "ADD COLUMN subscriber_name varchar(255) NOT NULL DEFAULT '' AFTER user_id",
            ) as $col => $alter_clause) {
                $col_exists = (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                    $subs_table,
                    $col
                ));
                if ($col_exists === 0) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("ALTER TABLE `{$subs_table}` {$alter_clause}");
                }
            }

            $list_user_unique_exists = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s AND NON_UNIQUE = 0',
                $subs_table,
                'list_user'
            ));
            if ($list_user_unique_exists > 0) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE `{$subs_table}` DROP INDEX `list_user`");
            }

            $list_user_index_exists = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
                $subs_table,
                'list_user'
            ));
            if ($list_user_index_exists === 0) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE `{$subs_table}` ADD KEY `list_user` (`list_id`, `user_id`)");
            }
        }

        $invalid_table = $tracking_prefix . 'mnem_invalid_phone_numbers';
        $invalid_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $invalid_table));
        if ($invalid_exists === $invalid_table) {
            foreach (array(
                'raw_input'              => "ADD COLUMN raw_input varchar(50) NULL",
                'detected_country_iso2'  => "ADD COLUMN detected_country_iso2 varchar(2) NULL",
                'reason_detail'          => "ADD COLUMN reason_detail varchar(255) NULL",
            ) as $col => $alter_clause) {
                $col_exists = (int) $wpdb->get_var($wpdb->prepare(
                    'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                    $invalid_table,
                    $col
                ));
                if ($col_exists === 0) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $wpdb->query("ALTER TABLE `{$invalid_table}` {$alter_clause}");
                }
            }

            $idx_exists = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(1) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = %s',
                $invalid_table,
                'detected_country_iso2'
            ));
            if ($idx_exists === 0) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $wpdb->query("ALTER TABLE `{$invalid_table}` ADD KEY detected_country_iso2 (detected_country_iso2)");
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

        // Migration 002: create mnem_sms_queue and move SMS rows from mnem_queue.
        // Guard: only run if mnem_sms_queue does not yet exist.
        $sms_queue_table = $tracking_prefix . 'mnem_sms_queue';
        $sms_queue_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sms_queue_table));
        if ($sms_queue_exists !== $sms_queue_table) {
            $migration_002 = __DIR__ . '/migrations/002-create-sms-queue-table.php';
            if (file_exists($migration_002)) {
                require_once $migration_002;
                if (function_exists('mnem_migration_002')) {
                    mnem_migration_002();
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
                    'status' => "enum('pending','processing','sent','delivered','opened','clicked','bounce','soft_bounce','invalid_email','deferred','complaint','unsubscribed','suppressed','failed','rejected')",
                    'attempts' => 'int(11)',
                    'scheduled_at' => 'datetime',
                    'sent_at' => 'datetime',
                    'opened' => 'datetime',
                    'clicked' => 'datetime',
                    'opens_count' => 'int(11)',
                    'clicks_count' => 'int(11)',
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
                    status enum('pending','processing','sent','delivered','opened','clicked','bounce','soft_bounce','invalid_email','deferred','complaint','unsubscribed','suppressed','failed','rejected') NOT NULL DEFAULT 'pending',
                    attempts int(11) NOT NULL DEFAULT 0,
                    scheduled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    sent_at datetime NULL,
                    opened datetime NULL,
                    clicked datetime NULL,
                    opens_count int(11) NOT NULL DEFAULT 0,
                    clicks_count int(11) NOT NULL DEFAULT 0,
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
            'mnem_sms_subscriber_lists' => array(
                'name' => $tracking_prefix . 'mnem_sms_subscriber_lists',
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
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_sms_subscriber_lists (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    name varchar(255) NOT NULL,
                    description longtext NULL,
                    created_at datetime NOT NULL,
                    updated_at datetime NOT NULL,
                    PRIMARY KEY  (id),
                    KEY created_at (created_at)
                ){$charset_suffix};",
            ),
            'mnem_sms_list_subscribers' => array(
                'name' => $tracking_prefix . 'mnem_sms_list_subscribers',
                'columns' => array(
                    'id' => 'bigint(20) unsigned',
                    'list_id' => 'bigint(20) unsigned',
                    'user_id' => 'bigint(20) unsigned',
                    'subscriber_name' => 'varchar(255)',
                    'phone_number' => 'varchar(20)',
                    'subscription_status' => "enum('subscribed','unsubscribed')",
                    'subscribed_at' => 'datetime',
                    'unsubscribed_at' => 'datetime',
                    'unsubscribed_reason' => 'text',
                    'phone_country_iso2' => 'varchar(2)',
                    'phone_raw_input' => 'varchar(50)',
                    'phone_detection_source' => 'varchar(20)',
                ),
                'indexes' => array(
                    'PRIMARY' => array('id'),
                    'list_id' => array('list_id'),
                    'user_id' => array('user_id'),
                    'subscription_status' => array('subscription_status'),
                    'list_user' => array('list_id', 'user_id'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_sms_list_subscribers (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    list_id bigint(20) unsigned NOT NULL,
                    user_id bigint(20) unsigned NOT NULL,
                    subscriber_name varchar(255) NOT NULL DEFAULT '',
                    phone_number varchar(20) NOT NULL DEFAULT '',
                    subscription_status enum('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
                    subscribed_at datetime NOT NULL,
                    unsubscribed_at datetime NULL,
                    unsubscribed_reason text NULL,
                    phone_country_iso2 varchar(2) NULL,
                    phone_raw_input varchar(50) NULL,
                    phone_detection_source varchar(20) NULL,
                    PRIMARY KEY  (id),
                    KEY list_id (list_id),
                    KEY user_id (user_id),
                    KEY phone_number (phone_number),
                    KEY subscription_status (subscription_status),
                    KEY list_user (list_id, user_id)
                ){$charset_suffix};",
            ),
            'mnem_invalid_phone_numbers' => array(
                'name' => $tracking_prefix . 'mnem_invalid_phone_numbers',
                'columns' => array(
                    'id' => 'bigint(20) unsigned',
                    'phone_number' => 'varchar(20)',
                    'reason' => 'varchar(50)',
                    'list_id' => 'bigint(20) unsigned',
                    'user_id' => 'bigint(20) unsigned',
                    'blocked' => 'tinyint(1)',
                    'created_at' => 'datetime',
                    'action_taken' => 'varchar(50)',
                    'taken_by' => 'bigint(20) unsigned',
                    'taken_at' => 'datetime',
                    'raw_input' => 'varchar(50)',
                    'detected_country_iso2' => 'varchar(2)',
                    'reason_detail' => 'varchar(255)',
                ),
                'indexes' => array(
                    'PRIMARY' => array('id'),
                    'phone_number' => array('phone_number'),
                    'list_id' => array('list_id'),
                    'user_id' => array('user_id'),
                    'blocked' => array('blocked'),
                    'created_at' => array('created_at'),
                    'phone_list_unique' => array('phone_number', 'list_id'),
                    'detected_country_iso2' => array('detected_country_iso2'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_invalid_phone_numbers (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    phone_number varchar(20) NOT NULL,
                    reason varchar(50) NOT NULL DEFAULT 'format_invalid',
                    list_id bigint(20) unsigned NOT NULL DEFAULT 0,
                    user_id bigint(20) unsigned NULL,
                    blocked tinyint(1) NOT NULL DEFAULT 0,
                    created_at datetime NOT NULL,
                    action_taken varchar(50) DEFAULT 'none',
                    taken_by bigint(20) unsigned NULL,
                    taken_at datetime NULL,
                    raw_input varchar(50) NULL,
                    detected_country_iso2 varchar(2) NULL,
                    reason_detail varchar(255) NULL,
                    PRIMARY KEY  (id),
                    KEY phone_number (phone_number),
                    KEY list_id (list_id),
                    KEY user_id (user_id),
                    KEY blocked (blocked),
                    KEY created_at (created_at),
                    KEY detected_country_iso2 (detected_country_iso2),
                    UNIQUE KEY phone_list_unique (phone_number, list_id)
                ){$charset_suffix};",
            ),
            'mnem_sms_queue' => array(
                'name' => $tracking_prefix . 'mnem_sms_queue',
                'columns' => array(
                    'id' => 'bigint(20) unsigned',
                    'site_id' => 'bigint(20) unsigned',
                    'sms_campaign_id' => 'bigint(20) unsigned',
                    'phone_number' => 'varchar(20)',
                    'body' => 'longtext',
                    'status' => 'varchar(50)',
                    'message_type' => 'varchar(20)',
                    'provider_type' => 'varchar(50)',
                    'provider_message_id' => 'varchar(255)',
                    'provider_status' => 'varchar(50)',
                    'provider_status_checked_at' => 'datetime',
                    'last_sync_error' => 'text',
                    'sync_attempts' => 'int(10) unsigned',
                    'provider_metadata' => 'longtext',
                    'sent_at' => 'datetime',
                    'attempts' => 'int(10) unsigned',
                    'created_at' => 'datetime',
                    'updated_at' => 'datetime',
                ),
                'indexes' => array(
                    'PRIMARY' => array('id'),
                    'idx_status' => array('status'),
                    'idx_site_id' => array('site_id'),
                    'idx_sms_campaign_id' => array('sms_campaign_id'),
                    'idx_created_at' => array('created_at'),
                    'idx_provider_status' => array('provider_status'),
                    'idx_provider_checked' => array('provider_status_checked_at'),
                ),
                'create_sql' => "CREATE TABLE {$tracking_prefix}mnem_sms_queue (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    site_id bigint(20) unsigned NOT NULL DEFAULT 0,
                    sms_campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
                    phone_number varchar(20) NOT NULL DEFAULT '',
                    body longtext NOT NULL,
                    status varchar(50) NOT NULL DEFAULT 'pending',
                    message_type varchar(20) NOT NULL DEFAULT 'sms',
                    provider_type varchar(50) NOT NULL DEFAULT '',
                    provider_message_id varchar(255) NOT NULL DEFAULT '',
                    provider_status varchar(50) NULL,
                    provider_status_checked_at datetime NULL,
                    last_sync_error text NULL,
                    sync_attempts int(10) unsigned NOT NULL DEFAULT 0,
                    provider_metadata longtext NULL,
                    sent_at datetime NULL,
                    attempts int(10) unsigned NOT NULL DEFAULT 0,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY  (id),
                    KEY idx_status (status),
                    KEY idx_site_id (site_id),
                    KEY idx_sms_campaign_id (sms_campaign_id),
                    KEY idx_created_at (created_at),
                    KEY idx_provider_status (provider_status),
                    KEY idx_provider_checked (provider_status_checked_at)
                ){$charset_suffix};",
            ),
        );
    }

    public static function update_db_version()
    {
        update_site_option('mnem_db_version', MNEM_DB_VERSION);
    }
}
