<?php
/**
 * Admin view: Dashboard
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Multisite Network Email Manager', 'mnem' ); ?></h1>
	<p><?php esc_html_e( 'Welcome to the Network Email Manager. Use the menu items on the left to configure SMTP, manage campaigns, review the send queue, handle suppressions, and view logs.', 'mnem' ); ?></p>

	<div class="mnem-dashboard-cards" style="display:flex;flex-wrap:wrap;gap:16px;margin-top:24px;">
		<div class="card" style="min-width:200px;">
			<h2><?php esc_html_e( 'SMTP', 'mnem' ); ?></h2>
			<?php
			$smtp_enabled = MNEM_SMTP_Settings::get( 'enabled', false );
			if ( $smtp_enabled ) {
				echo '<p style="color:green;">&#10003; ' . esc_html__( 'SMTP is enabled', 'mnem' ) . '</p>';
			} else {
				echo '<p style="color:#888;">&#9679; ' . esc_html__( 'SMTP is disabled', 'mnem' ) . '</p>';
			}
			?>
			<p><a href="<?php echo esc_url( network_admin_url( 'admin.php?page=mnem-smtp-settings' ) ); ?>"><?php esc_html_e( 'Configure SMTP &rarr;', 'mnem' ); ?></a></p>
		</div>

		<div class="card" style="min-width:200px;">
			<h2><?php esc_html_e( 'Plugin Version', 'mnem' ); ?></h2>
			<p><?php echo esc_html( MNEM_VERSION ); ?></p>
			<p><?php
				/* translators: %s: database schema version */
				printf( esc_html__( 'DB schema: %s', 'mnem' ), esc_html( MNEM_DB_VERSION ) );
			?></p>
		</div>
	</div>
</div>
