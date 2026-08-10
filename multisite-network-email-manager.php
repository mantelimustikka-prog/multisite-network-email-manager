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

// Core includes.
require_once MNEM_PLUGIN_DIR . 'includes/class-logger.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-installer.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-settings.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-smtp-settings.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-smtp-service.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-smtp-diagnostics.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-campaigns.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-queue.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-suppression.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-user-management.php';

// Admin includes (only in admin context).
if ( is_admin() ) {
	require_once MNEM_PLUGIN_DIR . 'admin/class-admin.php';
	require_once MNEM_PLUGIN_DIR . 'admin/class-admin-menu.php';
}

/**
 * Activation hook — runs on network activation.
 */
function mnem_activate() {
	MNEM_Installer::install();
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
	MNEM_Logger::init();
	MNEM_Settings::init();
	MNEM_SMTP_Service::init();
	MNEM_REST_API::init();
	MNEM_Queue::init();

	if ( is_admin() ) {
		MNEM_Admin::init();
		MNEM_Admin_Menu::init();
	}
}
add_action( 'plugins_loaded', 'mnem_init' );

/**
 * Run DB upgrades when version changes.
 */
function mnem_maybe_upgrade() {
	$installed_db_version = get_site_option( 'mnem_db_version', '0' );
	if ( version_compare( $installed_db_version, MNEM_DB_VERSION, '<' ) ) {
		MNEM_Installer::install();
		update_site_option( 'mnem_db_version', MNEM_DB_VERSION );
	}
}
add_action( 'plugins_loaded', 'mnem_maybe_upgrade', 5 );
