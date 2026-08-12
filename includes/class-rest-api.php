<?php

class MNEM_REST_API
{
    const NAMESPACE = 'mnem/v1';

    protected $smtp_settings;
    protected $queue;
    protected $suppression_list;

    public function __construct(MNEM_SMTP_Settings $smtp_settings = null, MNEM_Queue $queue = null, MNEM_Suppression_List $suppression_list = null)
    {
        $this->smtp_settings = $smtp_settings ?: new MNEM_SMTP_Settings();
        $this->queue = $queue ?: new MNEM_Queue();
        $this->suppression_list = $suppression_list ?: new MNEM_Suppression_List();
    }

    public function register()
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        if (! function_exists('register_rest_route')) {
            return;
        }

        register_rest_route(self::NAMESPACE, '/status', array(
            'methods' => 'GET',
            'callback' => array($this, 'status'),
            'permission_callback' => array($this, 'can_manage'),
        ));

        register_rest_route(self::NAMESPACE, '/smtp', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_smtp_settings'),
                'permission_callback' => array($this, 'can_manage'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'update_smtp_settings'),
                'permission_callback' => array($this, 'can_manage'),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/queue', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_queue'),
            'permission_callback' => array($this, 'can_manage'),
        ));

        register_rest_route(self::NAMESPACE, '/suppression', array(
            array(
                'methods' => 'GET',
                'callback' => array($this, 'get_suppressions'),
                'permission_callback' => array($this, 'can_manage'),
            ),
            array(
                'methods' => 'POST',
                'callback' => array($this, 'create_suppression'),
                'permission_callback' => array($this, 'can_manage'),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'delete_suppression'),
                'permission_callback' => array($this, 'can_manage'),
            ),
        ));
    }

    public function can_manage()
    {
        return function_exists('current_user_can') && current_user_can('manage_network_options');
    }

    public function status()
    {
        return $this->respond(array(
            'version' => defined('MNEM_VERSION') ? MNEM_VERSION : null,
            'db_version' => function_exists('get_site_option') ? get_site_option('mnem_db_version') : null,
            'queue_size' => $this->queue->count(),
            'suppression_count' => $this->suppression_list->count(),
        ));
    }

    public function get_smtp_settings()
    {
        $settings = $this->smtp_settings->export();
        $settings['password'] = '' === $settings['password'] ? '' : '***';

        return $this->respond($settings);
    }

    public function update_smtp_settings($request)
    {
        $params = $this->request_params($request);
        $allowed = array('enabled', 'host', 'port', 'secure', 'username', 'password', 'from_email', 'from_name');
        $updated = $this->smtp_settings->update(array_intersect_key($params, array_flip($allowed)));

        return $this->respond(array('success' => (bool) $updated));
    }

    public function get_queue()
    {
        return $this->respond($this->queue->all());
    }

    public function get_suppressions()
    {
        return $this->respond($this->suppression_list->all());
    }

    public function create_suppression($request)
    {
        $params = $this->request_params($request);

        return $this->respond(array(
            'success' => $this->suppression_list->add(isset($params['email']) ? $params['email'] : '', isset($params['reason']) ? $params['reason'] : ''),
        ));
    }

    public function delete_suppression($request)
    {
        $params = $this->request_params($request);

        return $this->respond(array(
            'success' => $this->suppression_list->remove(isset($params['email']) ? $params['email'] : ''),
        ));
    }

    protected function respond($data)
    {
        if (class_exists('WP_REST_Response')) {
            return new WP_REST_Response($data, 200);
        }

        return $data;
    }

    protected function request_params($request)
    {
        if (is_object($request) && method_exists($request, 'get_json_params')) {
            return (array) $request->get_json_params();
        }

        return is_array($request) ? $request : array();
    }
}
