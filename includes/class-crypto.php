<?php
/**
 * Credential encryption helpers for SMTP settings.
 */
class MNEM_Crypto {
	/**
	 * Transient key prefix used to cache the derived encryption key.
	 */
	const KEY_OPTION = 'mnem_enc_key';

	/**
	 * Encrypt a plaintext string using sodium.
	 *
	 * Falls back to base64 storage when sodium is unavailable so that the
	 * rest of the plugin continues to function on older hosts.
	 *
	 * @param string $plaintext Plaintext to encrypt.
	 * @return string Encoded ciphertext string (prefixed for detection).
	 */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;

		if ( '' === $plaintext ) {
			return '';
		}

		if ( ! self::sodium_available() ) {
			return 'b64:' . base64_encode( $plaintext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$key   = self::get_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
		sodium_memzero( $plaintext );

		return 'nacl:' . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a ciphertext string produced by self::encrypt().
	 *
	 * @param string $ciphertext Encoded ciphertext string.
	 * @return string Plaintext, or empty string on failure.
	 */
	public static function decrypt( $ciphertext ) {
		$ciphertext = (string) $ciphertext;

		if ( '' === $ciphertext ) {
			return '';
		}

		if ( 0 === strpos( $ciphertext, 'b64:' ) ) {
			return (string) base64_decode( substr( $ciphertext, 4 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		if ( 0 !== strpos( $ciphertext, 'nacl:' ) ) {
			// Legacy plain-text value — return as-is so existing installs keep working.
			return $ciphertext;
		}

		if ( ! self::sodium_available() ) {
			return '';
		}

		$decoded = base64_decode( substr( $ciphertext, 5 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded || strlen( $decoded ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$nonce      = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipherdata = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key        = self::get_key();

		$plaintext = sodium_crypto_secretbox_open( $cipherdata, $nonce, $key );

		return false !== $plaintext ? $plaintext : '';
	}

	/**
	 * Return true when the sodium extension is available.
	 *
	 * @return bool
	 */
	public static function sodium_available() {
		return function_exists( 'sodium_crypto_secretbox' );
	}

	/**
	 * Retrieve (or generate) the encryption key stored as a site option.
	 *
	 * The key is stored as a hex string in wp_sitemeta so that it survives
	 * across requests without being placed in code.
	 *
	 * @return string Raw 32-byte key.
	 */
	private static function get_key() {
		$stored = get_site_option( self::KEY_OPTION, '' );

		if ( is_string( $stored ) && strlen( $stored ) === 64 ) {
			$key = hex2bin( $stored );
			if ( false !== $key && strlen( $key ) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) {
				return $key;
			}
		}

		$key = random_bytes( SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		update_site_option( self::KEY_OPTION, bin2hex( $key ) );

		return $key;
	}
}
