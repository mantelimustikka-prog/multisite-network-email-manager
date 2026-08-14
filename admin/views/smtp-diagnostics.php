<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1>SMTP Diagnostics</h1>

    <div class="mnem-panel">
        <h2>Current SMTP Settings</h2>
        <table class="widefat striped">
            <tbody>
                <tr><th scope="row">Host</th><td><?php echo esc_html($settings['host'] !== '' ? $settings['host'] : 'Not set'); ?></td></tr>
                <tr><th scope="row">Port</th><td><?php echo esc_html((string) $settings['port']); ?></td></tr>
                <tr><th scope="row">Encryption</th><td><?php echo esc_html($settings['encryption']); ?></td></tr>
                <tr><th scope="row">Auth</th><td><?php echo esc_html($settings['username'] !== '' ? 'Enabled' : 'Disabled'); ?></td></tr>
                <tr><th scope="row">From Email</th><td><?php echo esc_html($settings['from_email']); ?></td></tr>
                <tr><th scope="row">Password</th><td>*****</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mnem-panel">
        <h2>Connection Status</h2>
        <p><strong><?php echo esc_html(!empty($connection['success']) ? 'Ready' : 'Needs Attention'); ?></strong> — <?php echo esc_html($connection['message']); ?></p>
        <p>
            <button type="button" class="button button-secondary" data-mnem-smtp-action="test-connection">Test Connection</button>
        </p>
    </div>

    <div class="mnem-panel">
        <h2>Send Test Email</h2>
        <p>
            <input type="email" class="regular-text" id="mnem-smtp-test-email" placeholder="admin@example.com" />
            <button type="button" class="button button-primary" data-mnem-smtp-action="send-test-email">Send Test Email</button>
        </p>
        <p class="description">Rate limit: 5 test emails per 5 minutes.</p>
    </div>

    <div class="mnem-panel">
        <h2>Last Test Result</h2>
        <?php if (!empty($last_result)) : ?>
            <p><strong><?php echo esc_html(!empty($last_result['success']) ? 'Success' : 'Failed'); ?></strong> — <?php echo esc_html($last_result['message']); ?></p>
            <p class="description">At: <?php echo esc_html($last_result['timestamp']); ?> | Type: <?php echo esc_html($last_result['type']); ?></p>
            <pre data-mnem-smtp-result><?php echo esc_html(wp_json_encode($last_result)); ?></pre>
        <?php else : ?>
            <p>No diagnostics run yet.</p>
            <pre data-mnem-smtp-result></pre>
        <?php endif; ?>
        <p><button type="button" class="button button-secondary" data-mnem-smtp-action="copy-result">Copy Result</button></p>
    </div>
</div>
