<?php
/**
 * WP-Cron-based mail retry queue for transient SMTP failures.
 */
class MNEM_Mail_Queue {
	const CRON_HOOK     = 'mnem_process_mail_queue';
	const QUEUE_OPTION  = 'mnem_mail_queue';
	const MAX_ATTEMPTS  = 3;
	const RETRY_DELAY   = 300; // seconds (5 minutes)

	/**
	 * Logger instance.
	 *
	 * @var MNEM_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param MNEM_Logger $logger Logger instance.
	 */
	public function __construct( MNEM_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( self::CRON_HOOK, array( $this, 'process_queue' ) );
	}

	/**
	 * Push a failed message onto the queue.
	 *
	 * @param string|array $to          Recipient(s).
	 * @param string       $subject     Subject.
	 * @param string       $message     Body.
	 * @param array        $headers     Headers.
	 * @param array        $attachments Attachments.
	 * @return void
	 */
	public function enqueue( $to, $subject, $message, array $headers = array(), array $attachments = array() ) {
		$queue = $this->load_queue();

		$queue[] = array(
			'to'          => $to,
			'subject'     => $subject,
			'message'     => $message,
			'headers'     => $headers,
			'attachments' => $attachments,
			'attempts'    => 0,
			'queued_at'   => time(),
		);

		$this->save_queue( $queue );
		$this->maybe_schedule();

		$this->logger->log(
			'info',
			'Mail queued for retry.',
			array(
				'to'      => $to,
				'subject' => $subject,
			)
		);
	}

	/**
	 * Process all queued messages.
	 *
	 * @return void
	 */
	public function process_queue() {
		$queue   = $this->load_queue();
		$pending = array();

		foreach ( $queue as $item ) {
			$attempts = (int) ( $item['attempts'] ?? 0 );

			if ( $attempts >= self::MAX_ATTEMPTS ) {
				$this->logger->log(
					'error',
					'Mail dropped after maximum retry attempts.',
					array(
						'to'       => $item['to'],
						'subject'  => $item['subject'],
						'attempts' => $attempts,
					)
				);
				continue;
			}

			$result = wp_mail(
				$item['to'],
				$item['subject'],
				$item['message'],
				$item['headers'],
				$item['attachments']
			);

			if ( $result ) {
				$this->logger->log(
					'info',
					'Queued mail sent successfully.',
					array(
						'to'      => $item['to'],
						'subject' => $item['subject'],
					)
				);
				continue;
			}

			$item['attempts'] = $attempts + 1;
			$pending[]        = $item;

			$this->logger->log(
				'warning',
				'Queued mail send failed, will retry.',
				array(
					'to'       => $item['to'],
					'subject'  => $item['subject'],
					'attempts' => $item['attempts'],
				)
			);
		}

		$this->save_queue( $pending );

		if ( ! empty( $pending ) ) {
			$this->maybe_schedule();
		}
	}

	/**
	 * Return the number of messages currently in the queue.
	 *
	 * @return int
	 */
	public function queue_size() {
		return count( $this->load_queue() );
	}

	/**
	 * Clear all queued messages (e.g. on deactivation).
	 *
	 * @return void
	 */
	public function clear_queue() {
		delete_site_option( self::QUEUE_OPTION );
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Load the persisted queue.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function load_queue() {
		$queue = get_site_option( self::QUEUE_OPTION, array() );

		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Persist the queue.
	 *
	 * @param array $queue Queue data.
	 * @return void
	 */
	private function save_queue( array $queue ) {
		update_site_option( self::QUEUE_OPTION, array_values( $queue ) );
	}

	/**
	 * Schedule the cron event if not already scheduled.
	 *
	 * @return void
	 */
	private function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + self::RETRY_DELAY, self::CRON_HOOK );
		}
	}
}
