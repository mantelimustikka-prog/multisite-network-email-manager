<?php

use PHPUnit\Framework\TestCase;

class ServiceConfigurationTest extends TestCase {
	public function test_is_enabled_false_when_missing_minimum_configuration() {
		$logger = new MNEM_Logger();
		$stub   = new class( $logger ) extends MNEM_SMTP_Settings {
			public function __construct( MNEM_Logger $logger ) {
				parent::__construct( $logger );
			}

			public function get() {
				return array(
					'enabled'      => true,
					'host'         => '',
					'port'         => 587,
					'auth_enabled' => false,
				);
			}
		};

		$service = new MNEM_SMTP_Service( $stub, $logger );
		$this->assertFalse( $service->is_enabled() );
	}

	public function test_is_enabled_true_when_minimum_configuration_is_present() {
		$logger = new MNEM_Logger();
		$stub   = new class( $logger ) extends MNEM_SMTP_Settings {
			public function __construct( MNEM_Logger $logger ) {
				parent::__construct( $logger );
			}

			public function get() {
				return array(
					'enabled'      => true,
					'host'         => 'smtp.example.com',
					'port'         => 587,
					'auth_enabled' => false,
				);
			}
		};

		$service = new MNEM_SMTP_Service( $stub, $logger );
		$this->assertTrue( $service->is_enabled() );
	}
}
