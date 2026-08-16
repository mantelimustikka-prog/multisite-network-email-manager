<?php

namespace MNEM\Admin;

defined('ABSPATH') || exit;

class TableDiagnostics
{
    public function init()
    {
        add_action('network_admin_menu', array($this, 'register_submenu'), 20);
    }

    public function register_submenu()
    {
        add_submenu_page(
            'mnem-dashboard',
            'DB Table Diagnosis',
            'DB Table Diagnosis',
            'manage_network_options',
            'mnem-table-diagnosis',
            array($this, 'render_page')
        );
    }

    public function render_page()
    {
        $diagnostics = self::collect_diagnostics();
        $generated_at = gmdate('Y-m-d H:i:s') . ' UTC';

        $this->render_template('table-diagnostics.php', compact('diagnostics', 'generated_at'));
    }

    public static function collect_diagnostics()
    {
        global $wpdb;

        $schema = \MNEM\Installer::get_table_schema();
        $tables = array();
        $recommendations = array();

        foreach ($schema as $table_key => $expected) {
            if (!isset($expected['name'])) {
                continue;
            }

            $table_name = (string) $expected['name'];
            $exists = false;
            if (isset($wpdb) && is_object($wpdb) && method_exists($wpdb, 'get_var') && method_exists($wpdb, 'prepare')) {
                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
            }

            $table_info = array(
                'key' => $table_key,
                'name' => $table_name,
                'exists' => $exists,
                'rows' => 0,
                'size_bytes' => 0,
                'size_human' => '0 B',
                'last_modified' => '',
                'charset' => '',
                'collation' => '',
                'missing_columns' => array(),
                'wrong_column_types' => array(),
                'missing_indexes' => array(),
            );

            if ($exists && isset($wpdb) && is_object($wpdb) && method_exists($wpdb, 'get_results')) {
                $table_info = self::enrich_table_details($table_info, $expected, $wpdb);
            } else {
                $table_info['missing_columns'] = array_keys(isset($expected['columns']) && is_array($expected['columns']) ? $expected['columns'] : array());
                $table_info['missing_indexes'] = array_keys(isset($expected['indexes']) && is_array($expected['indexes']) ? $expected['indexes'] : array());
            }

            if (!$table_info['exists']) {
                $recommendations[] = sprintf('Table %s is missing. Recreate tables.', $table_info['name']);
            }
            if (!empty($table_info['wrong_column_types']) || !empty($table_info['missing_columns']) || !empty($table_info['missing_indexes'])) {
                $recommendations[] = sprintf('Table %s has schema mismatches. Re-run table creation.', $table_info['name']);
            }

            $tables[] = $table_info;
        }

        if (empty($recommendations)) {
            $recommendations[] = 'All required tables are present and match expected schema.';
        }

        return array(
            'generated_at' => gmdate('c'),
            'tables' => $tables,
            'recommendations' => array_values(array_unique($recommendations)),
        );
    }

    public static function recreate_missing_tables()
    {
        \MNEM\Installer::install();
        $diagnostics = self::collect_diagnostics();

        return array(
            'success' => true,
            'message' => 'Recreate operation completed.',
            'diagnostics' => $diagnostics,
        );
    }

    public static function optimize_tables()
    {
        return self::run_maintenance_query('OPTIMIZE TABLE', 'Table optimization completed.');
    }

    public static function repair_tables()
    {
        return self::run_maintenance_query('REPAIR TABLE', 'Table repair completed.', true);
    }

    public static function get_database_statistics()
    {
        $diagnostics = self::collect_diagnostics();
        $stats = array(
            'total_tables'       => count($diagnostics['tables']),
            'total_records'      => 0,
            'total_size_bytes'   => 0,
            'tables_by_size'     => array(),
            'tables_by_records'  => array(),
        );

        foreach ($diagnostics['tables'] as $table) {
            if (empty($table['exists'])) {
                continue;
            }

            $stats['total_records']    += $table['rows'];
            $stats['total_size_bytes'] += $table['size_bytes'];

            $stats['tables_by_size'][] = array(
                'name'       => $table['name'],
                'size_bytes' => $table['size_bytes'],
                'size_human' => $table['size_human'],
            );

            $stats['tables_by_records'][] = array(
                'name' => $table['name'],
                'rows' => $table['rows'],
            );
        }

        usort(
            $stats['tables_by_size'],
            function ($a, $b) {
                return $b['size_bytes'] - $a['size_bytes'];
            }
        );

        usort(
            $stats['tables_by_records'],
            function ($a, $b) {
                return $b['rows'] - $a['rows'];
            }
        );

        return $stats;
    }

    public static function check_data_integrity()
    {
        global $wpdb;

        $issues             = array();
        $queue_table        = $wpdb->base_prefix . 'mnem_queue';
        $campaigns_table    = $wpdb->base_prefix . 'mnem_campaigns';
        $suppression_table  = $wpdb->base_prefix . 'mnem_suppression';

        // Check 1: Orphaned queue records (no matching campaign).
        $orphaned_queue = (int) $wpdb->get_var(
            "SELECT COUNT(1) FROM {$queue_table} q
             WHERE q.campaign_id > 0
             AND NOT EXISTS (SELECT 1 FROM {$campaigns_table} c WHERE c.id = q.campaign_id)"
        );
        if ($orphaned_queue > 0) {
            $issues[] = array(
                'severity'    => 'warning',
                'type'        => 'orphaned_queue_records',
                'title'       => 'Orphaned Queue Records',
                'description' => "Found {$orphaned_queue} queue records with non-existent campaign references.",
                'count'       => $orphaned_queue,
                'action'      => 'cleanup_orphaned_queue',
            );
        }

        // Check 2: Invalid email addresses in queue.
        $invalid_emails_queue = (int) $wpdb->get_var(
            "SELECT COUNT(1) FROM {$queue_table} WHERE recipient_email = '' OR recipient_email IS NULL"
        );
        if ($invalid_emails_queue > 0) {
            $issues[] = array(
                'severity'    => 'error',
                'type'        => 'invalid_emails_queue',
                'title'       => 'Invalid Email Addresses (Queue)',
                'description' => "Found {$invalid_emails_queue} queue records with empty/invalid email addresses.",
                'count'       => $invalid_emails_queue,
                'action'      => 'cleanup_invalid_emails_queue',
            );
        }

        // Check 3: Invalid email addresses in suppression.
        $invalid_emails_suppression = (int) $wpdb->get_var(
            "SELECT COUNT(1) FROM {$suppression_table} WHERE email = '' OR email IS NULL"
        );
        if ($invalid_emails_suppression > 0) {
            $issues[] = array(
                'severity'    => 'error',
                'type'        => 'invalid_emails_suppression',
                'title'       => 'Invalid Email Addresses (Suppression)',
                'description' => "Found {$invalid_emails_suppression} suppression records with empty/invalid email addresses.",
                'count'       => $invalid_emails_suppression,
                'action'      => 'cleanup_invalid_emails_suppression',
            );
        }

        // Check 4: Duplicate suppression entries.
        $duplicate_suppressions = (int) $wpdb->get_var(
            "SELECT COUNT(1) FROM (
                SELECT email, site_id, COUNT(*) as cnt FROM {$suppression_table}
                WHERE email IS NOT NULL AND email <> ''
                GROUP BY site_id, email HAVING cnt > 1
            ) as dupes"
        );
        if ($duplicate_suppressions > 0) {
            $issues[] = array(
                'severity'    => 'warning',
                'type'        => 'duplicate_suppressions',
                'title'       => 'Duplicate Suppression Entries',
                'description' => "Found {$duplicate_suppressions} duplicate email addresses in suppression list.",
                'count'       => $duplicate_suppressions,
                'action'      => 'cleanup_duplicate_suppressions',
            );
        }

        // Check 5: Stuck processing emails (older than 1 hour).
        $stuck_processing = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$queue_table} WHERE status = %s AND scheduled_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                'processing'
            )
        );
        if ($stuck_processing > 0) {
            $issues[] = array(
                'severity'    => 'warning',
                'type'        => 'stuck_processing',
                'title'       => 'Stuck Processing Emails',
                'description' => "Found {$stuck_processing} emails stuck in 'processing' status for over 1 hour.",
                'count'       => $stuck_processing,
                'action'      => 'recover_stuck_processing',
            );
        }

        return $issues;
    }

    public static function cleanup_orphaned_queue()
    {
        global $wpdb;

        $queue_table     = $wpdb->base_prefix . 'mnem_queue';
        $campaigns_table = $wpdb->base_prefix . 'mnem_campaigns';

        $deleted = $wpdb->query(
            "DELETE FROM {$queue_table} q
             WHERE q.campaign_id > 0
             AND NOT EXISTS (SELECT 1 FROM {$campaigns_table} c WHERE c.id = q.campaign_id)"
        );

        \MNEM\Logger::info('Cleanup: Deleted orphaned queue records', array('count' => $deleted));

        return array(
            'success' => true,
            'message' => "Deleted {$deleted} orphaned queue records.",
            'deleted' => $deleted,
        );
    }

    public static function cleanup_invalid_emails_queue()
    {
        global $wpdb;

        $queue_table = $wpdb->base_prefix . 'mnem_queue';

        $deleted = $wpdb->query(
            "DELETE FROM {$queue_table} WHERE recipient_email = '' OR recipient_email IS NULL"
        );

        \MNEM\Logger::info('Cleanup: Deleted invalid email addresses from queue', array('count' => $deleted));

        return array(
            'success' => true,
            'message' => "Deleted {$deleted} queue records with invalid emails.",
            'deleted' => $deleted,
        );
    }

    public static function cleanup_invalid_emails_suppression()
    {
        global $wpdb;

        $suppression_table = $wpdb->base_prefix . 'mnem_suppression';

        $deleted = $wpdb->query(
            "DELETE FROM {$suppression_table} WHERE email = '' OR email IS NULL"
        );

        \MNEM\Logger::info('Cleanup: Deleted invalid email addresses from suppression', array('count' => $deleted));

        return array(
            'success' => true,
            'message' => "Deleted {$deleted} suppression records with invalid emails.",
            'deleted' => $deleted,
        );
    }

    public static function cleanup_duplicate_suppressions()
    {
        global $wpdb;

        $suppression_table = $wpdb->base_prefix . 'mnem_suppression';

        // Keep the oldest record for each email/site_id combo, delete duplicates.
        $deleted = $wpdb->query(
            "DELETE FROM {$suppression_table}
             WHERE email IS NOT NULL AND email <> ''
             AND id NOT IN (
                SELECT MIN(id) FROM (
                    SELECT MIN(id) as id FROM {$suppression_table}
                    WHERE email IS NOT NULL AND email <> ''
                    GROUP BY site_id, email
                ) as keep_ids
             )"
        );

        \MNEM\Logger::info('Cleanup: Removed duplicate suppression entries', array('count' => $deleted));

        return array(
            'success' => true,
            'message' => "Deleted {$deleted} duplicate suppression entries.",
            'deleted' => $deleted,
        );
    }

    public static function recover_stuck_processing()
    {
        $recovered = \MNEM\Queue::recover_stuck_processing_rows();

        return array(
            'success'   => true,
            'message'   => "Recovered {$recovered} stuck processing emails.",
            'recovered' => $recovered,
        );
    }

    public static function export_report($format = 'json')
    {
        $diagnostics = self::collect_diagnostics();
        if ($format === 'text') {
            return self::export_text($diagnostics);
        }

        return wp_json_encode($diagnostics, JSON_PRETTY_PRINT);
    }

    private static function enrich_table_details(array $table_info, array $expected, $wpdb)
    {
        $table_name = $table_info['name'];
        $quoted_table_name = self::quote_table_name($table_name);
        $table_info['rows'] = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$quoted_table_name}");

        $status_row = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS LIKE %s', $table_name), ARRAY_A);
        if (is_array($status_row)) {
            $data_length = isset($status_row['Data_length']) ? (int) $status_row['Data_length'] : 0;
            $index_length = isset($status_row['Index_length']) ? (int) $status_row['Index_length'] : 0;
            $table_info['size_bytes'] = $data_length + $index_length;
            $table_info['size_human'] = self::format_bytes($table_info['size_bytes']);
            $table_info['last_modified'] = isset($status_row['Update_time']) ? (string) $status_row['Update_time'] : '';
            $table_info['collation'] = isset($status_row['Collation']) ? (string) $status_row['Collation'] : '';
            $table_info['charset'] = self::get_charset_from_collation($table_info['collation']);
        }

        $actual_columns = array();
        $column_rows = $wpdb->get_results("SHOW COLUMNS FROM {$quoted_table_name}", ARRAY_A);
        foreach ((array) $column_rows as $column_row) {
            if (!isset($column_row['Field'])) {
                continue;
            }
            $actual_columns[(string) $column_row['Field']] = strtolower((string) (isset($column_row['Type']) ? $column_row['Type'] : ''));
        }

        $expected_columns = isset($expected['columns']) && is_array($expected['columns']) ? $expected['columns'] : array();
        foreach ($expected_columns as $column_name => $column_type) {
            if (!array_key_exists($column_name, $actual_columns)) {
                $table_info['missing_columns'][] = $column_name;
                continue;
            }
            $expected_type = strtolower((string) $column_type);
            if ($actual_columns[$column_name] !== $expected_type) {
                $table_info['wrong_column_types'][] = array(
                    'column' => $column_name,
                    'expected' => $expected_type,
                    'actual' => $actual_columns[$column_name],
                );
            }
        }

        $actual_indexes = array();
        $index_rows = $wpdb->get_results("SHOW INDEX FROM {$quoted_table_name}", ARRAY_A);
        foreach ((array) $index_rows as $index_row) {
            if (!isset($index_row['Key_name'], $index_row['Column_name'])) {
                continue;
            }
            $index_name = strtoupper((string) $index_row['Key_name']) === 'PRIMARY' ? 'PRIMARY' : (string) $index_row['Key_name'];
            if (!isset($actual_indexes[$index_name])) {
                $actual_indexes[$index_name] = array();
            }
            $sequence = isset($index_row['Seq_in_index']) ? (int) $index_row['Seq_in_index'] : (count($actual_indexes[$index_name]) + 1);
            $actual_indexes[$index_name][$sequence] = (string) $index_row['Column_name'];
        }

        foreach ($actual_indexes as $index_name => $index_columns) {
            ksort($index_columns);
            $actual_indexes[$index_name] = array_values($index_columns);
        }

        $expected_indexes = isset($expected['indexes']) && is_array($expected['indexes']) ? $expected['indexes'] : array();
        foreach ($expected_indexes as $index_name => $expected_columns_for_index) {
            if (!isset($actual_indexes[$index_name])) {
                $table_info['missing_indexes'][] = $index_name;
                continue;
            }

            if (array_values((array) $actual_indexes[$index_name]) !== array_values((array) $expected_columns_for_index)) {
                $table_info['missing_indexes'][] = $index_name;
            }
        }

        return $table_info;
    }

    private static function run_maintenance_query($operation_sql, $message, $log_results = false)
    {
        global $wpdb;

        $diagnostics = self::collect_diagnostics();
        $results = array();
        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'query')) {
            return array(
                'success' => false,
                'message' => 'Database object not available.',
                'results' => $results,
                'diagnostics' => $diagnostics,
            );
        }

        foreach ($diagnostics['tables'] as $table) {
            if (empty($table['exists'])) {
                continue;
            }

            $query = $operation_sql . ' ' . self::quote_table_name($table['name']);
            $query_result = $wpdb->query($query);
            $entry = array(
                'table' => $table['name'],
                'query' => $query,
                'result' => $query_result,
                'success' => $query_result !== false,
            );

            if ($log_results) {
                \MNEM\Logger::info(
                    'Table diagnostics maintenance',
                    array(
                        'operation' => $operation_sql,
                        'table' => $table['name'],
                        'success' => $entry['success'],
                    )
                );
            }

            $results[] = $entry;
        }

        return array(
            'success' => true,
            'message' => $message,
            'results' => $results,
            'diagnostics' => $diagnostics,
        );
    }

    private static function export_text(array $diagnostics)
    {
        $lines = array();
        $lines[] = 'MNEM Table Diagnostics Report';
        $lines[] = 'Generated at: ' . (isset($diagnostics['generated_at']) ? $diagnostics['generated_at'] : gmdate('c'));
        $lines[] = '';

        foreach ((array) $diagnostics['tables'] as $table) {
            $lines[] = sprintf('[%s] %s', !empty($table['exists']) ? 'OK' : 'MISSING', isset($table['name']) ? $table['name'] : '');
            $lines[] = sprintf('Rows: %d | Size: %s | Last Modified: %s', isset($table['rows']) ? (int) $table['rows'] : 0, isset($table['size_human']) ? (string) $table['size_human'] : '0 B', isset($table['last_modified']) ? (string) $table['last_modified'] : '');
            $lines[] = sprintf('Charset/Collation: %s / %s', isset($table['charset']) ? (string) $table['charset'] : '', isset($table['collation']) ? (string) $table['collation'] : '');
            if (!empty($table['missing_columns'])) {
                $lines[] = 'Missing Columns: ' . implode(', ', $table['missing_columns']);
            }
            if (!empty($table['wrong_column_types'])) {
                foreach ($table['wrong_column_types'] as $mismatch) {
                    $lines[] = sprintf('Column Type Mismatch: %s (expected %s, actual %s)', $mismatch['column'], $mismatch['expected'], $mismatch['actual']);
                }
            }
            if (!empty($table['missing_indexes'])) {
                $lines[] = 'Missing/Invalid Indexes: ' . implode(', ', $table['missing_indexes']);
            }
            $lines[] = '';
        }

        $lines[] = 'Recommendations:';
        foreach ((array) $diagnostics['recommendations'] as $recommendation) {
            $lines[] = '- ' . $recommendation;
        }

        return implode("\n", $lines);
    }

    private static function get_charset_from_collation($collation)
    {
        if ($collation === '' || strpos((string) $collation, '_') === false) {
            return '';
        }

        $parts = explode('_', (string) $collation, 2);
        return isset($parts[0]) ? $parts[0] : '';
    }

    public static function format_bytes_public($bytes)
    {
        return self::format_bytes($bytes);
    }

    private static function format_bytes($bytes)
    {
        $bytes = (float) max(0, (int) $bytes);
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $power = 0;
        while ($bytes >= 1024 && $power < count($units) - 1) {
            $bytes /= 1024;
            $power++;
        }

        if ($power === 0) {
            return (string) (int) $bytes . ' ' . $units[$power];
        }

        return number_format($bytes, 2) . ' ' . $units[$power];
    }

    private static function quote_table_name($table_name)
    {
        $clean_name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $table_name);
        return '`' . $clean_name . '`';
    }

    private function render_template($template, array $variables)
    {
        $file = MNEM_PLUGIN_DIR . 'admin/templates/' . $template;
        if (!file_exists($file)) {
            echo '<div class="wrap"><p>Table diagnostics template not found.</p></div>';
            return;
        }

        extract($variables, EXTR_SKIP);
        include $file;
    }
}
