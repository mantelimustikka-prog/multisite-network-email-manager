<?php
/**
 * Adapter for outbound WordPress mail sends.
 */
class MNEM_Mailer_Adapter {
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
	 * Send mail using wp_mail.
	 *
	 * @param string|array $to          Recipient(s).
	 * @param string       $subject     Subject line.
	 * @param string       $message     Message body.
	 * @param array        $headers     Optional headers.
	 * @param array        $attachments Optional attachments.
	 * @return bool
	 */
	public function send( $to, $subject, $message, array $headers = array(), array $attachments = array() ) {
		$result = wp_mail( $to, $subject, $message, $headers, $attachments );

		if ( $result ) {
			$this->logger->log(
				'info',
				'Mail queued via WordPress mailer.',
				array(
					'to'      => $to,
					'subject' => $subject,
				)
			);
		} else {
			$this->logger->log(
				'error',
				'WordPress mailer reported a send failure.',
				array(
					'to'      => $to,
					'subject' => $subject,
				)
			);
		}

		return (bool) $result;
	}
}
