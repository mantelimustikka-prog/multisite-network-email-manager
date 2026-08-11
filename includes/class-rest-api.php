<?php

namespace MNEM;

defined('ABSPATH') || exit;

class RestApi
{
    public const NAMESPACE = 'mnem/v1';

    public function init()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(
            self::NAMESPACE,
            '/status',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_status'),
                'permission_callback' => array($this, 'permission_check'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/smtp',
            array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_smtp_settings'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'save_smtp_settings'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/queue',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_queue_items'),
                'permission_callback' => array($this, 'permission_check'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/suppression',
            array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_suppression_list'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'add_suppression_entry'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods' => 'DELETE',
                    'callback' => array($this, 'delete_suppression_entry'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
            )
        );
    }

    public function permission_check()
    {
        if (function_exists('current_user_can') && current_user_can('manage_network')) {
            return true;
        }

        return new \WP_Error('mnem_forbidden', 'You do not have permission to access this resource.', array('status' => 403));
    }

    public function get_status()
    {
        return array(
            'plugin_version' => defined('MNEM_VERSION') ? MNEM_VERSION : '1.0.0',
            'db_version' => get_site_option('mnem_db_version', ''),
            'smtp_configured' => SmtpSettings::get('host', '') !== '',
        );
    }

    public function get_smtp_settings()
    {
        $settings = SmtpSettings::get_all();
        $settings['password'] = '';

        return $settings;
    }

    public function save_smtp_settings($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        SmtpSettings::save($params);

        return array(
            'success' => true,
            'settings' => $this->get_smtp_settings(),
        );
    }

    public function get_queue_items()
    {
        global $wpdb;
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $table = $wpdb->prefix . 'mnem_queue';
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, site_id, recipient_email, subject, status, attempts, scheduled_at, processed_at, created_at FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d",
                $site_id,
                100
            ),
            ARRAY_A
        );

        return array('items' => (array) $items);
    }

    public function get_suppression_list()
    {
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;

        return array('items' => Suppression::get_list($site_id));
    }

    public function add_suppression_entry($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $result = Suppression::add(
            $site_id,
            isset($params['email']) ? (string) $params['email'] : '',
            isset($params['reason']) ? (string) $params['reason'] : ''
        );

        return array('success' => (bool) $result);
    }

    public function delete_suppression_entry($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $result = Suppression::remove($site_id, isset($params['email']) ? (string) $params['email'] : '');

        return array('success' => (bool) $result);
    }
}
