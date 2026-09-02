<?php

/**
 * Migration 002: Create mnem_sms_queue table and migrate SMS rows from mnem_queue.
 *
 * Run via the MNEM installer or manually via WP-CLI:
 *   wp eval 'require_once ABSPATH . "wp-content/plugins/multisite-network-email-manager/includes/migrations/002-create-sms-queue-table.php"; mnem_migration_002();'
 */

defined('ABSPATH') || exit;

/**
 * Execute migration 002.
 *
 * @global wpdb $wpdb
 * @return array Result with 'success' (bool) and 'messages' (array) keys.
 */
function mnem_migration_002()
{
    global $wpdb;

    $messages    = array();
    $success     = true;
    $sms_table   = $wpdb->base_prefix . 'mnem_sms_queue';
    $queue_table = $wpdb->base_prefix . 'mnem_queue';
    $charset_collate = $wpdb->get_charset_collate();

    // -------------------------------------------------------------------------
    // Create mnem_sms_queue table
    // -------------------------------------------------------------------------
    if (!function_exists('dbDelta')) {
        $upgrade_php = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (!file_exists($upgrade_php)) {
            $messages[] = 'dbDelta() not available and upgrade.php not found; table creation skipped.';
            return array('success' => false, 'messages' => $messages);
        }
        require_once $upgrade_php;
    }

    $sql_sms_queue = "CREATE TABLE IF NOT EXISTS {$sms_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        site_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        sms_campaign_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        phone_number VARCHAR(20) NOT NULL DEFAULT '',
        body LONGTEXT NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        message_type VARCHAR(20) NOT NULL DEFAULT 'sms',
        provider_type VARCHAR(50) NOT NULL DEFAULT '',
        provider_message_id VARCHAR(255) NOT NULL DEFAULT '',
        provider_status VARCHAR(50) NULL,
        provider_status_checked_at DATETIME NULL,
        last_sync_error TEXT NULL,
        sync_attempts INT UNSIGNED NOT NULL DEFAULT 0,
        provider_metadata LONGTEXT NULL,
        sent_at DATETIME NULL,
        attempts INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_status (status),
        KEY idx_site_id (site_id),
        KEY idx_sms_campaign_id (sms_campaign_id),
        KEY idx_created_at (created_at),
        KEY idx_provider_status (provider_status),
        KEY idx_provider_checked (provider_status_checked_at)
    ) {$charset_collate};";

    dbDelta($sql_sms_queue);

    if ((string)(isset($wpdb->last_error) ? $wpdb->last_error : '') !== '') {
        $messages[] = 'Error creating mnem_sms_queue table: ' . $wpdb->last_error;
        $success    = false;
        return array('success' => $success, 'messages' => $messages);
    } else {
        $messages[] = 'mnem_sms_queue table created or already exists.';
    }

    // -------------------------------------------------------------------------
    // Migrate existing SMS rows from mnem_queue to mnem_sms_queue
    // -------------------------------------------------------------------------
    $queue_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $queue_table));
    if ($queue_exists !== $queue_table) {
        $messages[] = 'mnem_queue table does not exist; SMS migration skipped.';
        return array('success' => $success, 'messages' => $messages);
    }

    // Check whether message_type column exists in mnem_queue (it may not exist on very fresh installs).
    $mt_col_exists = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
        $queue_table,
        'message_type'
    ));

    if ($mt_col_exists === 0) {
        $messages[] = 'Column message_type not found in mnem_queue; SMS migration skipped.';
        return array('success' => $success, 'messages' => $messages);
    }

    // Count how many SMS rows exist so we can report accurately.
    $sms_count = (int) $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} WHERE message_type = %s", 'sms')
    );

    if ($sms_count === 0) {
        $messages[] = 'No SMS rows found in mnem_queue; nothing to migrate.';
    } else {
        // Insert into mnem_sms_queue, mapping shared columns.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $inserted = $wpdb->query(
            "INSERT IGNORE INTO {$sms_table}
                (id, site_id, sms_campaign_id, phone_number, body, status, message_type, provider_type, provider_message_id, sent_at, attempts, created_at)
             SELECT
                id,
                COALESCE(site_id, 0),
                COALESCE(sms_campaign_id, 0),
                COALESCE(phone_number, ''),
                COALESCE(body, ''),
                COALESCE(status, 'pending'),
                'sms',
                COALESCE(provider_type, ''),
                COALESCE(provider_message_id, ''),
                sent_at,
                COALESCE(attempts, 0),
                COALESCE(created_at, NOW())
             FROM {$queue_table}
             WHERE message_type = 'sms'"
        );

        if ((string)(isset($wpdb->last_error) ? $wpdb->last_error : '') !== '') {
            $messages[] = 'Error migrating SMS rows: ' . $wpdb->last_error;
            $success    = false;
            return array('success' => $success, 'messages' => $messages);
        }

        $messages[] = sprintf('Migrated %d SMS row(s) to mnem_sms_queue.', (int) $inserted);

        // Remove the migrated SMS rows from mnem_queue.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $deleted = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$queue_table} WHERE message_type = %s", 'sms')
        );

        if ((string)(isset($wpdb->last_error) ? $wpdb->last_error : '') !== '') {
            $messages[] = 'Error removing SMS rows from mnem_queue: ' . $wpdb->last_error;
            $success    = false;
        } else {
            $messages[] = sprintf('Removed %d SMS row(s) from mnem_queue.', (int) $deleted);
        }
    }

    return array(
        'success'  => $success,
        'messages' => $messages,
    );
}
