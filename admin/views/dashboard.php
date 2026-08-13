<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1>Multisite Network Email Manager</h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <?php if (!$smtp_configured) : ?>
        <div class="notice notice-warning"><p>SMTP is not configured yet.</p></div>
    <?php endif; ?>

    <div class="mnem-grid">
        <div class="mnem-panel">
            <h2>Overview</h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row">Plugin Version</th>
                        <td><?php echo esc_html($plugin_version); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Suppression Entries</th>
                        <td><?php echo esc_html((string) $suppression_count); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Campaign Sending</th>
                        <td><?php echo esc_html($campaign_sends_paused ? 'Paused' : 'Active'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mnem-panel">
            <h2>Queue Statistics</h2>
            <table class="widefat striped mnem-queue-stats" data-mnem-queue-stats="1">
                <tbody>
                    <tr>
                        <th scope="row">Pending</th>
                        <td data-mnem-stat="pending"><?php echo esc_html((string) $queue_stats['pending']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Processing</th>
                        <td data-mnem-stat="processing"><?php echo esc_html((string) $queue_stats['processing']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Sent</th>
                        <td data-mnem-stat="sent"><?php echo esc_html((string) $queue_stats['sent']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Failed</th>
                        <td data-mnem-stat="failed"><?php echo esc_html((string) $queue_stats['failed']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Retry Backoff</th>
                        <td data-mnem-stat="next_retry_at"><?php echo esc_html($queue_stats['next_retry_at'] !== '' ? $queue_stats['next_retry_at'] . ' (attempt ' . (string) $queue_stats['next_retry_attempts'] . ')' : 'No retries scheduled'); ?></td>
                    </tr>
                </tbody>
            </table>
            <div class="mnem-actions">
                <form method="post">
                    <?php wp_nonce_field('mnem_queue'); ?>
                    <input type="hidden" name="mnem_action" value="process_queue_now" />
                    <input type="hidden" name="redirect_page" value="mnem-dashboard" />
                    <?php submit_button('Process Queue Now', 'secondary', 'submit', false); ?>
                </form>
                <form method="post">
                    <?php wp_nonce_field('mnem_queue'); ?>
                    <input type="hidden" name="mnem_action" value="retry_failed_queue" />
                    <input type="hidden" name="redirect_page" value="mnem-dashboard" />
                    <?php submit_button('Retry Failed Items', 'secondary', 'submit', false); ?>
                </form>
                <form method="post">
                    <?php wp_nonce_field('mnem_queue'); ?>
                    <input type="hidden" name="mnem_action" value="toggle_campaign_pause" />
                    <input type="hidden" name="redirect_page" value="mnem-dashboard" />
                    <?php submit_button($campaign_sends_paused ? 'Resume Campaign Sends' : 'Pause Campaign Sends', 'secondary', 'submit', false); ?>
                </form>
            </div>
            <?php if ($processed > 0) : ?>
                <p class="description">Processed <?php echo esc_html((string) $processed); ?> queue items.</p>
            <?php endif; ?>
            <?php if ($retried > 0) : ?>
                <p class="description">Rescheduled <?php echo esc_html((string) $retried); ?> failed queue items.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="mnem-grid">
        <div class="mnem-panel mnem-panel-wide">
            <h2>Campaign Management</h2>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Last Attempt</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($campaigns)) : ?>
                        <tr>
                            <td colspan="5">No campaigns yet.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($campaigns as $campaign) : ?>
                            <?php $pending_count = max(0, (int) $campaign['total_recipients'] - (int) $campaign['sent_count'] - (int) $campaign['failed_count']); ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($campaign['name']); ?></strong><br />
                                    <span class="description"><?php echo esc_html($campaign['subject']); ?></span>
                                </td>
                                <td><span class="mnem-badge mnem-status-<?php echo esc_attr($campaign['status']); ?>"><?php echo esc_html($campaign['status']); ?></span></td>
                                <td><?php echo esc_html((string) $campaign['sent_count']); ?> sent / <?php echo esc_html((string) $pending_count); ?> pending / <?php echo esc_html((string) $campaign['failed_count']); ?> failed</td>
                                <td><?php echo esc_html(!empty($campaign['last_send_attempt_at']) ? $campaign['last_send_attempt_at'] : 'Never'); ?></td>
                                <td>
                                    <?php if (in_array($campaign['status'], array('draft', 'scheduled'), true)) : ?>
                                        <form method="post" class="mnem-inline-form">
                                            <?php wp_nonce_field('mnem_campaign'); ?>
                                            <input type="hidden" name="mnem_action" value="send_campaign" />
                                            <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $campaign['id']); ?>" />
                                            <input type="hidden" name="redirect_page" value="mnem-dashboard" />
                                            <?php submit_button('Send Campaign', 'secondary', 'submit', false); ?>
                                        </form>
                                    <?php endif; ?>
                                    <a class="button button-secondary" href="<?php echo esc_attr(network_admin_url('admin.php?page=mnem-campaigns&mnem_campaign=' . (int) $campaign['id'])); ?>">Edit</a>
                                    <form method="post" class="mnem-inline-form">
                                        <?php wp_nonce_field('mnem_campaign'); ?>
                                        <input type="hidden" name="mnem_action" value="delete_campaign" />
                                        <input type="hidden" name="campaign_id" value="<?php echo esc_attr((string) $campaign['id']); ?>" />
                                        <input type="hidden" name="redirect_page" value="mnem-dashboard" />
                                        <?php submit_button('Delete', 'delete', 'submit', false); ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="mnem-panel mnem-panel-wide">
            <h2>Create Campaign</h2>
            <form method="post">
                <?php wp_nonce_field('mnem_campaign'); ?>
                <input type="hidden" name="mnem_action" value="save_campaign" />
                <input type="hidden" name="redirect_page" value="mnem-dashboard" />
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_name">Name</label></th>
                            <td><input class="regular-text" id="mnem_campaign_name" name="name" type="text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_subject">Subject</label></th>
                            <td><input class="regular-text" id="mnem_campaign_subject" name="subject" type="text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_body">Body</label></th>
                            <td><textarea class="large-text" id="mnem_campaign_body" name="body" rows="8" required></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_scope">Recipients</label></th>
                            <td>
                                <select id="mnem_campaign_scope" name="recipient_scope">
                                    <option value="all_users">All users</option>
                                    <option value="admins">Admins only</option>
                                    <option value="custom">Custom list</option>
                                </select>
                                <p class="description">For custom lists, enter one email per line below.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_list">Custom Recipients</label></th>
                            <td><textarea class="large-text" id="mnem_campaign_list" name="recipient_list" rows="4"></textarea></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_status">Initial Status</label></th>
                            <td>
                                <select id="mnem_campaign_status" name="status">
                                    <option value="draft">Draft</option>
                                    <option value="scheduled">Scheduled</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="mnem_campaign_schedule">Scheduled At</label></th>
                            <td><input id="mnem_campaign_schedule" name="scheduled_at" type="text" class="regular-text" placeholder="YYYY-MM-DD HH:MM:SS" /></td>
                        </tr>
                    </tbody>
                </table>
                <?php submit_button('Save Campaign'); ?>
            </form>
        </div>
    </div>

    <div class="mnem-panel mnem-panel-wide">
        <h2>Activity Timeline</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Event</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_logs)) : ?>
                    <tr>
                        <td colspan="3">No log entries yet.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($recent_logs as $log) : ?>
                        <tr>
                            <td><span class="mnem-badge mnem-level-<?php echo esc_attr($log['level']); ?>"><?php echo esc_html($log['level']); ?></span></td>
                            <td><?php echo esc_html($log['message']); ?></td>
                            <td><?php echo esc_html($log['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
