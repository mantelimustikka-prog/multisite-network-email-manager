<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1><?php esc_html_e('Logs', 'multisite-network-email-manager'); ?></h1>

    <nav class="nav-tab-wrapper" style="margin-bottom: 0;">
        <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-logs&tab=email')); ?>"
           class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('Email Logs', 'multisite-network-email-manager'); ?>
        </a>
        <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-logs&tab=sms')); ?>"
           class="nav-tab <?php echo $active_tab === 'sms' ? 'nav-tab-active' : ''; ?>">
            <?php esc_html_e('SMS Logs', 'multisite-network-email-manager'); ?>
        </a>
    </nav>

    <?php if ($active_tab === 'email') : ?>

        <?php include __DIR__ . '/queue.php'; ?>

    <?php endif; ?>

    <?php if ($active_tab === 'sms') : ?>

        <?php if ($notice_message !== '') : ?>
            <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
        <?php endif; ?>

        <div class="mnem-panel mnem-panel-wide">
            <h2><?php esc_html_e('SMS Campaign Delivery Status', 'multisite-network-email-manager'); ?></h2>

            <!-- Stats Summary -->
            <div class="mnem-stats-row" style="display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px;">
                <?php
                $sms_stat_items = array(
                    'total'   => array('label' => __('Total', 'multisite-network-email-manager'), 'color' => '#2271b1'),
                    'pending' => array('label' => __('Pending', 'multisite-network-email-manager'), 'color' => '#996800'),
                    'sent'    => array('label' => __('Sent', 'multisite-network-email-manager'), 'color' => '#00a32a'),
                    'delivered' => array('label' => __('Delivered', 'multisite-network-email-manager'), 'color' => '#0073aa'),
                    'failed'  => array('label' => __('Failed', 'multisite-network-email-manager'), 'color' => '#d63638'),
                    'rejected' => array('label' => __('Rejected', 'multisite-network-email-manager'), 'color' => '#dba617'),
                );
                foreach ($sms_stat_items as $key => $meta) :
                    $sms_stat_status = $key === 'total' ? '' : $key;
                    $sms_stat_url    = add_query_arg(
                        array(
                            'page'                => 'mnem-logs',
                            'tab'                 => 'sms',
                            'sms_status'          => $sms_stat_status,
                            'sms_provider_status' => $sms_provider_status_filter,
                            'sms_campaign'        => $sms_campaign_filter,
                            'sms_date_from'       => $sms_date_from,
                            'sms_date_to'         => $sms_date_to,
                            'sms_phone'           => $sms_phone_search,
                            'sms_per_page'        => $sms_per_page,
                        ),
                        network_admin_url('admin.php')
                    );
                    ?>
                    <a href="<?php echo esc_url($sms_stat_url); ?>" style="text-decoration:none;color:inherit;display:block;">
                        <div class="mnem-sms-stat-card" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px 20px;min-width:100px;text-align:center;cursor:pointer;transition:box-shadow 0.15s ease,border-color 0.15s ease;<?php echo $sms_status_filter === $sms_stat_status ? 'border-color:' . esc_attr($meta['color']) . ';box-shadow:0 0 0 1px ' . esc_attr($meta['color']) . ';' : ''; ?>"
                             onmouseover="this.style.boxShadow='0 1px 4px rgba(0,0,0,0.15)';"
                             onmouseout="this.style.boxShadow='<?php echo $sms_status_filter === $sms_stat_status ? '0 0 0 1px ' . esc_attr($meta['color']) : 'none'; ?>';">
                            <div style="font-size:24px;font-weight:700;color:<?php echo esc_attr($meta['color']); ?>;">
                                <?php echo esc_html(number_format((int) $sms_stats[$key])); ?>
                            </div>
                            <div style="color:#50575e;font-size:13px;"><?php echo esc_html($meta['label']); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <p class="description" style="margin-bottom:16px;">
                <?php esc_html_e('Failed = invalid number or the handset could not be reached. Rejected = the number is valid but the provider account owner or the mobile user blocked the message; rejected items are excluded from retry operations and should be removed from future campaigns.', 'multisite-network-email-manager'); ?>
            </p>

            <!-- Filters -->
            <form method="get" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" class="mnem-filters" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="mnem-logs" />
                <input type="hidden" name="tab" value="sms" />

                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">

                    <!-- Status filter -->
                    <label for="mnem-sms-status"><strong><?php esc_html_e('Status:', 'multisite-network-email-manager'); ?></strong></label>
                    <select name="sms_status" id="mnem-sms-status" onchange="this.form.submit();">
                        <option value=""><?php esc_html_e('All Statuses', 'multisite-network-email-manager'); ?></option>
                        <?php foreach (array('pending', 'sent', 'delivered', 'bounce', 'failed', 'rejected') as $s) : ?>
                            <option value="<?php echo esc_attr($s); ?>"<?php selected($sms_status_filter, $s); ?>>
                                <?php echo esc_html(ucfirst($s)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="mnem-sms-provider-status"><strong><?php esc_html_e('Provider Status:', 'multisite-network-email-manager'); ?></strong></label>
                    <select name="sms_provider_status" id="mnem-sms-provider-status" onchange="this.form.submit();">
                        <option value=""><?php esc_html_e('All Provider Statuses', 'multisite-network-email-manager'); ?></option>
                        <?php foreach ($sms_provider_statuses as $provider_status) : ?>
                            <option value="<?php echo esc_attr($provider_status); ?>"<?php selected($sms_provider_status_filter, $provider_status); ?>>
                                <?php echo esc_html(ucfirst($provider_status)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Campaign filter -->
                    <?php if (!empty($sms_campaigns_list)) : ?>
                        <label for="mnem-sms-campaign"><strong><?php esc_html_e('Campaign:', 'multisite-network-email-manager'); ?></strong></label>
                        <select name="sms_campaign" id="mnem-sms-campaign" onchange="this.form.submit();">
                            <option value=""><?php esc_html_e('All Campaigns', 'multisite-network-email-manager'); ?></option>
                            <?php foreach ($sms_campaigns_list as $camp) : ?>
                                <option value="<?php echo esc_attr((string) $camp['id']); ?>"<?php selected($sms_campaign_filter, (string) $camp['id']); ?>>
                                    <?php echo esc_html($camp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>

                    <!-- Date range -->
                    <label for="mnem-sms-date-from"><strong><?php esc_html_e('From:', 'multisite-network-email-manager'); ?></strong></label>
                    <input type="date" name="sms_date_from" id="mnem-sms-date-from" value="<?php echo esc_attr($sms_date_from); ?>" />

                    <label for="mnem-sms-date-to"><strong><?php esc_html_e('To:', 'multisite-network-email-manager'); ?></strong></label>
                    <input type="date" name="sms_date_to" id="mnem-sms-date-to" value="<?php echo esc_attr($sms_date_to); ?>" />

                    <!-- Phone search -->
                    <label for="mnem-sms-phone"><strong><?php esc_html_e('Phone:', 'multisite-network-email-manager'); ?></strong></label>
                    <input
                        type="search"
                        name="sms_phone"
                        id="mnem-sms-phone"
                        value="<?php echo esc_attr($sms_phone_search); ?>"
                        placeholder="<?php echo esc_attr(esc_html__('Search phone number', 'multisite-network-email-manager')); ?>"
                    />

                    <!-- Per page -->
                    <label for="mnem-sms-per-page"><strong><?php esc_html_e('Per page:', 'multisite-network-email-manager'); ?></strong></label>
                    <select name="sms_per_page" id="mnem-sms-per-page" onchange="this.form.submit();">
                        <?php foreach (array(10, 20, 50, 100, 200, 500) as $opt) : ?>
                            <option value="<?php echo esc_attr((string) $opt); ?>"<?php selected($sms_per_page, $opt); ?>><?php echo esc_html((string) $opt); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php submit_button(__('Filter', 'multisite-network-email-manager'), 'secondary', 'submit', false); ?>

                    <?php if ($sms_status_filter !== '' || $sms_provider_status_filter !== '' || $sms_campaign_filter !== '' || $sms_phone_search !== '' || $sms_date_from !== '' || $sms_date_to !== '') : ?>
                        <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-logs&tab=sms')); ?>" class="button">
                            <?php esc_html_e('Clear Filters', 'multisite-network-email-manager'); ?>
                        </a>
                    <?php endif; ?>

                    <span class="description">
                        <?php
                        $sms_range_start = $sms_total_filtered > 0 ? (($sms_current_page - 1) * $sms_per_page) + 1 : 0;
                        $sms_range_end   = min($sms_current_page * $sms_per_page, $sms_total_filtered);
                        printf(
                            esc_html__('Showing %1$s-%2$s of %3$s records', 'multisite-network-email-manager'),
                            esc_html(number_format($sms_range_start)),
                            esc_html(number_format($sms_range_end)),
                            esc_html(number_format($sms_total_filtered))
                        );
                        ?>
                    </span>

                </div>
            </form>

            <?php
            $sms_page_url_base = network_admin_url('admin.php?' . http_build_query(
                array_filter(array(
                    'page'         => 'mnem-logs',
                    'tab'          => 'sms',
                    'sms_status'   => $sms_status_filter !== '' ? $sms_status_filter : null,
                    'sms_provider_status' => $sms_provider_status_filter !== '' ? $sms_provider_status_filter : null,
                    'sms_campaign' => $sms_campaign_filter !== '' ? $sms_campaign_filter : null,
                    'sms_phone'    => $sms_phone_search !== '' ? $sms_phone_search : null,
                    'sms_date_from'=> $sms_date_from !== '' ? $sms_date_from : null,
                    'sms_date_to'  => $sms_date_to !== '' ? $sms_date_to : null,
                    'sms_per_page' => $sms_per_page !== 50 ? $sms_per_page : null,
                )),
                '',
                '&'
            ));
            ?>

            <!-- Pagination top -->
            <?php if ($sms_total_pages > 1) : ?>
            <div class="tablenav top" style="margin-bottom: 8px;">
                <div class="tablenav-pages" style="float: right;">
                    <a class="button<?php echo $sms_current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($sms_page_url_base . '&sms_paged=1'); ?>">&laquo; <?php esc_html_e('First', 'multisite-network-email-manager'); ?></a>
                    <a class="button<?php echo $sms_current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($sms_page_url_base . '&sms_paged=' . max(1, $sms_current_page - 1)); ?>">&lsaquo; <?php esc_html_e('Prev', 'multisite-network-email-manager'); ?></a>
                    <span class="paging-input" style="padding: 0 8px;">
                        <?php
                        printf(
                            esc_html__('Page %1$s of %2$s', 'multisite-network-email-manager'),
                            esc_html((string) $sms_current_page),
                            esc_html((string) $sms_total_pages)
                        );
                        ?>
                    </span>
                    <a class="button<?php echo $sms_current_page >= $sms_total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url($sms_page_url_base . '&sms_paged=' . min($sms_total_pages, $sms_current_page + 1)); ?>"><?php esc_html_e('Next', 'multisite-network-email-manager'); ?> &rsaquo;</a>
                    <a class="button<?php echo $sms_current_page >= $sms_total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url($sms_page_url_base . '&sms_paged=' . $sms_total_pages); ?>"><?php esc_html_e('Last', 'multisite-network-email-manager'); ?> &raquo;</a>
                </div>
                <br class="clear" />
            </div>
            <?php endif; ?>

            <!-- Bulk actions toolbar -->
            <div class="mnem-sms-bulk-toolbar" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;padding:10px 12px;background:#fff;border:1px solid #c3c4c7;border-radius:4px;">
                <label style="display:flex;align-items:center;gap:4px;">
                    <input type="checkbox" id="mnem-sms-select-all-toolbar" />
                    <?php esc_html_e('Select All', 'multisite-network-email-manager'); ?>
                </label>
                <a href="#" id="mnem-sms-clear-selection"><?php esc_html_e('Clear Selection', 'multisite-network-email-manager'); ?></a>
                <span id="mnem-sms-selected-count" style="font-weight:600;">
                    <?php esc_html_e('0 items selected', 'multisite-network-email-manager'); ?>
                </span>

                <span style="border-left:1px solid #dcdcde;height:24px;"></span>

                <select id="mnem-sms-bulk-action-select" disabled>
                    <option value=""><?php esc_html_e('Bulk Actions', 'multisite-network-email-manager'); ?></option>
                    <option value="unsubscribe"
                        data-label-template="<?php echo esc_attr__('Unsubscribe Selected (%d available)', 'multisite-network-email-manager'); ?>">
                        <?php echo esc_html(sprintf(__('Unsubscribe Selected (%d available)', 'multisite-network-email-manager'), 0)); ?>
                    </option>
                    <option value="delete_users"
                        data-label-template="<?php echo esc_attr__('Delete Selected Users (%d available)', 'multisite-network-email-manager'); ?>"
                        data-affects-users="1">
                        <?php echo esc_html(sprintf(__('Delete Selected Users (%d available)', 'multisite-network-email-manager'), 0)); ?>
                    </option>
                    <option value="both"
                        data-label-template="<?php echo esc_attr__('Unsubscribe & Delete Selected (%d available)', 'multisite-network-email-manager'); ?>"
                        data-affects-users="1">
                        <?php echo esc_html(sprintf(__('Unsubscribe & Delete Selected (%d available)', 'multisite-network-email-manager'), 0)); ?>
                    </option>
                    <option value="refresh_status"
                        data-label-template="<?php echo esc_attr__('Refresh Status for Selected (%d available)', 'multisite-network-email-manager'); ?>">
                        <?php echo esc_html(sprintf(__('Refresh Status for Selected (%d available)', 'multisite-network-email-manager'), 0)); ?>
                    </option>
                </select>

                <label style="display:flex;align-items:center;gap:4px;">
                    <input type="checkbox" id="mnem-sms-bulk-dry-run" />
                    <?php esc_html_e('Dry run (preview only)', 'multisite-network-email-manager'); ?>
                </label>

                <button type="button" class="button button-primary" id="mnem-sms-bulk-apply" disabled>
                    <?php esc_html_e('Apply', 'multisite-network-email-manager'); ?>
                </button>

                <span id="mnem-sms-bulk-warning" style="display:none;color:#996800;">
                    <span class="dashicons dashicons-warning"></span>
                    <?php esc_html_e('Warning: this action will delete WordPress user accounts.', 'multisite-network-email-manager'); ?>
                </span>

                <span id="mnem-sms-bulk-progress" style="display:none;color:#2271b1;"></span>
            </div>
            <div id="mnem-sms-bulk-result" style="display:none;margin-bottom:12px;padding:10px 12px;border-radius:4px;"></div>

            <!-- SMS Queue Table -->
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th class="check-column" style="width:2.2em;">
                            <input type="checkbox" id="mnem-sms-select-all-header"
                                   aria-label="<?php echo esc_attr__('Select all SMS entries', 'multisite-network-email-manager'); ?>" />
                        </th>
                        <th><?php esc_html_e('Campaign ID', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Campaign Name', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Phone Number', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Message', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Status', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Provider Status', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Status Last Checked', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Sent At', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Attempts', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Actions', 'multisite-network-email-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sms_items)) : ?>
                        <tr>
                            <td colspan="11"><?php esc_html_e('No SMS log entries found.', 'multisite-network-email-manager'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php $sms_log_campaign_cache = array(); ?>
                        <?php foreach ($sms_items as $sms_item) : ?>
                            <?php $sms_status_slug = isset($sms_item['status']) ? strtolower((string) $sms_item['status']) : 'pending'; ?>
                            <?php
                            $sms_row_campaign_id_for_cb = (int) $sms_item['sms_campaign_id'];
                            if (!array_key_exists($sms_row_campaign_id_for_cb, $sms_log_campaign_cache)) {
                                $sms_log_campaign_cache[$sms_row_campaign_id_for_cb] = $sms_row_campaign_id_for_cb > 0
                                    ? \MNEM\SmsCampaigns::get($sms_row_campaign_id_for_cb)
                                    : null;
                            }
                            $sms_log_campaign = $sms_log_campaign_cache[$sms_row_campaign_id_for_cb];
                            $sms_log_list_id  = is_array($sms_log_campaign) ? (int) $sms_log_campaign['sms_list_id'] : 0;
                            $sms_log_phone    = (string) $sms_item['phone_number'];

                            $sms_log_subscriber       = $sms_log_list_id > 0
                                ? \MNEM\SmsSubscriberLists::get_subscriber_by_phone_and_list($sms_log_list_id, $sms_log_phone)
                                : null;
                            $sms_log_is_unsubscribed  = is_array($sms_log_subscriber) && $sms_log_subscriber['subscription_status'] === 'unsubscribed';
                            $sms_log_has_wp_user      = is_array($sms_log_subscriber) && (int) $sms_log_subscriber['user_id'] > 0 && function_exists('get_user_by');
                            if ($sms_log_has_wp_user) {
                                $sms_log_wp_user = get_user_by('ID', (int) $sms_log_subscriber['user_id']);
                                $sms_log_has_wp_user = !empty($sms_log_wp_user);
                            }
                            ?>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox"
                                           class="mnem-sms-row-checkbox"
                                           value="<?php echo esc_attr((string) $sms_item['id']); ?>"
                                           data-unsubscribed="<?php echo $sms_log_is_unsubscribed ? '1' : '0'; ?>"
                                           data-has-user="<?php echo $sms_log_has_wp_user ? '1' : '0'; ?>"
                                           aria-label="<?php echo esc_attr__('Select this SMS entry', 'multisite-network-email-manager'); ?>" />
                                </td>
                                <td><?php echo esc_html((string) $sms_item['sms_campaign_id']); ?></td>
                                <td><?php echo esc_html(!empty($sms_item['campaign_name']) ? $sms_item['campaign_name'] : '—'); ?></td>
                                <td><?php echo esc_html((string) $sms_item['phone_number']); ?></td>
                                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr((string) $sms_item['body']); ?>">
                                    <?php echo esc_html((string) $sms_item['body']); ?>
                                </td>
                                <td class="mnem-sms-queue-status"><span class="mnem-badge mnem-status-<?php echo esc_attr($sms_status_slug); ?>"><?php echo esc_html(ucfirst($sms_status_slug)); ?></span></td>
                                <?php
                                $sms_provider_status = isset($sms_item['provider_status']) ? (string) $sms_item['provider_status'] : '';
                                $sms_mapped_provider_status = $sms_provider_status !== ''
                                    ? \MNEM\SmsProviderStatusMap::map((string) $sms_item['provider_type'], $sms_provider_status)
                                    : '';
                                $sms_status_out_of_sync = $sms_mapped_provider_status !== '' && $sms_mapped_provider_status !== $sms_status_slug;
                                $sms_sync_error = isset($sms_item['last_sync_error']) ? (string) $sms_item['last_sync_error'] : '';
                                $sms_provider_status_label = $sms_provider_status !== ''
                                    ? \MNEM\SmsProviderStatusMap::get_provider_display_name((string) $sms_item['provider_type'], $sms_provider_status)
                                    : '';
                                if ($sms_provider_status_label === '') {
                                    $sms_provider_status_label = $sms_provider_status;
                                }
                                ?>
                                <td class="mnem-sms-provider-status" title="<?php echo esc_attr($sms_sync_error !== '' ? $sms_sync_error : $sms_provider_status); ?>">
                                    <?php echo esc_html($sms_provider_status !== '' ? $sms_provider_status_label : '—'); ?>
                                    <?php if ($sms_status_out_of_sync) : ?>
                                        <span class="dashicons dashicons-warning" style="color:#d63638;" title="<?php echo esc_attr__('Queue and provider statuses differ.', 'multisite-network-email-manager'); ?>"></span>
                                    <?php elseif ($sms_provider_status !== '') : ?>
                                        <span aria-label="<?php echo esc_attr__('Synced', 'multisite-network-email-manager'); ?>">✓</span>
                                    <?php endif; ?>
                                    <?php if ($sms_sync_error !== '') : ?>
                                        <span class="dashicons dashicons-info-outline" style="color:#d63638;"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="mnem-sms-provider-checked"><?php echo esc_html(!empty($sms_item['provider_status_checked_at']) ? $sms_item['provider_status_checked_at'] : '—'); ?></td>
                                <td><?php echo esc_html(!empty($sms_item['sent_at']) ? $sms_item['sent_at'] : '—'); ?></td>
                                <td><?php echo esc_html((string) (int) $sms_item['attempts']); ?></td>
                                <td>
                                    <?php
                                    $sms_log_unsubscribe_disabled = !is_array($sms_log_subscriber) || $sms_log_is_unsubscribed;
                                    $sms_log_delete_user_disabled = !$sms_log_has_wp_user;
                                    $sms_log_both_disabled        = $sms_log_unsubscribe_disabled || $sms_log_delete_user_disabled;

                                    if (!is_array($sms_log_subscriber)) {
                                        $sms_log_message = __('Subscriber not found for this phone number.', 'multisite-network-email-manager');
                                    } elseif ($sms_log_is_unsubscribed && !$sms_log_has_wp_user) {
                                        $sms_log_message = __('All actions completed for this subscriber.', 'multisite-network-email-manager');
                                    } elseif ($sms_log_is_unsubscribed) {
                                        $sms_log_message = __('Already unsubscribed from list.', 'multisite-network-email-manager');
                                    } elseif (!$sms_log_has_wp_user) {
                                        $sms_log_message = __('No WordPress user associated with this subscriber.', 'multisite-network-email-manager');
                                    } else {
                                        $sms_log_message = '';
                                    }
                                    ?>
                                    <div class="mnem-sms-log-actions"
                                         style="display:flex;flex-direction:column;gap:4px;"
                                         data-queue-id="<?php echo esc_attr((string) $sms_item['id']); ?>"
                                         data-phone="<?php echo esc_attr($sms_log_phone); ?>"
                                         data-list-id="<?php echo esc_attr((string) $sms_log_list_id); ?>">
                                        <?php if ($sms_provider_status === '') : ?>
                                            <button type="button" class="button mnem-sms-refresh-status">
                                                <?php esc_html_e('Refresh Status', 'multisite-network-email-manager'); ?>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button"
                                                class="button button-primary mnem-sms-log-action"
                                                data-mnem-action="unsubscribe"
                                                <?php disabled($sms_log_unsubscribe_disabled, true); ?>
                                                <?php if ($sms_log_unsubscribe_disabled) : ?>title="<?php echo esc_attr__('Already unsubscribed from this list.', 'multisite-network-email-manager'); ?>"<?php endif; ?>>
                                            <?php esc_html_e('Unsubscribe', 'multisite-network-email-manager'); ?>
                                        </button>
                                        <button type="button"
                                                class="button mnem-sms-log-action"
                                                data-mnem-action="delete_user"
                                                <?php disabled($sms_log_delete_user_disabled, true); ?>
                                                <?php if ($sms_log_delete_user_disabled) : ?>title="<?php echo esc_attr__('No WordPress user associated with this subscriber.', 'multisite-network-email-manager'); ?>"<?php endif; ?>>
                                            <?php esc_html_e('Delete User', 'multisite-network-email-manager'); ?>
                                        </button>
                                        <button type="button"
                                                class="button button-link-delete mnem-sms-log-action"
                                                data-mnem-action="both"
                                                <?php disabled($sms_log_both_disabled, true); ?>
                                                <?php if ($sms_log_both_disabled) : ?>title="<?php echo esc_attr__('Already unsubscribed or no WordPress user to delete.', 'multisite-network-email-manager'); ?>"<?php endif; ?>>
                                            <?php esc_html_e('Unsubscribe & Delete', 'multisite-network-email-manager'); ?>
                                        </button>
                                        <?php if ($sms_log_message !== '') : ?>
                                            <span class="description" style="color:#787c82;"><?php echo esc_html($sms_log_message); ?></span>
                                        <?php endif; ?>
                                        <div class="mnem-sms-log-notice" style="display:none;"></div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>


            <!-- Pagination bottom -->
            <?php if ($sms_total_pages > 1) : ?>
            <div class="tablenav bottom" style="margin-top: 8px;">
                <div class="tablenav-pages" style="float: right;">
                    <a class="button<?php echo $sms_current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($sms_page_url_base . '&sms_paged=1'); ?>">&laquo; <?php esc_html_e('First', 'multisite-network-email-manager'); ?></a>
                    <a class="button<?php echo $sms_current_page <= 1 ? ' disabled' : ''; ?>" href="<?php echo esc_url($sms_page_url_base . '&sms_paged=' . max(1, $sms_current_page - 1)); ?>">&lsaquo; <?php esc_html_e('Prev', 'multisite-network-email-manager'); ?></a>
                    <span class="paging-input" style="padding: 0 8px;">
                        <?php
                        printf(
                            esc_html__('Page %1$s of %2$s', 'multisite-network-email-manager'),
                            esc_html((string) $sms_current_page),
                            esc_html((string) $sms_total_pages)
                        );
                        ?>
                    </span>
                    <a class="button<?php echo $sms_current_page >= $sms_total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url($sms_page_url_base . '&sms_paged=' . min($sms_total_pages, $sms_current_page + 1)); ?>"><?php esc_html_e('Next', 'multisite-network-email-manager'); ?> &rsaquo;</a>
                    <a class="button<?php echo $sms_current_page >= $sms_total_pages ? ' disabled' : ''; ?>" href="<?php echo esc_url($sms_page_url_base . '&sms_paged=' . $sms_total_pages); ?>"><?php esc_html_e('Last', 'multisite-network-email-manager'); ?> &raquo;</a>
                </div>
                <br class="clear" />
            </div>
            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>
