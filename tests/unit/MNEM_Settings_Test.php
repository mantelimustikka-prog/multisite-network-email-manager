<?php
/**
 * Unit tests for MNEM_Settings.
 *
 * @package MNEM
 */

use PHPUnit\Framework\TestCase;

class MNEM_Settings_Test extends TestCase {

	protected function setUp(): void {
		// Reset the fake site-options store and the class cache before each test.
		$GLOBALS['_mnem_site_options'] = array();
		// Reset static cache via reflection.
		$ref   = new ReflectionClass( MNEM_Settings::class );
		$cache = $ref->getProperty( 'cache' );
		$cache->setAccessible( true );
		$cache->setValue( null, array() );
	}

	public function test_get_returns_default_when_not_set() {
		$this->assertNull( MNEM_Settings::get( 'nonexistent_key_a' ) );
		// Use a different key so we don't hit the null cached for the key above.
		$this->assertSame( 'fallback', MNEM_Settings::get( 'nonexistent_key_b', 'fallback' ) );
	}

	public function test_set_and_get_round_trip() {
		MNEM_Settings::set( 'test_key', 'hello' );
		$this->assertSame( 'hello', MNEM_Settings::get( 'test_key' ) );
	}

	public function test_set_stores_under_prefixed_option_name() {
		MNEM_Settings::set( 'foo', 'bar' );
		$this->assertSame( 'bar', get_site_option( 'mnem_foo' ) );
	}

	public function test_delete_removes_value() {
		MNEM_Settings::set( 'to_delete', 'value' );
		MNEM_Settings::delete( 'to_delete' );
		$this->assertNull( MNEM_Settings::get( 'to_delete' ) );
	}

	public function test_flush_cache_forces_re_read() {
		MNEM_Settings::set( 'cached_key', 'original' );
		// Directly update underlying store to bypass cache.
		update_site_option( 'mnem_cached_key', 'updated' );

		// Cache still returns old value.
		$this->assertSame( 'original', MNEM_Settings::get( 'cached_key' ) );

		// After flush the new value is returned.
		MNEM_Settings::flush_cache();
		$this->assertSame( 'updated', MNEM_Settings::get( 'cached_key' ) );
	}
}
