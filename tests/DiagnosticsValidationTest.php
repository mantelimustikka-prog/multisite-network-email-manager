<?php

use PHPUnit\Framework\TestCase;

class DiagnosticsValidationTest extends TestCase {
	private function make_settings_stub( MNEM_Logger $logger, array $data ) {
		return new class( $logger, $data ) extends MNEM_SMTP_Settings {
			private $data;

			public function __construct( MNEM_Logger $logger, array $data ) {
				parent::__construct( $logger );
				$this->data = $data;
			}

			public function get() {
				return $this->data;
			}
		};
	}

	public function test_send_test_email_fails_when_smtp_disabled() {
		$logger   = new MNEM_Logger();
		$settings = $this->make_settings_stub(
			$logger,
			array(
				'enabled' => false,
			)
		);
		$service  = new MNEM_SMTP_Service( $settings, $logger );
		$mailer   = new MNEM_Mailer_Adapter( $logger );
		$diag     = new MNEM_SMTP_Diagnostics( $settings, $service, $mailer, $logger );

		$result = $diag->send_test_email();

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'SMTP is currently disabled.', $result['message'] );
	}

	public function test_test_connection_fails_when_port_invalid() {
		$logger   = new MNEM_Logger();
		$settings = $this->make_settings_stub(
			$logger,
			array(
				'enabled'      => true,
				'host'         => 'smtp.example.com',
				'port'         => 70000,
				'encryption'   => '',
				'auth_enabled' => false,
			)
		);
		$service  = new MNEM_SMTP_Service( $settings, $logger );
		$mailer   = new MNEM_Mailer_Adapter( $logger );
		$diag     = new MNEM_SMTP_Diagnostics( $settings, $service, $mailer, $logger );

		$result = $diag->test_connection();

		$this->assertFalse( $result['success'] );
		$this->assertSame( 'The SMTP port must be between 1 and 65535.', $result['message'] );
	}
}
