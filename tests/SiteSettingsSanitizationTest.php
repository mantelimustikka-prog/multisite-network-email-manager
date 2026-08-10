<?php

use PHPUnit\Framework\TestCase;

class SiteSettingsSanitizationTest extends TestCase {
	public function test_defaults_return_expected_keys() {
		$defaults = MNEM_Site_Settings::defaults();

		$this->assertArrayHasKey( 'override_enabled', $defaults );
		$this->assertArrayHasKey( 'from_email', $defaults );
		$this->assertArrayHasKey( 'from_name', $defaults );
		$this->assertArrayHasKey( 'reply_to_email', $defaults );
		$this->assertArrayHasKey( 'reply_to_name', $defaults );
		$this->assertFalse( $defaults['override_enabled'] );
	}

	public function test_sanitize_enables_override_and_sanitizes_fields() {
		$logger   = new MNEM_Logger();
		$settings = new MNEM_Site_Settings( $logger );

		$result = $settings->sanitize_settings(
			array(
				'override_enabled' => '1',
				'from_email'       => 'sender@example.com',
				'from_name'        => 'My Site',
				'reply_to_email'   => 'reply@example.com',
				'reply_to_name'    => 'Support',
			)
		);

		$this->assertTrue( $result['override_enabled'] );
		$this->assertSame( 'sender@example.com', $result['from_email'] );
		$this->assertSame( 'My Site', $result['from_name'] );
		$this->assertSame( 'reply@example.com', $result['reply_to_email'] );
		$this->assertSame( 'Support', $result['reply_to_name'] );
	}

	public function test_sanitize_rejects_invalid_emails() {
		$logger   = new MNEM_Logger();
		$settings = new MNEM_Site_Settings( $logger );

		$result = $settings->sanitize_settings(
			array(
				'override_enabled' => '1',
				'from_email'       => 'not-an-email',
				'reply_to_email'   => 'also-bad',
			)
		);

		$this->assertSame( '', $result['from_email'] );
		$this->assertSame( '', $result['reply_to_email'] );
	}

	public function test_sanitize_override_disabled_by_default() {
		$logger   = new MNEM_Logger();
		$settings = new MNEM_Site_Settings( $logger );

		$result = $settings->sanitize_settings( array() );

		$this->assertFalse( $result['override_enabled'] );
	}
}
