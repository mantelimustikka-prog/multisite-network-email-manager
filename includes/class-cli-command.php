<?php

namespace MNEM;

defined('ABSPATH') || exit;

class CliCommand
{
    public static function register_commands()
    {
        if (!defined('WP_CLI') || !WP_CLI || !class_exists('\WP_CLI') || !method_exists('\WP_CLI', 'add_command')) {
            return;
        }

        \WP_CLI::add_command('mnem migrate-network-tables', array(self::class, 'migrate_network_tables'));
    }

    public static function migrate_network_tables($args = array(), $assoc_args = array())
    {
        $report = self::execute_migration();
        self::log_report($report);

        if (empty($report['verification']['success'])) {
            if (class_exists('\WP_CLI') && method_exists('\WP_CLI', 'error')) {
                \WP_CLI::error('Migration verification failed. Check MNEM logs for details.');
            }
            return;
        }

        if (class_exists('\WP_CLI') && method_exists('\WP_CLI', 'success')) {
            \WP_CLI::success('Migration completed and verified.');
        }
    }

    public static function execute_migration()
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_col')) {
            return array(
                'before' => array(),
                'after' => array(),
                'verification' => array(
                    'success' => false,
                    'reason' => 'wpdb not available',
                ),
            );
        }

        $before = self::collect_snapshot($wpdb);
        Installer::install();
        Installer::run_migrations();
        $after = self::collect_snapshot($wpdb);
        $verification = self::verify_consolidation($before, $after);

        Logger::info(
            'MNEM network table migration executed.',
            array(
                'verification' => $verification,
                'before' => array(
                    'legacy_totals' => isset($before['legacy_totals']) ? $before['legacy_totals'] : array(),
                    'central_counts' => isset($before['central_counts']) ? $before['central_counts'] : array(),
                ),
                'after' => array(
                    'legacy_totals' => isset($after['legacy_totals']) ? $after['legacy_totals'] : array(),
                    'central_counts' => isset($after['central_counts']) ? $after['central_counts'] : array(),
                ),
            )
        );

        return array(
            'before' => $before,
            'after' => $after,
            'verification' => $verification,
        );
    }

    private static function collect_snapshot($wpdb)
    {
        $base_prefix = (property_exists($wpdb, 'base_prefix') && !empty($wpdb->base_prefix))
            ? (string) $wpdb->base_prefix
            : (string) $wpdb->prefix;

        $central_tables = array(
            'queue' => $base_prefix . 'mnem_queue',
            'campaigns' => $base_prefix . 'mnem_campaigns',
        );

        $column_exists = array();
        $central_counts = array();
        $zero_site_counts = array();

        foreach ($central_tables as $key => $table_name) {
            $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name)) === $table_name;
            $central_counts[$key] = $exists ? self::get_table_row_count($wpdb, $table_name) : 0;
            $column_exists[$key] = $exists ? (bool) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
                $table_name,
                'site_id'
            )) : false;
            $zero_site_counts[$key] = ($exists && $column_exists[$key])
                ? self::get_zero_site_id_row_count($wpdb, $table_name)
                : 0;
        }

        $legacy_queue = self::find_legacy_tables($wpdb, $base_prefix, 'mnem_queue');
        $legacy_campaigns = self::find_legacy_tables($wpdb, $base_prefix, 'mnem_campaigns');

        return array(
            'base_prefix' => $base_prefix,
            'central_tables' => $central_tables,
            'central_counts' => $central_counts,
            'site_id_column_exists' => $column_exists,
            'zero_site_id_counts' => $zero_site_counts,
            'legacy_tables' => array(
                'queue' => $legacy_queue,
                'campaigns' => $legacy_campaigns,
            ),
            'legacy_totals' => array(
                'queue' => self::sum_legacy_rows($legacy_queue),
                'campaigns' => self::sum_legacy_rows($legacy_campaigns),
            ),
        );
    }

    private static function verify_consolidation(array $before, array $after)
    {
        $columns_ok = !empty($after['site_id_column_exists']['queue'])
            && !empty($after['site_id_column_exists']['campaigns']);

        $legacy_removed = empty($after['legacy_tables']['queue']) && empty($after['legacy_tables']['campaigns']);

        $queue_delta = ((int) $after['central_counts']['queue']) - ((int) $before['central_counts']['queue']);
        $campaign_delta = ((int) $after['central_counts']['campaigns']) - ((int) $before['central_counts']['campaigns']);

        $queue_consolidated = (int) $before['legacy_totals']['queue'] === 0 || $queue_delta >= (int) $before['legacy_totals']['queue'];
        $campaign_consolidated = (int) $before['legacy_totals']['campaigns'] === 0 || $campaign_delta >= (int) $before['legacy_totals']['campaigns'];

        $site_ids_set = ((int) $after['zero_site_id_counts']['queue'] === 0)
            && ((int) $after['zero_site_id_counts']['campaigns'] === 0);

        $success = $columns_ok && $legacy_removed && $queue_consolidated && $campaign_consolidated && $site_ids_set;

        return array(
            'success' => $success,
            'checks' => array(
                'site_id_columns_present' => $columns_ok,
                'legacy_tables_removed' => $legacy_removed,
                'queue_rows_consolidated' => $queue_consolidated,
                'campaign_rows_consolidated' => $campaign_consolidated,
                'site_ids_set' => $site_ids_set,
            ),
            'row_deltas' => array(
                'queue' => $queue_delta,
                'campaigns' => $campaign_delta,
            ),
            'legacy_rows_before' => array(
                'queue' => (int) $before['legacy_totals']['queue'],
                'campaigns' => (int) $before['legacy_totals']['campaigns'],
            ),
        );
    }

    private static function log_report(array $report)
    {
        if (!class_exists('\WP_CLI') || !method_exists('\WP_CLI', 'log')) {
            return;
        }

        $verification = isset($report['verification']) ? $report['verification'] : array();
        $checks = isset($verification['checks']) ? $verification['checks'] : array();
        $legacy_rows_before = isset($verification['legacy_rows_before']) ? $verification['legacy_rows_before'] : array();
        $deltas = isset($verification['row_deltas']) ? $verification['row_deltas'] : array();
        $legacy_queue_remaining = isset($report['after']['legacy_tables']['queue']) ? count((array) $report['after']['legacy_tables']['queue']) : 0;
        $legacy_campaigns_remaining = isset($report['after']['legacy_tables']['campaigns']) ? count((array) $report['after']['legacy_tables']['campaigns']) : 0;

        \WP_CLI::log('MNEM migration report:');
        \WP_CLI::log(sprintf(' - site_id columns present: %s', !empty($checks['site_id_columns_present']) ? 'yes' : 'no'));
        \WP_CLI::log(sprintf(' - legacy tables removed: %s (queue=%d, campaigns=%d)', !empty($checks['legacy_tables_removed']) ? 'yes' : 'no', $legacy_queue_remaining, $legacy_campaigns_remaining));
        \WP_CLI::log(sprintf(' - queue consolidated: %s (legacy rows=%d, central delta=%d)', !empty($checks['queue_rows_consolidated']) ? 'yes' : 'no', isset($legacy_rows_before['queue']) ? (int) $legacy_rows_before['queue'] : 0, isset($deltas['queue']) ? (int) $deltas['queue'] : 0));
        \WP_CLI::log(sprintf(' - campaigns consolidated: %s (legacy rows=%d, central delta=%d)', !empty($checks['campaign_rows_consolidated']) ? 'yes' : 'no', isset($legacy_rows_before['campaigns']) ? (int) $legacy_rows_before['campaigns'] : 0, isset($deltas['campaigns']) ? (int) $deltas['campaigns'] : 0));
        \WP_CLI::log(sprintf(' - site_id values set: %s', !empty($checks['site_ids_set']) ? 'yes' : 'no'));
    }

    private static function find_legacy_tables($wpdb, $base_prefix, $table_suffix)
    {
        $prefix_like = method_exists($wpdb, 'esc_like') ? $wpdb->esc_like((string) $base_prefix) : addcslashes((string) $base_prefix, '_%\\');
        $suffix_like = method_exists($wpdb, 'esc_like') ? $wpdb->esc_like((string) $table_suffix) : addcslashes((string) $table_suffix, '_%\\');
        $pattern = $prefix_like . '%\\_' . $suffix_like;

        $tables = (array) $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $pattern));
        $matches = array();
        $regex = '/^' . preg_quote((string) $base_prefix, '/') . '([0-9]+)_' . preg_quote((string) $table_suffix, '/') . '$/';

        foreach ($tables as $table_name) {
            if (!preg_match($regex, (string) $table_name, $parts)) {
                continue;
            }

            $site_id = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($site_id <= 1) {
                continue;
            }

            $matches[] = array(
                'table' => (string) $table_name,
                'site_id' => $site_id,
                'rows' => self::get_table_row_count($wpdb, (string) $table_name),
            );
        }

        return $matches;
    }

    private static function sum_legacy_rows(array $legacy_tables)
    {
        $total = 0;
        foreach ($legacy_tables as $legacy_table) {
            $total += isset($legacy_table['rows']) ? (int) $legacy_table['rows'] : 0;
        }
        return $total;
    }

    private static function get_table_row_count($wpdb, $table_name)
    {
        $quoted_table = self::quote_table_name($table_name);
        if ($quoted_table === '') {
            return 0;
        }

        return (int) $wpdb->get_var('SELECT COUNT(1) FROM ' . $quoted_table);
    }

    private static function get_zero_site_id_row_count($wpdb, $table_name)
    {
        $quoted_table = self::quote_table_name($table_name);
        if ($quoted_table === '') {
            return 0;
        }

        return (int) $wpdb->get_var('SELECT COUNT(1) FROM ' . $quoted_table . ' WHERE site_id = 0');
    }

    private static function quote_table_name($table_name)
    {
        $table_name = (string) $table_name;
        $clean_name = preg_replace('/[^A-Za-z0-9_]/', '', $table_name);
        if ($clean_name === '' || $clean_name !== $table_name) {
            return '';
        }

        return '`' . $clean_name . '`';
    }
}
