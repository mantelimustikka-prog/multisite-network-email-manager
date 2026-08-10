<?php
/**
 * Admin view: Send Queue — real rows with retry bulk action.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table = $wpdb->base_prefix . 'mnem_queue';

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status_filter = isset( $_GET['queue_status'] ) ? sanitize_key( $_GET['queue_status'] ) : '';

$allowed_statuses = array( '', 'pending', 'processing', 'sent', 'failed' );
if ( ! in_array( $status_filter, $allowed_statuses, true ) ) {
	$status_filter = '';
}

$where  = '';
$params = array();
if ( $status_filter ) {
	$where    = ' WHERE status = %s';
	$params[] = $status_filter;
}

if ( $params ) {
	$jobs = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table}{$where} ORDER BY scheduled_at DESC LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$params
		),
		ARRAY_A
	);
} else {
	$jobs = $wpdb->get_results(
		"SELECT * FROM {$table} ORDER BY scheduled_at DESC LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		ARRAY_A
	);
}
$jobs = $jobs ? $jobs : array();

$failed_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'failed'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$status_labels = array(
	''           => __( 'All', 'mnem' ),
	'pending'    => __( 'Pending', 'mnem' ),
	'processing' => __( 'Processing', 'mnem' ),
	'sent'       => __( 'Sent', 'mnem' ),
	'failed'     => __( 'Failed', 'mnem' ),
);
$status_colors = array(
	'pending'    => '#888',
	'processing' => '#0073aa',
	'sent'       => 'green',
	'failed'     => '#d63638',
);
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Send Queue', 'mnem' ); ?></h1>

	<?php /* Status filter tabs */ ?>
	<ul class="subsubsub">
		<?php foreach ( $status_labels as $s => $label ) :
			$url = add_query_arg( array( 'page' => 'mnem-queue', 'queue_status' => $s ), network_admin_url( 'admin.php' ) );
		?>
		<li>
			<a href="<?php echo esc_url( $url ); ?>" <?php echo $status_filter === $s ? 'class="current"' : ''; ?>>
				<?php echo esc_html( $label ); ?>
			</a>
			<?php echo $s !== array_key_last( $status_labels ) ? ' |' : ''; ?>
		</li>
		<?php endforeach; ?>
	</ul>

	<?php /* Retry failed bulk action */ ?>
	<?php if ( $failed_count > 0 ) : ?>
	<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_retry_failed_queue' ) ); ?>" style="margin:12px 0;">
		<?php wp_nonce_field( 'mnem_retry_queue', 'mnem_nonce' ); ?>
		<button type="submit" class="button button-secondary">
			<?php
			/* translators: %d: number of failed jobs */
			printf( esc_html__( 'Retry All Failed (%d)', 'mnem' ), $failed_count );
			?>
		</button>
	</form>
	<?php endif; ?>

	<?php if ( ! empty( $jobs ) ) : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Recipient', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Status', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Attempts', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Scheduled', 'mnem' ); ?></th>
				<th><?php esc_html_e( 'Sent', 'mnem' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $jobs as $job ) :
				$color = isset( $status_colors[ $job['status'] ] ) ? $status_colors[ $job['status'] ] : '#000';
			?>
			<tr>
				<td><?php echo absint( $job['id'] ); ?></td>
				<td><?php echo esc_html( $job['recipient'] ); ?></td>
				<td><?php echo esc_html( wp_trim_words( $job['subject'], 8 ) ); ?></td>
				<td style="color:<?php echo esc_attr( $color ); ?>;font-weight:bold;"><?php echo esc_html( $job['status'] ); ?></td>
				<td><?php echo absint( $job['attempts'] ); ?></td>
				<td><?php echo esc_html( $job['scheduled_at'] ); ?></td>
				<td><?php echo $job['sent_at'] ? esc_html( $job['sent_at'] ) : '—'; ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php else : ?>
	<p><?php esc_html_e( 'No queue items found.', 'mnem' ); ?></p>
	<?php endif; ?>
</div>
