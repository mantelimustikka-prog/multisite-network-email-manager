<?php
/**
 * Admin view: Send Queue placeholder.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Send Queue', 'mnem' ); ?></h1>
	<p class="description"><?php esc_html_e( 'The email send queue will be displayed here. Queue processing runs automatically via WP-Cron every 5 minutes.', 'mnem' ); ?></p>
	<p><?php esc_html_e( 'No items in queue.', 'mnem' ); ?></p>
</div>
