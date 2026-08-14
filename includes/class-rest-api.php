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
            '/queue/process',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'process_queue'),
                'permission_callback' => array($this, 'permission_check'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/queue/retry-failed',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'retry_failed_queue'),
                'permission_callback' => array($this, 'permission_check'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/campaigns',
            array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_campaigns'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'save_campaign'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/campaigns/(?P<id>\d+)/send',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'send_campaign'),
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

        // Provider webhook endpoints (no auth required; signatures verified inside handler).
        foreach (array('mailgun', 'sendgrid', 'brevo', 'postmark', 'smtp2go') as $provider) {
            register_rest_route(
                self::NAMESPACE,
                '/webhooks/' . $provider,
                array(
                    'methods'             => 'POST',
                    'callback'            => array($this, 'handle_webhook'),
                    'permission_callback' => '__return_true',
                )
            );
        }
    }

    public function permission_check()
    {
        if (function_exists('current_user_can') && current_user_can('manage_network_options')) {
            return true;
        }

        return new \WP_Error('mnem_forbidden', 'You do not have permission to access this resource.', array('status' => 403));
    }

    public function get_status()
    {
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;

        return array(
            'plugin_version' => defined('MNEM_VERSION') ? MNEM_VERSION : '1.0.0',
            'db_version' => get_site_option('mnem_db_version', ''),
            'smtp_configured' => SmtpSettings::get('host', '') !== '',
            'campaign_sends_paused' => (int) get_site_option('mnem_campaign_sends_paused', 0) === 1,
            'queue_stats' => Queue::get_stats($site_id),
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
                "SELECT id, site_id, campaign_id, recipient_email, subject, status, attempts, scheduled_at, processed_at, created_at FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d",
                $site_id,
                100
            ),
            ARRAY_A
        );

        return array(
            'items' => (array) $items,
            'stats' => Queue::get_stats($site_id),
        );
    }

    public function process_queue()
    {
        return array('processed' => Queue::process_batch(50));
    }

    public function retry_failed_queue()
    {
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;

        return array('retried' => Queue::retry_failed($site_id));
    }

    public function get_campaigns()
    {
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;

        return array('items' => Campaigns::get_list($site_id));
    }

    public function save_campaign($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $campaign_id = isset($params['id']) ? (int) $params['id'] : 0;
        $data = array(
            'name' => isset($params['name']) ? (string) $params['name'] : '',
            'subject' => isset($params['subject']) ? (string) $params['subject'] : '',
            'body' => isset($params['body']) ? (string) $params['body'] : '',
            'status' => isset($params['status']) ? (string) $params['status'] : 'draft',
            'scheduled_at' => isset($params['scheduled_at']) ? (string) $params['scheduled_at'] : '',
            'recipient_scope' => isset($params['recipient_scope']) ? (string) $params['recipient_scope'] : 'all_users',
            'recipient_list' => isset($params['recipient_list']) ? $params['recipient_list'] : '',
        );

        if ($campaign_id > 0) {
            $result = Campaigns::update($campaign_id, $data);
            return array('success' => (bool) $result, 'id' => $campaign_id);
        }

        $result = Campaigns::create($site_id, $data['name'], $data['subject'], $data['body'], $data);

        return array('success' => (bool) $result, 'id' => (int) $result);
    }

    public function send_campaign($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $campaign_id = isset($params['id']) ? (int) $params['id'] : 0;

        return Campaigns::send_campaign($campaign_id);
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

    /**
     * Handle incoming provider webhooks.
     *
     * @param mixed $request
     * @return array<string,mixed>
     */
    public function handle_webhook($request)
    {
        $route = method_exists($request, 'get_route') ? (string) $request->get_route() : '';
        $provider = '';
        if (preg_match('#/webhooks/([a-z0-9_]+)$#', $route, $m)) {
            $provider = $m[1];
        }

        $body = method_exists($request, 'get_body') ? $request->get_body() : '';
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $data = method_exists($request, 'get_params') ? $request->get_params() : array();
        }

        if ($provider === '') {
            return array('success' => false, 'message' => 'Unknown provider.');
        }

        Logger::info('Webhook received.', array('provider' => $provider, 'event_count' => is_array($data) ? count($data) : 1));

        // Extract event type and recipient from payload depending on provider.
        $event_type    = '';
        $recipient     = '';
        $message_id    = '';

        switch ($provider) {
            case 'mailgun':
                $event_data = isset($data['event-data']) && is_array($data['event-data']) ? $data['event-data'] : $data;
                $event_type = isset($event_data['event']) ? (string) $event_data['event'] : '';
                $recipient  = isset($event_data['recipient']) ? (string) $event_data['recipient'] : '';
                $message_id = isset($event_data['message']['headers']['message-id']) ? (string) $event_data['message']['headers']['message-id'] : '';
                break;

            case 'sendgrid':
                $events = is_array($data) && isset($data[0]) ? $data : array($data);
                $event_data = $events[0];
                $event_type = isset($event_data['event']) ? (string) $event_data['event'] : '';
                $recipient  = isset($event_data['email']) ? (string) $event_data['email'] : '';
                $message_id = isset($event_data['sg_message_id']) ? (string) $event_data['sg_message_id'] : '';
                break;

            case 'brevo':
                $event_type = isset($data['event']) ? (string) $data['event'] : '';
                $recipient  = isset($data['email']) ? (string) $data['email'] : '';
                $message_id = isset($data['message-id']) ? (string) $data['message-id'] : '';
                break;

            case 'postmark':
                $event_type = isset($data['RecordType']) ? (string) $data['RecordType'] : '';
                $recipient  = isset($data['Recipient']) ? (string) $data['Recipient'] : (isset($data['Email']) ? (string) $data['Email'] : '');
                $message_id = isset($data['MessageID']) ? (string) $data['MessageID'] : '';
                break;

            case 'smtp2go':
                $event_type = isset($data['type']) ? (string) $data['type'] : '';
                $recipient  = isset($data['recipient']) ? (string) $data['recipient'] : '';
                $message_id = isset($data['request_id']) ? (string) $data['request_id'] : '';
                break;
        }

        Logger::info('Webhook event processed.', array(
            'provider'   => $provider,
            'event_type' => $event_type,
            'recipient'  => $recipient,
            'message_id' => $message_id,
        ));

        // Auto-suppress on hard bounce events.
        $bounce_events = array(
            'mailgun'  => array('failed', 'bounced'),
            'sendgrid' => array('bounce'),
            'brevo'    => array('hard_bounce'),
            'postmark' => array('Bounce', 'HardBounce'),
            'smtp2go'  => array('bounce', 'failed'),
        );

        if ($recipient !== '' && isset($bounce_events[$provider]) && in_array($event_type, $bounce_events[$provider], true)) {
            $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
            Suppression::add($site_id, $recipient, 'Auto-suppressed on ' . $provider . ' bounce: ' . $event_type);
            Logger::info('Auto-suppressed bounced address.', array('email' => $recipient, 'provider' => $provider, 'event' => $event_type));
        }

        return array('success' => true, 'provider' => $provider, 'event' => $event_type);
    }
}
