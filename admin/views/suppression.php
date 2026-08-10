<?php
/**
 * Admin view: Suppression List — with add/remove forms.
 *
 * Variables available:
 *   $suppression_list (array)
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$suppression_list = isset( $suppression_list ) ? $suppression_list : array();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Suppression List', 'mnem' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Emails on this list will never be sent to by any module of the plugin.', 'mnem' ); ?></p>

	<?php /* ---- Add form ---- */ ?>
	<div class="card" style="max-width:500px;padding:20px;margin-bottom:24px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Add Email', 'mnem' ); ?></h2>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_add_suppression' ) ); ?>">
			<?php wp_nonce_field( 'mnem_add_suppression', 'mnem_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mnem_suppression_email"><?php esc_html_e( 'Email Address', 'mnem' ); ?></label></th>
					<td><input type="email" id="mnem_suppression_email" name="mnem_suppression_email" value="" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="mnem_suppression_reason"><?php esc_html_e( 'Reason (optional)', 'mnem' ); ?></label></th>
					<td><input type="text" id="mnem_suppression_reason" name="mnem_suppression_reason" value="" class="regular-text"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Add to Suppression List', 'mnem' ), 'secondary' ); ?>
		</form>
	</div>

	<?php /* ---- List ---- */ ?>
	<?php if ( ! empty( $suppression_list ) ) : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Email', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Suppressed On', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'mnem' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $suppression_list as $entry ) : ?>
			<tr>
				<td><?php echo esc_html( $entry['email'] ); ?></td>
				<td><?php echo esc_html( $entry['reason'] ); ?></td>
				<td><?php echo esc_html( $entry['created_at'] ); ?></td>
				<td>
					<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_remove_suppression' ) ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'mnem_remove_suppression', 'mnem_nonce' ); ?>
						<input type="hidden" name="mnem_suppression_email" value="<?php echo esc_attr( $entry['email'] ); ?>">
						<button type="submit" class="button-link" style="color:#d63638;"
							onclick="return confirm('<?php echo esc_js( __( 'Remove this email from the suppression list?', 'mnem' ) ); ?>')">
							<?php esc_html_e( 'Remove', 'mnem' ); ?>
						</button>
					</form>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<p><?php esc_html_e( 'No suppressed emails.', 'mnem' ); ?></p>
	<?php endif; ?>
</div>
