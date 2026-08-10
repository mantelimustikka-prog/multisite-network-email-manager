<?php
/**
 * SMTP service integration for WordPress mail.
 */
class MNEM_SMTP_Service {
	/**
	 * Settings instance.
	 *
	 * @var MNEM_SMTP_Settings
	 */
	private $settings;

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
	 * @param MNEM_Logger        $logger   Logger instance.
	 */
	public function __construct( MNEM_SMTP_Settings $settings, MNEM_Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Register SMTP-related hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ) );
		add_action( 'wp_mail_failed', array( $this, 'handle_mail_failure' ) );
		add_action( 'wp_mail_succeeded', array( $this, 'handle_mail_success' ) );
	}

	/**
	 * Determine whether SMTP is enabled and minimally configured.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$settings = $this->settings->get();

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		return $this->has_minimum_configuration( $settings );
	}

	/**
	 * Apply saved settings to a PHPMailer instance.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return bool
	 */
	public function apply_settings_to_phpmailer( $phpmailer ) {
		$settings = $this->settings->get();

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( ! $this->has_minimum_configuration( $settings ) ) {
			$this->logger->log(
				'warning',
				'SMTP is enabled but not fully configured.',
				array(
					'host'         => $settings['host'],
					'port'         => $settings['port'],
					'auth_enabled' => ! empty( $settings['auth_enabled'] ),
				)
			);
			return false;
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $settings['host'];
		$phpmailer->Port       = (int) $settings['port'];
		$phpmailer->SMTPAuth   = ! empty( $settings['auth_enabled'] );
		$phpmailer->Timeout    = 15;
		$phpmailer->SMTPDebug  = 0;
		$phpmailer->SMTPAutoTLS = 'tls' === $settings['encryption'];
		$phpmailer->CharSet    = $this->get_charset();

		if ( ! empty( $settings['auth_enabled'] ) ) {
			$phpmailer->Username = $settings['username'];
			$phpmailer->Password = $settings['password'];
		}

		$phpmailer->SMTPSecure = ! empty( $settings['encryption'] ) ? $settings['encryption'] : '';

		if ( ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
			$phpmailer->setFrom( $settings['from_email'], $settings['from_name'], false );
		}

		if ( ! empty( $settings['reply_to_email'] ) && is_email( $settings['reply_to_email'] ) && $this->has_no_reply_to( $phpmailer ) ) {
			$phpmailer->addReplyTo( $settings['reply_to_email'], $settings['reply_to_name'] );
		}

		if ( ! empty( $settings['debug_mode'] ) ) {
			$this->logger->log(
				'debug',
				'Applied SMTP settings to PHPMailer.',
				array(
					'host'        => $settings['host'],
					'port'        => $settings['port'],
					'encryption'  => $settings['encryption'],
					'from_email'  => $settings['from_email'],
					'reply_to'    => $settings['reply_to_email'],
					'auth_enabled'=> ! empty( $settings['auth_enabled'] ),
				)
			);
		}

		return true;
	}

	/**
	 * Configure PHPMailer during wp_mail.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return void
	 */
	public function configure_phpmailer( $phpmailer ) {
		$this->apply_settings_to_phpmailer( $phpmailer );
	}

	/**
	 * Filter the default from email.
	 *
	 * @param string $from_email Current from email.
	 * @return string
	 */
	public function filter_from_email( $from_email ) {
		$settings = $this->settings->get();

		if ( ! empty( $settings['enabled'] ) && ! empty( $settings['from_email'] ) && is_email( $settings['from_email'] ) ) {
			return $settings['from_email'];
		}

		return $from_email;
	}

	/**
	 * Filter the default from name.
	 *
	 * @param string $from_name Current from name.
	 * @return string
	 */
	public function filter_from_name( $from_name ) {
		$settings = $this->settings->get();

		if ( ! empty( $settings['enabled'] ) && '' !== $settings['from_name'] ) {
			return $settings['from_name'];
		}

		return $from_name;
	}

	/**
	 * Handle mail send failures.
	 *
	 * @param WP_Error $error Error object.
	 * @return void
	 */
	public function handle_mail_failure( $error ) {
		$data = is_wp_error( $error ) ? $error->get_error_data() : array();

		$this->logger->log(
			'error',
			'SMTP mail send failed.',
			array(
				'code'      => is_wp_error( $error ) ? $error->get_error_code() : 'unknown',
				'message'   => is_wp_error( $error ) ? $error->get_error_message() : '',
				'to'        => is_array( $data ) && isset( $data['to'] ) ? $data['to'] : '',
				'subject'   => is_array( $data ) && isset( $data['subject'] ) ? $data['subject'] : '',
			)
		);
	}

	/**
	 * Handle successful mail sends.
	 *
	 * @param array $mail_data Successful mail data.
	 * @return void
	 */
	public function handle_mail_success( $mail_data ) {
		$settings = $this->settings->get();

		if ( empty( $settings['debug_mode'] ) ) {
			return;
		}

		$this->logger->log(
			'info',
			'SMTP mail sent successfully.',
			array(
				'to'      => is_array( $mail_data ) && isset( $mail_data['to'] ) ? $mail_data['to'] : '',
				'subject' => is_array( $mail_data ) && isset( $mail_data['subject'] ) ? $mail_data['subject'] : '',
			)
		);
	}

	/**
	 * Check if the mailer has no reply-to set.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 * @return bool
	 */
	private function has_no_reply_to( $phpmailer ) {
		if ( method_exists( $phpmailer, 'getReplyToAddresses' ) ) {
			return empty( $phpmailer->getReplyToAddresses() );
		}

		return true;
	}

	/**
	 * Check for minimum SMTP configuration.
	 *
	 * @param array $settings Settings array.
	 * @return bool
	 */
	private function has_minimum_configuration( array $settings ) {
		if ( empty( $settings['host'] ) || empty( $settings['port'] ) ) {
			return false;
		}

		if ( ! empty( $settings['auth_enabled'] ) && ( empty( $settings['username'] ) || empty( $settings['password'] ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the preferred mail charset.
	 *
	 * @return string
	 */
	private function get_charset() {
		if ( function_exists( 'get_bloginfo' ) ) {
			$charset = (string) get_bloginfo( 'charset' );

			if ( '' !== $charset ) {
				return $charset;
			}
		}

		return 'UTF-8';
	}
}
