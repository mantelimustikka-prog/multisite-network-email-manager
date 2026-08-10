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

	/**
	 * Register cron hooks.
	 */
	public static function init() {
		add_action( 'mnem_process_queue', array( __CLASS__, 'process' ) );

		if ( ! wp_next_scheduled( 'mnem_process_queue' ) ) {
			wp_schedule_event( time(), 'every_five_minutes', 'mnem_process_queue' );
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

		if ( MNEM_Suppression::is_suppressed( $job['recipient'] ?? '' ) ) {
			MNEM_Logger::info( 'queue', 'Skipped suppressed recipient', array( 'recipient' => $job['recipient'] ) );
			return false;
		}

		$table = $wpdb->base_prefix . 'mnem_queue';

		$result = $wpdb->insert(
			$table,
			array(
				'campaign_id'  => absint( $job['campaign_id'] ?? 0 ),
				'recipient'    => sanitize_email( $job['recipient'] ?? '' ),
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

		// Mark as processing to prevent duplicate sends.
		$wpdb->update(
			$table,
			array( 'status' => 'processing' ),
			array( 'id' => absint( $job['id'] ) ),
			array( '%s' ),
			array( '%d' )
		);

		$sent = wp_mail(
			$job['recipient'],
			$job['subject'],
			$job['body'],
			array( 'Content-Type: text/html; charset=UTF-8' )
		);

		$attempts = (int) $job['attempts'] + 1;

		if ( $sent ) {
			$wpdb->update(
				$table,
				array(
					'status'   => 'sent',
					'attempts' => $attempts,
					'sent_at'  => current_time( 'mysql', true ),
				),
				array( 'id' => absint( $job['id'] ) ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
			MNEM_Logger::info( 'queue', 'Email sent', array( 'job_id' => $job['id'], 'recipient' => $job['recipient'] ) );
		} else {
			$status = $attempts >= 3 ? 'failed' : 'pending';
			$wpdb->update(
				$table,
				array(
					'status'       => $status,
					'attempts'     => $attempts,
					'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + ( $attempts * 300 ) ),
				),
				array( 'id' => absint( $job['id'] ) ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
			MNEM_Logger::error( 'queue', 'Email failed', array( 'job_id' => $job['id'], 'attempt' => $attempts ) );
		}
	}
}
