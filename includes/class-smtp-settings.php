<?php
/**
 * SMTP Settings — storage and retrieval of SMTP configuration.
 *
 * Settings are stored as network options using MNEM_Settings.
 * Passwords are stored with basic obfuscation; never in plain text in logs.
 * The default `b64:` format is intentionally backward-compatible obfuscation,
 * not strong encryption.
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

	/** Sentinel value used in UI to indicate "keep existing password". */
	const PASSWORD_PLACEHOLDER = '********';

	/**
	 * Retrieve all SMTP settings.
	 *
	 * @param bool $include_password Whether to return the real plaintext password.
	 * @return array
	 */
	public static function get_all( $include_password = false ) {
		$settings = array();
		foreach ( self::DEFAULTS as $key => $default ) {
			$settings[ $key ] = MNEM_Settings::get( 'smtp_' . $key, $default );
		}

		// Decrypt the stored password before use.
		$settings['password'] = self::decrypt_password( (string) $settings['password'] );

		if ( ! $include_password ) {
			$settings['password'] = '' !== $settings['password'] ? self::PASSWORD_PLACEHOLDER : '';
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
		$value          = MNEM_Settings::get( 'smtp_' . $key, $stored_default );

		// Transparently decrypt password on retrieval.
		if ( 'password' === $key ) {
			$value = self::decrypt_password( (string) $value );
		}

		return $value;
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

			// Do not overwrite password when the placeholder sentinel is submitted.
			if ( 'password' === $key ) {
				if ( self::PASSWORD_PLACEHOLDER === $value || '' === $value ) {
					continue;
				}
				$value = self::encrypt_password( $value );
			}

			MNEM_Settings::set( 'smtp_' . $key, $value );
		}
	}

	/**
	 * Encrypt a password before storing it.
	 *
	 * Uses base64-style obfuscation by default (not cryptographic encryption).
	 * Site owners can replace this with a stronger
	 * scheme by filtering `mnem_encrypt_smtp_password`.
	 *
	 * @param string $plaintext Plaintext password.
	 * @return string Encrypted value.
	 */
	private static function encrypt_password( $plaintext ) {
		/**
		 * Filter to replace the default base64 password obfuscation.
		 *
		 * @param string|null $encrypted  Return a non-null string to short-circuit the default.
		 * @param string      $plaintext  The plaintext password.
		 */
		$custom = apply_filters( 'mnem_encrypt_smtp_password', null, $plaintext );
		if ( null !== $custom ) {
			return (string) $custom;
		}
		return 'b64:' . base64_encode( $plaintext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a stored password.
	 *
	 * Handles the `b64:` prefix added by `encrypt_password()` and legacy
	 * plain-text values that pre-date this feature.
	 *
	 * @param string $stored Stored value.
	 * @return string Plaintext password.
	 */
	private static function decrypt_password( $stored ) {
		/**
		 * Filter to replace the default base64 password decryption.
		 *
		 * @param string|null $decrypted  Return a non-null string to short-circuit the default.
		 * @param string      $stored     The stored value.
		 */
		$custom = apply_filters( 'mnem_decrypt_smtp_password', null, $stored );
		if ( null !== $custom ) {
			return (string) $custom;
		}
		if ( 0 === strpos( $stored, 'b64:' ) ) {
			$decoded = base64_decode( substr( $stored, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			return false !== $decoded ? $decoded : '';
		}
		// Legacy plain-text — return as-is.
		return $stored;
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
				// Return as-is; strip_tags/sanitize_text_field would corrupt special chars.
				return (string) $value;

			default:
				return sanitize_text_field( $value );
		}
	}
}
