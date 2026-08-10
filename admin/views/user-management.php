<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Advanced User Management', 'multisite-network-email-manager' ); ?></h1>
    <p><?php esc_html_e( 'Rule storage, event recording, and action execution scaffolds are available. Destructive delete actions are disabled by default and should only be enabled intentionally by a network administrator.', 'multisite-network-email-manager' ); ?></p>
    <p><?php echo esc_html( sprintf( 'Delete actions enabled: %s', ! empty( $settings['allow_user_deletion'] ) ? 'yes' : 'no' ) ); ?></p>
</div>
