<?php

class MNEM_Installer
{
    public static function install()
    {
        if (! function_exists('add_site_option')) {
            return false;
        }

        $tables = self::table_names();
        self::maybe_create_tables($tables);
        update_site_option('mnem_db_version', MNEM_DB_VERSION);

        return true;
    }

    public static function table_names($database = null)
    {
        global $wpdb;

        if (null === $database) {
            $database = $wpdb;
        }

        $prefix = isset($database->base_prefix) ? $database->base_prefix : 'wp_';

        return array(
            'queue' => $prefix . 'mnem_queue',
            'suppressions' => $prefix . 'mnem_suppressions',
            'campaigns' => $prefix . 'mnem_campaigns',
        );
    }

    protected static function maybe_create_tables(array $tables)
    {
        global $wpdb;

        if (! isset($wpdb) || ! method_exists($wpdb, 'get_charset_collate')) {
            return;
        }

        if (! function_exists('dbDelta')) {
            $upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
            if (! file_exists($upgrade_file)) {
                return;
            }
            require_once $upgrade_file;
        }

        if (! function_exists('dbDelta')) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();
        $sql = array(
            "CREATE TABLE {$tables['queue']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                recipient varchar(190) NOT NULL,
                subject text NOT NULL,
                body longtext NOT NULL,
                headers longtext NULL,
                campaign_id bigint(20) unsigned NOT NULL DEFAULT 0,
                status varchar(20) NOT NULL DEFAULT 'queued',
                attempts int(11) NOT NULL DEFAULT 0,
                max_attempts int(11) NOT NULL DEFAULT 3,
                next_attempt_gmt datetime NULL,
                locked_at_gmt datetime NULL,
                sent_at_gmt datetime NULL,
                last_error text NULL,
                dedupe_key varchar(64) NOT NULL,
                created_at_gmt datetime NOT NULL,
                updated_at_gmt datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY status_next_attempt (status,next_attempt_gmt),
                KEY dedupe_key (dedupe_key)
            ) $charset_collate;",
            "CREATE TABLE {$tables['suppressions']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                email varchar(190) NOT NULL,
                reason text NULL,
                created_at_gmt datetime NOT NULL,
                updated_at_gmt datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY email (email)
            ) $charset_collate;",
            "CREATE TABLE {$tables['campaigns']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(190) NOT NULL,
                subject text NOT NULL,
                content longtext NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'draft',
                created_at_gmt datetime NOT NULL,
                updated_at_gmt datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY status (status)
            ) $charset_collate;",
        );

        foreach ($sql as $statement) {
            dbDelta($statement);
        }
    }
}
