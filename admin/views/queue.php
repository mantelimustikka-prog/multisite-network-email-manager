<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Email Queue', 'multisite-network-email-manager' ); ?></h1>
    <p><?php esc_html_e( 'Queue processing is scaffolded with a custom table and a scheduled cron hook. Future iterations can add resend controls, retry policies, and queue filtering here.', 'multisite-network-email-manager' ); ?></p>
    <p><?php echo esc_html( sprintf( 'Current batch size: %d', (int) $settings['queue_batch_size'] ) ); ?></p>
</div>
