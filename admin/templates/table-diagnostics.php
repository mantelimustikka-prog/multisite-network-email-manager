<?php

defined('ABSPATH') || exit;
?>
<div class="wrap mnem-dashboard">
    <h1>Table Diagnosis</h1>
    <p class="description">Checks required MNEM database tables, schema compatibility, and maintenance status.</p>
    <p class="description">Generated at: <?php echo esc_html($generated_at); ?></p>

    <div class="mnem-panel">
        <h2>Maintenance Operations</h2>
        <p>
            <button type="button" class="button button-primary" data-mnem-table-action="recreate">Recreate Missing Tables</button>
            <button type="button" class="button" data-mnem-table-action="optimize">Optimize Tables</button>
            <button type="button" class="button" data-mnem-table-action="repair">Repair Tables</button>
        </p>
        <p class="description">Operations run via AJAX and results are logged in MNEM logs.</p>
    </div>

    <div class="mnem-panel">
        <h2>Export Diagnostics</h2>
        <p>
            <button type="button" class="button" data-mnem-table-export="json">Export JSON</button>
            <button type="button" class="button" data-mnem-table-export="text">Export Text</button>
        </p>
    </div>

    <div class="mnem-panel">
        <h2>Table Status</h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Table</th>
                    <th>Status</th>
                    <th>Schema</th>
                    <th>Rows</th>
                    <th>Size</th>
                    <th>Last Modified</th>
                    <th>Charset</th>
                    <th>Collation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ((array) $diagnostics['tables'] as $table) : ?>
                    <?php
                    $schema_ok = !empty($table['exists']) && empty($table['missing_columns']) && empty($table['wrong_column_types']) && empty($table['missing_indexes']);
                    ?>
                    <tr>
                        <td><code><?php echo esc_html(isset($table['name']) ? $table['name'] : ''); ?></code></td>
                        <td><?php echo !empty($table['exists']) ? '&#10003; Exists' : '&#10007; Missing'; ?></td>
                        <td>
                            <?php if ($schema_ok) : ?>
                                <?php echo esc_html('Compatible'); ?>
                            <?php else : ?>
                                <strong><?php echo esc_html('Mismatch'); ?></strong>
                                <ul>
                                    <?php foreach ((array) (isset($table['missing_columns']) ? $table['missing_columns'] : array()) as $column) : ?>
                                        <li><?php echo esc_html('Missing column: ' . $column); ?></li>
                                    <?php endforeach; ?>
                                    <?php foreach ((array) (isset($table['wrong_column_types']) ? $table['wrong_column_types'] : array()) as $mismatch) : ?>
                                        <li><?php echo esc_html(sprintf('Wrong type: %s (expected %s, actual %s)', $mismatch['column'], $mismatch['expected'], $mismatch['actual'])); ?></li>
                                    <?php endforeach; ?>
                                    <?php foreach ((array) (isset($table['missing_indexes']) ? $table['missing_indexes'] : array()) as $index_name) : ?>
                                        <li><?php echo esc_html('Missing/invalid index: ' . $index_name); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) (isset($table['rows']) ? $table['rows'] : 0)); ?></td>
                        <td><?php echo esc_html(isset($table['size_human']) ? $table['size_human'] : '0 B'); ?></td>
                        <td><?php echo esc_html(isset($table['last_modified']) && $table['last_modified'] !== '' ? $table['last_modified'] : 'N/A'); ?></td>
                        <td><?php echo esc_html(isset($table['charset']) && $table['charset'] !== '' ? $table['charset'] : 'N/A'); ?></td>
                        <td><?php echo esc_html(isset($table['collation']) && $table['collation'] !== '' ? $table['collation'] : 'N/A'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Database Statistics Dashboard -->
    <div class="mnem-panel mnem-stats-dashboard">
        <h2><?php esc_html_e('Database Statistics', 'multisite-network-email-manager'); ?></h2>

        <?php $stats = \MNEM\Admin\TableDiagnostics::get_database_statistics();
        $records_by_name = array();
        foreach ($stats['tables_by_records'] as $r) {
            $records_by_name[$r['name']] = $r['rows'];
        }
        ?>

        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:20px;">
            <div style="background:#fff;padding:15px;border-radius:4px;border-left:4px solid #0073aa;">
                <strong><?php esc_html_e('Total Tables', 'multisite-network-email-manager'); ?></strong>
                <p style="font-size:24px;margin:10px 0 0 0;"><?php echo esc_html($stats['total_tables']); ?></p>
            </div>
            <div style="background:#fff;padding:15px;border-radius:4px;border-left:4px solid #46b450;">
                <strong><?php esc_html_e('Total Records', 'multisite-network-email-manager'); ?></strong>
                <p style="font-size:24px;margin:10px 0 0 0;"><?php echo esc_html(number_format($stats['total_records'])); ?></p>
            </div>
            <div style="background:#fff;padding:15px;border-radius:4px;border-left:4px solid #9c27b0;">
                <strong><?php esc_html_e('Database Size', 'multisite-network-email-manager'); ?></strong>
                <p style="font-size:24px;margin:10px 0 0 0;"><?php echo esc_html(\MNEM\Admin\TableDiagnostics::format_bytes_public($stats['total_size_bytes'])); ?></p>
            </div>
            <div style="background:#fff;padding:15px;border-radius:4px;border-left:4px solid #ff9800;">
                <strong><?php esc_html_e('Largest Table', 'multisite-network-email-manager'); ?></strong>
                <p style="font-size:18px;margin:10px 0 0 0;">
                    <?php if (!empty($stats['tables_by_size'])) : ?>
                        <?php echo esc_html($stats['tables_by_size'][0]['name']); ?>
                        (<?php echo esc_html($stats['tables_by_size'][0]['size_human']); ?>)
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div style="margin-top:20px;">
            <h4><?php esc_html_e('Tables by Size', 'multisite-network-email-manager'); ?></h4>
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr style="background:#f0f0f0;">
                        <th style="padding:10px;text-align:left;border:1px solid #ddd;"><?php esc_html_e('Table Name', 'multisite-network-email-manager'); ?></th>
                        <th style="padding:10px;text-align:right;border:1px solid #ddd;"><?php esc_html_e('Records', 'multisite-network-email-manager'); ?></th>
                        <th style="padding:10px;text-align:right;border:1px solid #ddd;"><?php esc_html_e('Size', 'multisite-network-email-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['tables_by_size'] as $tbl) : ?>
                        <tr style="border:1px solid #ddd;">
                            <td style="padding:10px;"><?php echo esc_html($tbl['name']); ?></td>
                            <td style="padding:10px;text-align:right;">
                                <?php echo esc_html(number_format(isset($records_by_name[$tbl['name']]) ? $records_by_name[$tbl['name']] : 0)); ?>
                            </td>
                            <td style="padding:10px;text-align:right;"><?php echo esc_html($tbl['size_human']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Data Integrity Checks -->
    <div class="mnem-panel mnem-integrity-checks">
        <h2><?php esc_html_e('Data Integrity Checks', 'multisite-network-email-manager'); ?></h2>

        <?php $integrity_issues = \MNEM\Admin\TableDiagnostics::check_data_integrity(); ?>

        <?php if (empty($integrity_issues)) : ?>
            <div style="padding:15px;background:#d4edda;color:#155724;border-radius:4px;">
                <?php esc_html_e('✓ All integrity checks passed. Database is healthy.', 'multisite-network-email-manager'); ?>
            </div>
        <?php else : ?>
            <?php foreach ($integrity_issues as $issue) : ?>
                <?php
                $severity_color  = $issue['severity'] === 'error' ? '#f8d7da' : '#fff3cd';
                $severity_border = $issue['severity'] === 'error' ? '#f5c6cb' : '#ffeaa7';
                $severity_text   = $issue['severity'] === 'error' ? '#721c24' : '#856404';
                ?>
                <div style="padding:15px;margin-bottom:10px;background:<?php echo esc_attr($severity_color); ?>;border:1px solid <?php echo esc_attr($severity_border); ?>;border-radius:4px;color:<?php echo esc_attr($severity_text); ?>;">
                    <strong><?php echo esc_html($issue['title']); ?></strong> (<?php echo esc_html($issue['count']); ?> <?php esc_html_e('issues', 'multisite-network-email-manager'); ?>)
                    <p style="margin:10px 0 0 0;"><?php echo esc_html($issue['description']); ?></p>
                    <button class="button button-small" style="margin-top:8px;" onclick="mnemCleanupIssue('<?php echo esc_attr($issue['action']); ?>')">
                        <?php esc_html_e('Auto-Fix', 'multisite-network-email-manager'); ?>
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function mnemCleanupIssue(action) {
        if (!confirm('<?php echo esc_js(__('Are you sure? This action cannot be undone.', 'multisite-network-email-manager')); ?>')) {
            return;
        }
        jQuery.post(ajaxurl, {
            action: 'mnem_table_diagnostics_cleanup',
            nonce: '<?php echo esc_attr(wp_create_nonce('mnem_table_diagnostics')); ?>',
            cleanup_action: action,
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                location.reload();
            } else {
                alert('<?php echo esc_js(__('Error: ', 'multisite-network-email-manager')); ?>' + response.data.message);
            }
        });
    }
    </script>

    <div class="mnem-panel">
        <h2>Recommendations</h2>
        <ul>
            <?php foreach ((array) $diagnostics['recommendations'] as $recommendation) : ?>
                <li><?php echo esc_html((string) $recommendation); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="mnem-panel">
        <h2>Operation Output</h2>
        <pre id="mnem-table-diagnostics-output"><?php echo esc_html(wp_json_encode($diagnostics, JSON_PRETTY_PRINT)); ?></pre>
    </div>
</div>
