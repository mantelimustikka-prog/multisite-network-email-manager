<?php
/**
 * Settings tab — Webhook Health.
 *
 * Variables available: $webhook_provider, $webhook_url, $webhook_recent, $webhook_stats,
 * $webhook_provider_stats, $webhook_errors, $webhook_endpoints
 */

defined('ABSPATH') || exit;

$webhook_provider = isset($webhook_provider) ? (string) $webhook_provider : 'brevo';
$webhook_url = isset($webhook_url) ? (string) $webhook_url : '';
$webhook_recent = isset($webhook_recent) && is_array($webhook_recent) ? $webhook_recent : array();
$webhook_stats = isset($webhook_stats) && is_array($webhook_stats) ? $webhook_stats : array();
$webhook_provider_stats = isset($webhook_provider_stats) && is_array($webhook_provider_stats) ? $webhook_provider_stats : array();
$webhook_errors = isset($webhook_errors) && is_array($webhook_errors) ? $webhook_errors : array();
$webhook_endpoints = isset($webhook_endpoints) && is_array($webhook_endpoints) ? $webhook_endpoints : array();

if (empty($webhook_endpoints) && $webhook_url !== '') {
    $webhook_endpoints = array($webhook_provider => $webhook_url);
}

$total_received = isset($webhook_stats['total']) ? (int) $webhook_stats['total'] : 0;
$total_success = isset($webhook_stats['success']) ? (int) $webhook_stats['success'] : 0;
$total_failed = isset($webhook_stats['failed']) ? (int) $webhook_stats['failed'] : 0;
$success_rate = \MNEM\WebhookLog::calculate_success_rate($webhook_stats);
$last_received_at = isset($webhook_stats['last_received_at']) ? (string) $webhook_stats['last_received_at'] : '';

$brevo_events = array(
    'delivered'   => __('The provider accepted the message and delivered it to the recipient mailbox.', 'multisite-network-email-manager'),
    'request'     => __('The message was queued by the provider (shown as "sent" in the queue).', 'multisite-network-email-manager'),
    'opened'      => __('The recipient opened the message.', 'multisite-network-email-manager'),
    'click'       => __('The recipient clicked a link in the message.', 'multisite-network-email-manager'),
    'soft_bounce' => __('Temporary delivery failure — the provider will retry.', 'multisite-network-email-manager'),
    'hard_bounce' => __('Permanent delivery failure — the address is auto-suppressed.', 'multisite-network-email-manager'),
    'invalid_email' => __('The address is not a valid mailbox — it is auto-suppressed.', 'multisite-network-email-manager'),
    'spam'        => __('The recipient marked the message as spam — the address is auto-suppressed.', 'multisite-network-email-manager'),
    'unsubscribed' => __('The recipient unsubscribed through the provider.', 'multisite-network-email-manager'),
    'deferred'    => __('Delivery is delayed and will be retried by the provider.', 'multisite-network-email-manager'),
    'blocked'     => __('The provider blocked the message (for example a suppression list hit).', 'multisite-network-email-manager'),
);
?>

<p class="description">
    <?php esc_html_e('Verify that your email provider can deliver delivery-status webhooks to this site. Without a configured webhook, queue statuses only update through the scheduled provider sync.', 'multisite-network-email-manager'); ?>
</p>

<table class="form-table" role="presentation">
    <tbody>
        <tr>
            <th scope="row"><?php esc_html_e('Active provider', 'multisite-network-email-manager'); ?></th>
            <td><?php echo esc_html(ucfirst($webhook_provider)); ?></td>
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
                <?php
                printf(
                    /* translators: %s: success rate percentage. */
                    ' ' . esc_html__('Success rate: %s%%.', 'multisite-network-email-manager'),
                    esc_html((string) $success_rate)
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

<h2><?php esc_html_e('Webhook URLs and Provider Statistics', 'multisite-network-email-manager'); ?></h2>
<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Provider', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Webhook URL', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Received (7 days)', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Success rate', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Last received (UTC)', 'multisite-network-email-manager'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($webhook_endpoints as $endpoint_provider => $endpoint_url) : ?>
            <?php
            $provider_stats = isset($webhook_provider_stats[$endpoint_provider]) && is_array($webhook_provider_stats[$endpoint_provider])
                ? $webhook_provider_stats[$endpoint_provider]
                : array('total' => 0, 'success' => 0, 'failed' => 0, 'last_received_at' => '');
            $provider_total = (int) $provider_stats['total'];
            $provider_rate = \MNEM\WebhookLog::calculate_success_rate($provider_stats);
            $field_id = 'mnem-webhook-url-' . sanitize_key((string) $endpoint_provider);
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html(ucfirst((string) $endpoint_provider)); ?></strong>
                    <?php if ((string) $endpoint_provider === $webhook_provider) : ?>
                        <span class="description"><?php esc_html_e('(active)', 'multisite-network-email-manager'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <input type="text" id="<?php echo esc_attr($field_id); ?>" class="large-text code mnem-webhook-url-field" readonly="readonly" value="<?php echo esc_attr((string) $endpoint_url); ?>" />
                    <button type="button" class="button button-small mnem-copy-webhook-url" data-target="<?php echo esc_attr($field_id); ?>">
                        <?php esc_html_e('Copy', 'multisite-network-email-manager'); ?>
                    </button>
                </td>
                <td>
                    <?php
                    printf(
                        /* translators: 1: total webhooks, 2: failed webhooks. */
                        esc_html__('%1$d (%2$d failed)', 'multisite-network-email-manager'),
                        $provider_total,
                        (int) $provider_stats['failed']
                    );
                    ?>
                </td>
                <td><?php echo $provider_total > 0 ? esc_html($provider_rate . '%') : '&mdash;'; ?></td>
                <td><?php echo $provider_stats['last_received_at'] !== '' ? esc_html((string) $provider_stats['last_received_at']) : '&mdash;'; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2><?php esc_html_e('How to Configure Webhooks in Brevo', 'multisite-network-email-manager'); ?></h2>
<ol>
    <li><?php esc_html_e('Sign in to Brevo and open Transactional → Settings → Webhooks (marketing campaigns use Campaigns → Settings → Webhooks).', 'multisite-network-email-manager'); ?></li>
    <li><?php esc_html_e('Choose "Add a new webhook" and paste the Brevo webhook URL shown above into the URL field.', 'multisite-network-email-manager'); ?></li>
    <li><?php esc_html_e('Select the events to forward: sent/request, delivered, opened, clicked, soft bounce, hard bounce, invalid email, blocked, spam and unsubscribed.', 'multisite-network-email-manager'); ?></li>
    <li><?php esc_html_e('Save the webhook, then use "Send Test Webhook" above to confirm the endpoint is reachable from the internet.', 'multisite-network-email-manager'); ?></li>
    <li><?php esc_html_e('Send a test email and confirm a new row appears in the webhook receipts table below within a few minutes.', 'multisite-network-email-manager'); ?></li>
</ol>
<p class="description">
    <?php
    printf(
        /* translators: %s: Brevo documentation URL. */
        esc_html__('Brevo webhook documentation: %s', 'multisite-network-email-manager'),
        '<a href="https://developers.brevo.com/docs/transactional-webhooks" target="_blank" rel="noopener noreferrer">developers.brevo.com</a>'
    );
    ?>
</p>

<h3><?php esc_html_e('Webhook Event Reference', 'multisite-network-email-manager'); ?></h3>
<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Event', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('What it means', 'multisite-network-email-manager'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($brevo_events as $event_name => $event_description) : ?>
            <tr>
                <td><code><?php echo esc_html((string) $event_name); ?></code></td>
                <td><?php echo esc_html((string) $event_description); ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<h2><?php esc_html_e('Recent Webhook Errors', 'multisite-network-email-manager'); ?></h2>
<table class="widefat striped">
    <thead>
        <tr>
            <th><?php esc_html_e('Received At (UTC)', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Provider', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Event', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Recipient', 'multisite-network-email-manager'); ?></th>
            <th><?php esc_html_e('Error', 'multisite-network-email-manager'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($webhook_errors)) : ?>
            <tr><td colspan="5"><?php esc_html_e('No webhook processing errors have been recorded.', 'multisite-network-email-manager'); ?></td></tr>
        <?php else : ?>
            <?php foreach ($webhook_errors as $error_entry) : ?>
                <tr>
                    <td><?php echo esc_html(isset($error_entry['received_at']) ? (string) $error_entry['received_at'] : ''); ?></td>
                    <td><?php echo esc_html(isset($error_entry['provider']) ? (string) $error_entry['provider'] : ''); ?></td>
                    <td><?php echo esc_html(isset($error_entry['event_type']) && $error_entry['event_type'] !== '' ? (string) $error_entry['event_type'] : '—'); ?></td>
                    <td><?php echo esc_html(isset($error_entry['recipient_email']) && $error_entry['recipient_email'] !== '' ? (string) $error_entry['recipient_email'] : '—'); ?></td>
                    <td><?php echo esc_html(isset($error_entry['error_message']) && $error_entry['error_message'] !== '' ? (string) $error_entry['error_message'] : '—'); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<h2><?php esc_html_e('Last 50 Webhook Receipts', 'multisite-network-email-manager'); ?></h2>
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
