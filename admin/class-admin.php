<?php

namespace MNEM\Admin;

defined('ABSPATH') || exit;

class Admin
{
    public function init()
    {
        add_action('admin_init', array($this, 'handle_smtp_save'));
        add_action('admin_init', array($this, 'handle_suppression_action'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

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
        }
    }

    private function current_user_can_manage_network()
    {
        return function_exists('current_user_can') && current_user_can('manage_network');
    }

    private function verify_nonce($nonce, $action)
    {
        return function_exists('wp_verify_nonce') ? (bool) wp_verify_nonce($nonce, $action) : false;
    }

    private function redirect_with_notice($page, $notice)
    {
        $url = function_exists('network_admin_url') ? network_admin_url('admin.php?page=' . $page . '&mnem_notice=' . rawurlencode($notice)) : 'admin.php?page=' . $page . '&mnem_notice=' . rawurlencode($notice);
        wp_safe_redirect($url);
        exit;
    }
}
