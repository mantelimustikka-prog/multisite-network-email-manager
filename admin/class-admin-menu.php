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
        add_submenu_page('settings.php', 'Email Templates', 'Email Templates', 'manage_network_options', 'mnem-email-templates', array($this, 'render_email_templates'));
        add_submenu_page('mnem-dashboard', 'Add Bulk Subscribers', '', 'manage_network_options', 'mnem-subscriber-lists-bulk-add', array($this, 'render_subscriber_lists_bulk_add'));
    }

    public function render_subscriber_lists_bulk_add()
    {
        if (!isset($_GET['list_id'])) {
            wp_redirect(network_admin_url('admin.php?page=mnem-subscriber-lists'));
            exit;
        }

        $active_list_id = (int) $_GET['list_id'];
        $active_list = \MNEM\SubscriberLists::get($active_list_id);

        if (!$active_list) {
            wp_redirect(network_admin_url('admin.php?page=mnem-subscriber-lists'));
            exit;
        }

        $all_sites = self::get_all_sites_with_user_count();
        $all_roles = self::get_all_network_roles();
        $batch_sizes = self::get_allowed_network_user_batch_sizes();

        $this->render_view('subscriber-lists-bulk-add.php', compact(
            'active_list',
            'all_sites',
            'all_roles',
            'batch_sizes'
        ));
    }

    public static function get_allowed_network_user_batch_sizes()
    {
        return array(500, 1000, 1500, 2000, 5000, 10000);
    }

    public static function get_network_users_batch($batch_size, $offset)
    {
        $users = array();
        $batch_size = max(0, (int) $batch_size);
        $offset = max(0, (int) $offset);

        if (!class_exists('\WP_User_Query')) {
            return array(
                'users' => $users,
                'total' => 0,
                'loaded' => 0,
                'offset' => $offset,
                'next_offset' => $offset,
                'has_more' => false,
                'batch_size' => $batch_size,
            );
        }

        $query = new \WP_User_Query(array(
            'blog_id' => 0,
            'number' => $batch_size,
            'offset' => $offset,
            'orderby' => 'ID',
            'order' => 'ASC',
            'count_total' => true,
            'fields' => array('ID', 'user_login', 'user_email'),
        ));
        $queried_users = $query->get_results();
        $total = (int) $query->get_total();

        foreach ($queried_users as $user) {
            $user_id = (int) $user->ID;
            $site_ids = array();
            $site_names = array();
            $roles = array();

            if (function_exists('get_blogs_of_user')) {
                $blogs = get_blogs_of_user($user_id);

                foreach ($blogs as $blog) {
                    $site_id = isset($blog->userblog_id) ? (int) $blog->userblog_id : (isset($blog->blog_id) ? (int) $blog->blog_id : 0);
                    if ($site_id <= 0) {
                        continue;
                    }

                    $site_ids[] = $site_id;
                    $site_names[] = !empty($blog->blogname) ? $blog->blogname : sprintf(__('Site %d', 'multisite-network-email-manager'), $site_id);

                    $user_object = new \WP_User($user_id, '', $site_id);
                    if (!empty($user_object->roles[0])) {
                        $roles[] = $user_object->roles[0];
                    }
                }
            }

            $site_ids = array_values(array_unique(array_filter(array_map('intval', $site_ids))));
            $site_names = array_values(array_unique(array_filter($site_names)));
            $roles = array_values(array_unique(array_filter($roles)));

            if (empty($roles)) {
                $roles = array('subscriber');
            }

            $users[] = array(
                'user_id' => $user_id,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'site_id' => !empty($site_ids) ? (int) $site_ids[0] : 0,
                'site_ids' => $site_ids,
                'site_name' => !empty($site_names) ? implode(', ', $site_names) : __('No site membership', 'multisite-network-email-manager'),
                'role' => implode(', ', array_map(static function ($role) {
                    return ucfirst(str_replace(array('-', '_'), ' ', (string) $role));
                }, $roles)),
                'roles' => $roles,
            );
        }

        $loaded = count($users);
        $next_offset = $offset + $loaded;

        return array(
            'users' => $users,
            'total' => $total,
            'loaded' => $loaded,
            'offset' => $offset,
            'next_offset' => $next_offset,
            'has_more' => $next_offset < $total,
            'batch_size' => $batch_size,
        );
    }

    private static function get_all_sites_with_user_count()
    {
        $sites = array();

        if (!function_exists('get_sites')) {
            return $sites;
        }

        $site_objects = get_sites(array('number' => 0));

        foreach ($site_objects as $site) {
            $user_count = count_users('time', $site->blog_id);
            $total = isset($user_count['total_users']) ? (int) $user_count['total_users'] : 0;

            $sites[] = array(
                'id'    => (int) $site->blog_id,
                'name'  => $site->blogname,
                'count' => $total,
            );
        }

        return $sites;
    }

    private static function get_all_network_roles()
    {
        return array(
            'administrator' => __('Administrator'),
            'editor'        => __('Editor'),
            'author'        => __('Author'),
            'contributor'   => __('Contributor'),
            'subscriber'    => __('Subscriber'),
        );
    }

    public function render_dashboard()
    {
        global $wpdb;

        $plugin_version = defined('MNEM_VERSION') ? MNEM_VERSION : '1.0.0';
        $queue_stats = \MNEM\Queue::get_stats(null);
        $suppression_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$wpdb->base_prefix}mnem_suppression WHERE site_id >= %d", 0));
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

        $this->render_view('dashboard.php', compact('plugin_version', 'queue_stats', 'suppression_count', 'smtp_configured', 'campaigns', 'site_breakdown', 'notice', 'notice_message', 'notice_class', 'campaign_sends_paused', 'processed', 'retried', 'cron_status', 'failed_rule_triggers', 'smtp_status', 'smtp_warnings'));
    }

    public function render_settings()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';
        $allowed_tabs = array('smtp', 'sender', 'header-footer', 'status-updates', 'general');
        if (!in_array($active_tab, $allowed_tabs, true)) {
            $active_tab = 'general';
        }

        $settings = \MNEM\SmtpSettings::get_all();
        $cron_status = \MNEM\Cron::get_status();
        $status_update_interval = \MNEM\SmtpSettings::get_status_update_interval();
        $queue_retention_days = \MNEM\SmtpSettings::get_queue_retention_days();
        $campaign_rate_limit_per_minute = \MNEM\SmtpSettings::get_campaign_rate_limit_per_minute();
        $campaign_rate_limit_per_hour = \MNEM\SmtpSettings::get_campaign_rate_limit_per_hour();
        $campaign_rate_limit_per_day = \MNEM\SmtpSettings::get_campaign_rate_limit_per_day();
        $campaign_delay_between_sends = \MNEM\SmtpSettings::get_campaign_delay_between_sends();
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);

        $this->render_view('settings.php', compact('active_tab', 'settings', 'cron_status', 'status_update_interval', 'queue_retention_days', 'campaign_rate_limit_per_minute', 'campaign_rate_limit_per_hour', 'campaign_rate_limit_per_day', 'campaign_delay_between_sends', 'notice', 'notice_message', 'notice_class'));
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

        // Status filter.
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field(wp_unslash($_GET['status_filter'])) : '';

        // Per-page selector with validation.
        $allowed_per_page = array(10, 20, 50, 100, 200, 500);
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
        if (!in_array($per_page, $allowed_per_page, true)) {
            $per_page = 50;
        }

        // Current page and offset.
        $current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Total count of ALL records (for header).
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_all_records = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$queue_table}");

        // Build WHERE clause for status filter.
        if ($status_filter !== '') {
            $where_sql = $wpdb->prepare('WHERE status = %s', $status_filter);
            $total_filtered = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} WHERE status = %s", $status_filter));
        } else {
            $where_sql = '';
            $total_filtered = $total_all_records;
        }

        // Fetch page of records.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $queue_items = (array) $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT id, blog_id, campaign_id, recipient_email, subject, status, attempts, scheduled_at, sent_at, opened, clicked, opens_count, clicks_count, created_at, provider_message_id, provider_metadata FROM {$queue_table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        // All unique statuses for the filter dropdown.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $all_statuses = (array) $wpdb->get_col("SELECT DISTINCT status FROM {$queue_table} ORDER BY status ASC");

        $total_pages = $per_page > 0 ? (int) ceil($total_filtered / $per_page) : 1;

        $queue_stats = \MNEM\Queue::get_stats(null);
        $queue_summary = \MNEM\StatusSummary::get_summary(null);
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);

        $this->render_view('queue.php', compact(
            'queue_items', 'queue_stats', 'queue_summary',
            'notice', 'notice_message', 'notice_class',
            'total_all_records', 'total_filtered', 'total_pages',
            'current_page', 'per_page', 'status_filter', 'all_statuses'
        ));
    }

    public function render_suppression()
    {
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $suppression_entries = \MNEM\Suppression::get_list($site_id, 100, 0);
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';

        $this->render_view('suppression.php', compact('suppression_entries', 'site_id', 'notice'));
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
            'status_interval_saved' => 'Status update interval saved. Cron job has been rescheduled.',
            'status_interval_failed' => 'Failed to save status update interval.',
            'general_settings_saved' => 'General settings saved successfully.',
            'general_settings_failed' => 'Failed to save general settings.',
        );

        return isset($messages[$notice]) ? $messages[$notice] : '';
    }

    private function get_notice_class($notice)
    {
        if ($notice === 'queue_nothing_selected') {
            return 'notice notice-warning';
        }

        if (in_array($notice, array('campaign_nonce_failed', 'queue_nonce_failed', 'queue_delete_failed', 'campaign_send_failed', 'campaign_save_failed', 'campaign_delete_failed', 'diagnostics_nonce_failed', 'rule_save_failed', 'rule_nonce_failed', 'smtp_test_failed', 'sender_settings_failed', 'header_footer_failed', 'subscriber_operation_failed', 'email_template_failed', 'status_interval_failed', 'general_settings_failed'), true)) {
            return 'notice notice-error';
        }

        return $notice !== '' ? 'notice notice-success' : '';
    }
}
