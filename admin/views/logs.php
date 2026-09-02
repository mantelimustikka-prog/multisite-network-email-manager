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
                    'failed'  => array('label' => __('Failed', 'multisite-network-email-manager'), 'color' => '#d63638'),
                );
                foreach ($sms_stat_items as $key => $meta) : ?>
                    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:12px 20px;min-width:100px;text-align:center;">
                        <div style="font-size:24px;font-weight:700;color:<?php echo esc_attr($meta['color']); ?>;">
                            <?php echo esc_html(number_format((int) $sms_stats[$key])); ?>
                        </div>
                        <div style="color:#50575e;font-size:13px;"><?php echo esc_html($meta['label']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Filters -->
            <form method="get" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" class="mnem-filters" style="margin-bottom:16px;">
                <input type="hidden" name="page" value="mnem-logs" />
                <input type="hidden" name="tab" value="sms" />

                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">

                    <!-- Status filter -->
                    <label for="mnem-sms-status"><strong><?php esc_html_e('Status:', 'multisite-network-email-manager'); ?></strong></label>
                    <select name="sms_status" id="mnem-sms-status" onchange="this.form.submit();">
                        <option value=""><?php esc_html_e('All Statuses', 'multisite-network-email-manager'); ?></option>
                        <?php foreach (array('pending', 'sent', 'failed', 'bounced') as $s) : ?>
                            <option value="<?php echo esc_attr($s); ?>"<?php selected($sms_status_filter, $s); ?>>
                                <?php echo esc_html(ucfirst($s)); ?>
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

                    <?php if ($sms_status_filter !== '' || $sms_campaign_filter !== '' || $sms_phone_search !== '' || $sms_date_from !== '' || $sms_date_to !== '') : ?>
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

            <!-- SMS Queue Table -->
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Campaign ID', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Campaign Name', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Phone Number', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Message', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Status', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Sent At', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Attempts', 'multisite-network-email-manager'); ?></th>
                        <th><?php esc_html_e('Actions', 'multisite-network-email-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sms_items)) : ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('No SMS log entries found.', 'multisite-network-email-manager'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php $sms_log_campaign_cache = array(); ?>
                        <?php foreach ($sms_items as $sms_item) : ?>
                            <?php $sms_status_slug = isset($sms_item['status']) ? strtolower((string) $sms_item['status']) : 'pending'; ?>
                            <tr>
                                <td><?php echo esc_html((string) $sms_item['sms_campaign_id']); ?></td>
                                <td><?php echo esc_html(!empty($sms_item['campaign_name']) ? $sms_item['campaign_name'] : '—'); ?></td>
                                <td><?php echo esc_html((string) $sms_item['phone_number']); ?></td>
                                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr((string) $sms_item['body']); ?>">
                                    <?php echo esc_html((string) $sms_item['body']); ?>
                                </td>
                                <td><span class="mnem-badge mnem-status-<?php echo esc_attr($sms_status_slug); ?>"><?php echo esc_html(ucfirst($sms_status_slug)); ?></span></td>
                                <td><?php echo esc_html(!empty($sms_item['sent_at']) ? $sms_item['sent_at'] : '—'); ?></td>
                                <td><?php echo esc_html((string) (int) $sms_item['attempts']); ?></td>
                                <td>
                                    <?php
                                    $sms_log_campaign_id = (int) $sms_item['sms_campaign_id'];
                                    if (!array_key_exists($sms_log_campaign_id, $sms_log_campaign_cache)) {
                                        $sms_log_campaign_cache[$sms_log_campaign_id] = $sms_log_campaign_id > 0
                                            ? \MNEM\SmsCampaigns::get($sms_log_campaign_id)
                                            : null;
                                    }
                                    $sms_log_campaign = $sms_log_campaign_cache[$sms_log_campaign_id];
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
