<?php
/**
 * Admin — general admin-area initialisation.
 *
 * Registers admin notices and enqueues shared admin assets.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_Admin {

	/**
	 * Register admin hooks.
	 */
	public static function init() {
		add_action( 'network_admin_notices', array( __CLASS__, 'display_notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );

		// Handle settings save and SMTP test actions.
		add_action( 'network_admin_edit_mnem_save_smtp_settings', array( __CLASS__, 'handle_save_smtp_settings' ) );
		add_action( 'network_admin_edit_mnem_send_test_email', array( __CLASS__, 'handle_send_test_email' ) );
		add_action( 'network_admin_edit_mnem_test_connection', array( __CLASS__, 'handle_test_connection' ) );
	}

	/**
	 * Enqueue admin scripts and styles on plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'mnem' ) ) {
			return;
		}

		wp_enqueue_style(
			'mnem-admin',
			MNEM_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			MNEM_VERSION
		);
	}

	/**
	 * Display admin notices stored in a transient.
	 */
	public static function display_notices() {
		$notices = get_site_transient( 'mnem_admin_notices' );
		if ( empty( $notices ) || ! is_array( $notices ) ) {
			return;
		}

		delete_site_transient( 'mnem_admin_notices' );

		foreach ( $notices as $notice ) {
			$type    = isset( $notice['type'] ) ? sanitize_html_class( $notice['type'] ) : 'info';
			$message = isset( $notice['message'] ) ? wp_kses_post( $notice['message'] ) : '';
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . $message . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Queue an admin notice to be displayed on the next page load.
	 *
	 * @param string $message Notice text.
	 * @param string $type    'success', 'error', 'warning', or 'info'.
	 */
	public static function add_notice( $message, $type = 'info' ) {
		$notices   = get_site_transient( 'mnem_admin_notices' );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = array(
			'message' => $message,
			'type'    => $type,
		);
		set_site_transient( 'mnem_admin_notices', $notices, 60 );
	}

	// -------------------------------------------------------------------------
	// Form handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle SMTP settings save (network_admin_edit_{action}).
	 */
	public static function handle_save_smtp_settings() {
		check_admin_referer( 'mnem_smtp_settings', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$data = array(
			'enabled'        => isset( $_POST['mnem_smtp_enabled'] ),
			'host'           => isset( $_POST['mnem_smtp_host'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_host'] ) ) : '',
			'port'           => isset( $_POST['mnem_smtp_port'] ) ? absint( $_POST['mnem_smtp_port'] ) : 587,
			'encryption'     => isset( $_POST['mnem_smtp_encryption'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_encryption'] ) ) : 'tls',
			'auth_enabled'   => isset( $_POST['mnem_smtp_auth_enabled'] ),
			'username'       => isset( $_POST['mnem_smtp_username'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_username'] ) ) : '',
			'password'       => isset( $_POST['mnem_smtp_password'] ) ? wp_unslash( $_POST['mnem_smtp_password'] ) : '',
			'from_email'     => isset( $_POST['mnem_smtp_from_email'] ) ? sanitize_email( wp_unslash( $_POST['mnem_smtp_from_email'] ) ) : '',
			'from_name'      => isset( $_POST['mnem_smtp_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_from_name'] ) ) : '',
			'reply_to_email' => isset( $_POST['mnem_smtp_reply_to_email'] ) ? sanitize_email( wp_unslash( $_POST['mnem_smtp_reply_to_email'] ) ) : '',
			'reply_to_name'  => isset( $_POST['mnem_smtp_reply_to_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_reply_to_name'] ) ) : '',
			'debug_mode'     => isset( $_POST['mnem_smtp_debug_mode'] ),
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		MNEM_SMTP_Settings::save( $data );
		self::add_notice( __( 'SMTP settings saved.', 'mnem' ), 'success' );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'mnem-smtp-settings' ),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle test connection request.
	 */
	public static function handle_test_connection() {
		check_admin_referer( 'mnem_smtp_test', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		$result = MNEM_SMTP_Diagnostics::test_connection();
		$type   = $result['success'] ? 'success' : 'error';
		self::add_notice( $result['message'], $type );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'mnem-smtp-settings' ),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle send test email request.
	 */
	public static function handle_send_test_email() {
		check_admin_referer( 'mnem_smtp_test', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$recipient = isset( $_POST['mnem_test_recipient'] ) ? sanitize_email( wp_unslash( $_POST['mnem_test_recipient'] ) ) : '';

		$result = MNEM_SMTP_Diagnostics::send_test_email( $recipient );
		$type   = $result['success'] ? 'success' : 'error';
		self::add_notice( $result['message'], $type );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'mnem-smtp-settings' ),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
