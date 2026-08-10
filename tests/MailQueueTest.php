<?php

use PHPUnit\Framework\TestCase;

class MailQueueTest extends TestCase {
	public function test_queue_size_starts_at_zero() {
		$logger = new MNEM_Logger();
		$queue  = new MNEM_Mail_Queue( $logger );

		$this->assertSame( 0, $queue->queue_size() );
	}

	public function test_enqueue_increases_queue_size() {
		$logger = new MNEM_Logger();
		$queue  = new MNEM_Mail_Queue( $logger );

		// Ensure a clean slate by clearing before enqueue.
		$queue->clear_queue();
		$queue->enqueue( 'to@example.com', 'Subject', 'Body' );

		$this->assertSame( 1, $queue->queue_size() );

		// Clean up.
		$queue->clear_queue();
	}

	public function test_clear_queue_empties_the_queue() {
		$logger = new MNEM_Logger();
		$queue  = new MNEM_Mail_Queue( $logger );

		$queue->enqueue( 'to@example.com', 'Subject', 'Body' );
		$queue->clear_queue();

		$this->assertSame( 0, $queue->queue_size() );
	}

	public function test_process_queue_sends_and_clears_successful_items() {
		$logger = new MNEM_Logger();
		$queue  = new MNEM_Mail_Queue( $logger );

		$queue->clear_queue();
		$queue->enqueue( 'to@example.com', 'Subject', 'Body' );
		$queue->process_queue();

		// wp_mail() stub always returns true, so queue should be empty.
		$this->assertSame( 0, $queue->queue_size() );
	}
}
