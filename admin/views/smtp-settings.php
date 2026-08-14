<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>SMTP Settings</h1>

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
