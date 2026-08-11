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

	/** @var array|null Cached resolved SMTP settings with decrypted password. */
	private static $resolved_settings = null;

	/** Setting keys and their defaults. */
	const DEFAULTS = array(
		'enabled'               => false,
		'provider'              => 'custom',
		'host'                  => '',
		'port'                  => 587,
		'encryption'            => 'tls',
		'auth_enabled'          => true,
		'username'              => '',
		'password'              => '',
		'from_email'            => '',
		'from_name'             => '',
		'reply_to_email'        => '',
		'reply_to_name'         => '',
		'test_recipient'        => '',
		'debug_mode'            => false,
		'rate_limit_per_minute' => 0,
		'rate_limit_per_hour'   => 0,
		'sender_mode'           => 'network_global',
		'force_sender'          => false,
		'global_header'         => '',
		'global_footer'         => '',
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
		if ( null === self::$resolved_settings ) {
			$settings = array();
			foreach ( self::DEFAULTS as $key => $default ) {
				$settings[ $key ] = MNEM_Settings::get( 'smtp_' . $key, $default );
			}

			$settings = self::apply_provider_defaults( $settings );

			// Decrypt the stored password before use.
			$settings['password'] = self::decrypt_password( (string) $settings['password'] );

			self::$resolved_settings = $settings;
		}

		$settings = self::$resolved_settings;

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
		$settings       = self::get_all( true );

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $stored_default;
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

		self::$resolved_settings = null;
	}

	/**
	 * Get built-in SMTP provider presets.
	 *
	 * @return array
	 */
	public static function get_provider_presets() {
		return array(
			'custom'   => array(
				'label'           => __( 'Custom SMTP', 'mnem' ),
				'host'            => '',
				'port'            => 587,
				'encryption'      => 'tls',
				'auth_enabled'    => true,
				'default_username' => '',
				'credential_label' => __( 'SMTP Password / Token', 'mnem' ),
				'username_label'   => __( 'SMTP Username', 'mnem' ),
				'help'             => __( 'Use this option for any SMTP provider not listed below or when you need to define custom connection details.', 'mnem' ),
			),
			'brevo'    => array(
				'label'           => __( 'Brevo', 'mnem' ),
				'host'            => 'smtp-relay.brevo.com',
				'port'            => 587,
				'encryption'      => 'tls',
				'auth_enabled'    => true,
				'default_username' => '',
				'credential_label' => __( 'Brevo SMTP Key / Password', 'mnem' ),
				'username_label'   => __( 'Brevo SMTP Login', 'mnem' ),
				'help'             => __( 'Brevo commonly uses smtp-relay.brevo.com on port 587 with STARTTLS. Use your Brevo SMTP login and SMTP key.', 'mnem' ),
			),
			'sendgrid' => array(
				'label'           => __( 'SendGrid', 'mnem' ),
				'host'            => 'smtp.sendgrid.net',
				'port'            => 587,
				'encryption'      => 'tls',
				'auth_enabled'    => true,
				'default_username' => 'apikey',
				'credential_label' => __( 'SendGrid API Key', 'mnem' ),
				'username_label'   => __( 'SendGrid Username', 'mnem' ),
				'help'             => __( 'SendGrid SMTP typically uses smtp.sendgrid.net on port 587 with username "apikey" and your API key as the password.', 'mnem' ),
			),
			'mailgun'  => array(
				'label'           => __( 'Mailgun', 'mnem' ),
				'host'            => 'smtp.mailgun.org',
				'port'            => 587,
				'encryption'      => 'tls',
				'auth_enabled'    => true,
				'default_username' => '',
				'credential_label' => __( 'Mailgun SMTP Password', 'mnem' ),
				'username_label'   => __( 'Mailgun SMTP Username', 'mnem' ),
				'help'             => __( 'Mailgun SMTP commonly uses smtp.mailgun.org on port 587 with STARTTLS. If your account uses an EU region endpoint, update the host accordingly.', 'mnem' ),
			),
			'smtp2go'  => array(
				'label'           => __( 'SMTP2GO', 'mnem' ),
				'host'            => 'mail.smtp2go.com',
				'port'            => 587,
				'encryption'      => 'tls',
				'auth_enabled'    => true,
				'default_username' => '',
				'credential_label' => __( 'SMTP2GO Password / API Key', 'mnem' ),
				'username_label'   => __( 'SMTP2GO Username', 'mnem' ),
				'help'             => __( 'SMTP2GO typically uses mail.smtp2go.com on port 587 with STARTTLS. Use your SMTP username and password or API key if your account is configured for it.', 'mnem' ),
			),
			'postmark' => array(
				'label'           => __( 'Postmark', 'mnem' ),
				'host'            => 'smtp.postmarkapp.com',
				'port'            => 587,
				'encryption'      => 'tls',
				'auth_enabled'    => true,
				'default_username' => '',
				'credential_label' => __( 'Postmark Server Token / Password', 'mnem' ),
				'username_label'   => __( 'Postmark SMTP Username', 'mnem' ),
				'help'             => __( 'Postmark SMTP uses smtp.postmarkapp.com on port 587 with STARTTLS. Use the credentials provided for your Postmark server.', 'mnem' ),
			),
			'amazon_ses' => array(
				'label'           => __( 'Amazon SES', 'mnem' ),
				'host'            => 'email-smtp.us-east-1.amazonaws.com',
				'port'            => 587,
				'encryption'      => 'tls',
				'auth_enabled'    => true,
				'default_username' => '',
				'credential_label' => __( 'Amazon SES SMTP Password', 'mnem' ),
				'username_label'   => __( 'Amazon SES SMTP Username', 'mnem' ),
				'help'             => __( 'Amazon SES SMTP endpoints are region-specific. Update the host to match your SES region if needed.', 'mnem' ),
			),
		);
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
			case 'force_sender':
				return (bool) $value;

			case 'port':
				$port = absint( $value );
				return ( $port >= 1 && $port <= 65535 ) ? $port : 587;

			case 'rate_limit_per_minute':
			case 'rate_limit_per_hour':
				return absint( $value );

			case 'encryption':
				$allowed = array( '', 'ssl', 'tls' );
				return in_array( $value, $allowed, true ) ? $value : 'tls';

			case 'provider':
				$provider = sanitize_key( $value );
				return array_key_exists( $provider, self::get_provider_presets() ) ? $provider : 'custom';

			case 'sender_mode':
				$allowed = array( 'master_site', 'child_site', 'network_global' );
				return in_array( $value, $allowed, true ) ? $value : 'network_global';

			case 'from_email':
			case 'reply_to_email':
			case 'test_recipient':
				return sanitize_email( $value );

			case 'host':
			case 'username':
			case 'from_name':
			case 'reply_to_name':
				return sanitize_text_field( $value );

			case 'global_header':
			case 'global_footer':
				return wp_kses_post( $value );

			case 'password':
				// Return as-is; strip_tags/sanitize_text_field would corrupt special chars.
				return (string) $value;

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Fill missing connection values from the selected provider preset.
	 *
	 * @param array $settings Current settings.
	 * @return array
	 */
	private static function apply_provider_defaults( array $settings ) {
		$provider = isset( $settings['provider'] ) ? $settings['provider'] : 'custom';
		$presets  = self::get_provider_presets();

		if ( ! isset( $presets[ $provider ] ) ) {
			return $settings;
		}

		$preset = $presets[ $provider ];

		$raw_host = get_site_option( MNEM_OPTION_PREFIX . 'smtp_host', null );
		if ( null === $raw_host && ! empty( $preset['host'] ) ) {
			$settings['host'] = $preset['host'];
		}

		$raw_port = get_site_option( MNEM_OPTION_PREFIX . 'smtp_port', null );
		if ( null === $raw_port && ! empty( $preset['port'] ) ) {
			$settings['port'] = (int) $preset['port'];
		}

		$raw_encryption = get_site_option( MNEM_OPTION_PREFIX . 'smtp_encryption', null );
		if ( null === $raw_encryption && isset( $preset['encryption'] ) ) {
			$settings['encryption'] = $preset['encryption'];
		}

		$raw_username = get_site_option( MNEM_OPTION_PREFIX . 'smtp_username', null );
		if ( null === $raw_username && ! empty( $preset['default_username'] ) ) {
			$settings['username'] = $preset['default_username'];
		}

		$raw_auth_enabled = get_site_option( MNEM_OPTION_PREFIX . 'smtp_auth_enabled', null );
		if ( null === $raw_auth_enabled && isset( $preset['auth_enabled'] ) ) {
			$settings['auth_enabled'] = (bool) $preset['auth_enabled'];
		}

		return $settings;
	}
}
