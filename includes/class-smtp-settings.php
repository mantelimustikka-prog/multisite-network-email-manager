<?php
/**
 * SMTP settings management.
 */
class MNEM_SMTP_Settings {
	const OPTION_KEY = 'mnem_smtp_settings';

	/**
	 * Logger instance.
	 *
	 * @var MNEM_Logger
	 */
	private $logger;

	/**
	 * Diagnostics instance.
	 *
	 * @var MNEM_SMTP_Diagnostics|null
	 */
	private $diagnostics;

	/**
	 * Constructor.
	 *
	 * @param MNEM_Logger $logger Logger instance.
	 */
	public function __construct( MNEM_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Attach diagnostics dependency.
	 *
	 * @param MNEM_SMTP_Diagnostics $diagnostics Diagnostics instance.
	 * @return void
	 */
	public function set_diagnostics( MNEM_SMTP_Diagnostics $diagnostics ) {
		$this->diagnostics = $diagnostics;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'network_admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'network_admin_edit_mnem_smtp_save', array( $this, 'handle_save' ) );
		add_action( 'network_admin_edit_mnem_smtp_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'network_admin_edit_mnem_smtp_send_test_email', array( $this, 'handle_send_test_email' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'enabled'         => false,
			'host'            => '',
			'port'            => 587,
			'encryption'      => '',
			'auth_enabled'    => false,
			'username'        => '',
			'password'        => '',
			'from_email'      => '',
			'from_name'       => '',
			'reply_to_email'  => '',
			'reply_to_name'   => '',
			'test_recipient'  => '',
			'debug_mode'      => false,
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array<string,mixed>
	 */
	public function get() {
		$saved = get_site_option( self::OPTION_KEY, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Update settings.
	 *
	 * @param array $input Raw input.
	 * @return array<string,mixed>
	 */
	public function update( array $input ) {
		$sanitized = $this->sanitize_settings( $input, $this->get() );
		update_site_option( self::OPTION_KEY, $sanitized );

		return $sanitized;
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param array $input    Raw input.
	 * @param array $existing Existing settings.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( array $input, array $existing = array() ) {
		$existing   = wp_parse_args( $existing, self::defaults() );
		$encryption = isset( $input['encryption'] ) ? strtolower( sanitize_text_field( wp_unslash( $input['encryption'] ) ) ) : '';

		if ( ! in_array( $encryption, array( '', 'tls', 'ssl' ), true ) ) {
			$encryption = '';
		}

		$password = isset( $input['password'] ) ? (string) wp_unslash( $input['password'] ) : '';
		$password = trim( $password );
		if ( '' === $password ) {
			$password = (string) $existing['password'];
		}

		return array(
			'enabled'        => ! empty( $input['enabled'] ),
			'host'           => sanitize_text_field( wp_unslash( $input['host'] ?? '' ) ),
			'port'           => max( 0, absint( $input['port'] ?? 0 ) ),
			'encryption'     => $encryption,
			'auth_enabled'   => ! empty( $input['auth_enabled'] ),
			'username'       => sanitize_text_field( wp_unslash( $input['username'] ?? '' ) ),
			'password'       => $password,
			'from_email'     => sanitize_email( wp_unslash( $input['from_email'] ?? '' ) ),
			'from_name'      => sanitize_text_field( wp_unslash( $input['from_name'] ?? '' ) ),
			'reply_to_email' => sanitize_email( wp_unslash( $input['reply_to_email'] ?? '' ) ),
			'reply_to_name'  => sanitize_text_field( wp_unslash( $input['reply_to_name'] ?? '' ) ),
			'test_recipient' => sanitize_email( wp_unslash( $input['test_recipient'] ?? '' ) ),
			'debug_mode'     => ! empty( $input['debug_mode'] ),
		);
	}

	/**
	 * Register the network settings page.
	 *
	 * @return void
	 */
	public function register_admin_page() {
		add_submenu_page(
			'settings.php',
			__( 'SMTP Settings', 'multisite-network-email-manager' ),
			__( 'SMTP Settings', 'multisite-network-email-manager' ),
			'manage_network_options',
			'mnem-smtp-settings',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the network settings page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'multisite-network-email-manager' ) );
		}

		$settings = $this->get();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Multisite Network Email Manager SMTP', 'multisite-network-email-manager' ); ?></h1>
			<?php $this->render_notice(); ?>
			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_smtp_save' ) ); ?>">
				<?php wp_nonce_field( 'mnem_smtp_save' ); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<?php $this->render_checkbox_row( 'enabled', __( 'Enable SMTP', 'multisite-network-email-manager' ), ! empty( $settings['enabled'] ) ); ?>
						<?php $this->render_text_row( 'host', __( 'SMTP host', 'multisite-network-email-manager' ), $settings['host'] ); ?>
						<?php $this->render_number_row( 'port', __( 'SMTP port', 'multisite-network-email-manager' ), (int) $settings['port'] ); ?>
						<?php $this->render_encryption_row( $settings['encryption'] ); ?>
						<?php $this->render_checkbox_row( 'auth_enabled', __( 'Enable authentication', 'multisite-network-email-manager' ), ! empty( $settings['auth_enabled'] ) ); ?>
						<?php $this->render_text_row( 'username', __( 'SMTP username', 'multisite-network-email-manager' ), $settings['username'] ); ?>
						<?php $this->render_password_row( $settings['password'] ); ?>
						<?php $this->render_email_row( 'from_email', __( 'From email', 'multisite-network-email-manager' ), $settings['from_email'] ); ?>
						<?php $this->render_text_row( 'from_name', __( 'From name', 'multisite-network-email-manager' ), $settings['from_name'] ); ?>
						<?php $this->render_email_row( 'reply_to_email', __( 'Reply-to email', 'multisite-network-email-manager' ), $settings['reply_to_email'] ); ?>
						<?php $this->render_text_row( 'reply_to_name', __( 'Reply-to name', 'multisite-network-email-manager' ), $settings['reply_to_name'] ); ?>
						<?php $this->render_email_row( 'test_recipient', __( 'Test recipient', 'multisite-network-email-manager' ), $settings['test_recipient'] ); ?>
						<?php $this->render_checkbox_row( 'debug_mode', __( 'Enable safe debug logging', 'multisite-network-email-manager' ), ! empty( $settings['debug_mode'] ) ); ?>
					</tbody>
				</table>
				<?php submit_button( __( 'Save SMTP settings', 'multisite-network-email-manager' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Diagnostics', 'multisite-network-email-manager' ); ?></h2>
			<p><?php esc_html_e( 'Run diagnostics against the currently saved SMTP settings.', 'multisite-network-email-manager' ); ?></p>
			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_smtp_test_connection' ) ); ?>" style="display:inline-block;margin-right:12px;">
				<?php wp_nonce_field( 'mnem_smtp_test_connection' ); ?>
				<?php submit_button( __( 'Test connection', 'multisite-network-email-manager' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_smtp_send_test_email' ) ); ?>" style="display:inline-block;">
				<?php wp_nonce_field( 'mnem_smtp_send_test_email' ); ?>
				<?php submit_button( __( 'Send test email', 'multisite-network-email-manager' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save() {
		$this->assert_permissions();
		check_admin_referer( 'mnem_smtp_save' );

		$this->update( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$this->logger->log( 'info', 'SMTP settings updated.' );

		$this->redirect_with_notice( 'success', __( 'SMTP settings saved.', 'multisite-network-email-manager' ) );
	}

	/**
	 * Handle test connection action.
	 *
	 * @return void
	 */
	public function handle_test_connection() {
		$this->assert_permissions();
		check_admin_referer( 'mnem_smtp_test_connection' );

		if ( ! $this->diagnostics ) {
			$this->redirect_with_notice( 'error', __( 'SMTP diagnostics are not available.', 'multisite-network-email-manager' ) );
		}

		$result = $this->diagnostics->test_connection();
		$status = ! empty( $result['success'] ) ? 'success' : 'error';
		$this->redirect_with_notice( $status, $result['message'] );
	}

	/**
	 * Handle test email action.
	 *
	 * @return void
	 */
	public function handle_send_test_email() {
		$this->assert_permissions();
		check_admin_referer( 'mnem_smtp_send_test_email' );

		if ( ! $this->diagnostics ) {
			$this->redirect_with_notice( 'error', __( 'SMTP diagnostics are not available.', 'multisite-network-email-manager' ) );
		}

		$result = $this->diagnostics->send_test_email();
		$status = ! empty( $result['success'] ) ? 'success' : 'error';
		$this->redirect_with_notice( $status, $result['message'] );
	}

	/**
	 * Render a page notice.
	 *
	 * @return void
	 */
	private function render_notice() {
		if ( empty( $_GET['mnem_notice'] ) || empty( $_GET['mnem_message'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$type    = sanitize_key( wp_unslash( $_GET['mnem_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( wp_unslash( $_GET['mnem_message'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$class   = 'notice notice-success';

		if ( 'error' === $type ) {
			$class = 'notice notice-error';
		} elseif ( 'warning' === $type ) {
			$class = 'notice notice-warning';
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Render a checkbox row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param bool   $value Field value.
	 * @return void
	 */
	private function render_checkbox_row( $name, $label, $value ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value ); ?> /> <?php esc_html_e( 'Enabled', 'multisite-network-email-manager' ); ?></label></td>
		</tr>
		<?php
	}

	/**
	 * Render a text row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return void
	 */
	private function render_text_row( $name, $label, $value ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input class="regular-text" type="text" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" /></td>
		</tr>
		<?php
	}

	/**
	 * Render an email row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return void
	 */
	private function render_email_row( $name, $label, $value ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input class="regular-text" type="email" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" /></td>
		</tr>
		<?php
	}

	/**
	 * Render a number row.
	 *
	 * @param string $name  Field name.
	 * @param string $label Field label.
	 * @param int    $value Field value.
	 * @return void
	 */
	private function render_number_row( $name, $label, $value ) {
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input class="small-text" type="number" min="0" id="<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" /></td>
		</tr>
		<?php
	}

	/**
	 * Render the encryption selector.
	 *
	 * @param string $value Selected value.
	 * @return void
	 */
	private function render_encryption_row( $value ) {
		?>
		<tr>
			<th scope="row"><label for="encryption"><?php esc_html_e( 'Encryption', 'multisite-network-email-manager' ); ?></label></th>
			<td>
				<select id="encryption" name="encryption">
					<option value="" <?php selected( '', $value ); ?>><?php esc_html_e( 'None', 'multisite-network-email-manager' ); ?></option>
					<option value="tls" <?php selected( 'tls', $value ); ?>>TLS</option>
					<option value="ssl" <?php selected( 'ssl', $value ); ?>>SSL</option>
				</select>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the password row.
	 *
	 * @param string $password Saved password.
	 * @return void
	 */
	private function render_password_row( $password ) {
		?>
		<tr>
			<th scope="row"><label for="password"><?php esc_html_e( 'SMTP password / token', 'multisite-network-email-manager' ); ?></label></th>
			<td>
				<input class="regular-text" type="password" id="password" name="password" value="" autocomplete="new-password" />
				<?php if ( ! empty( $password ) ) : ?>
					<p class="description"><?php esc_html_e( 'A password is already stored. Leave this blank to keep the current secret.', 'multisite-network-email-manager' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Ensure the current user can manage network options.
	 *
	 * @return void
	 */
	private function assert_permissions() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage these settings.', 'multisite-network-email-manager' ) );
		}
	}

	/**
	 * Redirect back to the settings page with a notice.
	 *
	 * @param string $type    Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function redirect_with_notice( $type, $message ) {
		$url = add_query_arg(
			array(
				'page'         => 'mnem-smtp-settings',
				'mnem_notice'  => sanitize_key( $type ),
				'mnem_message' => wp_strip_all_tags( (string) $message ),
			),
			network_admin_url( 'settings.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
