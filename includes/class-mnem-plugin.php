<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Plugin {
    private $settings;
    private $logger;
    private $admin;
    private $mail_router;
    private $template_engine;
    private $queue;
    private $campaigns;
    private $suppression;
    private $user_management;
    private $rest_api;

    public function __construct() {
        $this->settings        = new MNEM_Settings();
        $this->logger          = new MNEM_Logger();
        $this->template_engine = new MNEM_Template_Engine();
        $this->suppression     = new MNEM_Suppression();
        $this->queue           = new MNEM_Queue( $this->settings, $this->logger, $this->suppression );
        $this->campaigns       = new MNEM_Campaigns( $this->logger );
        $this->user_management = new MNEM_User_Management( $this->settings, $this->logger );
        $this->mail_router     = new MNEM_Mail_Router( $this->settings, $this->logger );
        $this->rest_api        = new MNEM_REST_API( $this->settings, $this->logger );
        $this->admin           = new MNEM_Admin( $this->settings, $this->logger );
    }

    public function run(): void {
        $this->queue->hooks();
        $this->mail_router->hooks();
        $this->rest_api->hooks();
        $this->admin->hooks();
        add_action( 'init', array( $this, 'bootstrap' ) );
    }

    public function bootstrap(): void {
        if ( ! is_multisite() ) {
            $this->logger->warning( 'Plugin loaded outside of multisite. Network-focused features remain available as placeholders.' );
        }
    }

    public function settings(): MNEM_Settings {
        return $this->settings;
    }

    public function logger(): MNEM_Logger {
        return $this->logger;
    }

    public function template_engine(): MNEM_Template_Engine {
        return $this->template_engine;
    }

    public function queue(): MNEM_Queue {
        return $this->queue;
    }

    public function campaigns(): MNEM_Campaigns {
        return $this->campaigns;
    }

    public function suppression(): MNEM_Suppression {
        return $this->suppression;
    }

    public function user_management(): MNEM_User_Management {
        return $this->user_management;
    }
}
