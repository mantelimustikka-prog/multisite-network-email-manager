<?php
?><div class="wrap">
    <h1>Campaigns</h1>
    <?php $campaigns = $view_data['campaigns']; ?>
    <?php if (! empty($notice_code)) : ?>
        <div class="notice notice-info"><p><?php echo esc_html($notice_message ? $notice_message : $notice_code); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(network_admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="mnem_save_campaign" />
        <?php wp_nonce_field('mnem_save_campaign'); ?>
        <table class="form-table" role="presentation">
            <tr><th scope="row">Name</th><td><input type="text" class="regular-text" name="name" /></td></tr>
            <tr><th scope="row">Subject</th><td><input type="text" class="regular-text" name="subject" /></td></tr>
            <tr><th scope="row">Content</th><td><textarea class="large-text" rows="6" name="content"></textarea></td></tr>
        </table>
        <?php submit_button('Create Draft Campaign'); ?>
    </form>

    <h2>Existing Campaigns</h2>
    <table class="widefat striped">
        <thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Transition</th></tr></thead>
        <tbody>
        <?php if (empty($campaigns)) : ?>
            <tr><td colspan="4">No campaigns yet.</td></tr>
        <?php else : ?>
            <?php foreach ($campaigns as $campaign) : ?>
                <tr>
                    <td><?php echo esc_html((string) $campaign['id']); ?></td>
                    <td><?php echo esc_html($campaign['name']); ?></td>
                    <td><?php echo esc_html($campaign['status']); ?></td>
                    <td>
                        <form method="post" action="<?php echo esc_url(network_admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="mnem_transition_campaign" />
                            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $campaign['id']); ?>" />
                            <?php wp_nonce_field('mnem_transition_campaign'); ?>
                            <select name="status">
                                <option value="scheduled">scheduled</option>
                                <option value="sending">sending</option>
                                <option value="completed">completed</option>
                                <option value="cancelled">cancelled</option>
                            </select>
                            <?php submit_button('Update', 'secondary small', 'submit', false); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
