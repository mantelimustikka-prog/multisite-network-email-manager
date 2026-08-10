<?php
/**
 * Admin view: Campaigns placeholder.
 *
 * Variables available:
 *   $campaigns (array)
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$campaigns = isset( $campaigns ) ? $campaigns : array();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Campaigns', 'mnem' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Campaign management will be available in a future release.', 'mnem' ); ?></p>

	<?php if ( ! empty( $campaigns ) ) : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Name', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Status', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Created', 'mnem' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $campaigns as $campaign ) : ?>
			<tr>
				<td><?php echo absint( $campaign['id'] ); ?></td>
				<td><?php echo esc_html( $campaign['name'] ); ?></td>
				<td><?php echo esc_html( $campaign['subject'] ); ?></td>
				<td><?php echo esc_html( $campaign['status'] ); ?></td>
				<td><?php echo esc_html( $campaign['created_at'] ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<p><?php esc_html_e( 'No campaigns found.', 'mnem' ); ?></p>
	<?php endif; ?>
</div>
