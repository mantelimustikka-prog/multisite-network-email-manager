<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1><?php esc_html_e('Invalid Phone Numbers', 'multisite-network-email-manager'); ?></h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <form method="get" action="<?php echo esc_url(network_admin_url('admin.php')); ?>" style="margin-bottom:15px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
        <input type="hidden" name="page" value="mnem-invalid-phone-numbers" />
        <div>
            <label for="mnem_invalid_list_id"><strong><?php esc_html_e('SMS List', 'multisite-network-email-manager'); ?></strong></label><br />
            <select id="mnem_invalid_list_id" name="list_id">
                <option value=""><?php esc_html_e('All Lists', 'multisite-network-email-manager'); ?></option>
                <?php foreach ((array) $lists as $list) : ?>
                    <option value="<?php echo esc_attr((string) $list['id']); ?>"<?php selected((string) $list_id, (string) $list['id']); ?>><?php echo esc_html((string) $list['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="mnem_invalid_status"><strong><?php esc_html_e('Status', 'multisite-network-email-manager'); ?></strong></label><br />
            <select id="mnem_invalid_status" name="status">
                <?php foreach (array('all' => __('All', 'multisite-network-email-manager'), 'blocked' => __('Blocked', 'multisite-network-email-manager'), 'not_blocked' => __('Not Blocked', 'multisite-network-email-manager')) as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>"<?php selected($status, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="mnem_invalid_reason"><strong><?php esc_html_e('Reason', 'multisite-network-email-manager'); ?></strong></label><br />
            <input type="text" id="mnem_invalid_reason" name="reason" value="<?php echo esc_attr($reason); ?>" class="regular-text" placeholder="format_invalid" />
        </div>
        <div>
            <label for="mnem_invalid_search"><strong><?php esc_html_e('Phone Search', 'multisite-network-email-manager'); ?></strong></label><br />
            <input type="search" id="mnem_invalid_search" name="search" value="<?php echo esc_attr($search); ?>" class="regular-text" />
        </div>
        <div>
            <label for="mnem_invalid_date_from"><strong><?php esc_html_e('From', 'multisite-network-email-manager'); ?></strong></label><br />
            <input type="date" id="mnem_invalid_date_from" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
        </div>
        <div>
            <label for="mnem_invalid_date_to"><strong><?php esc_html_e('To', 'multisite-network-email-manager'); ?></strong></label><br />
            <input type="date" id="mnem_invalid_date_to" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
        </div>
        <div>
            <label for="mnem_invalid_per_page"><strong><?php esc_html_e('Per page', 'multisite-network-email-manager'); ?></strong></label><br />
            <select id="mnem_invalid_per_page" name="per_page">
                <?php foreach (array(20, 50, 100) as $option) : ?>
                    <option value="<?php echo esc_attr((string) $option); ?>"<?php selected((int) $per_page, $option); ?>><?php echo esc_html((string) $option); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="button button-primary"><?php esc_html_e('Filter', 'multisite-network-email-manager'); ?></button>
        </div>
    </form>

    <form method="post" action="<?php echo esc_url(network_admin_url('admin.php?page=mnem-invalid-phone-numbers')); ?>">
        <?php wp_nonce_field('mnem_invalid_phone_numbers'); ?>
        <input type="hidden" name="id" id="mnem_invalid_single_id" value="" />
        <div style="margin-bottom:10px;display:flex;gap:10px;align-items:center;">
            <select name="mnem_action">
                <option value=""><?php esc_html_e('Bulk Actions', 'multisite-network-email-manager'); ?></option>
                <option value="block_phone"><?php esc_html_e('Block selected numbers', 'multisite-network-email-manager'); ?></option>
                <option value="unblock_phone"><?php esc_html_e('Unblock selected numbers', 'multisite-network-email-manager'); ?></option>
                <option value="remove_invalid_entry"><?php esc_html_e('Remove from log', 'multisite-network-email-manager'); ?></option>
                <option value="delete_user_with_phone"><?php esc_html_e('Delete user accounts', 'multisite-network-email-manager'); ?></option>
            </select>
            <button type="submit" class="button" onclick="return confirm('Apply the selected action to the selected invalid phone number records?');"><?php esc_html_e('Apply', 'multisite-network-email-manager'); ?></button>
            <span><?php echo esc_html(sprintf(__('Total: %d', 'multisite-network-email-manager'), (int) $total)); ?></span>
        </div>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:32px;"><input type="checkbox" onclick="var boxes=document.querySelectorAll('.mnem-invalid-phone-checkbox'); for (var i=0;i<boxes.length;i++) { boxes[i].checked=this.checked; }" /></th>
                    <th><?php esc_html_e('Phone Number', 'multisite-network-email-manager'); ?></th>
                    <th><?php esc_html_e('Reason', 'multisite-network-email-manager'); ?></th>
                    <th><?php esc_html_e('List ID', 'multisite-network-email-manager'); ?></th>
                    <th><?php esc_html_e('User Login', 'multisite-network-email-manager'); ?></th>
                    <th><?php esc_html_e('Status', 'multisite-network-email-manager'); ?></th>
                    <th><?php esc_html_e('Action Taken', 'multisite-network-email-manager'); ?></th>
                    <th><?php esc_html_e('Date Added', 'multisite-network-email-manager'); ?></th>
                    <th><?php esc_html_e('Admin', 'multisite-network-email-manager'); ?></th>
                    <th><?php esc_html_e('Actions', 'multisite-network-email-manager'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)) : ?>
                    <tr><td colspan="10"><?php esc_html_e('No invalid phone numbers found.', 'multisite-network-email-manager'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ((array) $items as $item) : ?>
                    <tr>
                        <td><input class="mnem-invalid-phone-checkbox" type="checkbox" name="invalid_ids[]" value="<?php echo esc_attr((string) $item['id']); ?>" /></td>
                        <td><?php echo esc_html((string) $item['phone_number']); ?></td>
                        <td><?php echo esc_html((string) $item['reason']); ?></td>
                        <td><?php echo !empty($item['list_id']) ? esc_html((string) $item['list_id']) : '—'; ?></td>
                        <td><?php echo !empty($item['user_login']) ? esc_html((string) $item['user_login']) : '—'; ?></td>
                        <td><?php echo esc_html(!empty($item['blocked']) ? 'blocked' : 'active'); ?></td>
                        <td><?php echo esc_html((string) $item['action_taken']); ?></td>
                        <td><?php echo esc_html((string) $item['created_at']); ?></td>
                        <td><?php echo !empty($item['admin_login']) ? esc_html((string) $item['admin_login']) : '—'; ?></td>
                        <td>
                            <button type="submit" class="button" name="mnem_action" value="<?php echo !empty($item['blocked']) ? 'unblock_phone' : 'block_phone'; ?>" onclick="document.getElementById('mnem_invalid_single_id').value='<?php echo esc_js((string) $item['id']); ?>';"><?php echo esc_html(!empty($item['blocked']) ? 'Unblock' : 'Block'); ?></button>
                            <button type="submit" class="button delete" name="mnem_action" value="remove_invalid_entry" onclick="document.getElementById('mnem_invalid_single_id').value='<?php echo esc_js((string) $item['id']); ?>';"><?php esc_html_e('Remove', 'multisite-network-email-manager'); ?></button>
                            <?php if (!empty($item['user_id'])) : ?>
                                <button type="submit" class="button delete" name="mnem_action" value="delete_user_with_phone" onclick="document.getElementById('mnem_invalid_single_id').value='<?php echo esc_js((string) $item['id']); ?>'; return confirm('Delete the associated network user account permanently?');"><?php esc_html_e('Delete User', 'multisite-network-email-manager'); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </form>

    <?php
    $base_args = array(
        'page' => 'mnem-invalid-phone-numbers',
        'status' => $status,
        'reason' => $reason,
        'search' => $search,
        'date_from' => $date_from,
        'date_to' => $date_to,
        'per_page' => $per_page,
    );
    if ($list_id !== null) {
        $base_args['list_id'] = $list_id;
    }
    ?>
    <div style="margin-top:10px;display:flex;gap:8px;align-items:center;">
        <?php if ($page_number > 1) : ?>
            <?php $prev_args = $base_args; $prev_args['paged'] = $page_number - 1; ?>
            <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?' . http_build_query($prev_args, '', '&'))); ?>"><?php esc_html_e('Previous', 'multisite-network-email-manager'); ?></a>
        <?php else : ?>
            <span class="button disabled"><?php esc_html_e('Previous', 'multisite-network-email-manager'); ?></span>
        <?php endif; ?>
        <span><?php echo esc_html((string) $page_number); ?> / <?php echo esc_html((string) $total_pages); ?></span>
        <?php if ($page_number < $total_pages) : ?>
            <?php $next_args = $base_args; $next_args['paged'] = $page_number + 1; ?>
            <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?' . http_build_query($next_args, '', '&'))); ?>"><?php esc_html_e('Next', 'multisite-network-email-manager'); ?></a>
        <?php else : ?>
            <span class="button disabled"><?php esc_html_e('Next', 'multisite-network-email-manager'); ?></span>
        <?php endif; ?>
    </div>
</div>
