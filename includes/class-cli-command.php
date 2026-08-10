<?php
/**
 * WP-CLI command for Multisite Network Email Manager.
 */
class MNEM_CLI_Command extends WP_CLI_Command {
	/**
	 * SMTP settings.
	 *
	 * @var MNEM_SMTP_Settings
	 */
	private $settings;

	/**
	 * SMTP diagnostics.
	 *
	 * @var MNEM_SMTP_Diagnostics
	 */
	private $diagnostics;

	/**
	 * Constructor.
	 *
	 * @param MNEM_SMTP_Settings    $settings    Settings instance.
	 * @param MNEM_SMTP_Diagnostics $diagnostics Diagnostics instance.
	 */
	public function __construct( MNEM_SMTP_Settings $settings, MNEM_SMTP_Diagnostics $diagnostics ) {
		$this->settings    = $settings;
		$this->diagnostics = $diagnostics;
	}

	/**
	 * Test the SMTP connection using the saved network settings.
	 *
	 * ## EXAMPLES
	 *
	 *   wp mnem smtp test-connection
	 *
	 * @subcommand test-connection
	 * @return void
	 */
	public function test_connection() {
		$result = $this->diagnostics->test_connection();

		if ( ! empty( $result['success'] ) ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Send a test email using the saved network settings.
	 *
	 * ## EXAMPLES
	 *
	 *   wp mnem smtp send-test
	 *
	 * @subcommand send-test
	 * @return void
	 */
	public function send_test() {
		$result = $this->diagnostics->send_test_email();

		if ( ! empty( $result['success'] ) ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Display the current SMTP settings (secrets are redacted).
	 *
	 * ## EXAMPLES
	 *
	 *   wp mnem smtp status
	 *
	 * @subcommand status
	 * @return void
	 */
	public function status() {
		$settings = $this->settings->get();
		$rows     = array();

		$display = array(
			'enabled'       => 'Enabled',
			'host'          => 'Host',
			'port'          => 'Port',
			'encryption'    => 'Encryption',
			'auth_enabled'  => 'Auth enabled',
			'username'      => 'Username',
			'from_email'    => 'From email',
			'from_name'     => 'From name',
			'reply_to_email'=> 'Reply-to email',
			'reply_to_name' => 'Reply-to name',
			'test_recipient'=> 'Test recipient',
			'debug_mode'    => 'Debug mode',
		);

		foreach ( $display as $key => $label ) {
			$value = $settings[ $key ] ?? '';

			if ( is_bool( $value ) ) {
				$value = $value ? 'yes' : 'no';
			}

			$rows[] = array(
				'Setting' => $label,
				'Value'   => (string) $value,
			);
		}

		$rows[] = array(
			'Setting' => 'Password',
			'Value'   => empty( $settings['password'] ) ? '(not set)' : '(set)',
		);

		WP_CLI\Utils\format_items( 'table', $rows, array( 'Setting', 'Value' ) );
	}
}
