<?php
?><div class="wrap">
    <h1>Email Manager Dashboard</h1>
    <?php if (! empty($notice_code)) : ?>
        <div class="notice notice-info"><p><?php echo esc_html($notice_message ? $notice_message : $notice_code); ?></p></div>
    <?php endif; ?>
    <p>This baseline scaffold centralizes network email settings for multisite.</p>
    <ul>
        <li><strong>Queued items:</strong> <?php echo esc_html((string) count($queue)); ?></li>
        <li><strong>Campaigns:</strong> <?php echo esc_html((string) count($campaigns)); ?></li>
        <li><strong>Suppressed recipients:</strong> <?php echo esc_html((string) count($suppressions)); ?></li>
    </ul>
</div>
