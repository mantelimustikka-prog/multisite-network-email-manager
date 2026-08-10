<?php
/**
 * Settings — wrapper around site options for the plugin.
 *
 * All settings are stored as network (site) options so they apply globally.
 * Individual sub-sites can override specific settings in a future release.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_Settings {

	/** @var array In-memory cache of loaded settings. */
	private static $cache = array();

	/**
	 * Initialise — preload common settings into cache.
	 */
	public static function init() {
		// Nothing to preload for now; settings are loaded lazily via get().
	}

	/**
	 * Retrieve a setting value.
	 *
	 * @param string $key     Setting key (without prefix).
	 * @param mixed  $default Default value when the setting does not exist.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$option_name = MNEM_OPTION_PREFIX . sanitize_key( $key );

		if ( ! array_key_exists( $option_name, self::$cache ) ) {
			self::$cache[ $option_name ] = get_site_option( $option_name, $default );
		}

		return self::$cache[ $option_name ];
	}

	/**
	 * Save a setting value.
	 *
	 * @param string $key   Setting key (without prefix).
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	public static function set( $key, $value ) {
		$option_name                 = MNEM_OPTION_PREFIX . sanitize_key( $key );
		self::$cache[ $option_name ] = $value;
		return update_site_option( $option_name, $value );
	}

	/**
	 * Delete a setting.
	 *
	 * @param string $key Setting key (without prefix).
	 * @return bool
	 */
	public static function delete( $key ) {
		$option_name = MNEM_OPTION_PREFIX . sanitize_key( $key );
		unset( self::$cache[ $option_name ] );
		return delete_site_option( $option_name );
	}

	/**
	 * Clear the in-memory cache (useful after bulk updates).
	 */
	public static function flush_cache() {
		self::$cache = array();
	}
}
