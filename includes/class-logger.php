<?php
/**
 * Logger — safe event/error logging API for all modules.
 *
 * Usage:
 *   MNEM_Logger::log( 'smtp', 'info', 'Connection established', [ 'host' => 'smtp.example.com' ] );
 *   MNEM_Logger::error( 'queue', 'Job failed', [ 'job_id' => 42 ] );
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_Logger {

	/** @var bool Whether debug mode is active. */
	private static $debug = false;

	/**
	 * Initialise the logger (reads settings).
	 */
	public static function init() {
		self::$debug = (bool) MNEM_Settings::get( 'debug_mode', false );
	}

	/**
	 * Log a message.
	 *
	 * @param string $module  The module emitting the log entry (e.g. 'smtp', 'queue').
	 * @param string $level   Severity: 'debug', 'info', 'warning', 'error'.
	 * @param string $message Human-readable message. Must not contain secrets.
	 * @param array  $context Optional key/value context. Values are sanitized before storage.
	 */
	public static function log( $module, $level, $message, array $context = array() ) {
		$allowed_levels = array( 'debug', 'info', 'warning', 'error' );
		$level          = in_array( $level, $allowed_levels, true ) ? $level : 'info';

		// Skip debug entries unless debug mode is on.
		if ( 'debug' === $level && ! self::$debug ) {
			return;
		}

		global $wpdb;

		$table = $wpdb->base_prefix . 'mnem_logs';

		// Sanitize context: strip password-like keys to avoid accidental secret logging.
		$safe_context = self::sanitize_context( $context );

		$wpdb->insert(
			$table,
			array(
				'module'     => sanitize_key( $module ),
				'level'      => $level,
				'message'    => wp_strip_all_tags( $message ),
				'context'    => wp_json_encode( $safe_context ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Convenience: log an error.
	 *
	 * @param string $module  Module name.
	 * @param string $message Message.
	 * @param array  $context Optional context.
	 */
	public static function error( $module, $message, array $context = array() ) {
		self::log( $module, 'error', $message, $context );
	}

	/**
	 * Convenience: log an info message.
	 *
	 * @param string $module  Module name.
	 * @param string $message Message.
	 * @param array  $context Optional context.
	 */
	public static function info( $module, $message, array $context = array() ) {
		self::log( $module, 'info', $message, $context );
	}

	/**
	 * Strip sensitive keys from context before storing.
	 *
	 * @param array $context Raw context.
	 * @return array Sanitized context.
	 */
	private static function sanitize_context( array $context ) {
		$sensitive_keys = array( 'password', 'pass', 'secret', 'token', 'api_key', 'auth' );
		$safe           = array();
		foreach ( $context as $key => $value ) {
			$lower_key = strtolower( (string) $key );
			foreach ( $sensitive_keys as $sensitive ) {
				if ( false !== strpos( $lower_key, $sensitive ) ) {
					$safe[ $key ] = '***';
					continue 2;
				}
			}
			// Scalar values only; cast objects/arrays to strings.
			$safe[ $key ] = is_scalar( $value ) ? $value : wp_json_encode( $value );
		}
		return $safe;
	}
}
