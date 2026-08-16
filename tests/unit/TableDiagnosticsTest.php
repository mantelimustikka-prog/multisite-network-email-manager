<?php

defined('ABSPATH') || exit;

use MNEM\Admin\TableDiagnostics;
use PHPUnit\Framework\TestCase;

class TableDiagnosticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_submenu_pages'] = array();
    }

    public function test_register_submenu_adds_table_diagnosis_page()
    {
        $diagnostics = new TableDiagnostics();
        $diagnostics->register_submenu();

        $submenu_slugs = array_map(static function ($submenu) {
            return $submenu[4];
        }, $GLOBALS['mnem_submenu_pages']);

        $this->assertContains('mnem-table-diagnosis', $submenu_slugs);
    }

    public function test_installer_schema_exposes_required_tables()
    {
        $schema = \MNEM\Installer::get_table_schema('wp_');

        $this->assertArrayHasKey('mnem_queue', $schema);
        $this->assertArrayHasKey('mnem_campaigns', $schema);
        $this->assertArrayHasKey('mnem_suppression', $schema);
        $this->assertArrayHasKey('mnem_logs', $schema);
        $this->assertArrayHasKey('mnem_subscriber_lists', $schema);
        $this->assertArrayHasKey('mnem_list_subscribers', $schema);
        $this->assertArrayHasKey('opened', $schema['mnem_queue']['columns']);
        $this->assertArrayHasKey('clicked', $schema['mnem_queue']['columns']);
        $this->assertNotEmpty($schema['mnem_queue']['create_sql']);
    }

    public function test_installer_schema_uses_base_prefix_for_all_central_tables()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $prefix = 'wp_2_';
            public $base_prefix = 'wp_';
        };

        $schema = \MNEM\Installer::get_table_schema('wp_2_');

        $this->assertSame('wp_mnem_queue', $schema['mnem_queue']['name']);
        $this->assertStringContainsString('CREATE TABLE wp_mnem_queue', $schema['mnem_queue']['create_sql']);
        $this->assertStringContainsString("status enum('pending','processing','sent','delivered','opened','clicked','bounce','soft_bounce','invalid_email','deferred','complaint','unsubscribed','suppressed','failed','rejected')", $schema['mnem_queue']['create_sql']);
        $this->assertSame('wp_mnem_campaigns', $schema['mnem_campaigns']['name']);
        $this->assertStringContainsString('CREATE TABLE wp_mnem_campaigns', $schema['mnem_campaigns']['create_sql']);
    }

    public function test_collect_diagnostics_detects_missing_tables_and_schema_mismatch()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_queue'") !== false) {
                    return 'wp_mnem_queue';
                }
                if (strpos($query, 'SELECT COUNT(1) FROM `wp_mnem_queue`') !== false) {
                    return 7;
                }
                return null;
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'Data_length' => 1024,
                    'Index_length' => 512,
                    'Update_time' => '2026-08-15 22:30:00',
                    'Collation' => 'utf8mb4_unicode_ci',
                );
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SHOW COLUMNS FROM `wp_mnem_queue`') !== false) {
                    return array(
                        array(
                            'Field' => 'id',
                            'Type' => 'int(11)',
                        ),
                    );
                }

                if (strpos($query, 'SHOW INDEX FROM `wp_mnem_queue`') !== false) {
                    return array();
                }

                return array();
            }
        };

        $result = TableDiagnostics::collect_diagnostics();
        $queue_table = null;
        $missing_count = 0;
        foreach ($result['tables'] as $table) {
            if ($table['name'] === 'wp_mnem_queue') {
                $queue_table = $table;
            }
            if (empty($table['exists'])) {
                $missing_count++;
            }
        }

        $this->assertIsArray($result);
        $this->assertNotNull($queue_table);
        $this->assertFalse(empty($queue_table['wrong_column_types']));
        $this->assertFalse(empty($queue_table['missing_columns']));
        $this->assertFalse(empty($queue_table['missing_indexes']));
        $this->assertGreaterThan(0, $missing_count);
    }
}
