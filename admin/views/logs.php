<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>Logs</h1>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Blog ID</th>
                <th>Level</th>
                <th>Message</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)) : ?>
                <tr>
                    <td colspan="4">No log entries found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(isset($log['blog_id']) ? (string) $log['blog_id'] : '0'); ?></td>
                        <td><?php echo esc_html($log['level']); ?></td>
                        <td><?php echo esc_html($log['message']); ?></td>
                        <td><?php echo esc_html($log['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
