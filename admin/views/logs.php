<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Logs', 'multisite-network-email-manager' ); ?></h1>
    <p><?php esc_html_e( 'Recent log entries written to the custom network table appear below.', 'multisite-network-email-manager' ); ?></p>
    <?php if ( empty( $logs ) ) : ?>
        <p><?php esc_html_e( 'No log entries yet.', 'multisite-network-email-manager' ); ?></p>
    <?php else : ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Date', 'multisite-network-email-manager' ); ?></th>
                    <th><?php esc_html_e( 'Level', 'multisite-network-email-manager' ); ?></th>
                    <th><?php esc_html_e( 'Message', 'multisite-network-email-manager' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log['created_at'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $log['level'] ?? '' ); ?></td>
                        <td><?php echo esc_html( $log['message'] ?? '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
