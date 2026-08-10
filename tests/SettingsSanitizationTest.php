<?php

use PHPUnit\Framework\TestCase;

class SettingsSanitizationTest extends TestCase {
	public function test_sanitize_settings_keeps_existing_password_when_blank() {
		$logger   = new MNEM_Logger();
		$settings = new MNEM_SMTP_Settings( $logger );

		$existing = MNEM_SMTP_Settings::defaults();
		$existing['password'] = 'stored-secret';

		$result = $settings->sanitize_settings(
			array(
				'enabled'      => '1',
				'host'         => 'smtp.example.com',
				'port'         => '587',
				'encryption'   => 'TLS',
				'auth_enabled' => '1',
				'username'     => 'user',
				'password'     => '   ',
			),
			$existing
		);

		$this->assertSame( 'stored-secret', $result['password'] );
		$this->assertSame( 'tls', $result['encryption'] );
		$this->assertSame( 587, $result['port'] );
	}

	public function test_sanitize_settings_rejects_invalid_port_and_encryption() {
		$logger   = new MNEM_Logger();
		$settings = new MNEM_SMTP_Settings( $logger );

		$result = $settings->sanitize_settings(
			array(
				'port'       => 99999,
				'encryption' => 'starttls',
			),
			MNEM_SMTP_Settings::defaults()
		);

		$this->assertSame( 0, $result['port'] );
		$this->assertSame( '', $result['encryption'] );
	}
}
