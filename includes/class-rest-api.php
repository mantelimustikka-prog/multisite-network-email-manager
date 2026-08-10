<?php
/**
 * REST API — placeholder namespace and route registration.
 *
 * Routes live under /wp-json/mnem/v1/
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_REST_API {

	const NAMESPACE = 'mnem/v1';

	/**
	 * Register REST hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 */
	public static function register_routes() {
		// --- Status endpoint (public health-check) ----------------------
		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_status' ),
				'permission_callback' => '__return_true',
			)
		);

		// --- SMTP settings (network admin only) -------------------------
		register_rest_route(
			self::NAMESPACE,
			'/smtp/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_smtp_settings' ),
					'permission_callback' => array( __CLASS__, 'check_network_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( __CLASS__, 'update_smtp_settings' ),
					'permission_callback' => array( __CLASS__, 'check_network_admin' ),
				),
			)
		);

		// --- Queue (network admin only) ---------------------------------
		register_rest_route(
			self::NAMESPACE,
			'/queue',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_queue' ),
				'permission_callback' => array( __CLASS__, 'check_network_admin' ),
			)
		);

		// --- Suppression (network admin only) ---------------------------
		register_rest_route(
			self::NAMESPACE,
			'/suppression',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'get_suppression' ),
					'permission_callback' => array( __CLASS__, 'check_network_admin' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'add_suppression' ),
					'permission_callback' => array( __CLASS__, 'check_network_admin' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Only super admins may access network management endpoints.
	 *
	 * @return bool|WP_Error
	 */
	public static function check_network_admin() {
		if ( ! current_user_can( 'manage_network' ) ) {
			return new WP_Error(
				'mnem_forbidden',
				__( 'You do not have permission to access this endpoint.', 'mnem' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	// -------------------------------------------------------------------------
	// Route callbacks (stubs — implement in module classes)
	// -------------------------------------------------------------------------

	/**
	 * GET /mnem/v1/status
	 *
	 * @return WP_REST_Response
	 */
	public static function get_status() {
		return rest_ensure_response(
			array(
				'plugin'     => 'Multisite Network Email Manager',
				'version'    => MNEM_VERSION,
				'db_version' => MNEM_DB_VERSION,
				'status'     => 'ok',
			)
		);
	}

	/**
	 * GET /mnem/v1/smtp/settings
	 *
	 * @return WP_REST_Response
	 */
	public static function get_smtp_settings() {
		return rest_ensure_response( MNEM_SMTP_Settings::get_all() );
	}

	/**
	 * PUT/PATCH /mnem/v1/smtp/settings
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function update_smtp_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		MNEM_SMTP_Settings::save( $params );
		return rest_ensure_response( array( 'updated' => true ) );
	}

	/**
	 * GET /mnem/v1/queue
	 *
	 * @return WP_REST_Response
	 */
	public static function get_queue() {
		return rest_ensure_response( array( 'queue' => array() ) );
	}

	/**
	 * GET /mnem/v1/suppression
	 *
	 * @return WP_REST_Response
	 */
	public static function get_suppression() {
		return rest_ensure_response( MNEM_Suppression::get_list() );
	}

	/**
	 * POST /mnem/v1/suppression
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function add_suppression( WP_REST_Request $request ) {
		$email  = sanitize_email( $request->get_param( 'email' ) );
		$reason = sanitize_text_field( $request->get_param( 'reason' ) );
		$result = MNEM_Suppression::add( $email, $reason );
		return rest_ensure_response( array( 'added' => (bool) $result ) );
	}
}
