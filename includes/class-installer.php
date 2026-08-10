<?php
/**
 * Installer — custom table creation and migration.
 *
 * Call MNEM_Installer::install() on activation or during DB upgrades.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_Installer {

	/**
	 * Create or upgrade all custom tables.
	 *
	 * Uses dbDelta so it is safe to run multiple times.
	 */
	public static function install() {
		global $wpdb;

		$upgrade_file = ABSPATH . 'wp-admin/includes/upgrade.php';
		if ( file_exists( $upgrade_file ) ) {
			require_once $upgrade_file;
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return;
		}

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->base_prefix . 'mnem_';

		// --- Logs table --------------------------------------------------
		$sql_logs = "CREATE TABLE {$prefix}logs (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			module      VARCHAR(64)         NOT NULL DEFAULT '',
			level       VARCHAR(16)         NOT NULL DEFAULT 'info',
			message     TEXT                NOT NULL,
			context     LONGTEXT            NOT NULL,
			created_at  DATETIME            NOT NULL,
			PRIMARY KEY (id),
			KEY module_level (module, level),
			KEY created_at (created_at)
		) $charset_collate;";

		// --- SMTP settings table (per-site overrides possible later) -----
		$sql_smtp = "CREATE TABLE {$prefix}smtp_settings (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			setting_key VARCHAR(191)        NOT NULL DEFAULT '',
			setting_val LONGTEXT            NOT NULL,
			updated_at  DATETIME            NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY site_key (site_id, setting_key)
		) $charset_collate;";

		// --- Campaigns table --------------------------------------------
		$sql_campaigns = "CREATE TABLE {$prefix}campaigns (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name        VARCHAR(191)        NOT NULL DEFAULT '',
			subject     VARCHAR(255)        NOT NULL DEFAULT '',
			body        LONGTEXT            NOT NULL,
			status      VARCHAR(32)         NOT NULL DEFAULT 'draft',
			created_at  DATETIME            NOT NULL,
			updated_at  DATETIME            NOT NULL,
			PRIMARY KEY (id),
			KEY status (status)
		) $charset_collate;";

		// --- Send queue table -------------------------------------------
		$sql_queue = "CREATE TABLE {$prefix}queue (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_id  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			recipient    VARCHAR(191)        NOT NULL DEFAULT '',
			subject      VARCHAR(255)        NOT NULL DEFAULT '',
			body         LONGTEXT            NOT NULL,
			status       VARCHAR(32)         NOT NULL DEFAULT 'pending',
			attempts     SMALLINT(5) UNSIGNED NOT NULL DEFAULT 0,
			scheduled_at DATETIME            NOT NULL,
			sent_at      DATETIME            NULL,
			PRIMARY KEY (id),
			KEY status_scheduled (status, scheduled_at),
			KEY campaign_id (campaign_id)
		) $charset_collate;";

		// --- Suppression list table -------------------------------------
		$sql_suppression = "CREATE TABLE {$prefix}suppression (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			email       VARCHAR(191)        NOT NULL DEFAULT '',
			reason      VARCHAR(191)        NOT NULL DEFAULT '',
			created_at  DATETIME            NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY email (email)
		) $charset_collate;";

		// Run all migrations.
		dbDelta( $sql_logs );
		dbDelta( $sql_smtp );
		dbDelta( $sql_campaigns );
		dbDelta( $sql_queue );
		dbDelta( $sql_suppression );
	}
}
