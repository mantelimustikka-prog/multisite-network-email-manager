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
            '/track-open',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'track_open'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/track-click',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'track_click'),
                'permission_callback' => '__return_true',
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
            '/campaigns/(?P<id>\d+)/cancel',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'cancel_campaign'),
                'permission_callback' => array($this, 'permission_check'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/subscriber-lists',
            array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_subscriber_lists'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'save_subscriber_list'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods' => 'DELETE',
                    'callback' => array($this, 'delete_subscriber_list'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/subscriber-lists/(?P<id>\d+)/subscribers',
            array(
                array(
                    'methods' => 'GET',
                    'callback' => array($this, 'get_list_subscribers'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods' => 'POST',
                    'callback' => array($this, 'add_list_subscriber'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
                array(
                    'methods' => 'DELETE',
                    'callback' => array($this, 'remove_list_subscriber'),
                    'permission_callback' => array($this, 'permission_check'),
                ),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/subscriber-lists/(?P<id>\d+)/search-users',
            array(
                'methods' => 'GET',
                'callback' => array($this, 'search_network_users'),
                'permission_callback' => array($this, 'permission_check'),
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/subscriber-lists/(?P<id>\d+)/check-unsubscribed',
            array(
                'methods' => 'POST',
                'callback' => array($this, 'check_unsubscribed'),
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
            'smtp_configured' => SmtpSettings::is_active_provider_configured(),
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
        $table = $wpdb->base_prefix . 'mnem_queue';
        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, site_id, campaign_id, recipient_email, subject, status, attempts, scheduled_at, sent_at, opened, clicked, opens_count, clicks_count, created_at, provider_message_id, provider_metadata FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d",
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

    public function track_open($request)
    {
        $token = method_exists($request, 'get_param') ? (string) $request->get_param('token') : '';
        $gif = EmailTracker::handle_pixel_request($token);
        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        if (!headers_sent()) {
            header('Content-Type: image/gif');
            header('Cache-Control: no-cache, no-store, must-revalidate');
        }
        echo $gif;
        exit;
    }

    public function track_click($request)
    {
        $token = method_exists($request, 'get_param') ? (string) $request->get_param('token') : '';
        $url = method_exists($request, 'get_param') ? (string) $request->get_param('url') : '';
        $redirect = EmailTracker::handle_link_click($token, $url);
        if ($redirect === '') {
            $redirect = function_exists('home_url') ? home_url('/') : '/';
        }
        if (function_exists('wp_sanitize_redirect')) {
            $redirect = wp_sanitize_redirect($redirect);
        }

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        if (function_exists('wp_redirect')) {
            wp_redirect($redirect, 302);
        } else {
            header('Location: ' . $redirect, true, 302);
        }
        exit;
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

    public function cancel_campaign($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $campaign_id = isset($params['id']) ? (int) $params['id'] : 0;
        $nonce = isset($params['_wpnonce']) ? (string) $params['_wpnonce'] : '';

        if (function_exists('wp_verify_nonce') && !wp_verify_nonce($nonce, 'wp_rest')) {
            return array('success' => false, 'message' => 'Invalid nonce.');
        }

        $result = Campaigns::cancel_campaign($campaign_id);

        return array(
            'success' => (bool) $result,
            'message' => $result ? 'Campaign cancelled.' : 'Campaign could not be cancelled.',
        );
    }

    public function get_subscriber_lists()
    {
        return array('items' => SubscriberLists::get_all());
    }

    public function save_subscriber_list($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $list_id = isset($params['id']) ? (int) $params['id'] : 0;
        $name = isset($params['name']) ? (string) $params['name'] : '';
        $description = isset($params['description']) ? (string) $params['description'] : '';

        if ($list_id > 0) {
            $updated = SubscriberLists::update($list_id, $name, $description);
            return array('success' => (bool) $updated, 'id' => $list_id);
        }

        $created = SubscriberLists::create($name, $description);
        return array('success' => (bool) $created, 'id' => (int) $created);
    }

    public function delete_subscriber_list($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $list_id = isset($params['id']) ? (int) $params['id'] : 0;
        return array('success' => SubscriberLists::delete($list_id));
    }

    public function get_list_subscribers($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $list_id = isset($params['id']) ? (int) $params['id'] : 0;
        $status = isset($params['status']) ? (string) $params['status'] : 'subscribed';

        if ($status === 'unsubscribed') {
            return array('items' => SubscriberLists::get_unsubscribed($list_id, 1000, 0));
        }

        return array('items' => SubscriberLists::get_subscribers($list_id, 1000, 0));
    }

    public function add_list_subscriber($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $list_id = isset($params['id']) ? (int) $params['id'] : 0;

        if (!empty($params['csv_content'])) {
            return SubscriberLists::import_from_csv($list_id, (string) $params['csv_content']);
        }

        $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        $result = SubscriberLists::add_subscriber($list_id, $user_id);
        if ($result instanceof \WP_Error) {
            return array('success' => false, 'message' => $result->get_error_message());
        }

        return array('success' => (bool) $result);
    }

    public function remove_list_subscriber($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $list_id = isset($params['id']) ? (int) $params['id'] : 0;
        $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        $result = SubscriberLists::remove_subscriber($list_id, $user_id);

        return array('success' => (bool) $result);
    }

    public function search_network_users($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $query = isset($params['q']) ? sanitize_text_field((string) $params['q']) : '';
        if (!function_exists('get_users')) {
            return array('items' => array());
        }

        $users = get_users(array(
            'search' => '*' . $query . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'number' => 20,
        ));

        $items = array();
        foreach ((array) $users as $user) {
            $items[] = array(
                'id' => isset($user->ID) ? (int) $user->ID : (isset($user['ID']) ? (int) $user['ID'] : 0),
                'login' => isset($user->user_login) ? (string) $user->user_login : (isset($user['user_login']) ? (string) $user['user_login'] : ''),
                'email' => isset($user->user_email) ? (string) $user->user_email : (isset($user['user_email']) ? (string) $user['user_email'] : ''),
            );
        }

        return array('items' => $items);
    }

    public function check_unsubscribed($request)
    {
        $params = method_exists($request, 'get_params') ? $request->get_params() : (array) $request;
        $list_id = isset($params['id']) ? (int) $params['id'] : 0;
        $user_id = isset($params['user_id']) ? (int) $params['user_id'] : 0;
        $blocked = SubscriberLists::is_unsubscribed($list_id, $user_id);

        return array(
            'can_add' => !$blocked,
            'message' => $blocked ? 'User is in unsubscribed list. Cannot add.' : 'User can be added.',
        );
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

        $events = $this->extract_webhook_events($provider, $data);
        foreach ($events as $event) {
            $event_type = isset($event['event_type']) ? (string) $event['event_type'] : '';
            $recipient = isset($event['recipient']) ? (string) $event['recipient'] : '';
            $message_id = isset($event['message_id']) ? (string) $event['message_id'] : '';
            $timestamp = isset($event['timestamp']) ? (string) $event['timestamp'] : '';
            $payload = isset($event['payload']) && is_array($event['payload']) ? $event['payload'] : array();
            $status = Queue::map_webhook_status($provider, $event_type, $payload);

            Logger::info('Webhook event processed.', array(
                'provider'   => $provider,
                'event_type' => $event_type,
                'status'     => $status,
                'recipient'  => $recipient,
                'message_id' => $message_id,
            ));

            if ($status === '') {
                continue;
            }

            Queue::update_status_from_webhook($provider, $message_id, $status, $payload, $recipient, $timestamp);

            if ($recipient !== '' && in_array($status, array('bounce', 'invalid_email', 'complaint'), true)) {
                $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
                Suppression::add($site_id, $recipient, 'Auto-suppressed on ' . $provider . ' event: ' . $status);
                Logger::info('Auto-suppressed address after provider webhook.', array('email' => $recipient, 'provider' => $provider, 'event' => $status));
            }
        }

        return array('success' => true, 'provider' => $provider, 'event_count' => count($events));
    }

    /**
     * @param array<string,mixed> $data
     * @return array<int,array<string,string>>
     */
    private function extract_webhook_events(string $provider, array $data): array
    {
        $events = array();

        switch ($provider) {
            case 'mailgun':
                $event_data = isset($data['event-data']) && is_array($data['event-data']) ? $data['event-data'] : $data;
                $events[] = array(
                    'event_type' => isset($event_data['event']) ? (string) $event_data['event'] : '',
                    'recipient' => isset($event_data['recipient']) ? (string) $event_data['recipient'] : '',
                    'message_id' => isset($event_data['message']['headers']['message-id']) ? (string) $event_data['message']['headers']['message-id'] : '',
                    'timestamp' => isset($event_data['timestamp']) ? gmdate('Y-m-d H:i:s', (int) $event_data['timestamp']) : '',
                    'payload' => $event_data,
                );
                break;
            case 'sendgrid':
                $items = is_array($data) && isset($data[0]) ? $data : array($data);
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $events[] = array(
                        'event_type' => isset($item['event']) ? (string) $item['event'] : '',
                        'recipient' => isset($item['email']) ? (string) $item['email'] : '',
                        'message_id' => isset($item['message-id']) ? (string) $item['message-id'] : (isset($item['sg_message_id']) ? (string) $item['sg_message_id'] : ''),
                        'timestamp' => isset($item['timestamp']) ? gmdate('Y-m-d H:i:s', (int) $item['timestamp']) : '',
                        'payload' => $item,
                    );
                }
                break;
            case 'brevo':
                $events[] = array(
                    'event_type' => isset($data['event']) ? (string) $data['event'] : '',
                    'recipient' => isset($data['email']) ? (string) $data['email'] : '',
                    'message_id' => isset($data['message-id']) ? (string) $data['message-id'] : '',
                    'timestamp' => isset($data['ts_event']) ? gmdate('Y-m-d H:i:s', (int) $data['ts_event']) : '',
                    'payload' => $data,
                );
                break;
            case 'postmark':
                $events[] = array(
                    'event_type' => isset($data['RecordType']) ? (string) $data['RecordType'] : '',
                    'recipient' => isset($data['Recipient']) ? (string) $data['Recipient'] : (isset($data['Email']) ? (string) $data['Email'] : ''),
                    'message_id' => isset($data['MessageID']) ? (string) $data['MessageID'] : '',
                    'timestamp' => isset($data['ReceivedAt']) ? (string) $data['ReceivedAt'] : '',
                    'payload' => $data,
                );
                break;
            case 'smtp2go':
                $events[] = array(
                    'event_type' => isset($data['type']) ? (string) $data['type'] : '',
                    'recipient' => isset($data['recipient']) ? (string) $data['recipient'] : '',
                    'message_id' => isset($data['request_id']) ? (string) $data['request_id'] : '',
                    'timestamp' => isset($data['time']) ? (string) $data['time'] : '',
                    'payload' => $data,
                );
                break;
        }

        return $events;
    }
}
