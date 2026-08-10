<?php
/**
 * SMTP diagnostics and test actions.
 */
class MNEM_SMTP_Diagnostics {
	/**
	 * Settings instance.
	 *
	 * @var MNEM_SMTP_Settings
	 */
	private $settings;

	/**
	 * Service instance.
	 *
	 * @var MNEM_SMTP_Service
	 */
	private $service;

	/**
	 * Mailer adapter.
	 *
	 * @var MNEM_Mailer_Adapter
	 */
	private $mailer;

	/**
	 * Logger instance.
	 *
	 * @var MNEM_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param MNEM_SMTP_Settings $settings Settings instance.
	 * @param MNEM_SMTP_Service  $service  SMTP service instance.
	 * @param MNEM_Mailer_Adapter $mailer  Mailer adapter.
	 * @param MNEM_Logger        $logger   Logger instance.
	 */
	public function __construct( MNEM_SMTP_Settings $settings, MNEM_SMTP_Service $service, MNEM_Mailer_Adapter $mailer, MNEM_Logger $logger ) {
		$this->settings = $settings;
		$this->service  = $service;
		$this->mailer   = $mailer;
		$this->logger   = $logger;
	}

	/**
	 * Test SMTP connectivity.
	 *
	 * @return array<string,mixed>
	 */
	public function test_connection() {
		$settings = $this->settings->get();
		$valid    = $this->validate_settings( $settings, false );

		if ( is_wp_error( $valid ) ) {
			return $this->error_result( $valid->get_error_message() );
		}

		$loaded = $this->load_phpmailer();
		if ( is_wp_error( $loaded ) ) {
			return $this->error_result( $loaded->get_error_message() );
		}

		try {
			$mailer = new PHPMailer\PHPMailer\PHPMailer( true );
			$applied = $this->service->apply_settings_to_phpmailer( $mailer );

			if ( ! $applied ) {
				return $this->error_result( __( 'SMTP settings are incomplete.', 'multisite-network-email-manager' ) );
			}

			$connected = $mailer->smtpConnect();

			if ( true !== $connected ) {
				$message = ! empty( $mailer->ErrorInfo ) ? $mailer->ErrorInfo : __( 'The SMTP server rejected the connection.', 'multisite-network-email-manager' );

				$this->logger->log(
					'error',
					'SMTP connection test failed.',
					array(
						'host'    => $settings['host'],
						'port'    => $settings['port'],
						'message' => $message,
					)
				);

				return $this->error_result( sprintf( __( 'SMTP connection failed: %s', 'multisite-network-email-manager' ), $message ) );
			}

			$mailer->smtpClose();
		} catch ( Exception $exception ) {
			$this->logger->log(
				'error',
				'SMTP connection test failed.',
				array(
					'host'    => $settings['host'],
					'port'    => $settings['port'],
					'message' => $exception->getMessage(),
				)
			);
			return $this->error_result( sprintf( __( 'SMTP connection failed: %s', 'multisite-network-email-manager' ), $exception->getMessage() ) );
		}

		$this->logger->log(
			'info',
			'SMTP connection test succeeded.',
			array(
				'host' => $settings['host'],
				'port' => $settings['port'],
			)
		);

		return array(
			'success' => true,
			'message' => __( 'SMTP connection test succeeded.', 'multisite-network-email-manager' ),
		);
	}

	/**
	 * Send a test email with the saved settings.
	 *
	 * @return array<string,mixed>
	 */
	public function send_test_email() {
		$settings = $this->settings->get();
		$valid    = $this->validate_settings( $settings, true );

		if ( is_wp_error( $valid ) ) {
			return $this->error_result( $valid->get_error_message() );
		}

		$subject = __( 'Multisite Network Email Manager SMTP Test', 'multisite-network-email-manager' );
		$body    = sprintf(
			/* translators: 1: site name, 2: time string */
			__( "This is a test email from %1\$s sent at %2\$s.", 'multisite-network-email-manager' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			wp_date( DATE_RFC2822 )
		);

		$result = $this->mailer->send( $settings['test_recipient'], $subject, $body );
		if ( ! $result ) {
			return $this->error_result( __( 'The test email could not be sent. Check the SMTP logs for details.', 'multisite-network-email-manager' ) );
		}

		$this->logger->log(
			'info',
			'SMTP test email sent.',
			array(
				'to' => $settings['test_recipient'],
			)
		);

		return array(
			'success' => true,
			'message' => sprintf( __( 'A test email was sent to %s.', 'multisite-network-email-manager' ), $settings['test_recipient'] ),
		);
	}

	/**
	 * Validate current SMTP settings.
	 *
	 * @param array $settings           Settings array.
	 * @param bool  $require_test_email Whether the test recipient is required.
	 * @return true|WP_Error
	 */
	private function validate_settings( array $settings, $require_test_email ) {
		if ( empty( $settings['enabled'] ) ) {
			return new WP_Error( 'mnem_smtp_disabled', __( 'SMTP is currently disabled.', 'multisite-network-email-manager' ) );
		}

		if ( empty( $settings['host'] ) ) {
			return new WP_Error( 'mnem_smtp_missing_host', __( 'Please set an SMTP host first.', 'multisite-network-email-manager' ) );
		}

		if ( empty( $settings['port'] ) ) {
			return new WP_Error( 'mnem_smtp_missing_port', __( 'Please set an SMTP port first.', 'multisite-network-email-manager' ) );
		}

		if ( $settings['port'] < 1 || $settings['port'] > 65535 ) {
			return new WP_Error( 'mnem_smtp_invalid_port', __( 'The SMTP port must be between 1 and 65535.', 'multisite-network-email-manager' ) );
		}

		if ( ! in_array( $settings['encryption'], array( '', 'tls', 'ssl' ), true ) ) {
			return new WP_Error( 'mnem_smtp_invalid_encryption', __( 'The SMTP encryption setting is invalid.', 'multisite-network-email-manager' ) );
		}

		if ( ! empty( $settings['auth_enabled'] ) && ( empty( $settings['username'] ) || empty( $settings['password'] ) ) ) {
			return new WP_Error( 'mnem_smtp_missing_auth', __( 'SMTP authentication is enabled but the username or password is missing.', 'multisite-network-email-manager' ) );
		}

		if ( ! empty( $settings['from_email'] ) && ! is_email( $settings['from_email'] ) ) {
			return new WP_Error( 'mnem_smtp_invalid_from', __( 'The from email address is invalid.', 'multisite-network-email-manager' ) );
		}

		if ( ! empty( $settings['reply_to_email'] ) && ! is_email( $settings['reply_to_email'] ) ) {
			return new WP_Error( 'mnem_smtp_invalid_reply_to', __( 'The reply-to email address is invalid.', 'multisite-network-email-manager' ) );
		}

		if ( $require_test_email && ( empty( $settings['test_recipient'] ) || ! is_email( $settings['test_recipient'] ) ) ) {
			return new WP_Error( 'mnem_smtp_invalid_test_recipient', __( 'Please provide a valid test recipient email address.', 'multisite-network-email-manager' ) );
		}

		return true;
	}

	/**
	 * Ensure PHPMailer classes are available.
	 *
	 * @return true|WP_Error
	 */
	private function load_phpmailer() {
		if ( class_exists( 'PHPMailer\\PHPMailer\\PHPMailer' ) ) {
			return true;
		}

		if ( ! defined( 'ABSPATH' ) ) {
			return new WP_Error( 'mnem_missing_wordpress', __( 'WordPress is not fully loaded.', 'multisite-network-email-manager' ) );
		}

		$phpmailer_files = array(
			ABSPATH . WPINC . '/PHPMailer/Exception.php',
			ABSPATH . WPINC . '/PHPMailer/PHPMailer.php',
			ABSPATH . WPINC . '/PHPMailer/SMTP.php',
		);

		foreach ( $phpmailer_files as $file ) {
			if ( ! file_exists( $file ) ) {
				return new WP_Error( 'mnem_missing_phpmailer', __( 'PHPMailer could not be loaded from WordPress core.', 'multisite-network-email-manager' ) );
			}

			require_once $file;
		}

		return true;
	}

	/**
	 * Build an error result payload.
	 *
	 * @param string $message Error message.
	 * @return array<string,mixed>
	 */
	private function error_result( $message ) {
		return array(
			'success' => false,
			'message' => $message,
		);
	}
}
