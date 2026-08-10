<?php
/**
 * Unit tests for MNEM_Logger.
 *
 * Because MNEM_Logger writes to the DB we verify the sanitize logic
 * using the private sanitize_context method via reflection.
 *
 * @package MNEM
 */

use PHPUnit\Framework\TestCase;

class MNEM_Logger_Test extends TestCase {

	/** @var ReflectionMethod */
	private $sanitize_context;

	protected function setUp(): void {
		$ref                    = new ReflectionClass( MNEM_Logger::class );
		$this->sanitize_context = $ref->getMethod( 'sanitize_context' );
		$this->sanitize_context->setAccessible( true );
	}

	private function sanitize( array $context ): array {
		return $this->sanitize_context->invoke( null, $context );
	}

	public function test_non_sensitive_keys_are_kept() {
		$ctx  = array( 'host' => 'smtp.example.com', 'port' => 587 );
		$safe = $this->sanitize( $ctx );
		$this->assertSame( 'smtp.example.com', $safe['host'] );
		$this->assertSame( 587, $safe['port'] );
	}

	public function test_password_key_is_masked() {
		$safe = $this->sanitize( array( 'password' => 'super_secret' ) );
		$this->assertSame( '***', $safe['password'] );
	}

	public function test_token_key_is_masked() {
		$safe = $this->sanitize( array( 'api_token' => 'abc123' ) );
		$this->assertSame( '***', $safe['api_token'] );
	}

	public function test_secret_key_is_masked() {
		$safe = $this->sanitize( array( 'client_secret' => 'xyz' ) );
		$this->assertSame( '***', $safe['client_secret'] );
	}

	public function test_mixed_context_masks_only_sensitive() {
		$ctx = array(
			'host'     => 'smtp.test',
			'password' => 'do_not_log',
			'port'     => 465,
		);
		$safe = $this->sanitize( $ctx );
		$this->assertSame( 'smtp.test', $safe['host'] );
		$this->assertSame( '***', $safe['password'] );
		$this->assertSame( 465, $safe['port'] );
	}

	public function test_array_value_is_json_encoded() {
		$safe = $this->sanitize( array( 'headers' => array( 'x-foo' => 'bar' ) ) );
		$this->assertIsString( $safe['headers'] );
		$decoded = json_decode( $safe['headers'], true );
		$this->assertSame( 'bar', $decoded['x-foo'] );
	}
}
