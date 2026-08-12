<?php
/**
 * Plugin Name: Multisite Network Email Manager
 * Description: Network-only email manager scaffold for WordPress multisite.
 * Version: 0.1.0
 * Author: mantelimustikka-prog
 * Network: true
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('MNEM_VERSION', '0.1.0');
define('MNEM_DB_VERSION', '1');
define('MNEM_PLUGIN_FILE', __FILE__);
define('MNEM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MNEM_PLUGIN_URL', plugin_dir_url(__FILE__));

$mnem_files = array(
    MNEM_PLUGIN_DIR . 'includes/class-settings.php',
    MNEM_PLUGIN_DIR . 'includes/class-logger.php',
    MNEM_PLUGIN_DIR . 'includes/class-installer.php',
    MNEM_PLUGIN_DIR . 'includes/class-smtp-settings.php',
    MNEM_PLUGIN_DIR . 'includes/class-smtp-service.php',
    MNEM_PLUGIN_DIR . 'includes/class-smtp-diagnostics.php',
    MNEM_PLUGIN_DIR . 'includes/class-suppression-list.php',
    MNEM_PLUGIN_DIR . 'includes/class-campaigns.php',
    MNEM_PLUGIN_DIR . 'includes/class-queue.php',
    MNEM_PLUGIN_DIR . 'includes/class-user-events.php',
    MNEM_PLUGIN_DIR . 'includes/class-rest-api.php',
    MNEM_PLUGIN_DIR . 'admin/class-network-admin.php',
    MNEM_PLUGIN_DIR . 'includes/class-plugin.php',
);

foreach ($mnem_files as $mnem_file) {
    if (file_exists($mnem_file)) {
        require_once $mnem_file;
    }
}

function mnem_activate($network_wide)
{
    if (! is_multisite()) {
        deactivate_plugins(plugin_basename(MNEM_PLUGIN_FILE));
        wp_die(esc_html__('Multisite Network Email Manager requires WordPress multisite.', 'multisite-network-email-manager'));
    }

    if (! $network_wide) {
        deactivate_plugins(plugin_basename(MNEM_PLUGIN_FILE));
        wp_die(esc_html__('Activate this plugin from Network Admin only.', 'multisite-network-email-manager'));
    }

    if (class_exists('MNEM_Installer')) {
        MNEM_Installer::install();
    }

    if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event') && ! wp_next_scheduled('mnem_process_queue')) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', 'mnem_process_queue');
    }
}

function mnem_deactivate($network_wide)
{
    if (! $network_wide && is_multisite()) {
        return;
    }

    if (function_exists('wp_next_scheduled') && function_exists('wp_unschedule_event')) {
        $timestamp = wp_next_scheduled('mnem_process_queue');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'mnem_process_queue');
        }
    }
}

if (function_exists('register_activation_hook')) {
    register_activation_hook(MNEM_PLUGIN_FILE, 'mnem_activate');
}

if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(MNEM_PLUGIN_FILE, 'mnem_deactivate');
}

function mnem_bootstrap()
{
    static $plugin = null;

    if (null !== $plugin || ! class_exists('MNEM_Plugin')) {
        return $plugin;
    }

    $plugin = new MNEM_Plugin();
    $plugin->register();

    return $plugin;
}

if (function_exists('add_action')) {
    add_action('plugins_loaded', 'mnem_bootstrap');
}
