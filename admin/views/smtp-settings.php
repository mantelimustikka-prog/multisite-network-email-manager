<?php
/**
 * Admin view: SMTP Settings
 *
 * Variables available in scope:
 *   $settings  (array) — current SMTP settings (password is masked).
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Provide safe defaults if $settings is not set.
$settings = isset( $settings ) && is_array( $settings ) ? $settings : array();
$defaults = array(
	'enabled'        => false,
	'host'           => '',
	'port'           => 587,
	'encryption'     => 'tls',
	'auth_enabled'   => true,
	'username'       => '',
	'password'       => '',
	'from_email'     => '',
	'from_name'      => '',
	'reply_to_email' => '',
	'reply_to_name'  => '',
	'test_recipient' => get_site_option( 'admin_email', '' ),
	'debug_mode'     => false,
);
$settings = wp_parse_args( $settings, $defaults );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'SMTP Settings', 'mnem' ); ?></h1>

	<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_save_smtp_settings' ) ); ?>">
		<?php wp_nonce_field( 'mnem_smtp_settings', 'mnem_nonce' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enable SMTP', 'mnem' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mnem_smtp_enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
						<?php esc_html_e( 'Use SMTP to send emails', 'mnem' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mnem_smtp_host"><?php esc_html_e( 'SMTP Host', 'mnem' ); ?></label></th>
				<td>
					<input type="text" id="mnem_smtp_host" name="mnem_smtp_host" value="<?php echo esc_attr( $settings['host'] ); ?>" class="regular-text" placeholder="smtp.example.com">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mnem_smtp_port"><?php esc_html_e( 'SMTP Port', 'mnem' ); ?></label></th>
				<td>
					<input type="number" id="mnem_smtp_port" name="mnem_smtp_port" value="<?php echo esc_attr( $settings['port'] ); ?>" class="small-text" min="1" max="65535">
					<p class="description"><?php esc_html_e( 'Common ports: 25, 465 (SSL), 587 (TLS/STARTTLS).', 'mnem' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Encryption', 'mnem' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Encryption', 'mnem' ); ?></legend>
						<?php
						$encryptions = array(
							''    => __( 'None', 'mnem' ),
							'tls' => __( 'TLS / STARTTLS', 'mnem' ),
							'ssl' => __( 'SSL', 'mnem' ),
						);
						foreach ( $encryptions as $val => $label ) :
						?>
						<label style="margin-right:16px;">
							<input type="radio" name="mnem_smtp_encryption" value="<?php echo esc_attr( $val ); ?>" <?php checked( $settings['encryption'], $val ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
						<?php endforeach; ?>
					</fieldset>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Authentication', 'mnem' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mnem_smtp_auth_enabled" value="1" <?php checked( $settings['auth_enabled'] ); ?>>
						<?php esc_html_e( 'Enable SMTP authentication', 'mnem' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mnem_smtp_username"><?php esc_html_e( 'SMTP Username', 'mnem' ); ?></label></th>
				<td>
					<input type="text" id="mnem_smtp_username" name="mnem_smtp_username" value="<?php echo esc_attr( $settings['username'] ); ?>" class="regular-text" autocomplete="off">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mnem_smtp_password"><?php esc_html_e( 'SMTP Password / Token', 'mnem' ); ?></label></th>
				<td>
					<input type="password" id="mnem_smtp_password" name="mnem_smtp_password" value="<?php echo esc_attr( $settings['password'] ); ?>" class="regular-text" autocomplete="new-password">
					<p class="description"><?php esc_html_e( 'Leave blank to keep the existing password. Shown as &#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679; if already set.', 'mnem' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mnem_smtp_from_email"><?php esc_html_e( 'From Email', 'mnem' ); ?></label></th>
				<td>
					<input type="email" id="mnem_smtp_from_email" name="mnem_smtp_from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mnem_smtp_from_name"><?php esc_html_e( 'From Name', 'mnem' ); ?></label></th>
				<td>
					<input type="text" id="mnem_smtp_from_name" name="mnem_smtp_from_name" value="<?php echo esc_attr( $settings['from_name'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mnem_smtp_reply_to_email"><?php esc_html_e( 'Reply-To Email', 'mnem' ); ?></label></th>
				<td>
					<input type="email" id="mnem_smtp_reply_to_email" name="mnem_smtp_reply_to_email" value="<?php echo esc_attr( $settings['reply_to_email'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="mnem_smtp_reply_to_name"><?php esc_html_e( 'Reply-To Name', 'mnem' ); ?></label></th>
				<td>
					<input type="text" id="mnem_smtp_reply_to_name" name="mnem_smtp_reply_to_name" value="<?php echo esc_attr( $settings['reply_to_name'] ); ?>" class="regular-text">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Debug Mode', 'mnem' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="mnem_smtp_debug_mode" value="1" <?php checked( $settings['debug_mode'] ); ?>>
						<?php esc_html_e( 'Enable verbose SMTP debug output (do not use in production)', 'mnem' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'mnem' ) ); ?>
	</form>

	<hr>

	<h2><?php esc_html_e( 'Test SMTP', 'mnem' ); ?></h2>

	<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_test_connection' ) ); ?>" style="display:inline;">
		<?php wp_nonce_field( 'mnem_smtp_test', 'mnem_nonce' ); ?>
		<?php submit_button( __( 'Test Connection', 'mnem' ), 'secondary', 'submit', false ); ?>
	</form>

	&nbsp;

	<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_send_test_email' ) ); ?>" style="display:inline-flex;align-items:center;gap:8px;">
		<?php wp_nonce_field( 'mnem_smtp_test', 'mnem_nonce' ); ?>
		<input type="email" name="mnem_test_recipient" value="<?php echo esc_attr( $settings['test_recipient'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Recipient email', 'mnem' ); ?>" required>
		<?php submit_button( __( 'Send Test Email', 'mnem' ), 'secondary', 'submit', false ); ?>
	</form>
</div>
