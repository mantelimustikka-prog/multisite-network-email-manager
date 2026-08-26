<?php

namespace MNEM\Admin;

defined('ABSPATH') || exit;

class NetworkAdmin
{
    private const QUILL_VERSION = '2.0.3';

    public function init()
    {
        add_action('admin_init', array($this, 'handle_smtp_save'));
        add_action('admin_init', array($this, 'handle_save_sender_settings'));
        add_action('admin_init', array($this, 'handle_save_header_footer_settings'));
        add_action('admin_init', array($this, 'handle_save_status_interval_settings'));
        add_action('admin_init', array($this, 'handle_save_general_settings'));
        add_action('admin_init', array($this, 'handle_sms_settings_save'));
        add_action('admin_init', array($this, 'handle_sms_data_integrity_action'));
        add_action('admin_init', array($this, 'handle_suppression_action'));
        add_action('admin_init', array($this, 'handle_campaign_action'));
        add_action('admin_init', array($this, 'handle_subscriber_list_action'));
        add_action('admin_init', array($this, 'handle_sms_subscriber_list_action'));
        add_action('admin_init', array($this, 'handle_invalid_phone_action'));
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
        add_action('wp_ajax_mnem_test_provider_connection', array($this, 'ajax_test_provider_connection'));
        add_action('wp_ajax_mnem_send_test_email', array($this, 'ajax_send_test_email'));
        add_action('wp_ajax_mnem_get_queue_preview', array($this, 'ajax_get_queue_preview'));
        add_action('wp_ajax_mnem_send_queue_item_now', array($this, 'ajax_send_queue_item_now'));
        add_action('wp_ajax_mnem_table_diagnostics_recreate', array($this, 'ajax_table_diagnostics_recreate'));
        add_action('wp_ajax_mnem_table_diagnostics_optimize', array($this, 'ajax_table_diagnostics_optimize'));
        add_action('wp_ajax_mnem_table_diagnostics_repair', array($this, 'ajax_table_diagnostics_repair'));
        add_action('wp_ajax_mnem_table_diagnostics_export', array($this, 'ajax_table_diagnostics_export'));
        add_action('wp_ajax_mnem_table_diagnostics_cleanup', array($this, 'handle_table_diagnostics_cleanup'));
        add_action('wp_ajax_mnem_load_batch_users', array($this, 'handle_load_batch_users'));
        add_action('wp_ajax_mnem_bulk_add_subscribers', array($this, 'handle_bulk_add_subscribers'));
        add_action('wp_ajax_mnem_bulk_add_sms_subscribers', array($this, 'handle_bulk_add_sms_subscribers'));
        add_action('wp_ajax_mnem_invalid_phone_list', array($this, 'ajax_get_invalid_phone_numbers'));
        add_action('wp_ajax_mnem_invalid_phone_action', array($this, 'ajax_take_action_on_phone_number'));
        add_action('wp_ajax_mnem_invalid_phone_delete_user', array($this, 'ajax_take_action_on_phone_number'));
        add_action('wp_ajax_mnem_send_campaign_test_email', array($this, 'handle_send_campaign_test_email'));
        add_action('wp_ajax_mnem_preview_campaign_test_email', array($this, 'handle_preview_campaign_test_email'));

        $menu = new AdminMenu();
        $menu->init();

        $table_diagnostics = new TableDiagnostics();
        $table_diagnostics->init();
    }

    public function handle_smtp_save()
    {
        if (!isset($_POST['mnem_action']) || !in_array($_POST['mnem_action'], array('save_smtp_settings', 'send_test_email', 'save_cron_settings'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        $action = isset($_POST['mnem_action']) ? sanitize_text_field(wp_unslash($_POST['mnem_action'])) : '';
        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_smtp_settings')) {
            $this->redirect_with_notice('mnem-settings', 'smtp_nonce_failed', array('tab' => 'smtp'));
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
            $old_status_interval = \MNEM\SmtpSettings::get_status_update_interval();
            $status_interval = isset($_POST['status_update_interval']) ? (int) sanitize_text_field(wp_unslash($_POST['status_update_interval'])) : $old_status_interval;
            \MNEM\SmtpSettings::set_status_update_interval($status_interval);
            $new_status_interval = \MNEM\SmtpSettings::get_status_update_interval();
            if ($new_status_interval !== $old_status_interval && function_exists('do_action')) {
                do_action('mnem_status_update_interval_changed', $new_status_interval, $old_status_interval);
            }
            $this->redirect_with_notice('mnem-settings', 'cron_settings_saved', array('tab' => 'smtp'));
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
        $force_sender = isset($_POST['force_sender_settings']) && $_POST['force_sender_settings'] === '1';

        update_site_option('mnem_sender_name', $sender_name);
        update_site_option('mnem_sender_email', $sender_email);
        \MNEM\SmtpSettings::set_force_sender($force_sender);

        \MNEM\Logger::info('Sender settings updated', array(
            'force_sender_enabled' => $force_sender,
            'sender_email' => $sender_email,
        ));

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

    public function handle_save_status_interval_settings()
    {
        if (!isset($_POST['mnem_action']) || $_POST['mnem_action'] !== 'save_status_interval_settings') {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$this->verify_nonce($nonce, 'mnem_status_interval_settings')) {
            $this->redirect_with_notice('mnem-settings', 'status_interval_failed', array('tab' => 'status-updates'));
            return;
        }

        $interval = isset($_POST['mnem_status_update_interval']) ? (int) $_POST['mnem_status_update_interval'] : 30;

        \MNEM\SmtpSettings::set_status_update_interval($interval);

        \MNEM\Logger::info('Status update interval saved.', array('interval_minutes' => $interval));

        $this->redirect_with_notice('mnem-settings', 'status_interval_saved', array('tab' => 'status-updates'));
    }

    public function handle_save_general_settings()
    {
        if (!isset($_POST['mnem_action']) || $_POST['mnem_action'] !== 'save_general_settings') {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$this->verify_nonce($nonce, 'mnem_general_settings')) {
            $this->redirect_with_notice('mnem-settings', 'general_settings_failed', array('tab' => 'general'));
            return;
        }

        $days = isset($_POST['mnem_queue_retention_days']) ? (int) $_POST['mnem_queue_retention_days'] : 90;

        \MNEM\SmtpSettings::set_queue_retention_days($days);

        if (isset($_POST['mnem_campaign_rate_limit_per_minute'])) {
            \MNEM\SmtpSettings::set_campaign_rate_limit_per_minute((int) $_POST['mnem_campaign_rate_limit_per_minute']);
        }
        if (isset($_POST['mnem_campaign_rate_limit_per_hour'])) {
            \MNEM\SmtpSettings::set_campaign_rate_limit_per_hour((int) $_POST['mnem_campaign_rate_limit_per_hour']);
        }
        if (isset($_POST['mnem_campaign_rate_limit_per_day'])) {
            \MNEM\SmtpSettings::set_campaign_rate_limit_per_day((int) $_POST['mnem_campaign_rate_limit_per_day']);
        }
        if (isset($_POST['mnem_campaign_delay_between_sends'])) {
            \MNEM\SmtpSettings::set_campaign_delay_between_sends((int) $_POST['mnem_campaign_delay_between_sends']);
        }

        \MNEM\Logger::info('General settings saved.', array(
            'queue_retention_days' => $days,
            'campaign_rate_limit_per_minute' => \MNEM\SmtpSettings::get_campaign_rate_limit_per_minute(),
            'campaign_rate_limit_per_hour' => \MNEM\SmtpSettings::get_campaign_rate_limit_per_hour(),
            'campaign_rate_limit_per_day' => \MNEM\SmtpSettings::get_campaign_rate_limit_per_day(),
            'campaign_delay_between_sends' => \MNEM\SmtpSettings::get_campaign_delay_between_sends(),
        ));

        $this->redirect_with_notice('mnem-settings', 'general_settings_saved', array('tab' => 'general'));
    }

    public function handle_sms_settings_save()
    {
        if (!isset($_POST['mnem_action']) || $_POST['mnem_action'] !== 'save_sms_settings') {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$this->verify_nonce($nonce, 'mnem_sms_settings')) {
            $this->redirect_with_notice('mnem-settings', 'sms_settings_failed', array('tab' => 'sms'));
            return;
        }

        $no_sms_hours = isset($_POST['no_sms_hours']) ? sanitize_text_field(wp_unslash($_POST['no_sms_hours'])) : '';
        if ($no_sms_hours !== '' && !\MNEM\SmsSettings::validate_no_sms_hours($no_sms_hours)) {
            $this->redirect_with_notice('mnem-settings', 'sms_no_hours_invalid', array('tab' => 'sms'));
            return;
        }

        $max_per_day = isset($_POST['max_sms_per_day']) ? (int) $_POST['max_sms_per_day'] : 1000;
        if ($max_per_day < 1) {
            $this->redirect_with_notice('mnem-settings', 'sms_settings_failed', array('tab' => 'sms'));
            return;
        }

        $delay = isset($_POST['sms_delay']) ? (int) $_POST['sms_delay'] : 100;
        if ($delay < 0) {
            $this->redirect_with_notice('mnem-settings', 'sms_settings_failed', array('tab' => 'sms'));
            return;
        }

        $provider = isset($_POST['sms_provider']) ? sanitize_text_field(wp_unslash($_POST['sms_provider'])) : '';
        $valid_providers = \MNEM\SmsProviderManager::get_available_providers();
        if ($provider !== '' && !array_key_exists($provider, $valid_providers)) {
            $this->redirect_with_notice('mnem-settings', 'sms_settings_failed', array('tab' => 'sms'));
            return;
        }

        $fallback_provider = isset($_POST['sms_fallback_provider']) ? sanitize_text_field(wp_unslash($_POST['sms_fallback_provider'])) : '';
        if ($fallback_provider !== '' && !array_key_exists($fallback_provider, $valid_providers)) {
            $fallback_provider = '';
        }

        // Sanitize provider config fields.
        $raw_config = isset($_POST['sms_config']) && is_array($_POST['sms_config']) ? $_POST['sms_config'] : array();
        $sanitized_config = array();
        foreach ($raw_config as $prov => $fields) {
            if (!is_array($fields)) {
                continue;
            }
            $prov_key = sanitize_key((string) $prov);
            $sanitized_config[$prov_key] = array();
            foreach ($fields as $field_key => $field_val) {
                $sanitized_config[$prov_key][sanitize_key((string) $field_key)] = sanitize_text_field(wp_unslash((string) $field_val));
            }
        }

        $data = array(
            'provider'          => $provider,
            'enabled'           => !empty($_POST['sms_enabled']),
            'max_per_day'       => $max_per_day,
            'no_sms_hours'      => $no_sms_hours,
            'delay'             => $delay,
            'fallback_provider' => $fallback_provider,
            'tracking_enabled'  => !empty($_POST['sms_tracking_enabled']),
            'phone_validation_enabled' => !empty($_POST['phone_validation_enabled']),
            'validation_country_code' => isset($_POST['validation_country_code']) ? sanitize_text_field(wp_unslash($_POST['validation_country_code'])) : 'US',
            'allow_duplicate_numbers' => !empty($_POST['allow_duplicate_numbers']),
            'auto_block_failed_attempts' => isset($_POST['auto_block_failed_attempts']) ? (int) $_POST['auto_block_failed_attempts'] : 0,
            'notify_invalid_numbers' => !empty($_POST['notify_invalid_numbers']),
            'config'            => $sanitized_config,
        );

        $saved = \MNEM\SmsSettings::save($data);

        $this->redirect_with_notice('mnem-settings', $saved ? 'sms_settings_saved' : 'sms_settings_failed', array('tab' => 'sms'));
    }

    public function handle_sms_data_integrity_action()
    {
        if (!isset($_POST['mnem_sms_integrity_action']) || !in_array($_POST['mnem_sms_integrity_action'], array('check_sms_data_integrity', 'cleanup_sms_orphans', 'export_sms_cleanup_report'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        $nonce = isset($_POST['mnem_sms_integrity_nonce']) ? sanitize_text_field(wp_unslash($_POST['mnem_sms_integrity_nonce'])) : '';
        if (!$this->verify_nonce($nonce, 'mnem_sms_data_integrity')) {
            $this->redirect_with_notice('mnem-settings', 'sms_integrity_failed', array('tab' => 'sms'));
            return;
        }

        if ($_POST['mnem_sms_integrity_action'] === 'check_sms_data_integrity') {
            $result = \MNEM\SmsSubscriberLists::check_data_integrity();
            $this->store_sms_integrity_result(array('type' => 'check', 'result' => $result));
            $this->redirect_with_notice('mnem-settings', 'sms_integrity_checked', array('tab' => 'sms'));
            return;
        }

        if ($_POST['mnem_sms_integrity_action'] === 'cleanup_sms_orphans') {
            $result = \MNEM\SmsSubscriberLists::cleanup_orphaned_records();
            $this->store_sms_integrity_result(array('type' => 'cleanup', 'result' => $result));
            $notice = empty($result['errors']) ? 'sms_integrity_cleaned' : 'sms_integrity_failed';
            $this->redirect_with_notice('mnem-settings', $notice, array('tab' => 'sms'));
            return;
        }

        $report = \MNEM\SmsSubscriberLists::export_cleanup_report();
        $this->store_sms_integrity_result(array('type' => 'export', 'report' => $report));
        $this->redirect_with_notice('mnem-settings', 'sms_integrity_report_generated', array('tab' => 'sms'));
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
            if (empty($result['success'])) {
                $error_detail = isset($result['message']) ? (string) $result['message'] : 'Unknown error';
                $this->store_error_detail($error_detail);
                $this->redirect_with_notice($page, 'campaign_send_failed');
            } else {
                $this->redirect_with_notice($page, 'campaign_sent');
            }
            return;
        }

        if ($_POST['mnem_action'] === 'cancel_campaign') {
            $result = \MNEM\Campaigns::cancel_campaign($campaign_id);
            if (!$result) {
                $this->store_error_detail('Campaign could not be cancelled.');
                $this->redirect_with_notice($page, 'campaign_send_failed');
            } else {
                $this->redirect_with_notice($page, 'campaign_cancelled');
            }
            return;
        }

        if ($_POST['mnem_action'] === 'delete_campaign') {
            $result = \MNEM\Campaigns::delete($campaign_id);
            if (!$result) {
                $this->store_error_detail('Campaign could not be deleted from the database.');
                $this->redirect_with_notice($page, 'campaign_delete_failed');
            } else {
                $this->redirect_with_notice($page, 'campaign_deleted');
            }
            return;
        }

        if ($campaign_id > 0) {
            $campaign = \MNEM\Campaigns::get($campaign_id);
            if (is_array($campaign) && isset($campaign['status']) && $campaign['status'] === 'cancelled') {
                $this->store_error_detail('Cancelled campaigns cannot be edited.');
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
            if (!$result) {
                $this->store_error_detail('Campaign could not be updated.');
                $this->redirect_with_notice($page, 'campaign_save_failed');
            } else {
                $this->redirect_with_notice($page, 'campaign_updated');
            }
        }

        $result = \MNEM\Campaigns::create($site_id, $data['name'], $data['subject'], $data['body'], $data);
        if (!$result) {
            $this->store_error_detail('Campaign could not be created.');
            $this->redirect_with_notice($page, 'campaign_save_failed');
        } else {
            $this->redirect_with_notice($page, 'campaign_created');
        }
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

        if ($action === 'subscriber_unsubscribe_user') {
            $this->handle_subscriber_unsubscribe_action($list_id, $redirect_args);
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

    private function handle_subscriber_unsubscribe_action($list_id, array $redirect_args = array())
    {
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if ((int) $list_id <= 0 || $user_id <= 0 || !\MNEM\SubscriberLists::is_subscribed((int) $list_id, $user_id)) {
            $this->redirect_with_notice('mnem-subscriber-lists', 'subscriber_operation_failed', $redirect_args);
            return;
        }

        $reason = isset($_POST['unsubscribe_reason']) ? sanitize_text_field(wp_unslash($_POST['unsubscribe_reason'])) : '';
        if ($reason === '') {
            $reason = 'Unsubscribed by admin';
        }

        $result = \MNEM\SubscriberLists::unsubscribe_user((int) $list_id, $user_id, $reason);
        $this->redirect_with_notice('mnem-subscriber-lists', $result ? 'subscriber_unsubscribed' : 'subscriber_operation_failed', $redirect_args);
    }

    public function handle_sms_subscriber_list_action()
    {
        if (!isset($_POST['mnem_action']) || strpos((string) $_POST['mnem_action'], 'sms_subscriber_') !== 0) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_sms_subscriber_lists')) {
            $this->redirect_with_notice('mnem-sms-subscriber-lists', 'sms_subscriber_operation_failed');
            return;
        }

        $action = sanitize_text_field(wp_unslash($_POST['mnem_action']));
        $list_id = isset($_POST['list_id']) ? (int) $_POST['list_id'] : 0;
        $redirect_args = array();
        if ($list_id > 0) {
            $redirect_args['list_id'] = $list_id;
        }

        if ($action === 'sms_subscriber_save_list') {
            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            $description = isset($_POST['description']) ? wp_kses_post(wp_unslash($_POST['description'])) : '';
            $result = $list_id > 0
                ? \MNEM\SmsSubscriberLists::update($list_id, $name, $description)
                : \MNEM\SmsSubscriberLists::create($name, $description);
            if (is_int($result)) {
                $redirect_args['list_id'] = $result;
            }
            $this->redirect_with_notice('mnem-sms-subscriber-lists', $result ? 'sms_subscriber_list_saved' : 'sms_subscriber_operation_failed', $redirect_args);
        }

        if ($action === 'sms_subscriber_delete_list') {
            $impact = \MNEM\SmsSubscriberLists::get_delete_impact($list_id);
            $total_related = isset($impact['total_related']) ? (int) $impact['total_related'] : 0;
            $confirmed = !empty($_POST['confirm_cascade_delete']);

            if ($total_related > 100 && !$confirmed) {
                \MNEM\Logger::warning('SMS subscriber list delete requires explicit confirmation.', array(
                    'list_id' => $list_id,
                    'total_related' => $total_related,
                    'admin_id' => get_current_user_id(),
                ));
                $this->redirect_with_notice('mnem-sms-subscriber-lists', 'sms_subscriber_delete_confirmation_required', array(
                    'list_id' => $list_id,
                    'deleted_total' => $total_related,
                ));
                return;
            }

            \MNEM\Logger::info('SMS subscriber list delete requested.', array(
                'list_id' => $list_id,
                'impact' => $impact,
                'admin_id' => get_current_user_id(),
            ));
            $result = \MNEM\SmsSubscriberLists::delete($list_id, $impact);
            if (!empty($result['success'])) {
                $deleted_counts = isset($result['deleted_counts']) && is_array($result['deleted_counts']) ? $result['deleted_counts'] : array();
                $this->redirect_with_notice('mnem-sms-subscriber-lists', 'sms_subscriber_list_deleted', array(
                    'deleted_total' => (int) (isset($deleted_counts['subscribers']) ? $deleted_counts['subscribers'] : 0)
                        + (int) (isset($deleted_counts['invalid_phones']) ? $deleted_counts['invalid_phones'] : 0)
                        + (int) (isset($deleted_counts['queue_items']) ? $deleted_counts['queue_items'] : 0)
                        + (int) (isset($deleted_counts['logs']) ? $deleted_counts['logs'] : 0)
                        + (int) (isset($deleted_counts['mapping_rows']) ? $deleted_counts['mapping_rows'] : 0),
                    'mnem_alert' => $this->build_sms_delete_summary($result),
                ));
                return;
            }

            $this->redirect_with_notice('mnem-sms-subscriber-lists', 'sms_subscriber_operation_failed', array(
                'list_id' => $list_id,
                'mnem_alert' => isset($result['message']) ? (string) $result['message'] : 'SMS subscriber list operation failed.',
            ));
            return;
        }

        if ($action === 'sms_subscriber_add_user') {
            $identifier = isset($_POST['user_identifier']) ? trim((string) wp_unslash($_POST['user_identifier'])) : '';
            $phone_number = isset($_POST['phone_number']) ? sanitize_text_field(wp_unslash($_POST['phone_number'])) : '';
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
                $this->redirect_with_notice('mnem-sms-subscriber-lists', 'sms_subscriber_operation_failed', $redirect_args);
                return;
            }

            $result = \MNEM\SmsSubscriberLists::add_subscriber($list_id, $user_id, $phone_number);
            if (empty($result['success'])) {
                $redirect_args['mnem_alert'] = isset($result['phone_error']) && $result['phone_error'] !== '' ? $result['phone_error'] : $result['message'];
                $this->redirect_with_notice('mnem-sms-subscriber-lists', 'sms_subscriber_operation_failed', $redirect_args);
            }
            $this->redirect_with_notice('mnem-sms-subscriber-lists', !empty($result['added']) ? 'sms_subscriber_added' : 'sms_subscriber_operation_failed', $redirect_args);
        }

        if ($action === 'sms_subscriber_remove_user') {
            $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $result = \MNEM\SmsSubscriberLists::remove_subscriber($list_id, $user_id);
            $this->redirect_with_notice('mnem-sms-subscriber-lists', $result ? 'sms_subscriber_removed' : 'sms_subscriber_operation_failed', $redirect_args);
        }

        if ($action === 'sms_subscriber_unsubscribe_user') {
            $this->handle_sms_subscriber_unsubscribe_action($list_id, $redirect_args);
        }

        if ($action === 'sms_subscriber_restore_user') {
            $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $result = \MNEM\SmsSubscriberLists::resubscribe_user($list_id, $user_id);
            $this->redirect_with_notice('mnem-sms-subscriber-lists', $result ? 'sms_subscriber_restored' : 'sms_subscriber_operation_failed', $redirect_args);
        }

        if ($action === 'sms_subscriber_import_csv') {
            $csv_content = isset($_POST['csv_content']) ? (string) wp_unslash($_POST['csv_content']) : '';
            if ($csv_content === '' && isset($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                $csv_content = (string) file_get_contents($_FILES['csv_file']['tmp_name']);
            }
            $result = \MNEM\SmsSubscriberLists::import_from_csv($list_id, $csv_content);
            \MNEM\Logger::info('SMS subscriber CSV imported.', array('list_id' => $list_id, 'result' => $result));
            if (!empty($result['invalid']) || !empty($result['duplicates'])) {
                $redirect_args['mnem_alert'] = sprintf(
                    'Added %1$d, skipped %2$d, invalid %3$d, duplicates %4$d.',
                    isset($result['added']) ? (int) $result['added'] : 0,
                    isset($result['skipped']) ? (int) $result['skipped'] : 0,
                    isset($result['invalid']) ? (int) $result['invalid'] : 0,
                    isset($result['duplicates']) ? (int) $result['duplicates'] : 0
                );
            }
            $this->redirect_with_notice('mnem-sms-subscriber-lists', 'sms_subscriber_csv_imported', $redirect_args);
        }
    }

    private function handle_sms_subscriber_unsubscribe_action($list_id, array $redirect_args = array())
    {
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if ((int) $list_id <= 0 || $user_id <= 0 || !\MNEM\SmsSubscriberLists::is_subscribed((int) $list_id, $user_id)) {
            $this->redirect_with_notice('mnem-sms-subscriber-lists', 'sms_subscriber_operation_failed', $redirect_args);
            return;
        }

        $reason = isset($_POST['unsubscribe_reason']) ? sanitize_text_field(wp_unslash($_POST['unsubscribe_reason'])) : '';
        if ($reason === '') {
            $reason = 'Unsubscribed by admin';
        }

        $result = \MNEM\SmsSubscriberLists::unsubscribe_user((int) $list_id, $user_id, $reason);
        $this->redirect_with_notice('mnem-sms-subscriber-lists', $result ? 'sms_subscriber_unsubscribed' : 'sms_subscriber_operation_failed', $redirect_args);
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
                        'i18n' => array(
                            'sourceView' => __('HTML Source', 'multisite-network-email-manager'),
                            'visualView' => __('Visual Editor', 'multisite-network-email-manager'),
                        ),
                    )
                );
            }
        }

        if (!in_array($page, array('mnem-campaigns', 'mnem-dashboard', 'mnem-settings', 'mnem-email-templates'), true)) {
            return;
        }

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('mnem-quill-core', 'https://cdn.quilljs.com/' . self::QUILL_VERSION . '/quill.core.css', array(), self::QUILL_VERSION);
            wp_enqueue_style('mnem-quill-snow', 'https://cdn.quilljs.com/' . self::QUILL_VERSION . '/quill.snow.css', array('mnem-quill-core'), self::QUILL_VERSION);
        }

        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('mnem-quill', 'https://cdn.quilljs.com/' . self::QUILL_VERSION . '/quill.js', array(), self::QUILL_VERSION, true);
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

    public function ajax_test_provider_connection()
    {
        $this->ensure_ajax_permissions();
        $valid_providers = array('smtp', 'mailgun', 'sendgrid', 'brevo', 'postmark', 'smtp2go');
        $provider = isset($_POST['provider']) ? sanitize_text_field(wp_unslash($_POST['provider'])) : '';
        if (!in_array($provider, $valid_providers, true)) {
            wp_send_json_error(array('message' => 'Invalid provider.'), 400);
            return;
        }

        $instance = \MNEM\ProviderManager::get_provider($provider);
        if ($instance === null) {
            wp_send_json_error(array('message' => 'Provider not available.'), 400);
            return;
        }

        $result = $instance->test_connection();
        if (!empty($result['success'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result, 400);
        }
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

    public function ajax_get_queue_preview()
    {
        $this->ensure_ajax_permissions();
        $queue_id = isset($_POST['queue_id']) ? (int) $_POST['queue_id'] : 0;
        if ($queue_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid queue ID.'), 400);
            return;
        }

        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, site_id, blog_id, campaign_id, recipient_email, subject, body, from_email, from_name, headers, status, attempts, scheduled_at, sent_at, opened, clicked, opens_count, clicks_count, created_at, provider_message_id, provider_metadata FROM {$wpdb->base_prefix}mnem_queue WHERE id = %d",
                $queue_id
            ),
            ARRAY_A
        );

        if (empty($row)) {
            wp_send_json_error(array('message' => 'Queue item not found.'), 404);
            return;
        }

        wp_send_json_success($row);
    }

    public function ajax_send_queue_item_now()
    {
        $this->ensure_ajax_permissions();

        $queue_id = isset($_POST['queue_id']) ? (int) $_POST['queue_id'] : 0;
        if ($queue_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid queue ID.'), 400);
            return;
        }

        $result = \MNEM\Queue::send_now($queue_id);
        if (!empty($result['success'])) {
            \MNEM\Logger::info('Manual queue item send completed.', array(
                'queue_id' => $queue_id,
                'status' => isset($result['status']) ? (string) $result['status'] : 'sent',
                'provider' => isset($result['provider']) ? (string) $result['provider'] : '',
                'message_id' => isset($result['message_id']) ? (string) $result['message_id'] : '',
            ));

            wp_send_json_success(array(
                'message' => 'Queue item sent successfully.',
                'notice' => 'queue_item_sent_now',
                'result' => $result,
            ));
            return;
        }

        \MNEM\Logger::warning('Manual queue item send failed.', array(
            'queue_id' => $queue_id,
            'status' => isset($result['status']) ? (string) $result['status'] : 'failed',
            'message' => isset($result['message']) ? (string) $result['message'] : '',
        ));

        wp_send_json_error(array(
            'message' => !empty($result['message']) ? (string) $result['message'] : 'Queue item could not be sent.',
            'notice' => 'queue_send_failed',
            'result' => $result,
        ), 400);
    }

    public function handle_table_diagnostics_cleanup()
    {
        check_ajax_referer('mnem_table_diagnostics', 'nonce');

        if (!current_user_can('manage_network_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $cleanup_action = isset($_POST['cleanup_action']) ? sanitize_text_field(wp_unslash($_POST['cleanup_action'])) : '';

        $allowed_actions = array(
            'cleanup_orphaned_queue',
            'cleanup_invalid_emails_queue',
            'cleanup_invalid_emails_suppression',
            'cleanup_duplicate_suppressions',
            'recover_stuck_processing',
        );

        if (!in_array($cleanup_action, $allowed_actions, true)) {
            wp_send_json_error(array('message' => 'Invalid action'));
        }

        if (method_exists('\MNEM\Admin\TableDiagnostics', $cleanup_action)) {
            $result = call_user_func(array('\MNEM\Admin\TableDiagnostics', $cleanup_action));
            wp_send_json_success($result);
        }

        wp_send_json_error(array('message' => 'Action not found'));
    }

    public function ajax_table_diagnostics_recreate()
    {
        $this->ensure_ajax_permissions();
        wp_send_json_success(TableDiagnostics::recreate_missing_tables());
    }

    public function ajax_table_diagnostics_optimize()
    {
        $this->ensure_ajax_permissions();
        wp_send_json_success(TableDiagnostics::optimize_tables());
    }

    public function ajax_table_diagnostics_repair()
    {
        $this->ensure_ajax_permissions();
        wp_send_json_success(TableDiagnostics::repair_tables());
    }

    public function ajax_table_diagnostics_export()
    {
        $this->ensure_ajax_permissions();
        $format = isset($_POST['format']) ? sanitize_text_field(wp_unslash($_POST['format'])) : 'json';
        if (!in_array($format, array('json', 'text'), true)) {
            $format = 'json';
        }

        wp_send_json_success(
            array(
                'format' => $format,
                'report' => TableDiagnostics::export_report($format),
                'generated_at' => gmdate('c'),
            )
        );
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

    public function handle_bulk_add_subscribers()
    {
        check_ajax_referer('mnem_bulk_add_users', 'nonce');

        if (!current_user_can('manage_network_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $list_id = isset($_POST['list_id']) ? (int) $_POST['list_id'] : 0;
        $user_ids_raw = isset($_POST['user_ids']) ? explode(',', sanitize_text_field(wp_unslash($_POST['user_ids']))) : array();
        $user_ids = array_map('intval', array_filter($user_ids_raw));
        $skip_existing = isset($_POST['skip_existing']) && sanitize_text_field(wp_unslash($_POST['skip_existing'])) === '1';
        $skip_unsubscribed = isset($_POST['skip_unsubscribed']) && sanitize_text_field(wp_unslash($_POST['skip_unsubscribed'])) === '1';

        if ($list_id <= 0 || empty($user_ids)) {
            wp_send_json_error(array('message' => 'Invalid list or no users selected'));
        }

        $added = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($user_ids as $user_id) {
            if (!get_userdata($user_id)) {
                $failed++;
                continue;
            }

            if ($skip_existing && \MNEM\SubscriberLists::is_subscribed($list_id, $user_id)) {
                $skipped++;
                continue;
            }

            if ($skip_unsubscribed && \MNEM\SubscriberLists::is_unsubscribed($list_id, $user_id)) {
                $skipped++;
                continue;
            }

            $result = \MNEM\SubscriberLists::add_subscriber($list_id, $user_id);
            if ($result instanceof \WP_Error || $result === false) {
                $failed++;
            } else {
                $added++;
            }
        }

        $message = sprintf(
            /* translators: 1: added count, 2: skipped count, 3: failed count */
            __('Added %1$d subscribers, skipped %2$d, failed %3$d.', 'multisite-network-email-manager'),
            $added,
            $skipped,
            $failed
        );

        wp_send_json_success(array('message' => $message, 'added' => $added, 'skipped' => $skipped, 'failed' => $failed));
    }

    public function handle_load_batch_users()
    {
        check_ajax_referer('mnem_bulk_add_users', 'nonce');

        if (!current_user_can('manage_network_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $batch_size = isset($_POST['batch_size']) ? (int) $_POST['batch_size'] : 0;
        $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
        $allowed_batch_sizes = AdminMenu::get_allowed_network_user_batch_sizes();

        if (!in_array($batch_size, $allowed_batch_sizes, true)) {
            wp_send_json_error(array('message' => 'Invalid batch size'), 400);
            return;
        }

        wp_send_json_success(AdminMenu::get_network_users_batch($batch_size, $offset));
    }

    public function handle_bulk_add_sms_subscribers()
    {
        check_ajax_referer('mnem_bulk_add_sms_users', 'nonce');

        if (!current_user_can('manage_network_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }

        $list_id = isset($_POST['list_id']) ? (int) $_POST['list_id'] : 0;
        $user_ids_raw = isset($_POST['user_ids']) ? explode(',', sanitize_text_field(wp_unslash($_POST['user_ids']))) : array();
        $user_ids = array_map('intval', array_filter($user_ids_raw));
        $skip_existing = isset($_POST['skip_existing']) && sanitize_text_field(wp_unslash($_POST['skip_existing'])) === '1';
        $skip_unsubscribed = isset($_POST['skip_unsubscribed']) && sanitize_text_field(wp_unslash($_POST['skip_unsubscribed'])) === '1';
        $phone_handling = isset($_POST['phone_handling']) ? sanitize_text_field(wp_unslash($_POST['phone_handling'])) : 'skip';

        if (!in_array($phone_handling, array('skip', 'empty', 'exclude'), true)) {
            $phone_handling = 'skip';
        }

        if ($list_id <= 0 || empty($user_ids)) {
            wp_send_json_error(array('message' => 'Invalid list or no users selected'));
            return;
        }

        $added = 0;
        $skipped = 0;
        $failed = 0;
        $invalid = 0;
        $duplicates = 0;
        $invalid_numbers = array();

        foreach ($user_ids as $user_id) {
            if (!get_userdata($user_id)) {
                ++$failed;
                continue;
            }

            if ($skip_existing && \MNEM\SmsSubscriberLists::is_subscribed($list_id, $user_id)) {
                ++$skipped;
                continue;
            }

            if ($skip_unsubscribed && \MNEM\SmsSubscriberLists::is_unsubscribed($list_id, $user_id)) {
                ++$skipped;
                continue;
            }

            $phone_number = \MNEM\SmsSubscriberLists::get_resolved_phone_number($user_id);
            if ($phone_number === '' && in_array($phone_handling, array('skip', 'exclude'), true)) {
                ++$skipped;
                continue;
            }

            $result = \MNEM\SmsSubscriberLists::add_subscriber($list_id, $user_id, $phone_number);
            if (!empty($result['added'])) {
                ++$added;
                continue;
            }

            if (!empty($result['is_duplicate'])) {
                ++$duplicates;
                ++$skipped;
                continue;
            }

            if (empty($result['phone_valid'])) {
                ++$invalid;
                ++$failed;
                $invalid_numbers[] = array(
                    'user_id' => $user_id,
                    'phone_number' => $phone_number,
                    'error' => isset($result['phone_error']) ? (string) $result['phone_error'] : '',
                );
                continue;
            }

            ++$failed;
        }

        /* translators: 1: added count, 2: skipped count, 3: failed count, 4: invalid count, 5: duplicate count */
        $message = sprintf(
            __('Added %1$d subscribers, skipped %2$d, failed %3$d, invalid %4$d, duplicates %5$d.', 'multisite-network-email-manager'),
            $added,
            $skipped,
            $failed,
            $invalid,
            $duplicates
        );

        wp_send_json_success(array(
            'message' => $message,
            'added' => $added,
            'skipped' => $skipped,
            'failed' => $failed,
            'invalid' => $invalid,
            'duplicates' => $duplicates,
            'invalid_numbers' => $invalid_numbers,
            'review_url' => network_admin_url('admin.php?page=mnem-invalid-phone-numbers'),
        ));
    }

    public function handle_invalid_phone_action()
    {
        $action = isset($_POST['mnem_action']) ? sanitize_key(wp_unslash($_POST['mnem_action'])) : '';
        if (!in_array($action, array('block_phone', 'unblock_phone', 'remove_invalid_entry', 'delete_user_with_phone'), true)) {
            return;
        }

        if (!$this->current_user_can_manage_network()) {
            return;
        }

        if (!$this->verify_nonce(isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : '', 'mnem_invalid_phone_numbers')) {
            $this->redirect_with_notice('mnem-invalid-phone-numbers', 'invalid_phone_failed');
            return;
        }

        $ids = isset($_POST['invalid_ids']) ? array_map('intval', (array) wp_unslash($_POST['invalid_ids'])) : array();
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            $ids[] = $id;
        }
        $ids = array_values(array_unique(array_filter($ids)));
        $phone_number = isset($_POST['phone_number']) ? sanitize_text_field(wp_unslash($_POST['phone_number'])) : '';
        $admin_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $processed = 0;

        foreach ($ids as $entry_id) {
            $entry = \MNEM\InvalidPhoneNumbers::get_invalid_entry($entry_id);
            if (!is_array($entry)) {
                continue;
            }

            $result = $this->process_invalid_phone_action($action, $entry, $admin_id);
            if (!empty($result['success'])) {
                ++$processed;
            }
        }

        if ($processed === 0 && $phone_number !== '' && in_array($action, array('block_phone', 'unblock_phone'), true)) {
            $result = $action === 'block_phone'
                ? \MNEM\InvalidPhoneNumbers::block_number($phone_number)
                : \MNEM\InvalidPhoneNumbers::unblock_number($phone_number);
            $processed = $result ? 1 : 0;
        }

        if ($processed === 0) {
            $this->redirect_with_notice('mnem-invalid-phone-numbers', 'invalid_phone_failed');
            return;
        }

        $notice = $action === 'remove_invalid_entry' ? 'invalid_phone_removed' : ($action === 'delete_user_with_phone' ? 'invalid_phone_deleted_user' : 'invalid_phone_updated');
        $this->redirect_with_notice('mnem-invalid-phone-numbers', $notice);
    }

    public function ajax_get_invalid_phone_numbers()
    {
        check_ajax_referer('mnem_invalid_phone_numbers', 'nonce');

        if (!$this->current_user_can_manage_network()) {
            wp_send_json_error(array('message' => 'Forbidden'), 403);
            return;
        }

        $list_id = isset($_GET['list_id']) && $_GET['list_id'] !== '' ? (int) $_GET['list_id'] : null;
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'all';
        $reason = isset($_GET['reason']) ? sanitize_text_field(wp_unslash($_GET['reason'])) : '';
        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
        $per_page = isset($_GET['per_page']) ? max(1, (int) $_GET['per_page']) : 20;
        $page = isset($_GET['page_number']) ? max(1, (int) $_GET['page_number']) : 1;
        $offset = ($page - 1) * $per_page;
        $filters = array(
            'reason' => $reason,
            'search' => $search,
            'date_from' => $date_from,
            'date_to' => $date_to,
        );

        $items = \MNEM\InvalidPhoneNumbers::get_invalid_numbers($list_id, $status, $per_page, $offset, $filters);
        $total = \MNEM\InvalidPhoneNumbers::get_invalid_count($list_id, $status, $filters);

        wp_send_json_success(array(
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'items' => $items,
        ));
    }

    public function ajax_take_action_on_phone_number()
    {
        if (!$this->current_user_can_manage_network()) {
            wp_send_json_error(array('message' => 'Forbidden'), 403);
            return;
        }
        check_ajax_referer('mnem_invalid_phone_numbers', 'nonce');

        $action_type = isset($_POST['action_type']) ? sanitize_key(wp_unslash($_POST['action_type'])) : '';
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $confirm_delete = isset($_POST['confirm_delete']) && sanitize_text_field(wp_unslash($_POST['confirm_delete'])) === '1';
        $entry = $id > 0 ? \MNEM\InvalidPhoneNumbers::get_invalid_entry($id) : null;

        if (!is_array($entry)) {
            wp_send_json_error(array('message' => 'Invalid phone number entry not found.'), 404);
            return;
        }

        if ($action_type === 'delete_user_with_phone' && !$confirm_delete) {
            wp_send_json_error(array('message' => 'Confirmation required before deleting a network user.'), 400);
            return;
        }

        $result = $this->process_invalid_phone_action($action_type, $entry, function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);
        if (empty($result['success'])) {
            wp_send_json_error(array('message' => isset($result['message']) ? $result['message'] : 'Action failed.'), 400);
            return;
        }

        wp_send_json_success($result);
    }

    /**
     * @param array<string,mixed> $entry
     * @return array<string,mixed>
     */
    private function process_invalid_phone_action(string $action, array $entry, int $admin_id): array
    {
        $phone_number = isset($entry['phone_number']) ? (string) $entry['phone_number'] : '';
        $entry_id = isset($entry['id']) ? (int) $entry['id'] : 0;

        if ($action === 'block_phone') {
            $success = \MNEM\InvalidPhoneNumbers::block_number($phone_number);
            if ($success) {
                \MNEM\InvalidPhoneNumbers::take_action($entry_id, 'blocked', $admin_id);
            }

            return array('success' => $success, 'message' => $success ? 'Phone number blocked.' : 'Failed to block phone number.');
        }

        if ($action === 'unblock_phone') {
            $success = \MNEM\InvalidPhoneNumbers::unblock_number($phone_number);
            if ($success) {
                \MNEM\InvalidPhoneNumbers::take_action($entry_id, 'removed', $admin_id);
            }

            return array('success' => $success, 'message' => $success ? 'Phone number unblocked.' : 'Failed to unblock phone number.');
        }

        if ($action === 'remove_invalid_entry') {
            $success = \MNEM\InvalidPhoneNumbers::remove_invalid_entry($entry_id);
            if ($success) {
                \MNEM\Logger::info('Admin removed invalid phone number log entry.', array('id' => $entry_id, 'phone_number' => $phone_number, 'admin_id' => $admin_id));
            }

            return array('success' => $success, 'message' => $success ? 'Invalid phone number entry removed.' : 'Failed to remove invalid phone number entry.');
        }

        if ($action === 'delete_user_with_phone') {
            $user_id = isset($entry['user_id']) ? (int) $entry['user_id'] : 0;
            if ($user_id <= 0) {
                return array('success' => false, 'message' => 'No user is associated with this phone number.');
            }

            $success = $this->delete_network_user($user_id);
            if ($success) {
                \MNEM\InvalidPhoneNumbers::take_action($entry_id, 'deleted_user', $admin_id);
                \MNEM\Logger::warning('Admin deleted network user associated with invalid phone number.', array('id' => $entry_id, 'phone_number' => $phone_number, 'deleted_user_id' => $user_id, 'admin_id' => $admin_id));
            }

            return array('success' => $success, 'message' => $success ? 'User deleted successfully.' : 'Failed to delete user.');
        }

        return array('success' => false, 'message' => 'Unsupported action.');
    }

    private function delete_network_user(int $user_id): bool
    {
        if (function_exists('wpmu_delete_user')) {
            return (bool) wpmu_delete_user($user_id);
        }

        if (function_exists('wp_delete_user')) {
            return (bool) wp_delete_user($user_id);
        }

        return false;
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

    private function store_error_detail($message)
    {
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        if ($user_id === 0) {
            return;
        }
        set_transient('mnem_campaign_error_' . $user_id, (string) $message, 60);
    }

    public static function get_and_clear_error_detail()
    {
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        if ($user_id === 0) {
            return '';
        }
        $key = 'mnem_campaign_error_' . $user_id;
        $detail = get_transient($key);
        if ($detail !== false) {
            delete_transient($key);
            return (string) $detail;
        }
        return '';
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_and_clear_sms_integrity_result(): array
    {
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        if ($user_id === 0) {
            return array();
        }

        $key = 'mnem_sms_integrity_result_' . $user_id;
        $detail = get_transient($key);
        if ($detail !== false && is_array($detail)) {
            delete_transient($key);

            return $detail;
        }

        return array();
    }

    private function maybe_wrap_with_global_header_footer(string $body): string
    {
        return \MNEM\EmailFormatter::apply_global_header_footer($body);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function store_sms_integrity_result(array $payload): void
    {
        $user_id = function_exists('get_current_user_id') ? get_current_user_id() : 0;
        if ($user_id === 0) {
            return;
        }

        set_transient('mnem_sms_integrity_result_' . $user_id, $payload, 60);
    }

    /**
     * @param array<string,mixed> $result
     */
    private function build_sms_delete_summary(array $result): string
    {
        $counts = isset($result['deleted_counts']) && is_array($result['deleted_counts']) ? $result['deleted_counts'] : array();
        $parts = array();

        foreach (array(
            'subscribers' => 'subscribers',
            'invalid_phones' => 'invalid phone records',
            'queue_items' => 'queue items',
            'logs' => 'logs',
            'mapping_rows' => 'mapping rows',
        ) as $key => $label) {
            if (!isset($counts[$key]) || (int) $counts[$key] <= 0) {
                continue;
            }

            $parts[] = sprintf('%d %s', (int) $counts[$key], $label);
        }

        return empty($parts)
            ? 'Deleted SMS list with no related records to remove.'
            : 'Deleted SMS list and removed ' . implode(', ', $parts) . '.';
    }

    public function handle_send_campaign_test_email()
    {
        check_ajax_referer('mnem_test_email', 'nonce');

        if (!current_user_can('manage_network_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'multisite-network-email-manager')));
        }

        $campaign_id = isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0;
        $test_email = isset($_POST['test_email']) ? sanitize_email(wp_unslash($_POST['test_email'])) : '';
        $template_vars = isset($_POST['template_vars']) ? json_decode(wp_unslash($_POST['template_vars']), true) : array();

        if ($campaign_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid campaign', 'multisite-network-email-manager')));
        }

        if (!is_email($test_email)) {
            wp_send_json_error(array('message' => __('Invalid email address', 'multisite-network-email-manager')));
        }

        $campaign = \MNEM\Campaigns::get($campaign_id);
        if (!$campaign) {
            wp_send_json_error(array('message' => __('Campaign not found', 'multisite-network-email-manager')));
        }

        $subject = (string) $campaign['subject'];
        $body = (string) $campaign['body'];

        if (is_array($template_vars) && !empty($template_vars)) {
            foreach ($template_vars as $key => $value) {
                $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
                $body = str_replace('{{' . $key . '}}', (string) $value, $body);
            }
        }

        $body = $this->maybe_wrap_with_global_header_footer($body);

        $from_name = \MNEM\SmtpSettings::get_sender_name();
        $from_email = \MNEM\SmtpSettings::get_sender_email();
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
        );

        $result = wp_mail($test_email, $subject, $body, $headers);

        if ($result) {
            \MNEM\Logger::info('Test email sent', array(
                'campaign_id' => $campaign_id,
                'test_email'  => $test_email,
                'sent_by'     => get_current_user_id(),
            ));
            wp_send_json_success(array('message' => sprintf(
                /* translators: %s: email address */
                __('Test email sent successfully to %s', 'multisite-network-email-manager'),
                $test_email
            )));
        } else {
            wp_send_json_error(array('message' => __('Failed to send test email. Check SMTP configuration.', 'multisite-network-email-manager')));
        }
    }

    public function handle_preview_campaign_test_email()
    {
        check_ajax_referer('mnem_test_email', 'nonce');

        if (!current_user_can('manage_network_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'multisite-network-email-manager')));
        }

        $campaign_id = isset($_POST['campaign_id']) ? (int) $_POST['campaign_id'] : 0;
        $template_vars = isset($_POST['template_vars']) ? json_decode(wp_unslash($_POST['template_vars']), true) : array();

        if ($campaign_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid campaign', 'multisite-network-email-manager')));
        }

        $campaign = \MNEM\Campaigns::get($campaign_id);
        if (!$campaign) {
            wp_send_json_error(array('message' => __('Campaign not found', 'multisite-network-email-manager')));
        }

        $subject = (string) $campaign['subject'];
        $body = (string) $campaign['body'];

        if (is_array($template_vars) && !empty($template_vars)) {
            foreach ($template_vars as $key => $value) {
                $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
                $body = str_replace('{{' . $key . '}}', (string) $value, $body);
            }
        }

        $body = $this->maybe_wrap_with_global_header_footer($body);

        wp_send_json_success(array(
            'subject' => $subject,
            'body'    => $body,
            'to'      => wp_get_current_user()->user_email,
        ));
    }
}
