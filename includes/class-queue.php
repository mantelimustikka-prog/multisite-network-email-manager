<?php
/**
 * Queue — send queue management placeholder.
 *
 * Manages the async email send queue. Jobs are enqueued and processed
 * by a scheduled WP-Cron event.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_Queue {

	const MAX_ATTEMPTS = 3;
	const RETRY_BASE_SECONDS = 300;
	const RETRY_MAX_SECONDS  = 3600;

	/**
	 * Register cron hooks.
	 */
	public static function init() {
		add_action( 'mnem_process_queue', array( __CLASS__, 'process' ) );

		if ( ! wp_next_scheduled( 'mnem_process_queue' ) ) {
			$schedules = wp_get_schedules();
			$interval  = isset( $schedules['every_five_minutes'] ) ? 'every_five_minutes' : 'hourly';
			wp_schedule_event( time(), $interval, 'mnem_process_queue' );
		}
	}

	/**
	 * Enqueue a single email job.
	 *
	 * @param array $job {
	 *     @type int    $campaign_id Campaign ID (0 for transactional).
	 *     @type string $recipient   Recipient email.
	 *     @type string $subject     Email subject.
	 *     @type string $body        Email body (HTML).
	 * }
	 * @return int|false Inserted job ID or false on failure.
	 */
	public static function enqueue( array $job ) {
		global $wpdb;

		$recipient = sanitize_email( $job['recipient'] ?? '' );
		if ( ! is_email( $recipient ) ) {
			return false;
		}

		if ( MNEM_Suppression::is_suppressed( $recipient ) ) {
			MNEM_Logger::info( 'queue', 'Skipped suppressed recipient', array( 'recipient' => $job['recipient'] ) );
			return false;
		}

		$table = $wpdb->base_prefix . 'mnem_queue';

		$result = $wpdb->insert(
			$table,
			array(
				'campaign_id'  => absint( $job['campaign_id'] ?? 0 ),
				'recipient'    => $recipient,
				'subject'      => sanitize_text_field( $job['subject'] ?? '' ),
				'body'         => wp_kses_post( $job['body'] ?? '' ),
				'status'       => 'pending',
				'attempts'     => 0,
				'scheduled_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Process pending queue items (called by WP-Cron).
	 *
	 * Sends up to 50 emails per run to avoid PHP timeouts.
	 */
	public static function process() {
		global $wpdb;

		$table = $wpdb->base_prefix . 'mnem_queue';
		$now   = current_time( 'mysql', true );
		$limit = apply_filters( 'mnem_queue_batch_size', 50 );

		$jobs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY scheduled_at ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$now,
				absint( $limit )
			),
			ARRAY_A
		);

		if ( empty( $jobs ) ) {
			return;
		}

		foreach ( $jobs as $job ) {
			self::send_job( $job );
		}
	}

	/**
	 * Attempt to send a single queue job.
	 *
	 * @param array $job Queue row.
	 */
	private static function send_job( array $job ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'mnem_queue';
		$job_id = absint( $job['id'] );

		// Atomically claim only pending, due jobs to reduce duplicate sends.
		$claimed = $wpdb->update(
			$table,
			array( 'status' => 'processing' ),
			array(
				'id'     => $job_id,
				'status' => 'pending',
			),
			array( '%s' ),
			array( '%d', '%s' )
		);
		if ( 1 !== (int) $claimed ) {
			return;
		}

		if ( ! is_email( $job['recipient'] ) ) {
			self::mark_failed( $job_id, (int) $job['attempts'] + 1, __( 'Invalid recipient address.', 'mnem' ) );
			return;
		}

		try {
			$sent = wp_mail(
				$job['recipient'],
				$job['subject'],
				$job['body'],
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
		} catch ( Throwable $e ) {
			$sent = false;
		}

		$attempts = (int) $job['attempts'] + 1;

		if ( $sent ) {
			$wpdb->update(
				$table,
				array(
					'status'   => 'sent',
					'attempts' => $attempts,
					'sent_at'  => current_time( 'mysql', true ),
				),
				array( 'id' => $job_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
			MNEM_Logger::info( 'queue', 'Email sent', array( 'job_id' => $job_id, 'recipient' => $job['recipient'] ) );
		} else {
			$delay = self::calculate_retry_delay( $attempts );
			$status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
			$wpdb->update(
				$table,
				array(
					'status'       => $status,
					'attempts'     => $attempts,
					'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + $delay ),
				),
				array( 'id' => $job_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
			MNEM_Logger::error( 'queue', 'Email failed', array( 'job_id' => $job_id, 'attempt' => $attempts, 'retry_delay' => $delay ) );
		}
	}

	/**
	 * Mark queue job failed immediately.
	 *
	 * @param int    $job_id    Queue ID.
	 * @param int    $attempts  Attempts count.
	 * @param string $reason    Failure reason.
	 */
	private static function mark_failed( $job_id, $attempts, $reason ) {
		global $wpdb;
		$table = $wpdb->base_prefix . 'mnem_queue';
		$wpdb->update(
			$table,
			array(
				'status'   => 'failed',
				'attempts' => absint( $attempts ),
			),
			array( 'id' => absint( $job_id ) ),
			array( '%s', '%d' ),
			array( '%d' )
		);
		MNEM_Logger::error( 'queue', 'Queue job failed', array( 'job_id' => absint( $job_id ), 'reason' => $reason ) );
	}

	/**
	 * Calculate retry backoff delay in seconds.
	 *
	 * @param int $attempts Attempt number (1-indexed).
	 * @return int
	 */
	private static function calculate_retry_delay( $attempts ) {
		$attempts = max( 1, absint( $attempts ) );
		$delay    = self::RETRY_BASE_SECONDS * ( 1 << ( $attempts - 1 ) );
		return min( self::RETRY_MAX_SECONDS, $delay );
	}
}
