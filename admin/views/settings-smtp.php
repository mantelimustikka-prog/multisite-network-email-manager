<?php
/**
 * Settings tab — SMTP Settings.
 *
 * Variables available: $settings, $cron_status, $notice, $notice_message, $notice_class
 */

defined('ABSPATH') || exit;

use MNEM\ProviderManager;

$providers       = ProviderManager::get_available_providers();
$provider_config = isset($settings['provider_config']) && is_array($settings['provider_config']) ? $settings['provider_config'] : array();

$api_providers = array('mailgun', 'sendgrid', 'brevo', 'postmark', 'smtp2go');

$provider_labels = array(
    'mailgun'  => 'Mailgun',
    'sendgrid' => 'SendGrid',
    'brevo'    => 'Brevo',
    'postmark' => 'Postmark',
    'smtp2go'  => 'SMTP2GO',
);

$provider_field_defs = array(
    'mailgun'  => array('api_key' => 'API Key', 'domain' => 'Domain'),
    'sendgrid' => array('api_key' => 'API Key'),
    'brevo'    => array('api_key' => 'API Key'),
    'postmark' => array('server_token' => 'Server Token'),
    'smtp2go'  => array('api_key' => 'API Key'),
);
?>

<form method="post" action="">
    <?php wp_nonce_field('mnem_smtp_settings'); ?>
    <input type="hidden" name="mnem_action" value="save_smtp_settings" />

    <h2><?php esc_html_e('Email Service Provider', 'multisite-network-email-manager'); ?></h2>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><label for="mnem-provider-type"><?php esc_html_e('Email Provider', 'multisite-network-email-manager'); ?></label></th>
                <td>
                    <select name="provider_type" id="mnem-provider-type">
                        <?php foreach ($providers as $ptype => $pmeta) : ?>
                            <option value="<?php echo esc_attr($ptype); ?>" <?php selected($settings['provider_type'], $ptype); ?>><?php echo esc_html($pmeta['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php
                        $current_provider = isset($settings['provider_type']) ? $settings['provider_type'] : 'smtp';
                        $meta = isset($providers[$current_provider]) ? $providers[$current_provider] : $providers['smtp'];
                        echo esc_html($meta['description']);
                        ?>
                    </p>
                </td>
            </tr>
        </tbody>
    </table>

    <div id="mnem-smtp-fields">
        <h2><?php esc_html_e('SMTP Configuration', 'multisite-network-email-manager'); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-host"><?php esc_html_e('Host', 'multisite-network-email-manager'); ?></label></th>
                    <td><input name="host" id="mnem-host" type="text" class="regular-text" value="<?php echo esc_attr($settings['host']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-port"><?php esc_html_e('Port', 'multisite-network-email-manager'); ?></label></th>
                    <td><input name="port" id="mnem-port" type="number" class="small-text" value="<?php echo esc_attr((string) $settings['port']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-encryption"><?php esc_html_e('Encryption', 'multisite-network-email-manager'); ?></label></th>
                    <td>
                        <select name="encryption" id="mnem-encryption">
                            <option value="tls" <?php selected($settings['encryption'], 'tls'); ?>>TLS</option>
                            <option value="ssl" <?php selected($settings['encryption'], 'ssl'); ?>>SSL</option>
                            <option value="none" <?php selected($settings['encryption'], 'none'); ?>>None</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-username"><?php esc_html_e('Username', 'multisite-network-email-manager'); ?></label></th>
                    <td><input name="username" id="mnem-username" type="text" class="regular-text" value="<?php echo esc_attr($settings['username']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-password"><?php esc_html_e('Password', 'multisite-network-email-manager'); ?></label></th>
                    <td>
                        <input name="password" id="mnem-password" type="password" class="regular-text" value="" autocomplete="new-password" />
                        <p class="description"><?php esc_html_e('Leave blank to keep the currently stored password.', 'multisite-network-email-manager'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <?php foreach ($api_providers as $ptype) :
        $pconfig = isset($provider_config[$ptype]) && is_array($provider_config[$ptype]) ? $provider_config[$ptype] : array();
    ?>
    <div id="mnem-<?php echo esc_attr($ptype); ?>-fields">
        <h2><?php echo esc_html($provider_labels[$ptype]); ?> <?php esc_html_e('Configuration', 'multisite-network-email-manager'); ?></h2>
        <table class="form-table" role="presentation">
            <tbody>
                <?php foreach ($provider_field_defs[$ptype] as $field_key => $field_label) : ?>
                <tr>
                    <th scope="row"><label for="mnem-<?php echo esc_attr($ptype . '-' . $field_key); ?>"><?php echo esc_html($field_label); ?></label></th>
                    <td>
                        <input
                            name="provider_config[<?php echo esc_attr($ptype); ?>][<?php echo esc_attr($field_key); ?>]"
                            id="mnem-<?php echo esc_attr($ptype . '-' . $field_key); ?>"
                            type="password"
                            class="regular-text"
                            value=""
                            autocomplete="new-password"
                        />
                        <?php if (!empty($pconfig[$field_key])) : ?>
                            <span class="description">&#10003; <?php esc_html_e('Saved. Leave blank to keep current value.', 'multisite-network-email-manager'); ?></span>
                        <?php else : ?>
                            <p class="description"><?php printf(esc_html__('Enter your %1$s %2$s.', 'multisite-network-email-manager'), esc_html($provider_labels[$ptype]), esc_html($field_label)); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>

    <h2><?php esc_html_e('Fallback Provider', 'multisite-network-email-manager'); ?></h2>
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><label for="mnem-fallback-enabled"><?php esc_html_e('Enable Fallback', 'multisite-network-email-manager'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="fallback_enabled" id="mnem-fallback-enabled" value="1" <?php checked(!empty($settings['fallback_enabled'])); ?> />
                        <?php esc_html_e('Retry with a secondary provider if the primary provider fails', 'multisite-network-email-manager'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="mnem-fallback-provider"><?php esc_html_e('Fallback Provider', 'multisite-network-email-manager'); ?></label></th>
                <td>
                    <select name="fallback_provider" id="mnem-fallback-provider">
                        <option value=""><?php esc_html_e('(None)', 'multisite-network-email-manager'); ?></option>
                        <?php foreach ($providers as $ptype => $pmeta) : ?>
                            <option value="<?php echo esc_attr($ptype); ?>" <?php selected($settings['fallback_provider'], $ptype); ?>><?php echo esc_html($pmeta['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Configure the selected fallback provider in its settings section above.', 'multisite-network-email-manager'); ?></p>
                </td>
            </tr>
        </tbody>
    </table>

    <?php submit_button(esc_html__('Save SMTP Settings', 'multisite-network-email-manager')); ?>
</form>

<hr />

<h2><?php esc_html_e('Cron Settings', 'multisite-network-email-manager'); ?></h2>
<form method="post" action="">
    <?php wp_nonce_field('mnem_smtp_settings'); ?>
    <input type="hidden" name="mnem_action" value="save_cron_settings" />
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><label for="mnem-cron-interval"><?php esc_html_e('Queue Processing Interval', 'multisite-network-email-manager'); ?></label></th>
                <td>
                    <select name="cron_interval" id="mnem-cron-interval">
                        <option value="mnem_5_minutes" <?php selected($cron_status['interval'], 'mnem_5_minutes'); ?>><?php esc_html_e('5 minutes', 'multisite-network-email-manager'); ?></option>
                        <option value="mnem_15_minutes" <?php selected($cron_status['interval'], 'mnem_15_minutes'); ?>><?php esc_html_e('15 minutes', 'multisite-network-email-manager'); ?></option>
                        <option value="mnem_30_minutes" <?php selected($cron_status['interval'], 'mnem_30_minutes'); ?>><?php esc_html_e('30 minutes', 'multisite-network-email-manager'); ?></option>
                        <option value="hourly" <?php selected($cron_status['interval'], 'hourly'); ?>><?php esc_html_e('1 hour', 'multisite-network-email-manager'); ?></option>
                        <option value="mnem_6_hours" <?php selected($cron_status['interval'], 'mnem_6_hours'); ?>><?php esc_html_e('6 hours', 'multisite-network-email-manager'); ?></option>
                        <option value="mnem_12_hours" <?php selected($cron_status['interval'], 'mnem_12_hours'); ?>><?php esc_html_e('12 hours', 'multisite-network-email-manager'); ?></option>
                        <option value="daily" <?php selected($cron_status['interval'], 'daily'); ?>><?php esc_html_e('Daily', 'multisite-network-email-manager'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Last Run', 'multisite-network-email-manager'); ?></th>
                <td><?php echo esc_html($cron_status['last_run'] !== '' ? $cron_status['last_run'] : __('Never', 'multisite-network-email-manager')); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Next Run', 'multisite-network-email-manager'); ?></th>
                <td><?php echo esc_html($cron_status['next_run'] !== '' ? $cron_status['next_run'] : __('Not scheduled', 'multisite-network-email-manager')); ?></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Failure Count', 'multisite-network-email-manager'); ?></th>
                <td><?php echo esc_html((string) $cron_status['failed_runs']); ?></td>
            </tr>
        </tbody>
    </table>
    <?php submit_button(esc_html__('Save Cron Settings', 'multisite-network-email-manager'), 'secondary'); ?>
</form>
<form method="post" action="">
    <?php wp_nonce_field('mnem_queue'); ?>
    <input type="hidden" name="mnem_action" value="process_queue_now" />
    <input type="hidden" name="redirect_page" value="mnem-settings" />
    <?php submit_button(esc_html__('Process Queue Now', 'multisite-network-email-manager'), 'secondary'); ?>
</form>

<hr />

<h2><?php esc_html_e('Send Test Email', 'multisite-network-email-manager'); ?></h2>
<form method="post" action="">
    <?php wp_nonce_field('mnem_smtp_settings'); ?>
    <input type="hidden" name="mnem_action" value="send_test_email" />
    <table class="form-table" role="presentation">
        <tbody>
            <tr>
                <th scope="row"><label for="mnem-test-email"><?php esc_html_e('Recipient Email', 'multisite-network-email-manager'); ?></label></th>
                <td><input name="test_email" id="mnem-test-email" type="email" class="regular-text" value="" /></td>
            </tr>
        </tbody>
    </table>
    <?php submit_button(esc_html__('Send Test Email', 'multisite-network-email-manager'), 'secondary'); ?>
</form>

<script>
(function() {
    var primarySelect  = document.getElementById('mnem-provider-type');
    var fallbackSelect = document.getElementById('mnem-fallback-provider');
    if (!primarySelect) return;

    var sections = {
        smtp:     document.getElementById('mnem-smtp-fields'),
        mailgun:  document.getElementById('mnem-mailgun-fields'),
        sendgrid: document.getElementById('mnem-sendgrid-fields'),
        brevo:    document.getElementById('mnem-brevo-fields'),
        postmark: document.getElementById('mnem-postmark-fields'),
        smtp2go:  document.getElementById('mnem-smtp2go-fields')
    };

    function updateVisibility() {
        var primary  = primarySelect.value;
        var fallback = fallbackSelect ? fallbackSelect.value : '';

        for (var key in sections) {
            if (sections[key]) {
                sections[key].style.display = (key === primary || key === fallback) ? '' : 'none';
            }
        }
    }

    primarySelect.addEventListener('change', updateVisibility);
    if (fallbackSelect) {
        fallbackSelect.addEventListener('change', updateVisibility);
    }
    updateVisibility();
}());
</script>
