<?php

defined('ABSPATH') || exit;

// Page slug and tab param are set by the parent view (logs.php).
$_email_page_slug = 'mnem-logs';
$_email_tab_param = '&tab=email';
?>
    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <?php
    $rate_limit_minute = \MNEM\SmtpSettings::get_campaign_rate_limit_per_minute();
    $rate_limit_hour = \MNEM\SmtpSettings::get_campaign_rate_limit_per_hour();
    $rate_limit_day = \MNEM\SmtpSettings::get_campaign_rate_limit_per_day();

    $identifier_minute = 'campaign_send_' . gmdate('Y-m-d-H-i');
    $identifier_hour = 'campaign_send_' . gmdate('Y-m-d-H');
    $identifier_day = 'campaign_send_' . gmdate('Y-m-d');

    $current_minute = \MNEM\RateLimiter::get_count($identifier_minute);
    $current_hour = \MNEM\RateLimiter::get_count($identifier_hour);
    $current_day = \MNEM\RateLimiter::get_count($identifier_day);
    ?>

    <div class="mnem-rate-limit-status" style="margin: 15px 0; padding: 15px; background: #f0f0f0; border-radius: 4px;">
        <h3><?php esc_html_e('Campaign Rate Limits (Current Period)', 'multisite-network-email-manager'); ?></h3>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div>
                <strong><?php esc_html_e('Per Minute:', 'multisite-network-email-manager'); ?></strong>
                <?php if ($rate_limit_minute > 0) : ?>
                    <p><?php printf(esc_html__('%d / %d emails', 'multisite-network-email-manager'), $current_minute, $rate_limit_minute); ?></p>
                    <div style="width: 100%; height: 20px; background: #ddd; border-radius: 3px; overflow: hidden;">
                        <div style="width: <?php echo esc_attr((string) min(100, (int) (($current_minute / $rate_limit_minute) * 100))); ?>%; height: 100%; background: #28a745;"></div>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e('Unlimited', 'multisite-network-email-manager'); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <strong><?php esc_html_e('Per Hour:', 'multisite-network-email-manager'); ?></strong>
                <?php if ($rate_limit_hour > 0) : ?>
                    <p><?php printf(esc_html__('%d / %d emails', 'multisite-network-email-manager'), $current_hour, $rate_limit_hour); ?></p>
                    <div style="width: 100%; height: 20px; background: #ddd; border-radius: 3px; overflow: hidden;">
                        <div style="width: <?php echo esc_attr((string) min(100, (int) (($current_hour / $rate_limit_hour) * 100))); ?>%; height: 100%; background: #28a745;"></div>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e('Unlimited', 'multisite-network-email-manager'); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <strong><?php esc_html_e('Per Day:', 'multisite-network-email-manager'); ?></strong>
                <?php if ($rate_limit_day > 0) : ?>
                    <p><?php printf(esc_html__('%d / %d emails', 'multisite-network-email-manager'), $current_day, $rate_limit_day); ?></p>
                    <div style="width: 100%; height: 20px; background: #ddd; border-radius: 3px; overflow: hidden;">
                        <div style="width: <?php echo esc_attr((string) min(100, (int) (($current_day / $rate_limit_day) * 100))); ?>%; height: 100%; background: #28a745;"></div>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e('Unlimited', 'multisite-network-email-manager'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="mnem-panel">
        <div style="display: flex; align-items: center; gap: 10px; cursor: pointer;" class="mnem-queue-summary-toggle" id="mnem-queue-summary-toggle">
            <span class="dashicons dashicons-arrow-down" id="mnem-queue-summary-icon"></span>
            <h2 style="margin: 0; display: inline;">Queue Summary</h2>
        </div>
        <div id="mnem-queue-summary-content" style="display: none; margin-top: 15px;">
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
                    <input type="hidden" name="redirect_page" value="mnem-logs" />
                    <?php submit_button('Process Queue Now', 'secondary', 'submit', false); ?>
                </form>
                <form method="post">
                    <?php wp_nonce_field('mnem_queue'); ?>
                    <input type="hidden" name="mnem_action" value="retry_failed_queue" />
                    <input type="hidden" name="redirect_page" value="mnem-logs" />
                    <?php submit_button('Retry Failed Items', 'secondary', 'submit', false); ?>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function() {
            var toggle = document.getElementById('mnem-queue-summary-toggle');
            var content = document.getElementById('mnem-queue-summary-content');
            var icon = document.getElementById('mnem-queue-summary-icon');
            
            if (!toggle || !content || !icon) {
                return;
            }
            
            // Check localStorage for saved state, default to closed (false)
            var isOpen = localStorage.getItem('mnem_queue_summary_open') === 'true';
            
            function updateUI() {
                if (isOpen) {
                    content.style.display = 'block';
                    icon.className = 'dashicons dashicons-arrow-up';
                } else {
                    content.style.display = 'none';
                    icon.className = 'dashicons dashicons-arrow-down';
                }
            }
            
            toggle.addEventListener('click', function() {
                isOpen = !isOpen;
                localStorage.setItem('mnem_queue_summary_open', isOpen);
                updateUI();
            });
            
            // Initialize with saved state
            updateUI();
        })();
    </script>

    <?php
    // Build base URL preserving current filters except paged.
    $base_url_args = array('page' => 'mnem-logs', 'tab' => 'email');
    if ($status_filter !== '') {
        $base_url_args['status_filter'] = $status_filter;
    }
    if ($search_email !== '') {
        $base_url_args['search_email'] = $search_email;
    }
    if ($search_subject !== '') {
        $base_url_args['search_subject'] = $search_subject;
    }
    if ($per_page !== 50) {
        $base_url_args['per_page'] = $per_page;
    }
    $base_url = network_admin_url('admin.php?' . http_build_query($base_url_args, '', '&'));

    // Build filter form URL (without paged/status_filter/per_page — the form provides them).
    $filter_form_url = network_admin_url('admin.php?page=mnem-logs&tab=email');
    ?>

    <!-- Bulk action POST form (hidden - controls reference it via form="mnem-bulk-form") -->
    <form method="post" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" id="mnem-bulk-form" class="mnem-queue-bulk-form">
        <?php wp_nonce_field('mnem_queue_item_delete'); ?>
        <input type="hidden" name="redirect_page" value="mnem-logs" />
        <input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>" />
        <input type="hidden" name="per_page" value="<?php echo esc_attr((string) $per_page); ?>" />
    </form>

    <!-- Filter GET form (hidden - controls reference it via form="mnem-filter-form") -->
    <form method="get" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" id="mnem-filter-form">
        <input type="hidden" name="page" value="mnem-logs" />
        <input type="hidden" name="tab" value="email" />
    </form>

    <div class="mnem-panel" style="padding: 12px 16px;">
        <!-- Main Filter Controls Row - All on one line -->
        <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: nowrap; margin-bottom: 15px;">
            <!-- Bulk Actions -->
            <div style="display: flex; flex-direction: column; gap: 3px; min-width: 200px;">
                <label for="mnem-bulk-action" style="font-size: 12px; font-weight: bold; margin: 0;"><strong><?php esc_html_e('Bulk Actions:', 'multisite-network-email-manager'); ?></strong></label>
                <div style="display: flex; gap: 6px;">
                    <select name="bulk_action" id="mnem-bulk-action" form="mnem-bulk-form"<?php echo empty($queue_items) ? ' disabled="disabled"' : ''; ?> style="padding: 5px; font-size: 13px;">
                        <option value="">-- Select Action --</option>
                        <option value="delete_pending">Delete All Pending</option>
                        <option value="delete_failed">Delete All Failed</option>
                        <option value="delete_selected">Delete Selected Items</option>
                        <option value="unsubscribe_delete_accounts"><?php esc_html_e('Unsubscribe & Delete Account', 'multisite-network-email-manager'); ?></option>
                    </select>
                    <button type="submit" form="mnem-bulk-form" class="button" style="padding: 5px 10px; font-size: 13px;"<?php echo empty($queue_items) ? ' disabled="disabled"' : ''; ?>>Apply</button>
                </div>
            </div>

            <!-- Status Filter -->
            <div style="display: flex; flex-direction: column; gap: 3px; min-width: 160px;">
                <label for="mnem-status-filter" style="font-size: 12px; font-weight: bold; margin: 0;"><strong><?php esc_html_e('Status:', 'multisite-network-email-manager'); ?></strong></label>
                <div style="display: flex; gap: 6px; align-items: center;">
                    <select name="status_filter" id="mnem-status-filter" form="mnem-filter-form" onchange="document.getElementById('mnem-filter-form').submit();" style="padding: 5px; font-size: 13px;">
                        <option value=""><?php esc_html_e('All Statuses', 'multisite-network-email-manager'); ?></option>
                        <?php foreach ($all_statuses as $s) : ?>
                            <option value="<?php echo esc_attr($s); ?>"<?php selected($status_filter, $s); ?>>
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $s))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($status_filter !== '') : ?>
                        <?php
                        $clear_filter_args = array(
                            'page' => 'mnem-logs',
                            'tab' => 'email',
                            'per_page' => $per_page,
                        );
                        if ($search_email !== '') {
                            $clear_filter_args['search_email'] = $search_email;
                        }
                        if ($search_subject !== '') {
                            $clear_filter_args['search_subject'] = $search_subject;
                        }
                        ?>
                        <a href="<?php echo esc_url(network_admin_url('admin.php?' . http_build_query($clear_filter_args, '', '&'))); ?>" class="button" style="padding: 5px 10px; font-size: 13px;">Clear</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Per Page -->
            <div style="display: flex; flex-direction: column; gap: 3px; min-width: 120px;">
                <label for="mnem-per-page" style="font-size: 12px; font-weight: bold; margin: 0;"><strong><?php esc_html_e('Per page:', 'multisite-network-email-manager'); ?></strong></label>
                <select name="per_page" id="mnem-per-page" form="mnem-filter-form" onchange="document.getElementById('mnem-filter-form').submit();" style="padding: 5px; font-size: 13px;">
                    <?php foreach (array(10, 20, 50, 100, 200, 500) as $opt) : ?>
                        <option value="<?php echo esc_attr((string) $opt); ?>"<?php selected($per_page, $opt); ?>><?php echo esc_html((string) $opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Email Search -->
            <div style="display: flex; flex-direction: column; gap: 3px; flex: 1; min-width: 150px;">
                <label for="mnem-search-email" style="font-size: 12px; font-weight: bold; margin: 0;"><strong><?php esc_html_e('Email Address:', 'multisite-network-email-manager'); ?></strong></label>
                <input
                    type="search"
                    id="mnem-search-email"
                    name="search_email"
                    form="mnem-filter-form"
                    value="<?php echo esc_attr($search_email); ?>"
                    placeholder="<?php echo esc_attr(esc_html__('Search email', 'multisite-network-email-manager')); ?>"
                    style="padding: 5px; font-size: 13px; width: 100%;"
                />
            </div>

            <!-- Subject Search -->
            <div style="display: flex; flex-direction: column; gap: 3px; flex: 1; min-width: 150px;">
                <label for="mnem-search-subject" style="font-size: 12px; font-weight: bold; margin: 0;"><strong><?php esc_html_e('Email Subject:', 'multisite-network-email-manager'); ?></strong></label>
                <input
                    type="search"
                    id="mnem-search-subject"
                    name="search_subject"
                    form="mnem-filter-form"
                    value="<?php echo esc_attr($search_subject); ?>"
                    placeholder="<?php echo esc_attr(esc_html__('Search subject', 'multisite-network-email-manager')); ?>"
                    style="padding: 5px; font-size: 13px; width: 100%;"
                />
            </div>

            <!-- Search Buttons -->
            <div style="display: flex; gap: 6px; align-items: flex-end;">
                <button type="submit" form="mnem-filter-form" class="button" style="padding: 5px 10px; font-size: 13px; white-space: nowrap;"><?php esc_html_e('Search', 'multisite-network-email-manager'); ?></button>
                <?php if ($search_email !== '' || $search_subject !== '') : ?>
                    <?php
                    $clear_search_args = array(
                        'page' => 'mnem-logs',
                        'tab' => 'email',
                    );
                    if ($status_filter !== '') {
                        $clear_search_args['status_filter'] = $status_filter;
                    }
                    if ($per_page !== 50) {
                        $clear_search_args['per_page'] = $per_page;
                    }
                    ?>
                    <a href="<?php echo esc_url(network_admin_url('admin.php?' . http_build_query($clear_search_args, '', '&'))); ?>" class="button" style="padding: 5px 10px; font-size: 13px; white-space: nowrap;">Clear</a>
                <?php endif; ?>
            </div>

            <!-- Refresh Controls -->
            <div style="display: flex; flex-direction: column; gap: 3px; min-width: 130px;">
                <label style="font-size: 12px; font-weight: bold; margin: 0;"><strong><?php esc_html_e('Statuses:', 'multisite-network-email-manager'); ?></strong></label>
                <button
                    type="button"
                    class="button mnem-refresh-all-statuses"
                    style="padding: 5px 10px; font-size: 13px; white-space: nowrap;"
                    <?php echo empty($queue_items) ? ' disabled="disabled"' : ''; ?>
                ><?php esc_html_e('Refresh Now', 'multisite-network-email-manager'); ?></button>
            </div>

            <!-- Record Count -->
            <div style="display: flex; flex-direction: column; gap: 3px; text-align: right; white-space: nowrap; min-width: 160px;">
                <span style="font-size: 12px; color: #666;">
                    <?php
                    $range_start = $total_filtered > 0 ? (($current_page - 1) * $per_page) + 1 : 0;
                    $range_end   = min($current_page * $per_page, $total_filtered);
                    printf(
                        esc_html__('%1$s-%2$s of %3$s', 'multisite-network-email-manager'),
                        esc_html(number_format($range_start)),
                        esc_html(number_format($range_end)),
                        esc_html(number_format($total_filtered))
                    );
                    ?>
                </span>
            </div>
        </div>

        <!-- Warning message below all controls -->
        <div style="margin-top: 12px;">
            <p class="description" style="color: #b32d2e; margin: 0;">
                <?php esc_html_e('Warning: "Unsubscribe & Delete Account" permanently deletes', 'multisite-network-email-manager'); ?><br />
                <?php esc_html_e('the network user accounts of the selected recipients.', 'multisite-network-email-manager'); ?>
            </p>
        </div>

        <div style="margin-top: 10px;">
            <span class="description mnem-refresh-indicator" id="mnem-refresh-indicator" data-refreshed-at=""><?php esc_html_e('Statuses have not been refreshed yet on this page.', 'multisite-network-email-manager'); ?></span>
        </div>

        <?php if ($search_email !== '' || $search_subject !== '') : ?>
            <p class="description" style="margin-top: 10px;">
                <strong><?php esc_html_e('Searching for:', 'multisite-network-email-manager'); ?></strong>
                <?php
                $search_fragments = array();
                if ($search_email !== '') {
                    $search_fragments[] = sprintf(esc_html__('Email: %s', 'multisite-network-email-manager'), esc_html($search_email));
                }
                if ($search_subject !== '') {
                    $search_fragments[] = sprintf(esc_html__('Subject: %s', 'multisite-network-email-manager'), esc_html($search_subject));
                }
                echo implode(' | ', $search_fragments);
                ?>
            </p>
        <?php endif; ?>
    </div>

    <script>
        (function () {
            var filterForm = document.getElementById('mnem-filter-form');
            var searchEmail = document.getElementById('mnem-search-email');
            var searchSubject = document.getElementById('mnem-search-subject');
            if (!filterForm || !searchEmail || !searchSubject) {
                return;
            }

            var debounceTimer = null;
            var submitSearch = function () {
                if (debounceTimer) {
                    clearTimeout(debounceTimer);
                }
                debounceTimer = setTimeout(function () {
                    filterForm.submit();
                }, 350);
            };

            searchEmail.addEventListener('input', submitSearch);
            searchSubject.addEventListener('input', submitSearch);
        })();
    </script>

        <?php if ($total_pages > 1) : ?>
        <div class="tablenav top" style="margin-bottom: 8px;">
            <div class="tablenav-pages" style="float: right;">
                <?php
                $page_url_base = network_admin_url('admin.php?' . http_build_query(
                    array_filter(array(
                        'page'          => 'mnem-logs',
                        'tab'           => 'email',
                        'status_filter' => $status_filter !== '' ? $status_filter : null,
                        'search_email'  => $search_email !== '' ? $search_email : null,
                        'search_subject'=> $search_subject !== '' ? $search_subject : null,
                        'per_page'      => $per_page !== 50 ? $per_page : null,
                    )),
                    '',
                    '&'
                ));
                ?>
                <a class="button<?php echo $current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=1'); ?>">&laquo; <?php esc_html_e('First', 'multisite-network-email-manager'); ?></a>
                <a class="button<?php echo $current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=' . max(1, $current_page - 1)); ?>">&lsaquo; <?php esc_html_e('Previous', 'multisite-network-email-manager'); ?></a>
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

        <table class="widefat striped" id="mnem-queue-table" data-auto-refresh="1">
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
                        <?php $is_processing = isset($item['status']) && $item['status'] === 'processing'; ?>
                        <?php $display_status = \MNEM\Queue::get_display_status($item); ?>
                        <?php $status_slug = isset($item['status']) ? strtolower((string) $item['status']) : 'failed'; ?>
                        <tr class="mnem-queue-row" data-queue-id="<?php echo esc_attr((string) $item['id']); ?>">
                            <th scope="row" class="check-column">
                                <input type="checkbox" class="mnem-queue-checkbox" name="queue_ids[]" form="mnem-bulk-form" value="<?php echo esc_attr((string) $item['id']); ?>" />
                            </th>
                            <td><?php echo esc_html((string) $item['id']); ?></td>
                            <td><?php echo esc_html((string) $item['blog_id']); ?></td>
                            <td><?php echo esc_html((string) $item['campaign_id']); ?></td>
                            <td><?php echo esc_html($item['recipient_email']); ?></td>
                            <td><?php echo esc_html($item['subject']); ?></td>
                            <td class="mnem-queue-status-cell"><span class="mnem-badge mnem-status-<?php echo esc_attr($status_slug); ?>"><?php echo esc_html($display_status); ?></span></td>
                            <td><?php echo esc_html((string) $item['attempts']); ?></td>
                            <td><?php echo esc_html($item['scheduled_at']); ?></td>
                            <td title="<?php echo esc_attr(!empty($item['opened']) ? 'First open: ' . (string) $item['opened'] : 'No opens yet'); ?>"><?php echo esc_html((string) (isset($item['opens_count']) ? $item['opens_count'] : 0)); ?></td>
                            <td title="<?php echo esc_attr(!empty($item['clicked']) ? 'First click: ' . (string) $item['clicked'] : 'No clicks yet'); ?>"><?php echo esc_html((string) (isset($item['clicks_count']) ? $item['clicks_count'] : 0)); ?></td>
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
                                <button
                                    type="button"
                                    class="button button-small mnem-refresh-queue-item"
                                    data-queue-id="<?php echo esc_attr((string) $item['id']); ?>"
                                ><?php esc_html_e('Refresh', 'multisite-network-email-manager'); ?></button>
                                <?php if ($item['status'] !== 'processing') : ?>
                                    <button
                                        type="button"
                                        class="button button-small mnem-send-queue-item-now"
                                        data-queue-id="<?php echo esc_attr((string) $item['id']); ?>"
                                        data-recipient="<?php echo esc_attr($item['recipient_email']); ?>"
                                        data-status="<?php echo esc_attr($item['status']); ?>"
                                    >Send Now!</button>
                                <?php endif; ?>
                                <button
                                    type="button"
                                    class="button button-small button-link-delete mnem-delete-queue-item"
                                    data-queue-id="<?php echo esc_attr((string) $item['id']); ?>"
                                    data-recipient="<?php echo esc_attr($item['recipient_email']); ?>"
                                    data-status="<?php echo esc_attr($item['status']); ?>"
                                    data-force-delete="<?php echo esc_attr($is_processing ? '1' : '0'); ?>"
                                ><?php echo esc_html($is_processing ? 'Force Delete' : 'Delete'); ?></button>
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
                <a class="button<?php echo $current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($page_url_base . '&paged=' . max(1, $current_page - 1)); ?>">&lsaquo; <?php esc_html_e('Previous', 'multisite-network-email-manager'); ?></a>
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
    <form method="post" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" id="mnem-single-queue-delete-form">
        <?php wp_nonce_field('mnem_queue_item_delete'); ?>
        <input type="hidden" name="mnem_action" value="delete_queue_item" />
        <input type="hidden" name="queue_id" value="" />
        <input type="hidden" name="redirect_page" value="mnem-logs" />
    </form>

    <p class="description">Retry backoff status: <?php echo esc_html($queue_stats['next_retry_at'] !== '' ? $queue_stats['next_retry_at'] . ' (attempt ' . (string) $queue_stats['next_retry_attempts'] . ')' : 'No pending retries'); ?></p>
