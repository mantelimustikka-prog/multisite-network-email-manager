<?php

defined('ABSPATH') || exit;

$tracking_enabled = \MNEM\EmailTracking::is_enabled();
$retention_days = \MNEM\EmailTracking::get_retention_days();
$storage_usage = \MNEM\EmailTracking::get_storage_usage();
?>
<h2><?php esc_html_e('Email Preview & Tracking Retention', 'multisite-network-email-manager'); ?></h2>
<form method="post" action="">
    <?php wp_nonce_field('mnem_email_tracking_settings'); ?>
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
                    <input type="number" min="0" class="small-text" id="mnem-email-preview-retention-days" name="email_preview_retention_days" value="<?php echo esc_attr((string) $retention_days); ?>" />
                    <p class="description"><?php esc_html_e('Use 0 to keep previews until they are manually removed.', 'multisite-network-email-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Current Storage Usage', 'multisite-network-email-manager'); ?></th>
                <td>
                    <strong><?php echo esc_html($storage_usage['formatted']); ?></strong>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %d is the number of tracked emails */
                            esc_html__('%d tracked email previews are currently stored.', 'multisite-network-email-manager'),
                            (int) $storage_usage['emails']
                        );
                        ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(esc_html__('Save Email Tracking Settings', 'multisite-network-email-manager')); ?>
</form>
