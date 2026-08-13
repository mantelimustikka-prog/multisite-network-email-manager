<?php

defined('ABSPATH') || exit;

$editing = is_array($edit_campaign);
$campaign_name = $editing ? $edit_campaign['name'] : '';
$campaign_subject = $editing ? $edit_campaign['subject'] : '';
$campaign_body = $editing ? $edit_campaign['body'] : '';
$campaign_status = $editing ? $edit_campaign['status'] : 'draft';
$campaign_scope = $editing && isset($edit_campaign['recipient_scope']) ? $edit_campaign['recipient_scope'] : 'all_users';
$campaign_list = $editing && isset($edit_campaign['recipient_list']) ? $edit_campaign['recipient_list'] : '';
$campaign_scheduled_at = $editing && isset($edit_campaign['scheduled_at']) ? $edit_campaign['scheduled_at'] : '';
?>
<div class="wrap mnem-dashboard">
    <h1><?php echo esc_html($editing ? 'Edit Campaign' : 'Campaigns'); ?></h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <div class="mnem-grid">
        <div class="mnem-panel mnem-panel-wide">
            <h2><?php echo esc_html($editing ? 'Update Campaign' : 'Create Campaign'); ?></h2>
            <form method="post">
                <?php wp_nonce_field('mnem_campaign'); ?>
                <input type="hidden" name="mnem_action" value="save_campaign" />
                <input type="hidden" name="redirect_page" value="mnem-campaigns" />
                <?php if ($editing) : ?>
                    <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $edit_campaign['id']); ?>" />
                <?php endif; ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_name">Name</label></th>
                            <td><input class="regular-text" id="mnem_campaign_name" name="name" type="text" value="<?php echo esc_attr($campaign_name); ?>" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_subject">Subject</label></th>
                            <td><input class="regular-text" id="mnem_campaign_subject" name="subject" type="text" value="<?php echo esc_attr($campaign_subject); ?>" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_body">Body</label></th>
                            <td><textarea class="large-text" id="mnem_campaign_body" name="body" rows="10" required><?php echo esc_textarea($campaign_body); ?></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_status">Status</label></th>
                            <td>
                                <select id="mnem_campaign_status" name="status">
                                    <option value="draft" <?php selected($campaign_status, 'draft'); ?>>Draft</option>
                                    <option value="scheduled" <?php selected($campaign_status, 'scheduled'); ?>>Scheduled</option>
                                    <option value="cancelled" <?php selected($campaign_status, 'cancelled'); ?>>Cancelled</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_scope">Recipients</label></th>
                            <td>
                                <select id="mnem_campaign_scope" name="recipient_scope">
                                    <option value="all_users" <?php selected($campaign_scope, 'all_users'); ?>>All users</option>
                                    <option value="admins" <?php selected($campaign_scope, 'admins'); ?>>Admins only</option>
                                    <option value="custom" <?php selected($campaign_scope, 'custom'); ?>>Custom list</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_list">Custom Recipients</label></th>
                            <td>
                                <textarea class="large-text" id="mnem_campaign_list" name="recipient_list" rows="5"><?php echo esc_textarea($campaign_list); ?></textarea>
                                <p class="description">One email address per line.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_schedule">Scheduled At</label></th>
                            <td><input id="mnem_campaign_schedule" name="scheduled_at" type="text" class="regular-text" value="<?php echo esc_attr($campaign_scheduled_at); ?>" placeholder="YYYY-MM-DD HH:MM:SS" /></td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button($editing ? 'Update Campaign' : 'Create Campaign'); ?>
            </form>
        </div>

        <div class="mnem-panel mnem-panel-wide">
            <h2>Existing Campaigns</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Recipients</th>
                        <th>Progress</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)) : ?>
                        <tr>
                            <td colspan="6">No campaigns yet.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($campaigns as $campaign) : ?>
                            <?php $pending_count = max(0, (int) $campaign['total_recipients'] - (int) $campaign['sent_count'] - (int) $campaign['failed_count']); ?>
                            <tr>
                                <td><?php echo esc_html((string) $campaign['id']); ?></td>
                                <td>
                                    <strong><?php echo esc_html($campaign['name']); ?></strong><br />
                                    <span class="description"><?php echo esc_html($campaign['subject']); ?></span>
                                </td>
                                <td><span class="mnem-badge mnem-status-<?php echo esc_attr($campaign['status']); ?>"><?php echo esc_html($campaign['status']); ?></span></td>
                                <td><?php echo esc_html(isset($campaign['recipient_scope']) ? $campaign['recipient_scope'] : 'all_users'); ?></td>
                                <td><?php echo esc_html((string) $campaign['sent_count']); ?> sent / <?php echo esc_html((string) $pending_count); ?> pending / <?php echo esc_html((string) $campaign['failed_count']); ?> failed</td>
                                <td>
                                    <?php if (in_array($campaign['status'], array('draft', 'scheduled'), true)) : ?>
                                        <form method="post" class="mnem-inline-form">
                                            <?php wp_nonce_field('mnem_campaign'); ?>
                                            <input type="hidden" name="mnem_action" value="send_campaign" />
                                            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $campaign['id']); ?>" />
                                            <input type="hidden" name="redirect_page" value="mnem-campaigns" />
                                            <?php submit_button('Send Campaign', 'secondary', 'submit', false); ?>
                                        </form>
                                    <?php endif; ?>
                                    <a class="button button-secondary" href="<?php echo esc_attr(network_admin_url('admin.php?page=mnem-campaigns&mnem_campaign=' . (int) $campaign['id'])); ?>">Edit</a>
                                    <form method="post" class="mnem-inline-form">
                                        <?php wp_nonce_field('mnem_campaign'); ?>
                                        <input type="hidden" name="mnem_action" value="delete_campaign" />
                                        <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $campaign['id']); ?>" />
                                        <input type="hidden" name="redirect_page" value="mnem-campaigns" />
                                        <?php submit_button('Delete', 'delete', 'submit', false); ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
