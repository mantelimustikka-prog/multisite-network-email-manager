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
