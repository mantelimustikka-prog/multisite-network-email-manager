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
	'enabled'               => false,
	'provider'              => 'custom',
	'host'                  => '',
	'port'                  => 587,
	'encryption'            => 'tls',
	'auth_enabled'          => true,
	'username'              => '',
	'password'              => '',
	'from_email'            => '',
	'from_name'             => '',
	'reply_to_email'        => '',
	'reply_to_name'         => '',
	'test_recipient'        => get_site_option( 'admin_email', '' ),
	'debug_mode'            => false,
	'rate_limit_per_minute' => 0,
	'rate_limit_per_hour'   => 0,
	'sender_mode'           => 'network_global',
	'force_sender'          => false,
	'global_header'         => '',
	'global_footer'         => '',
);
$settings          = wp_parse_args( $settings, $defaults );
$provider_presets  = isset( $provider_presets ) && is_array( $provider_presets ) ? $provider_presets : MNEM_SMTP_Settings::get_provider_presets();
$active_tab        = isset( $active_tab ) ? $active_tab : 'smtp';
$allowed_tabs      = array(
	'smtp'    => __( 'SMTP Settings', 'mnem' ),
	'sender'  => __( 'Sender Settings', 'mnem' ),
	'content' => __( 'Global Header & Footer', 'mnem' ),
);
$active_tab        = array_key_exists( $active_tab, $allowed_tabs ) ? $active_tab : 'smtp';
$active_provider   = isset( $provider_presets[ $settings['provider'] ] ) ? $provider_presets[ $settings['provider'] ] : $provider_presets['custom'];
$username_label    = ! empty( $active_provider['username_label'] ) ? $active_provider['username_label'] : __( 'SMTP Username', 'mnem' );
$credential_label  = ! empty( $active_provider['credential_label'] ) ? $active_provider['credential_label'] : __( 'SMTP Password / Token', 'mnem' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'SMTP Settings', 'mnem' ); ?></h1>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Email settings tabs', 'mnem' ); ?>">
		<?php foreach ( $allowed_tabs as $tab_key => $tab_label ) : ?>
			<?php
			$tab_url = add_query_arg(
				array(
					'page' => 'mnem-smtp-settings',
					'tab'  => $tab_key,
				),
				network_admin_url( 'admin.php' )
			);
			?>
			<a href="<?php echo esc_url( $tab_url ); ?>" class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $tab_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_save_smtp_settings' ) ); ?>">
		<?php wp_nonce_field( 'mnem_smtp_settings', 'mnem_nonce' ); ?>
		<input type="hidden" name="mnem_smtp_active_tab" value="<?php echo esc_attr( $active_tab ); ?>">

		<?php if ( 'smtp' === $active_tab ) : ?>
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
					<th scope="row"><label for="mnem_smtp_provider"><?php esc_html_e( 'SMTP Provider', 'mnem' ); ?></label></th>
					<td>
						<select id="mnem_smtp_provider" name="mnem_smtp_provider">
							<?php foreach ( $provider_presets as $provider_key => $provider ) : ?>
								<option value="<?php echo esc_attr( $provider_key ); ?>" <?php selected( $settings['provider'], $provider_key ); ?>><?php echo esc_html( $provider['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php echo esc_html( $active_provider['help'] ); ?></p>
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
						<p class="description"><?php esc_html_e( 'Common ports: 25, 465 (SSL), 587 (TLS/STARTTLS). Provider presets fill recommended defaults and still allow custom overrides.', 'mnem' ); ?></p>
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
					<th scope="row"><label for="mnem_smtp_username"><?php echo esc_html( $username_label ); ?></label></th>
					<td>
						<input type="text" id="mnem_smtp_username" name="mnem_smtp_username" value="<?php echo esc_attr( $settings['username'] ); ?>" class="regular-text" autocomplete="off">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mnem_smtp_password"><?php echo esc_html( $credential_label ); ?></label></th>
					<td>
						<input type="password" id="mnem_smtp_password" name="mnem_smtp_password" value="<?php echo esc_attr( $settings['password'] ); ?>" class="regular-text" autocomplete="new-password">
						<p class="description"><?php esc_html_e( 'Leave blank to keep the existing password or token. Shown as &#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679;&#9679; if already set.', 'mnem' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mnem_smtp_from_email"><?php esc_html_e( 'Network Sender Email', 'mnem' ); ?></label></th>
					<td>
						<input type="email" id="mnem_smtp_from_email" name="mnem_smtp_from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Used by Global Network sender mode and as a fallback when a site-based sender email is unavailable.', 'mnem' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mnem_smtp_from_name"><?php esc_html_e( 'Network Sender Name', 'mnem' ); ?></label></th>
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
					<th scope="row"><label for="mnem_smtp_rate_limit_per_minute"><?php esc_html_e( 'Maximum Emails Per Minute', 'mnem' ); ?></label></th>
					<td>
						<input type="number" id="mnem_smtp_rate_limit_per_minute" name="mnem_smtp_rate_limit_per_minute" value="<?php echo esc_attr( $settings['rate_limit_per_minute'] ); ?>" class="small-text" min="0" step="1">
						<p class="description"><?php esc_html_e( 'Set to 0 to disable the per-minute threshold. The limit applies across the multisite network and works with the existing queue retry flow.', 'mnem' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mnem_smtp_rate_limit_per_hour"><?php esc_html_e( 'Maximum Emails Per Hour', 'mnem' ); ?></label></th>
					<td>
						<input type="number" id="mnem_smtp_rate_limit_per_hour" name="mnem_smtp_rate_limit_per_hour" value="<?php echo esc_attr( $settings['rate_limit_per_hour'] ); ?>" class="small-text" min="0" step="1">
						<p class="description"><?php esc_html_e( 'Set to 0 to disable the per-hour threshold.', 'mnem' ); ?></p>
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
		<?php elseif ( 'sender' === $active_tab ) : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mnem_smtp_sender_mode"><?php esc_html_e( 'Sender Source', 'mnem' ); ?></label></th>
					<td>
						<select id="mnem_smtp_sender_mode" name="mnem_smtp_sender_mode">
							<option value="master_site" <?php selected( $settings['sender_mode'], 'master_site' ); ?>><?php esc_html_e( 'Master site based sender information', 'mnem' ); ?></option>
							<option value="child_site" <?php selected( $settings['sender_mode'], 'child_site' ); ?>><?php esc_html_e( 'Child site based sender information', 'mnem' ); ?></option>
							<option value="network_global" <?php selected( $settings['sender_mode'], 'network_global' ); ?>><?php esc_html_e( 'Global network based sender information', 'mnem' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Global network mode uses the Network Sender Name and Network Sender Email configured in the SMTP Settings tab.', 'mnem' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Force Sender Information', 'mnem' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="mnem_smtp_force_sender" value="1" <?php checked( $settings['force_sender'] ); ?>>
							<?php esc_html_e( 'Remove sender information prepared by other plugins and replace it with this plugin’s configured sender information.', 'mnem' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'When disabled, existing From and Reply-To information is respected where possible.', 'mnem' ); ?></p>
					</td>
				</tr>
			</table>
		<?php else : ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mnem_smtp_global_header"><?php esc_html_e( 'Global Header', 'mnem' ); ?></label></th>
					<td>
						<textarea id="mnem_smtp_global_header" name="mnem_smtp_global_header" rows="8" class="large-text code"><?php echo esc_textarea( $settings['global_header'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'HTML is allowed for email template content. Plain-text emails will use a text-only version of this content.', 'mnem' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="mnem_smtp_global_footer"><?php esc_html_e( 'Global Footer', 'mnem' ); ?></label></th>
					<td>
						<textarea id="mnem_smtp_global_footer" name="mnem_smtp_global_footer" rows="8" class="large-text code"><?php echo esc_textarea( $settings['global_footer'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'This content is appended to outgoing plugin-managed emails before they are handed to the configured mail transport.', 'mnem' ); ?></p>
					</td>
				</tr>
			</table>
		<?php endif; ?>

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
