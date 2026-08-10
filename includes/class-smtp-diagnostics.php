<?php
/**
 * SMTP Diagnostics — test connection and send test email.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_SMTP_Diagnostics {

	/**
	 * Attempt to open a socket connection to the configured SMTP host/port.
	 *
	 * @return array {
	 *     @type bool   $success
	 *     @type string $message Human-readable result.
	 * }
	 */
	public static function test_connection() {
		$host    = MNEM_SMTP_Settings::get( 'host', '' );
		$port    = (int) MNEM_SMTP_Settings::get( 'port', 587 );
		$timeout = 10;

		if ( empty( $host ) ) {
			return array(
				'success' => false,
				'message' => __( 'SMTP host is not configured.', 'mnem' ),
			);
		}

		$connection = @fsockopen( $host, $port, $errno, $errstr, $timeout ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( is_resource( $connection ) ) {
			fclose( $connection );
			MNEM_Logger::info( 'smtp', 'Connection test succeeded', array( 'host' => $host, 'port' => $port ) );
			return array(
				'success' => true,
				/* translators: 1: host, 2: port */
				'message' => sprintf( __( 'Successfully connected to %1$s:%2$d.', 'mnem' ), esc_html( $host ), $port ),
			);
		}

		MNEM_Logger::error( 'smtp', 'Connection test failed', array( 'host' => $host, 'port' => $port, 'errno' => $errno ) );
		return array(
			'success' => false,
			/* translators: 1: host, 2: port, 3: error string */
			'message' => sprintf( __( 'Could not connect to %1$s:%2$d — %3$s.', 'mnem' ), esc_html( $host ), $port, esc_html( $errstr ) ),
		);
	}

	/**
	 * Send a test email via wp_mail.
	 *
	 * @param string $recipient Recipient email address.
	 * @return array {
	 *     @type bool   $success
	 *     @type string $message
	 * }
	 */
	public static function send_test_email( $recipient ) {
		$recipient = sanitize_email( $recipient );

		if ( ! is_email( $recipient ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid recipient email address.', 'mnem' ),
			);
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] SMTP Test Email', 'mnem' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: %s: site name */
			__( 'This is a test email sent from the Multisite Network Email Manager plugin on %s.', 'mnem' ),
			get_bloginfo( 'name' )
		);

		$sent = wp_mail( $recipient, $subject, $message );

		if ( $sent ) {
			MNEM_Logger::info( 'smtp', 'Test email sent', array( 'recipient' => $recipient ) );
			return array(
				'success' => true,
				/* translators: %s: recipient */
				'message' => sprintf( __( 'Test email sent to %s.', 'mnem' ), esc_html( $recipient ) ),
			);
		}

		MNEM_Logger::error( 'smtp', 'Test email failed to send', array( 'recipient' => $recipient ) );
		return array(
			'success' => false,
			'message' => __( 'Failed to send test email. Check your SMTP settings and server logs.', 'mnem' ),
		);
	}
}
