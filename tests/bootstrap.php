<?php
/**
 * PHPUnit bootstrap for MNEM unit tests.
 *
 * These tests run without a full WordPress installation.
 * WordPress-dependent classes are replaced with lightweight stubs defined here.
 *
 * @package MNEM
 */

// Plugin constants (replicate what the bootstrap file sets).
define( 'ABSPATH', __DIR__ . '/' );
define( 'MNEM_VERSION', '0.1.0' );
define( 'MNEM_PLUGIN_FILE', dirname( __DIR__ ) . '/multisite-network-email-manager.php' );
define( 'MNEM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'MNEM_PLUGIN_URL', 'https://example.com/wp-content/plugins/multisite-network-email-manager/' );
define( 'MNEM_OPTION_PREFIX', 'mnem_' );
define( 'MNEM_DB_VERSION', '1' );

// ---------------------------------------------------------------------------
// Minimal WordPress stubs so production classes load without a WP install.
// ---------------------------------------------------------------------------

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) {
		return null;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( trim( $email ), FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $str ) {
		return strip_tags( $str );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $str ) {
		return $str; // Stub; full WP would strip unsafe tags.
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0 ) {
		return json_encode( $data, $options );
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( $key, $default = false ) {
		return $GLOBALS['_mnem_site_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = false ) {
		$current_blog_id = $GLOBALS['_mnem_current_blog_id'] ?? 1;
		return $GLOBALS['_mnem_blog_options'][ $current_blog_id ][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'get_blog_option' ) ) {
	function get_blog_option( $blog_id, $key, $default = false ) {
		return $GLOBALS['_mnem_blog_options'][ $blog_id ][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'update_site_option' ) ) {
	function update_site_option( $key, $value ) {
		$GLOBALS['_mnem_site_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_site_option' ) ) {
	function delete_site_option( $key ) {
		unset( $GLOBALS['_mnem_site_options'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		return $value; // No filter hooks in unit tests.
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = false ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id() {
		return $GLOBALS['_mnem_current_blog_id'] ?? 1;
	}
}

if ( ! function_exists( 'get_main_site_id' ) ) {
	function get_main_site_id() {
		return $GLOBALS['_mnem_main_site_id'] ?? 1;
	}
}

if ( ! function_exists( 'str_starts_with' ) ) {
	// Polyfill for PHP < 8.0.
	function str_starts_with( $haystack, $needle ) {
		return substr( $haystack, 0, strlen( $needle ) ) === $needle;
	}
}

// Initialise the fake site-options store.
$GLOBALS['_mnem_site_options'] = array();
$GLOBALS['_mnem_current_blog_id'] = 1;
$GLOBALS['_mnem_main_site_id']    = 1;
$GLOBALS['_mnem_blog_options']    = array(
	1 => array(
		'admin_email' => 'network@example.com',
		'blogname'    => 'Network Site',
	),
	2 => array(
		'admin_email' => 'child@example.com',
		'blogname'    => 'Child Site',
	),
);
$GLOBALS['wpdb'] = new class() {
	public $base_prefix = 'wp_';
	public function insert( $table, $data, $format = array() ) {
		return true;
	}
};

// Load the classes under test.
require_once MNEM_PLUGIN_DIR . 'includes/class-settings.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-smtp-settings.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-smtp-service.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-suppression.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-logger.php';
