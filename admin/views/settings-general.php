<?php
/**
 * General Settings tab.
 *
 * Variables available: $queue_retention_days
 */

defined('ABSPATH') || exit;
?>
<form method="post" action="">
    <?php wp_nonce_field('mnem_general_settings'); ?>
    <input type="hidden" name="mnem_action" value="save_general_settings" />

    <table class="form-table" role="presentation">
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
                    value="<?php echo esc_attr((string) $queue_retention_days); ?>"
                    min="1"
                    max="3650"
                    class="small-text"
                />
                <p class="description">
                    <?php esc_html_e('Email status log records older than this number of days (with a terminal status) will be automatically deleted once per day. Minimum: 1, Maximum: 3650.', 'multisite-network-email-manager'); ?>
                </p>
            </td>
        </tr>
    </table>

    <?php submit_button(esc_html__('Save General Settings', 'multisite-network-email-manager')); ?>
</form>
