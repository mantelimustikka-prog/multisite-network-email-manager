<?php
/**
 * Settings tab — Webhook Health.
 *
 * Variables available: $webhook_provider, $webhook_url, $webhook_recent, $webhook_stats
 */

defined('ABSPATH') || exit;

$webhook_provider = isset($webhook_provider) ? (string) $webhook_provider : 'brevo';
$webhook_url = isset($webhook_url) ? (string) $webhook_url : '';
$webhook_recent = isset($webhook_recent) && is_array($webhook_recent) ? $webhook_recent : array();
$webhook_stats = isset($webhook_stats) && is_array($webhook_stats) ? $webhook_stats : array();

$total_received = isset($webhook_stats['total']) ? (int) $webhook_stats['total'] : 0;
$total_success = isset($webhook_stats['success']) ? (int) $webhook_stats['success'] : 0;
$total_failed = isset($webhook_stats['failed']) ? (int) $webhook_stats['failed'] : 0;
$last_received_at = isset($webhook_stats['last_received_at']) ? (string) $webhook_stats['last_received_at'] : '';
?>

<p class="description">
    <?php esc_html_e('Verify that your email provider can deliver delivery-status webhooks to this site. Configure the URL below in your provider dashboard (for Brevo: Transactional → Settings → Webhooks).', 'multisite-network-email-manager'); ?>
</p>

<table class="form-table" role="presentation">
    <tbody>
        <tr>
            <th scope="row"><?php esc_html_e('Provider', 'multisite-network-email-manager'); ?></th>
            <td><?php echo esc_html(ucfirst($webhook_provider)); ?></td>
        </tr>
        <tr>
            <th scope="row"><label for="mnem-webhook-url"><?php esc_html_e('Webhook URL', 'multisite-network-email-manager'); ?></label></th>
            <td>
                <input type="text" id="mnem-webhook-url" class="large-text code" readonly="readonly" value="<?php echo esc_attr($webhook_url); ?>" />
                <p class="description"><?php esc_html_e('Copy this URL into your provider webhook configuration so status changes are pushed to this site.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Webhooks received (last 7 days)', 'multisite-network-email-manager'); ?></th>
            <td>
                <?php
                printf(
                    /* translators: 1: total webhooks, 2: processed successfully, 3: failed. */
                    esc_html__('%1$d received — %2$d processed, %3$d unmatched or failed.', 'multisite-network-email-manager'),
                    $total_received,
                    $total_success,
                    $total_failed
                );
                ?>
                <p class="description">
                    <?php if ($last_received_at !== '') : ?>
                        <?php printf(esc_html__('Last webhook received at %s (UTC).', 'multisite-network-email-manager'), esc_html($last_received_at)); ?>
                    <?php else : ?>
                        <?php esc_html_e('No webhooks have been received yet. If this stays empty, the provider cannot reach this site or the webhook is not configured.', 'multisite-network-email-manager'); ?>
                    <?php endif; ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e('Endpoint test', 'multisite-network-email-manager'); ?></th>
            <td>
                <button type="button" class="button" id="mnem-test-webhook-endpoint" data-provider="<?php echo esc_attr($webhook_provider); ?>">
                    <?php esc_html_e('Send Test Webhook', 'multisite-network-email-manager'); ?>
                </button>
                <span id="mnem-test-webhook-result" class="description" style="margin-left: 8px;"></span>
                <p class="description"><?php esc_html_e('Sends a test request to the webhook URL and confirms the endpoint accepts and logs it.', 'multisite-network-email-manager'); ?></p>
            </td>
        </tr>
    </tbody>
</table>

<h2><?php esc_html_e('Last 10 Webhook Receipts', 'multisite-network-email-manager'); ?></h2>
<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Received At (UTC)', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Provider', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Event', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Recipient', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Mapped Status', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Processed', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Details', 'multisite-network-email-manager'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($webhook_recent)) : ?>
            <tr><td colspan="7"><?php esc_html_e('No webhook receipts have been logged yet.', 'multisite-network-email-manager'); ?></td></tr>
        <?php else : ?>
            <?php foreach ($webhook_recent as $entry) : ?>
                <?php $success = !empty($entry['success']); ?>
                <tr>
                    <td><?php echo esc_html(isset($entry['received_at']) ? (string) $entry['received_at'] : ''); ?></td>
                    <td><?php echo esc_html(isset($entry['provider']) ? (string) $entry['provider'] : ''); ?></td>
                    <td><?php echo esc_html(isset($entry['event_type']) && $entry['event_type'] !== '' ? (string) $entry['event_type'] : '—'); ?></td>
                    <td><?php echo esc_html(isset($entry['recipient_email']) && $entry['recipient_email'] !== '' ? (string) $entry['recipient_email'] : '—'); ?></td>
                    <td><?php echo esc_html(isset($entry['status']) && $entry['status'] !== '' ? (string) $entry['status'] : '—'); ?></td>
                    <td>
                        <span class="mnem-badge mnem-status-<?php echo esc_attr($success ? 'delivered' : 'failed'); ?>">
                            <?php echo esc_html($success ? __('Success', 'multisite-network-email-manager') : __('Failed', 'multisite-network-email-manager')); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html(isset($entry['error_message']) && $entry['error_message'] !== '' ? (string) $entry['error_message'] : '—'); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
