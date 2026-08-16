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
        <!-- Existing Campaigns List - Top -->
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
                                        <form method="post" class="mnem-inline-form" onsubmit="return confirm('Are you sure you want to cancel this campaign? This will remove all pending emails from the queue.')">
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

        <!-- Create/Edit Campaign Form -->
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
                                    <?php wp_editor($campaign_body, 'mnem_campaign_body', array('textarea_name' => 'body', 'textarea_rows' => 12, 'media_buttons' => true, 'wpautop' => true)); ?>
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
                                            <input type="checkbox" name="target_lists[]" value="<?php echo esc_attr((string) $list_id); ?>" <?php checked(in_array($list_id, $campaign_target_lists, true)); ?> <?php echo $is_cancelled ? 'disabled' : ''; ?> />
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

        <?php if ($editing) : ?>
        <!-- Test Email Section -->
        <div class="mnem-panel mnem-panel-wide" style="background: #f0f8ff; border-left: 4px solid #0073aa; margin-top: 20px;">
            <h3><?php esc_html_e('Send Test Email', 'multisite-network-email-manager'); ?></h3>

            <div style="background: white; padding: 20px; border-radius: 4px;">
                <p style="color: #666; margin-bottom: 15px;">
                    <?php esc_html_e('Send a test email to verify your campaign content before sending to all recipients.', 'multisite-network-email-manager'); ?>
                </p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label for="mnem_test_email" style="font-weight: bold; display: block; margin-bottom: 5px;">
                            <?php esc_html_e('Test Email Address:', 'multisite-network-email-manager'); ?>
                        </label>
                        <input type="email" id="mnem_test_email" class="regular-text" placeholder="test@example.com" value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>" style="margin-bottom: 10px;" />
                    </div>

                    <div>
                        <label for="mnem_test_template_var" style="font-weight: bold; display: block; margin-bottom: 5px;">
                            <?php esc_html_e('Test Template Variables (Optional):', 'multisite-network-email-manager'); ?>
                        </label>
                        <input type="text" id="mnem_test_template_var" class="regular-text" placeholder='{"name": "John", "site": "Example"}' style="margin-bottom: 10px;">
                        <small style="color: #666;"><?php esc_html_e('JSON format for variable substitution', 'multisite-network-email-manager'); ?></small>
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="button" id="mnem_send_test_email_btn" class="button button-primary" onclick="mnemSendTestEmail(<?php echo (int) $edit_campaign['id']; ?>)">
                        <?php esc_html_e('Send Test Email', 'multisite-network-email-manager'); ?>
                    </button>

                    <button type="button" id="mnem_preview_test_email_btn" class="button" onclick="mnemPreviewTestEmail(<?php echo (int) $edit_campaign['id']; ?>)">
                        <?php esc_html_e('Preview Test Email', 'multisite-network-email-manager'); ?>
                    </button>
                </div>

                <div id="mnem_test_email_result" style="margin-top: 15px; display: none; padding: 15px; border-radius: 4px;"></div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div id="mnem_preview_modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 999999; overflow-y: auto;">
            <div style="background: white; margin: 20px auto; max-width: 800px; border-radius: 4px; padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2><?php esc_html_e('Email Preview', 'multisite-network-email-manager'); ?></h2>
                    <button type="button" onclick="document.getElementById('mnem_preview_modal').style.display='none'" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
                </div>

                <div style="border-bottom: 1px solid #ddd; padding-bottom: 15px; margin-bottom: 15px;">
                    <p><strong><?php esc_html_e('To:', 'multisite-network-email-manager'); ?></strong> <span id="mnem_preview_to"></span></p>
                    <p><strong><?php esc_html_e('Subject:', 'multisite-network-email-manager'); ?></strong> <span id="mnem_preview_subject"></span></p>
                </div>

                <div id="mnem_preview_body" style="border: 1px solid #ddd; border-radius: 4px; min-height: 200px; max-height: 400px; overflow-y: auto;">
                    <iframe id="mnem_preview_iframe" sandbox="" style="width: 100%; min-height: 200px; border: none; display: block;"></iframe>
                </div>

                <div style="margin-top: 15px; text-align: right;">
                    <button type="button" onclick="document.getElementById('mnem_preview_modal').style.display='none'" class="button">
                        <?php esc_html_e('Close', 'multisite-network-email-manager'); ?>
                    </button>
                </div>
            </div>
        </div>

        <script>
        async function mnemSendTestEmail(campaignId) {
            const email = document.getElementById('mnem_test_email').value.trim();

            if (!email) {
                alert('<?php echo esc_js(__('Please enter an email address', 'multisite-network-email-manager')); ?>');
                return;
            }

            if (!email.includes('@')) {
                alert('<?php echo esc_js(__('Please enter a valid email address', 'multisite-network-email-manager')); ?>');
                return;
            }

            const templateVars = document.getElementById('mnem_test_template_var').value.trim();
            let parsedVars = {};

            if (templateVars) {
                try {
                    parsedVars = JSON.parse(templateVars);
                } catch (e) {
                    alert('<?php echo esc_js(__('Invalid JSON in template variables', 'multisite-network-email-manager')); ?>: ' + e.message);
                    return;
                }
            }

            const button = document.getElementById('mnem_send_test_email_btn');
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = '<?php echo esc_js(__('Sending...', 'multisite-network-email-manager')); ?>';

            const resultDiv = document.getElementById('mnem_test_email_result');

            try {
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'mnem_send_campaign_test_email',
                        nonce: <?php echo wp_json_encode(wp_create_nonce('mnem_test_email')); ?>,
                        campaign_id: campaignId,
                        test_email: email,
                        template_vars: JSON.stringify(parsedVars),
                    })
                });

                const data = await response.json();

                if (data.success) {
                    resultDiv.style.display = 'block';
                    resultDiv.style.background = '#d4edda';
                    resultDiv.style.borderLeft = '4px solid #28a745';
                    resultDiv.style.color = '#155724';
                    resultDiv.innerHTML = '<strong><?php echo esc_js(__('✓ Success!', 'multisite-network-email-manager')); ?></strong> ' + data.data.message;
                } else {
                    resultDiv.style.display = 'block';
                    resultDiv.style.background = '#f8d7da';
                    resultDiv.style.borderLeft = '4px solid #dc3545';
                    resultDiv.style.color = '#721c24';
                    resultDiv.innerHTML = '<strong><?php echo esc_js(__('✗ Error:', 'multisite-network-email-manager')); ?></strong> ' + data.data.message;
                }
            } catch (error) {
                resultDiv.style.display = 'block';
                resultDiv.style.background = '#f8d7da';
                resultDiv.style.borderLeft = '4px solid #dc3545';
                resultDiv.style.color = '#721c24';
                resultDiv.innerHTML = '<strong><?php echo esc_js(__('✗ Error:', 'multisite-network-email-manager')); ?></strong> ' + error.message;
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        }

        async function mnemPreviewTestEmail(campaignId) {
            const templateVars = document.getElementById('mnem_test_template_var').value.trim();
            let parsedVars = {};

            if (templateVars) {
                try {
                    parsedVars = JSON.parse(templateVars);
                } catch (e) {
                    alert('<?php echo esc_js(__('Invalid JSON in template variables', 'multisite-network-email-manager')); ?>: ' + e.message);
                    return;
                }
            }

            try {
                const response = await fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'mnem_preview_campaign_test_email',
                        nonce: <?php echo wp_json_encode(wp_create_nonce('mnem_test_email')); ?>,
                        campaign_id: campaignId,
                        template_vars: JSON.stringify(parsedVars),
                    })
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('mnem_preview_to').textContent = data.data.to || '<?php echo esc_js(__('test@example.com', 'multisite-network-email-manager')); ?>';
                    document.getElementById('mnem_preview_subject').textContent = data.data.subject;
                    document.getElementById('mnem_preview_iframe').srcdoc = data.data.body;
                    document.getElementById('mnem_preview_modal').style.display = 'block';
                } else {
                    alert('<?php echo esc_js(__('Error generating preview', 'multisite-network-email-manager')); ?>: ' + data.data.message);
                }
            } catch (error) {
                alert('<?php echo esc_js(__('Error', 'multisite-network-email-manager')); ?>: ' + error.message);
            }
        }
        </script>
        <?php endif; ?>
    </div>
</div>
