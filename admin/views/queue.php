<?php

defined('ABSPATH') || exit;

$base_url = network_admin_url('admin.php?page=mnem-queue');
$filter_url = add_query_arg(array(
    'per_page'      => $per_page,
    'status_filter' => $status_filter,
), $base_url);
?>
<div class="wrap mnem-dashboard">
    <h1>
        <?php
        printf(
            esc_html__('Email Status Logs (%s Records)', 'multisite-network-email-manager'),
            esc_html(number_format($total_all_records))
        );
        ?>
    </h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <div class="mnem-panel">
        <h2>Queue Summary</h2>
        <table class="widefat striped">
            <tbody>
                <?php if (empty($queue_summary)) : ?>
                    <tr>
                        <td colspan="2">No status data available.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($queue_summary as $status => $count) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $status))); ?></th>
                            <td><?php echo esc_html((string) ((int) $count)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
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

    <!-- Filter & per-page controls -->
    <form method="get" action="<?php echo esc_url(network_admin_url('admin.php')); ?>">
        <input type="hidden" name="page" value="mnem-queue" />
        <div style="margin: 10px 0; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <label for="mnem-status-filter"><strong><?php esc_html_e('Status:', 'multisite-network-email-manager'); ?></strong></label>
            <select name="status_filter" id="mnem-status-filter">
                <option value=""><?php esc_html_e('All Statuses', 'multisite-network-email-manager'); ?></option>
                <?php foreach ($all_statuses as $s) : ?>
                    <option value="<?php echo esc_attr((string) $s); ?>"<?php selected($status_filter, (string) $s); ?>>
                        <?php echo esc_html(ucwords(str_replace('_', ' ', (string) $s))); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="mnem-per-page"><strong><?php esc_html_e('Per page:', 'multisite-network-email-manager'); ?></strong></label>
            <select name="per_page" id="mnem-per-page">
                <?php foreach (array(10, 20, 50, 100, 200, 500) as $n) : ?>
                    <option value="<?php echo esc_attr((string) $n); ?>"<?php selected($per_page, $n); ?>><?php echo esc_html((string) $n); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="button"><?php esc_html_e('Apply', 'multisite-network-email-manager'); ?></button>
        </div>
    </form>

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
                <?php
                $range_start = $total_filtered > 0 ? ($offset + 1) : 0;
                $range_end   = min($offset + $per_page, $total_filtered);
                ?>
                <span class="description">
                    <?php
                    printf(
                        esc_html__('Showing %1$s–%2$s of %3$s records', 'multisite-network-email-manager'),
                        esc_html(number_format($range_start)),
                        esc_html(number_format($range_end)),
                        esc_html(number_format($total_filtered))
                    );
                    if ($status_filter !== '') {
                        echo ' (' . esc_html(number_format($total_all_records)) . ' ' . esc_html__('total in database', 'multisite-network-email-manager') . ')';
                    }
                    ?>
                </span>
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
                            <td title="<?php echo esc_attr(!empty($item['opened']) ? 'First open: ' . (string) $item['opened'] : 'No opens yet'); ?>"><?php echo esc_html((string) (isset($item['opens_count']) ? (int) $item['opens_count'] : 0)); ?></td>
                            <td title="<?php echo esc_attr(!empty($item['clicked']) ? 'First click: ' . (string) $item['clicked'] : 'No clicks yet'); ?>"><?php echo esc_html((string) (isset($item['clicks_count']) ? (int) $item['clicks_count'] : 0)); ?></td>
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
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>

    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom" style="margin-top: 10px;">
            <div class="tablenav-pages">
                <?php
                $page_url = add_query_arg(array(
                    'per_page'      => $per_page,
                    'status_filter' => $status_filter,
                ), $base_url);
                ?>
                <a class="button<?php echo $current_page <= 1 ? ' disabled' : ''; ?>"
                   href="<?php echo esc_url(add_query_arg('paged', 1, $page_url)); ?>">&laquo; <?php esc_html_e('First', 'multisite-network-email-manager'); ?></a>
                <a class="button<?php echo $current_page <= 1 ? ' disabled' : ''; ?>"
                   href="<?php echo esc_url(add_query_arg('paged', max(1, $current_page - 1), $page_url)); ?>">&lsaquo; <?php esc_html_e('Previous', 'multisite-network-email-manager'); ?></a>

                <span style="margin: 0 8px; line-height: 28px;">
                    <?php
                    printf(
                        esc_html__('Page %1$s of %2$s', 'multisite-network-email-manager'),
                        esc_html((string) $current_page),
                        esc_html((string) $total_pages)
                    );
                    ?>
                </span>

                <a class="button<?php echo $current_page >= $total_pages ? ' disabled' : ''; ?>"
                   href="<?php echo esc_url(add_query_arg('paged', min($total_pages, $current_page + 1), $page_url)); ?>"><?php esc_html_e('Next', 'multisite-network-email-manager'); ?> &rsaquo;</a>
                <a class="button<?php echo $current_page >= $total_pages ? ' disabled' : ''; ?>"
                   href="<?php echo esc_url(add_query_arg('paged', $total_pages, $page_url)); ?>"><?php esc_html_e('Last', 'multisite-network-email-manager'); ?> &raquo;</a>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" id="mnem-single-queue-delete-form">
        <?php wp_nonce_field('mnem_queue_item_delete'); ?>
        <input type="hidden" name="mnem_action" value="delete_queue_item" />
        <input type="hidden" name="queue_id" value="" />
        <input type="hidden" name="redirect_page" value="mnem-queue" />
    </form>

    <p class="description">Retry backoff status: <?php echo esc_html($queue_stats['next_retry_at'] !== '' ? $queue_stats['next_retry_at'] . ' (attempt ' . (string) $queue_stats['next_retry_attempts'] . ')' : 'No retries scheduled'); ?></p>
</div>
