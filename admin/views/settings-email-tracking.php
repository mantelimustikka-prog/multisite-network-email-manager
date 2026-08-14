<?php

defined('ABSPATH') || exit;

$tracking_enabled = \MNEM\EmailTracking::is_enabled();
$retention_days = \MNEM\EmailTracking::get_retention_days();
?>
<h2><?php esc_html_e('Email Preview & Tracking Retention', 'multisite-network-email-manager'); ?></h2>
<form method="post" action="">
    <?php wp_nonce_field('mnem_smtp_settings'); ?>
    <input type="hidden" name="mnem_action" value="save_email_tracking_settings" />

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><label for="mnem-keep-email-previews"><?php esc_html_e('Keep Email Preview Records', 'multisite-network-email-manager'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="mnem-keep-email-previews" name="keep_email_previews" value="1" <?php checked($tracking_enabled); ?> />
                        <?php esc_html_e('Store sent email previews and tracking details for audit history', 'multisite-network-email-manager'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mnem-email-preview-retention-days"><?php esc_html_e('Auto-delete After (days)', 'multisite-network-email-manager'); ?></label></th>
                <td>
                    <input type="number" min="1" class="small-text" id="mnem-email-preview-retention-days" name="email_preview_retention_days" value="<?php echo esc_attr((string) $retention_days); ?>" />
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(esc_html__('Save Email Tracking Settings', 'multisite-network-email-manager')); ?>
</form>
