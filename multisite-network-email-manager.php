<?php
/**
 * Plugin Name:       Multisite Network Email Manager
 * Plugin URI:        https://github.com/mantelimustikka-prog/multisite-network-email-manager
 * Description:       Centralized email management for WordPress multisite networks. Provides SMTP configuration, campaign management, send queue, suppression lists, logging, and advanced user management triggers — all from the network admin.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Multisite Network Email Manager Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mnem
 * Domain Path:       /languages
 * Network:           true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MNEM_VERSION', '0.1.0' );
define( 'MNEM_PLUGIN_FILE', __FILE__ );
define( 'MNEM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MNEM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MNEM_OPTION_PREFIX', 'mnem_' );
define( 'MNEM_DB_VERSION', '1' );

/**
 * Safely include a plugin module file.
 *
 * @param string $relative_path Relative path from plugin root.
 * @return bool True when included (or already loaded), false when missing.
 */
function mnem_safe_include( $relative_path ) {
	$path = MNEM_PLUGIN_DIR . ltrim( $relative_path, '/' );
	if ( ! file_exists( $path ) ) {
		error_log( sprintf( 'MNEM bootstrap warning: missing file %s', $relative_path ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		return false;
	}
	require_once $path;
	return true;
}

// Core includes (defensive load-order hardening).
mnem_safe_include( 'includes/class-logger.php' );
mnem_safe_include( 'includes/class-installer.php' );
mnem_safe_include( 'includes/class-settings.php' );
mnem_safe_include( 'includes/class-rest-api.php' );
mnem_safe_include( 'includes/class-smtp-settings.php' );
mnem_safe_include( 'includes/class-smtp-service.php' );
mnem_safe_include( 'includes/class-smtp-diagnostics.php' );
mnem_safe_include( 'includes/class-campaigns.php' );
mnem_safe_include( 'includes/class-queue.php' );
mnem_safe_include( 'includes/class-suppression.php' );
mnem_safe_include( 'includes/class-user-management.php' );

// Admin includes (only in admin context).
if ( is_admin() ) {
	mnem_safe_include( 'admin/class-admin.php' );
	mnem_safe_include( 'admin/class-admin-menu.php' );
}

/**
 * Activation hook — runs on network activation.
 */
function mnem_activate() {
	if ( class_exists( 'MNEM_Installer' ) ) {
		MNEM_Installer::install();
	}
	// Store the plugin version on activation.
	update_site_option( 'mnem_version', MNEM_VERSION );
	update_site_option( 'mnem_db_version', MNEM_DB_VERSION );
}
register_activation_hook( MNEM_PLUGIN_FILE, 'mnem_activate' );

/**
 * Deactivation hook.
 */
function mnem_deactivate() {
	// Flush scheduled events. Destructive table drops are intentionally omitted.
	wp_clear_scheduled_hook( 'mnem_process_queue' );
}
register_deactivation_hook( MNEM_PLUGIN_FILE, 'mnem_deactivate' );

/**
 * Register custom WP-Cron intervals needed by the plugin.
 *
 * @param array $schedules Existing cron schedules.
 * @return array
 */
function mnem_cron_schedules( array $schedules ) {
	if ( ! isset( $schedules['every_five_minutes'] ) ) {
		$schedules['every_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'mnem' ),
		);
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'mnem_cron_schedules' );

/**
 * Bootstrap the plugin on plugins_loaded.
 */
function mnem_init() {
	// Load text domain.
	load_plugin_textdomain( 'mnem', false, dirname( plugin_basename( MNEM_PLUGIN_FILE ) ) . '/languages' );

	// Initialize core services.
	if ( class_exists( 'MNEM_Logger' ) ) {
		MNEM_Logger::init();
	}
	if ( class_exists( 'MNEM_Settings' ) ) {
		MNEM_Settings::init();
	}
	if ( class_exists( 'MNEM_SMTP_Service' ) ) {
		MNEM_SMTP_Service::init();
	}
	if ( class_exists( 'MNEM_REST_API' ) ) {
		MNEM_REST_API::init();
	}
	if ( class_exists( 'MNEM_Queue' ) ) {
		MNEM_Queue::init();
	}

	if ( is_admin() ) {
		if ( class_exists( 'MNEM_Admin' ) ) {
			MNEM_Admin::init();
		}
		if ( class_exists( 'MNEM_Admin_Menu' ) ) {
			MNEM_Admin_Menu::init();
		}
	}
}
add_action( 'plugins_loaded', 'mnem_init' );

/**
 * Run DB upgrades when version changes.
 */
function mnem_maybe_upgrade() {
	$installed_db_version = get_site_option( 'mnem_db_version', '0' );
	if ( class_exists( 'MNEM_Installer' ) && version_compare( $installed_db_version, MNEM_DB_VERSION, '<' ) ) {
		MNEM_Installer::install();
		update_site_option( 'mnem_db_version', MNEM_DB_VERSION );
	}
}
add_action( 'plugins_loaded', 'mnem_maybe_upgrade', 5 );
