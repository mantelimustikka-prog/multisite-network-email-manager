<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-error-logs">
    <h1>Email Errors</h1>

    <?php if ($notice_message !== '') : ?>
        <div class="<?php echo esc_attr($notice_class); ?>"><p><?php echo esc_html($notice_message); ?></p></div>
    <?php endif; ?>

    <!-- Filters -->
    <form method="get" class="mnem-error-log-filters">
        <input type="hidden" name="page" value="mnem-error-logs" />
        <div class="tablenav top">
            <div class="alignleft actions">
                <select name="error_level">
                    <option value=""><?php esc_html_e('All Levels', 'multisite-network-email-manager'); ?></option>
                    <?php foreach ($allowed_levels as $lvl) : ?>
                        <option value="<?php echo esc_attr($lvl); ?>"<?php selected(isset($filters['error_level']) && $filters['error_level'] === $lvl); ?>>
                            <?php echo esc_html(ucfirst($lvl)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="error_type">
                    <option value=""><?php esc_html_e('All Types', 'multisite-network-email-manager'); ?></option>
                    <?php foreach ($allowed_types as $type) : ?>
                        <option value="<?php echo esc_attr($type); ?>"<?php selected(isset($filters['error_type']) && $filters['error_type'] === $type); ?>>
                            <?php echo esc_html(str_replace('_', ' ', ucfirst($type))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="recipient_email" placeholder="<?php esc_attr_e('Recipient email', 'multisite-network-email-manager'); ?>"
                    value="<?php echo esc_attr(isset($filters['recipient_email']) ? $filters['recipient_email'] : ''); ?>" style="width:180px;" />
                <input type="text" name="provider_type" placeholder="<?php esc_attr_e('Provider', 'multisite-network-email-manager'); ?>"
                    value="<?php echo esc_attr(isset($filters['provider_type']) ? $filters['provider_type'] : ''); ?>" style="width:100px;" />
                <input type="date" name="date_from" value="<?php echo esc_attr(isset($filters['date_from']) ? $filters['date_from'] : ''); ?>" title="<?php esc_attr_e('From date', 'multisite-network-email-manager'); ?>" />
                <input type="date" name="date_to" value="<?php echo esc_attr(isset($filters['date_to']) ? $filters['date_to'] : ''); ?>" title="<?php esc_attr_e('To date', 'multisite-network-email-manager'); ?>" />
                <?php submit_button(__('Filter', 'multisite-network-email-manager'), 'secondary action', '', false); ?>
                <?php if (!empty($filters)) : ?>
                    <a href="<?php echo esc_url(network_admin_url('admin.php?page=mnem-error-logs')); ?>" class="button"><?php esc_html_e('Clear', 'multisite-network-email-manager'); ?></a>
                <?php endif; ?>
            </div>
            <div class="alignleft actions">
                <input type="search" name="s" value="<?php echo esc_attr(isset($filters['search']) ? $filters['search'] : ''); ?>"
                    placeholder="<?php esc_attr_e('Search errors&hellip;', 'multisite-network-email-manager'); ?>" style="width:220px;" />
                <?php submit_button(__('Search', 'multisite-network-email-manager'), 'secondary action', '', false); ?>
            </div>
            <div class="alignright actions">
                <button type="button" class="button" id="mnem-export-errors"><?php esc_html_e('Export CSV', 'multisite-network-email-manager'); ?></button>
                <button type="button" class="button" id="mnem-clear-old-errors" data-days="30"><?php esc_html_e('Clear Errors &gt;30 Days', 'multisite-network-email-manager'); ?></button>
            </div>
        </div>
    </form>

    <!-- Totals -->
    <p class="description">
        <?php
        printf(
            /* translators: %d: total error count */
            esc_html(_n('%d error found.', '%d errors found.', $total_items, 'multisite-network-email-manager')),
            (int) $total_items
        );
        ?>
    </p>

    <!-- Table -->
    <table class="widefat striped mnem-error-logs-table">
        <thead>
            <tr>
                <th><?php esc_html_e('Date', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Level', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Type', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Recipient', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Subject', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Provider', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Error Message', 'multisite-network-email-manager'); ?></th>
                <th><?php esc_html_e('Actions', 'multisite-network-email-manager'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)) : ?>
                <tr>
                    <td colspan="8"><?php esc_html_e('No error log entries found.', 'multisite-network-email-manager'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($items as $item) : ?>
                    <tr>
                        <td><?php echo esc_html(isset($item['created_at']) ? $item['created_at'] : ''); ?></td>
                        <td>
                            <span class="mnem-badge mnem-level-<?php echo esc_attr(isset($item['error_level']) ? $item['error_level'] : ''); ?>">
                                <?php echo esc_html(isset($item['error_level']) ? ucfirst($item['error_level']) : ''); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(isset($item['error_type']) ? str_replace('_', ' ', ucfirst($item['error_type'])) : ''); ?></td>
                        <td><?php echo esc_html(isset($item['recipient_email']) ? $item['recipient_email'] : '—'); ?></td>
                        <td><?php echo esc_html(isset($item['subject']) ? (mb_strlen($item['subject']) > 40 ? mb_substr($item['subject'], 0, 40) . '…' : $item['subject']) : '—'); ?></td>
                        <td><?php echo esc_html(isset($item['provider_type']) ? $item['provider_type'] : '—'); ?></td>
                        <td title="<?php echo esc_attr(isset($item['error_message']) ? $item['error_message'] : ''); ?>">
                            <?php
                            $msg = isset($item['error_message']) ? (string) $item['error_message'] : '';
                            echo esc_html(mb_strlen($msg) > 80 ? mb_substr($msg, 0, 80) . '…' : $msg);
                            ?>
                        </td>
                        <td>
                            <button type="button" class="button button-small mnem-view-error-details"
                                data-log-id="<?php echo esc_attr((string) ($item['id'] ?? '')); ?>">
                                <?php esc_html_e('Details', 'multisite-network-email-manager'); ?>
                            </button>
                            <button type="button" class="button button-small button-link-delete mnem-delete-error-log"
                                data-log-id="<?php echo esc_attr((string) ($item['id'] ?? '')); ?>">
                                <?php esc_html_e('Delete', 'multisite-network-email-manager'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <?php if ($total_pages > 1) : ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $base_url = network_admin_url('admin.php?page=mnem-error-logs');
                foreach ($filters as $k => $v) {
                    $base_url = add_query_arg(esc_attr($k), esc_attr($v), $base_url);
                }
                ?>
                <span class="displaying-num">
                    <?php
                    printf(
                        /* translators: 1: current page, 2: total pages */
                        esc_html__('Page %1$s of %2$s', 'multisite-network-email-manager'),
                        (int) $current_page,
                        (int) $total_pages
                    );
                    ?>
                </span>
                <?php if ($current_page > 1) : ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg('paged', $current_page - 1, $base_url)); ?>">&laquo; <?php esc_html_e('Prev', 'multisite-network-email-manager'); ?></a>
                <?php endif; ?>
                <?php if ($current_page < $total_pages) : ?>
                    <a class="button" href="<?php echo esc_url(add_query_arg('paged', $current_page + 1, $base_url)); ?>"><?php esc_html_e('Next', 'multisite-network-email-manager'); ?> &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Details Modal -->
    <?php include MNEM_PLUGIN_DIR . 'admin/views/partials/error-details-modal.php'; ?>
</div>

<style>
.mnem-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 12px; font-weight: 600; }
.mnem-level-error    { background: #f8d7da; color: #721c24; }
.mnem-level-warning  { background: #fff3cd; color: #856404; }
.mnem-level-critical { background: #f5c2c7; color: #58151c; }
#mnem-error-details-modal { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); z-index:99999; overflow-y:auto; }
#mnem-error-details-modal .mnem-modal-inner { background:#fff; max-width:900px; margin:40px auto; padding:24px; border-radius:4px; }
#mnem-error-details-modal h2 { margin-top:0; }
.mnem-modal-section { margin-bottom:20px; }
.mnem-modal-section h3 { border-bottom:1px solid #ddd; padding-bottom:4px; }
.mnem-detail-table { width:100%; border-collapse:collapse; }
.mnem-detail-table th { text-align:left; width:180px; padding:4px 8px; background:#f9f9f9; }
.mnem-detail-table td { padding:4px 8px; vertical-align:top; word-break:break-all; }
pre.mnem-stack-trace { background:#f6f6f6; padding:12px; overflow:auto; max-height:300px; font-size:11px; }
</style>

<script>
(function($) {
    var nonce = '<?php echo esc_js(wp_create_nonce('mnem_dashboard_ajax')); ?>';

    // View error details
    $(document).on('click', '.mnem-view-error-details', function() {
        var logId = $(this).data('log-id');
        $.post(ajaxurl, { action: 'mnem_get_error_details', log_id: logId, nonce: nonce }, function(res) {
            if (res.success && res.data) {
                populateModal(res.data);
                $('#mnem-error-details-modal').show();
            } else {
                alert('Could not load error details.');
            }
        });
    });

    // Close modal
    $(document).on('click', '#mnem-modal-close, #mnem-error-details-modal', function(e) {
        if (e.target === this) { $('#mnem-error-details-modal').hide(); }
    });
    $(document).on('click', '#mnem-modal-close', function() { $('#mnem-error-details-modal').hide(); });

    // Delete error log
    $(document).on('click', '.mnem-delete-error-log', function() {
        if (!confirm('Delete this error log entry?')) return;
        var logId = $(this).data('log-id');
        var $row  = $(this).closest('tr');
        $.post(ajaxurl, { action: 'mnem_delete_error_log', log_id: logId, nonce: nonce }, function(res) {
            if (res.success) { $row.fadeOut(); } else { alert('Could not delete.'); }
        });
    });

    // Export CSV
    $('#mnem-export-errors').on('click', function() {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = ajaxurl;
        var fields = { action: 'mnem_export_error_logs', nonce: nonce };
        // pass current filters
        var params = new URLSearchParams(window.location.search);
        ['error_level','error_type','provider_type'].forEach(function(k) {
            if (params.get(k)) fields[k] = params.get(k);
        });
        Object.keys(fields).forEach(function(k) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = k; i.value = fields[k];
            form.appendChild(i);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    // Clear old errors
    $('#mnem-clear-old-errors').on('click', function() {
        var days = parseInt($(this).data('days'), 10) || 30;
        if (!confirm('Delete all error logs older than ' + days + ' days?')) return;
        $.post(ajaxurl, { action: 'mnem_clear_old_errors', days: days, nonce: nonce }, function(res) {
            if (res.success) { alert(res.data.message); location.reload(); }
            else { alert('Could not clear logs.'); }
        });
    });

    function esc(str) {
        if (!str) return '—';
        return $('<div/>').text(str).html();
    }

    function populateModal(d) {
        var html = '';

        html += '<div class="mnem-modal-section">';
        html += '<h3>Error Details</h3>';
        html += '<table class="mnem-detail-table">';
        html += row('Date/Time', d.created_at);
        html += row('Error Type', d.error_type ? d.error_type.replace(/_/g,' ') : '');
        html += row('Error Level', d.error_level);
        html += row('Error Code', d.error_code);
        html += '</table></div>';

        html += '<div class="mnem-modal-section">';
        html += '<h3>Plugin Error Message</h3>';
        html += '<pre style="white-space:pre-wrap;word-break:break-all;">' + esc(d.error_message) + '</pre>';
        html += '</div>';

        if (d.recipient_email || d.sender_email || d.subject || d.queue_id || d.campaign_id) {
            html += '<div class="mnem-modal-section">';
            html += '<h3>Email Information</h3>';
            html += '<table class="mnem-detail-table">';
            html += row('Recipient', d.recipient_email);
            html += row('Sender', d.sender_email);
            html += row('Subject', d.subject);
            html += row('Queue ID', d.queue_id);
            html += row('Campaign ID', d.campaign_id);
            html += '</table></div>';
        }

        if (d.provider_type || d.http_status_code || d.provider_error_code || d.provider_error_message || d.api_response) {
            html += '<div class="mnem-modal-section">';
            html += '<h3>Provider Information</h3>';
            html += '<table class="mnem-detail-table">';
            html += row('Provider', d.provider_type);
            html += row('HTTP Status', d.http_status_code);
            html += row('Provider Error Code', d.provider_error_code);
            html += row('Provider Error', d.provider_error_message);
            html += '</table>';
            if (d.api_response) {
                var apiJson = d.api_response;
                try { apiJson = JSON.stringify(JSON.parse(d.api_response), null, 2); } catch(e) {}
                html += '<strong>API Response:</strong><pre style="white-space:pre-wrap;max-height:200px;overflow:auto;">' + esc(apiJson) + '</pre>';
            }
            html += '</div>';
        }

        if (d.system_error) {
            html += '<div class="mnem-modal-section">';
            html += '<h3>System Error</h3>';
            html += '<pre style="white-space:pre-wrap;word-break:break-all;">' + esc(d.system_error) + '</pre>';
            html += '</div>';
        }

        if (d.stack_trace) {
            html += '<div class="mnem-modal-section">';
            html += '<h3>Stack Trace</h3>';
            html += '<pre class="mnem-stack-trace">' + esc(d.stack_trace) + '</pre>';
            html += '</div>';
        }

        if (d.context) {
            var ctx = d.context;
            try { ctx = JSON.stringify(JSON.parse(d.context), null, 2); } catch(e) {}
            html += '<div class="mnem-modal-section">';
            html += '<h3>Additional Context</h3>';
            html += '<pre style="white-space:pre-wrap;max-height:200px;overflow:auto;">' + esc(ctx) + '</pre>';
            html += '</div>';
        }

        $('#mnem-error-details-body').html(html);
    }

    function row(label, value) {
        if (!value && value !== 0) return '';
        return '<tr><th>' + esc(label) + '</th><td>' + esc(String(value)) + '</td></tr>';
    }
}(jQuery));
</script>
