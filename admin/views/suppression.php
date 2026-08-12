<?php
?><div class="wrap">
    <h1>Suppression List</h1>
    <?php $entries = $view_data['entries']; ?>
    <?php if (! empty($notice_code)) : ?>
        <div class="notice notice-info"><p><?php echo esc_html($notice_message ? $notice_message : $notice_code); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(network_admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="mnem_add_suppression" />
        <?php wp_nonce_field('mnem_add_suppression'); ?>
        <table class="form-table" role="presentation">
            <tr><th scope="row">Email</th><td><input type="email" class="regular-text" name="email" required /></td></tr>
            <tr><th scope="row">Reason</th><td><input type="text" class="regular-text" name="reason" /></td></tr>
        </table>
        <?php submit_button('Add Suppression'); ?>
    </form>

    <h2>Current entries</h2>
    <table class="widefat striped">
        <thead><tr><th>Email</th><th>Reason</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (empty($entries)) : ?>
            <tr><td colspan="3">No suppressed recipients.</td></tr>
        <?php else : ?>
            <?php foreach ($entries as $entry) : ?>
                <tr>
                    <td><?php echo esc_html($entry['email']); ?></td>
                    <td><?php echo esc_html($entry['reason']); ?></td>
                    <td>
                        <form method="post" action="<?php echo esc_url(network_admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="mnem_delete_suppression" />
                            <input type="hidden" name="email" value="<?php echo esc_attr($entry['email']); ?>" />
                            <?php wp_nonce_field('mnem_delete_suppression'); ?>
                            <?php submit_button('Remove', 'delete small', 'submit', false); ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
