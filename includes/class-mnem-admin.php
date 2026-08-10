<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Admin {
    private $settings;
    private $logger;

    public function __construct( MNEM_Settings $settings, MNEM_Logger $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    public function hooks(): void {
        add_action( 'network_admin_menu', array( $this, 'register_menu' ) );
        add_action( 'network_admin_edit_mnem_save_settings', array( $this, 'save_settings' ) );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'Network Email Manager', 'multisite-network-email-manager' ),
            __( 'Email Manager', 'multisite-network-email-manager' ),
            MNEM_Capabilities::MANAGE_SETTINGS,
            'mnem-dashboard',
            array( $this, 'render_dashboard' ),
            'dashicons-email-alt2',
            59
        );

        add_submenu_page( 'mnem-dashboard', __( 'Dashboard', 'multisite-network-email-manager' ), __( 'Dashboard', 'multisite-network-email-manager' ), MNEM_Capabilities::MANAGE_SETTINGS, 'mnem-dashboard', array( $this, 'render_dashboard' ) );
        add_submenu_page( 'mnem-dashboard', __( 'Settings', 'multisite-network-email-manager' ), __( 'Settings', 'multisite-network-email-manager' ), MNEM_Capabilities::MANAGE_SETTINGS, 'mnem-settings', array( $this, 'render_settings' ) );
        add_submenu_page( 'mnem-dashboard', __( 'Queue', 'multisite-network-email-manager' ), __( 'Queue', 'multisite-network-email-manager' ), MNEM_Capabilities::MANAGE_QUEUE, 'mnem-queue', array( $this, 'render_queue' ) );
        add_submenu_page( 'mnem-dashboard', __( 'Campaigns', 'multisite-network-email-manager' ), __( 'Campaigns', 'multisite-network-email-manager' ), MNEM_Capabilities::MANAGE_SETTINGS, 'mnem-campaigns', array( $this, 'render_campaigns' ) );
        add_submenu_page( 'mnem-dashboard', __( 'Suppressions', 'multisite-network-email-manager' ), __( 'Suppressions', 'multisite-network-email-manager' ), MNEM_Capabilities::MANAGE_SETTINGS, 'mnem-suppressions', array( $this, 'render_suppressions' ) );
        add_submenu_page( 'mnem-dashboard', __( 'Advanced User Management', 'multisite-network-email-manager' ), __( 'Advanced User Management', 'multisite-network-email-manager' ), MNEM_Capabilities::MANAGE_USERS, 'mnem-user-management', array( $this, 'render_user_management' ) );
        add_submenu_page( 'mnem-dashboard', __( 'Logs', 'multisite-network-email-manager' ), __( 'Logs', 'multisite-network-email-manager' ), MNEM_Capabilities::VIEW_LOGS, 'mnem-logs', array( $this, 'render_logs' ) );
    }

    public function save_settings(): void {
        if ( ! MNEM_Capabilities::can( MNEM_Capabilities::MANAGE_SETTINGS ) ) {
            wp_die( esc_html__( 'You do not have permission to manage these settings.', 'multisite-network-email-manager' ) );
        }

        check_admin_referer( 'mnem_save_settings' );

        $settings = array(
            'smtp_enabled'        => isset( $_POST['smtp_enabled'] ) ? 1 : 0,
            'smtp_provider'       => isset( $_POST['smtp_provider'] ) ? wp_unslash( $_POST['smtp_provider'] ) : 'custom',
            'smtp_host'           => isset( $_POST['smtp_host'] ) ? wp_unslash( $_POST['smtp_host'] ) : '',
            'smtp_port'           => isset( $_POST['smtp_port'] ) ? wp_unslash( $_POST['smtp_port'] ) : 587,
            'smtp_encryption'     => isset( $_POST['smtp_encryption'] ) ? wp_unslash( $_POST['smtp_encryption'] ) : 'tls',
            'smtp_username'       => isset( $_POST['smtp_username'] ) ? wp_unslash( $_POST['smtp_username'] ) : '',
            'smtp_password'       => isset( $_POST['smtp_password'] ) ? wp_unslash( $_POST['smtp_password'] ) : '',
            'from_email'          => isset( $_POST['from_email'] ) ? wp_unslash( $_POST['from_email'] ) : '',
            'from_name'           => isset( $_POST['from_name'] ) ? wp_unslash( $_POST['from_name'] ) : '',
            'queue_batch_size'    => isset( $_POST['queue_batch_size'] ) ? wp_unslash( $_POST['queue_batch_size'] ) : 20,
            'admin_notice_email'  => isset( $_POST['admin_notice_email'] ) ? wp_unslash( $_POST['admin_notice_email'] ) : '',
            'allow_user_deletion' => isset( $_POST['allow_user_deletion'] ) ? 1 : 0,
        );

        $this->settings->update( $settings );
        $this->logger->info( 'Network settings updated from admin UI.' );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'    => 'mnem-settings',
                    'updated' => '1',
                ),
                network_admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function render_dashboard(): void {
        $this->render_view( 'dashboard', array( 'settings' => $this->settings->all() ), MNEM_Capabilities::MANAGE_SETTINGS );
    }

    public function render_settings(): void {
        $this->render_view( 'settings', array( 'settings' => $this->settings->all() ), MNEM_Capabilities::MANAGE_SETTINGS );
    }

    public function render_queue(): void {
        $this->render_view( 'queue', array( 'settings' => $this->settings->all() ), MNEM_Capabilities::MANAGE_QUEUE );
    }

    public function render_campaigns(): void {
        $this->render_view( 'campaigns', array( 'settings' => $this->settings->all() ), MNEM_Capabilities::MANAGE_SETTINGS );
    }

    public function render_suppressions(): void {
        $this->render_view( 'suppressions', array( 'settings' => $this->settings->all() ), MNEM_Capabilities::MANAGE_SETTINGS );
    }

    public function render_user_management(): void {
        $this->render_view( 'user-management', array( 'settings' => $this->settings->all() ), MNEM_Capabilities::MANAGE_USERS );
    }

    public function render_logs(): void {
        $this->render_view( 'logs', array( 'logs' => $this->logger->latest() ), MNEM_Capabilities::VIEW_LOGS );
    }

    private function render_view( string $view, array $args = array(), string $capability = MNEM_Capabilities::MANAGE_SETTINGS ): void {
        if ( ! MNEM_Capabilities::can( $capability ) ) {
            wp_die( esc_html__( 'You do not have permission to view this page.', 'multisite-network-email-manager' ) );
        }

        $file = MNEM_PLUGIN_DIR . 'admin/views/' . $view . '.php';
        if ( ! file_exists( $file ) ) {
            wp_die( esc_html__( 'View file not found.', 'multisite-network-email-manager' ) );
        }

        extract( $args, EXTR_SKIP );
        include $file;
    }
}
