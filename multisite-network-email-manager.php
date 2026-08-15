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
    define('MNEM_DB_VERSION', '7');
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
        // Do NOT replace underscores with slashes: class names use underscores
        // as word separators that become hyphens, not sub-directories.
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
    static function ($network_wide = false) {
        if (function_exists('is_multisite') && is_multisite() && !$network_wide) {
            if (function_exists('deactivate_plugins')) {
                deactivate_plugins(plugin_basename(__FILE__));
            }

            if (function_exists('wp_die')) {
                wp_die('Multisite Network Email Manager must be network activated.');
            }

            return;
        }

        \MNEM\Installer::activate($network_wide);
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

add_action(
    'admin_init',
    static function () {
        if (!function_exists('is_multisite') || !is_multisite()) {
            return;
        }

        if (!function_exists('is_plugin_active_for_network') && defined('ABSPATH')) {
            $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
            if (file_exists($plugin_file)) {
                require_once $plugin_file;
            }
        }

        if (function_exists('is_plugin_active_for_network') && !is_plugin_active_for_network(plugin_basename(__FILE__))) {
            if (function_exists('is_plugin_active') && is_plugin_active(plugin_basename(__FILE__)) && function_exists('deactivate_plugins')) {
                deactivate_plugins(plugin_basename(__FILE__));
                update_site_option('mnem_network_only_notice', 1);
            }
        }

        $is_plugin_activation = isset($_GET['action'], $_GET['plugin']) && $_GET['action'] === 'activate' && $_GET['plugin'] === plugin_basename(__FILE__);
        $is_network_admin = function_exists('is_network_admin') ? is_network_admin() : false;

        if ($is_plugin_activation && !$is_network_admin && function_exists('wp_safe_redirect')) {
            update_site_option('mnem_network_only_notice', 1);
            wp_safe_redirect(network_admin_url('plugins.php'));
            exit;
        }
    }
);

add_action(
    'network_admin_notices',
    static function () {
        if ((int) get_site_option('mnem_network_only_notice', 0) !== 1) {
            return;
        }

        update_site_option('mnem_network_only_notice', 0);
        echo '<div class="notice notice-error"><p>Multisite Network Email Manager must be network activated.</p></div>';
    }
);
