<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1>Suppression List</h1>

    <?php if (!empty($notice)) : ?>
        <div class="notice notice-info"><p><?php echo esc_html($notice); ?></p></div>
    <?php endif; ?>

    <form method="post" action="" style="margin-bottom: 20px;">
        <?php wp_nonce_field('mnem_suppression'); ?>
        <input type="hidden" name="mnem_action" value="add_suppression" />
        <input type="hidden" name="site_id" value="<?php echo esc_attr((string) $site_id); ?>" />
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row"><label for="mnem-suppression-email">Email</label></th>
                    <td><input name="email" id="mnem-suppression-email" type="email" class="regular-text" value="" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="mnem-suppression-reason">Reason</label></th>
                    <td><input name="reason" id="mnem-suppression-reason" type="text" class="regular-text" value="" /></td>
                </tr>
            </tbody>
        </table>
        <?php submit_button('Add Suppression'); ?>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Site ID</th>
                <th>Email</th>
                <th>Reason</th>
                <th>Created At</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($suppression_entries)) : ?>
                <tr>
                    <td colspan="5">No suppressed emails found.</td>
                </tr>
            <?php else : ?>
                <?php foreach ($suppression_entries as $entry) : ?>
                    <tr>
                        <td><?php echo esc_html((string) $entry['site_id']); ?></td>
                        <td><?php echo esc_html($entry['email']); ?></td>
                        <td><?php echo esc_html($entry['reason']); ?></td>
                        <td><?php echo esc_html($entry['created_at']); ?></td>
                        <td>
                            <form method="post" action="">
                                <?php wp_nonce_field('mnem_suppression'); ?>
                                <input type="hidden" name="mnem_action" value="remove_suppression" />
                                <input type="hidden" name="site_id" value="<?php echo esc_attr((string) $entry['site_id']); ?>" />
                                <input type="hidden" name="email" value="<?php echo esc_attr($entry['email']); ?>" />
                                <?php submit_button('Remove', 'delete small', '', false); ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
