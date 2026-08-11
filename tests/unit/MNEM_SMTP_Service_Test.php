<?php
/**
 * Unit tests for MNEM_SMTP_Service.
 *
 * @package MNEM
 */

use PHPUnit\Framework\TestCase;

class MNEM_SMTP_Service_Test extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_mnem_site_options']    = array();
		$GLOBALS['_mnem_current_blog_id'] = 2;
		$GLOBALS['_mnem_main_site_id']    = 1;

		$ref   = new ReflectionClass( MNEM_Settings::class );
		$cache = $ref->getProperty( 'cache' );
		$cache->setAccessible( true );
		$cache->setValue( null, array() );

		$service_ref     = new ReflectionClass( MNEM_SMTP_Service::class );
		$send_timestamps = $service_ref->getProperty( 'send_timestamps' );
		$send_timestamps->setAccessible( true );
		$send_timestamps->setValue( null, null );
	}

	public function test_force_sender_removes_from_and_reply_to_headers() {
		MNEM_SMTP_Settings::save( array( 'force_sender' => true ) );

		$args = MNEM_SMTP_Service::filter_wp_mail(
			array(
				'message' => 'Body',
				'headers' => array(
					'From: Other Sender <other@example.com>',
					'Reply-To: reply@example.com',
					'Content-Type: text/html; charset=UTF-8',
				),
			)
		);

		$this->assertSame(
			array( 'Content-Type: text/html; charset=UTF-8' ),
			$args['headers']
		);
	}

	public function test_html_message_receives_global_header_and_footer() {
		MNEM_SMTP_Settings::save(
			array(
				'global_header' => '<p>Header</p>',
				'global_footer' => '<p>Footer</p>',
			)
		);

		$args = MNEM_SMTP_Service::filter_wp_mail(
			array(
				'message' => '<p>Body</p>',
				'headers' => array( 'Content-Type: text/html; charset=UTF-8' ),
			)
		);

		$this->assertStringContainsString( '<div class="mnem-global-header"><p>Header</p></div>', $args['message'] );
		$this->assertStringContainsString( '<p>Body</p>', $args['message'] );
		$this->assertStringContainsString( '<div class="mnem-global-footer"><p>Footer</p></div>', $args['message'] );
	}

	public function test_plain_text_message_uses_text_only_header_and_footer() {
		MNEM_SMTP_Settings::save(
			array(
				'global_header' => '<strong>Header</strong>',
				'global_footer' => '<em>Footer</em>',
			)
		);

		$args = MNEM_SMTP_Service::filter_wp_mail(
			array(
				'message' => 'Body',
				'headers' => array(),
			)
		);

		$this->assertSame( "Header\n\nBody\n\nFooter", $args['message'] );
	}

	public function test_configure_mailer_uses_master_site_sender_details() {
		MNEM_SMTP_Settings::save(
			array(
				'host'        => 'smtp.example.com',
				'port'        => 587,
				'sender_mode' => 'master_site',
			)
		);

		$mailer = new MNEM_Test_Fake_Mailer();
		MNEM_SMTP_Service::configure_mailer( $mailer );

		$this->assertTrue( $mailer->is_smtp );
		$this->assertSame( 'network@example.com', $mailer->From );
		$this->assertSame( 'Network Site', $mailer->FromName );
		$this->assertSame(
			array(
				array(
					'email' => 'network@example.com',
					'name'  => 'Network Site',
				),
			),
			$mailer->getReplyToAddresses()
		);
	}

	public function test_configure_mailer_respects_existing_sender_when_force_is_disabled() {
		MNEM_SMTP_Settings::save(
			array(
				'host'         => 'smtp.example.com',
				'port'         => 587,
				'sender_mode'  => 'network_global',
				'from_email'   => 'plugin@example.com',
				'from_name'    => 'Plugin Sender',
				'force_sender' => false,
			)
		);

		$mailer           = new MNEM_Test_Fake_Mailer();
		$mailer->From     = 'existing@example.com';
		$mailer->FromName = 'Existing Sender';

		MNEM_SMTP_Service::configure_mailer( $mailer );

		$this->assertSame( 'existing@example.com', $mailer->From );
		$this->assertSame( 'Existing Sender', $mailer->FromName );
	}

	public function test_rate_limit_blocks_after_recorded_success() {
		MNEM_SMTP_Settings::save( array( 'rate_limit_per_minute' => 1 ) );
		MNEM_SMTP_Service::record_successful_send( array( 'to' => 'one@example.com' ) );

		$this->assertFalse(
			MNEM_SMTP_Service::maybe_throttle_mail(
				null,
				array( 'to' => 'two@example.com' )
			)
		);
	}
}

class MNEM_Test_Fake_Mailer {
	public $is_smtp = false;
	public $Host = '';
	public $Port = 0;
	public $SMTPAuth = false;
	public $Username = '';
	public $Password = '';
	public $SMTPDebug = 0;
	public $SMTPSecure = '';
	public $From = '';
	public $FromName = '';
	private $reply_tos = array();

	public function isSMTP() {
		$this->is_smtp = true;
	}

	public function setFrom( $email, $name = '' ) {
		$this->From     = $email;
		$this->FromName = $name;
	}

	public function addReplyTo( $email, $name = '' ) {
		$this->reply_tos[] = array(
			'email' => $email,
			'name'  => $name,
		);
	}

	public function getReplyToAddresses() {
		return $this->reply_tos;
	}

	public function clearReplyTos() {
		$this->reply_tos = array();
	}
}
