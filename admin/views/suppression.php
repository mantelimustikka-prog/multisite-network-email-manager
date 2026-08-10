<?php
/**
 * Admin view: Suppression List.
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

	<?php if ( ! empty( $suppression_list ) ) : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Email', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Reason', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Suppressed On', 'mnem' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $suppression_list as $entry ) : ?>
			<tr>
				<td><?php echo esc_html( $entry['email'] ); ?></td>
				<td><?php echo esc_html( $entry['reason'] ); ?></td>
				<td><?php echo esc_html( $entry['created_at'] ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<p><?php esc_html_e( 'No suppressed emails.', 'mnem' ); ?></p>
	<?php endif; ?>
</div>
