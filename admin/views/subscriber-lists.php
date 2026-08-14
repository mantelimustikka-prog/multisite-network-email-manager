<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1>Subscriber Lists</h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($alert_message)) : ?>
        <script>window.alert(<?php echo wp_json_encode($alert_message); ?>);</script>
    <?php endif; ?>

    <div class="mnem-grid">
        <div class="mnem-panel mnem-panel-wide">
            <h2><?php echo $active_list ? 'Edit List' : 'Create New List'; ?></h2>
            <form method="post">
                <?php wp_nonce_field('mnem_subscriber_lists'); ?>
                <input type="hidden" name="mnem_action" value="subscriber_save_list" />
                <?php if ($active_list) : ?>
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                <?php endif; ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="mnem_list_name">List name</label></th>
                        <td><input id="mnem_list_name" name="name" type="text" class="regular-text" required value="<?php echo esc_attr($active_list ? (string) $active_list['name'] : ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label for="mnem_list_description">Description</label></th>
                        <td><textarea id="mnem_list_description" name="description" rows="4" class="large-text"><?php echo esc_textarea($active_list ? (string) $active_list['description'] : ''); ?></textarea></td>
                    </tr>
                </table>
                <?php submit_button($active_list ? 'Save List' : 'Create List'); ?>
            </form>
            <?php if ($active_list) : ?>
                <form method="post" onsubmit="return confirm('Delete this subscriber list?');">
                    <?php wp_nonce_field('mnem_subscriber_lists'); ?>
                    <input type="hidden" name="mnem_action" value="subscriber_delete_list" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <?php submit_button('Delete List', 'delete', 'submit', false); ?>
                </form>
            <?php endif; ?>
        </div>

        <div class="mnem-panel mnem-panel-wide">
            <h2>All Lists</h2>
            <table class="widefat striped">
                <thead><tr><th>Name</th><th>Count</th><th>Created</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ((array) $lists as $list) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $list['name']); ?></td>
                        <td><?php echo esc_html((string) \MNEM\SubscriberLists::get_list_subscribers_count((int) $list['id'])); ?></td>
                        <td><?php echo esc_html((string) $list['created_at']); ?></td>
                        <td>
                            <a class="button" href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-subscriber-lists&list_id=' . (int) $list['id'])); ?>">Manage</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($active_list) : ?>
            <div class="mnem-panel mnem-panel-wide">
                <h2>Subscribed Users</h2>
                <form method="post">
                    <?php wp_nonce_field('mnem_subscriber_lists'); ?>
                    <input type="hidden" name="mnem_action" value="subscriber_add_user" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <input type="text" name="user_identifier" class="regular-text" placeholder="User ID or username" />
                    <?php submit_button('Add Subscriber', 'secondary', 'submit', false); ?>
                </form>
                <form method="post" enctype="multipart/form-data" style="margin-top:10px;">
                    <?php wp_nonce_field('mnem_subscriber_lists'); ?>
                    <input type="hidden" name="mnem_action" value="subscriber_import_csv" />
                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                    <input type="file" name="csv_file" accept=".csv,text/csv,text/plain" />
                    <textarea name="csv_content" rows="4" class="large-text" placeholder="user_id or username per line"></textarea>
                    <?php submit_button('Upload CSV', 'secondary', 'submit', false); ?>
                </form>
                <table class="widefat striped">
                    <thead><tr><th>Login</th><th>Email</th><th>Subscribed At</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ((array) $subscribers as $subscriber) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $subscriber['user_login']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['user_email']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['subscribed_at']); ?></td>
                            <td>
                                <form method="post" class="mnem-inline-form">
                                    <?php wp_nonce_field('mnem_subscriber_lists'); ?>
                                    <input type="hidden" name="mnem_action" value="subscriber_remove_user" />
                                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $subscriber['user_id']); ?>" />
                                    <?php submit_button('Remove', 'delete', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mnem-panel mnem-panel-wide">
                <h2>Unsubscribed Users</h2>
                <table class="widefat striped">
                    <thead><tr><th>Login</th><th>Email</th><th>Unsubscribed At</th><th>Reason</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ((array) $unsubscribed as $subscriber) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $subscriber['user_login']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['user_email']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['unsubscribed_at']); ?></td>
                            <td><?php echo esc_html((string) $subscriber['unsubscribed_reason']); ?></td>
                            <td>
                                <form method="post" class="mnem-inline-form">
                                    <?php wp_nonce_field('mnem_subscriber_lists'); ?>
                                    <input type="hidden" name="mnem_action" value="subscriber_restore_user" />
                                    <input type="hidden" name="list_id" value="<?php echo esc_attr((string) $active_list['id']); ?>" />
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $subscriber['user_id']); ?>" />
                                    <?php submit_button('Restore', 'secondary', 'submit', false); ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
