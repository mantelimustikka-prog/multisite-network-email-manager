<?php

namespace MNEM\Admin;

defined('ABSPATH') || exit;

class AdminMenu
{
    public function init()
    {
        add_action('network_admin_menu', array($this, 'register_menus'));
    }

    public function register_menus()
    {
        add_menu_page(
            'MNEM Dashboard',
            'Email Manager',
            'manage_network_options',
            'mnem-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-email-alt',
            58
        );

        add_submenu_page('mnem-dashboard', 'Dashboard', 'Dashboard', 'manage_network_options', 'mnem-dashboard', array($this, 'render_dashboard'));
        add_submenu_page('mnem-dashboard', 'SMTP Settings', 'SMTP Settings', 'manage_network_options', 'mnem-smtp-settings', array($this, 'render_smtp_settings'));
        add_submenu_page('mnem-dashboard', 'Campaigns', 'Campaigns', 'manage_network_options', 'mnem-campaigns', array($this, 'render_campaigns'));
        add_submenu_page('mnem-dashboard', 'Queue', 'Queue', 'manage_network_options', 'mnem-queue', array($this, 'render_queue'));
        add_submenu_page('mnem-dashboard', 'Suppression', 'Suppression', 'manage_network_options', 'mnem-suppression', array($this, 'render_suppression'));
        add_submenu_page('mnem-dashboard', 'Logs', 'Logs', 'manage_network_options', 'mnem-logs', array($this, 'render_logs'));
    }

    public function render_dashboard()
    {
        global $wpdb;

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $logs_table = $wpdb->prefix . 'mnem_logs';
        $plugin_version = defined('MNEM_VERSION') ? MNEM_VERSION : '1.0.0';
        $queue_stats = \MNEM\Queue::get_stats($site_id);
        $suppression_count = \MNEM\Suppression::count($site_id);
        $recent_logs = (array) $wpdb->get_results($wpdb->prepare("SELECT level, message, created_at FROM {$logs_table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d", $site_id, 10), ARRAY_A);
        $smtp_configured = \MNEM\SmtpSettings::get('host', '') !== '';
        $campaigns = \MNEM\Campaigns::get_list($site_id, '', 10, 0);
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);
        $campaign_sends_paused = (int) get_site_option('mnem_campaign_sends_paused', 0) === 1;
        $processed = isset($_GET['processed']) ? (int) $_GET['processed'] : 0;
        $retried = isset($_GET['retried']) ? (int) $_GET['retried'] : 0;

        $this->render_view('dashboard.php', compact('plugin_version', 'queue_stats', 'suppression_count', 'recent_logs', 'smtp_configured', 'campaigns', 'notice', 'notice_message', 'notice_class', 'campaign_sends_paused', 'processed', 'retried'));
    }

    public function render_smtp_settings()
    {
        $settings = \MNEM\SmtpSettings::get_all();
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';

        $this->render_view('smtp-settings.php', compact('settings', 'notice'));
    }

    public function render_campaigns()
    {
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $campaigns = \MNEM\Campaigns::get_list($site_id, '', 50, 0);
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);
        $edit_campaign_id = isset($_GET['mnem_campaign']) ? (int) $_GET['mnem_campaign'] : 0;
        $edit_campaign = $edit_campaign_id > 0 ? \MNEM\Campaigns::get($edit_campaign_id) : null;

        $this->render_view('campaigns.php', compact('campaigns', 'notice', 'notice_message', 'notice_class', 'edit_campaign'));
    }

    public function render_queue()
    {
        global $wpdb;

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $queue_table = $wpdb->prefix . 'mnem_queue';
        $queue_items = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, campaign_id, recipient_email, subject, status, attempts, scheduled_at, processed_at, created_at FROM {$queue_table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $site_id,
                50,
                0
            ),
            ARRAY_A
        );
        $queue_stats = \MNEM\Queue::get_stats($site_id);
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);

        $this->render_view('queue.php', compact('queue_items', 'queue_stats', 'notice', 'notice_message', 'notice_class'));
    }

    public function render_suppression()
    {
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $suppression_entries = \MNEM\Suppression::get_list($site_id, 100, 0);
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';

        $this->render_view('suppression.php', compact('suppression_entries', 'site_id', 'notice'));
    }

    public function render_logs()
    {
        global $wpdb;

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $logs_table = $wpdb->prefix . 'mnem_logs';
        $logs = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT level, message, created_at FROM {$logs_table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d",
                $site_id,
                50
            ),
            ARRAY_A
        );

        $this->render_view('logs.php', compact('logs'));
    }

    private function render_view($view, array $variables)
    {
        $file = MNEM_PLUGIN_DIR . 'admin/views/' . $view;
        if (!file_exists($file)) {
            echo '<div class="wrap"><p>View not found.</p></div>';
            return;
        }

        extract($variables, EXTR_SKIP);
        include $file;
    }

    private function get_notice_message($notice)
    {
        $messages = array(
            'campaign_created' => 'Campaign created successfully.',
            'campaign_updated' => 'Campaign updated successfully.',
            'campaign_sent' => 'Campaign send has been queued.',
            'campaign_deleted' => 'Campaign deleted successfully.',
            'campaign_paused' => 'Campaign sending is now paused.',
            'campaign_resumed' => 'Campaign sending has resumed.',
            'queue_processed' => 'Queue processed successfully.',
            'queue_retried' => 'Failed queue items were rescheduled.',
            'campaign_nonce_failed' => 'Campaign security check failed.',
            'queue_nonce_failed' => 'Queue security check failed.',
            'campaign_send_failed' => 'Campaign send failed.',
            'campaign_save_failed' => 'Campaign could not be saved.',
            'campaign_delete_failed' => 'Campaign could not be deleted.',
        );

        return isset($messages[$notice]) ? $messages[$notice] : '';
    }

    private function get_notice_class($notice)
    {
        if (in_array($notice, array('campaign_nonce_failed', 'queue_nonce_failed', 'campaign_send_failed', 'campaign_save_failed', 'campaign_delete_failed'), true)) {
            return 'notice notice-error';
        }

        return $notice !== '' ? 'notice notice-success' : '';
    }
}
