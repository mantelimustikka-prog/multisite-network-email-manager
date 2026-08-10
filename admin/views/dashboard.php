<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Multisite Network Email Manager', 'multisite-network-email-manager' ); ?></h1>
    <p><?php esc_html_e( 'Initial scaffold loaded successfully. Use the network menu to configure SMTP delivery, queue processing, campaigns, suppressions, and advanced user management rules.', 'multisite-network-email-manager' ); ?></p>
    <ul>
        <li><?php echo esc_html( sprintf( 'SMTP enabled: %s', ! empty( $settings['smtp_enabled'] ) ? 'yes' : 'no' ) ); ?></li>
        <li><?php echo esc_html( sprintf( 'Queue batch size: %d', (int) $settings['queue_batch_size'] ) ); ?></li>
        <li><?php echo esc_html( sprintf( 'Delete actions enabled: %s', ! empty( $settings['allow_user_deletion'] ) ? 'yes' : 'no' ) ); ?></li>
    </ul>
</div>
