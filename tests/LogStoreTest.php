<?php

use PHPUnit\Framework\TestCase;

class LogStoreTest extends TestCase {
	public function test_get_entries_returns_empty_when_nothing_captured() {
		$store = new MNEM_Log_Store();
		$store->clear();

		$this->assertSame( array(), $store->get_entries() );
	}

	public function test_capture_stores_error_entries() {
		$store = new MNEM_Log_Store();
		$store->clear();

		$store->capture(
			array(
				'level'   => 'error',
				'message' => 'Something went wrong.',
				'context' => array(),
			)
		);

		$entries = $store->get_entries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'error', $entries[0]['level'] );
		$this->assertSame( 'Something went wrong.', $entries[0]['message'] );

		$store->clear();
	}

	public function test_capture_ignores_info_entries() {
		$store = new MNEM_Log_Store();
		$store->clear();

		$store->capture(
			array(
				'level'   => 'info',
				'message' => 'All good.',
				'context' => array(),
			)
		);

		$this->assertSame( array(), $store->get_entries() );
	}

	public function test_capture_stores_warning_entries() {
		$store = new MNEM_Log_Store();
		$store->clear();

		$store->capture(
			array(
				'level'   => 'warning',
				'message' => 'A warning.',
				'context' => array(),
			)
		);

		$entries = $store->get_entries();

		$this->assertCount( 1, $entries );
		$this->assertSame( 'warning', $entries[0]['level'] );

		$store->clear();
	}

	public function test_clear_removes_all_entries() {
		$store = new MNEM_Log_Store();

		$store->capture(
			array(
				'level'   => 'error',
				'message' => 'An error.',
				'context' => array(),
			)
		);

		$store->clear();

		$this->assertSame( array(), $store->get_entries() );
	}
}
