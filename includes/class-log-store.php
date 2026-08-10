<?php
/**
 * In-memory log store that surfaces recent SMTP errors in the admin UI.
 */
class MNEM_Log_Store {
	const TRANSIENT_KEY = 'mnem_recent_log';
	const MAX_ENTRIES   = 20;

	/**
	 * Register the hook that captures log records.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'mnem_log', array( $this, 'capture' ) );
	}

	/**
	 * Capture a log record emitted by MNEM_Logger.
	 *
	 * @param array $record Log record with 'level', 'message', 'context' keys.
	 * @return void
	 */
	public function capture( array $record ) {
		if ( ! in_array( $record['level'] ?? '', array( 'error', 'warning' ), true ) ) {
			return;
		}

		$entries   = $this->get_entries();
		$entries[] = array(
			'level'   => $record['level'],
			'message' => $record['message'],
			'time'    => time(),
		);

		// Keep only the most recent entries.
		if ( count( $entries ) > self::MAX_ENTRIES ) {
			$entries = array_slice( $entries, -self::MAX_ENTRIES );
		}

		set_transient( self::TRANSIENT_KEY, $entries, DAY_IN_SECONDS );
	}

	/**
	 * Get stored log entries.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_entries() {
		$entries = get_transient( self::TRANSIENT_KEY );

		return is_array( $entries ) ? $entries : array();
	}

	/**
	 * Clear stored log entries.
	 *
	 * @return void
	 */
	public function clear() {
		delete_transient( self::TRANSIENT_KEY );
	}
}
