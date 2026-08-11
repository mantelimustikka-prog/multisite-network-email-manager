<?php
/**
 * Admin Menu — network admin menu registration.
 *
 * Registers top-level and sub-menu entries in the Network Admin.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_Admin_Menu {

	/**
	 * Register menu hooks.
	 */
	public static function init() {
		add_action( 'network_admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Add network admin menu pages.
	 */
	public static function register_menu() {
		// Top-level menu.
		add_menu_page(
			__( 'Network Email Manager', 'mnem' ),
			__( 'Email Manager', 'mnem' ),
			'manage_network',
			'mnem-dashboard',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-email-alt',
			30
		);

		// Dashboard sub-page (same as top-level).
		add_submenu_page(
			'mnem-dashboard',
			__( 'Dashboard', 'mnem' ),
			__( 'Dashboard', 'mnem' ),
			'manage_network',
			'mnem-dashboard',
			array( __CLASS__, 'render_dashboard' )
		);

		// SMTP Settings.
		add_submenu_page(
			'mnem-dashboard',
			__( 'SMTP Settings', 'mnem' ),
			__( 'SMTP Settings', 'mnem' ),
			'manage_network',
			'mnem-smtp-settings',
			array( __CLASS__, 'render_smtp_settings' )
		);

		// Campaigns.
		add_submenu_page(
			'mnem-dashboard',
			__( 'Campaigns', 'mnem' ),
			__( 'Campaigns', 'mnem' ),
			'manage_network',
			'mnem-campaigns',
			array( __CLASS__, 'render_campaigns' )
		);

		// Queue.
		add_submenu_page(
			'mnem-dashboard',
			__( 'Send Queue', 'mnem' ),
			__( 'Send Queue', 'mnem' ),
			'manage_network',
			'mnem-queue',
			array( __CLASS__, 'render_queue' )
		);

		// Suppression list.
		add_submenu_page(
			'mnem-dashboard',
			__( 'Suppression List', 'mnem' ),
			__( 'Suppression', 'mnem' ),
			'manage_network',
			'mnem-suppression',
			array( __CLASS__, 'render_suppression' )
		);

		// Logs.
		add_submenu_page(
			'mnem-dashboard',
			__( 'Logs', 'mnem' ),
			__( 'Logs', 'mnem' ),
			'manage_network',
			'mnem-logs',
			array( __CLASS__, 'render_logs' )
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/** Render dashboard page. */
	public static function render_dashboard() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mnem' ) );
		}
		self::include_view( 'dashboard.php' );
	}

	/** Render SMTP settings page. */
	public static function render_smtp_settings() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mnem' ) );
		}
		if ( ! class_exists( 'MNEM_SMTP_Settings' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'SMTP module is unavailable.', 'mnem' ) . '</p></div>';
			return;
		}
		$settings         = MNEM_SMTP_Settings::get_all();
		$provider_presets = MNEM_SMTP_Settings::get_provider_presets();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab       = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'smtp';
		self::include_view( 'smtp-settings.php' );
	}

	/** Render campaigns page. */
	public static function render_campaigns() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mnem' ) );
		}
		if ( ! class_exists( 'MNEM_Campaigns' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Campaigns module is unavailable.', 'mnem' ) . '</p></div>';
			return;
		}
		$campaigns     = MNEM_Campaigns::get_campaigns();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_id       = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$edit_campaign = $edit_id ? MNEM_Campaigns::get_campaign( $edit_id ) : null;
		self::include_view( 'campaigns.php' );
	}

	/** Render queue page. */
	public static function render_queue() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mnem' ) );
		}
		self::include_view( 'queue.php' );
	}

	/** Render suppression list page. */
	public static function render_suppression() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mnem' ) );
		}
		if ( ! class_exists( 'MNEM_Suppression' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Suppression module is unavailable.', 'mnem' ) . '</p></div>';
			return;
		}
		$suppression_list = MNEM_Suppression::get_list();
		self::include_view( 'suppression.php' );
	}

	/** Render logs page. */
	public static function render_logs() {
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mnem' ) );
		}
		self::include_view( 'logs.php' );
	}

	/**
	 * Include a view file safely.
	 *
	 * @param string $view Relative path under admin/views.
	 */
	private static function include_view( $view ) {
		$path = MNEM_PLUGIN_DIR . 'admin/views/' . ltrim( $view, '/' );
		if ( file_exists( $path ) ) {
			include $path;
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Required admin view is missing.', 'mnem' ) . '</p></div>';
	}
}
