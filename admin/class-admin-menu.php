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
            'Network Email Manager',
            'manage_network_options',
            'mnem-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-email-alt',
            58
        );

        add_submenu_page('mnem-dashboard', 'Dashboard', 'Dashboard', 'manage_network_options', 'mnem-dashboard', array($this, 'render_dashboard'));
        add_submenu_page('mnem-dashboard', 'Settings', 'Settings', 'manage_network_options', 'mnem-settings', array($this, 'render_settings'));
        add_submenu_page('mnem-dashboard', 'Campaigns', 'Campaigns', 'manage_network_options', 'mnem-campaigns', array($this, 'render_campaigns'));
        add_submenu_page('mnem-dashboard', 'Subscriber Lists', 'Subscriber Lists', 'manage_network_options', 'mnem-subscriber-lists', array($this, 'render_subscriber_lists'));
        add_submenu_page('mnem-dashboard', 'User Event Rules', 'User Event Rules', 'manage_network_options', 'mnem-user-event-rules', array($this, 'render_user_event_rules'));
        add_submenu_page('mnem-dashboard', 'Email Status Logs', 'Email Status Logs', 'manage_network_options', 'mnem-queue', array($this, 'render_queue'));
        add_submenu_page('mnem-dashboard', 'Suppression', 'Suppression', 'manage_network_options', 'mnem-suppression', array($this, 'render_suppression'));
        add_submenu_page('mnem-dashboard', 'Logs', 'Logs', 'manage_network_options', 'mnem-logs', array($this, 'render_logs'));
        add_submenu_page('settings.php', 'Email Templates', 'Email Templates', 'manage_network_options', 'mnem-email-templates', array($this, 'render_email_templates'));
    }

    public function render_dashboard()
    {
        global $wpdb;

        $logs_table = $wpdb->base_prefix . 'mnem_logs';
        $plugin_version = defined('MNEM_VERSION') ? MNEM_VERSION : '1.0.0';
        $queue_stats = \MNEM\Queue::get_stats(null);
        $suppression_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$wpdb->base_prefix}mnem_suppression WHERE site_id >= %d", 0));
        $recent_logs = (array) $wpdb->get_results($wpdb->prepare("SELECT blog_id, level, message, created_at FROM {$logs_table} ORDER BY created_at DESC LIMIT %d", 10), ARRAY_A);
        $smtp_configured = \MNEM\SmtpSettings::is_active_provider_configured();
        $campaigns = (array) $wpdb->get_results($wpdb->prepare("SELECT id, site_id, name, subject, status, total_recipients, sent_count, failed_count, last_send_attempt_at FROM {$wpdb->base_prefix}mnem_campaigns ORDER BY created_at DESC LIMIT %d", 10), ARRAY_A);
        $site_breakdown = (array) $wpdb->get_results($wpdb->prepare("SELECT blog_id, status, COUNT(1) AS total FROM {$wpdb->base_prefix}mnem_queue GROUP BY blog_id, status ORDER BY blog_id ASC LIMIT %d", 200), ARRAY_A);
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);
        $campaign_sends_paused = (int) get_site_option('mnem_campaign_sends_paused', 0) === 1;
        $processed = isset($_GET['processed']) ? (int) $_GET['processed'] : 0;
        $retried = isset($_GET['retried']) ? (int) $_GET['retried'] : 0;
        $cron_status = \MNEM\Cron::get_status();
        $failed_rule_triggers = (int) get_site_option(\MNEM\UserEventsCampaign::OPTION_FAILED_RULE_TRIGGERS, 0);
        $sender_email = \MNEM\SmtpSettings::get_sender_email();
        $smtp_warnings = array();
        if ($sender_email === '') {
            $smtp_warnings[] = 'Sender email is not configured. Please configure it in <a href="' . esc_url(network_admin_url('admin.php?page=mnem-settings&tab=sender')) . '">Settings > Sender Settings</a>.';
        }

        $smtp_provider_type = \MNEM\SmtpSettings::get('provider_type', 'smtp');
        $smtp_provider      = \MNEM\ProviderManager::get_provider((string) $smtp_provider_type);
        if ($smtp_provider === null) {
            $smtp_status = 'Unknown';
        } elseif (\MNEM\SmtpSettings::is_active_provider_configured()) {
            $smtp_status_cache_key = 'mnem_smtp_conn_status_' . md5((string) $smtp_provider_type);
            $smtp_status_cached    = get_site_transient($smtp_status_cache_key);
            if ($smtp_status_cached !== false) {
                $smtp_status = (string) $smtp_status_cached;
            } else {
                $smtp_test   = $smtp_provider->test_connection();
                $smtp_status = !empty($smtp_test['success']) ? 'Connected' : 'Not Connected: ' . (isset($smtp_test['message']) ? $smtp_test['message'] : 'Unknown error');
                set_site_transient($smtp_status_cache_key, $smtp_status, 5 * MINUTE_IN_SECONDS);
            }
        } else {
            $smtp_status = 'Not Configured';
        }

        $this->render_view('dashboard.php', compact('plugin_version', 'queue_stats', 'suppression_count', 'recent_logs', 'smtp_configured', 'campaigns', 'site_breakdown', 'notice', 'notice_message', 'notice_class', 'campaign_sends_paused', 'processed', 'retried', 'cron_status', 'failed_rule_triggers', 'smtp_status', 'smtp_warnings'));
    }

    public function render_settings()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'smtp';
        $allowed_tabs = array('smtp', 'sender', 'header-footer');
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = 'smtp';
        }

        $settings = \MNEM\SmtpSettings::get_all();
        $cron_status = \MNEM\Cron::get_status();
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);

        $this->render_view('settings.php', compact('active_tab', 'settings', 'cron_status', 'notice', 'notice_message', 'notice_class'));
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

        $subscriber_lists = \MNEM\SubscriberLists::get_all();
        $templates = \MNEM\EmailTemplates::get_all_templates();
        $this->render_view('campaigns.php', compact('campaigns', 'notice', 'notice_message', 'notice_class', 'edit_campaign', 'subscriber_lists', 'templates'));
    }

    public function render_subscriber_lists()
    {
        $lists = \MNEM\SubscriberLists::get_all();
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);
        $active_list_id = isset($_GET['list_id']) ? (int) $_GET['list_id'] : 0;
        $active_list = $active_list_id > 0 ? \MNEM\SubscriberLists::get($active_list_id) : null;
        $subscribers = $active_list_id > 0 ? \MNEM\SubscriberLists::get_subscribers($active_list_id, 1000, 0) : array();
        $unsubscribed = $active_list_id > 0 ? \MNEM\SubscriberLists::get_unsubscribed($active_list_id, 1000, 0) : array();
        $alert_message = isset($_GET['mnem_alert']) ? sanitize_text_field(wp_unslash($_GET['mnem_alert'])) : '';

        $this->render_view('subscriber-lists.php', compact('lists', 'notice', 'notice_message', 'notice_class', 'active_list', 'active_list_id', 'subscribers', 'unsubscribed', 'alert_message'));
    }

    public function render_email_templates()
    {
        $templates = \MNEM\EmailTemplates::get_all_templates();
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);

        $this->render_view('email-templates.php', compact('templates', 'notice', 'notice_message', 'notice_class'));
    }

    public function render_queue()
    {
        global $wpdb;

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $queue_items = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, blog_id, campaign_id, recipient_email, subject, status, attempts, scheduled_at, sent_at, opened, clicked, opens_count, clicks_count, created_at, provider_message_id, provider_metadata FROM {$queue_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                50,
                0
            ),
            ARRAY_A
        );
        $queue_stats = \MNEM\Queue::get_stats(null);
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

        $logs_table = $wpdb->base_prefix . 'mnem_logs';
        $logs = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT blog_id, level, message, created_at FROM {$logs_table} ORDER BY created_at DESC LIMIT %d",
                50
            ),
            ARRAY_A
        );

        $this->render_view('logs.php', compact('logs'));
    }

    public function render_user_event_rules()
    {
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $rules = \MNEM\UserEventsCampaign::get_rules();
        $campaigns = \MNEM\Campaigns::get_list($site_id, '', 100, 0);
        $eligible_campaigns = array_values(array_filter($campaigns, static function ($campaign) {
            return isset($campaign['status']) && in_array($campaign['status'], array('draft', 'scheduled'), true);
        }));
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);
        $dry_run_matches = isset($_GET['dry_run_matches']) ? (int) $_GET['dry_run_matches'] : 0;
        $preview_campaign_id = isset($_GET['preview_campaign']) ? (int) $_GET['preview_campaign'] : 0;
        $preview_campaign = $preview_campaign_id > 0 ? \MNEM\Campaigns::get($preview_campaign_id) : null;
        $edit_rule_id = isset($_GET['edit_rule']) ? sanitize_text_field(wp_unslash($_GET['edit_rule'])) : '';
        $edit_rule = null;
        foreach ($rules as $rule) {
            if (isset($rule['id']) && (string) $rule['id'] === $edit_rule_id) {
                $edit_rule = $rule;
                break;
            }
        }

        $this->render_view('user-event-rules.php', compact('rules', 'eligible_campaigns', 'notice', 'notice_message', 'notice_class', 'dry_run_matches', 'preview_campaign', 'edit_rule'));
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
        $count = isset($_GET['count']) ? (int) $_GET['count'] : 0;
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        if (!in_array($status, \MNEM\Queue::DELETABLE_STATUSES, true)) {
            $status = 'queue';
        }
        $messages = array(
            'campaign_created' => 'Campaign created successfully.',
            'campaign_updated' => 'Campaign updated successfully.',
            'campaign_sent' => 'Campaign send has been queued.',
            'campaign_deleted' => 'Campaign deleted successfully.',
            'campaign_cancelled' => 'Campaign cancelled successfully.',
            'campaign_paused' => 'Campaign sending is now paused.',
            'campaign_resumed' => 'Campaign sending has resumed.',
            'queue_processed' => 'Queue processed successfully.',
            'queue_retried' => 'Failed queue items were rescheduled.',
            'queue_item_deleted' => 'Queue item deleted.',
            'queue_items_deleted' => $count === 1 ? '1 queue item deleted.' : sprintf('%d queue items deleted.', $count),
            'queue_deleted_by_status' => sprintf('%d %s item%s deleted.', $count, $status, $count === 1 ? '' : 's'),
            'queue_delete_failed' => 'Failed to delete queue item.',
            'queue_nothing_selected' => 'No items selected for deletion.',
            'campaign_nonce_failed' => 'Campaign security check failed.',
            'queue_nonce_failed' => 'Queue security check failed.',
            'campaign_send_failed' => 'Campaign send failed.',
            'campaign_save_failed' => 'Campaign could not be saved.',
            'campaign_delete_failed' => 'Campaign could not be deleted.',
            'smtp_saved' => 'SMTP settings saved.',
            'smtp_failed' => 'SMTP settings could not be saved.',
            'cron_settings_saved' => 'Cron settings saved.',
            'sender_settings_saved' => 'Sender settings saved.',
            'sender_settings_failed' => 'Sender settings could not be saved.',
            'header_footer_saved' => 'Global header/footer settings saved.',
            'header_footer_failed' => 'Global header/footer settings could not be saved.',
            'subscriber_list_saved' => 'Subscriber list saved.',
            'subscriber_list_deleted' => 'Subscriber list deleted.',
            'subscriber_added' => 'Subscriber added successfully.',
            'subscriber_removed' => 'Subscriber removed successfully.',
            'subscriber_restored' => 'Subscriber restored successfully.',
            'subscriber_csv_imported' => 'Subscriber CSV import processed.',
            'subscriber_operation_failed' => 'Subscriber list operation failed.',
            'email_template_saved' => 'Email template saved.',
            'email_template_deleted' => 'Email template deleted.',
            'email_template_reset' => 'Template reset to default.',
            'email_template_failed' => 'Template action failed.',
            'rule_saved' => 'User event rule saved.',
            'rule_deleted' => 'User event rule deleted.',
            'rule_save_failed' => 'User event rule is invalid.',
            'rule_nonce_failed' => 'User event rule security check failed.',
            'diagnostics_nonce_failed' => 'SMTP diagnostics security check failed.',
            'smtp_test_sent' => 'SMTP test email was sent.',
            'smtp_test_failed' => 'SMTP test email failed.',
        );

        return isset($messages[$notice]) ? $messages[$notice] : '';
    }

    private function get_notice_class($notice)
    {
        if ($notice === 'queue_nothing_selected') {
            return 'notice notice-warning';
        }

        if (in_array($notice, array('campaign_nonce_failed', 'queue_nonce_failed', 'queue_delete_failed', 'campaign_send_failed', 'campaign_save_failed', 'campaign_delete_failed', 'diagnostics_nonce_failed', 'rule_save_failed', 'rule_nonce_failed', 'smtp_test_failed', 'sender_settings_failed', 'header_footer_failed', 'subscriber_operation_failed', 'email_template_failed'), true)) {
            return 'notice notice-error';
        }

        return $notice !== '' ? 'notice notice-success' : '';
    }
}
