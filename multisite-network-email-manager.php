<?php
/**
 * Plugin Name: Multisite Network Email Manager
 * Description: Network-wide email management tooling for WordPress multisite.
 * Version: 1.0.0
 * Network: true
 * Requires PHP: 7.4
 * Author: Copilot
 */

defined('ABSPATH') || exit;

if (!defined('MNEM_VERSION')) {
    define('MNEM_VERSION', '1.0.0');
}

if (!defined('MNEM_DB_VERSION')) {
    define('MNEM_DB_VERSION', '1');
}

if (!defined('MNEM_PLUGIN_DIR')) {
    define('MNEM_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('MNEM_PLUGIN_URL')) {
    define('MNEM_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('MNEM_PLUGIN_FILE')) {
    define('MNEM_PLUGIN_FILE', __FILE__);
}

if (!function_exists('is_multisite') || !is_multisite()) {
    $mnem_notice = static function () {
        echo '<div class="notice notice-error"><p>Multisite Network Email Manager requires WordPress multisite.</p></div>';
    };

    if (function_exists('add_action')) {
        add_action('admin_notices', $mnem_notice);
        add_action('network_admin_notices', $mnem_notice);
    }

    return;
}

spl_autoload_register(
    static function ($class) {
        if (strpos($class, 'MNEM\\') !== 0) {
            return;
        }

        $relative = substr($class, 5);
        $relative = str_replace('\\', '/', $relative);
        $relative = str_replace('_', '/', $relative);
        $parts    = array_values(array_filter(explode('/', $relative)));

        if (empty($parts)) {
            return;
        }

        $base_dir = MNEM_PLUGIN_DIR . 'includes/';

        if ($parts[0] === 'Admin') {
            array_shift($parts);
            $base_dir = MNEM_PLUGIN_DIR . 'admin/';
        }

        $class_name = array_pop($parts);
        $class_name = preg_replace('/(?<!^)[A-Z]/', '-$0', $class_name);
        $class_name = strtolower((string) $class_name);
        $path_parts = $parts;
        $path_parts[] = 'class-' . $class_name . '.php';
        $file = $base_dir . implode('/', array_map('strtolower', $path_parts));

        if (file_exists($file)) {
            require_once $file;
        }
    }
);

register_activation_hook(
    __FILE__,
    static function () {
        \MNEM\Installer::activate();
    }
);

register_deactivation_hook(
    __FILE__,
    static function () {
        \MNEM\Installer::deactivate();
    }
);

add_action(
    'plugins_loaded',
    static function () {
        $plugin = new \MNEM\Plugin();
        $plugin->init();
    }
);
