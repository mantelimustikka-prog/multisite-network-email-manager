<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_REST_API {
    private $settings;
    private $logger;

    public function __construct( MNEM_Settings $settings, MNEM_Logger $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    public function hooks(): void {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes(): void {
        register_rest_route(
            'mnem/v1',
            '/status',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => array( $this, 'can_manage' ),
                'callback'            => array( $this, 'status' ),
            )
        );

        register_rest_route(
            'mnem/v1',
            '/settings',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'permission_callback' => array( $this, 'can_manage' ),
                'callback'            => array( $this, 'settings' ),
            )
        );
    }

    public function can_manage(): bool {
        return MNEM_Capabilities::can( MNEM_Capabilities::MANAGE_SETTINGS );
    }

    public function status(): WP_REST_Response {
        return new WP_REST_Response(
            array(
                'plugin'     => 'Multisite Network Email Manager',
                'version'    => MNEM_VERSION,
                'smtp'       => (bool) $this->settings->get( 'smtp_enabled', false ),
                'queue_hook' => MNEM_Queue::CRON_HOOK,
            )
        );
    }

    public function settings(): WP_REST_Response {
        $settings = $this->settings->all();
        unset( $settings['smtp_password'] );

        return new WP_REST_Response( $settings );
    }
}
