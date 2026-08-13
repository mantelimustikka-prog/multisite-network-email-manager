<?php

namespace MNEM\Admin;

defined('ABSPATH') || exit;

class NetworkAdmin
{
    public function init()
    {
        add_action('admin_init', array($this, 'handle_smtp_save'));
        add_action('admin_init', array($this, 'handle_suppression_action'));
        add_action('admin_init', array($this, 'handle_campaign_action'));
        add_action('admin_init', array($this, 'handle_queue_action'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_mnem_dashboard_stats', array($this, 'ajax_dashboard_stats'));
        add_action('wp_ajax_mnem_process_queue', array($this, 'ajax_process_queue'));
        add_action('wp_ajax_mnem_retry_failed_queue', array($this, 'ajax_retry_failed_queue'));
        add_action('wp_ajax_mnem_toggle_campaign_pause', array($this, 'ajax_toggle_campaign_pause'));

        $menu = new AdminMenu();
        $menu->init();
    }

    public function handle_smtp_save()
    {
        if (!isset($_POST['mnem_action']) || !in_array($_POST['mnem_action'], array('save_smtp_settings', 'send_test_email'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_smtp_settings')) {
            $this->redirect_with_notice('mnem-smtp-settings', 'smtp_nonce_failed');
        }

        if ($_POST['mnem_action'] === 'send_test_email') {
            $email = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';
            $result = \MNEM\SmtpDiagnostics::send_test_email($email);
            $this->redirect_with_notice('mnem-smtp-settings', $result['success'] ? 'test_sent' : 'test_failed');
            return;
        }

        $data = array(
            'host' => isset($_POST['host']) ? wp_unslash($_POST['host']) : '',
            'port' => isset($_POST['port']) ? wp_unslash($_POST['port']) : '',
            'encryption' => isset($_POST['encryption']) ? wp_unslash($_POST['encryption']) : 'tls',
            'username' => isset($_POST['username']) ? wp_unslash($_POST['username']) : '',
            'password' => isset($_POST['password']) ? wp_unslash($_POST['password']) : '',
            'from_email' => isset($_POST['from_email']) ? wp_unslash($_POST['from_email']) : '',
            'from_name' => isset($_POST['from_name']) ? wp_unslash($_POST['from_name']) : '',
        );

        $saved = \MNEM\SmtpSettings::save($data);
        $this->redirect_with_notice('mnem-smtp-settings', $saved ? 'smtp_saved' : 'smtp_failed');
    }

    public function handle_suppression_action()
    {
        if (!isset($_POST['mnem_action']) || !in_array($_POST['mnem_action'], array('add_suppression', 'remove_suppression'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_suppression')) {
            $this->redirect_with_notice('mnem-suppression', 'suppression_nonce_failed');
        }

        $site_id = isset($_POST['site_id']) ? (int) $_POST['site_id'] : (function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1);

        if ($_POST['mnem_action'] === 'add_suppression') {
            $result = \MNEM\Suppression::add(
                $site_id,
                isset($_POST['email']) ? wp_unslash($_POST['email']) : '',
                isset($_POST['reason']) ? wp_unslash($_POST['reason']) : ''
            );
            $this->redirect_with_notice('mnem-suppression', $result ? 'suppression_added' : 'suppression_failed');
        }

        $result = \MNEM\Suppression::remove($site_id, isset($_POST['email']) ? wp_unslash($_POST['email']) : '');
        $this->redirect_with_notice('mnem-suppression', $result ? 'suppression_removed' : 'suppression_failed');
    }

    public function handle_campaign_action()
    {
        if (!isset($_POST['mnem_action']) || !in_array($_POST['mnem_action'], array('save_campaign', 'send_campaign', 'delete_campaign'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_campaign')) {
            $this->redirect_with_notice($this->get_campaign_redirect_page(), 'campaign_nonce_failed');
        }

        $page = $this->get_campaign_redirect_page();
        $campaign_id = isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0;

        if ($_POST['mnem_action'] === 'send_campaign') {
            $result = \MNEM\Campaigns::send_campaign($campaign_id);
            $notice = !empty($result['success']) ? 'campaign_sent' : 'campaign_send_failed';
            $this->redirect_with_notice($page, $notice);
        }

        if ($_POST['mnem_action'] === 'delete_campaign') {
            $result = \MNEM\Campaigns::delete($campaign_id);
            $this->redirect_with_notice($page, $result ? 'campaign_deleted' : 'campaign_delete_failed');
        }

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $data = array(
            'name' => isset($_POST['name']) ? wp_unslash($_POST['name']) : '',
            'subject' => isset($_POST['subject']) ? wp_unslash($_POST['subject']) : '',
            'body' => isset($_POST['body']) ? wp_unslash($_POST['body']) : '',
            'status' => isset($_POST['status']) ? wp_unslash($_POST['status']) : 'draft',
            'scheduled_at' => isset($_POST['scheduled_at']) ? wp_unslash($_POST['scheduled_at']) : '',
            'recipient_scope' => isset($_POST['recipient_scope']) ? wp_unslash($_POST['recipient_scope']) : 'all_users',
            'recipient_list' => isset($_POST['recipient_list']) ? wp_unslash($_POST['recipient_list']) : '',
        );

        if ($campaign_id > 0) {
            $result = \MNEM\Campaigns::update($campaign_id, $data);
            $this->redirect_with_notice($page, $result ? 'campaign_updated' : 'campaign_save_failed');
        }

        $result = \MNEM\Campaigns::create($site_id, $data['name'], $data['subject'], $data['body'], $data);
        $this->redirect_with_notice($page, $result ? 'campaign_created' : 'campaign_save_failed');
    }

    public function handle_queue_action()
    {
        if (!isset($_POST['mnem_action']) || !in_array($_POST['mnem_action'], array('process_queue_now', 'retry_failed_queue', 'toggle_campaign_pause'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_queue')) {
            $this->redirect_with_notice($this->get_queue_redirect_page(), 'queue_nonce_failed');
        }

        $page = $this->get_queue_redirect_page();
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;

        if ($_POST['mnem_action'] === 'process_queue_now') {
            $processed = \MNEM\Queue::process_batch(50);
            \MNEM\Logger::info('Manual queue processing triggered.', array('site_id' => $site_id, 'processed' => $processed));
            $this->redirect_with_notice($page, 'queue_processed', array('processed' => $processed));
        }

        if ($_POST['mnem_action'] === 'retry_failed_queue') {
            $retried = \MNEM\Queue::retry_failed($site_id);
            $this->redirect_with_notice($page, 'queue_retried', array('retried' => $retried));
        }

        $paused = (int) get_site_option('mnem_campaign_sends_paused', 0) === 1 ? 0 : 1;
        update_site_option('mnem_campaign_sends_paused', $paused);
        \MNEM\Logger::info('Campaign sending pause state changed.', array('site_id' => $site_id, 'paused' => $paused));
        $this->redirect_with_notice($page, $paused === 1 ? 'campaign_paused' : 'campaign_resumed');
    }

    public function enqueue_assets($hook_suffix)
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if (strpos($page, 'mnem-') !== 0) {
            return;
        }

        if (function_exists('wp_enqueue_style') && file_exists(MNEM_PLUGIN_DIR . 'assets/admin.css')) {
            wp_enqueue_style('mnem-admin', MNEM_PLUGIN_URL . 'assets/admin.css', array(), MNEM_VERSION);
        }

        if (function_exists('wp_enqueue_script') && file_exists(MNEM_PLUGIN_DIR . 'assets/admin.js')) {
            wp_enqueue_script('mnem-admin', MNEM_PLUGIN_URL . 'assets/admin.js', array('jquery'), MNEM_VERSION, true);

            if (function_exists('wp_localize_script')) {
                wp_localize_script(
                    'mnem-admin',
                    'mnemAdmin',
                    array(
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('mnem_dashboard_ajax') : '',
                    )
                );
            }
        }
    }

    public function ajax_dashboard_stats()
    {
        $this->ensure_ajax_permissions();

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        wp_send_json_success(
            array(
                'queue' => \MNEM\Queue::get_stats($site_id),
                'campaigns' => \MNEM\Campaigns::get_list($site_id, '', 10, 0),
                'paused' => (int) get_site_option('mnem_campaign_sends_paused', 0) === 1,
            )
        );
    }

    public function ajax_process_queue()
    {
        $this->ensure_ajax_permissions();
        wp_send_json_success(array('processed' => \MNEM\Queue::process_batch(50)));
    }

    public function ajax_retry_failed_queue()
    {
        $this->ensure_ajax_permissions();
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        wp_send_json_success(array('retried' => \MNEM\Queue::retry_failed($site_id)));
    }

    public function ajax_toggle_campaign_pause()
    {
        $this->ensure_ajax_permissions();
        $paused = (int) get_site_option('mnem_campaign_sends_paused', 0) === 1 ? 0 : 1;
        update_site_option('mnem_campaign_sends_paused', $paused);
        wp_send_json_success(array('paused' => (bool) $paused));
    }

    private function current_user_can_manage_network()
    {
        return function_exists('current_user_can') && current_user_can('manage_network_options');
    }

    private function verify_nonce($nonce, $action)
    {
        return function_exists('wp_verify_nonce') ? (bool) wp_verify_nonce($nonce, $action) : false;
    }

    private function ensure_ajax_permissions()
    {
        if (!$this->current_user_can_manage_network()) {
            wp_send_json_error(array('message' => 'Forbidden'), 403);
        }

        if (function_exists('check_ajax_referer')) {
            check_ajax_referer('mnem_dashboard_ajax', 'nonce');
        }
    }

    private function get_campaign_redirect_page()
    {
        $page = isset($_POST['redirect_page']) ? sanitize_text_field(wp_unslash($_POST['redirect_page'])) : 'mnem-campaigns';

        return strpos($page, 'mnem-') === 0 ? $page : 'mnem-campaigns';
    }

    private function get_queue_redirect_page()
    {
        $page = isset($_POST['redirect_page']) ? sanitize_text_field(wp_unslash($_POST['redirect_page'])) : 'mnem-dashboard';

        return strpos($page, 'mnem-') === 0 ? $page : 'mnem-dashboard';
    }

    private function redirect_with_notice($page, $notice, array $extra_args = array())
    {
        $args = array_merge(
            array(
                'page' => $page,
                'mnem_notice' => $notice,
            ),
            $extra_args
        );

        $url = function_exists('network_admin_url')
            ? network_admin_url('admin.php?' . http_build_query($args, '', '&'))
            : 'admin.php?' . http_build_query($args, '', '&');
        wp_safe_redirect($url);
        exit;
    }
}
