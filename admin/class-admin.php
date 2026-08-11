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

		// SMTP form handlers.
		add_action( 'network_admin_edit_mnem_save_smtp_settings', array( __CLASS__, 'handle_save_smtp_settings' ) );
		add_action( 'network_admin_edit_mnem_send_test_email', array( __CLASS__, 'handle_send_test_email' ) );
		add_action( 'network_admin_edit_mnem_test_connection', array( __CLASS__, 'handle_test_connection' ) );

		// Campaign form handlers.
		add_action( 'network_admin_edit_mnem_save_campaign', array( __CLASS__, 'handle_save_campaign' ) );
		add_action( 'network_admin_edit_mnem_campaign_status', array( __CLASS__, 'handle_campaign_status' ) );

		// Suppression form handlers.
		add_action( 'network_admin_edit_mnem_add_suppression', array( __CLASS__, 'handle_add_suppression' ) );
		add_action( 'network_admin_edit_mnem_remove_suppression', array( __CLASS__, 'handle_remove_suppression' ) );

		// Queue bulk actions.
		add_action( 'network_admin_edit_mnem_retry_failed_queue', array( __CLASS__, 'handle_retry_failed_queue' ) );
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

		if ( ! self::ensure_class_available( 'MNEM_SMTP_Settings', 'mnem-smtp-settings' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$active_tab = isset( $_POST['mnem_smtp_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['mnem_smtp_active_tab'] ) ) : 'smtp';
		$data       = MNEM_SMTP_Settings::get_all( true );

		if ( 'sender' === $active_tab ) {
			$data['sender_mode']  = isset( $_POST['mnem_smtp_sender_mode'] ) ? sanitize_key( wp_unslash( $_POST['mnem_smtp_sender_mode'] ) ) : 'network_global';
			$data['force_sender'] = isset( $_POST['mnem_smtp_force_sender'] );
		} elseif ( 'content' === $active_tab ) {
			$data['global_header'] = isset( $_POST['mnem_smtp_global_header'] ) ? wp_kses_post( wp_unslash( $_POST['mnem_smtp_global_header'] ) ) : '';
			$data['global_footer'] = isset( $_POST['mnem_smtp_global_footer'] ) ? wp_kses_post( wp_unslash( $_POST['mnem_smtp_global_footer'] ) ) : '';
		} else {
			$data['enabled']               = isset( $_POST['mnem_smtp_enabled'] );
			$data['provider']              = isset( $_POST['mnem_smtp_provider'] ) ? sanitize_key( wp_unslash( $_POST['mnem_smtp_provider'] ) ) : 'custom';
			$data['host']                  = isset( $_POST['mnem_smtp_host'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_host'] ) ) : '';
			$data['port']                  = isset( $_POST['mnem_smtp_port'] ) ? absint( $_POST['mnem_smtp_port'] ) : 587;
			$data['encryption']            = isset( $_POST['mnem_smtp_encryption'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_encryption'] ) ) : 'tls';
			$data['auth_enabled']          = isset( $_POST['mnem_smtp_auth_enabled'] );
			$data['username']              = isset( $_POST['mnem_smtp_username'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_username'] ) ) : '';
			$data['password']              = isset( $_POST['mnem_smtp_password'] ) ? wp_unslash( $_POST['mnem_smtp_password'] ) : MNEM_SMTP_Settings::PASSWORD_PLACEHOLDER;
			$data['from_email']            = isset( $_POST['mnem_smtp_from_email'] ) ? sanitize_email( wp_unslash( $_POST['mnem_smtp_from_email'] ) ) : '';
			$data['from_name']             = isset( $_POST['mnem_smtp_from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_from_name'] ) ) : '';
			$data['reply_to_email']        = isset( $_POST['mnem_smtp_reply_to_email'] ) ? sanitize_email( wp_unslash( $_POST['mnem_smtp_reply_to_email'] ) ) : '';
			$data['reply_to_name']         = isset( $_POST['mnem_smtp_reply_to_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_smtp_reply_to_name'] ) ) : '';
			$data['debug_mode']            = isset( $_POST['mnem_smtp_debug_mode'] );
			$data['rate_limit_per_minute'] = isset( $_POST['mnem_smtp_rate_limit_per_minute'] ) ? absint( $_POST['mnem_smtp_rate_limit_per_minute'] ) : 0;
			$data['rate_limit_per_hour']   = isset( $_POST['mnem_smtp_rate_limit_per_hour'] ) ? absint( $_POST['mnem_smtp_rate_limit_per_hour'] ) : 0;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		try {
			MNEM_SMTP_Settings::save( $data );
			self::add_notice( __( 'SMTP settings saved.', 'mnem' ), 'success' );
		} catch ( Throwable $e ) {
			self::add_notice( __( 'Failed to save SMTP settings.', 'mnem' ), 'error' );
		}

		self::redirect_to_page( 'mnem-smtp-settings', array( 'tab' => $active_tab ) );
	}

	/**
	 * Handle test connection request.
	 */
	public static function handle_test_connection() {
		check_admin_referer( 'mnem_smtp_test', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		if ( ! self::ensure_class_available( 'MNEM_SMTP_Diagnostics', 'mnem-smtp-settings' ) ) {
			return;
		}

		try {
			$result  = MNEM_SMTP_Diagnostics::test_connection();
			$success = ! empty( $result['success'] );
			$message = isset( $result['message'] ) ? (string) $result['message'] : __( 'SMTP connection test completed.', 'mnem' );
			self::add_notice( $message, $success ? 'success' : 'error' );
		} catch ( Throwable $e ) {
			self::add_notice( __( 'SMTP connection test failed unexpectedly.', 'mnem' ), 'error' );
		}

		self::redirect_to_page( 'mnem-smtp-settings' );
	}

	/**
	 * Handle send test email request.
	 */
	public static function handle_send_test_email() {
		check_admin_referer( 'mnem_smtp_test', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		if ( ! self::ensure_class_available( 'MNEM_SMTP_Diagnostics', 'mnem-smtp-settings' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
		$recipient = isset( $_POST['mnem_test_recipient'] ) ? sanitize_email( wp_unslash( $_POST['mnem_test_recipient'] ) ) : '';

		try {
			$result  = MNEM_SMTP_Diagnostics::send_test_email( $recipient );
			$success = ! empty( $result['success'] );
			$message = isset( $result['message'] ) ? (string) $result['message'] : __( 'SMTP test email request completed.', 'mnem' );
			self::add_notice( $message, $success ? 'success' : 'error' );
		} catch ( Throwable $e ) {
			self::add_notice( __( 'SMTP test email failed unexpectedly.', 'mnem' ), 'error' );
		}

		self::redirect_to_page( 'mnem-smtp-settings' );
	}

	// -------------------------------------------------------------------------
	// Campaign handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle campaign create/update form submission.
	 */
	public static function handle_save_campaign() {
		check_admin_referer( 'mnem_save_campaign', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$campaign_id = isset( $_POST['mnem_campaign_id'] ) ? absint( $_POST['mnem_campaign_id'] ) : 0;
		$data        = array(
			'name'    => isset( $_POST['mnem_campaign_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_campaign_name'] ) ) : '',
			'subject' => isset( $_POST['mnem_campaign_subject'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_campaign_subject'] ) ) : '',
			'body'    => isset( $_POST['mnem_campaign_body'] ) ? wp_kses_post( wp_unslash( $_POST['mnem_campaign_body'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( $campaign_id > 0 ) {
			MNEM_Campaigns::update( $campaign_id, $data );
			self::add_notice( __( 'Campaign updated.', 'mnem' ), 'success' );
		} else {
			$campaign_id = MNEM_Campaigns::create( $data );
			if ( $campaign_id ) {
				self::add_notice( __( 'Campaign created.', 'mnem' ), 'success' );
			} else {
				self::add_notice( __( 'Failed to create campaign.', 'mnem' ), 'error' );
				$campaign_id = 0;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => 'mnem-campaigns',
					'id'   => $campaign_id,
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle campaign status transition.
	 */
	public static function handle_campaign_status() {
		check_admin_referer( 'mnem_campaign_status', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$id         = isset( $_POST['mnem_campaign_id'] ) ? absint( $_POST['mnem_campaign_id'] ) : 0;
		$new_status = isset( $_POST['mnem_new_status'] ) ? sanitize_key( $_POST['mnem_new_status'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$result = MNEM_Campaigns::transition_status( $id, $new_status );
		if ( is_wp_error( $result ) ) {
			self::add_notice( $result->get_error_message(), 'error' );
		} else {
			self::add_notice( __( 'Campaign status updated.', 'mnem' ), 'success' );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'mnem-campaigns' ),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Suppression handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle adding an email to the suppression list.
	 */
	public static function handle_add_suppression() {
		check_admin_referer( 'mnem_add_suppression', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$email  = isset( $_POST['mnem_suppression_email'] ) ? sanitize_email( wp_unslash( $_POST['mnem_suppression_email'] ) ) : '';
		$reason = isset( $_POST['mnem_suppression_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['mnem_suppression_reason'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( MNEM_Suppression::add( $email, $reason ) ) {
			self::add_notice( __( 'Email added to suppression list.', 'mnem' ), 'success' );
		} else {
			self::add_notice( __( 'Failed to add email. Make sure it is a valid address.', 'mnem' ), 'error' );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'mnem-suppression' ),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle removing an email from the suppression list.
	 */
	public static function handle_remove_suppression() {
		check_admin_referer( 'mnem_remove_suppression', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$email = isset( $_POST['mnem_suppression_email'] ) ? sanitize_email( wp_unslash( $_POST['mnem_suppression_email'] ) ) : '';

		if ( MNEM_Suppression::remove( $email ) ) {
			self::add_notice( __( 'Email removed from suppression list.', 'mnem' ), 'success' );
		} else {
			self::add_notice( __( 'Failed to remove email.', 'mnem' ), 'error' );
		}

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'mnem-suppression' ),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	// -------------------------------------------------------------------------
	// Queue handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle retrying all failed queue items.
	 */
	public static function handle_retry_failed_queue() {
		check_admin_referer( 'mnem_retry_queue', 'mnem_nonce' );

		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mnem' ) );
		}

		global $wpdb;
		$table   = $wpdb->base_prefix . 'mnem_queue';
		$updated = $wpdb->update(
			$table,
			array(
				'status'       => 'pending',
				'attempts'     => 0,
				'scheduled_at' => current_time( 'mysql', true ),
			),
			array( 'status' => 'failed' ),
			array( '%s', '%d', '%s' ),
			array( '%s' )
		);

		/* translators: %d: number of jobs requeued */
		self::add_notice( sprintf( __( '%d failed job(s) re-queued.', 'mnem' ), (int) $updated ), 'success' );

		wp_safe_redirect(
			add_query_arg(
				array( 'page' => 'mnem-queue' ),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Redirect to a network admin page.
	 *
	 * @param string $page Menu slug page.
	 */
	private static function redirect_to_page( $page, $args = array() ) {
		wp_safe_redirect(
			add_query_arg(
				array_merge(
					array( 'page' => $page ),
					$args
				),
				network_admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Ensure required class exists before handling an action.
	 *
	 * @param string $class_name Class name.
	 * @param string $page       Redirect page slug.
	 * @return bool
	 */
	private static function ensure_class_available( $class_name, $page = 'mnem-dashboard' ) {
		if ( class_exists( $class_name ) ) {
			return true;
		}
		self::add_notice( __( 'Required plugin module is unavailable.', 'mnem' ), 'error' );
		self::redirect_to_page( $page );
		return false;
	}
}
