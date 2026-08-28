<?php

defined('ABSPATH') || exit;

$editing            = is_array($edit_campaign);
$campaign_name      = $editing ? $edit_campaign['name'] : '';
$campaign_desc      = $editing ? ($edit_campaign['description'] ?? '') : '';
$campaign_body      = $editing ? $edit_campaign['message_body'] : '';
$campaign_status    = $editing ? $edit_campaign['status'] : 'draft';
$campaign_list_id   = $editing ? (int) $edit_campaign['sms_list_id'] : 0;
$campaign_scheduled = $editing && isset($edit_campaign['scheduled_at']) ? $edit_campaign['scheduled_at'] : '';
$is_locked          = $editing && in_array($campaign_status, array('cancelled', 'completed'), true);
?>
<div class="wrap mnem-dashboard">
    <h1><?php echo esc_html($editing ? 'Edit SMS Campaign' : 'SMS Campaigns'); ?></h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <div class="mnem-grid">
        <!-- Campaign List -->
        <div class="mnem-panel mnem-panel-wide">
            <h2>Existing SMS Campaigns</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>SMS List</th>
                        <th>Status</th>
                        <th>Recipients / Sent / Failed</th>
                        <th>Scheduled At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)) : ?>
                        <tr>
                            <td colspan="7">No SMS campaigns yet.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($campaigns as $camp) : ?>
                            <?php
                            $pending = max(0, (int) $camp['total_recipients'] - (int) $camp['sent_count'] - (int) $camp['failed_count']);
                            ?>
                            <tr>
                                <td><?php echo esc_html((string) $camp['id']); ?></td>
                                <td>
                                    <strong><?php echo esc_html($camp['name']); ?></strong>
                                    <?php if (!empty($camp['description'])) : ?>
                                        <br /><span class="description"><?php echo esc_html($camp['description']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $list_label = '';
                                    foreach ((array) $sms_lists as $sl) {
                                        if ((int) $sl['id'] === (int) $camp['sms_list_id']) {
                                            $list_label = $sl['name'];
                                            break;
                                        }
                                    }
                                    echo $list_label !== '' ? esc_html($list_label) : esc_html('#' . $camp['sms_list_id']);
                                    ?>
                                </td>
                                <td>
                                    <span class="mnem-badge mnem-status-<?php echo esc_attr($camp['status']); ?>">
                                        <?php echo esc_html($camp['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html((string) $camp['total_recipients']); ?> /
                                    <?php echo esc_html((string) $camp['sent_count']); ?> /
                                    <?php echo esc_html((string) $camp['failed_count']); ?>
                                    <?php if ($pending > 0) : ?>
                                        <span class="description">(<?php echo esc_html((string) $pending); ?> pending)</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($camp['scheduled_at'] ?? '—'); ?></td>
                                <td>
                                    <?php $is_completed_or_cancelled = in_array($camp['status'], array('cancelled', 'completed'), true); ?>
                                    <?php if (in_array($camp['status'], array('draft', 'scheduled'), true)) : ?>
                                        <form method="post" class="mnem-inline-form">
                                            <?php wp_nonce_field('mnem_sms_campaign'); ?>
                                            <input type="hidden" name="mnem_action" value="send_sms_campaign" />
                                            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $camp['id']); ?>" />
                                            <input type="hidden" name="redirect_page" value="mnem-sms-campaigns" />
                                            <?php submit_button('Send Now', 'secondary', 'submit', false); ?>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($camp['status'] === 'sending') : ?>
                                        <form method="post" class="mnem-inline-form">
                                            <?php wp_nonce_field('mnem_sms_campaign'); ?>
                                            <input type="hidden" name="mnem_action" value="pause_sms_campaign" />
                                            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $camp['id']); ?>" />
                                            <input type="hidden" name="redirect_page" value="mnem-sms-campaigns" />
                                            <?php submit_button('Pause', 'secondary', 'submit', false); ?>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($camp['status'] === 'paused') : ?>
                                        <form method="post" class="mnem-inline-form">
                                            <?php wp_nonce_field('mnem_sms_campaign'); ?>
                                            <input type="hidden" name="mnem_action" value="resume_sms_campaign" />
                                            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $camp['id']); ?>" />
                                            <input type="hidden" name="redirect_page" value="mnem-sms-campaigns" />
                                            <?php submit_button('Resume', 'secondary', 'submit', false); ?>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (\MNEM\SmsCampaigns::can_cancel((int) $camp['id'])) : ?>
                                        <form method="post" class="mnem-inline-form" onsubmit="return confirm('Cancel this SMS campaign?')">
                                            <?php wp_nonce_field('mnem_sms_campaign'); ?>
                                            <input type="hidden" name="mnem_action" value="cancel_sms_campaign" />
                                            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $camp['id']); ?>" />
                                            <input type="hidden" name="redirect_page" value="mnem-sms-campaigns" />
                                            <?php submit_button('Cancel', 'delete', 'submit', false); ?>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($is_completed_or_cancelled) : ?>
                                        <a class="button button-secondary" href="<?php echo esc_attr(network_admin_url('admin.php?page=mnem-sms-campaigns&mnem_campaign=' . (int) $camp['id'])); ?>">View</a>
                                        <form method="post" class="mnem-inline-form" onsubmit="return confirm('Duplicate this campaign as a new draft?')">
                                            <?php wp_nonce_field('mnem_sms_campaign'); ?>
                                            <input type="hidden" name="mnem_action" value="copy_sms_campaign" />
                                            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $camp['id']); ?>" />
                                            <input type="hidden" name="redirect_page" value="mnem-sms-campaigns" />
                                            <?php submit_button('Copy', 'secondary', 'submit', false); ?>
                                        </form>
                                    <?php else : ?>
                                        <a class="button button-secondary" href="<?php echo esc_attr(network_admin_url('admin.php?page=mnem-sms-campaigns&mnem_campaign=' . (int) $camp['id'])); ?>">Edit</a>
                                    <?php endif; ?>
                                    <form method="post" class="mnem-inline-form" onsubmit="return confirm('Delete this SMS campaign?')">
                                        <?php wp_nonce_field('mnem_sms_campaign'); ?>
                                        <input type="hidden" name="mnem_action" value="delete_sms_campaign" />
                                        <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $camp['id']); ?>" />
                                        <input type="hidden" name="redirect_page" value="mnem-sms-campaigns" />
                                        <?php submit_button('Delete', 'delete', 'submit', false); ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Create / Edit Form -->
        <div class="mnem-panel mnem-panel-wide">
            <h2><?php echo esc_html($editing ? 'Update SMS Campaign' : 'Create SMS Campaign'); ?></h2>
            <form method="post" id="mnem-sms-campaign-form">
                <?php wp_nonce_field('mnem_sms_campaign'); ?>
                <input type="hidden" name="mnem_action" value="save_sms_campaign" />
                <input type="hidden" name="redirect_page" value="mnem-sms-campaigns" />
                <?php if ($editing) : ?>
                    <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $edit_campaign['id']); ?>" />
                <?php endif; ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="mnem_sms_campaign_name">Name <span class="required">*</span></label></th>
                            <td>
                                <input class="regular-text" id="mnem_sms_campaign_name" name="name" type="text"
                                    value="<?php echo esc_attr($campaign_name); ?>"
                                    required <?php echo $is_locked ? 'readonly' : ''; ?> />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_sms_campaign_desc">Description</label></th>
                            <td>
                                <textarea class="large-text" id="mnem_sms_campaign_desc" name="description" rows="2"
                                    <?php echo $is_locked ? 'readonly' : ''; ?>><?php echo esc_textarea($campaign_desc); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_sms_campaign_list">SMS Subscriber List <span class="required">*</span></label></th>
                            <td>
                                <select id="mnem_sms_campaign_list" name="sms_list_id" required
                                    <?php echo $is_locked ? 'disabled' : ''; ?>>
                                    <option value="">— Select a list —</option>
                                    <?php foreach ((array) $sms_lists as $sl) : ?>
                                        <option value="<?php echo esc_attr((string) $sl['id']); ?>"
                                            <?php selected($campaign_list_id, (int) $sl['id']); ?>>
                                            <?php echo esc_html($sl['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span id="mnem-sms-recipient-count" class="description"></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_sms_campaign_body">Message Body <span class="required">*</span></label></th>
                            <td>
                                <textarea class="large-text" id="mnem_sms_campaign_body" name="message_body" rows="6"
                                    required maxlength="1600"
                                    <?php echo $is_locked ? 'readonly' : ''; ?>><?php echo esc_textarea($campaign_body); ?></textarea>
                                <div id="mnem-sms-char-counter" class="description">
                                    <span id="mnem-sms-chars">0</span> characters &bull;
                                    <span id="mnem-sms-segments">1</span> segment(s)
                                    (160 chars / segment for standard SMS)
                                </div>
                                <p class="description">Available variables: {user_name}, {phone_number}, {site_name}, {date}</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_sms_campaign_status">Status</label></th>
                            <td>
                                <select id="mnem_sms_campaign_status" name="status"
                                    <?php echo $is_locked ? 'disabled' : ''; ?>>
                                    <option value="draft" <?php selected($campaign_status, 'draft'); ?>>Draft</option>
                                    <option value="scheduled" <?php selected($campaign_status, 'scheduled'); ?>>Scheduled</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_sms_campaign_scheduled_at">Scheduled At</label></th>
                            <td>
                                <input id="mnem_sms_campaign_scheduled_at" name="scheduled_at" type="text"
                                    class="regular-text"
                                    value="<?php echo esc_attr($campaign_scheduled); ?>"
                                    placeholder="YYYY-MM-DD HH:MM:SS"
                                    <?php echo $is_locked ? 'readonly' : ''; ?> />
                                <p class="description">Leave blank to send immediately when you click &ldquo;Send Now&rdquo;.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php if ($is_locked) : ?>
                    <p><em>This campaign is <?php echo esc_html($campaign_status); ?> and cannot be edited.</em></p>
                <?php else : ?>
                    <?php submit_button($editing ? 'Update Campaign' : 'Create Campaign'); ?>
                <?php endif; ?>
            </form>
        </div>

        <?php if ($editing) : ?>
        <!-- Test SMS -->
        <div class="mnem-panel mnem-panel-wide" style="background:#f0f8ff;border-left:4px solid #0073aa;margin-top:20px;">
            <h3>Send Test SMS</h3>
            <p class="description">Send a test message to a phone number before sending to all recipients.</p>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row"><label for="mnem_sms_test_phone">Phone Number</label></th>
                        <td>
                            <input id="mnem_sms_test_phone" type="text" class="regular-text"
                                placeholder="+1 555 000 0000" />
                            <button type="button" id="mnem-send-sms-test" class="button button-secondary">Send Test SMS</button>
                            <span id="mnem-sms-test-result" class="description" style="margin-left:10px;"></span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <input type="hidden" id="mnem_sms_test_campaign_id" value="<?php echo esc_attr((string) $edit_campaign['id']); ?>" />
            <input type="hidden" id="mnem_sms_ajax_nonce" value="<?php echo esc_attr(wp_create_nonce('mnem_sms_campaign_ajax')); ?>" />
        </div>
        <?php endif; ?>
    </div>
</div>
