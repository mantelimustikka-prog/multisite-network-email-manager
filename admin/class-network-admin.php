<?php

class MNEM_Network_Admin
{
    protected $smtp_settings;
    protected $smtp_diagnostics;
    protected $queue;
    protected $suppression_list;
    protected $campaigns;
    protected $logger;

    public function __construct(MNEM_SMTP_Settings $smtp_settings = null, MNEM_SMTP_Diagnostics $smtp_diagnostics = null, MNEM_Queue $queue = null, MNEM_Suppression_List $suppression_list = null, MNEM_Campaigns $campaigns = null, MNEM_Logger $logger = null)
    {
        $this->smtp_settings = $smtp_settings ?: new MNEM_SMTP_Settings();
        $this->smtp_diagnostics = $smtp_diagnostics ?: new MNEM_SMTP_Diagnostics($this->smtp_settings);
        $this->queue = $queue ?: new MNEM_Queue();
        $this->suppression_list = $suppression_list ?: new MNEM_Suppression_List();
        $this->campaigns = $campaigns ?: new MNEM_Campaigns();
        $this->logger = $logger ?: new MNEM_Logger();
    }

    public function register()
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('network_admin_menu', array($this, 'register_menu'));
        add_action('admin_post_mnem_save_smtp', array($this, 'handle_save_smtp'));
        add_action('admin_post_mnem_test_smtp', array($this, 'handle_test_smtp'));
        add_action('admin_post_mnem_send_test_email', array($this, 'handle_send_test_email'));
        add_action('admin_post_mnem_add_suppression', array($this, 'handle_add_suppression'));
        add_action('admin_post_mnem_delete_suppression', array($this, 'handle_delete_suppression'));
        add_action('admin_post_mnem_save_campaign', array($this, 'handle_save_campaign'));
        add_action('admin_post_mnem_transition_campaign', array($this, 'handle_transition_campaign'));
        add_action('admin_post_mnem_process_queue', array($this, 'handle_process_queue'));
    }

    public function register_menu()
    {
        if (! $this->current_user_can_manage()) {
            return;
        }

        add_menu_page('Email Manager', 'Email Manager', 'manage_network_options', 'mnem-dashboard', array($this, 'render_dashboard'), 'dashicons-email-alt', 58);
        add_submenu_page('mnem-dashboard', 'Dashboard', 'Dashboard', 'manage_network_options', 'mnem-dashboard', array($this, 'render_dashboard'));
        add_submenu_page('mnem-dashboard', 'SMTP Settings', 'SMTP Settings', 'manage_network_options', 'mnem-smtp', array($this, 'render_smtp'));
        add_submenu_page('mnem-dashboard', 'Campaigns', 'Campaigns', 'manage_network_options', 'mnem-campaigns', array($this, 'render_campaigns'));
        add_submenu_page('mnem-dashboard', 'Queue', 'Queue', 'manage_network_options', 'mnem-queue', array($this, 'render_queue'));
        add_submenu_page('mnem-dashboard', 'Suppression', 'Suppression', 'manage_network_options', 'mnem-suppression', array($this, 'render_suppression'));
        add_submenu_page('mnem-dashboard', 'Logs', 'Logs', 'manage_network_options', 'mnem-logs', array($this, 'render_logs'));
    }

    public function render_dashboard()
    {
        $this->render_view('dashboard', array(
            'queue' => $this->queue->all(10),
            'campaigns' => $this->campaigns->all(10),
            'suppressions' => $this->suppression_list->all(10),
        ));
    }

    public function render_smtp()
    {
        $settings = $this->smtp_settings->export();
        $settings['password'] = '';
        $this->render_view('smtp-settings', array('settings' => $settings));
    }

    public function render_campaigns()
    {
        $this->render_view('campaigns', array('campaigns' => $this->campaigns->all(50)));
    }

    public function render_queue()
    {
        $this->render_view('queue', array('items' => $this->queue->all(50)));
    }

    public function render_suppression()
    {
        $this->render_view('suppression', array('entries' => $this->suppression_list->all(50)));
    }

    public function render_logs()
    {
        $this->render_view('logs', array('entries' => $this->logger->all()));
    }

    public function handle_save_smtp()
    {
        $this->verify_request('mnem_save_smtp');
        $data = array(
            'enabled' => ! empty($_POST['enabled']),
            'host' => isset($_POST['host']) ? wp_unslash($_POST['host']) : '',
            'port' => isset($_POST['port']) ? (int) $_POST['port'] : 587,
            'secure' => isset($_POST['secure']) ? wp_unslash($_POST['secure']) : 'tls',
            'username' => isset($_POST['username']) ? wp_unslash($_POST['username']) : '',
            'password' => isset($_POST['password']) ? wp_unslash($_POST['password']) : '',
            'from_email' => isset($_POST['from_email']) ? wp_unslash($_POST['from_email']) : '',
            'from_name' => isset($_POST['from_name']) ? wp_unslash($_POST['from_name']) : '',
        );
        $this->smtp_settings->update($data);
        $this->redirect_with_notice('mnem-smtp', 'smtp_saved');
    }

    public function handle_test_smtp()
    {
        $this->verify_request('mnem_test_smtp');
        $result = $this->smtp_diagnostics->test_connection();
        $this->redirect_with_notice('mnem-smtp', $result['success'] ? 'smtp_test_ok' : 'smtp_test_fail', $result['message']);
    }

    public function handle_send_test_email()
    {
        $this->verify_request('mnem_send_test_email');
        $to = isset($_POST['test_email']) ? wp_unslash($_POST['test_email']) : '';
        $result = $this->smtp_diagnostics->send_test_email($to);
        $this->redirect_with_notice('mnem-smtp', $result['success'] ? 'email_test_ok' : 'email_test_fail', $result['message']);
    }

    public function handle_add_suppression()
    {
        $this->verify_request('mnem_add_suppression');
        $email = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $reason = isset($_POST['reason']) ? wp_unslash($_POST['reason']) : '';
        $success = $this->suppression_list->add($email, $reason);
        $this->redirect_with_notice('mnem-suppression', $success ? 'suppression_added' : 'suppression_failed');
    }

    public function handle_delete_suppression()
    {
        $this->verify_request('mnem_delete_suppression');
        $email = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
        $success = $this->suppression_list->remove($email);
        $this->redirect_with_notice('mnem-suppression', $success ? 'suppression_removed' : 'suppression_failed');
    }

    public function handle_save_campaign()
    {
        $this->verify_request('mnem_save_campaign');
        $success = $this->campaigns->create(array(
            'name' => isset($_POST['name']) ? wp_unslash($_POST['name']) : '',
            'subject' => isset($_POST['subject']) ? wp_unslash($_POST['subject']) : '',
            'content' => isset($_POST['content']) ? wp_unslash($_POST['content']) : '',
            'status' => 'draft',
        ));
        $this->redirect_with_notice('mnem-campaigns', $success ? 'campaign_saved' : 'campaign_failed');
    }

    public function handle_transition_campaign()
    {
        $this->verify_request('mnem_transition_campaign');
        $id = isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0;
        $status = isset($_POST['status']) ? wp_unslash($_POST['status']) : '';
        $success = $this->campaigns->transition($id, $status);
        $this->redirect_with_notice('mnem-campaigns', $success ? 'campaign_transitioned' : 'campaign_failed');
    }

    public function handle_process_queue()
    {
        $this->verify_request('mnem_process_queue');
        $result = $this->queue->process_batch(10);
        $message = sprintf('Sent %d, failed %d, skipped %d.', $result['sent'], $result['failed'], $result['skipped']);
        $this->redirect_with_notice('mnem-queue', 'queue_processed', $message);
    }

    protected function render_view($view, array $data = array())
    {
        if (! $this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to access this page.', 'multisite-network-email-manager'));
        }

        $file = MNEM_PLUGIN_DIR . 'admin/views/' . $view . '.php';
        if (! file_exists($file)) {
            wp_die(esc_html__('View not found.', 'multisite-network-email-manager'));
        }

        $notice_code = isset($_GET['mnem_notice']) ? sanitize_key(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = isset($_GET['mnem_message']) ? sanitize_text_field(wp_unslash($_GET['mnem_message'])) : '';
        extract($data, EXTR_SKIP);
        include $file;
    }

    protected function verify_request($nonce_action)
    {
        if (! $this->current_user_can_manage()) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'multisite-network-email-manager'));
        }

        check_admin_referer($nonce_action);
    }

    protected function redirect_with_notice($page, $notice, $message = '')
    {
        $url = add_query_arg(array_filter(array(
            'page' => $page,
            'mnem_notice' => $notice,
            'mnem_message' => $message,
        )), network_admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    protected function current_user_can_manage()
    {
        return ! function_exists('current_user_can') || current_user_can('manage_network_options');
    }
}
