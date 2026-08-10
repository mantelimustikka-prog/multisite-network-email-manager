<?php
/**
 * Minimal plugin logger.
 */
class MNEM_Logger {
	/**
	 * Log a message safely.
	 *
	 * @param string $level   Log level.
	 * @param string $message Log message.
	 * @param array  $context Optional context.
	 * @return void
	 */
	public function log( $level, $message, array $context = array() ) {
		$record = array(
			'level'   => sanitize_key( (string) $level ),
			'message' => sanitize_text_field( wp_strip_all_tags( (string) $message ) ),
			'context' => $this->sanitize_context( $context ),
		);

		do_action( 'mnem_log', $record );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[MNEM] ' . $record['level'] . ': ' . $record['message'] . ' ' . wp_json_encode( $record['context'] ) );
		}
	}

	/**
	 * Sanitize log context recursively.
	 *
	 * @param mixed $value Context value.
	 * @param mixed $key   Optional key.
	 * @return mixed
	 */
	private function sanitize_context( $value, $key = null ) {
		if ( null !== $key && $this->is_secret_key( $key ) ) {
			return '[redacted]';
		}

		if ( is_array( $value ) ) {
			$sanitized = array();

			foreach ( $value as $context_key => $context_value ) {
				$sanitized[ $context_key ] = $this->sanitize_context( $context_value, $context_key );
			}

			return $sanitized;
		}

		if ( is_object( $value ) ) {
			return '[object]';
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		return '[unloggable]';
	}

	/**
	 * Determine whether a context key is sensitive.
	 *
	 * @param string|int $key Context key.
	 * @return bool
	 */
	private function is_secret_key( $key ) {
		$key = strtolower( (string) $key );

		foreach ( array( 'pass', 'password', 'token', 'secret', 'auth', 'credential' ) as $needle ) {
			if ( false !== strpos( $key, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
