<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Network Email Manager Settings', 'multisite-network-email-manager' ); ?></h1>
    <?php if ( isset( $_GET['updated'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'multisite-network-email-manager' ); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=mnem_save_settings' ) ); ?>">
        <?php wp_nonce_field( 'mnem_save_settings' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Enable SMTP routing', 'multisite-network-email-manager' ); ?></th>
                <td><label><input type="checkbox" name="smtp_enabled" value="1" <?php checked( ! empty( $settings['smtp_enabled'] ) ); ?>> <?php esc_html_e( 'Send mail through configured SMTP provider', 'multisite-network-email-manager' ); ?></label></td>
            </tr>
            <tr>
                <th scope="row"><label for="smtp_provider"><?php esc_html_e( 'SMTP provider slug', 'multisite-network-email-manager' ); ?></label></th>
                <td><input class="regular-text" type="text" id="smtp_provider" name="smtp_provider" value="<?php echo esc_attr( $settings['smtp_provider'] ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="smtp_host"><?php esc_html_e( 'SMTP host', 'multisite-network-email-manager' ); ?></label></th>
                <td><input class="regular-text" type="text" id="smtp_host" name="smtp_host" value="<?php echo esc_attr( $settings['smtp_host'] ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="smtp_port"><?php esc_html_e( 'SMTP port', 'multisite-network-email-manager' ); ?></label></th>
                <td><input class="small-text" type="number" id="smtp_port" name="smtp_port" value="<?php echo esc_attr( (string) $settings['smtp_port'] ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="smtp_encryption"><?php esc_html_e( 'Encryption', 'multisite-network-email-manager' ); ?></label></th>
                <td>
                    <select id="smtp_encryption" name="smtp_encryption">
                        <option value="none" <?php selected( $settings['smtp_encryption'], 'none' ); ?>><?php esc_html_e( 'None', 'multisite-network-email-manager' ); ?></option>
                        <option value="ssl" <?php selected( $settings['smtp_encryption'], 'ssl' ); ?>>SSL</option>
                        <option value="tls" <?php selected( $settings['smtp_encryption'], 'tls' ); ?>>TLS</option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="smtp_username"><?php esc_html_e( 'SMTP username', 'multisite-network-email-manager' ); ?></label></th>
                <td><input class="regular-text" type="text" id="smtp_username" name="smtp_username" value="<?php echo esc_attr( $settings['smtp_username'] ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="smtp_password"><?php esc_html_e( 'SMTP password', 'multisite-network-email-manager' ); ?></label></th>
                <td><input class="regular-text" type="password" id="smtp_password" name="smtp_password" value="<?php echo esc_attr( $settings['smtp_password'] ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="from_email"><?php esc_html_e( 'From email', 'multisite-network-email-manager' ); ?></label></th>
                <td><input class="regular-text" type="email" id="from_email" name="from_email" value="<?php echo esc_attr( $settings['from_email'] ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="from_name"><?php esc_html_e( 'From name', 'multisite-network-email-manager' ); ?></label></th>
                <td><input class="regular-text" type="text" id="from_name" name="from_name" value="<?php echo esc_attr( $settings['from_name'] ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="queue_batch_size"><?php esc_html_e( 'Queue batch size', 'multisite-network-email-manager' ); ?></label></th>
                <td><input class="small-text" type="number" id="queue_batch_size" name="queue_batch_size" value="<?php echo esc_attr( (string) $settings['queue_batch_size'] ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><label for="admin_notice_email"><?php esc_html_e( 'Admin notice email', 'multisite-network-email-manager' ); ?></label></th>
                <td><input class="regular-text" type="email" id="admin_notice_email" name="admin_notice_email" value="<?php echo esc_attr( $settings['admin_notice_email'] ); ?>"></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Allow user deletion', 'multisite-network-email-manager' ); ?></th>
                <td>
                    <label><input type="checkbox" name="allow_user_deletion" value="1" <?php checked( ! empty( $settings['allow_user_deletion'] ) ); ?>> <?php esc_html_e( 'Disabled by default for safety. Leave unchecked to notify only.', 'multisite-network-email-manager' ); ?></label>
                </td>
            </tr>
        </table>
        <?php submit_button( __( 'Save Settings', 'multisite-network-email-manager' ) ); ?>
    </form>
</div>
