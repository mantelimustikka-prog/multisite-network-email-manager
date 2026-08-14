<?php

defined('ABSPATH') || exit;

use MNEM\ProviderManager;
?>
<div class="wrap">
    <h1>Email Settings</h1>

    <?php if (!empty($notice_message)) : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('mnem_smtp_settings'); ?>
        <input type="hidden" name="mnem_action" value="save_smtp_settings" />

        <h2>Email Service Provider</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-provider-type">Email Provider</label></th>
                    <td>
                        <select name="provider_type" id="mnem-provider-type">
                            <?php
                            $providers = ProviderManager::get_available_providers();
                            foreach ($providers as $ptype => $pmeta) :
                            ?>
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

        <h2>From Address</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-from-email">From Email</label></th>
                    <td><input name="from_email" id="mnem-from-email" type="email" class="regular-text" value="<?php echo esc_attr($settings['from_email']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-from-name">From Name</label></th>
                    <td><input name="from_name" id="mnem-from-name" type="text" class="regular-text" value="<?php echo esc_attr($settings['from_name']); ?>" /></td>
                </tr>
            </tbody>
        </table>

        <div id="mnem-smtp-fields">
            <h2>SMTP Configuration</h2>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="mnem-host">Host</label></th>
                        <td><input name="host" id="mnem-host" type="text" class="regular-text" value="<?php echo esc_attr($settings['host']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mnem-port">Port</label></th>
                        <td><input name="port" id="mnem-port" type="number" class="small-text" value="<?php echo esc_attr((string) $settings['port']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mnem-encryption">Encryption</label></th>
                        <td>
                            <select name="encryption" id="mnem-encryption">
                                <option value="tls" <?php selected($settings['encryption'], 'tls'); ?>>TLS</option>
                                <option value="ssl" <?php selected($settings['encryption'], 'ssl'); ?>>SSL</option>
                                <option value="none" <?php selected($settings['encryption'], 'none'); ?>>None</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mnem-username">Username</label></th>
                        <td><input name="username" id="mnem-username" type="text" class="regular-text" value="<?php echo esc_attr($settings['username']); ?>" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mnem-password">Password</label></th>
                        <td>
                            <input name="password" id="mnem-password" type="password" class="regular-text" value="" autocomplete="new-password" />
                            <p class="description">Leave blank to keep the currently stored password.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php
        $api_providers = array('mailgun', 'sendgrid', 'brevo', 'postmark', 'smtp2go');
        $provider_config = isset($settings['provider_config']) && is_array($settings['provider_config']) ? $settings['provider_config'] : array();
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
        foreach ($api_providers as $ptype) :
            $pconfig = isset($provider_config[$ptype]) && is_array($provider_config[$ptype]) ? $provider_config[$ptype] : array();
        ?>
        <div id="mnem-<?php echo esc_attr($ptype); ?>-fields">
            <h2><?php echo esc_html($provider_labels[$ptype]); ?> Configuration</h2>
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
                                <span class="description">&#10003; Saved. Leave blank to keep current value.</span>
                            <?php else : ?>
                                <p class="description">Enter your <?php echo esc_html($provider_labels[$ptype]); ?> <?php echo esc_html($field_label); ?>.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>

        <h2>Fallback Provider</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-fallback-enabled">Enable Fallback</label></th>
                    <td>
                        <label>
                            <input type="checkbox" name="fallback_enabled" id="mnem-fallback-enabled" value="1" <?php checked(!empty($settings['fallback_enabled'])); ?> />
                            Retry with a secondary provider if the primary provider fails
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-fallback-provider">Fallback Provider</label></th>
                    <td>
                        <select name="fallback_provider" id="mnem-fallback-provider">
                            <option value="">(None)</option>
                            <?php foreach ($providers as $ptype => $pmeta) : ?>
                                <option value="<?php echo esc_attr($ptype); ?>" <?php selected($settings['fallback_provider'], $ptype); ?>><?php echo esc_html($pmeta['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button('Save Settings'); ?>
    </form>

    <hr />

    <h2>Cron Settings</h2>
    <form method="post" action="">
        <?php wp_nonce_field('mnem_smtp_settings'); ?>
        <input type="hidden" name="mnem_action" value="save_cron_settings" />
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-cron-interval">Queue Processing Interval</label></th>
                    <td>
                        <select name="cron_interval" id="mnem-cron-interval">
                            <option value="mnem_5_minutes" <?php selected($cron_status['interval'], 'mnem_5_minutes'); ?>>5 minutes</option>
                            <option value="mnem_15_minutes" <?php selected($cron_status['interval'], 'mnem_15_minutes'); ?>>15 minutes</option>
                            <option value="mnem_30_minutes" <?php selected($cron_status['interval'], 'mnem_30_minutes'); ?>>30 minutes</option>
                            <option value="hourly" <?php selected($cron_status['interval'], 'hourly'); ?>>1 hour</option>
                            <option value="mnem_6_hours" <?php selected($cron_status['interval'], 'mnem_6_hours'); ?>>6 hours</option>
                            <option value="mnem_12_hours" <?php selected($cron_status['interval'], 'mnem_12_hours'); ?>>12 hours</option>
                            <option value="daily" <?php selected($cron_status['interval'], 'daily'); ?>>Daily</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Last Run</th>
                    <td><?php echo esc_html($cron_status['last_run'] !== '' ? $cron_status['last_run'] : 'Never'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Next Run</th>
                    <td><?php echo esc_html($cron_status['next_run'] !== '' ? $cron_status['next_run'] : 'Not scheduled'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Failure Count</th>
                    <td><?php echo esc_html((string) $cron_status['failed_runs']); ?></td>
                </tr>
            </tbody>
        </table>
        <?php submit_button('Save Cron Settings', 'secondary'); ?>
    </form>
    <form method="post" action="">
        <?php wp_nonce_field('mnem_queue'); ?>
        <input type="hidden" name="mnem_action" value="process_queue_now" />
        <input type="hidden" name="redirect_page" value="mnem-smtp-settings" />
        <?php submit_button('Process Queue Now', 'secondary'); ?>
    </form>

    <hr />

    <h2>Send Test Email</h2>
    <form method="post" action="">
        <?php wp_nonce_field('mnem_smtp_settings'); ?>
        <input type="hidden" name="mnem_action" value="send_test_email" />
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-test-email">Recipient Email</label></th>
                    <td><input name="test_email" id="mnem-test-email" type="email" class="regular-text" value="" /></td>
                </tr>
            </tbody>
        </table>
        <?php submit_button('Send Test Email', 'secondary'); ?>
    </form>
</div>
<script>
(function() {
    var select = document.getElementById('mnem-provider-type');
    if (!select) return;

    var sections = {
        smtp:     document.getElementById('mnem-smtp-fields'),
        mailgun:  document.getElementById('mnem-mailgun-fields'),
        sendgrid: document.getElementById('mnem-sendgrid-fields'),
        brevo:    document.getElementById('mnem-brevo-fields'),
        postmark: document.getElementById('mnem-postmark-fields'),
        smtp2go:  document.getElementById('mnem-smtp2go-fields')
    };

    function showActive() {
        var active = select.value;
        for (var key in sections) {
            if (sections[key]) {
                sections[key].style.display = (key === active) ? '' : 'none';
            }
        }
    }

    select.addEventListener('change', showActive);
    showActive();
})();
</script>

    <?php if (!empty($notice_message)) : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field('mnem_smtp_settings'); ?>
        <input type="hidden" name="mnem_action" value="save_smtp_settings" />
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-host">Host</label></th>
                    <td><input name="host" id="mnem-host" type="text" class="regular-text" value="<?php echo esc_attr($settings['host']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-port">Port</label></th>
                    <td><input name="port" id="mnem-port" type="number" class="small-text" value="<?php echo esc_attr((string) $settings['port']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-encryption">Encryption</label></th>
                    <td>
                        <select name="encryption" id="mnem-encryption">
                            <option value="tls" <?php selected($settings['encryption'], 'tls'); ?>>TLS</option>
                            <option value="ssl" <?php selected($settings['encryption'], 'ssl'); ?>>SSL</option>
                            <option value="none" <?php selected($settings['encryption'], 'none'); ?>>None</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-username">Username</label></th>
                    <td><input name="username" id="mnem-username" type="text" class="regular-text" value="<?php echo esc_attr($settings['username']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-password">Password</label></th>
                    <td>
                        <input name="password" id="mnem-password" type="password" class="regular-text" value="" autocomplete="new-password" />
                        <p class="description">Leave blank to keep the currently stored password.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-from-email">From Email</label></th>
                    <td><input name="from_email" id="mnem-from-email" type="email" class="regular-text" value="<?php echo esc_attr($settings['from_email']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-from-name">From Name</label></th>
                    <td><input name="from_name" id="mnem-from-name" type="text" class="regular-text" value="<?php echo esc_attr($settings['from_name']); ?>" /></td>
                </tr>
            </tbody>
        </table>
        <?php submit_button('Save SMTP Settings'); ?>
    </form>

    <hr />

    <h2>Cron Settings</h2>
    <form method="post" action="">
        <?php wp_nonce_field('mnem_smtp_settings'); ?>
        <input type="hidden" name="mnem_action" value="save_cron_settings" />
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-cron-interval">Queue Processing Interval</label></th>
                    <td>
                        <select name="cron_interval" id="mnem-cron-interval">
                            <option value="mnem_5_minutes" <?php selected($cron_status['interval'], 'mnem_5_minutes'); ?>>5 minutes</option>
                            <option value="mnem_15_minutes" <?php selected($cron_status['interval'], 'mnem_15_minutes'); ?>>15 minutes</option>
                            <option value="mnem_30_minutes" <?php selected($cron_status['interval'], 'mnem_30_minutes'); ?>>30 minutes</option>
                            <option value="hourly" <?php selected($cron_status['interval'], 'hourly'); ?>>1 hour</option>
                            <option value="mnem_6_hours" <?php selected($cron_status['interval'], 'mnem_6_hours'); ?>>6 hours</option>
                            <option value="mnem_12_hours" <?php selected($cron_status['interval'], 'mnem_12_hours'); ?>>12 hours</option>
                            <option value="daily" <?php selected($cron_status['interval'], 'daily'); ?>>Daily</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Last Run</th>
                    <td><?php echo esc_html($cron_status['last_run'] !== '' ? $cron_status['last_run'] : 'Never'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Next Run</th>
                    <td><?php echo esc_html($cron_status['next_run'] !== '' ? $cron_status['next_run'] : 'Not scheduled'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Failure Count</th>
                    <td><?php echo esc_html((string) $cron_status['failed_runs']); ?></td>
                </tr>
            </tbody>
        </table>
        <?php submit_button('Save Cron Settings', 'secondary'); ?>
    </form>
    <form method="post" action="">
        <?php wp_nonce_field('mnem_queue'); ?>
        <input type="hidden" name="mnem_action" value="process_queue_now" />
        <input type="hidden" name="redirect_page" value="mnem-smtp-settings" />
        <?php submit_button('Process Queue Now', 'secondary'); ?>
    </form>

    <hr />

    <h2>Send Test Email</h2>
    <form method="post" action="">
        <?php wp_nonce_field('mnem_smtp_settings'); ?>
        <input type="hidden" name="mnem_action" value="send_test_email" />
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-test-email">Recipient Email</label></th>
                    <td><input name="test_email" id="mnem-test-email" type="email" class="regular-text" value="" /></td>
                </tr>
            </tbody>
        </table>
        <?php submit_button('Send Test Email', 'secondary'); ?>
    </form>
</div>
