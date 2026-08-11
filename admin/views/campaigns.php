<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>Campaigns</h1>
    <p>Campaign creation and delivery UI is a placeholder for future development.</p>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($campaigns)) : ?>
                <tr>
                    <td colspan="5">No campaigns yet.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($campaigns as $campaign) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $campaign['id']); ?></td>
                        <td><?php echo esc_html($campaign['name']); ?></td>
                        <td><?php echo esc_html($campaign['subject']); ?></td>
                        <td><?php echo esc_html($campaign['status']); ?></td>
                        <td><?php echo esc_html($campaign['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
