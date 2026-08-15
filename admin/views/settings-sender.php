<?php
/**
 * Settings tab — Sender Settings.
 *
 * Variables available: (none required, reads from site options)
 */

defined('ABSPATH') || exit;

$sender_name  = (string) get_site_option('mnem_sender_name', '');
$sender_email = (string) get_site_option('mnem_sender_email', '');
$force_sender = \MNEM\SmtpSettings::is_force_sender_enabled();
?>

<p class="description">
    <?php esc_html_e('These settings control the sender name and email address used for all outbound emails from this plugin.', 'multisite-network-email-manager'); ?>
</p>

<form method="post" action="">
    <?php wp_nonce_field('mnem_sender_settings'); ?>
    <input type="hidden" name="mnem_action" value="save_sender_settings" />

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="mnem-sender-name"><?php esc_html_e('Sender Name:', 'multisite-network-email-manager'); ?></label>
                </th>
                <td>
                    <input
                        name="sender_name"
                        id="mnem-sender-name"
                        type="text"
                        class="regular-text"
                        value="<?php echo esc_attr($sender_name); ?>"
                    />
                    <p class="description">
                        <?php esc_html_e('Displayed as the "From" name in outbound emails. Defaults to the site name if left blank.', 'multisite-network-email-manager'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mnem-sender-email"><?php esc_html_e('Sender Email:', 'multisite-network-email-manager'); ?></label>
                </th>
                <td>
                    <input
                        name="sender_email"
                        id="mnem-sender-email"
                        type="email"
                        class="regular-text"
                        value="<?php echo esc_attr($sender_email); ?>"
                    />
                    <p class="description">
                        <?php esc_html_e('Displayed as the "From" address in outbound emails. Defaults to the WordPress admin email if left blank.', 'multisite-network-email-manager'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="mnem-force-sender"><?php esc_html_e('Force to use these sender settings', 'multisite-network-email-manager'); ?></label>
                </th>
                <td>
                    <label for="mnem-force-sender">
                        <input
                            name="force_sender_settings"
                            id="mnem-force-sender"
                            type="checkbox"
                            value="1"
                            <?php checked($force_sender); ?>
                        />
                        <?php esc_html_e('Use these sender settings for ALL outgoing emails (test, campaigns, transactional).', 'multisite-network-email-manager'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('When enabled, all emails use Sender Name and Sender Email configured above, regardless of custom From headers.', 'multisite-network-email-manager'); ?>
                    </p>
                    <?php if ($force_sender && $sender_email !== '') : ?>
                        <p style="color: #0a6b0a; font-weight: bold;">
                            <?php esc_html_e('Enforced sender:', 'multisite-network-email-manager'); ?>
                            <?php echo esc_html($sender_name !== '' ? $sender_name . ' <' . $sender_email . '>' : $sender_email); ?>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(esc_html__('Save Sender Settings', 'multisite-network-email-manager')); ?>
</form>
