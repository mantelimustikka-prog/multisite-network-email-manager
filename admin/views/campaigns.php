<?php
/**
 * Admin view: Campaigns — list + create/edit form.
 *
 * Variables available:
 *   $campaigns     (array)       — list of campaign rows.
 *   $edit_campaign (array|null)  — campaign being edited, or null for new.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$campaigns     = isset( $campaigns ) ? $campaigns : array();
$edit_campaign = isset( $edit_campaign ) ? $edit_campaign : null;

$form_title   = $edit_campaign ? __( 'Edit Campaign', 'mnem' ) : __( 'New Campaign', 'mnem' );
$campaign_id  = $edit_campaign ? absint( $edit_campaign['id'] ) : 0;
$name_value   = $edit_campaign ? esc_attr( $edit_campaign['name'] ) : '';
$subj_value   = $edit_campaign ? esc_attr( $edit_campaign['subject'] ) : '';
$body_value   = $edit_campaign ? esc_textarea( $edit_campaign['body'] ) : '';
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Campaigns', 'mnem' ); ?></h1>

	<?php /* ---- Create / Edit form ---- */ ?>
	<div class="card" style="max-width:700px;padding:20px;margin-bottom:24px;">
		<h2 style="margin-top:0;"><?php echo esc_html( $form_title ); ?></h2>
		<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_save_campaign' ) ); ?>">
			<?php wp_nonce_field( 'mnem_save_campaign', 'mnem_nonce' ); ?>
			<input type="hidden" name="mnem_campaign_id" value="<?php echo esc_attr( $campaign_id ); ?>">

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="mnem_campaign_name"><?php esc_html_e( 'Campaign Name', 'mnem' ); ?></label></th>
					<td><input type="text" id="mnem_campaign_name" name="mnem_campaign_name" value="<?php echo $name_value; ?>" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="mnem_campaign_subject"><?php esc_html_e( 'Email Subject', 'mnem' ); ?></label></th>
					<td><input type="text" id="mnem_campaign_subject" name="mnem_campaign_subject" value="<?php echo $subj_value; ?>" class="regular-text" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="mnem_campaign_body"><?php esc_html_e( 'Email Body (HTML)', 'mnem' ); ?></label></th>
					<td><textarea id="mnem_campaign_body" name="mnem_campaign_body" rows="8" class="large-text"><?php echo $body_value; ?></textarea></td>
				</tr>
			</table>
			<?php submit_button( $edit_campaign ? __( 'Update Campaign', 'mnem' ) : __( 'Create Campaign', 'mnem' ) ); ?>
		</form>
	</div>

	<?php /* ---- Campaign list ---- */ ?>
	<h2><?php esc_html_e( 'All Campaigns', 'mnem' ); ?></h2>

	<?php if ( ! empty( $campaigns ) ) : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Name', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Status', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Created', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'mnem' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $campaigns as $campaign ) :
				$transitions = MNEM_Campaigns::STATUS_TRANSITIONS[ $campaign['status'] ] ?? array();
			?>
			<tr>
				<td><?php echo absint( $campaign['id'] ); ?></td>
				<td><?php echo esc_html( $campaign['name'] ); ?></td>
				<td><?php echo esc_html( $campaign['subject'] ); ?></td>
				<td><strong><?php echo esc_html( $campaign['status'] ); ?></strong></td>
				<td><?php echo esc_html( $campaign['created_at'] ); ?></td>
				<td>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'mnem-campaigns', 'id' => $campaign['id'] ), network_admin_url( 'admin.php' ) ) ); ?>">
						<?php esc_html_e( 'Edit', 'mnem' ); ?>
					</a>
					<?php foreach ( $transitions as $new_status ) : ?>
					&nbsp;|&nbsp;
					<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_campaign_status' ) ); ?>" style="display:inline;">
						<?php wp_nonce_field( 'mnem_campaign_status', 'mnem_nonce' ); ?>
						<input type="hidden" name="mnem_campaign_id" value="<?php echo absint( $campaign['id'] ); ?>">
						<input type="hidden" name="mnem_new_status" value="<?php echo esc_attr( $new_status ); ?>">
						<button type="submit" class="button-link">
							<?php
							/* translators: %s: status name */
							printf( esc_html__( 'Mark as %s', 'mnem' ), esc_html( $new_status ) );
							?>
						</button>
					</form>
					<?php endforeach; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<p><?php esc_html_e( 'No campaigns yet. Create one above.', 'mnem' ); ?></p>
	<?php endif; ?>
</div>
