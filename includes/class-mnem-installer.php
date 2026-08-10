<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Installer {
    public static function activate( bool $network_wide ): void {
        self::install();
        update_site_option( 'mnem_network_activated', (bool) $network_wide );
        MNEM_Queue::schedule();
    }

    public static function deactivate( bool $network_wide ): void {
        $timestamp = wp_next_scheduled( MNEM_Queue::CRON_HOOK );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, MNEM_Queue::CRON_HOOK );
        }

        update_site_option( 'mnem_network_activated', (bool) $network_wide );
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $base_prefix     = $wpdb->base_prefix;
        $tables          = array(
            "CREATE TABLE {$base_prefix}mnem_logs (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                level varchar(20) NOT NULL,
                message text NOT NULL,
                context longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY level (level),
                KEY created_at (created_at)
            ) {$charset_collate};",
            "CREATE TABLE {$base_prefix}mnem_queue (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                recipient varchar(190) NOT NULL,
                subject text NOT NULL,
                body longtext NOT NULL,
                headers longtext NULL,
                attachments longtext NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                attempts smallint(5) unsigned NOT NULL DEFAULT 0,
                last_error text NULL,
                available_at datetime NOT NULL,
                processed_at datetime NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY status (status),
                KEY available_at (available_at)
            ) {$charset_collate};",
            "CREATE TABLE {$base_prefix}mnem_campaigns (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(190) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'draft',
                settings longtext NULL,
                content longtext NULL,
                created_by bigint(20) unsigned NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY status (status)
            ) {$charset_collate};",
            "CREATE TABLE {$base_prefix}mnem_suppressions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                email varchar(190) NOT NULL,
                reason varchar(190) NOT NULL,
                source varchar(50) NOT NULL DEFAULT 'manual',
                metadata longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY email (email)
            ) {$charset_collate};",
            "CREATE TABLE {$base_prefix}mnem_user_rules (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                name varchar(190) NOT NULL,
                trigger_event varchar(100) NOT NULL,
                action varchar(50) NOT NULL,
                is_enabled tinyint(1) NOT NULL DEFAULT 1,
                settings longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY trigger_event (trigger_event),
                KEY is_enabled (is_enabled)
            ) {$charset_collate};",
            "CREATE TABLE {$base_prefix}mnem_user_events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL,
                event_type varchar(100) NOT NULL,
                action_taken varchar(50) NOT NULL DEFAULT 'recorded',
                details longtext NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY event_type (event_type)
            ) {$charset_collate};",
        );

        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        update_site_option( 'mnem_db_version', MNEM_VERSION );

        $settings = new MNEM_Settings();
        update_site_option( MNEM_Settings::OPTION_KEY, $settings->sanitize( (array) get_site_option( MNEM_Settings::OPTION_KEY, array() ) ) );
    }
}
