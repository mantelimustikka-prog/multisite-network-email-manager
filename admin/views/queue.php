<?php

defined('ABSPATH') || exit;
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

    <?php
    // Build base URL preserving current filters except paged.
    $base_url_args = array('page' => 'mnem-queue');
    if ($status_filter !== '') {
        $base_url_args['status_filter'] = $status_filter;
    }
    if ($per_page !== 50) {
        $base_url_args['per_page'] = $per_page;
    }
    $base_url = network_admin_url('admin.php?' . http_build_query($base_url_args, '', '&'));

    // Build filter form URL (without paged/status_filter/per_page — the form provides them).
    $filter_form_url = network_admin_url('admin.php?page=mnem-queue');
    ?>

    <div class="mnem-panel mnem-queue-filters" style="padding: 12px 16px;">
        <form method="get" action="<?php echo esc_url(network_admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="mnem-queue" />
            <label for="mnem-status-filter"><strong><?php esc_html_e('Status:', 'multisite-network-email-manager'); ?></strong></label>
            <select name="status_filter" id="mnem-status-filter">
                <option value=""><?php esc_html_e('All Statuses', 'multisite-network-email-manager'); ?></option>
                <?php foreach ($all_statuses as $s) : ?>
                    <option value="<?php echo esc_attr($s); ?>"<?php selected($status_filter, $s); ?>>
                        <?php echo esc_html(ucwords(str_replace('_', ' ', $s))); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            &nbsp;
            <label for="mnem-per-page"><strong><?php esc_html_e('Per page:', 'multisite-network-email-manager'); ?></strong></label>
            <select name="per_page" id="mnem-per-page">
                <?php foreach (array(10, 20, 50, 100, 200, 500) as $opt) : ?>
                    <option value="<?php echo esc_attr((string) $opt); ?>"<?php selected($per_page, $opt); ?>><?php echo esc_html((string) $opt); ?></option>
                <?php endforeach; ?>
            </select>
            &nbsp;
            <button type="submit" class="button"><?php esc_html_e('Filter', 'multisite-network-email-manager'); ?></button>
            <?php if ($status_filter !== '') : ?>
                <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-queue&per_page=' . $per_page)); ?>" class="button"><?php esc_html_e('Clear Filter', 'multisite-network-email-manager'); ?></a>
            <?php endif; ?>
        </form>
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
                <span class="description">
                    <?php
                    $range_start = $total_filtered > 0 ? (($current_page - 1) * $per_page) + 1 : 0;
                    $range_end   = min($current_page * $per_page, $total_filtered);
                    printf(
                        esc_html__('Showing %1$s-%2$s of %3$s records', 'multisite-network-email-manager'),
                        esc_html(number_format($range_start)),
                        esc_html(number_format($range_end)),
                        esc_html(number_format($total_filtered))
                    );
                    ?>
                </span>
            </div>
        </div>

        <?php if ($total_pages > 1) : ?>
        <div class="tablenav top" style="margin-bottom: 8px;">
            <div class="tablenav-pages" style="float: right;">
                <?php
                $page_url_base = network_admin_url('admin.php?' . http_build_query(
                    array_filter(array(
                        'page'          => 'mnem-queue',
                        'status_filter' => $status_filter !== '' ? $status_filter : null,
                        'per_page'      => $per_page !== 50 ? $per_page : null,
                    )),
                    '',
                    '&'
                ));
                ?>
                <a class="button<?php echo $current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=1'); ?>">&laquo; <?php esc_html_e('First', 'multisite-network-email-manager'); ?></a>
                <a class="button<?php echo $current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=' . max(1, $current_page - 1)); ?>">&lsaquo; <?php esc_html_e('Prev', 'multisite-network-email-manager'); ?></a>
                <span class="paging-input" style="padding: 0 8px;">
                    <?php
                    printf(
                        esc_html__('Page %1$s of %2$s', 'multisite-network-email-manager'),
                        esc_html((string) $current_page),
                        esc_html((string) $total_pages)
                    );
                    ?>
                </span>
                <a class="button<?php echo $current_page >= $total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=' . min($total_pages, $current_page + 1)); ?>"><?php esc_html_e('Next', 'multisite-network-email-manager'); ?> &rsaquo;</a>
                <a class="button<?php echo $current_page >= $total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=' . $total_pages); ?>"><?php esc_html_e('Last', 'multisite-network-email-manager'); ?> &raquo;</a>
            </div>
            <br class="clear" />
        </div>
        <?php endif; ?>

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
                                <?php else : ?>
                                    <span class="description">Not deletable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom" style="margin-top: 8px;">
            <div class="tablenav-pages" style="float: right;">
                <a class="button<?php echo $current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=1'); ?>">&laquo; <?php esc_html_e('First', 'multisite-network-email-manager'); ?></a>
                <a class="button<?php echo $current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=' . max(1, $current_page - 1)); ?>">&lsaquo; <?php esc_html_e('Prev', 'multisite-network-email-manager'); ?></a>
                <span class="paging-input" style="padding: 0 8px;">
                    <?php
                    printf(
                        esc_html__('Page %1$s of %2$s', 'multisite-network-email-manager'),
                        esc_html((string) $current_page),
                        esc_html((string) $total_pages)
                    );
                    ?>
                </span>
                <a class="button<?php echo $current_page >= $total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=' . min($total_pages, $current_page + 1)); ?>"><?php esc_html_e('Next', 'multisite-network-email-manager'); ?> &rsaquo;</a>
                <a class="button<?php echo $current_page >= $total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=' . $total_pages); ?>"><?php esc_html_e('Last', 'multisite-network-email-manager'); ?> &raquo;</a>
            </div>
            <br class="clear" />
        </div>
        <?php endif; ?>
    </form>
    <form method="post" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" id="mnem-single-queue-delete-form">
        <?php wp_nonce_field('mnem_queue_item_delete'); ?>
        <input type="hidden" name="mnem_action" value="delete_queue_item" />
        <input type="hidden" name="queue_id" value="" />
        <input type="hidden" name="redirect_page" value="mnem-queue" />
    </form>

    <p class="description">Retry backoff status: <?php echo esc_html($queue_stats['next_retry_at'] !== '' ? $queue_stats['next_retry_at'] . ' (attempt ' . (string) $queue_stats['next_retry_attempts'] . ')' : 'No retries scheduled'); ?></p>
</div>

