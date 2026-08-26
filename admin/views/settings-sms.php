<?php
/**
 * SMS Settings tab.
 *
 * Variables available: $sms_settings (array from SmsSettings::get_all()),
 *                      $sms_providers (array from SmsProviderManager::get_available_providers())
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
                <label for="mnem_sms_validation_country_code"><?php esc_html_e('Validation Country Code', 'multisite-network-email-manager'); ?></label>
            </th>
            <td>
                <input type="text" id="mnem_sms_validation_country_code" name="validation_country_code" value="<?php echo esc_attr($sms_validation_country_code); ?>" class="small-text" maxlength="2" />
                <p class="description"><?php esc_html_e('Two-letter country code used for formatting local numbers. Default: US.', 'multisite-network-email-manager'); ?></p>
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
