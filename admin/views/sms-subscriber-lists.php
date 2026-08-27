<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1>SMS Subscriber Lists</h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($alert_message)) : ?>
        <div class="notice notice-error"><p><?php echo esc_html($alert_message); ?></p></div>
    <?php endif; ?>

    <div class="mnem-grid">
        <?php if (!$active_list) : ?>
            <div class="mnem-panel mnem-panel-wide">
                <h2>All SMS Lists</h2>
                <table class="widefat striped">
                    <thead><tr><th>Name</th><th>Count</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ((array) $lists as $list) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $list['name']); ?></td>
                            <td><?php echo esc_html((string) \MNEM\SmsSubscriberLists::get_list_subscribers_count((int) $list['id'])); ?></td>
                            <td><?php echo esc_html((string) $list['created_at']); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-sms-subscriber-lists&list_id=' . (int) $list['id'])); ?>">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="mnem-panel mnem-panel-wide">
            <h2><?php echo $active_list ? 'Edit SMS List' : 'Create New SMS List'; ?></h2>
            <?php if ($active_list) : ?>
                <p>
                    <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-sms-subscriber-lists')); ?>">Back to All SMS Lists</a>
                </p>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                <input type="hidden" name="mnem_action" value="sms_subscriber_save_list" />
                <?php if ($active_list) : ?>
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                <?php endif; ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="mnem_sms_list_name">List name</label></th>
                        <td><input id="mnem_sms_list_name" name="name" type="text" class="regular-text" required value="<?php echo esc_attr($active_list ? (string) $active_list['name'] : ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="mnem_sms_list_description">Description</label></th>
                        <td><textarea id="mnem_sms_list_description" name="description" rows="4" class="large-text"><?php echo esc_textarea($active_list ? (string) $active_list['description'] : ''); ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button($active_list ? 'Save List' : 'Create List'); ?>
            </form>
            <?php if ($active_list) : ?>
                <?php
                $delete_counts = isset($delete_impact['counts']) && is_array($delete_impact['counts']) ? $delete_impact['counts'] : array();
                $delete_total_related = isset($delete_impact['total_related']) ? (int) $delete_impact['total_related'] : 0;
                $delete_confirmation_message = sprintf(
                    'Delete this SMS subscriber list and cascade delete %d related records?',
                    $delete_total_related
                );
                ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php
                        echo esc_html(sprintf(
                            'Deleting this SMS list will also remove %1$d subscribers, %2$d invalid phone records, %3$d logs, %4$d queue items, and %5$d mapping rows.',
                            isset($delete_counts['subscribers']) ? (int) $delete_counts['subscribers'] : 0,
                            isset($delete_counts['invalid_phones']) ? (int) $delete_counts['invalid_phones'] : 0,
                            isset($delete_counts['logs']) ? (int) $delete_counts['logs'] : 0,
                            isset($delete_counts['queue_items']) ? (int) $delete_counts['queue_items'] : 0,
                            isset($delete_counts['mapping_rows']) ? (int) $delete_counts['mapping_rows'] : 0
                        ));
                        ?>
                    </p>
                    <?php if (!empty($delete_impact['notes'])) : ?>
                        <p><?php echo esc_html(implode(' ', array_map('strval', (array) $delete_impact['notes']))); ?></p>
                    <?php endif; ?>
                </div>
                <form method="post" onsubmit="return confirm(<?php echo wp_json_encode($delete_confirmation_message); ?>);">
                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                    <input type="hidden" name="mnem_action" value="sms_subscriber_delete_list" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <?php if (!empty($delete_requires_confirmation)) : ?>
                        <p>
                            <label>
                                <input type="checkbox" name="confirm_cascade_delete" value="1" required />
                                <?php echo esc_html(sprintf('I understand this will permanently delete %d related records.', $delete_total_related)); ?>
                            </label>
                        </p>
                    <?php else : ?>
                        <input type="hidden" name="confirm_cascade_delete" value="1" />
                    <?php endif; ?>
                    <?php submit_button('Delete List', 'delete', 'submit', false); ?>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($active_list) : ?>
            <?php
            $subscriber_filter_base_args = array(
                'page' => 'mnem-sms-subscriber-lists',
                'list_id' => (int) $active_list['id'],
            );
            if ($subscriber_search !== '') {
                $subscriber_filter_base_args['subscriber_search'] = $subscriber_search;
            }
            if ((int) $subscriber_per_page !== 100) {
                $subscriber_filter_base_args['subscriber_per_page'] = (int) $subscriber_per_page;
            }

            $subscribed_range_start = $subscribed_total > 0 ? (($subscribed_current_page - 1) * $subscriber_per_page) + 1 : 0;
            $subscribed_range_end = min($subscribed_current_page * $subscriber_per_page, $subscribed_total);
            $unsubscribed_range_start = $unsubscribed_total > 0 ? (($unsubscribed_current_page - 1) * $subscriber_per_page) + 1 : 0;
            $unsubscribed_range_end = min($unsubscribed_current_page * $subscriber_per_page, $unsubscribed_total);
            ?>
            <div class="mnem-panel mnem-panel-wide">
                <h2>Search &amp; Pagination</h2>
                <form method="get" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <input type="hidden" name="page" value="mnem-sms-subscriber-lists" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <input type="hidden" name="subscribed_paged" value="1" />
                    <input type="hidden" name="unsubscribed_paged" value="1" />
                    <label for="mnem-sms-subscriber-search"><strong>Search:</strong></label>
                    <input id="mnem-sms-subscriber-search" type="search" name="subscriber_search" value="<?php echo esc_attr($subscriber_search); ?>" placeholder="Search username, standalone name, or phone number" />
                    <label for="mnem-sms-subscriber-per-page"><strong>Per page:</strong></label>
                    <select id="mnem-sms-subscriber-per-page" name="subscriber_per_page" onchange="this.form.submit();">
                        <?php foreach (array(100, 500, 1000) as $option) : ?>
                            <option value="<?php echo esc_attr((string) $option); ?>"<?php selected($subscriber_per_page, $option); ?>><?php echo esc_html((string) $option); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button">Search</button>
                    <?php if ($subscriber_search !== '') : ?>
                        <?php
                        $clear_search_args = array(
                            'page' => 'mnem-sms-subscriber-lists',
                            'list_id' => (int) $active_list['id'],
                            'subscribed_paged' => 1,
                            'unsubscribed_paged' => 1,
                        );
                        if ((int) $subscriber_per_page !== 100) {
                            $clear_search_args['subscriber_per_page'] = (int) $subscriber_per_page;
                        }
                        ?>
                        <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?' . http_build_query($clear_search_args, '', '&'))); ?>">Clear Search</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="mnem-panel mnem-panel-wide">
                <h2>Add Standalone Subscriber</h2>
                <p class="description">Add phone numbers with custom names without requiring WordPress user accounts.</p>
                <form method="post" action="<?php echo esc_url(network_admin_url('admin.php?page=mnem-sms-subscriber-lists&list_id=' . (int) $active_list['id'])); ?>">
                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                    <input type="hidden" name="mnem_action" value="sms_subscriber_add_standalone" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <table class="form-table" role="presentation" style="width:auto;">
                        <tr>
                            <th><label for="mnem_sms_subscriber_name">Subscriber Name / Identifier</label></th>
                            <td><input id="mnem_sms_subscriber_name" type="text" name="subscriber_name" class="regular-text" placeholder="e.g., John Doe, Business Name, External Contact" required /></td>
                        </tr>
                        <tr>
                            <th><label for="mnem_sms_standalone_phone_number">Phone Number</label></th>
                            <td>
                                <input id="mnem_sms_standalone_phone_number" type="text" name="phone_number" class="regular-text" placeholder="+1234567890 or local format" required />
                                <p class="description">Enter phone number in E.164 format (+1234567890) or your local format.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Add Standalone Subscriber', 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div class="mnem-panel mnem-panel-wide">
                <h2>Subscribed Users &amp; Standalone Subscribers</h2>
                <div style="margin-bottom: 15px;">
                    <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-sms-subscriber-lists-bulk-add&list_id=' . (int) $active_list['id'])); ?>" class="button button-primary">
                        <?php esc_html_e('+ Add from Network Users', 'multisite-network-email-manager'); ?>
                    </a>
                    <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-invalid-phone-numbers&list_id=' . (int) $active_list['id'])); ?>" class="button" style="margin-left: 8px;">
                        <?php esc_html_e('Review Invalid Numbers', 'multisite-network-email-manager'); ?>
                    </a>
                </div>
                <form method="post" action="<?php echo esc_url(network_admin_url('admin.php?page=mnem-sms-subscriber-lists&list_id=' . (int) $active_list['id'])); ?>">
                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                    <input type="hidden" name="mnem_action" value="sms_subscriber_add_user" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <table class="form-table" role="presentation" style="width:auto;">
                        <tr>
                            <th><label for="mnem_sms_user_identifier">User ID or username</label></th>
                            <td><input id="mnem_sms_user_identifier" type="text" name="user_identifier" class="regular-text" placeholder="User ID or username" /></td>
                        </tr>
                        <tr>
                            <th><label for="mnem_sms_phone_number">Phone number</label></th>
                            <td>
                                <input id="mnem_sms_phone_number" type="text" name="phone_number" class="regular-text" placeholder="Leave blank to auto-detect from user meta" />
                                <p class="description">If left blank, the plugin will try to read <code>phone_number</code>, <code>phone</code>, or <code>mobile</code> from user meta.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Add Subscriber', 'secondary', 'submit', false); ?>
                </form>
                <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                    <input type="hidden" name="mnem_action" value="sms_subscriber_import_csv" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <input type="file" name="csv_file" accept=".csv,text/csv,text/plain" />
                    <textarea name="csv_content" rows="4" class="large-text" placeholder="Format options:
user_id or username (will use user's phone from meta)
user_id:phone_number or username:phone_number
name:phone_number (standalone subscriber, e.g., John Doe:+1234567890)"></textarea>
                    <?php submit_button('Upload CSV', 'secondary', 'submit', false); ?>
                </form>
                <p class="description">
                    <?php
                    printf(
                        esc_html__('Showing %1$s-%2$s of %3$s records', 'multisite-network-email-manager'),
                        esc_html(number_format($subscribed_range_start)),
                        esc_html(number_format($subscribed_range_end)),
                        esc_html(number_format($subscribed_total))
                    );
                    ?>
                </p>
                <table class="widefat striped">
                    <thead><tr><th>Login/Name</th><th>Type</th><th>Phone Number</th><th>Subscribed At</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ((array) $subscribers as $subscriber) : ?>
                        <?php $is_standalone = isset($subscriber['subscriber_type']) && $subscriber['subscriber_type'] === 'standalone'; ?>
                        <tr>
                            <td><?php echo esc_html(isset($subscriber['display_name']) ? (string) $subscriber['display_name'] : (string) $subscriber['user_login']); ?></td>
                            <td>
                                <?php if ($is_standalone) : ?>
                                    <span class="tag">Standalone</span>
                                <?php else : ?>
                                    <span class="tag">User</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) $subscriber['phone_number']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['subscribed_at']); ?></td>
                            <td>
                                <form method="post" class="mnem-inline-form">
                                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                                    <input type="hidden" name="mnem_action" value="<?php echo esc_attr($is_standalone ? 'sms_subscriber_remove_standalone' : 'sms_subscriber_remove_user'); ?>" />
                                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                                    <?php if ($is_standalone) : ?>
                                        <input type="hidden" name="phone_number" value="<?php echo esc_attr((string) $subscriber['phone_number']); ?>" />
                                    <?php else : ?>
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $subscriber['user_id']); ?>" />
                                    <?php endif; ?>
                                    <?php submit_button('Remove', 'delete', 'submit', false); ?>
                                </form>
                                <button
                                    type="button"
                                    class="button"
                                    style="margin-left:6px;"
                                    data-subscriber-type="<?php echo esc_attr($is_standalone ? 'standalone' : 'user'); ?>"
                                    data-user-id="<?php echo esc_attr((string) $subscriber['user_id']); ?>"
                                    data-user-login="<?php echo esc_attr((string) (isset($subscriber['display_name']) ? $subscriber['display_name'] : $subscriber['user_login'])); ?>"
                                    data-phone-number="<?php echo esc_attr((string) $subscriber['phone_number']); ?>"
                                    onclick="mnemOpenSmsUnsubscribeModal(this);"
                                >Unsubscribe</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                $subscribed_prev_args = $subscriber_filter_base_args;
                $subscribed_prev_args['subscribed_paged'] = max(1, $subscribed_current_page - 1);
                $subscribed_prev_args['unsubscribed_paged'] = $unsubscribed_current_page;
                $subscribed_next_args = $subscriber_filter_base_args;
                $subscribed_next_args['subscribed_paged'] = min($subscribed_total_pages, $subscribed_current_page + 1);
                $subscribed_next_args['unsubscribed_paged'] = $unsubscribed_current_page;
                ?>
                <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
                    <?php if ($subscribed_current_page > 1) : ?>
                        <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?' . http_build_query($subscribed_prev_args, '', '&'))); ?>">Previous</a>
                    <?php else : ?>
                        <span class="button disabled">Previous</span>
                    <?php endif; ?>
                    <span><?php echo esc_html((string) $subscribed_current_page); ?> / <?php echo esc_html((string) $subscribed_total_pages); ?></span>
                    <?php if ($subscribed_current_page < $subscribed_total_pages) : ?>
                        <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?' . http_build_query($subscribed_next_args, '', '&'))); ?>">Next</a>
                    <?php else : ?>
                        <span class="button disabled">Next</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mnem-panel mnem-panel-wide">
                <h2>Unsubscribed Users &amp; Standalone Subscribers</h2>
                <p class="description">
                    <?php
                    printf(
                        esc_html__('Showing %1$s-%2$s of %3$s records', 'multisite-network-email-manager'),
                        esc_html(number_format($unsubscribed_range_start)),
                        esc_html(number_format($unsubscribed_range_end)),
                        esc_html(number_format($unsubscribed_total))
                    );
                    ?>
                </p>
                <table class="widefat striped">
                    <thead><tr><th>Login/Name</th><th>Type</th><th>Phone Number</th><th>Unsubscribed At</th><th>Reason</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ((array) $unsubscribed as $subscriber) : ?>
                        <?php $is_standalone = isset($subscriber['subscriber_type']) && $subscriber['subscriber_type'] === 'standalone'; ?>
                        <tr>
                            <td><?php echo esc_html(isset($subscriber['display_name']) ? (string) $subscriber['display_name'] : (string) $subscriber['user_login']); ?></td>
                            <td>
                                <?php if ($is_standalone) : ?>
                                    <span class="tag">Standalone</span>
                                <?php else : ?>
                                    <span class="tag">User</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html((string) $subscriber['phone_number']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['unsubscribed_at']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['unsubscribed_reason']); ?></td>
                            <td>
                                <form method="post" class="mnem-inline-form">
                                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                                    <input type="hidden" name="mnem_action" value="<?php echo esc_attr($is_standalone ? 'sms_subscriber_restore_standalone' : 'sms_subscriber_restore_user'); ?>" />
                                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                                    <?php if ($is_standalone) : ?>
                                        <input type="hidden" name="phone_number" value="<?php echo esc_attr((string) $subscriber['phone_number']); ?>" />
                                    <?php else : ?>
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $subscriber['user_id']); ?>" />
                                    <?php endif; ?>
                                    <?php submit_button('Restore', 'secondary', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php
                $unsubscribed_prev_args = $subscriber_filter_base_args;
                $unsubscribed_prev_args['subscribed_paged'] = $subscribed_current_page;
                $unsubscribed_prev_args['unsubscribed_paged'] = max(1, $unsubscribed_current_page - 1);
                $unsubscribed_next_args = $subscriber_filter_base_args;
                $unsubscribed_next_args['subscribed_paged'] = $subscribed_current_page;
                $unsubscribed_next_args['unsubscribed_paged'] = min($unsubscribed_total_pages, $unsubscribed_current_page + 1);
                ?>
                <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
                    <?php if ($unsubscribed_current_page > 1) : ?>
                        <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?' . http_build_query($unsubscribed_prev_args, '', '&'))); ?>">Previous</a>
                    <?php else : ?>
                        <span class="button disabled">Previous</span>
                    <?php endif; ?>
                    <span><?php echo esc_html((string) $unsubscribed_current_page); ?> / <?php echo esc_html((string) $unsubscribed_total_pages); ?></span>
                    <?php if ($unsubscribed_current_page < $unsubscribed_total_pages) : ?>
                        <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?' . http_build_query($unsubscribed_next_args, '', '&'))); ?>">Next</a>
                    <?php else : ?>
                        <span class="button disabled">Next</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mnem-panel mnem-panel-wide">
                <h2>Export</h2>
                <form method="post" action="<?php echo esc_url(network_admin_url('admin-ajax.php')); ?>">
                    <input type="hidden" name="action" value="mnem_sms_export_csv" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                    <?php submit_button('Export Subscribers to CSV', 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div id="mnem-sms-unsubscribe-modal" style="display:none;position:fixed;z-index:9999;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);">
                <div style="background:#fff;max-width:500px;margin:120px auto;padding:20px;">
                    <h3>Unsubscribe Subscriber from SMS List</h3>
                    <p id="mnem-sms-unsubscribe-user-label"></p>
                    <form method="post" action="<?php echo esc_url(network_admin_url('admin.php?page=mnem-sms-subscriber-lists&list_id=' . (int) $active_list['id'])); ?>">
                        <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                        <input type="hidden" name="mnem_action" value="sms_subscriber_unsubscribe_user" />
                        <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                        <input type="hidden" name="subscriber_type" id="mnem-sms-unsubscribe-subscriber-type" value="user" />
                        <input type="hidden" name="user_id" id="mnem-sms-unsubscribe-user-id" value="" />
                        <input type="hidden" name="phone_number" id="mnem-sms-unsubscribe-phone-number" value="" />
                        <label for="mnem-sms-unsubscribe-reason">Reason (optional)</label>
                        <textarea id="mnem-sms-unsubscribe-reason" name="unsubscribe_reason" rows="3" class="large-text" placeholder="Unsubscribed by admin"></textarea>
                        <div style="margin-top:10px;display:flex;gap:8px;">
                            <button type="submit" class="button button-primary">Confirm Unsubscribe</button>
                            <button type="button" class="button" onclick="mnemCloseSmsUnsubscribeModal();">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                if (typeof mnemOpenSmsUnsubscribeModal === 'undefined') {
                    function mnemOpenSmsUnsubscribeModal(button) {
                        var modal = document.getElementById('mnem-sms-unsubscribe-modal');
                        var subscriberTypeField = document.getElementById('mnem-sms-unsubscribe-subscriber-type');
                        var userField = document.getElementById('mnem-sms-unsubscribe-user-id');
                        var phoneField = document.getElementById('mnem-sms-unsubscribe-phone-number');
                        var userLabel = document.getElementById('mnem-sms-unsubscribe-user-label');
                        if (!modal || !subscriberTypeField || !userField || !phoneField || !userLabel || !button) {
                            return;
                        }
                        var subscriberType = button.getAttribute('data-subscriber-type') || 'user';
                        subscriberTypeField.value = subscriberType;
                        userField.value = button.getAttribute('data-user-id') || '';
                        phoneField.value = button.getAttribute('data-phone-number') || '';
                        var userLogin = button.getAttribute('data-user-login') || '';
                        if (subscriberType === 'standalone') {
                            userLabel.textContent = userLogin ? ('Standalone subscriber: ' + userLogin) : '';
                        } else {
                            userLabel.textContent = userLogin ? ('User: ' + userLogin) : '';
                        }
                        modal.style.display = 'block';
                    }
                }
                if (typeof mnemCloseSmsUnsubscribeModal === 'undefined') {
                    function mnemCloseSmsUnsubscribeModal() {
                        var modal = document.getElementById('mnem-sms-unsubscribe-modal');
                        if (!modal) {
                            return;
                        }
                        modal.style.display = 'none';
                    }
                }
            </script>
        <?php endif; ?>
    </div>
</div>
