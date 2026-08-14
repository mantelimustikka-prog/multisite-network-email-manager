<?php

$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!defined('MNEM_VERSION')) {
    define('MNEM_VERSION', '1.0.0');
}

if (!defined('MNEM_DB_VERSION')) {
    define('MNEM_DB_VERSION', '2');
}

if (!defined('MNEM_PLUGIN_DIR')) {
    define('MNEM_PLUGIN_DIR', dirname(__DIR__) . '/');
}

if (!defined('MNEM_PLUGIN_URL')) {
    define('MNEM_PLUGIN_URL', 'http://example.org/wp-content/plugins/mnem/');
}

if (!defined('MNEM_PLUGIN_FILE')) {
    define('MNEM_PLUGIN_FILE', dirname(__DIR__) . '/multisite-network-email-manager.php');
}

$GLOBALS['mnem_site_options'] = array();
$GLOBALS['mnem_hooks'] = array();
$GLOBALS['mnem_cron_events'] = array();
$GLOBALS['wpdb'] = null;

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value)
    {
        return json_encode($value);
    }
}

if (!function_exists('get_site_option')) {
    function get_site_option($key, $default = false)
    {
        return array_key_exists($key, $GLOBALS['mnem_site_options']) ? $GLOBALS['mnem_site_options'][$key] : $default;
    }
}

if (!function_exists('update_site_option')) {
    function update_site_option($key, $value)
    {
        $GLOBALS['mnem_site_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return filter_var((string) $email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('is_email')) {
    function is_email($email)
    {
        return (bool) filter_var((string) $email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return trim(filter_var((string) $value, FILTER_UNSAFE_RAW));
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        if (is_array($value)) {
            return array_map('wp_unslash', $value);
        }

        return stripslashes((string) $value);
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback)
    {
        $GLOBALS['mnem_hooks'][$hook][] = $callback;
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback)
    {
        $GLOBALS['mnem_hooks'][$hook][] = $callback;
        return true;
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route($namespace, $route, $args)
    {
        $GLOBALS['mnem_hooks']['rest_routes'][] = array($namespace, $route, $args);
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability)
    {
        return in_array($capability, array('manage_network', 'manage_network_options'), true);
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite()
    {
        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin()
    {
        return true;
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id()
    {
        return 1;
    }
}

if (!function_exists('get_users')) {
    function get_users($args = array())
    {
        return isset($GLOBALS['mnem_users']) ? $GLOBALS['mnem_users'] : array();
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata($user_id)
    {
        if (!isset($GLOBALS['mnem_user_data']) || !is_array($GLOBALS['mnem_user_data'])) {
            return null;
        }

        return isset($GLOBALS['mnem_user_data'][$user_id]) ? $GLOBALS['mnem_user_data'][$user_id] : null;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = false)
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message)
    {
        return true;
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id()
    {
        return 1;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action)
    {
        echo '<input type="hidden" name="_wpnonce" value="test-nonce" />';
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action)
    {
        return true;
    }
}

if (!function_exists('wp_safe_redirect')) {
    function wp_safe_redirect($url)
    {
        $GLOBALS['mnem_last_redirect'] = $url;
        return true;
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '')
    {
        return 'http://example.org/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action)
    {
        return 'test-nonce';
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style()
    {
        return true;
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script()
    {
        return true;
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script()
    {
        return true;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action, $query_arg = false)
    {
        return true;
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4()
    {
        return '00000000-0000-4000-8000-000000000001';
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null)
    {
        return array('success' => true, 'data' => $data);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null)
    {
        return array('success' => false, 'data' => $data, 'status_code' => $status_code);
    }
}

if (!function_exists('network_admin_url')) {
    function network_admin_url($path = '')
    {
        return 'http://example.org/wp-admin/network/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($timestamp, $recurrence, $hook)
    {
        $GLOBALS['mnem_cron_events'][$hook] = array(
            'timestamp' => (int) $timestamp,
            'recurrence' => (string) $recurrence,
        );
        return true;
    }
}

if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook)
    {
        if (!isset($GLOBALS['mnem_cron_events'][$hook])) {
            return false;
        }

        return (int) $GLOBALS['mnem_cron_events'][$hook]['timestamp'];
    }
}

if (!function_exists('wp_get_scheduled_event')) {
    function wp_get_scheduled_event($hook)
    {
        if (!isset($GLOBALS['mnem_cron_events'][$hook])) {
            return false;
        }

        return (object) array(
            'hook' => $hook,
            'timestamp' => $GLOBALS['mnem_cron_events'][$hook]['timestamp'],
            'schedule' => $GLOBALS['mnem_cron_events'][$hook]['recurrence'],
        );
    }
}

if (!function_exists('wp_unschedule_event')) {
    function wp_unschedule_event($timestamp, $hook)
    {
        unset($GLOBALS['mnem_cron_events'][$hook]);
        return true;
    }
}

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook)
    {
        unset($GLOBALS['mnem_cron_events'][$hook]);
        return true;
    }
}

if (!function_exists('wp_get_schedules')) {
    function wp_get_schedules()
    {
        return array(
            'hourly' => array('interval' => 3600, 'display' => 'Once Hourly'),
            'daily' => array('interval' => 86400, 'display' => 'Once Daily'),
            'mnem_5_minutes' => array('interval' => 300, 'display' => 'Every 5 Minutes'),
            'mnem_15_minutes' => array('interval' => 900, 'display' => 'Every 15 Minutes'),
            'mnem_30_minutes' => array('interval' => 1800, 'display' => 'Every 30 Minutes'),
            'mnem_6_hours' => array('interval' => 21600, 'display' => 'Every 6 Hours'),
            'mnem_12_hours' => array('interval' => 43200, 'display' => 'Every 12 Hours'),
        );
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = 'Submit', $type = 'primary', $name = 'submit', $wrap = true)
    {
        echo '<button type="submit">' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</button>';
    }
}

if (!function_exists('selected')) {
    function selected($current, $value)
    {
        if ((string) $current === (string) $value) {
            echo 'selected="selected"';
        }
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_textarea')) {
    function esc_textarea($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('add_menu_page')) {
    function add_menu_page()
    {
        return true;
    }
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page()
    {
        return true;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $code;
        public $message;
        public $data;

        public function __construct($code = '', $message = '', $data = null)
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_message()
        {
            return (string) $this->message;
        }

        public function get_error_code()
        {
            return $this->code;
        }
    }
}

if (!class_exists('wpdb')) {
    class wpdb
    {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $queries = array();
        public $results = array();
        public $var = null;
        public $row = null;
        public $col = array();

        public function prepare($query)
        {
            $args = func_get_args();
            array_shift($args);
            $index = 0;

            return preg_replace_callback('/%[ds]/', function ($matches) use (&$args, &$index) {
                $value = $args[$index++];
                if ($matches[0] === '%d') {
                    return (string) (int) $value;
                }
                return "'" . str_replace("'", "\'", (string) $value) . "'";
            }, $query);
        }

        public function query($query)
        {
            $this->queries[] = $query;
            return 1;
        }

        public function get_var($query)
        {
            $this->queries[] = $query;
            return $this->var;
        }

        public function get_results($query, $output = OBJECT)
        {
            $this->queries[] = $query;
            return $this->results;
        }

        public function get_row($query, $output = OBJECT)
        {
            $this->queries[] = $query;
            return $this->row;
        }

        public function get_col($query)
        {
            $this->queries[] = $query;
            return $this->col;
        }

        public function get_charset_collate()
        {
            return '';
        }
    }
}

if ($GLOBALS['wpdb'] === null) {
    $GLOBALS['wpdb'] = new wpdb();
}

if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array())
    {
        return isset($GLOBALS['mnem_http_response']) ? $GLOBALS['mnem_http_response'] : new WP_Error('http_request_failed', 'Mock not set.');
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = array())
    {
        return isset($GLOBALS['mnem_http_response']) ? $GLOBALS['mnem_http_response'] : new WP_Error('http_request_failed', 'Mock not set.');
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        if (is_array($response) && isset($response['response']['code'])) {
            return $response['response']['code'];
        }
        return 0;
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        if (is_array($response) && isset($response['body'])) {
            return $response['body'];
        }
        return '';
    }
}

if (!function_exists('wp_remote_retrieve_header')) {
    function wp_remote_retrieve_header($response, $header)
    {
        if (is_array($response) && isset($response['headers'][$header])) {
            return $response['headers'][$header];
        }
        return '';
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text)
    {
        return strip_tags((string) $text);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($content)
    {
        return strip_tags((string) $content, '<a><p><br><strong><em><u><img><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><hr><span><div>');
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '')
    {
        if ($show === 'name') {
            return 'Test Site';
        }
        return '';
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false)
    {
        return array_key_exists($key, $GLOBALS['mnem_site_options']) ? $GLOBALS['mnem_site_options'][$key] : $default;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = '')
    {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = '')
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('checked')) {
    function checked($current, $value = true, $echo = true)
    {
        if ((string) $current === (string) $value) {
            if ($echo) {
                echo 'checked="checked"';
            }
            return 'checked="checked"';
        }
        return '';
    }
}

require_once __DIR__ . '/../includes/class-settings.php';
require_once __DIR__ . '/../includes/class-logger.php';
require_once __DIR__ . '/../includes/class-smtp-settings.php';
require_once __DIR__ . '/../includes/class-email-formatter.php';
require_once __DIR__ . '/../includes/class-suppression.php';
require_once __DIR__ . '/../includes/class-email-provider.php';
require_once __DIR__ . '/../includes/class-smtp-provider.php';
require_once __DIR__ . '/../includes/class-mailgun-provider.php';
require_once __DIR__ . '/../includes/class-sendgrid-provider.php';
require_once __DIR__ . '/../includes/class-brevo-provider.php';
require_once __DIR__ . '/../includes/class-postmark-provider.php';
require_once __DIR__ . '/../includes/class-smtp2go-provider.php';
require_once __DIR__ . '/../includes/class-provider-manager.php';
require_once __DIR__ . '/../includes/class-queue.php';
require_once __DIR__ . '/../includes/class-campaigns.php';
require_once __DIR__ . '/../includes/class-cron.php';
require_once __DIR__ . '/../includes/class-user-events-campaign.php';
require_once __DIR__ . '/../includes/class-user-events.php';
require_once __DIR__ . '/../includes/class-smtp-diagnostics.php';
require_once __DIR__ . '/../includes/class-rest-api.php';
require_once __DIR__ . '/../admin/class-admin-menu.php';
require_once __DIR__ . '/../admin/class-network-admin.php';
require_once __DIR__ . '/../admin/class-admin.php';
