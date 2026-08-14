<?php

defined('ABSPATH') || exit;
?>
<div class="wrap">
    <h1><?php esc_html_e('Email History', 'multisite-network-email-manager'); ?></h1>

    <form method="get" style="margin: 12px 0;">
        <input type="hidden" name="page" value="mnem-email-history" />
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php echo esc_attr('Search recipient or subject'); ?>" class="regular-text" />
        <?php submit_button(esc_html__('Search', 'multisite-network-email-manager'), 'secondary', '', false); ?>
    </form>

    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Sent At', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Recipient', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Subject', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Status', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Opens', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Clicks', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Actions', 'multisite-network-email-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)) : ?>
                <tr>
                    <td colspan="7"><?php esc_html_e('No sent emails found.', 'multisite-network-email-manager'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($items as $item) : ?>
                    <?php
                    $status = isset($item['delivery_status']) ? (string) $item['delivery_status'] : 'pending';
                    $open_timestamps = json_decode(isset($item['open_timestamps']) ? (string) $item['open_timestamps'] : '[]', true);
                    $click_timestamps = json_decode(isset($item['click_timestamps']) ? (string) $item['click_timestamps'] : '[]', true);
                    ?>
                    <tr>
                        <td><?php echo esc_html(isset($item['created_at']) ? (string) $item['created_at'] : ''); ?></td>
                        <td><?php echo esc_html(isset($item['recipient_email']) ? (string) $item['recipient_email'] : ''); ?></td>
                        <td><?php echo esc_html(isset($item['subject']) ? (string) $item['subject'] : ''); ?></td>
                        <td><span class="mnem-badge mnem-status-<?php echo esc_attr($status); ?>"><?php echo esc_html($status); ?></span></td>
                        <td><?php echo esc_html((string) (isset($item['open_count']) ? (int) $item['open_count'] : 0)); ?></td>
                        <td><?php echo esc_html((string) (isset($item['click_count']) ? (int) $item['click_count'] : 0)); ?></td>
                        <td>
                            <button
                                type="button"
                                class="button button-secondary mnem-email-preview-button"
                                data-recipient="<?php echo esc_attr(isset($item['recipient_email']) ? (string) $item['recipient_email'] : ''); ?>"
                                data-subject="<?php echo esc_attr(isset($item['subject']) ? (string) $item['subject'] : ''); ?>"
                                data-status="<?php echo esc_attr($status); ?>"
                                data-message-id="<?php echo esc_attr(isset($item['provider_message_id']) ? (string) $item['provider_message_id'] : ''); ?>"
                                data-body="<?php echo esc_attr(isset($item['body']) ? (string) $item['body'] : ''); ?>"
                                data-headers="<?php echo esc_attr(isset($item['headers']) ? (string) $item['headers'] : '[]'); ?>"
                                data-open-count="<?php echo esc_attr((string) (isset($item['open_count']) ? (int) $item['open_count'] : 0)); ?>"
                                data-open-timestamps="<?php echo esc_attr(wp_json_encode(is_array($open_timestamps) ? $open_timestamps : array())); ?>"
                                data-click-count="<?php echo esc_attr((string) (isset($item['click_count']) ? (int) $item['click_count'] : 0)); ?>"
                                data-click-timestamps="<?php echo esc_attr(wp_json_encode(is_array($click_timestamps) ? $click_timestamps : array())); ?>"
                                data-sent-at="<?php echo esc_attr(isset($item['created_at']) ? (string) $item['created_at'] : ''); ?>"
                            ><?php esc_html_e('Preview', 'multisite-network-email-manager'); ?></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
