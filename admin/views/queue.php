<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>Queue</h1>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Recipient</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Scheduled At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($queue_items)) : ?>
                <tr>
                    <td colspan="6">No queue items found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($queue_items as $item) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $item['id']); ?></td>
                        <td><?php echo esc_html($item['recipient_email']); ?></td>
                        <td><?php echo esc_html($item['subject']); ?></td>
                        <td><?php echo esc_html($item['status']); ?></td>
                        <td><?php echo esc_html((string) $item['attempts']); ?></td>
                        <td><?php echo esc_html($item['scheduled_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="description">Paging controls will be added in a future update.</p>
</div>
