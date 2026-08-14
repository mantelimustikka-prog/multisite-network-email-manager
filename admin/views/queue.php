<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1>Queue</h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <div class="mnem-panel">
        <h2>Queue Summary</h2>
        <table class="widefat striped">
            <tbody>
                <tr>
                    <th scope="row">Pending</th>
                    <td><?php echo esc_html((string) $queue_stats['pending']); ?></td>
                </tr>
                <tr>
                    <th scope="row">Processing</th>
                    <td><?php echo esc_html((string) $queue_stats['processing']); ?></td>
                </tr>
                <tr>
                    <th scope="row">Sent</th>
                    <td><?php echo esc_html((string) $queue_stats['sent']); ?></td>
                </tr>
                <tr>
                    <th scope="row">Failed</th>
                    <td><?php echo esc_html((string) $queue_stats['failed']); ?></td>
                </tr>
            </tbody>
        </table>
        <div class="mnem-actions">
            <form method="post">
                <?php wp_nonce_field('mnem_queue'); ?>
                <input type="hidden" name="mnem_action" value="process_queue_now" />
                <input type="hidden" name="redirect_page" value="mnem-queue" />
                <?php submit_button('Process Queue Now', 'secondary', 'submit', false); ?>
            </form>
            <form method="post">
                <?php wp_nonce_field('mnem_queue'); ?>
                <input type="hidden" name="mnem_action" value="retry_failed_queue" />
                <input type="hidden" name="redirect_page" value="mnem-queue" />
                <?php submit_button('Retry Failed Items', 'secondary', 'submit', false); ?>
            </form>
        </div>
    </div>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Blog ID</th>
                <th>Campaign</th>
                <th>Recipient</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Attempts</th>
                <th>Scheduled At</th>
                <th>Processed At</th>
                <th>Sent At</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($queue_items)) : ?>
                <tr>
                    <td colspan="10">No queue items found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($queue_items as $item) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $item['id']); ?></td>
                        <td><?php echo esc_html((string) $item['blog_id']); ?></td>
                        <td><?php echo esc_html((string) $item['campaign_id']); ?></td>
                        <td><?php echo esc_html($item['recipient_email']); ?></td>
                        <td><?php echo esc_html($item['subject']); ?></td>
                        <td><?php echo esc_html($item['status']); ?></td>
                        <td><?php echo esc_html((string) $item['attempts']); ?></td>
                        <td><?php echo esc_html($item['scheduled_at']); ?></td>
                        <td><?php echo esc_html(!empty($item['processed_at']) ? $item['processed_at'] : '—'); ?></td>
                        <td><?php echo esc_html(!empty($item['sent_at']) ? $item['sent_at'] : '—'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="description">Retry backoff status: <?php echo esc_html($queue_stats['next_retry_at'] !== '' ? $queue_stats['next_retry_at'] . ' (attempt ' . (string) $queue_stats['next_retry_attempts'] . ')' : 'No retries scheduled'); ?></p>
</div>
