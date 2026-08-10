<?php
/**
 * SMTP Settings — storage and retrieval of SMTP configuration.
 *
 * Settings are stored as network options using MNEM_Settings.
 * Passwords are stored with basic obfuscation; never in plain text in logs.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_SMTP_Settings {

	/** Setting keys and their defaults. */
	const DEFAULTS = array(
		'enabled'        => false,
		'host'           => '',
		'port'           => 587,
		'encryption'     => 'tls',
		'auth_enabled'   => true,
		'username'       => '',
		'password'       => '',
		'from_email'     => '',
		'from_name'      => '',
		'reply_to_email' => '',
		'reply_to_name'  => '',
		'test_recipient' => '',
		'debug_mode'     => false,
	);

	/**
	 * Retrieve all SMTP settings.
	 *
	 * @param bool $include_password Whether to include the password in the result.
	 * @return array
	 */
	public static function get_all( $include_password = false ) {
		$settings = array();
		foreach ( self::DEFAULTS as $key => $default ) {
			$settings[ $key ] = MNEM_Settings::get( 'smtp_' . $key, $default );
		}
		if ( ! $include_password ) {
			$settings['password'] = '' !== $settings['password'] ? '********' : '';
		}
		return $settings;
	}

	/**
	 * Retrieve a single SMTP setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		if ( ! array_key_exists( $key, self::DEFAULTS ) ) {
			return $default;
		}
		$stored_default = null !== $default ? $default : self::DEFAULTS[ $key ];
		return MNEM_Settings::get( 'smtp_' . $key, $stored_default );
	}

	/**
	 * Save SMTP settings from an array.
	 *
	 * @param array $data Raw input data (will be sanitized).
	 */
	public static function save( array $data ) {
		foreach ( self::DEFAULTS as $key => $default ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}

			$value = self::sanitize_field( $key, $data[ $key ] );

			// Special case: do not overwrite password if the masked value is submitted.
			if ( 'password' === $key && '********' === $value ) {
				continue;
			}

			MNEM_Settings::set( 'smtp_' . $key, $value );
		}
	}

	/**
	 * Sanitize an individual field value based on its key.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Raw value.
	 * @return mixed Sanitized value.
	 */
	private static function sanitize_field( $key, $value ) {
		switch ( $key ) {
			case 'enabled':
			case 'auth_enabled':
			case 'debug_mode':
				return (bool) $value;

			case 'port':
				$port = absint( $value );
				return ( $port >= 1 && $port <= 65535 ) ? $port : 587;

			case 'encryption':
				$allowed = array( '', 'ssl', 'tls' );
				return in_array( $value, $allowed, true ) ? $value : 'tls';

			case 'from_email':
			case 'reply_to_email':
			case 'test_recipient':
				return sanitize_email( $value );

			case 'host':
			case 'username':
			case 'from_name':
			case 'reply_to_name':
				return sanitize_text_field( $value );

			case 'password':
				// Store as-is; never sanitize with strip functions that could corrupt special chars.
				return (string) $value;

			default:
				return sanitize_text_field( $value );
		}
	}
}
