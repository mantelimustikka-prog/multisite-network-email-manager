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

	/** @var array|null Cached rate-limit timestamps. */
	private static $send_timestamps = null;

	/**
	 * Register the mail hooks used by the plugin.
	 */
	public static function init() {
		add_filter( 'wp_mail', array( __CLASS__, 'filter_wp_mail' ) );
		add_filter( 'pre_wp_mail', array( __CLASS__, 'maybe_throttle_mail' ), 10, 2 );
		add_action( 'wp_mail_succeeded', array( __CLASS__, 'record_successful_send' ) );

		if ( MNEM_SMTP_Settings::get( 'enabled', false ) ) {
			add_action( 'phpmailer_init', array( __CLASS__, 'configure_mailer' ) );
		}
	}

	/**
	 * Apply plugin-controlled message adjustments before wp_mail builds PHPMailer.
	 *
	 * @param array $args wp_mail() arguments.
	 * @return array
	 */
	public static function filter_wp_mail( $args ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		$args = self::apply_global_header_footer( $args );
		$args = self::maybe_strip_sender_headers( $args );

		return $args;
	}

	/**
	 * Rate-limit outgoing email volume when thresholds are configured.
	 *
	 * @param null|bool $return Short-circuit value.
	 * @param array     $atts   wp_mail() attributes.
	 * @return null|bool
	 */
	public static function maybe_throttle_mail( $return, $atts ) {
		$per_minute = absint( MNEM_SMTP_Settings::get( 'rate_limit_per_minute', 0 ) );
		$per_hour   = absint( MNEM_SMTP_Settings::get( 'rate_limit_per_hour', 0 ) );

		if ( $per_minute <= 0 && $per_hour <= 0 ) {
			return $return;
		}

		$recipient_count = self::count_recipients( $atts );
		$timestamps      = self::get_send_timestamps();
		$now             = time();

		if ( $per_minute > 0 ) {
			$minute_count = self::count_timestamps_since( $timestamps, $now - 60 );
			if ( $minute_count + $recipient_count > $per_minute ) {
				MNEM_Logger::error( 'smtp', 'Email blocked by per-minute rate limit.', array( 'limit' => $per_minute, 'recipient_count' => $recipient_count ) );
				return false;
			}
		}

		if ( $per_hour > 0 ) {
			$hour_count = self::count_timestamps_since( $timestamps, $now - 3600 );
			if ( $hour_count + $recipient_count > $per_hour ) {
				MNEM_Logger::error( 'smtp', 'Email blocked by per-hour rate limit.', array( 'limit' => $per_hour, 'recipient_count' => $recipient_count ) );
				return false;
			}
		}

		return $return;
	}

	/**
	 * Track successful sends for threshold calculations.
	 *
	 * @param array $mail_data Succeeded mail data.
	 */
	public static function record_successful_send( $mail_data ) {
		$count      = self::count_recipients( $mail_data );
		$timestamps = self::get_send_timestamps();
		$now        = time();

		for ( $i = 0; $i < $count; ++$i ) {
			$timestamps[] = $now;
		}

		self::save_send_timestamps( $timestamps );
	}

	/**
	 * Configure PHPMailer with SMTP settings.
	 *
	 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance (passed by reference).
	 */
	public static function configure_mailer( $phpmailer ) {
		$settings = MNEM_SMTP_Settings::get_all( true ); // include password.
		$sender   = self::resolve_sender_settings( $settings );
		$force    = ! empty( $settings['force_sender'] );

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

			if ( ! empty( $sender['from_email'] ) && ( $force || empty( $phpmailer->From ) ) ) {
				$phpmailer->setFrom(
					$sender['from_email'],
					$sender['from_name']
				);
			}

			$reply_to_addresses = method_exists( $phpmailer, 'getReplyToAddresses' ) ? $phpmailer->getReplyToAddresses() : array();
			if ( $force && method_exists( $phpmailer, 'clearReplyTos' ) ) {
				$phpmailer->clearReplyTos();
				$reply_to_addresses = array();
			}

			if ( ! empty( $sender['reply_to_email'] ) && ( $force || empty( $reply_to_addresses ) ) ) {
				$phpmailer->addReplyTo(
					$sender['reply_to_email'],
					$sender['reply_to_name']
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

	/**
	 * Apply configured global header/footer content to a message.
	 *
	 * @param array $args wp_mail() arguments.
	 * @return array
	 */
	private static function apply_global_header_footer( array $args ) {
		$header = trim( (string) MNEM_SMTP_Settings::get( 'global_header', '' ) );
		$footer = trim( (string) MNEM_SMTP_Settings::get( 'global_footer', '' ) );

		if ( '' === $header && '' === $footer ) {
			return $args;
		}

		$message = isset( $args['message'] ) ? (string) $args['message'] : '';
		if ( '' === $message ) {
			return $args;
		}

		if ( self::is_html_message( $args ) ) {
			$parts = array_filter(
				array(
					'' !== $header ? '<div class="mnem-global-header">' . $header . '</div>' : '',
					$message,
					'' !== $footer ? '<div class="mnem-global-footer">' . $footer . '</div>' : '',
				)
			);
			$args['message'] = implode( "\n", $parts );
			return $args;
		}

		$text_parts = array_filter(
			array(
				'' !== $header ? trim( wp_strip_all_tags( $header ) ) : '',
				$message,
				'' !== $footer ? trim( wp_strip_all_tags( $footer ) ) : '',
			)
		);
		$args['message'] = implode( "\n\n", $text_parts );

		return $args;
	}

	/**
	 * Remove sender headers prepared by other plugins when forced sender info is enabled.
	 *
	 * @param array $args wp_mail() arguments.
	 * @return array
	 */
	private static function maybe_strip_sender_headers( array $args ) {
		if ( ! MNEM_SMTP_Settings::get( 'force_sender', false ) ) {
			return $args;
		}

		$headers = self::normalize_headers( isset( $args['headers'] ) ? $args['headers'] : array() );
		$headers = array_values(
			array_filter(
				$headers,
				static function ( $header ) {
					$normalized = strtolower( trim( (string) $header ) );
					return 0 !== strpos( $normalized, 'from:' ) && 0 !== strpos( $normalized, 'reply-to:' );
				}
			)
		);

		$args['headers'] = $headers;

		return $args;
	}

	/**
	 * Resolve sender details according to the selected sender mode.
	 *
	 * @param array $settings SMTP settings.
	 * @return array
	 */
	private static function resolve_sender_settings( array $settings ) {
		$sender = array(
			'from_email'     => '',
			'from_name'      => '',
			'reply_to_email' => (string) $settings['reply_to_email'],
			'reply_to_name'  => (string) $settings['reply_to_name'],
		);

		switch ( $settings['sender_mode'] ) {
			case 'master_site':
				$main_site_id          = function_exists( 'get_main_site_id' ) ? absint( get_main_site_id() ) : 1;
				$sender['from_email']  = self::get_blog_setting( $main_site_id, 'admin_email', (string) $settings['from_email'] );
				$sender['from_name']   = self::get_blog_setting( $main_site_id, 'blogname', (string) $settings['from_name'] );
				break;

			case 'child_site':
				$current_site_id       = function_exists( 'get_current_blog_id' ) ? absint( get_current_blog_id() ) : 0;
				$sender['from_email']  = self::get_blog_setting( $current_site_id, 'admin_email', (string) $settings['from_email'] );
				$sender['from_name']   = self::get_blog_setting( $current_site_id, 'blogname', (string) $settings['from_name'] );
				break;

			case 'network_global':
			default:
				$sender['from_email'] = (string) $settings['from_email'];
				$sender['from_name']  = (string) $settings['from_name'];
				break;
		}

		if ( empty( $sender['reply_to_email'] ) ) {
			$sender['reply_to_email'] = $sender['from_email'];
			$sender['reply_to_name']  = $sender['from_name'];
		}

		return $sender;
	}

	/**
	 * Detect whether a wp_mail payload is HTML email.
	 *
	 * @param array $args wp_mail() arguments.
	 * @return bool
	 */
	private static function is_html_message( array $args ) {
		$headers = self::normalize_headers( isset( $args['headers'] ) ? $args['headers'] : array() );

		foreach ( $headers as $header ) {
			if ( false !== stripos( $header, 'content-type:' ) && false !== stripos( $header, 'text/html' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize a headers payload into an array of header lines.
	 *
	 * @param string|array $headers Headers payload.
	 * @return array
	 */
	private static function normalize_headers( $headers ) {
		if ( empty( $headers ) ) {
			return array();
		}

		if ( is_array( $headers ) ) {
			return array_filter( array_map( 'trim', $headers ) );
		}

		return array_filter(
			array_map(
				'trim',
				preg_split( '/\r\n|\r|\n/', (string) $headers )
			)
		);
	}

	/**
	 * Retrieve a site-level setting with a safe fallback.
	 *
	 * @param int    $site_id  Site ID.
	 * @param string $key      Setting key.
	 * @param string $fallback Fallback value.
	 * @return string
	 */
	private static function get_blog_setting( $site_id, $key, $fallback = '' ) {
		if ( function_exists( 'get_blog_option' ) && $site_id > 0 ) {
			return (string) get_blog_option( $site_id, $key, $fallback );
		}

		if ( function_exists( 'get_option' ) ) {
			return (string) get_option( $key, $fallback );
		}

		return (string) $fallback;
	}

	/**
	 * Count recipients in a mail payload.
	 *
	 * @param array $mail_data wp_mail payload.
	 * @return int
	 */
	private static function count_recipients( $mail_data ) {
		$to = isset( $mail_data['to'] ) ? $mail_data['to'] : array();

		if ( is_string( $to ) ) {
			$to = preg_split( '/,/', $to );
		}

		if ( ! is_array( $to ) ) {
			return 1;
		}

		$recipients = array_filter(
			array_map(
				static function ( $recipient ) {
					return sanitize_email( (string) $recipient );
				},
				$to
			)
		);

		return max( 1, count( $recipients ) );
	}

	/**
	 * Count timestamps at or after the provided cutoff.
	 *
	 * @param array $timestamps Unix timestamps.
	 * @param int   $cutoff     Unix cutoff.
	 * @return int
	 */
	private static function count_timestamps_since( array $timestamps, $cutoff ) {
		return count(
			array_filter(
				$timestamps,
				static function ( $timestamp ) use ( $cutoff ) {
					return (int) $timestamp >= (int) $cutoff;
				}
			)
		);
	}

	/**
	 * Load recent send timestamps.
	 *
	 * @return array
	 */
	private static function get_send_timestamps() {
		if ( null !== self::$send_timestamps ) {
			return self::$send_timestamps;
		}

		$timestamps = MNEM_Settings::get( 'mail_send_timestamps', array() );
		$timestamps = is_array( $timestamps ) ? $timestamps : array();
		$timestamps = array_map( 'absint', $timestamps );
		$cutoff     = time() - 3600;

		self::$send_timestamps = array_values(
			array_filter(
				$timestamps,
				static function ( $timestamp ) use ( $cutoff ) {
					return (int) $timestamp >= $cutoff;
				}
			)
		);

		if ( count( self::$send_timestamps ) !== count( $timestamps ) ) {
			MNEM_Settings::set( 'mail_send_timestamps', self::$send_timestamps );
		}

		return self::$send_timestamps;
	}

	/**
	 * Persist recent send timestamps.
	 *
	 * @param array $timestamps Unix timestamps.
	 */
	private static function save_send_timestamps( array $timestamps ) {
		self::$send_timestamps = array_values(
			array_filter(
				array_map( 'absint', $timestamps ),
				static function ( $timestamp ) {
					return $timestamp > 0;
				}
			)
		);

		MNEM_Settings::set( 'mail_send_timestamps', self::$send_timestamps );
	}
}
