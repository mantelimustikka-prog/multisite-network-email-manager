<?php

/**
 * Migration 001: Create mnem_sms_campaigns table and update mnem_queue.
 *
 * Run via the MNEM installer or manually via WP-CLI:
 *   wp eval 'require_once ABSPATH . "wp-content/plugins/multisite-network-email-manager/includes/migrations/001-create-sms-campaigns-table.php"; mnem_migration_001();'
 */

defined('ABSPATH') || exit;

/**
 * Execute migration 001.
 *
 * @global wpdb $wpdb
 * @return array Result with 'success' (bool) and 'messages' (array) keys.
 */
function mnem_migration_001()
{
    global $wpdb;

    $messages = array();
    $success  = true;

    $table_name      = $wpdb->base_prefix . 'mnem_sms_campaigns';
    $queue_table     = $wpdb->base_prefix . 'mnem_queue';
    $charset_collate = $wpdb->get_charset_collate();

    // -------------------------------------------------------------------------
    // Create mnem_sms_campaigns table
    // -------------------------------------------------------------------------
    $sql_campaigns = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        site_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        description LONGTEXT,
        message_body LONGTEXT NOT NULL,
        sms_list_id BIGINT UNSIGNED NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'draft',
        total_recipients INT UNSIGNED DEFAULT 0,
        sent_count INT UNSIGNED DEFAULT 0,
        failed_count INT UNSIGNED DEFAULT 0,
        bounce_count INT UNSIGNED DEFAULT 0,
        delivery_status_map LONGTEXT,
        scheduled_at DATETIME,
        started_at DATETIME,
        completed_at DATETIME,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_by BIGINT UNSIGNED NOT NULL,
        KEY idx_site_id (site_id),
        KEY idx_status (status),
        KEY idx_created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql_campaigns);

    if ($wpdb->last_error !== '') {
        $messages[] = 'Error creating mnem_sms_campaigns table: ' . $wpdb->last_error;
        $success    = false;
    } else {
        $messages[] = 'mnem_sms_campaigns table created or already exists.';
    }

    // -------------------------------------------------------------------------
    // Add sms_campaign_id column to mnem_queue
    // -------------------------------------------------------------------------
    $existing_columns = $wpdb->get_col(
        $wpdb->prepare("SHOW COLUMNS FROM {$queue_table} LIKE %s", 'sms_campaign_id')
    );

    if (empty($existing_columns)) {
        $wpdb->query("ALTER TABLE {$queue_table} ADD COLUMN sms_campaign_id BIGINT UNSIGNED DEFAULT NULL");
        if ($wpdb->last_error !== '') {
            $messages[] = 'Error adding sms_campaign_id to mnem_queue: ' . $wpdb->last_error;
            $success    = false;
        } else {
            $messages[] = 'Column sms_campaign_id added to mnem_queue.';
        }
    } else {
        $messages[] = 'Column sms_campaign_id already exists in mnem_queue.';
    }

    // -------------------------------------------------------------------------
    // Add phone_number column to mnem_queue
    // -------------------------------------------------------------------------
    $existing_columns = $wpdb->get_col(
        $wpdb->prepare("SHOW COLUMNS FROM {$queue_table} LIKE %s", 'phone_number')
    );

    if (empty($existing_columns)) {
        $wpdb->query("ALTER TABLE {$queue_table} ADD COLUMN phone_number VARCHAR(20) DEFAULT NULL");
        if ($wpdb->last_error !== '') {
            $messages[] = 'Error adding phone_number to mnem_queue: ' . $wpdb->last_error;
            $success    = false;
        } else {
            $messages[] = 'Column phone_number added to mnem_queue.';
        }
    } else {
        $messages[] = 'Column phone_number already exists in mnem_queue.';
    }

    // -------------------------------------------------------------------------
    // Add message_type column to mnem_queue
    // -------------------------------------------------------------------------
    $existing_columns = $wpdb->get_col(
        $wpdb->prepare("SHOW COLUMNS FROM {$queue_table} LIKE %s", 'message_type')
    );

    if (empty($existing_columns)) {
        $wpdb->query("ALTER TABLE {$queue_table} ADD COLUMN message_type VARCHAR(20) DEFAULT 'email'");
        if ($wpdb->last_error !== '') {
            $messages[] = 'Error adding message_type to mnem_queue: ' . $wpdb->last_error;
            $success    = false;
        } else {
            $messages[] = 'Column message_type added to mnem_queue.';
        }
    } else {
        $messages[] = 'Column message_type already exists in mnem_queue.';
    }

    // -------------------------------------------------------------------------
    // Add idx_sms_campaign_id index to mnem_queue
    // -------------------------------------------------------------------------
    $existing_index = $wpdb->get_results(
        $wpdb->prepare("SHOW INDEX FROM {$queue_table} WHERE Key_name = %s", 'idx_sms_campaign_id')
    );

    if (empty($existing_index)) {
        $wpdb->query("ALTER TABLE {$queue_table} ADD INDEX idx_sms_campaign_id (sms_campaign_id)");
        if ($wpdb->last_error !== '') {
            $messages[] = 'Error adding idx_sms_campaign_id index: ' . $wpdb->last_error;
        } else {
            $messages[] = 'Index idx_sms_campaign_id added to mnem_queue.';
        }
    } else {
        $messages[] = 'Index idx_sms_campaign_id already exists in mnem_queue.';
    }

    // -------------------------------------------------------------------------
    // Add idx_message_type index to mnem_queue
    // -------------------------------------------------------------------------
    $existing_index = $wpdb->get_results(
        $wpdb->prepare("SHOW INDEX FROM {$queue_table} WHERE Key_name = %s", 'idx_message_type')
    );

    if (empty($existing_index)) {
        $wpdb->query("ALTER TABLE {$queue_table} ADD INDEX idx_message_type (message_type)");
        if ($wpdb->last_error !== '') {
            $messages[] = 'Error adding idx_message_type index: ' . $wpdb->last_error;
        } else {
            $messages[] = 'Index idx_message_type added to mnem_queue.';
        }
    } else {
        $messages[] = 'Index idx_message_type already exists in mnem_queue.';
    }

    return array(
        'success'  => $success,
        'messages' => $messages,
    );
}
