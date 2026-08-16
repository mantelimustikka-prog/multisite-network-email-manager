<?php

defined('ABSPATH') || exit;

use MNEM\CliCommand;
use PHPUnit\Framework\TestCase;

if (!class_exists('WP_CLI')) {
    class WP_CLI
    {
        public static $commands = array();
        public static $logs = array();
        public static $errors = array();
        public static $successes = array();

        public static function add_command($name, $callback)
        {
            self::$commands[$name] = $callback;
        }

        public static function log($message)
        {
            self::$logs[] = (string) $message;
        }

        public static function error($message)
        {
            self::$errors[] = (string) $message;
        }

        public static function success($message)
        {
            self::$successes[] = (string) $message;
        }
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta($sql)
    {
        return array($sql);
    }
}

if (!function_exists('get_sites')) {
    function get_sites($args = array())
    {
        return array(1, 2, 3);
    }
}

if (!defined('WP_CLI')) {
    define('WP_CLI', true);
}

class CliCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        WP_CLI::$commands = array();
        WP_CLI::$logs = array();
        WP_CLI::$errors = array();
        WP_CLI::$successes = array();
        $GLOBALS['mnem_site_options'] = array();
        $this->reset_installer_migration_state();
    }

    public function test_register_commands_adds_migration_command()
    {
        CliCommand::register_commands();

        $this->assertArrayHasKey('mnem migrate-network-tables', WP_CLI::$commands);
    }

    public function test_execute_migration_consolidates_and_verifies()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $tables = array(
                'wp_mnem_queue' => array('rows' => 2, 'site_id_column' => true, 'zero_site_rows' => 0),
                'wp_mnem_campaigns' => array('rows' => 1, 'site_id_column' => true, 'zero_site_rows' => 0),
                'wp_2_mnem_queue' => array('rows' => 4, 'site_id_column' => true, 'zero_site_rows' => 4),
                'wp_3_mnem_queue' => array('rows' => 6, 'site_id_column' => true, 'zero_site_rows' => 6),
                'wp_2_mnem_campaigns' => array('rows' => 5, 'site_id_column' => true, 'zero_site_rows' => 5),
                'wp_3_mnem_campaigns' => array('rows' => 7, 'site_id_column' => true, 'zero_site_rows' => 7),
            );

            public function get_var($query)
            {
                $this->queries[] = $query;

                if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $query, $parts)) {
                    $table_name = stripslashes($parts[1]);
                    return isset($this->tables[$table_name]) ? $table_name : null;
                }

                if (preg_match("/TABLE_NAME = '([^']+)' AND COLUMN_NAME = 'site_id'/", $query, $parts)) {
                    $table_name = stripslashes($parts[1]);
                    return (!empty($this->tables[$table_name]['site_id_column'])) ? 1 : 0;
                }

                if (preg_match('/SELECT COUNT\(1\) FROM `([A-Za-z0-9_]+)` WHERE site_id = 0/', $query, $parts)) {
                    $table_name = $parts[1];
                    return isset($this->tables[$table_name]['zero_site_rows']) ? (int) $this->tables[$table_name]['zero_site_rows'] : 0;
                }

                if (preg_match('/SELECT COUNT\(1\) FROM `([A-Za-z0-9_]+)`/', $query, $parts)) {
                    $table_name = $parts[1];
                    return isset($this->tables[$table_name]['rows']) ? (int) $this->tables[$table_name]['rows'] : 0;
                }

                return null;
            }

            public function get_col($query)
            {
                $this->queries[] = $query;
                if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $query, $parts)) {
                    $pattern = stripslashes($parts[1]);
                    $regex = '/^' . str_replace(array('%', '_'), array('.*', '.'), preg_quote($pattern, '/')) . '$/';
                    $matches = array();
                    foreach (array_keys($this->tables) as $table_name) {
                        if (preg_match($regex, $table_name)) {
                            $matches[] = $table_name;
                        }
                    }
                    return $matches;
                }

                return array();
            }

            public function query($query)
            {
                $this->queries[] = $query;

                if (preg_match('/ALTER TABLE `([A-Za-z0-9_]+)` ADD COLUMN site_id/', $query, $parts)) {
                    $table_name = $parts[1];
                    if (isset($this->tables[$table_name])) {
                        $this->tables[$table_name]['site_id_column'] = true;
                        $this->tables[$table_name]['zero_site_rows'] = isset($this->tables[$table_name]['rows']) ? (int) $this->tables[$table_name]['rows'] : 0;
                    }
                }

                if (preg_match('/UPDATE `([A-Za-z0-9_]+)` SET site_id = [0-9]+ WHERE site_id = 0/', $query, $parts)) {
                    $table_name = $parts[1];
                    if (isset($this->tables[$table_name])) {
                        $this->tables[$table_name]['zero_site_rows'] = 0;
                    }
                }

                if (preg_match('/INSERT IGNORE INTO `([A-Za-z0-9_]+)` SELECT \* FROM `([A-Za-z0-9_]+)`/', $query, $parts)) {
                    $new_table = $parts[1];
                    $old_table = $parts[2];
                    $legacy_rows = isset($this->tables[$old_table]['rows']) ? (int) $this->tables[$old_table]['rows'] : 0;
                    if (isset($this->tables[$new_table])) {
                        $this->tables[$new_table]['rows'] += $legacy_rows;
                    }
                }

                if (preg_match('/DROP TABLE IF EXISTS `([A-Za-z0-9_]+)`/', $query, $parts)) {
                    $table_name = $parts[1];
                    unset($this->tables[$table_name]);
                }

                return 1;
            }
        };

        $report = CliCommand::execute_migration();

        $this->assertTrue($report['verification']['success']);
        $this->assertSame(0, count($report['after']['legacy_tables']['queue']));
        $this->assertSame(0, count($report['after']['legacy_tables']['campaigns']));
        $this->assertSame(10, $report['verification']['legacy_rows_before']['queue']);
        $this->assertSame(12, $report['verification']['legacy_rows_before']['campaigns']);
        $this->assertTrue($report['verification']['checks']['site_id_columns_present']);
    }

    private function reset_installer_migration_state()
    {
        $reflection = new ReflectionClass(\MNEM\Installer::class);
        $property = $reflection->getProperty('migrations_ran');
        $property->setAccessible(true);
        $property->setValue(null, false);
    }
}
