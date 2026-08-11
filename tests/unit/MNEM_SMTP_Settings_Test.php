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
		$this->assertSame( 'custom', $settings['provider'] );
		$this->assertSame( '', $settings['host'] );
		$this->assertSame( 587, $settings['port'] );
		$this->assertSame( 'tls', $settings['encryption'] );
		$this->assertSame( 0, $settings['rate_limit_per_minute'] );
		$this->assertSame( 0, $settings['rate_limit_per_hour'] );
		$this->assertSame( 'network_global', $settings['sender_mode'] );
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

	public function test_invalid_provider_falls_back_to_custom() {
		MNEM_SMTP_Settings::save( array( 'provider' => 'invalid-provider' ) );
		$this->assertSame( 'custom', MNEM_SMTP_Settings::get( 'provider' ) );
	}

	public function test_sendgrid_preset_supplies_runtime_defaults() {
		MNEM_SMTP_Settings::save( array( 'provider' => 'sendgrid' ) );

		$settings = MNEM_SMTP_Settings::get_all( true );

		$this->assertSame( 'sendgrid', $settings['provider'] );
		$this->assertSame( 'smtp.sendgrid.net', $settings['host'] );
		$this->assertSame( 587, $settings['port'] );
		$this->assertSame( 'tls', $settings['encryption'] );
		$this->assertSame( 'apikey', $settings['username'] );
	}

	public function test_rate_limits_are_sanitized_to_non_negative_integers() {
		MNEM_SMTP_Settings::save(
			array(
				'rate_limit_per_minute' => '-5',
				'rate_limit_per_hour'   => '120.8',
			)
		);

		$this->assertSame( 5, MNEM_SMTP_Settings::get( 'rate_limit_per_minute' ) );
		$this->assertSame( 120, MNEM_SMTP_Settings::get( 'rate_limit_per_hour' ) );
	}

	public function test_global_header_and_footer_preserve_html() {
		MNEM_SMTP_Settings::save(
			array(
				'global_header' => '<div><strong>Header</strong></div>',
				'global_footer' => '<p>Footer</p>',
			)
		);

		$this->assertSame( '<div><strong>Header</strong></div>', MNEM_SMTP_Settings::get( 'global_header' ) );
		$this->assertSame( '<p>Footer</p>', MNEM_SMTP_Settings::get( 'global_footer' ) );
	}

	public function test_invalid_sender_mode_falls_back_to_network_global() {
		MNEM_SMTP_Settings::save( array( 'sender_mode' => 'nope' ) );
		$this->assertSame( 'network_global', MNEM_SMTP_Settings::get( 'sender_mode' ) );
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

	public function test_invalid_base64_password_returns_empty_string() {
		update_site_option( 'mnem_smtp_password', 'b64:not-valid-base64%%' );
		$this->assertSame( '', MNEM_SMTP_Settings::get( 'password' ) );
	}
}
