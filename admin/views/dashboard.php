<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>Multisite Network Email Manager</h1>

    <?php if (!$smtp_configured) : ?>
        <div class="notice notice-warning"><p>SMTP is not configured yet.</p></div>
    <?php endif; ?>

    <table class="widefat striped" style="max-width: 700px; margin-bottom: 20px;">
        <tbody>
            <tr>
                <th scope="row">Plugin Version</th>
                <td><?php echo esc_html($plugin_version); ?></td>
            </tr>
            <tr>
                <th scope="row">Queue Items</th>
                <td><?php echo esc_html((string) $queue_count); ?></td>
            </tr>
            <tr>
                <th scope="row">Suppression Entries</th>
                <td><?php echo esc_html((string) $suppression_count); ?></td>
            </tr>
        </tbody>
    </table>

    <h2>Recent Logs</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Level</th>
                <th>Message</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recent_logs)) : ?>
                <tr>
                    <td colspan="3">No log entries yet.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($recent_logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log['level']); ?></td>
                        <td><?php echo esc_html($log['message']); ?></td>
                        <td><?php echo esc_html($log['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
