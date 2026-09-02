<?php
/**
 * SMS Settings tab.
 *
 * Variables available: $sms_settings (array from SmsSettings::get_all()),
 *                      $sms_providers (array from SmsProviderManager::get_available_providers()),
 *                      $sms_integrity_stats (array from SmsSubscriberLists::get_data_integrity_overview()),
 *                      $sms_integrity_result (array from NetworkAdmin::get_and_clear_sms_integrity_result())
 */

defined('ABSPATH') || exit;

$sms_provider          = isset($sms_settings['provider'])          ? $sms_settings['provider']          : '';
$sms_enabled           = isset($sms_settings['enabled'])           ? (bool) $sms_settings['enabled']     : false;
$sms_max_per_day       = isset($sms_settings['max_per_day'])       ? (int)  $sms_settings['max_per_day'] : 1000;
$sms_no_hours          = isset($sms_settings['no_sms_hours'])      ? $sms_settings['no_sms_hours']       : '';
$sms_delay             = isset($sms_settings['delay'])             ? (int)  $sms_settings['delay']       : 100;
$sms_fallback_provider = isset($sms_settings['fallback_provider']) ? $sms_settings['fallback_provider']  : '';
$sms_tracking_enabled  = isset($sms_settings['tracking_enabled'])  ? (bool) $sms_settings['tracking_enabled'] : false;
$sms_phone_validation_enabled = isset($sms_settings['phone_validation_enabled']) ? (bool) $sms_settings['phone_validation_enabled'] : true;
$sms_validation_country_code = isset($sms_settings['validation_country_code']) ? (string) $sms_settings['validation_country_code'] : 'US';
$sms_allow_duplicate_numbers = isset($sms_settings['allow_duplicate_numbers']) ? (bool) $sms_settings['allow_duplicate_numbers'] : false;
$sms_auto_block_failed_attempts = isset($sms_settings['auto_block_failed_attempts']) ? (int) $sms_settings['auto_block_failed_attempts'] : 0;
$sms_notify_invalid_numbers = isset($sms_settings['notify_invalid_numbers']) ? (bool) $sms_settings['notify_invalid_numbers'] : false;
?>
<form method="post" action="">
    <?php wp_nonce_field('mnem_sms_settings'); ?>
    <input type="hidden" name="mnem_action" value="save_sms_settings" />

    <h3><?php esc_html_e('SMS Provider', 'multisite-network-email-manager'); ?></h3>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">
                <label for="mnem_sms_provider">
                    <?php esc_html_e('SMS Provider', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <select name="sms_provider" id="mnem_sms_provider">
                    <option value=""><?php esc_html_e('-- Select Provider --', 'multisite-network-email-manager'); ?></option>
                    <?php foreach ($sms_providers as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>"<?php selected($sms_provider, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Provider Credentials', 'multisite-network-email-manager'); ?></th>
            <td>
                <?php foreach ($sms_providers as $key => $label) :
                    $schema        = \MNEM\SmsProviderManager::get_provider_schema($key);
                    $saved_config  = \MNEM\SmsSettings::get_provider_config($key);
                    if (empty($schema)) {
                        continue;
                    }
                ?>
                <div class="mnem-sms-provider-config" id="mnem_sms_config_<?php echo esc_attr($key); ?>"
                     style="display:<?php echo $sms_provider === $key ? 'block' : 'none'; ?>;">
                    <table class="form-table" role="presentation">
                        <?php foreach ($schema as $field) :
                            $field_key   = isset($field['key'])   ? $field['key']   : '';
                            $field_label = isset($field['label']) ? $field['label'] : $field_key;
                            $field_type  = isset($field['type'])  ? $field['type']  : 'text';
                            $field_value = isset($saved_config[$field_key]) ? $saved_config[$field_key] : '';
                            $input_type  = $field_type === 'password' ? 'password' : 'text';
                            $display_val = $field_type === 'password' && $field_value !== '' ? '' : $field_value;
                            $placeholder = $field_type === 'password' && $field_value !== ''
                                ? esc_attr__('(saved — leave blank to keep)', 'multisite-network-email-manager')
                                : '';
                        ?>
                        <tr>
                            <th scope="row">
                                <label for="mnem_sms_config_<?php echo esc_attr($key); ?>_<?php echo esc_attr($field_key); ?>">
                                    <?php echo esc_html($field_label); ?>
                                </label>
                            </th>
                            <td>
                                <input
                                    type="<?php echo esc_attr($input_type); ?>"
                                    id="mnem_sms_config_<?php echo esc_attr($key); ?>_<?php echo esc_attr($field_key); ?>"
                                    name="sms_config[<?php echo esc_attr($key); ?>][<?php echo esc_attr($field_key); ?>]"
                                    value="<?php echo esc_attr($display_val); ?>"
                                    placeholder="<?php echo esc_attr($placeholder); ?>"
                                    class="regular-text"
                                    autocomplete="<?php echo esc_attr($field_type === 'password' ? 'new-password' : 'off'); ?>"
                                    <?php if (!empty($field['maxlength'])) : ?>maxlength="<?php echo esc_attr((int) $field['maxlength']); ?>"<?php endif; ?>
                                />
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <?php endforeach; ?>
                <button type="button" id="mnem_test_sms_connection" class="button">
                    <?php esc_html_e('Test Connection', 'multisite-network-email-manager'); ?>
                </button>
                <span id="mnem_sms_test_result" style="margin-left:10px;"></span>
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e('SMS Sending Settings', 'multisite-network-email-manager'); ?></h3>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Enable SMS Sending', 'multisite-network-email-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="sms_enabled" value="1"<?php checked($sms_enabled); ?> />
                    <?php esc_html_e('Enable SMS Sending', 'multisite-network-email-manager'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_max_sms_per_day">
                    <?php esc_html_e('Max SMS per Day', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <input
                    type="number"
                    id="mnem_max_sms_per_day"
                    name="max_sms_per_day"
                    value="<?php echo esc_attr((string) $sms_max_per_day); ?>"
                    min="1"
                    class="small-text"
                />
                <p class="description"><?php esc_html_e('Maximum number of SMS messages to send per day. Default: 1000.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_no_sms_hours">
                    <?php esc_html_e('No SMS Hours', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <input
                    type="text"
                    id="mnem_no_sms_hours"
                    name="no_sms_hours"
                    value="<?php echo esc_attr($sms_no_hours); ?>"
                    placeholder="21:00:00-07:00:00"
                    class="regular-text"
                />
                <p class="description">
                    <?php esc_html_e('Format: HH:MM:SS-HH:MM:SS. Example: 21:00:00-07:00:00 means do not send between 9 PM and 7 AM. Leave blank to send at any time.', 'multisite-network-email-manager'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_sms_delay">
                    <?php esc_html_e('SMS Sending Delay (milliseconds)', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <input
                    type="number"
                    id="mnem_sms_delay"
                    name="sms_delay"
                    value="<?php echo esc_attr((string) $sms_delay); ?>"
                    min="0"
                    class="small-text"
                />
                <p class="description"><?php esc_html_e('Delay between each SMS send in milliseconds. Default: 100.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
    </table>

    <h3><?php esc_html_e('Advanced Options', 'multisite-network-email-manager'); ?></h3>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Enable SMS Tracking', 'multisite-network-email-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="sms_tracking_enabled" value="1"<?php checked($sms_tracking_enabled); ?> />
                    <?php esc_html_e('Enable SMS Tracking (if provider supports)', 'multisite-network-email-manager'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Phone Number Validation', 'multisite-network-email-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="phone_validation_enabled" value="1"<?php checked($sms_phone_validation_enabled); ?> />
                    <?php esc_html_e('Validate phone numbers before adding SMS subscribers', 'multisite-network-email-manager'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_sms_validation_country_code"><?php esc_html_e('Fallback Country Code', 'multisite-network-email-manager'); ?></label>
            </th>
            <td>
                <input type="text" id="mnem_sms_validation_country_code" name="validation_country_code" value="<?php echo esc_attr($sms_validation_country_code); ?>" class="small-text" maxlength="2" />
                <p class="description"><?php esc_html_e('Two-letter country code, used for verifying phone number if other verifications fail.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Allow Duplicate Numbers', 'multisite-network-email-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="allow_duplicate_numbers" value="1"<?php checked($sms_allow_duplicate_numbers); ?> />
                    <?php esc_html_e('Allow the same phone number to be subscribed more than once in the same list', 'multisite-network-email-manager'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_sms_auto_block_failed_attempts"><?php esc_html_e('Auto-block Invalid Attempts', 'multisite-network-email-manager'); ?></label>
            </th>
            <td>
                <input type="number" id="mnem_sms_auto_block_failed_attempts" name="auto_block_failed_attempts" value="<?php echo esc_attr((string) $sms_auto_block_failed_attempts); ?>" min="0" class="small-text" />
                <p class="description"><?php esc_html_e('Automatically block a phone number after this many invalid attempts. Use 0 to disable.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Notify Admin of Invalid Numbers', 'multisite-network-email-manager'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="notify_invalid_numbers" value="1"<?php checked($sms_notify_invalid_numbers); ?> />
                    <?php esc_html_e('Keep invalid phone number records visible for admin review', 'multisite-network-email-manager'); ?>
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="mnem_sms_fallback_provider">
                    <?php esc_html_e('Fallback Provider', 'multisite-network-email-manager'); ?>
                </label>
            </th>
            <td>
                <select name="sms_fallback_provider" id="mnem_sms_fallback_provider">
                    <option value=""><?php esc_html_e('-- None --', 'multisite-network-email-manager'); ?></option>
                    <?php foreach ($sms_providers as $key => $label) :
                        if ($key === $sms_provider) {
                            continue;
                        }
                    ?>
                        <option value="<?php echo esc_attr($key); ?>"<?php selected($sms_fallback_provider, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Optional fallback provider if the primary provider fails.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
    </table>

    <?php submit_button(esc_html__('Save SMS Settings', 'multisite-network-email-manager')); ?>
</form>

<h3><?php esc_html_e('Provider Status Sync', 'multisite-network-email-manager'); ?></h3>
<form method="post" action="">
    <?php wp_nonce_field('mnem_sms_provider_status_sync'); ?>
    <input type="hidden" name="mnem_action" value="sync_sms_provider_statuses" />
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="mnem_sync_provider"><?php esc_html_e('Provider', 'multisite-network-email-manager'); ?></label></th>
            <td>
                <select name="sync_provider" id="mnem_sync_provider" required>
                    <?php foreach ($sms_providers as $key => $label) : ?>
                        <option value="<?php echo esc_attr($key); ?>"<?php selected($sms_provider, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="mnem_sync_date_range"><?php esc_html_e('Date Range', 'multisite-network-email-manager'); ?></label></th>
            <td>
                <select name="sync_date_range" id="mnem_sync_date_range">
                    <option value="7"><?php esc_html_e('Last 7 days', 'multisite-network-email-manager'); ?></option>
                    <option value="30"><?php esc_html_e('Last 30 days', 'multisite-network-email-manager'); ?></option>
                    <option value="custom"><?php esc_html_e('Custom range', 'multisite-network-email-manager'); ?></option>
                </select>
                <input type="date" name="sync_date_from" aria-label="<?php echo esc_attr__('Custom start date', 'multisite-network-email-manager'); ?>" />
                <input type="date" name="sync_date_to" aria-label="<?php echo esc_attr__('Custom end date', 'multisite-network-email-manager'); ?>" />
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="mnem_sync_limit"><?php esc_html_e('Limit', 'multisite-network-email-manager'); ?></label></th>
            <td>
                <select name="sync_limit" id="mnem_sync_limit">
                    <?php foreach (array(100, 500, 1000) as $limit) : ?>
                        <option value="<?php echo esc_attr((string) $limit); ?>"><?php echo esc_html((string) $limit); ?></option>
                    <?php endforeach; ?>
                </select>
                <label style="margin-left:12px;">
                    <input type="checkbox" name="sync_dry_run" value="1" />
                    <?php esc_html_e('Dry run (preview changes only)', 'multisite-network-email-manager'); ?>
                </label>
            </td>
        </tr>
    </table>
    <?php submit_button(__('Sync SMS Status from Provider', 'multisite-network-email-manager'), 'secondary'); ?>
</form>

<?php if (!empty($sms_status_sync_result)) : ?>
    <div class="notice notice-info inline">
        <p>
            <?php
            printf(
                esc_html__('Checked: %1$d; Updated: %2$d; Delivered: %3$d; Failed: %4$d; Bounced: %5$d; Rejected: %6$d.', 'multisite-network-email-manager'),
                (int) $sms_status_sync_result['checked'],
                (int) $sms_status_sync_result['updated'],
                (int) $sms_status_sync_result['delivered'],
                (int) $sms_status_sync_result['failed'],
                (int) $sms_status_sync_result['bounced'],
                (int) $sms_status_sync_result['rejected']
            );
            ?>
        </p>
        <?php if (!empty($sms_status_sync_result['errors'])) : ?>
            <ul><?php foreach ($sms_status_sync_result['errors'] as $error) : ?><li><?php echo esc_html($error); ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>
<?php endif; ?>

<h3><?php esc_html_e('SMS Data Integrity', 'multisite-network-email-manager'); ?></h3>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><?php esc_html_e('Current Stats', 'multisite-network-email-manager'); ?></th>
        <td>
            <ul style="margin:0;">
                <li><?php echo esc_html(sprintf(__('Total SMS lists: %d', 'multisite-network-email-manager'), isset($sms_integrity_stats['total_lists']) ? (int) $sms_integrity_stats['total_lists'] : 0)); ?></li>
                <li><?php echo esc_html(sprintf(__('Total SMS subscribers: %d', 'multisite-network-email-manager'), isset($sms_integrity_stats['total_subscribers']) ? (int) $sms_integrity_stats['total_subscribers'] : 0)); ?></li>
                <li><?php echo esc_html(sprintf(__('Total invalid phone numbers: %d', 'multisite-network-email-manager'), isset($sms_integrity_stats['total_invalid_phone_numbers']) ? (int) $sms_integrity_stats['total_invalid_phone_numbers'] : 0)); ?></li>
                <li><?php echo esc_html(sprintf(__('Orphaned records: %d', 'multisite-network-email-manager'), isset($sms_integrity_stats['orphaned_records']) ? (int) $sms_integrity_stats['orphaned_records'] : 0)); ?></li>
            </ul>
        </td>
    </tr>
    <tr>
        <th scope="row"><?php esc_html_e('Integrity Tools', 'multisite-network-email-manager'); ?></th>
        <td>
            <form method="post" action="" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                <?php wp_nonce_field('mnem_sms_data_integrity', 'mnem_sms_integrity_nonce'); ?>
                <button type="submit" class="button" name="mnem_sms_integrity_action" value="check_sms_data_integrity"><?php esc_html_e('Check Integrity', 'multisite-network-email-manager'); ?></button>
                <button type="submit" class="button" name="mnem_sms_integrity_action" value="cleanup_sms_orphans"><?php esc_html_e('Cleanup Orphans', 'multisite-network-email-manager'); ?></button>
                <button type="submit" class="button" name="mnem_sms_integrity_action" value="export_sms_cleanup_report"><?php esc_html_e('Export Cleanup Report', 'multisite-network-email-manager'); ?></button>
            </form>
            <p class="description"><?php esc_html_e('Check for orphaned SMS subscriber, invalid-phone, queue, and SMS log records. Queue cleanup is skipped until SMS queue rows store list_id.', 'multisite-network-email-manager'); ?></p>
        </td>
    </tr>
    <?php if (!empty($sms_integrity_result)) : ?>
        <tr>
            <th scope="row"><?php esc_html_e('Last Result', 'multisite-network-email-manager'); ?></th>
            <td>
                <?php if (isset($sms_integrity_result['type']) && $sms_integrity_result['type'] === 'export') : ?>
                    <textarea class="large-text code" rows="10" readonly><?php echo esc_textarea(isset($sms_integrity_result['report']) ? (string) $sms_integrity_result['report'] : ''); ?></textarea>
                <?php elseif (isset($sms_integrity_result['result']) && is_array($sms_integrity_result['result'])) : ?>
                    <?php $sms_integrity_json = wp_json_encode($sms_integrity_result['result'], JSON_PRETTY_PRINT); ?>
                    <pre style="max-height:260px;overflow:auto;"><?php echo esc_html($sms_integrity_json !== false ? $sms_integrity_json : __('Unable to encode integrity result.', 'multisite-network-email-manager')); ?></pre>
                <?php endif; ?>
            </td>
        </tr>
    <?php endif; ?>
</table>

<script type="text/javascript">
(function($) {
    function toggleProviderConfig() {
        var selected = $('#mnem_sms_provider').val();
        $('.mnem-sms-provider-config').hide();
        if (selected) {
            $('#mnem_sms_config_' + selected).show();
        }
    }

    $('#mnem_sms_provider').on('change', function() {
        toggleProviderConfig();
        // Remove the primary from fallback options
        var selected = $(this).val();
        $('#mnem_sms_fallback_provider option').each(function() {
            $(this).toggle($(this).val() === '' || $(this).val() !== selected);
        });
    });

    toggleProviderConfig();
})(jQuery);
</script>
