<?php
/**
 * General Settings tab.
 *
 * Variables available: $queue_retention_days, $campaign_rate_limit_per_minute, $campaign_rate_limit_per_hour, $campaign_rate_limit_per_day, $campaign_delay_between_sends
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
                    <?php esc_html_e('Email/SMS status log records older than this number of days (with a terminal status) will be automatically deleted once per day. Minimum: 1, Maximum: 3650.', 'multisite-network-email-manager'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_campaign_rate_limit_per_minute">
                    <?php esc_html_e('Campaign Rate Limit (per minute)', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <input type="number" id="mnem_campaign_rate_limit_per_minute" name="mnem_campaign_rate_limit_per_minute" value="<?php echo esc_attr((string) $campaign_rate_limit_per_minute); ?>" min="0" max="10000" class="small-text" />
                <p class="description"><?php esc_html_e('Maximum campaign emails sent per minute. Set to 0 for unlimited.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_campaign_rate_limit_per_hour">
                    <?php esc_html_e('Campaign Rate Limit (per hour)', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <input type="number" id="mnem_campaign_rate_limit_per_hour" name="mnem_campaign_rate_limit_per_hour" value="<?php echo esc_attr((string) $campaign_rate_limit_per_hour); ?>" min="0" max="100000" class="small-text" />
                <p class="description"><?php esc_html_e('Maximum campaign emails sent per hour. Set to 0 for unlimited.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_campaign_rate_limit_per_day">
                    <?php esc_html_e('Campaign Rate Limit (per day)', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <input type="number" id="mnem_campaign_rate_limit_per_day" name="mnem_campaign_rate_limit_per_day" value="<?php echo esc_attr((string) $campaign_rate_limit_per_day); ?>" min="0" max="1000000" class="small-text" />
                <p class="description"><?php esc_html_e('Maximum campaign emails sent per day. Set to 0 for unlimited.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_campaign_delay_between_sends">
                    <?php esc_html_e('Delay Between Campaign Sends (milliseconds)', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <input type="number" id="mnem_campaign_delay_between_sends" name="mnem_campaign_delay_between_sends" value="<?php echo esc_attr((string) $campaign_delay_between_sends); ?>" min="0" max="10000" class="small-text" />
                <p class="description"><?php esc_html_e('Delay between each campaign email in milliseconds. Set to 0 for no delay.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
    </table>

    <?php submit_button(esc_html__('Save General Settings', 'multisite-network-email-manager')); ?>
</form>
