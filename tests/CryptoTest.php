<?php

use PHPUnit\Framework\TestCase;

class CryptoTest extends TestCase {
	public function test_encrypt_and_decrypt_round_trip_with_sodium() {
		if ( ! MNEM_Crypto::sodium_available() ) {
			$this->markTestSkipped( 'sodium extension not available.' );
		}

		$plaintext  = 'super-secret-password';
		$ciphertext = MNEM_Crypto::encrypt( $plaintext );

		$this->assertNotSame( $plaintext, $ciphertext );
		$this->assertStringStartsWith( 'nacl:', $ciphertext );
		$this->assertSame( $plaintext, MNEM_Crypto::decrypt( $ciphertext ) );
	}

	public function test_encrypt_empty_string_returns_empty() {
		$this->assertSame( '', MNEM_Crypto::encrypt( '' ) );
	}

	public function test_decrypt_empty_string_returns_empty() {
		$this->assertSame( '', MNEM_Crypto::decrypt( '' ) );
	}

	public function test_decrypt_legacy_plain_value_returns_as_is() {
		// Values without a known prefix are treated as legacy plaintext.
		$this->assertSame( 'legacypassword', MNEM_Crypto::decrypt( 'legacypassword' ) );
	}

	public function test_b64_fallback_round_trip() {
		// Manually encode as b64 prefix to simulate fallback storage.
		$plaintext  = 'my-password';
		$ciphertext = 'b64:' . base64_encode( $plaintext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$this->assertSame( $plaintext, MNEM_Crypto::decrypt( $ciphertext ) );
	}
}
