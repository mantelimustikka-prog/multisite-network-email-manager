<?php
/**
 * Plugin Name: Multisite Network Email Manager
 * Plugin URI: https://github.com/mantelimustikka-prog/multisite-network-email-manager
 * Description: Centralized transactional and promotional email management scaffold for WordPress multisite networks.
 * Version: 0.1.0
 * Author: mantelimustikka-prog
 * Text Domain: multisite-network-email-manager
 * Network: true
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MNEM_VERSION', '0.1.0' );
define( 'MNEM_PLUGIN_FILE', __FILE__ );
define( 'MNEM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MNEM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

$mnem_includes = array(
    'includes/class-mnem-capabilities.php',
    'includes/class-mnem-settings.php',
    'includes/class-mnem-installer.php',
    'includes/class-mnem-logger.php',
    'includes/class-mnem-template-engine.php',
    'includes/class-mnem-suppression.php',
    'includes/class-mnem-queue.php',
    'includes/class-mnem-campaigns.php',
    'includes/class-mnem-user-management.php',
    'includes/class-mnem-mail-router.php',
    'includes/class-mnem-rest-api.php',
    'includes/class-mnem-admin.php',
    'includes/class-mnem-plugin.php',
);

foreach ( $mnem_includes as $mnem_include ) {
    require_once MNEM_PLUGIN_DIR . $mnem_include;
}

register_activation_hook( __FILE__, array( 'MNEM_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MNEM_Installer', 'deactivate' ) );

function mnem(): MNEM_Plugin {
    static $plugin = null;

    if ( null === $plugin ) {
        $plugin = new MNEM_Plugin();
    }

    return $plugin;
}

add_action(
    'plugins_loaded',
    static function () {
        mnem()->run();
    }
);
