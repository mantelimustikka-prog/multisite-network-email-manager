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

	const ROUTE_NAMESPACE = 'mnem/v1';

	/**
	 * Register REST hooks.
	 */
	public static function init() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		}
	}

	/**
	 * Register all REST routes.
	 */
	public static function register_routes() {
		if ( ! function_exists( 'register_rest_route' ) || ! class_exists( 'WP_REST_Server' ) ) {
			return;
		}

		// --- Status endpoint (public health-check) ----------------------
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_status' ),
				'permission_callback' => '__return_true',
			)
		);

		// --- SMTP settings (network admin only) -------------------------
		register_rest_route(
			self::ROUTE_NAMESPACE,
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
					'args'                => self::smtp_settings_schema(),
				),
			)
		);

		// --- Queue (network admin only) ---------------------------------
		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/queue',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_queue' ),
				'permission_callback' => array( __CLASS__, 'check_network_admin' ),
			)
		);

		// --- Suppression (network admin only) ---------------------------
		register_rest_route(
			self::ROUTE_NAMESPACE,
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
	// Argument schemas
	// -------------------------------------------------------------------------

	/**
	 * Return the JSON Schema args array for the SMTP settings update endpoint.
	 *
	 * WordPress REST API uses this for automatic sanitization and validation
	 * before the callback is invoked.
	 *
	 * @return array
	 */
	private static function smtp_settings_schema() {
		return array(
			'enabled'        => array(
				'type'              => 'boolean',
				'required'          => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_boolean' ),
			),
			'host'           => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'port'           => array(
				'type'              => 'integer',
				'required'          => false,
				'minimum'           => 1,
				'maximum'           => 65535,
				'sanitize_callback' => 'absint',
			),
			'encryption'     => array(
				'type'              => 'string',
				'required'          => false,
				'enum'              => array( '', 'ssl', 'tls' ),
				'sanitize_callback' => 'sanitize_text_field',
			),
			'auth_enabled'   => array(
				'type'              => 'boolean',
				'required'          => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_boolean' ),
			),
			'username'       => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'password'       => array(
				'type'     => 'string',
				'required' => false,
				// No sanitize_callback — password chars must not be stripped.
			),
			'from_email'     => array(
				'type'              => 'string',
				'format'            => 'email',
				'required'          => false,
				'sanitize_callback' => 'sanitize_email',
			),
			'from_name'      => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'reply_to_email' => array(
				'type'              => 'string',
				'format'            => 'email',
				'required'          => false,
				'sanitize_callback' => 'sanitize_email',
			),
			'reply_to_name'  => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'debug_mode'     => array(
				'type'              => 'boolean',
				'required'          => false,
				'sanitize_callback' => array( __CLASS__, 'sanitize_boolean' ),
			),
		);
	}

	/**
	 * Sanitize booleans with a fallback for older WordPress versions.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public static function sanitize_boolean( $value ) {
		if ( function_exists( 'rest_sanitize_boolean' ) ) {
			return rest_sanitize_boolean( $value );
		}
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
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
	public static function update_smtp_settings( $request ) {
		$params = array();
		if ( class_exists( 'WP_REST_Request' ) && $request instanceof WP_REST_Request ) {
			$params = (array) $request->get_json_params();
			if ( empty( $params ) ) {
				$params = (array) $request->get_params();
			}
		}
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
	public static function add_suppression( $request ) {
		$email  = '';
		$reason = '';
		if ( class_exists( 'WP_REST_Request' ) && $request instanceof WP_REST_Request ) {
			$email  = sanitize_email( $request->get_param( 'email' ) );
			$reason = sanitize_text_field( $request->get_param( 'reason' ) );
		}
		$result = MNEM_Suppression::add( $email, $reason );
		return rest_ensure_response( array( 'added' => (bool) $result ) );
	}
}
