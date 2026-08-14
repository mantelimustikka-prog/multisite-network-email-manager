<?php

namespace MNEM\Admin;

defined('ABSPATH') || exit;

class NetworkAdmin
{
    public function init()
    {
        add_action('admin_init', array($this, 'handle_smtp_save'));
        add_action('admin_init', array($this, 'handle_save_sender_settings'));
        add_action('admin_init', array($this, 'handle_save_header_footer_settings'));
        add_action('admin_init', array($this, 'handle_suppression_action'));
        add_action('admin_init', array($this, 'handle_campaign_action'));
        add_action('admin_init', array($this, 'handle_subscriber_list_action'));
        add_action('admin_init', array($this, 'handle_email_template_action'));
        add_action('admin_init', array($this, 'handle_queue_action'));
        add_action('admin_init', array($this, 'handle_queue_item_delete_action'));
        add_action('admin_init', array($this, 'handle_user_event_rule_action'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_ajax_mnem_dashboard_stats', array($this, 'ajax_dashboard_stats'));
        add_action('wp_ajax_mnem_process_queue', array($this, 'ajax_process_queue'));
        add_action('wp_ajax_mnem_process_queue_now', array($this, 'ajax_process_queue_now'));
        add_action('wp_ajax_mnem_retry_failed_queue', array($this, 'ajax_retry_failed_queue'));
        add_action('wp_ajax_mnem_toggle_campaign_pause', array($this, 'ajax_toggle_campaign_pause'));
        add_action('wp_ajax_mnem_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_mnem_send_test_email', array($this, 'ajax_send_test_email'));
        add_action('wp_ajax_mnem_get_email_preview', array($this, 'ajax_get_email_preview'));

        $menu = new AdminMenu();
        $menu->init();
    }

    public function handle_smtp_save()
    {
        if (!isset($_POST['mnem_action']) || !in_array($_POST['mnem_action'], array('save_smtp_settings', 'send_test_email', 'save_cron_settings', 'save_email_tracking_settings'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        $action = isset($_POST['mnem_action']) ? sanitize_text_field(wp_unslash($_POST['mnem_action'])) : '';
        $nonce_action = $action === 'save_email_tracking_settings' ? 'mnem_email_tracking_settings' : 'mnem_smtp_settings';
        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', $nonce_action)) {
            $this->redirect_with_notice('mnem-settings', 'smtp_nonce_failed', array('tab' => $action === 'save_email_tracking_settings' ? 'email-tracking' : 'smtp'));
            return;
        }

        if ($_POST['mnem_action'] === 'send_test_email') {
            $email = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';
            $result = \MNEM\SmtpDiagnostics::send_test_email($email);
            $this->redirect_with_notice('mnem-settings', $result['success'] ? 'smtp_test_sent' : 'smtp_test_failed', array('tab' => 'smtp'));
            return;
        }

        if ($_POST['mnem_action'] === 'save_cron_settings') {
            $interval = isset($_POST['cron_interval']) ? sanitize_text_field(wp_unslash($_POST['cron_interval'])) : \MNEM\Cron::DEFAULT_INTERVAL;
            \MNEM\Cron::set_interval($interval);
            $this->redirect_with_notice('mnem-settings', 'cron_settings_saved', array('tab' => 'smtp'));
            return;
        }

        if ($_POST['mnem_action'] === 'save_email_tracking_settings') {
            $enabled = isset($_POST['keep_email_previews']) && (int) $_POST['keep_email_previews'] === 1;
            $retention_days = isset($_POST['email_preview_retention_days']) ? max(0, (int) $_POST['email_preview_retention_days']) : 30;
            \MNEM\EmailTracking::save_settings($enabled, $retention_days);
            $this->redirect_with_notice('mnem-settings', 'email_tracking_saved', array('tab' => 'email-tracking'));
            return;
        }

        $data = array(
            'host'              => isset($_POST['host']) ? wp_unslash($_POST['host']) : '',
            'port'              => isset($_POST['port']) ? wp_unslash($_POST['port']) : '',
            'encryption'        => isset($_POST['encryption']) ? wp_unslash($_POST['encryption']) : 'tls',
            'username'          => isset($_POST['username']) ? wp_unslash($_POST['username']) : '',
            'password'          => isset($_POST['password']) ? wp_unslash($_POST['password']) : '',
            'from_email'        => isset($_POST['from_email']) ? wp_unslash($_POST['from_email']) : '',
            'from_name'         => isset($_POST['from_name']) ? wp_unslash($_POST['from_name']) : '',
            'provider_type'     => isset($_POST['provider_type']) ? wp_unslash($_POST['provider_type']) : 'smtp',
            'provider_config'   => isset($_POST['provider_config']) && is_array($_POST['provider_config']) ? wp_unslash($_POST['provider_config']) : array(),
            'fallback_provider' => isset($_POST['fallback_provider']) ? wp_unslash($_POST['fallback_provider']) : '',
            'fallback_enabled'  => isset($_POST['fallback_enabled']) ? 1 : 0,
        );

        $saved = \MNEM\SmtpSettings::save($data);
        $this->redirect_with_notice('mnem-settings', $saved ? 'smtp_saved' : 'smtp_failed', array('tab' => 'smtp'));
    }

    public function handle_save_sender_settings()
    {
        if (!isset($_POST['mnem_action']) || $_POST['mnem_action'] !== 'save_sender_settings') {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$this->verify_nonce($nonce, 'mnem_sender_settings')) {
            $this->redirect_with_notice('mnem-settings', 'sender_settings_failed');
            return;
        }

        $sender_name  = isset($_POST['sender_name'])  ? sanitize_text_field(wp_unslash($_POST['sender_name']))  : '';
        $sender_email = isset($_POST['sender_email']) ? sanitize_email(wp_unslash($_POST['sender_email'])) : '';

        update_site_option('mnem_sender_name', $sender_name);
        update_site_option('mnem_sender_email', $sender_email);

        \MNEM\Logger::info('Sender settings updated');

        $this->redirect_with_notice('mnem-settings', 'sender_settings_saved', array('tab' => 'sender'));
    }

    public function handle_save_header_footer_settings()
    {
        if (!isset($_POST['mnem_action']) || $_POST['mnem_action'] !== 'save_header_footer_settings') {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$this->verify_nonce($nonce, 'mnem_header_footer_settings')) {
            $this->redirect_with_notice('mnem-settings', 'header_footer_failed');
            return;
        }

        $force = isset($_POST['force_global_header_footer']) ? 1 : 0;

        $header = isset($_POST['global_header']) ? wp_unslash($_POST['global_header']) : '';
        $footer = isset($_POST['global_footer']) ? wp_unslash($_POST['global_footer']) : '';

        if (function_exists('wp_kses_post')) {
            $header = wp_kses_post($header);
            $footer = wp_kses_post($footer);
        }

        update_site_option('mnem_force_global_header_footer', $force);
        update_site_option('mnem_global_header', $header);
        update_site_option('mnem_global_footer', $footer);

        \MNEM\Logger::info('Global header/footer settings updated');

        $this->redirect_with_notice('mnem-settings', 'header_footer_saved', array('tab' => 'header-footer'));
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
        if (!isset($_POST['mnem_action']) || !in_array($_POST['mnem_action'], array('save_campaign', 'send_campaign', 'delete_campaign', 'cancel_campaign'), true)) {
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
            return;
        }

        if ($_POST['mnem_action'] === 'cancel_campaign') {
            $result = \MNEM\Campaigns::cancel_campaign($campaign_id);
            $this->redirect_with_notice($page, $result ? 'campaign_cancelled' : 'campaign_send_failed');
            return;
        }

        if ($_POST['mnem_action'] === 'delete_campaign') {
            $result = \MNEM\Campaigns::delete($campaign_id);
            $this->redirect_with_notice($page, $result ? 'campaign_deleted' : 'campaign_delete_failed');
            return;
        }

        if ($campaign_id > 0) {
            $campaign = \MNEM\Campaigns::get($campaign_id);
            if (is_array($campaign) && isset($campaign['status']) && $campaign['status'] === 'cancelled') {
                $this->redirect_with_notice($page, 'campaign_save_failed');
            }
        }

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $data = array(
            'name' => isset($_POST['name']) ? wp_unslash($_POST['name']) : '',
            'subject' => isset($_POST['subject']) ? wp_unslash($_POST['subject']) : '',
            'body' => isset($_POST['body']) ? wp_kses_post(wp_unslash($_POST['body'])) : '',
            'status' => isset($_POST['status']) ? wp_unslash($_POST['status']) : 'draft',
            'scheduled_at' => isset($_POST['scheduled_at']) ? wp_unslash($_POST['scheduled_at']) : '',
            'recipient_scope' => isset($_POST['recipient_scope']) ? wp_unslash($_POST['recipient_scope']) : 'all_users',
            'recipient_list' => isset($_POST['recipient_list']) ? wp_unslash($_POST['recipient_list']) : '',
            'template_id' => isset($_POST['template_id']) ? sanitize_text_field(wp_unslash($_POST['template_id'])) : '',
            'target_lists' => isset($_POST['target_lists']) ? array_map('intval', (array) wp_unslash($_POST['target_lists'])) : array(),
        );

        if ($campaign_id > 0) {
            $result = \MNEM\Campaigns::update($campaign_id, $data);
            $this->redirect_with_notice($page, $result ? 'campaign_updated' : 'campaign_save_failed');
        }

        $result = \MNEM\Campaigns::create($site_id, $data['name'], $data['subject'], $data['body'], $data);
        $this->redirect_with_notice($page, $result ? 'campaign_created' : 'campaign_save_failed');
    }

    public function handle_subscriber_list_action()
    {
        if (!isset($_POST['mnem_action']) || strpos((string) $_POST['mnem_action'], 'subscriber_') !== 0) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_subscriber_lists')) {
            $this->redirect_with_notice('mnem-subscriber-lists', 'subscriber_operation_failed');
        }

        $action = sanitize_text_field(wp_unslash($_POST['mnem_action']));
        $list_id = isset($_POST['list_id']) ? (int) $_POST['list_id'] : 0;
        $redirect_args = array();
        if ($list_id > 0) {
            $redirect_args['list_id'] = $list_id;
        }

        if ($action === 'subscriber_save_list') {
            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
            $result = $list_id > 0
                ? \MNEM\SubscriberLists::update($list_id, $name, $description)
                : \MNEM\SubscriberLists::create($name, $description);
            if (is_int($result)) {
                $redirect_args['list_id'] = $result;
            }
            $this->redirect_with_notice('mnem-subscriber-lists', $result ? 'subscriber_list_saved' : 'subscriber_operation_failed', $redirect_args);
        }

        if ($action === 'subscriber_delete_list') {
            $result = \MNEM\SubscriberLists::delete($list_id);
            $this->redirect_with_notice('mnem-subscriber-lists', $result ? 'subscriber_list_deleted' : 'subscriber_operation_failed');
        }

        if ($action === 'subscriber_add_user') {
            $identifier = isset($_POST['user_identifier']) ? trim((string) wp_unslash($_POST['user_identifier'])) : '';
            $user_id = ctype_digit($identifier) ? (int) $identifier : 0;
            if ($user_id <= 0 && function_exists('get_users')) {
                $users = get_users(array(
                    'search' => $identifier,
                    'search_columns' => array('user_login'),
                    'number' => 1,
                    'fields' => array('ID'),
                ));
                $user = isset($users[0]) ? $users[0] : null;
                $user_id = is_object($user) && isset($user->ID) ? (int) $user->ID : (is_array($user) && isset($user['ID']) ? (int) $user['ID'] : 0);
            }
            if ($user_id <= 0) {
                $this->redirect_with_notice('mnem-subscriber-lists', 'subscriber_operation_failed', $redirect_args);
            }

            $result = \MNEM\SubscriberLists::add_subscriber($list_id, $user_id);
            if ($result instanceof \WP_Error) {
                $redirect_args['mnem_alert'] = $result->get_error_message();
                $this->redirect_with_notice('mnem-subscriber-lists', 'subscriber_operation_failed', $redirect_args);
            }
            $this->redirect_with_notice('mnem-subscriber-lists', $result ? 'subscriber_added' : 'subscriber_operation_failed', $redirect_args);
        }

        if ($action === 'subscriber_remove_user') {
            $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $result = \MNEM\SubscriberLists::remove_subscriber($list_id, $user_id);
            $this->redirect_with_notice('mnem-subscriber-lists', $result ? 'subscriber_removed' : 'subscriber_operation_failed', $redirect_args);
        }

        if ($action === 'subscriber_restore_user') {
            $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $result = \MNEM\SubscriberLists::resubscribe_user($list_id, $user_id);
            $this->redirect_with_notice('mnem-subscriber-lists', $result ? 'subscriber_restored' : 'subscriber_operation_failed', $redirect_args);
        }

        if ($action === 'subscriber_import_csv') {
            $csv_content = isset($_POST['csv_content']) ? (string) wp_unslash($_POST['csv_content']) : '';
            if ($csv_content === '' && isset($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                $csv_content = (string) file_get_contents($_FILES['csv_file']['tmp_name']);
            }
            $result = \MNEM\SubscriberLists::import_from_csv($list_id, $csv_content);
            \MNEM\Logger::info('Subscriber CSV imported.', array('list_id' => $list_id, 'result' => $result));
            $this->redirect_with_notice('mnem-subscriber-lists', 'subscriber_csv_imported', $redirect_args);
        }
    }

    public function handle_email_template_action()
    {
        if (!isset($_POST['mnem_action']) || strpos((string) $_POST['mnem_action'], 'template_') !== 0) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_email_templates')) {
            $this->redirect_with_notice('mnem-email-templates', 'email_template_failed');
        }

        $action = sanitize_text_field(wp_unslash($_POST['mnem_action']));
        $template_id = isset($_POST['template_id']) ? sanitize_text_field(wp_unslash($_POST['template_id'])) : '';

        if ($action === 'template_save') {
            $name = isset($_POST['template_name']) ? sanitize_text_field(wp_unslash($_POST['template_name'])) : '';
            $subject = isset($_POST['template_subject']) ? sanitize_text_field(wp_unslash($_POST['template_subject'])) : '';
            $body = isset($_POST['template_body']) ? wp_kses_post(wp_unslash($_POST['template_body'])) : '';
            $result = \MNEM\EmailTemplates::save_template($template_id, $name, $subject, $body);
            $this->redirect_with_notice('mnem-email-templates', $result ? 'email_template_saved' : 'email_template_failed');
        }

        if ($action === 'template_delete') {
            $result = \MNEM\EmailTemplates::delete_custom_template($template_id);
            $this->redirect_with_notice('mnem-email-templates', $result ? 'email_template_deleted' : 'email_template_failed');
        }

        if ($action === 'template_reset') {
            $result = \MNEM\EmailTemplates::reset_to_default($template_id);
            $this->redirect_with_notice('mnem-email-templates', $result ? 'email_template_reset' : 'email_template_failed');
        }
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
            return;
        }

        $page = $this->get_queue_redirect_page();
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;

        if ($_POST['mnem_action'] === 'process_queue_now') {
            $processed = \MNEM\Cron::process_queue_batch();
            \MNEM\Logger::info('Manual queue processing triggered.', array('site_id' => $site_id, 'processed' => $processed));
            $this->redirect_with_notice($page, 'queue_processed', array('processed' => $processed));
            return;
        }

        if ($_POST['mnem_action'] === 'retry_failed_queue') {
            $retried = \MNEM\Queue::retry_failed($site_id);
            $this->redirect_with_notice($page, 'queue_retried', array('retried' => $retried));
            return;
        }

        $paused = (int) get_site_option('mnem_campaign_sends_paused', 0) === 1 ? 0 : 1;
        update_site_option('mnem_campaign_sends_paused', $paused);
        \MNEM\Logger::info('Campaign sending pause state changed.', array('site_id' => $site_id, 'paused' => $paused));
        $this->redirect_with_notice($page, $paused === 1 ? 'campaign_paused' : 'campaign_resumed');
    }

    public function handle_queue_item_delete_action()
    {
        $action = isset($_POST['mnem_action']) ? sanitize_text_field(wp_unslash($_POST['mnem_action'])) : '';
        $bulk_action = isset($_POST['bulk_action']) ? sanitize_text_field(wp_unslash($_POST['bulk_action'])) : '';
        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

        if ($action === '' && $bulk_action !== '') {
            if ($bulk_action === 'delete_pending') {
                $action = 'delete_queue_by_status';
                $status = 'pending';
            } elseif ($bulk_action === 'delete_failed') {
                $action = 'delete_queue_by_status';
                $status = 'failed';
            } elseif ($bulk_action === 'delete_selected') {
                $action = 'delete_queue_items';
            }
        }

        if ($action === 'delete_pending') {
            $action = 'delete_queue_by_status';
            $status = 'pending';
        } elseif ($action === 'delete_failed') {
            $action = 'delete_queue_by_status';
            $status = 'failed';
        }

        if (!in_array($action, array('delete_queue_item', 'delete_queue_items', 'delete_queue_by_status'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        $page = $this->get_queue_redirect_page();
        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_queue_item_delete')) {
            \MNEM\Logger::warning('Queue deletion request failed nonce verification.', array('action' => $action));
            $this->redirect_with_notice($page, 'queue_nonce_failed');
            return;
        }

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;

        if ($action === 'delete_queue_item') {
            $queue_id = isset($_POST['queue_id']) ? (int) $_POST['queue_id'] : 0;
            $deleted = $queue_id > 0 ? \MNEM\Queue::delete_item($queue_id) : false;
            \MNEM\Logger::info('Queue single delete requested.', array('site_id' => $site_id, 'queue_id' => $queue_id, 'deleted' => (bool) $deleted));
            $this->redirect_with_notice($page, $deleted ? 'queue_item_deleted' : 'queue_delete_failed');
            return;
        }

        if ($action === 'delete_queue_items') {
            $queue_ids = isset($_POST['queue_ids']) ? array_map('intval', (array) wp_unslash($_POST['queue_ids'])) : array();
            if (empty($queue_ids)) {
                $this->redirect_with_notice($page, 'queue_nothing_selected');
                return;
            }

            $deleted = \MNEM\Queue::delete_items($queue_ids);
            \MNEM\Logger::info('Queue bulk delete requested.', array('site_id' => $site_id, 'queue_ids' => $queue_ids, 'deleted_count' => $deleted));
            $this->redirect_with_notice($page, $deleted > 0 ? 'queue_items_deleted' : 'queue_delete_failed', array('count' => $deleted));
            return;
        }

        if (!in_array($status, \MNEM\Queue::DELETABLE_STATUSES, true)) {
            \MNEM\Logger::warning('Queue status delete rejected because status is invalid.', array('site_id' => $site_id, 'status' => $status));
            $this->redirect_with_notice($page, 'queue_delete_failed');
            return;
        }

        $deleted = \MNEM\Queue::delete_by_status(0, $status);
        \MNEM\Logger::info('Queue delete by status requested.', array('site_id' => 0, 'requested_site_id' => $site_id, 'status' => $status, 'deleted_count' => $deleted));
        $this->redirect_with_notice($page, $deleted > 0 ? 'queue_deleted_by_status' : 'queue_delete_failed', array('count' => $deleted, 'status' => $status));
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

        wp_send_json_success(
            array(
                'queue' => \MNEM\Queue::get_stats(null),
                'campaigns' => \MNEM\Campaigns::get_list(function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1, '', 10, 0),
                'paused' => (int) get_site_option('mnem_campaign_sends_paused', 0) === 1,
                'cron' => \MNEM\Cron::get_status(),
            )
        );
    }

    public function ajax_process_queue()
    {
        $this->ensure_ajax_permissions();
        wp_send_json_success(array('processed' => \MNEM\Cron::process_queue_batch()));
    }

    public function ajax_process_queue_now()
    {
        $this->ensure_ajax_permissions();
        wp_send_json_success(array('processed' => \MNEM\Cron::process_queue_batch()));
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

    public function ajax_test_connection()
    {
        $this->ensure_ajax_permissions();
        wp_send_json_success(\MNEM\SmtpDiagnostics::test_connection());
    }

    public function ajax_send_test_email()
    {
        $this->ensure_ajax_permissions();
        $to_email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $result = \MNEM\SmtpDiagnostics::send_test_email($to_email);
        if (!empty($result['success'])) {
            wp_send_json_success($result);
            return;
        }
        wp_send_json_error($result, 400);
    }

    public function ajax_get_email_preview()
    {
        $this->ensure_ajax_permissions();
        $email_id = isset($_POST['email_id']) ? (int) $_POST['email_id'] : 0;
        if ($email_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid email ID.'), 400);
            return;
        }

        $email = \MNEM\EmailTracking::get_email($email_id);
        if (empty($email)) {
            wp_send_json_error(array('message' => 'Email preview not found.'), 404);
            return;
        }

        wp_send_json_success($email);
    }

    public function handle_user_event_rule_action()
    {
        if (!isset($_POST['mnem_action']) || !in_array($_POST['mnem_action'], array('save_user_event_rule', 'delete_user_event_rule'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_user_event_rules')) {
            $this->redirect_with_notice('mnem-user-event-rules', 'rule_nonce_failed');
        }

        if ($_POST['mnem_action'] === 'delete_user_event_rule') {
            $rule_id = isset($_POST['rule_id']) ? sanitize_text_field(wp_unslash($_POST['rule_id'])) : '';
            \MNEM\UserEventsCampaign::delete_rule($rule_id);
            $this->redirect_with_notice('mnem-user-event-rules', 'rule_deleted');
        }

        $rule = array(
            'id' => isset($_POST['rule_id']) ? sanitize_text_field(wp_unslash($_POST['rule_id'])) : '',
            'event_type' => isset($_POST['event_type']) ? sanitize_text_field(wp_unslash($_POST['event_type'])) : '',
            'campaign_id' => isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0,
            'enabled' => isset($_POST['enabled']) && (int) $_POST['enabled'] === 1,
            'conditions' => array(
                'role' => isset($_POST['role']) ? sanitize_text_field(wp_unslash($_POST['role'])) : 'any',
                'site_id' => isset($_POST['site_id']) ? sanitize_text_field(wp_unslash($_POST['site_id'])) : 'any',
            ),
        );

        if (isset($_POST['mnem_dry_run'])) {
            $matches = \MNEM\UserEventsCampaign::dry_run($rule);
            $this->redirect_with_notice('mnem-user-event-rules', 'rule_saved', array('dry_run_matches' => count($matches)));
        }

        $result = \MNEM\UserEventsCampaign::upsert_rule($rule);
        $this->redirect_with_notice('mnem-user-event-rules', $result ? 'rule_saved' : 'rule_save_failed');
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
        $this->exit_after_redirect();
    }

    protected function exit_after_redirect()
    {
        exit;
    }
}
