<?php
?><div class="wrap">
    <h1>Queue</h1>
    <?php if (! empty($notice_code)) : ?>
        <div class="notice notice-info"><p><?php echo esc_html($notice_message ? $notice_message : $notice_code); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(network_admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="mnem_process_queue" />
        <?php wp_nonce_field('mnem_process_queue'); ?>
        <?php submit_button('Process Queue Batch', 'secondary'); ?>
    </form>

    <table class="widefat striped">
        <thead><tr><th>ID</th><th>Recipient</th><th>Status</th><th>Attempts</th><th>Next Attempt</th></tr></thead>
        <tbody>
        <?php if (empty($items)) : ?>
            <tr><td colspan="5">Queue is empty.</td></tr>
        <?php else : ?>
            <?php foreach ($items as $item) : ?>
                <tr>
                    <td><?php echo esc_html((string) $item['id']); ?></td>
                    <td><?php echo esc_html($item['recipient']); ?></td>
                    <td><?php echo esc_html($item['status']); ?></td>
                    <td><?php echo esc_html((string) $item['attempts']); ?></td>
                    <td><?php echo esc_html((string) $item['next_attempt_gmt']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
