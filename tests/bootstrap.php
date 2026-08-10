<?php

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $text ) {
		return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action() {}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( intval( $value ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = trim( (string) $email );
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return (bool) filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show ) {
		if ( 'charset' === $show ) {
			return 'UTF-8';
		}

		if ( 'name' === $show ) {
			return 'Test Site';
		}

		return '';
	}
}

if ( ! function_exists( 'wp_specialchars_decode' ) ) {
	function wp_specialchars_decode( $string, $quote_style = ENT_NOQUOTES ) {
		return html_entity_decode( (string) $string, $quote_style );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format ) {
		return gmdate( $format );
	}
}

if ( ! function_exists( 'wp_mail' ) ) {
	function wp_mail() {
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action() {}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter() {}
}

// --- Site/blog option stubs ------------------------------------------------

if ( ! function_exists( 'get_site_option' ) ) {
	$_mnem_site_options = array();

	function get_site_option( $key, $default = false ) {
		global $_mnem_site_options;
		return array_key_exists( $key, $_mnem_site_options ) ? $_mnem_site_options[ $key ] : $default;
	}
}

if ( ! function_exists( 'update_site_option' ) ) {
	function update_site_option( $key, $value ) {
		global $_mnem_site_options;
		$_mnem_site_options[ $key ] = $value;
	}
}

if ( ! function_exists( 'delete_site_option' ) ) {
	function delete_site_option( $key ) {
		global $_mnem_site_options;
		unset( $_mnem_site_options[ $key ] );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	$_mnem_options = array();

	function get_option( $key, $default = false ) {
		global $_mnem_options;
		return array_key_exists( $key, $_mnem_options ) ? $_mnem_options[ $key ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		global $_mnem_options;
		$_mnem_options[ $key ] = $value;
	}
}

// --- Transient stubs -------------------------------------------------------

if ( ! function_exists( 'get_transient' ) ) {
	$_mnem_transients = array();

	function get_transient( $key ) {
		global $_mnem_transients;
		return isset( $_mnem_transients[ $key ] ) ? $_mnem_transients[ $key ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		global $_mnem_transients;
		$_mnem_transients[ $key ] = $value;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		global $_mnem_transients;
		unset( $_mnem_transients[ $key ] );
	}
}

// --- Cron stubs ------------------------------------------------------------

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event() {}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled() {
		return false;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook() {}
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-logger.php';
require_once dirname( __DIR__ ) . '/includes/class-crypto.php';
require_once dirname( __DIR__ ) . '/includes/class-log-store.php';
require_once dirname( __DIR__ ) . '/includes/class-smtp-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-smtp-service.php';
require_once dirname( __DIR__ ) . '/includes/class-mailer-adapter.php';
require_once dirname( __DIR__ ) . '/includes/class-smtp-diagnostics.php';
require_once dirname( __DIR__ ) . '/includes/class-mail-queue.php';
require_once dirname( __DIR__ ) . '/includes/class-site-settings.php';
