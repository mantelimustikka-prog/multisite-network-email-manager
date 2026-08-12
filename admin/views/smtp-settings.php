<?php
?><div class="wrap">
    <h1>SMTP Settings</h1>
    <?php $settings = $view_data['settings']; ?>
    <?php if (! empty($notice_code)) : ?>
        <div class="notice notice-info"><p><?php echo esc_html($notice_message ? $notice_message : $notice_code); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(network_admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="mnem_save_smtp" />
        <?php wp_nonce_field('mnem_save_smtp'); ?>
        <table class="form-table" role="presentation">
            <tr><th scope="row">Enable SMTP</th><td><label><input type="checkbox" name="enabled" value="1" <?php checked(! empty($settings['enabled'])); ?> /> Enabled</label></td></tr>
            <tr><th scope="row">Host</th><td><input type="text" class="regular-text" name="host" value="<?php echo esc_attr($settings['host']); ?>" /></td></tr>
            <tr><th scope="row">Port</th><td><input type="number" name="port" value="<?php echo esc_attr((string) $settings['port']); ?>" /></td></tr>
            <tr><th scope="row">Security</th><td><select name="secure"><option value="tls" <?php selected($settings['secure'], 'tls'); ?>>TLS</option><option value="ssl" <?php selected($settings['secure'], 'ssl'); ?>>SSL</option><option value="" <?php selected($settings['secure'], ''); ?>>None</option></select></td></tr>
            <tr><th scope="row">Username</th><td><input type="text" class="regular-text" name="username" value="<?php echo esc_attr($settings['username']); ?>" /></td></tr>
            <tr><th scope="row">Password</th><td><input type="password" class="regular-text" name="password" value="" autocomplete="new-password" /><p class="description">Stored with obfuscation only; leave blank to keep the current password.</p></td></tr>
            <tr><th scope="row">From Email</th><td><input type="email" class="regular-text" name="from_email" value="<?php echo esc_attr($settings['from_email']); ?>" /></td></tr>
            <tr><th scope="row">From Name</th><td><input type="text" class="regular-text" name="from_name" value="<?php echo esc_attr($settings['from_name']); ?>" /></td></tr>
        </table>
        <?php submit_button('Save SMTP Settings'); ?>
    </form>

    <form method="post" action="<?php echo esc_url(network_admin_url('admin-post.php')); ?>" style="margin-top:1rem;">
        <input type="hidden" name="action" value="mnem_test_smtp" />
        <?php wp_nonce_field('mnem_test_smtp'); ?>
        <?php submit_button('Test SMTP Connection', 'secondary', 'submit', false); ?>
    </form>

    <form method="post" action="<?php echo esc_url(network_admin_url('admin-post.php')); ?>" style="margin-top:1rem;">
        <input type="hidden" name="action" value="mnem_send_test_email" />
        <?php wp_nonce_field('mnem_send_test_email'); ?>
        <label for="mnem-test-email">Test recipient</label>
        <input id="mnem-test-email" type="email" class="regular-text" name="test_email" />
        <?php submit_button('Send Test Email', 'secondary', 'submit', false); ?>
    </form>
</div>
