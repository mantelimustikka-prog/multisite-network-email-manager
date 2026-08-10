<?php
/**
 * Unit tests for MNEM_SMTP_Settings.
 *
 * @package MNEM
 */

use PHPUnit\Framework\TestCase;

class MNEM_SMTP_Settings_Test extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_mnem_site_options'] = array();
		// Reset Settings cache.
		$ref   = new ReflectionClass( MNEM_Settings::class );
		$cache = $ref->getProperty( 'cache' );
		$cache->setAccessible( true );
		$cache->setValue( null, array() );
	}

	public function test_get_all_returns_defaults_when_nothing_saved() {
		$settings = MNEM_SMTP_Settings::get_all();
		$this->assertFalse( $settings['enabled'] );
		$this->assertSame( '', $settings['host'] );
		$this->assertSame( 587, $settings['port'] );
		$this->assertSame( 'tls', $settings['encryption'] );
	}

	public function test_password_is_masked_by_default() {
		MNEM_SMTP_Settings::save( array( 'password' => 'mysecret' ) );
		$settings = MNEM_SMTP_Settings::get_all();
		$this->assertSame( MNEM_SMTP_Settings::PASSWORD_PLACEHOLDER, $settings['password'] );
	}

	public function test_password_is_available_with_flag() {
		MNEM_SMTP_Settings::save( array( 'password' => 'mysecret' ) );
		$settings = MNEM_SMTP_Settings::get_all( true );
		$this->assertSame( 'mysecret', $settings['password'] );
	}

	public function test_password_placeholder_does_not_overwrite_existing() {
		MNEM_SMTP_Settings::save( array( 'password' => 'original' ) );

		// Submit with placeholder (as the admin form does when user leaves the field untouched).
		MNEM_SMTP_Settings::save( array( 'password' => MNEM_SMTP_Settings::PASSWORD_PLACEHOLDER ) );

		$settings = MNEM_SMTP_Settings::get_all( true );
		$this->assertSame( 'original', $settings['password'] );
	}

	public function test_empty_password_does_not_overwrite() {
		MNEM_SMTP_Settings::save( array( 'password' => 'keep_me' ) );
		MNEM_SMTP_Settings::save( array( 'password' => '' ) );

		$settings = MNEM_SMTP_Settings::get_all( true );
		$this->assertSame( 'keep_me', $settings['password'] );
	}

	public function test_port_is_sanitized_to_integer() {
		MNEM_SMTP_Settings::save( array( 'port' => '465abc' ) );
		$this->assertSame( 465, MNEM_SMTP_Settings::get( 'port' ) );
	}

	public function test_invalid_port_falls_back_to_587() {
		MNEM_SMTP_Settings::save( array( 'port' => 0 ) );
		$this->assertSame( 587, MNEM_SMTP_Settings::get( 'port' ) );
	}

	public function test_invalid_encryption_falls_back_to_tls() {
		MNEM_SMTP_Settings::save( array( 'encryption' => 'invalid_value' ) );
		$this->assertSame( 'tls', MNEM_SMTP_Settings::get( 'encryption' ) );
	}

	public function test_valid_encryption_values_are_accepted() {
		foreach ( array( '', 'ssl', 'tls' ) as $enc ) {
			MNEM_SMTP_Settings::save( array( 'encryption' => $enc ) );
			MNEM_Settings::flush_cache();
			$this->assertSame( $enc, MNEM_SMTP_Settings::get( 'encryption' ) );
		}
	}

	public function test_get_unknown_key_returns_default() {
		$this->assertNull( MNEM_SMTP_Settings::get( 'nonexistent_key' ) );
		$this->assertSame( 'default', MNEM_SMTP_Settings::get( 'nonexistent_key', 'default' ) );
	}

	public function test_password_is_stored_encrypted_not_plain() {
		MNEM_SMTP_Settings::save( array( 'password' => 'p@ssw0rd!' ) );
		// Raw site option should NOT equal the plain-text password.
		$raw = get_site_option( 'mnem_smtp_password' );
		$this->assertNotSame( 'p@ssw0rd!', $raw );
		// But should start with the b64: prefix.
		$this->assertStringStartsWith( 'b64:', $raw );
	}
}
