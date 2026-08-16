<?php
/**
 * General Settings tab.
 */

defined('ABSPATH') || exit;

$retention_days = \MNEM\SmtpSettings::get_queue_retention_days();
?>
<form method="post" action="<?php echo esc_url(network_admin_url('admin.php')); ?>">
    <?php wp_nonce_field('mnem_general_settings'); ?>
    <input type="hidden" name="mnem_action" value="save_general_settings" />

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="mnem_queue_retention_days">
                    <?php esc_html_e('Queue Retention Period (days)', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <input
                    type="number"
                    id="mnem_queue_retention_days"
                    name="mnem_queue_retention_days"
                    value="<?php echo esc_attr((string) $retention_days); ?>"
                    min="1"
                    max="3650"
                    step="1"
                    class="small-text"
                />
                <p class="description">
                    <?php esc_html_e('Email status log records older than this many days will be automatically deleted by the daily cleanup cron job. Applies only to records with terminal statuses (sent, delivered, opened, clicked, bounce, failed, etc.). Minimum 1, maximum 3650.', 'multisite-network-email-manager'); ?>
                </p>
            </td>
        </tr>
    </table>

    <?php submit_button(esc_html__('Save General Settings', 'multisite-network-email-manager')); ?>
</form>
