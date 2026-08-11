<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>Logs</h1>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Level</th>
                <th>Message</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
                <tr>
                    <td colspan="3">No log entries found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($logs as $log) : ?>
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
