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

    <?php if (!empty($smtp_warnings)) : ?>
        <?php foreach ($smtp_warnings as $smtp_warning) : ?>
            <div class="notice notice-warning"><p><?php echo wp_kses($smtp_warning, array('a' => array('href' => array()))); ?></p></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$tracking_enabled) : ?>
        <div class="notice notice-warning"><p>
            <?php esc_html_e('Email tracking is disabled. Emails will not be recorded in Email History.', 'multisite-network-email-manager'); ?>
            <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-settings&tab=email_tracking')); ?>"><?php esc_html_e('Enable in Settings', 'multisite-network-email-manager'); ?></a>
        </p></div>
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
                    <tr>
                        <th scope="row">Cron Last Run</th>
                        <td><?php echo esc_html($cron_status['last_run'] !== '' ? $cron_status['last_run'] : 'Never'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Cron Next Run</th>
                        <td><?php echo esc_html($cron_status['next_run'] !== '' ? $cron_status['next_run'] : 'Not scheduled'); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Cron Failures</th>
                        <td><?php echo esc_html((string) $cron_status['failed_runs']); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">User Event Rule Failures</th>
                        <td><?php echo esc_html((string) $failed_rule_triggers); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">SMTP Connection Status</th>
                        <td><?php echo esc_html($smtp_status); ?></td>
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

        <div class="mnem-panel">
            <h2><?php esc_html_e('Error Summary', 'multisite-network-email-manager'); ?>
                <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-error-logs')); ?>" style="font-size:13px;font-weight:normal;margin-left:8px;"><?php esc_html_e('View All', 'multisite-network-email-manager'); ?></a>
            </h2>
            <?php $table_size = \MNEM\ErrorLog::get_table_size_formatted(); ?>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('Errors (last 24h)', 'multisite-network-email-manager'); ?></th>
                        <td><?php echo esc_html((string) $error_summary['errors_24h']); ?>
                            <?php if ($error_summary['errors_24h'] > 0) : ?>
                                <span style="color:#a00;font-weight:bold;">&#x26a0;</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Most Common Error', 'multisite-network-email-manager'); ?></th>
                        <td><?php echo $error_summary['most_common_type'] !== '' ? esc_html(str_replace('_', ' ', ucfirst($error_summary['most_common_type']))) : esc_html__('None', 'multisite-network-email-manager'); ?></td>
                    </tr>
                    <?php if (!empty($error_summary['most_recent'])) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Most Recent Error', 'multisite-network-email-manager'); ?></th>
                        <td>
                            <?php echo esc_html($error_summary['most_recent']['created_at']); ?><br />
                            <span class="description"><?php echo esc_html(mb_strlen($error_summary['most_recent']['error_message']) > 60 ? mb_substr($error_summary['most_recent']['error_message'], 0, 60) . '…' : $error_summary['most_recent']['error_message']); ?></span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Error Log DB Size', 'multisite-network-email-manager'); ?></th>
                        <td>
                            <?php echo esc_html($table_size); ?>
                            <span class="description">(<?php echo esc_html(sprintf(__('auto-cleanup after %d days', 'multisite-network-email-manager'), (int) \MNEM\ErrorLog::ERROR_LOG_RETENTION_DAYS)); ?>)</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <?php if ($error_summary['errors_24h'] > 0) : ?>
                <div class="mnem-actions">
                    <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-error-logs')); ?>" class="button"><?php esc_html_e('View Errors', 'multisite-network-email-manager'); ?></a>
                </div>
            <?php endif; ?>
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
        <h2>Top Sending Sites</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Blog ID</th>
                    <th>Status</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($site_breakdown)) : ?>
                    <tr>
                        <td colspan="3">No site email activity yet.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($site_breakdown as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $row['blog_id']); ?></td>
                            <td><?php echo esc_html((string) $row['status']); ?></td>
                            <td><?php echo esc_html((string) $row['total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>

    <div class="mnem-panel mnem-panel-wide">
    <h2>Activity Timeline</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Blog ID</th>
                <th>Status</th>
                <th>Event</th>
                <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_logs)) : ?>
                    <tr>
                        <td colspan="4">No log entries yet.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($recent_logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html(isset($log['blog_id']) ? (string) $log['blog_id'] : '0'); ?></td>
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
