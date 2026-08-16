<?php
/**
 * Settings tab — Status Updates.
 *
 * Variables available: $status_update_interval
 */

defined('ABSPATH') || exit;

$status_update_interval = isset($status_update_interval) ? (int) $status_update_interval : \MNEM\SmtpSettings::get_status_update_interval();
?>

<p class="description">
    <?php esc_html_e('Configure how frequently the plugin checks email statuses with your SMTP provider.', 'multisite-network-email-manager'); ?>
</p>

<form method="post" action="">
    <?php wp_nonce_field('mnem_status_interval_settings'); ?>
    <input type="hidden" name="mnem_action" value="save_status_interval_settings" />

    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="mnem-status-update-interval"><?php esc_html_e('Status Update Interval:', 'multisite-network-email-manager'); ?></label>
                </th>
                <td>
                    <select name="mnem_status_update_interval" id="mnem-status-update-interval" class="regular-text">
                        <option value="5" <?php selected($status_update_interval, 5); ?>><?php esc_html_e('5 minutes', 'multisite-network-email-manager'); ?></option>
                        <option value="10" <?php selected($status_update_interval, 10); ?>><?php esc_html_e('10 minutes', 'multisite-network-email-manager'); ?></option>
                        <option value="15" <?php selected($status_update_interval, 15); ?>><?php esc_html_e('15 minutes', 'multisite-network-email-manager'); ?></option>
                        <option value="20" <?php selected($status_update_interval, 20); ?>><?php esc_html_e('20 minutes', 'multisite-network-email-manager'); ?></option>
                        <option value="30" <?php selected($status_update_interval, 30); ?>><?php esc_html_e('30 minutes (default)', 'multisite-network-email-manager'); ?></option>
                        <option value="60" <?php selected($status_update_interval, 60); ?>><?php esc_html_e('60 minutes', 'multisite-network-email-manager'); ?></option>
                    </select>
                    <p class="description">
                        <?php esc_html_e('How often the plugin will poll your SMTP provider for email status updates (delivered, opened, clicked, bounced, etc.). Shorter intervals provide more real-time updates but use more API calls. Changing this setting immediately reschedules the background cron job.', 'multisite-network-email-manager'); ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(esc_html__('Save Status Update Interval', 'multisite-network-email-manager')); ?>
</form>
