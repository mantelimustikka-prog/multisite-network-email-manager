<?php

class MNEM_Plugin
{
    protected $smtp_service;
    protected $rest_api;
    protected $admin;
    protected $user_events;
    protected $queue;

    public function __construct(MNEM_SMTP_Service $smtp_service = null, MNEM_REST_API $rest_api = null, MNEM_Network_Admin $admin = null, MNEM_User_Events $user_events = null, MNEM_Queue $queue = null)
    {
        $this->smtp_service = $smtp_service ?: (class_exists('MNEM_SMTP_Service') ? new MNEM_SMTP_Service() : null);
        $this->rest_api = $rest_api ?: (class_exists('MNEM_REST_API') ? new MNEM_REST_API() : null);
        $this->admin = $admin ?: (class_exists('MNEM_Network_Admin') ? new MNEM_Network_Admin() : null);
        $this->user_events = $user_events ?: (class_exists('MNEM_User_Events') ? new MNEM_User_Events() : null);
        $this->queue = $queue ?: (class_exists('MNEM_Queue') ? new MNEM_Queue() : null);
    }

    public function register()
    {
        if ($this->smtp_service) {
            $this->smtp_service->register();
        }

        if ($this->rest_api) {
            $this->rest_api->register();
        }

        if ($this->admin) {
            $this->admin->register();
        }

        if ($this->user_events) {
            $this->user_events->register();
        }

        if ($this->queue && function_exists('add_action')) {
            add_action('mnem_process_queue', array($this->queue, 'process_batch'));
        }
    }
}
