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
            'manage_network',
            'mnem-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-email-alt',
            58
        );

        add_submenu_page('mnem-dashboard', 'Dashboard', 'Dashboard', 'manage_network', 'mnem-dashboard', array($this, 'render_dashboard'));
        add_submenu_page('mnem-dashboard', 'SMTP Settings', 'SMTP Settings', 'manage_network', 'mnem-smtp-settings', array($this, 'render_smtp_settings'));
        add_submenu_page('mnem-dashboard', 'Campaigns', 'Campaigns', 'manage_network', 'mnem-campaigns', array($this, 'render_campaigns'));
        add_submenu_page('mnem-dashboard', 'Queue', 'Queue', 'manage_network', 'mnem-queue', array($this, 'render_queue'));
        add_submenu_page('mnem-dashboard', 'Suppression', 'Suppression', 'manage_network', 'mnem-suppression', array($this, 'render_suppression'));
        add_submenu_page('mnem-dashboard', 'Logs', 'Logs', 'manage_network', 'mnem-logs', array($this, 'render_logs'));
    }

    public function render_dashboard()
    {
        global $wpdb;

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $queue_table = $wpdb->prefix . 'mnem_queue';
        $logs_table = $wpdb->prefix . 'mnem_logs';

        $plugin_version = defined('MNEM_VERSION') ? MNEM_VERSION : '1.0.0';
        $queue_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} WHERE site_id = %d", $site_id));
        $suppression_count = \MNEM\Suppression::count($site_id);
        $recent_logs = (array) $wpdb->get_results($wpdb->prepare("SELECT level, message, created_at FROM {$logs_table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d", $site_id, 5), ARRAY_A);
        $smtp_configured = \MNEM\SmtpSettings::get('host', '') !== '';

        $this->render_view('dashboard.php', compact('plugin_version', 'queue_count', 'suppression_count', 'recent_logs', 'smtp_configured'));
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
        $campaigns = \MNEM\Campaigns::get_list($site_id);

        $this->render_view('campaigns.php', compact('campaigns'));
    }

    public function render_queue()
    {
        global $wpdb;

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $queue_table = $wpdb->prefix . 'mnem_queue';
        $queue_items = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, recipient_email, subject, status, attempts, scheduled_at, processed_at, created_at FROM {$queue_table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $site_id,
                50,
                0
            ),
            ARRAY_A
        );

        $this->render_view('queue.php', compact('queue_items'));
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
}
