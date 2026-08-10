<?php
/**
 * Plugin Name: Multisite Network Email Manager
 * Description: Network-level email management and SMTP settings for WordPress multisite.
 * Version: 0.1.0
 * Author: mantelimustikka-prog
 * Network: true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'MNEM_VERSION' ) ) {
	define( 'MNEM_VERSION', '0.1.0' );
}

if ( ! defined( 'MNEM_PLUGIN_FILE' ) ) {
	define( 'MNEM_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MNEM_PLUGIN_DIR' ) ) {
	define( 'MNEM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

require_once MNEM_PLUGIN_DIR . 'includes/class-logger.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-crypto.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-log-store.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-smtp-settings.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-smtp-service.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-smtp-diagnostics.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-mailer-adapter.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-mail-queue.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-site-settings.php';
require_once MNEM_PLUGIN_DIR . 'includes/class-cli-command.php';

class MNEM_Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var MNEM_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Logger instance.
	 *
	 * @var MNEM_Logger
	 */
	private $logger;

	/**
	 * SMTP settings instance.
	 *
	 * @var MNEM_SMTP_Settings
	 */
	private $settings;

	/**
	 * SMTP service instance.
	 *
	 * @var MNEM_SMTP_Service
	 */
	private $smtp_service;

	/**
	 * Mailer adapter instance.
	 *
	 * @var MNEM_Mailer_Adapter
	 */
	private $mailer_adapter;

	/**
	 * SMTP diagnostics instance.
	 *
	 * @var MNEM_SMTP_Diagnostics
	 */
	private $diagnostics;

	/**
	 * Log store instance.
	 *
	 * @var MNEM_Log_Store
	 */
	private $log_store;

	/**
	 * Mail queue instance.
	 *
	 * @var MNEM_Mail_Queue
	 */
	private $mail_queue;

	/**
	 * Per-site settings instance.
	 *
	 * @var MNEM_Site_Settings
	 */
	private $site_settings;

	/**
	 * Get the plugin instance.
	 *
	 * @return MNEM_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation hook callback.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( function_exists( 'add_site_option' ) ) {
			add_site_option( MNEM_SMTP_Settings::OPTION_KEY, MNEM_SMTP_Settings::defaults() );
		}
	}

	/**
	 * Deactivation hook callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$disable_on_deactivate = (bool) apply_filters( 'mnem_smtp_disable_on_deactivate', false );

		if ( $disable_on_deactivate ) {
			$settings = get_site_option( MNEM_SMTP_Settings::OPTION_KEY, MNEM_SMTP_Settings::defaults() );
			if ( ! is_array( $settings ) ) {
				$settings = MNEM_SMTP_Settings::defaults();
			}

			$settings['enabled'] = false;
			update_site_option( MNEM_SMTP_Settings::OPTION_KEY, $settings );
		}

		wp_clear_scheduled_hook( MNEM_Mail_Queue::CRON_HOOK );
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->logger         = new MNEM_Logger();
		$this->log_store      = new MNEM_Log_Store();
		$this->settings       = new MNEM_SMTP_Settings( $this->logger );
		$this->smtp_service   = new MNEM_SMTP_Service( $this->settings, $this->logger );
		$this->mailer_adapter = new MNEM_Mailer_Adapter( $this->logger );
		$this->diagnostics    = new MNEM_SMTP_Diagnostics( $this->settings, $this->smtp_service, $this->mailer_adapter, $this->logger );
		$this->mail_queue     = new MNEM_Mail_Queue( $this->logger );
		$this->site_settings  = new MNEM_Site_Settings( $this->logger );

		$this->settings->set_diagnostics( $this->diagnostics );
		$this->settings->set_log_store( $this->log_store );
		$this->smtp_service->set_site_settings( $this->site_settings );
		$this->smtp_service->set_mail_queue( $this->mail_queue );

		$this->log_store->register_hooks();
		$this->settings->register_hooks();
		$this->smtp_service->register_hooks();
		$this->mail_queue->register_hooks();
		$this->site_settings->register_hooks();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command(
				'mnem smtp',
				new MNEM_CLI_Command( $this->settings, $this->diagnostics )
			);
		}
	}
}

register_activation_hook( __FILE__, array( 'MNEM_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MNEM_Plugin', 'deactivate' ) );
MNEM_Plugin::instance();
