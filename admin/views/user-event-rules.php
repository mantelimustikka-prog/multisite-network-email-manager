<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1>User Event Rules</h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <?php if ($dry_run_matches > 0) : ?>
        <div class="notice notice-success"><p><?php echo esc_html((string) $dry_run_matches); ?> users match the dry-run rule.</p></div>
    <?php endif; ?>

    <?php if (!empty($preview_campaign)) : ?>
        <div class="mnem-panel">
            <h2>Preview Campaign</h2>
            <p><strong><?php echo esc_html($preview_campaign['subject']); ?></strong></p>
            <p><?php echo esc_html($preview_campaign['body']); ?></p>
        </div>
    <?php endif; ?>

    <div class="mnem-panel">
        <h2>Active Rules</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Campaign</th>
                    <th>Role</th>
                    <th>Site</th>
                    <th>Enabled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rules)) : ?>
                    <tr><td colspan="6">No rules configured.</td></tr>
                <?php else : ?>
                    <?php foreach ($rules as $rule) : ?>
                        <tr>
                            <td><?php echo esc_html($rule['event_type']); ?></td>
                            <td><?php echo esc_html((string) $rule['campaign_id']); ?></td>
                            <td><?php echo esc_html(isset($rule['conditions']['role']) ? $rule['conditions']['role'] : 'any'); ?></td>
                            <td><?php echo esc_html(isset($rule['conditions']['site_id']) ? (string) $rule['conditions']['site_id'] : 'any'); ?></td>
                            <td><?php echo esc_html(!empty($rule['enabled']) ? 'Yes' : 'No'); ?></td>
                            <td>
                                <form method="post" class="mnem-inline-form">
                                    <?php wp_nonce_field('mnem_user_event_rules'); ?>
                                    <input type="hidden" name="mnem_action" value="delete_user_event_rule" />
                                    <input type="hidden" name="rule_id" value="<?php echo esc_attr($rule['id']); ?>" />
                                    <?php submit_button('Delete', 'delete', 'submit', false); ?>
                                </form>
                                <a class="button button-secondary" href="<?php echo esc_attr(network_admin_url('admin.php?page=mnem-user-event-rules&edit_rule=' . rawurlencode((string) $rule['id']))); ?>">Edit</a>
                                <a class="button button-secondary" href="<?php echo esc_attr(network_admin_url('admin.php?page=mnem-user-event-rules&preview_campaign=' . (int) $rule['campaign_id'])); ?>">Preview</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mnem-panel">
        <h2>Add Rule</h2>
        <form method="post">
            <?php wp_nonce_field('mnem_user_event_rules'); ?>
            <input type="hidden" name="mnem_action" value="save_user_event_rule" />
            <input type="hidden" name="rule_id" value="<?php echo esc_attr(!empty($edit_rule['id']) ? (string) $edit_rule['id'] : ''); ?>" />
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="mnem-rule-event">Event Type</label></th>
                        <td>
                            <select id="mnem-rule-event" name="event_type">
                                <option value="user_register" <?php selected(!empty($edit_rule['event_type']) ? $edit_rule['event_type'] : 'user_register', 'user_register'); ?>>user_register</option>
                                <option value="user_delete" <?php selected(!empty($edit_rule['event_type']) ? $edit_rule['event_type'] : 'user_register', 'user_delete'); ?>>user_delete</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mnem-rule-campaign">Campaign</label></th>
                        <td>
                            <select id="mnem-rule-campaign" name="campaign_id">
                                <?php foreach ($eligible_campaigns as $campaign) : ?>
                                    <option value="<?php echo esc_attr((string) $campaign['id']); ?>" <?php selected(!empty($edit_rule['campaign_id']) ? (int) $edit_rule['campaign_id'] : 0, (int) $campaign['id']); ?>><?php echo esc_html($campaign['name'] . ' (' . $campaign['status'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mnem-rule-role">Role</label></th>
                        <td>
                            <select id="mnem-rule-role" name="role">
                                <option value="any" <?php selected(!empty($edit_rule['conditions']['role']) ? $edit_rule['conditions']['role'] : 'any', 'any'); ?>>any</option>
                                <option value="subscriber" <?php selected(!empty($edit_rule['conditions']['role']) ? $edit_rule['conditions']['role'] : 'any', 'subscriber'); ?>>subscriber</option>
                                <option value="contributor" <?php selected(!empty($edit_rule['conditions']['role']) ? $edit_rule['conditions']['role'] : 'any', 'contributor'); ?>>contributor</option>
                                <option value="author" <?php selected(!empty($edit_rule['conditions']['role']) ? $edit_rule['conditions']['role'] : 'any', 'author'); ?>>author</option>
                                <option value="editor" <?php selected(!empty($edit_rule['conditions']['role']) ? $edit_rule['conditions']['role'] : 'any', 'editor'); ?>>editor</option>
                                <option value="administrator" <?php selected(!empty($edit_rule['conditions']['role']) ? $edit_rule['conditions']['role'] : 'any', 'administrator'); ?>>administrator</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mnem-rule-site">Site ID</label></th>
                        <td><input id="mnem-rule-site" name="site_id" type="text" value="<?php echo esc_attr(!empty($edit_rule['conditions']['site_id']) ? (string) $edit_rule['conditions']['site_id'] : 'any'); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mnem-rule-enabled">Enabled</label></th>
                        <td><input id="mnem-rule-enabled" name="enabled" type="checkbox" value="1" <?php echo empty($edit_rule) || !empty($edit_rule['enabled']) ? 'checked="checked"' : ''; ?> /></td>
                    </tr>
                </tbody>
            </table>
            <?php submit_button(!empty($edit_rule) ? 'Update Rule' : 'Save Rule'); ?>
            <?php submit_button('Dry Run', 'secondary', 'mnem_dry_run', false); ?>
        </form>
    </div>
</div>
