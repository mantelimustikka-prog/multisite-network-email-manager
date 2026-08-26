<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1>SMS Subscriber Lists</h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($alert_message)) : ?>
        <script>window.alert(<?php echo wp_json_encode($alert_message); ?>);</script>
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
                <form method="post" onsubmit="return confirm('Delete this SMS subscriber list?');">
                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                    <input type="hidden" name="mnem_action" value="sms_subscriber_delete_list" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
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
                    <input id="mnem-sms-subscriber-search" type="search" name="subscriber_search" value="<?php echo esc_attr($subscriber_search); ?>" placeholder="Search username or phone number" />
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
                <h2>Subscribed Users</h2>
                <div style="margin-bottom: 15px;">
                    <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-sms-subscriber-lists-bulk-add&list_id=' . (int) $active_list['id'])); ?>" class="button button-primary">
                        <?php esc_html_e('+ Add from Network Users', 'multisite-network-email-manager'); ?>
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
                    <textarea name="csv_content" rows="4" class="large-text" placeholder="user_id or username per line (optionally user_id:phone_number)"></textarea>
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
                    <thead><tr><th>Login</th><th>Phone Number</th><th>Subscribed At</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ((array) $subscribers as $subscriber) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $subscriber['user_login']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['phone_number']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['subscribed_at']); ?></td>
                            <td>
                                <form method="post" class="mnem-inline-form">
                                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                                    <input type="hidden" name="mnem_action" value="sms_subscriber_remove_user" />
                                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $subscriber['user_id']); ?>" />
                                    <?php submit_button('Remove', 'delete', 'submit', false); ?>
                                </form>
                                <button
                                    type="button"
                                    class="button"
                                    style="margin-left:6px;"
                                    data-user-id="<?php echo esc_attr((string) $subscriber['user_id']); ?>"
                                    data-user-login="<?php echo esc_attr((string) $subscriber['user_login']); ?>"
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
                <h2>Unsubscribed Users</h2>
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
                    <thead><tr><th>Login</th><th>Phone Number</th><th>Unsubscribed At</th><th>Reason</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ((array) $unsubscribed as $subscriber) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $subscriber['user_login']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['phone_number']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['unsubscribed_at']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['unsubscribed_reason']); ?></td>
                            <td>
                                <form method="post" class="mnem-inline-form">
                                    <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                                    <input type="hidden" name="mnem_action" value="sms_subscriber_restore_user" />
                                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $subscriber['user_id']); ?>" />
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
                    <h3>Unsubscribe User from SMS List</h3>
                    <p id="mnem-sms-unsubscribe-user-label"></p>
                    <form method="post" action="<?php echo esc_url(network_admin_url('admin.php?page=mnem-sms-subscriber-lists&list_id=' . (int) $active_list['id'])); ?>">
                        <?php wp_nonce_field('mnem_sms_subscriber_lists'); ?>
                        <input type="hidden" name="mnem_action" value="sms_subscriber_unsubscribe_user" />
                        <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                        <input type="hidden" name="user_id" id="mnem-sms-unsubscribe-user-id" value="" />
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
                        var userField = document.getElementById('mnem-sms-unsubscribe-user-id');
                        var userLabel = document.getElementById('mnem-sms-unsubscribe-user-label');
                        if (!modal || !userField || !userLabel || !button) {
                            return;
                        }
                        userField.value = button.getAttribute('data-user-id') || '';
                        var userLogin = button.getAttribute('data-user-login') || '';
                        userLabel.textContent = userLogin ? ('User: ' + userLogin) : '';
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
