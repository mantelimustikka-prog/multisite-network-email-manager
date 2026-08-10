<?php
/**
 * SMTP Service — sends email via a configured SMTP server.
 *
 * This class hooks into WordPress's phpmailer_init action so all wp_mail()
 * calls are routed through the configured SMTP server when SMTP is enabled.
 *
 * No vendor library is hard-coded here. WordPress ships PHPMailer; we simply
 * configure it. Swap in any compatible library via the filter hooks.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_SMTP_Service {

	/**
	 * Register the phpmailer_init hook when SMTP is enabled.
	 */
	public static function init() {
		if ( MNEM_SMTP_Settings::get( 'enabled', false ) ) {
			add_action( 'phpmailer_init', array( __CLASS__, 'configure_mailer' ) );
		}
	}

	/**
	 * Configure PHPMailer with SMTP settings.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance (passed by reference).
	 */
	public static function configure_mailer( $phpmailer ) {
		$settings = MNEM_SMTP_Settings::get_all( true ); // include password.

		try {
			$phpmailer->isSMTP();
			$phpmailer->Host       = $settings['host'];
			$phpmailer->Port       = (int) $settings['port'];
			$phpmailer->SMTPAuth   = (bool) $settings['auth_enabled'];
			$phpmailer->Username   = $settings['username'];
			$phpmailer->Password   = $settings['password'];
			$phpmailer->SMTPDebug  = $settings['debug_mode'] ? 2 : 0;

			if ( ! empty( $settings['encryption'] ) ) {
				$phpmailer->SMTPSecure = $settings['encryption'];
			}

			if ( ! empty( $settings['from_email'] ) ) {
				$phpmailer->setFrom(
					$settings['from_email'],
					$settings['from_name']
				);
			}

			if ( ! empty( $settings['reply_to_email'] ) ) {
				$phpmailer->addReplyTo(
					$settings['reply_to_email'],
					$settings['reply_to_name']
				);
			}

			/**
			 * Filter to allow further customization of the PHPMailer instance.
			 *
			 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer instance.
			 * @param array                         $settings  Resolved SMTP settings.
			 */
			do_action( 'mnem_smtp_mailer_configured', $phpmailer, $settings );

		} catch ( Exception $e ) {
			MNEM_Logger::error( 'smtp', 'Failed to configure mailer: ' . $e->getMessage() );
		}
	}
}
