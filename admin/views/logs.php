<?php
/**
 * Admin view: Logs.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table = $wpdb->base_prefix . 'mnem_logs';
$logs  = $wpdb->get_results(
	$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", 100 ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	ARRAY_A
);
$logs = $logs ? $logs : array();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Logs', 'mnem' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Showing the last 100 log entries.', 'mnem' ); ?></p>

	<?php if ( ! empty( $logs ) ) : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Module', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Level', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Message', 'mnem' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $logs as $log ) :
				$level_colors = array(
					'debug'   => '#aaa',
					'info'    => '#0073aa',
					'warning' => '#d98500',
					'error'   => '#d63638',
				);
				$color = isset( $level_colors[ $log['level'] ] ) ? $level_colors[ $log['level'] ] : '#000';
			?>
			<tr>
				<td><?php echo esc_html( $log['created_at'] ); ?></td>
				<td><?php echo esc_html( $log['module'] ); ?></td>
				<td style="color:<?php echo esc_attr( $color ); ?>;font-weight:bold;"><?php echo esc_html( strtoupper( $log['level'] ) ); ?></td>
				<td><?php echo esc_html( $log['message'] ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<p><?php esc_html_e( 'No log entries found.', 'mnem' ); ?></p>
	<?php endif; ?>
</div>
