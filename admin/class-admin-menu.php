<?php

namespace MNEM\Admin;

defined('ABSPATH') || exit;

class AdminMenu
{
    public function init()
    {
        add_action('network_admin_menu', array($this, 'register_menus'));
        add_action('network_admin_menu', array($this, 'register_logs_submenu'), 30);
    }

    public function register_menus()
    {
        $invalid_phone_count = class_exists('\MNEM\InvalidPhoneNumbers') ? (int) \MNEM\InvalidPhoneNumbers::get_invalid_count() : 0;
        $invalid_phone_menu_title = 'Invalid Phone Numbers';
        if ($invalid_phone_count > 0) {
            $invalid_phone_menu_title .= ' <span class="awaiting-mod">' . (int) $invalid_phone_count . '</span>';
        }

        add_menu_page(
            'MNEM Dashboard',
            'SMS Campaign & Network Emails',
            'manage_network_options',
            'mnem-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-email-alt',
            58
        );

        add_submenu_page('mnem-dashboard', 'Dashboard', 'Dashboard', 'manage_network_options', 'mnem-dashboard', array($this, 'render_dashboard'));
        add_submenu_page('mnem-dashboard', 'Settings', 'Settings', 'manage_network_options', 'mnem-settings', array($this, 'render_settings'));
        add_submenu_page('mnem-dashboard', '──────────────── EMAIL SECTION ────────────────', '──────────────── EMAIL SECTION ────────────────', 'manage_network_options', 'mnem-separator-email', null);
        add_submenu_page('mnem-dashboard', 'Email Campaigns', 'Email Campaigns', 'manage_network_options', 'mnem-campaigns', array($this, 'render_campaigns'));
        add_submenu_page('mnem-dashboard', 'Email Subscribers Lists', 'Email Subscribers Lists', 'manage_network_options', 'mnem-subscriber-lists', array($this, 'render_subscriber_lists'));
        add_submenu_page('mnem-dashboard', 'Email Event Rules', 'Email Event Rules', 'manage_network_options', 'mnem-user-event-rules', array($this, 'render_user_event_rules'));
        add_submenu_page('mnem-dashboard', 'Email Suppression', 'Email Suppression', 'manage_network_options', 'mnem-suppression', array($this, 'render_suppression'));
        add_submenu_page('mnem-dashboard', 'Add Bulk Email Subscribers', 'Add Bulk Email Subscribers', 'manage_network_options', 'mnem-subscriber-lists-bulk-add', array($this, 'render_subscriber_lists_bulk_add'));
        add_submenu_page('mnem-dashboard', '──────────────── SMS SECTION ────────────────', '──────────────── SMS SECTION ────────────────', 'manage_network_options', 'mnem-separator-sms', null);
        add_submenu_page('settings.php', 'Email Templates', 'Email Templates', 'manage_network_options', 'mnem-email-templates', array($this, 'render_email_templates'));
        add_submenu_page('mnem-dashboard', 'SMS Campaigns', 'SMS Campaigns', 'manage_network_options', 'mnem-sms-campaigns', array($this, 'render_sms_campaigns'));
        add_submenu_page('mnem-dashboard', 'SMS Subscriber Lists', 'SMS Subscriber Lists', 'manage_network_options', 'mnem-sms-subscriber-lists', array($this, 'render_sms_subscriber_lists'));
        add_submenu_page('mnem-dashboard', 'Invalid Phone Numbers', $invalid_phone_menu_title, 'manage_network_options', 'mnem-invalid-phone-numbers', array($this, 'render_invalid_phone_numbers'));
        add_submenu_page('mnem-dashboard', 'Add Bulk SMS Subscribers', 'Add Bulk SMS Subscribers', 'manage_network_options', 'mnem-sms-subscriber-lists-bulk-add', array($this, 'render_sms_subscriber_lists_bulk_add'));
        add_submenu_page('mnem-dashboard', '', '', 'manage_network_options', 'mnem-separator-space', null);
    }

    public function register_logs_submenu()
    {
        add_submenu_page('mnem-dashboard', 'Logs', 'Logs', 'manage_network_options', 'mnem-logs', array($this, 'render_logs'));
    }

    public function render_logs()
    {
        global $wpdb;

        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'email';
        if (!in_array($active_tab, array('email', 'sms'), true)) {
            $active_tab = 'email';
        }

        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);

        // ── Email tab data ──────────────────────────────────────────────────────
        $queue_items       = array();
        $queue_stats       = array();
        $queue_summary     = array();
        $total_all_records = 0;
        $total_filtered    = 0;
        $total_pages       = 1;
        $current_page      = 1;
        $per_page          = 50;
        $status_filter     = '';
        $all_statuses      = array();
        $search_email      = '';
        $search_subject    = '';

        if ($active_tab === 'email') {
            $queue_table = $wpdb->base_prefix . 'mnem_queue';

            $status_filter  = isset($_GET['status_filter']) ? sanitize_text_field(wp_unslash($_GET['status_filter'])) : '';
            $search_email   = isset($_GET['search_email']) ? sanitize_text_field(wp_unslash($_GET['search_email'])) : '';
            $search_subject = isset($_GET['search_subject']) ? sanitize_text_field(wp_unslash($_GET['search_subject'])) : '';

            $allowed_per_page = array(10, 20, 50, 100, 200, 500);
            $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 50;
            if (!in_array($per_page, $allowed_per_page, true)) {
                $per_page = 50;
            }

            $current_page = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
            $offset       = ($current_page - 1) * $per_page;

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total_all_records = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$queue_table}");

            $where_clauses = array();
            $where_args    = array();

            if ($status_filter !== '') {
                $where_clauses[] = 'status = %s';
                $where_args[]    = $status_filter;
            }
            if ($search_email !== '') {
                $where_clauses[] = 'LOWER(recipient_email) LIKE %s';
                $where_args[]    = '%' . strtolower($wpdb->esc_like($search_email)) . '%';
            }
            if ($search_subject !== '') {
                $where_clauses[] = 'LOWER(subject) LIKE %s';
                $where_args[]    = '%' . strtolower($wpdb->esc_like($search_subject)) . '%';
            }

            $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

            if (!empty($where_args)) {
                $count_query = call_user_func_array(
                    array($wpdb, 'prepare'),
                    array_merge(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        array("SELECT COUNT(1) FROM {$queue_table} {$where_sql}"),
                        $where_args
                    )
                );
                $total_filtered = (int) $wpdb->get_var($count_query);
            } else {
                $total_filtered = $total_all_records;
            }

            $queue_select_columns = 'id, blog_id, campaign_id, recipient_email, subject, status, attempts, scheduled_at, sent_at, opened, clicked, opens_count, clicks_count, created_at, provider_message_id, provider_metadata';
            if (!empty($where_args)) {
                $queue_query = call_user_func_array(
                    array($wpdb, 'prepare'),
                    array_merge(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        array("SELECT {$queue_select_columns} FROM {$queue_table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d"),
                        $where_args,
                        array($per_page, $offset)
                    )
                );
            } else {
                $queue_query = $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "SELECT {$queue_select_columns} FROM {$queue_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $per_page,
                    $offset
                );
            }

            $queue_items = (array) $wpdb->get_results($queue_query, ARRAY_A);

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $all_statuses = (array) $wpdb->get_col("SELECT DISTINCT status FROM {$queue_table} ORDER BY status ASC");

            $total_pages = $per_page > 0 ? (int) ceil($total_filtered / $per_page) : 1;

            $queue_stats   = \MNEM\Queue::get_stats(null);
            $queue_summary = \MNEM\StatusSummary::get_summary(null);
        }

        // ── SMS tab data ────────────────────────────────────────────────────────
        $sms_items            = array();
        $sms_campaigns_list   = array();
        $sms_provider_statuses = array();
        $sms_total_all        = 0;
        $sms_total_filtered   = 0;
        $sms_total_pages      = 1;
        $sms_current_page     = 1;
        $sms_per_page         = 50;
        $sms_status_filter    = '';
        $sms_provider_status_filter = '';
        $sms_campaign_filter  = '';
        $sms_phone_search     = '';
        $sms_date_from        = '';
        $sms_date_to          = '';
        $sms_stats            = array('total' => 0, 'pending' => 0, 'sent' => 0, 'delivered' => 0, 'failed' => 0);

        if ($active_tab === 'sms') {
            $queue_table         = $wpdb->base_prefix . 'mnem_sms_queue';
            $sms_campaigns_table = $wpdb->base_prefix . 'mnem_sms_campaigns';

            $sms_status_filter   = isset($_GET['sms_status']) ? sanitize_text_field(wp_unslash($_GET['sms_status'])) : '';
            $sms_provider_status_filter = isset($_GET['sms_provider_status']) ? sanitize_text_field(wp_unslash($_GET['sms_provider_status'])) : '';
            $sms_campaign_filter = isset($_GET['sms_campaign']) ? sanitize_text_field(wp_unslash($_GET['sms_campaign'])) : '';
            $sms_phone_search    = isset($_GET['sms_phone']) ? sanitize_text_field(wp_unslash($_GET['sms_phone'])) : '';
            $sms_date_from       = isset($_GET['sms_date_from']) ? sanitize_text_field(wp_unslash($_GET['sms_date_from'])) : '';
            $sms_date_to         = isset($_GET['sms_date_to']) ? sanitize_text_field(wp_unslash($_GET['sms_date_to'])) : '';

            $allowed_sms_per_page = array(10, 20, 50, 100, 200, 500);
            $sms_per_page = isset($_GET['sms_per_page']) ? (int) $_GET['sms_per_page'] : 50;
            if (!in_array($sms_per_page, $allowed_sms_per_page, true)) {
                $sms_per_page = 50;
            }

            $sms_current_page = isset($_GET['sms_paged']) ? max(1, (int) $_GET['sms_paged']) : 1;
            $sms_offset       = ($sms_current_page - 1) * $sms_per_page;

            // Base WHERE for SMS records (all rows in mnem_sms_queue are SMS).
            $sms_base_where  = '1=1';
            $sms_where_extra = array();
            $sms_where_args  = array();

            if ($sms_status_filter !== '') {
                $sms_where_extra[] = 'q.status = %s';
                $sms_where_args[]  = $sms_status_filter;
            }
            if ($sms_provider_status_filter !== '') {
                $sms_where_extra[] = 'q.provider_status = %s';
                $sms_where_args[] = $sms_provider_status_filter;
            }
            if ($sms_campaign_filter !== '') {
                $sms_where_extra[] = 'q.sms_campaign_id = %d';
                $sms_where_args[]  = (int) $sms_campaign_filter;
            }
            if ($sms_phone_search !== '') {
                $sms_where_extra[] = 'q.phone_number LIKE %s';
                $sms_where_args[]  = '%' . $wpdb->esc_like($sms_phone_search) . '%';
            }
            if ($sms_date_from !== '') {
                $sms_where_extra[] = 'q.created_at >= %s';
                $sms_where_args[]  = $sms_date_from . ' 00:00:00';
            }
            if ($sms_date_to !== '') {
                $sms_where_extra[] = 'q.created_at <= %s';
                $sms_where_args[]  = $sms_date_to . ' 23:59:59';
            }

            $sms_full_where = $sms_base_where;
            if (!empty($sms_where_extra)) {
                $sms_full_where .= ' AND ' . implode(' AND ', $sms_where_extra);
            }

            // Stats summary (always use base filter only, no text search for totals).
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sms_total_all = (int) $wpdb->get_var("SELECT COUNT(1) FROM {$queue_table} q WHERE {$sms_base_where}");
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sms_stats['total']   = $sms_total_all;
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sms_stats['pending'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} q WHERE {$sms_base_where} AND q.status = %s", 'pending'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sms_stats['sent']    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} q WHERE {$sms_base_where} AND q.status = %s", 'sent'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sms_stats['delivered'] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} q WHERE {$sms_base_where} AND q.status = %s", 'delivered'));
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sms_stats['failed']  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} q WHERE {$sms_base_where} AND q.status = %s", 'failed'));

            if (!empty($sms_where_args)) {
                $sms_count_query = call_user_func_array(
                    array($wpdb, 'prepare'),
                    array_merge(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        array("SELECT COUNT(1) FROM {$queue_table} q LEFT JOIN {$sms_campaigns_table} sc ON q.sms_campaign_id = sc.id WHERE {$sms_full_where}"),
                        $sms_where_args
                    )
                );
                $sms_total_filtered = (int) $wpdb->get_var($sms_count_query);
            } else {
                $sms_total_filtered = $sms_total_all;
            }

            $sms_select = 'q.id, q.sms_campaign_id, q.phone_number, q.body, q.status, q.provider_type, q.provider_message_id, q.provider_status, q.provider_status_checked_at, q.last_sync_error, q.sent_at, q.attempts, q.created_at, sc.name AS campaign_name';
            if (!empty($sms_where_args)) {
                $sms_data_query = call_user_func_array(
                    array($wpdb, 'prepare'),
                    array_merge(
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                        array("SELECT {$sms_select} FROM {$queue_table} q LEFT JOIN {$sms_campaigns_table} sc ON q.sms_campaign_id = sc.id WHERE {$sms_full_where} ORDER BY q.created_at DESC LIMIT %d OFFSET %d"),
                        $sms_where_args,
                        array($sms_per_page, $sms_offset)
                    )
                );
            } else {
                $sms_data_query = $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    "SELECT {$sms_select} FROM {$queue_table} q LEFT JOIN {$sms_campaigns_table} sc ON q.sms_campaign_id = sc.id WHERE {$sms_full_where} ORDER BY q.created_at DESC LIMIT %d OFFSET %d",
                    $sms_per_page,
                    $sms_offset
                );
            }
            $sms_items = (array) $wpdb->get_results($sms_data_query, ARRAY_A);

            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sms_total_pages = $sms_per_page > 0 ? (int) ceil($sms_total_filtered / $sms_per_page) : 1;

            // Campaign list for the filter dropdown.
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sms_campaigns_list = (array) $wpdb->get_results("SELECT id, name FROM {$sms_campaigns_table} ORDER BY name ASC", ARRAY_A);
            $sms_provider_statuses = (array) $wpdb->get_col("SELECT DISTINCT provider_status FROM {$queue_table} WHERE provider_status IS NOT NULL AND provider_status <> '' ORDER BY provider_status ASC");
        }

        $this->render_view('logs.php', compact(
            'active_tab',
            'notice', 'notice_message', 'notice_class',
            // email vars
            'queue_items', 'queue_stats', 'queue_summary',
            'total_all_records', 'total_filtered', 'total_pages',
            'current_page', 'per_page', 'status_filter', 'all_statuses',
            'search_email', 'search_subject',
            // sms vars
            'sms_items', 'sms_campaigns_list',
            'sms_total_all', 'sms_total_filtered', 'sms_total_pages',
            'sms_current_page', 'sms_per_page',
            'sms_status_filter', 'sms_provider_status_filter', 'sms_provider_statuses', 'sms_campaign_filter', 'sms_phone_search',
            'sms_date_from', 'sms_date_to', 'sms_stats'
        ));
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
        $default_batch_size = in_array(1000, $batch_sizes, true) ? 1000 : (int) $batch_sizes[0];

        $this->render_view('subscriber-lists-bulk-add.php', compact(
            'active_list',
            'all_sites',
            'all_roles',
            'batch_sizes',
            'default_batch_size'
        ));
    }

    public function render_sms_subscriber_lists_bulk_add()
    {
        if (!isset($_GET['list_id'])) {
            wp_redirect(network_admin_url('admin.php?page=mnem-sms-subscriber-lists'));
            exit;
        }

        $active_list_id = (int) $_GET['list_id'];
        $active_list = \MNEM\SmsSubscriberLists::get($active_list_id);

        if (!$active_list) {
            wp_redirect(network_admin_url('admin.php?page=mnem-sms-subscriber-lists'));
            exit;
        }

        $all_sites = self::get_all_sites_with_user_count();
        $all_roles = self::get_all_network_roles();
        $batch_sizes = self::get_allowed_network_user_batch_sizes();
        $default_batch_size = in_array(1000, $batch_sizes, true) ? 1000 : (int) $batch_sizes[0];

        $this->render_view('sms-subscriber-lists-bulk-add.php', compact(
            'active_list',
            'all_sites',
            'all_roles',
            'batch_sizes',
            'default_batch_size'
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
        $user_ids = array_map(static function ($user) {
            return (int) $user->ID;
        }, $queried_users);
        $site_names_by_id = array();
        $memberships_by_user = array();

        if (function_exists('get_sites')) {
            foreach (get_sites(array('number' => 0)) as $site) {
                $site_names_by_id[(int) $site->blog_id] = $site->blogname;
            }
        }

        global $wpdb;
        $phone_by_user = array();
        if (!empty($user_ids) && isset($wpdb->usermeta, $wpdb->base_prefix)) {
            $placeholders = implode(',', array_fill(0, count($user_ids), '%d'));
            $capabilities_pattern = $wpdb->esc_like($wpdb->base_prefix) . '%capabilities';
            $membership_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT user_id, meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id IN ({$placeholders}) AND (meta_key = %s OR meta_key LIKE %s OR meta_key IN ('phone_number', 'phone', 'mobile'))",
                    array_merge($user_ids, array($wpdb->base_prefix . 'capabilities', $capabilities_pattern))
                )
            );

            foreach ($membership_rows as $membership_row) {
                if (in_array($membership_row->meta_key, array('phone_number', 'phone', 'mobile'), true)) {
                    $uid = (int) $membership_row->user_id;
                    if (!isset($phone_by_user[$uid]) || $phone_by_user[$uid] === '') {
                        $phone_by_user[$uid] = sanitize_text_field((string) $membership_row->meta_value);
                    }
                    continue;
                }
                if (!preg_match('/^' . preg_quote($wpdb->base_prefix, '/') . '(?:(\d+)_)?capabilities$/', (string) $membership_row->meta_key, $matches)) {
                    continue;
                }

                $site_id = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 1;
                $capabilities = maybe_unserialize($membership_row->meta_value);
                if (!is_array($capabilities)) {
                    continue;
                }

                $roles = array_keys(array_filter($capabilities));
                if (empty($roles)) {
                    continue;
                }

                $user_id = (int) $membership_row->user_id;
                if (!isset($memberships_by_user[$user_id])) {
                    $memberships_by_user[$user_id] = array();
                }

                $memberships_by_user[$user_id][$site_id] = $roles;
            }
        }

        foreach ($queried_users as $user) {
            $user_id = (int) $user->ID;
            $site_ids = array();
            $site_names = array();
            $roles = array();

            if (isset($memberships_by_user[$user_id])) {
                foreach ($memberships_by_user[$user_id] as $site_id => $site_roles) {
                    $site_ids[] = (int) $site_id;
                    $site_names[] = isset($site_names_by_id[(int) $site_id]) ? $site_names_by_id[(int) $site_id] : sprintf(__('Site %d', 'multisite-network-email-manager'), $site_id);
                    $roles = array_merge($roles, $site_roles);
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
                'phone_number' => isset($phone_by_user[$user_id]) ? $phone_by_user[$user_id] : '',
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
        $allowed_tabs = array('smtp', 'sender', 'header-footer', 'status-updates', 'general', 'sms');
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
        $sms_settings = \MNEM\SmsSettings::get_all();
        $sms_providers = \MNEM\SmsProviderManager::get_available_providers();
        $sms_integrity_stats = \MNEM\SmsSubscriberLists::get_data_integrity_overview();
        $sms_integrity_result = \MNEM\Admin\NetworkAdmin::get_and_clear_sms_integrity_result();
        $sms_status_sync_result = \MNEM\Admin\NetworkAdmin::get_and_clear_sms_status_sync_result();
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);

        $this->render_view('settings.php', compact('active_tab', 'settings', 'cron_status', 'status_update_interval', 'queue_retention_days', 'campaign_rate_limit_per_minute', 'campaign_rate_limit_per_hour', 'campaign_rate_limit_per_day', 'campaign_delay_between_sends', 'sms_settings', 'sms_providers', 'sms_integrity_stats', 'sms_integrity_result', 'sms_status_sync_result', 'notice', 'notice_message', 'notice_class'));
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
        global $wpdb;

        $lists = \MNEM\SubscriberLists::get_all();
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);
        $active_list_id = isset($_GET['list_id']) ? (int) $_GET['list_id'] : 0;
        $active_list = $active_list_id > 0 ? \MNEM\SubscriberLists::get($active_list_id) : null;
        $subscriber_search = isset($_GET['subscriber_search']) ? sanitize_text_field(wp_unslash($_GET['subscriber_search'])) : '';
        $allowed_subscriber_per_page = array(100, 500, 1000);
        $subscriber_per_page = isset($_GET['subscriber_per_page']) ? (int) $_GET['subscriber_per_page'] : 100;
        if (!in_array($subscriber_per_page, $allowed_subscriber_per_page, true)) {
            $subscriber_per_page = 100;
        }

        $subscribed_current_page = isset($_GET['subscribed_paged']) ? max(1, (int) $_GET['subscribed_paged']) : 1;
        $unsubscribed_current_page = isset($_GET['unsubscribed_paged']) ? max(1, (int) $_GET['unsubscribed_paged']) : 1;

        $subscribers = array();
        $unsubscribed = array();
        $subscribed_total = 0;
        $unsubscribed_total = 0;
        $subscribed_total_pages = 1;
        $unsubscribed_total_pages = 1;

        if ($active_list_id > 0) {
            $subscribed_result = $this->get_subscriber_table_data($wpdb, $active_list_id, 'subscribed', $subscriber_search, $subscriber_per_page, $subscribed_current_page);
            $unsubscribed_result = $this->get_subscriber_table_data($wpdb, $active_list_id, 'unsubscribed', $subscriber_search, $subscriber_per_page, $unsubscribed_current_page);

            $subscribers = $subscribed_result['rows'];
            $unsubscribed = $unsubscribed_result['rows'];
            $subscribed_total = $subscribed_result['total'];
            $unsubscribed_total = $unsubscribed_result['total'];
            $subscribed_total_pages = $subscribed_result['total_pages'];
            $unsubscribed_total_pages = $unsubscribed_result['total_pages'];
            $subscribed_current_page = $subscribed_result['current_page'];
            $unsubscribed_current_page = $unsubscribed_result['current_page'];
        }
        $alert_message = isset($_GET['mnem_alert']) ? sanitize_text_field(wp_unslash($_GET['mnem_alert'])) : '';

        $this->render_view('subscriber-lists.php', compact(
            'lists',
            'notice',
            'notice_message',
            'notice_class',
            'active_list',
            'active_list_id',
            'subscribers',
            'unsubscribed',
            'alert_message',
            'subscriber_search',
            'subscriber_per_page',
            'subscribed_current_page',
            'unsubscribed_current_page',
            'subscribed_total',
            'unsubscribed_total',
            'subscribed_total_pages',
            'unsubscribed_total_pages'
        ));
    }

    public function render_sms_subscriber_lists()
    {
        global $wpdb;

        $lists = \MNEM\SmsSubscriberLists::get_all();
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);
        $active_list_id = isset($_GET['list_id']) ? (int) $_GET['list_id'] : 0;
        $active_list = $active_list_id > 0 ? \MNEM\SmsSubscriberLists::get($active_list_id) : null;
        $subscriber_search = isset($_GET['subscriber_search']) ? sanitize_text_field(wp_unslash($_GET['subscriber_search'])) : '';
        $allowed_subscriber_per_page = array(100, 500, 1000);
        $subscriber_per_page = isset($_GET['subscriber_per_page']) ? (int) $_GET['subscriber_per_page'] : 100;
        if (!in_array($subscriber_per_page, $allowed_subscriber_per_page, true)) {
            $subscriber_per_page = 100;
        }

        $subscribed_current_page = isset($_GET['subscribed_paged']) ? max(1, (int) $_GET['subscribed_paged']) : 1;
        $unsubscribed_current_page = isset($_GET['unsubscribed_paged']) ? max(1, (int) $_GET['unsubscribed_paged']) : 1;

        $subscribers = array();
        $unsubscribed = array();
        $subscribed_total = 0;
        $unsubscribed_total = 0;
        $subscribed_total_pages = 1;
        $unsubscribed_total_pages = 1;

        if ($active_list_id > 0) {
            $subscribed_result = $this->get_sms_subscriber_table_data($wpdb, $active_list_id, 'subscribed', $subscriber_search, $subscriber_per_page, $subscribed_current_page);
            $unsubscribed_result = $this->get_sms_subscriber_table_data($wpdb, $active_list_id, 'unsubscribed', $subscriber_search, $subscriber_per_page, $unsubscribed_current_page);

            $subscribers = $subscribed_result['rows'];
            $unsubscribed = $unsubscribed_result['rows'];
            $subscribed_total = $subscribed_result['total'];
            $unsubscribed_total = $unsubscribed_result['total'];
            $subscribed_total_pages = $subscribed_result['total_pages'];
            $unsubscribed_total_pages = $unsubscribed_result['total_pages'];
            $subscribed_current_page = $subscribed_result['current_page'];
            $unsubscribed_current_page = $unsubscribed_result['current_page'];
        }
        $delete_impact = $active_list_id > 0 ? \MNEM\SmsSubscriberLists::get_delete_impact($active_list_id, is_array($active_list) ? $active_list : null) : array('counts' => array(), 'notes' => array(), 'total_related' => 0);
        $delete_requires_confirmation = !empty($delete_impact['total_related']) && (int) $delete_impact['total_related'] > 100;
        $alert_message = isset($_GET['mnem_alert']) ? sanitize_text_field(wp_unslash($_GET['mnem_alert'])) : '';

        $this->render_view('sms-subscriber-lists.php', compact(
            'lists',
            'notice',
            'notice_message',
            'notice_class',
            'active_list',
            'active_list_id',
            'subscribers',
            'unsubscribed',
            'alert_message',
            'subscriber_search',
            'subscriber_per_page',
            'subscribed_current_page',
            'unsubscribed_current_page',
            'subscribed_total',
            'unsubscribed_total',
            'subscribed_total_pages',
            'unsubscribed_total_pages',
            'delete_impact',
            'delete_requires_confirmation'
        ));
    }

    public function render_sms_campaigns()
    {
        $site_id        = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $campaigns      = \MNEM\SmsCampaigns::get_list($site_id, '', 50, 0);
        $sms_lists      = \MNEM\SmsSubscriberLists::get_all();
        $notice         = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class   = $this->get_notice_class($notice);

        $edit_campaign_id = isset($_GET['mnem_campaign']) ? (int) $_GET['mnem_campaign'] : 0;
        $edit_campaign    = $edit_campaign_id > 0 ? \MNEM\SmsCampaigns::get($edit_campaign_id) : null;

        $this->render_view('sms-campaigns.php', compact(
            'campaigns',
            'sms_lists',
            'notice',
            'notice_message',
            'notice_class',
            'edit_campaign'
        ));
    }

    public function render_email_templates()    {
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

        // Status filter + optional search filters.
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field(wp_unslash($_GET['status_filter'])) : '';
        $search_email = isset($_GET['search_email']) ? sanitize_text_field(wp_unslash($_GET['search_email'])) : '';
        $search_subject = isset($_GET['search_subject']) ? sanitize_text_field(wp_unslash($_GET['search_subject'])) : '';

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

        // Build WHERE clause for status and search filters.
        $where_clauses = array();
        $where_args = array();

        if ($status_filter !== '') {
            $where_clauses[] = 'status = %s';
            $where_args[] = $status_filter;
        }

        if ($search_email !== '') {
            $where_clauses[] = 'LOWER(recipient_email) LIKE %s';
            $where_args[] = '%' . strtolower($wpdb->esc_like($search_email)) . '%';
        }

        if ($search_subject !== '') {
            $where_clauses[] = 'LOWER(subject) LIKE %s';
            $where_args[] = '%' . strtolower($wpdb->esc_like($search_subject)) . '%';
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        if (!empty($where_args)) {
            $count_query = call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    array("SELECT COUNT(1) FROM {$queue_table} {$where_sql}"),
                    $where_args
                )
            );
            $total_filtered = (int) $wpdb->get_var($count_query);
        } else {
            $total_filtered = $total_all_records;
        }

        // Fetch page of records.
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $queue_select_columns = 'id, blog_id, campaign_id, recipient_email, subject, status, attempts, scheduled_at, sent_at, opened, clicked, opens_count, clicks_count, created_at, provider_message_id, provider_metadata';
        if (!empty($where_args)) {
            $queue_query = call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    array("SELECT {$queue_select_columns} FROM {$queue_table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d"),
                    $where_args,
                    array($per_page, $offset)
                )
            );
        } else {
            $queue_query = $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT {$queue_select_columns} FROM {$queue_table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            );
        }
        $queue_items = (array) $wpdb->get_results(
            $queue_query,
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
            'current_page', 'per_page', 'status_filter', 'all_statuses',
            'search_email', 'search_subject'
        ));
    }

    public function render_suppression()
    {
        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $suppression_entries = \MNEM\Suppression::get_list($site_id, 100, 0);
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';

        $this->render_view('suppression.php', compact('suppression_entries', 'site_id', 'notice'));
    }

    public function render_invalid_phone_numbers()
    {
        $lists = \MNEM\SmsSubscriberLists::get_all();
        $list_id = isset($_GET['list_id']) && $_GET['list_id'] !== '' ? (int) $_GET['list_id'] : null;
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : 'all';
        $reason = isset($_GET['reason']) ? sanitize_text_field(wp_unslash($_GET['reason'])) : '';
        $search = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
        $date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
        $date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
        $per_page = isset($_GET['per_page']) ? max(1, (int) $_GET['per_page']) : 20;
        $page_number = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset = ($page_number - 1) * $per_page;
        $filters = array(
            'reason' => $reason,
            'search' => $search,
            'date_from' => $date_from,
            'date_to' => $date_to,
        );
        $items = \MNEM\InvalidPhoneNumbers::get_invalid_numbers($list_id, $status, $per_page, $offset, $filters);
        $total = \MNEM\InvalidPhoneNumbers::get_invalid_count($list_id, $status, $filters);
        $total_pages = max(1, (int) ceil($total / $per_page));
        $notice = isset($_GET['mnem_notice']) ? sanitize_text_field(wp_unslash($_GET['mnem_notice'])) : '';
        $notice_message = $this->get_notice_message($notice);
        $notice_class = $this->get_notice_class($notice);

        $this->render_view('invalid-phone-numbers.php', compact(
            'lists',
            'list_id',
            'status',
            'reason',
            'search',
            'date_from',
            'date_to',
            'per_page',
            'page_number',
            'items',
            'total',
            'total_pages',
            'notice',
            'notice_message',
            'notice_class'
        ));
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

    private function get_subscriber_table_data($wpdb, $list_id, $status, $search, $per_page, $current_page)
    {
        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
        $users_table = $wpdb->base_prefix . 'users';

        $where_clauses = array('s.list_id = %d', 's.subscription_status = %s');
        $where_args = array((int) $list_id, (string) $status);

        if ($search !== '') {
            $search_like = '%' . strtolower($wpdb->esc_like((string) $search)) . '%';
            $where_clauses[] = '(LOWER(u.user_login) LIKE %s OR LOWER(u.user_email) LIKE %s)';
            $where_args[] = $search_like;
            $where_args[] = $search_like;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        $count_query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                array("SELECT COUNT(1) FROM {$table} s LEFT JOIN {$users_table} u ON s.user_id = u.ID {$where_sql}"),
                $where_args
            )
        );
        $total = (int) $wpdb->get_var($count_query);

        $total_pages = max(1, (int) ceil($total / $per_page));
        $current_page = min(max(1, (int) $current_page), $total_pages);
        $offset = ($current_page - 1) * $per_page;

        $rows_query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                array("SELECT s.user_id, s.subscribed_at, s.unsubscribed_at, s.unsubscribed_reason, COALESCE(u.user_login, '') AS user_login, COALESCE(u.user_email, '') AS user_email FROM {$table} s LEFT JOIN {$users_table} u ON s.user_id = u.ID {$where_sql} ORDER BY s.id DESC LIMIT %d OFFSET %d"),
                $where_args,
                array($per_page, $offset)
            )
        );
        $rows = (array) $wpdb->get_results($rows_query, ARRAY_A);

        return array(
            'rows' => $rows,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $current_page,
        );
    }

    private function get_sms_subscriber_table_data($wpdb, $list_id, $status, $search, $per_page, $current_page)
    {
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $users_table = $wpdb->base_prefix . 'users';

        $where_clauses = array('s.list_id = %d', 's.subscription_status = %s');
        $where_args = array((int) $list_id, (string) $status);

        if ($search !== '') {
            $search_like = '%' . strtolower($wpdb->esc_like((string) $search)) . '%';
            $where_clauses[] = '(LOWER(u.user_login) LIKE %s OR LOWER(s.phone_number) LIKE %s OR LOWER(s.subscriber_name) LIKE %s)';
            $where_args[] = $search_like;
            $where_args[] = $search_like;
            $where_args[] = $search_like;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        $count_query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                array("SELECT COUNT(1) FROM {$table} s LEFT JOIN {$users_table} u ON s.user_id = u.ID {$where_sql}"),
                $where_args
            )
        );
        $total = (int) $wpdb->get_var($count_query);

        $total_pages = max(1, (int) ceil($total / $per_page));
        $current_page = min(max(1, (int) $current_page), $total_pages);
        $offset = ($current_page - 1) * $per_page;

        $rows_query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                array("SELECT s.user_id, s.subscriber_name, s.phone_number, s.subscribed_at, s.unsubscribed_at, s.unsubscribed_reason, COALESCE(u.user_login, '') AS user_login FROM {$table} s LEFT JOIN {$users_table} u ON s.user_id = u.ID {$where_sql} ORDER BY s.id DESC LIMIT %d OFFSET %d"),
                $where_args,
                array($per_page, $offset)
            )
        );
        $rows = (array) $wpdb->get_results($rows_query, ARRAY_A);

        foreach ($rows as &$row) {
            $row['subscriber_name'] = isset($row['subscriber_name']) ? (string) $row['subscriber_name'] : '';
            if ((int) $row['user_id'] > 0) {
                $row['subscriber_type'] = 'user';
                $row['display_name'] = isset($row['user_login']) && $row['user_login'] !== ''
                    ? (string) $row['user_login']
                    : ('user_id:' . (int) $row['user_id']);
            } else {
                $row['subscriber_type'] = 'standalone';
                $row['display_name'] = $row['subscriber_name'] !== '' ? $row['subscriber_name'] : 'Standalone Subscriber';
            }
        }
        unset($row);

        return array(
            'rows' => $rows,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $current_page,
        );
    }

    private function get_notice_message($notice)    {
        $count = isset($_GET['count']) ? (int) $_GET['count'] : 0;
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $deleted_total = isset($_GET['deleted_total']) ? (int) sanitize_text_field(wp_unslash($_GET['deleted_total'])) : 0;
        $new_campaign_id = isset($_GET['mnem_new_campaign_id']) ? (int) sanitize_text_field(wp_unslash($_GET['mnem_new_campaign_id'])) : 0;
        if (!in_array($status, \MNEM\Queue::DELETABLE_STATUSES, true)) {
            $status = 'queue';
        }
        $error_detail_notices = array('campaign_send_failed', 'campaign_save_failed', 'campaign_delete_failed', 'sms_campaign_save_failed', 'sms_campaign_action_failed', 'sms_log_action_failed');
        $error_detail = in_array($notice, $error_detail_notices, true)
            ? \MNEM\Admin\NetworkAdmin::get_and_clear_error_detail()
            : '';
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
            'queue_item_sent_now' => 'Queue item sent immediately.',
            'queue_item_deleted' => 'Queue item deleted.',
            'queue_items_deleted' => $count === 1 ? '1 queue item deleted.' : sprintf('%d queue items deleted.', $count),
            'queue_deleted_by_status' => sprintf('%d %s item%s deleted.', $count, $status, $count === 1 ? '' : 's'),
            'queue_delete_failed' => 'Failed to delete queue item.',
            'queue_send_failed' => 'Failed to send queue item immediately.',
            'queue_nothing_selected' => 'No items selected for deletion.',
            'campaign_nonce_failed' => 'Campaign security check failed.',
            'queue_nonce_failed' => 'Queue security check failed.',
            'campaign_send_failed' => 'Campaign send failed' . ($error_detail !== '' ? ': ' . $error_detail : '.'),
            'campaign_save_failed' => 'Campaign could not be saved' . ($error_detail !== '' ? ': ' . $error_detail : '.'),
            'campaign_delete_failed' => 'Campaign could not be deleted' . ($error_detail !== '' ? ': ' . $error_detail : '.'),
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
            'subscriber_unsubscribed' => 'Subscriber unsubscribed successfully.',
            'subscriber_restored' => 'Subscriber restored successfully.',
            'subscriber_csv_imported' => 'Subscriber CSV import processed.',
            'subscriber_operation_failed' => 'Subscriber list operation failed.',
            'sms_subscriber_list_saved' => 'SMS subscriber list saved.',
            'sms_subscriber_list_deleted' => sprintf('SMS subscriber list deleted. Removed %d related record%s.', max(0, $deleted_total), $deleted_total === 1 ? '' : 's'),
            'sms_subscriber_added' => 'SMS subscriber added successfully.',
            'sms_subscriber_removed' => 'SMS subscriber removed successfully.',
            'sms_subscriber_unsubscribed' => 'SMS subscriber unsubscribed successfully.',
            'sms_subscriber_restored' => 'SMS subscriber restored successfully.',
            'sms_subscriber_csv_imported' => 'SMS subscriber CSV import processed.',
            'sms_subscriber_delete_confirmation_required' => sprintf('This SMS list delete will remove %d related record%s. Please confirm the cascade delete and submit again.', max(0, $deleted_total), $deleted_total === 1 ? '' : 's'),
            'invalid_phone_updated' => 'Invalid phone number records updated successfully.',
            'invalid_phone_removed' => 'Invalid phone number entry removed successfully.',
            'invalid_phone_deleted_user' => 'Network user deleted successfully.',
            'sms_subscriber_operation_failed' => 'SMS subscriber list operation failed.',
            'sms_integrity_checked' => 'SMS data integrity check completed.',
            'sms_integrity_cleaned' => 'SMS orphan cleanup completed.',
            'sms_integrity_report_generated' => 'SMS cleanup report generated.',
            'sms_integrity_failed' => 'SMS data integrity action failed.',
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
            'sms_settings_saved' => 'SMS settings saved successfully.',
            'sms_settings_failed' => 'Failed to save SMS settings.',
            'sms_no_hours_invalid' => "Invalid 'No SMS Hours' format. Use HH:MM:SS-HH:MM:SS",
            'sms_connection_tested' => 'SMS connection test completed.',
            'sms_campaign_created' => 'SMS campaign created successfully.',
            'sms_campaign_updated' => 'SMS campaign updated successfully.',
            'sms_campaign_deleted' => 'SMS campaign deleted successfully.',
            'sms_campaign_sent' => 'SMS campaign send has been queued.',
            'sms_campaign_scheduled' => 'SMS campaign scheduled successfully.',
            'sms_campaign_paused' => 'SMS campaign sending is now paused.',
            'sms_campaign_resumed' => 'SMS campaign sending has resumed.',
            'sms_campaign_cancelled' => 'SMS campaign cancelled successfully.',
            'sms_campaign_copied' => $new_campaign_id > 0
                ? sprintf('SMS campaign copied successfully. New draft campaign ID: %d.', $new_campaign_id)
                : 'SMS campaign copied successfully.',
            'sms_campaign_nonce_failed' => 'SMS campaign security check failed.',
            'sms_campaign_save_failed' => 'SMS campaign could not be saved' . ($error_detail !== '' ? ': ' . $error_detail : '.'),
            'sms_campaign_action_failed' => 'SMS campaign action failed' . ($error_detail !== '' ? ': ' . $error_detail : '.'),
            'sms_log_unsubscribed' => 'Subscriber unsubscribed successfully.',
            'sms_log_user_deleted' => 'WordPress user deleted successfully.',
            'sms_log_unsubscribed_and_deleted' => 'Subscriber unsubscribed and WordPress user deleted.',
            'sms_log_action_failed' => 'Action failed. Please check logs.' . ($error_detail !== '' ? ' ' . $error_detail : ''),
            'sms_status_sync_complete' => 'SMS provider status sync completed.',
            'sms_status_sync_failed' => 'SMS provider status sync completed with errors.',
        );

        return isset($messages[$notice]) ? $messages[$notice] : '';
    }

    private function get_notice_class($notice)
    {
        if ($notice === 'queue_nothing_selected') {
            return 'notice notice-warning';
        }

        if ($notice === 'sms_subscriber_delete_confirmation_required') {
            return 'notice notice-warning';
        }

        if (in_array($notice, array('campaign_nonce_failed', 'queue_nonce_failed', 'queue_delete_failed', 'queue_send_failed', 'campaign_send_failed', 'campaign_save_failed', 'campaign_delete_failed', 'diagnostics_nonce_failed', 'rule_save_failed', 'rule_nonce_failed', 'smtp_test_failed', 'sender_settings_failed', 'header_footer_failed', 'subscriber_operation_failed', 'sms_subscriber_operation_failed', 'email_template_failed', 'status_interval_failed', 'general_settings_failed', 'sms_settings_failed', 'sms_no_hours_invalid', 'invalid_phone_failed', 'sms_integrity_failed', 'sms_campaign_nonce_failed', 'sms_campaign_save_failed', 'sms_campaign_action_failed', 'sms_log_action_failed', 'sms_status_sync_failed'), true)) {
            return 'notice notice-error';
        }

        return $notice !== '' ? 'notice notice-success' : '';
    }
}
