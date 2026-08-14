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
$campaign_template_id = $editing && isset($edit_campaign['template_id']) ? (string) $edit_campaign['template_id'] : '';
$campaign_target_lists = $editing && !empty($edit_campaign['target_lists']) ? json_decode((string) $edit_campaign['target_lists'], true) : array();
$campaign_target_lists = is_array($campaign_target_lists) ? array_map('intval', $campaign_target_lists) : array();
$is_cancelled = $editing && $campaign_status === 'cancelled';
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
                            <td><input class="regular-text" id="mnem_campaign_name" name="name" type="text" value="<?php echo esc_attr($campaign_name); ?>" required <?php echo $is_cancelled ? 'readonly' : ''; ?> /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_subject">Subject</label></th>
                            <td><input class="regular-text" id="mnem_campaign_subject" name="subject" type="text" value="<?php echo esc_attr($campaign_subject); ?>" required <?php echo $is_cancelled ? 'readonly' : ''; ?> /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_template_id">Template</label></th>
                            <td>
                                <select id="mnem_campaign_template_id" name="template_id" <?php echo $is_cancelled ? 'disabled' : ''; ?>>
                                    <option value="">Start Blank</option>
                                    <?php foreach ((array) $templates as $template_id => $template) : ?>
                                        <option value="<?php echo esc_attr((string) $template_id); ?>" <?php selected($campaign_template_id, (string) $template_id); ?>>
                                            <?php echo esc_html(isset($template['name']) ? (string) $template['name'] : (string) $template_id); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_body">Body</label></th>
                            <td>
                                <?php if (function_exists('wp_editor')) : ?>
                                    <?php wp_editor($campaign_body, 'mnem_campaign_body', array('textarea_name' => 'body', 'textarea_rows' => 12, 'media_buttons' => true)); ?>
                                <?php else : ?>
                                    <textarea class="large-text" id="mnem_campaign_body" name="body" rows="10" required><?php echo esc_textarea($campaign_body); ?></textarea>
                                <?php endif; ?>
                                <p class="description">Available variables: {user_name}, {user_email}, {site_name}, {date}</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_status">Status</label></th>
                            <td>
                                <select id="mnem_campaign_status" name="status" <?php echo $is_cancelled ? 'disabled' : ''; ?>>
                                    <option value="draft" <?php selected($campaign_status, 'draft'); ?>>Draft</option>
                                    <option value="scheduled" <?php selected($campaign_status, 'scheduled'); ?>>Scheduled</option>
                                    <option value="cancelled" <?php selected($campaign_status, 'cancelled'); ?>>Cancelled</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_scope">Recipients</label></th>
                            <td>
                                <select id="mnem_campaign_scope" name="recipient_scope" <?php echo $is_cancelled ? 'disabled' : ''; ?>>
                                    <option value="all_users" <?php selected($campaign_scope, 'all_users'); ?>>All users</option>
                                    <option value="admins" <?php selected($campaign_scope, 'admins'); ?>>Admins only</option>
                                    <option value="custom" <?php selected($campaign_scope, 'custom'); ?>>Custom list</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Select Subscriber Lists</th>
                            <td>
                                <?php if (empty($subscriber_lists)) : ?>
                                    <p class="description">No subscriber lists yet.</p>
                                <?php else : ?>
                                    <?php foreach ($subscriber_lists as $list) : ?>
                                        <?php $list_id = (int) $list['id']; ?>
                                        <label style="display:block;margin:2px 0;">
                                            <input type="checkbox" name="target_lists[]" value="<?php echo esc_attr((string) $list_id); ?>" <?php checked(in_array($list_id, $campaign_target_lists, true), true); ?> <?php echo $is_cancelled ? 'disabled' : ''; ?> />
                                            <?php echo esc_html($list['name']); ?> (<?php echo esc_html((string) \MNEM\SubscriberLists::get_list_subscribers_count($list_id)); ?>)
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_list">Custom Recipients</label></th>
                            <td>
                                <textarea class="large-text" id="mnem_campaign_list" name="recipient_list" rows="5" <?php echo $is_cancelled ? 'readonly' : ''; ?>><?php echo esc_textarea($campaign_list); ?></textarea>
                                <p class="description">One email address per line.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_schedule">Scheduled At</label></th>
                            <td><input id="mnem_campaign_schedule" name="scheduled_at" type="text" class="regular-text" value="<?php echo esc_attr($campaign_scheduled_at); ?>" placeholder="YYYY-MM-DD HH:MM:SS" <?php echo $is_cancelled ? 'readonly' : ''; ?> /></td>
                        </tr>
                    </tbody>
                </table>
                <?php if ($is_cancelled) : ?>
                    <p><em>This campaign is cancelled and read-only.</em></p>
                <?php else : ?>
                    <?php submit_button($editing ? 'Update Campaign' : 'Create Campaign'); ?>
                <?php endif; ?>
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
                                    <?php if (\MNEM\Campaigns::can_cancel((int) $campaign['id'])) : ?>
                                        <form method="post" class="mnem-inline-form" onsubmit="return confirm('Are you sure you want to cancel this campaign? This will remove all pending emails from the queue.');">
                                            <?php wp_nonce_field('mnem_campaign'); ?>
                                            <input type="hidden" name="mnem_action" value="cancel_campaign" />
                                            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $campaign['id']); ?>" />
                                            <input type="hidden" name="redirect_page" value="mnem-campaigns" />
                                            <?php submit_button('Cancel Campaign', 'delete', 'submit', false); ?>
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
