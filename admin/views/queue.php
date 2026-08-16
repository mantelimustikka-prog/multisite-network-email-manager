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

    <form method="post" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" class="mnem-queue-bulk-form">
        <?php wp_nonce_field('mnem_queue_item_delete'); ?>
        <input type="hidden" name="redirect_page" value="mnem-queue" />

        <div class="mnem-panel mnem-queue-bulk-actions">
            <div class="mnem-actions">
                <label for="mnem-bulk-action"><strong>Bulk Actions:</strong></label>
                <select name="bulk_action" id="mnem-bulk-action"<?php echo empty($queue_items) ? ' disabled="disabled"' : ''; ?>>
                    <option value="">-- Select Action --</option>
                    <option value="delete_pending">Delete All Pending</option>
                    <option value="delete_failed">Delete All Failed</option>
                    <option value="delete_selected">Delete Selected Items</option>
                </select>
                <button type="submit" class="button"<?php echo empty($queue_items) ? ' disabled="disabled"' : ''; ?>>Apply</button>
                <span class="description">Showing <?php echo esc_html((string) count($queue_items)); ?> queue items.</span>
            </div>
        </div>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th scope="col" class="check-column"><input type="checkbox" id="mnem-check-all"<?php echo empty($queue_items) ? ' disabled="disabled"' : ''; ?> /></th>
                    <th>ID</th>
                    <th>Blog ID</th>
                    <th>Campaign</th>
                    <th>Recipient</th>
                    <th>Subject</th>
                    <th>Status</th>
                    <th>Attempts</th>
                    <th>Scheduled At</th>
                    <th>Opens</th>
                    <th>Clicks</th>
                    <th>Sent At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($queue_items)) : ?>
                    <tr>
                        <td colspan="13">No queue items found.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($queue_items as $item) : ?>
                        <?php $is_deletable = in_array($item['status'], \MNEM\Queue::DELETABLE_STATUSES, true); ?>
                        <?php $display_status = \MNEM\Queue::get_display_status($item); ?>
                        <?php $status_slug = isset($item['status']) ? strtolower((string) $item['status']) : 'failed'; ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" class="mnem-queue-checkbox" name="queue_ids[]" value="<?php echo esc_attr((string) $item['id']); ?>"<?php echo $is_deletable ? '' : ' disabled="disabled"'; ?> />
                            </th>
                            <td><?php echo esc_html((string) $item['id']); ?></td>
                            <td><?php echo esc_html((string) $item['blog_id']); ?></td>
                            <td><?php echo esc_html((string) $item['campaign_id']); ?></td>
                            <td><?php echo esc_html($item['recipient_email']); ?></td>
                            <td><?php echo esc_html($item['subject']); ?></td>
                            <td><span class="mnem-badge mnem-status-<?php echo esc_attr($status_slug); ?>"><?php echo esc_html($display_status); ?></span></td>
                            <td><?php echo esc_html((string) $item['attempts']); ?></td>
                            <td><?php echo esc_html($item['scheduled_at']); ?></td>
                            <td><?php echo esc_html(!empty($item['opened']) ? (string) $item['opened'] : '—'); ?></td>
                            <td><?php echo esc_html(!empty($item['clicked']) ? (string) $item['clicked'] : '—'); ?></td>
                            <td><?php echo esc_html(!empty($item['sent_at']) ? $item['sent_at'] : '—'); ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="button button-small mnem-queue-preview-button"
                                    data-queue-id="<?php echo esc_attr((string) $item['id']); ?>"
                                    data-recipient="<?php echo esc_attr($item['recipient_email']); ?>"
                                    data-subject="<?php echo esc_attr($item['subject']); ?>"
                                    data-status="<?php echo esc_attr($item['status']); ?>"
                                    data-created-at="<?php echo esc_attr($item['created_at']); ?>"
                                >Preview</button>
                                <?php if ($is_deletable) : ?>
                                    <button
                                        type="button"
                                        class="button button-small button-link-delete mnem-delete-queue-item"
                                        data-queue-id="<?php echo esc_attr((string) $item['id']); ?>"
                                        data-recipient="<?php echo esc_attr($item['recipient_email']); ?>"
                                        data-status="<?php echo esc_attr($item['status']); ?>"
                                    >Delete</button>
                                <?php else : ?>
                                    <span class="description">Not deletable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
    <form method="post" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" id="mnem-single-queue-delete-form">
        <?php wp_nonce_field('mnem_queue_item_delete'); ?>
        <input type="hidden" name="mnem_action" value="delete_queue_item" />
        <input type="hidden" name="queue_id" value="" />
        <input type="hidden" name="redirect_page" value="mnem-queue" />
    </form>

    <p class="description">Retry backoff status: <?php echo esc_html($queue_stats['next_retry_at'] !== '' ? $queue_stats['next_retry_at'] . ' (attempt ' . (string) $queue_stats['next_retry_attempts'] . ')' : 'No retries scheduled'); ?></p>
</div>
