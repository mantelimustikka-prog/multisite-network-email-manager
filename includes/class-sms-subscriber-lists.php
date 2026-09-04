<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmsSubscriberLists
{
    private const ORPHANED_SMS_LOG_SCAN_LIMIT = 1000;
    private const PHONE_MATCH_FALLBACK_LIMIT = 5000;

    public static function create(string $name, string $description = '')
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $now = self::current_time_mysql();
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (name, description, created_at, updated_at) VALUES (%s, %s, %s, %s)",
                sanitize_text_field($name),
                (string) $description,
                $now,
                $now
            )
        );

        if ($result === false) {
            return false;
        }

        Logger::info('SMS subscriber list created.', array('name' => $name, 'user_id' => get_current_user_id()));

        return isset($wpdb->insert_id) ? (int) $wpdb->insert_id : true;
    }

    public static function get(int $id)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';

        return (array) $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", 500),
            ARRAY_A
        );
    }

    public static function update(int $id, string $name, string $description)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET name = %s, description = %s, updated_at = %s WHERE id = %d",
                sanitize_text_field($name),
                (string) $description,
                self::current_time_mysql(),
                $id
            )
        );

        if ($result !== false) {
            Logger::info('SMS subscriber list updated.', array('list_id' => $id, 'user_id' => get_current_user_id()));
        }

        return $result !== false;
    }

    /**
     * @return array<string,mixed>
     */
    public static function delete(int $id, ?array $impact = null): array
    {
        global $wpdb;

        $lists_table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $subs_table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';

        $result = array(
            'success' => false,
            'message' => 'SMS subscriber list could not be deleted.',
            'deleted_counts' => array(
                'list' => 0,
                'subscribers' => 0,
                'invalid_phones' => 0,
                'queue_items' => 0,
                'logs' => 0,
                'mapping_rows' => 0,
            ),
            'errors' => array(),
            'notes' => array(),
        );

        $list = self::get($id);
        if (!is_array($list)) {
            $result['message'] = 'SMS subscriber list not found.';
            $result['errors'][] = 'list_not_found';

            return $result;
        }

        $impact = is_array($impact) ? $impact : self::get_delete_impact($id, $list);
        $result['notes'] = isset($impact['notes']) && is_array($impact['notes']) ? $impact['notes'] : array();

        if ($wpdb->query('START TRANSACTION') === false) {
            $result['message'] = 'Unable to start SMS subscriber list deletion transaction.';
            $result['errors'][] = 'transaction_start_failed';
            Logger::error('SMS subscriber list delete failed before cleanup started.', array(
                'deleted_list_id' => $id,
                'list_name' => isset($list['name']) ? (string) $list['name'] : '',
                'errors' => $result['errors'],
            ));

            return $result;
        }

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $invalid_table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $logs_table = $wpdb->base_prefix . 'mnem_logs';
        $mapping_table = $wpdb->base_prefix . 'mnem_sms_campaign_list_map';
        $log_ids = self::get_sms_log_ids_for_list($id);

        $deletions = array(
            'mapping_rows' => self::table_exists($mapping_table)
                ? $wpdb->prepare("DELETE FROM {$mapping_table} WHERE list_id = %d", $id)
                : '',
            'invalid_phones' => self::table_exists($invalid_table)
                ? $wpdb->prepare("DELETE FROM {$invalid_table} WHERE list_id = %d", $id)
                : '',
            'queue_items' => self::table_exists($queue_table) && self::table_has_column($queue_table, 'list_id')
                ? $wpdb->prepare("DELETE FROM {$queue_table} WHERE list_id = %d", $id)
                : '',
            'logs' => !empty($log_ids) ? self::prepare_delete_ids_query($logs_table, $log_ids) : '',
            'subscribers' => $wpdb->prepare("DELETE FROM {$subs_table} WHERE list_id = %d", $id),
            'list' => $wpdb->prepare("DELETE FROM {$lists_table} WHERE id = %d", $id),
        );

        foreach ($deletions as $key => $query) {
            if ($query === '') {
                continue;
            }

            $deleted = $wpdb->query($query);
            if ($deleted === false) {
                $wpdb->query('ROLLBACK');
                $result['errors'][] = $key . '_delete_failed';
                $result['message'] = 'SMS subscriber list delete failed during related record cleanup.';
                Logger::error('SMS subscriber list delete rolled back.', array(
                    'deleted_list_id' => $id,
                    'list_name' => isset($list['name']) ? (string) $list['name'] : '',
                    'failed_step' => $key,
                    'errors' => $result['errors'],
                    'notes' => $result['notes'],
                ));

                return $result;
            }

            $result['deleted_counts'][$key] = (int) $deleted;
        }

        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            $result['errors'][] = 'transaction_commit_failed';
            $result['message'] = 'SMS subscriber list delete failed while committing changes.';
            Logger::error('SMS subscriber list delete commit failed.', array(
                'deleted_list_id' => $id,
                'list_name' => isset($list['name']) ? (string) $list['name'] : '',
                'deleted_counts' => $result['deleted_counts'],
                'errors' => $result['errors'],
            ));

            return $result;
        }

        $result['success'] = true;
        $result['message'] = 'SMS subscriber list deleted successfully.';

        Logger::info('SMS subscriber list deleted with cascade cleanup.', array(
            'deleted_list_id' => $id,
            'list_name' => isset($list['name']) ? (string) $list['name'] : '',
            'deleted_counts' => $result['deleted_counts'],
            'notes' => $result['notes'],
            'admin_id' => get_current_user_id(),
        ));

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public static function add_subscriber(int $list_id, int $user_id, string $phone_number = '', ?string $country_code = null)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';

        if ($phone_number === '') {
            $phone_number = self::resolve_phone_number($user_id);
        }

        $validation = self::validate_phone_number($phone_number, $country_code);

        // Ambiguous country: reject with explanation.
        if (!$validation['valid'] && isset($validation['reason_code']) && $validation['reason_code'] === 'ambiguous_country') {
            InvalidPhoneNumbers::log_invalid_number($phone_number, 'ambiguous_country', $list_id, $user_id);
            self::maybe_auto_block_invalid_number($phone_number);
            return self::build_add_response(false, false, false, null, $validation, 'Phone number is ambiguous: multiple countries could match.', 'error');
        }

        // Unsupported country: reject.
        if ($validation['valid']) {
            $allowed = SmsSettings::get_allowed_countries();
            if (!empty($allowed)) {
                $detected_country = isset($validation['country_iso2']) ? (string) $validation['country_iso2'] : '';
                if ($detected_country !== '' && !in_array($detected_country, $allowed, true)) {
                    InvalidPhoneNumbers::log_invalid_number($phone_number, 'unsupported_country', $list_id, $user_id);
                    return self::build_add_response(false, false, false, null, $validation, sprintf('Phone number country %s is not in the allowed countries list.', $detected_country), 'error');
                }
            }
        }

        // Invalid format: reject.
        if (empty($validation['valid'])) {
            $reason = (isset($validation['reason_code']) && $validation['reason_code'] !== null) ? (string) $validation['reason_code'] : 'format_invalid';
            InvalidPhoneNumbers::log_invalid_number($phone_number, $reason, $list_id, $user_id);
            self::maybe_auto_block_invalid_number($phone_number);
            return self::build_add_response(false, false, false, null, $validation, 'Phone number is invalid.', 'error');
        }

        $formatted_phone = (string) $validation['formatted'];

        // Blocked number: reject.
        if (InvalidPhoneNumbers::is_blocked($formatted_phone)) {
            return self::build_add_response(false, false, false, null, array(
                'valid'     => true,
                'formatted' => $formatted_phone,
                'error'     => '',
            ), 'Phone number has been blocked from subscribing.', 'error');
        }

        // Cross-user duplicate check.
        if (!SmsSettings::allow_duplicate_numbers()) {
            $duplicate = self::find_subscriber_by_phone($list_id, $formatted_phone);
            if (is_array($duplicate) && isset($duplicate['user_id']) && (int) $duplicate['user_id'] !== $user_id) {
                InvalidPhoneNumbers::log_invalid_number($formatted_phone, 'duplicate', $list_id, $user_id);
                return self::build_add_response(false, false, true, (int) $duplicate['user_id'], $validation, 'Phone number is already subscribed to this list.', 'error');
            }
        }

        // Look up any existing record for this user in this list.
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE list_id = %d AND user_id = %d", $list_id, $user_id),
            ARRAY_A
        );

        if (is_array($existing)) {
            $status = isset($existing['subscription_status']) ? $existing['subscription_status'] : '';

            if ($status === 'subscribed') {
                return self::build_add_response(true, false, true, $user_id, $validation, 'User is already subscribed to this SMS list.', 'duplicate');
            }

            // Unsubscribed (or any other non-subscribed status): restore the subscription.
            $restored    = self::resubscribe_user($list_id, $user_id);
            $existing_id = $restored && isset($existing['id']) ? (int) $existing['id'] : null;
            if ($restored) {
                Logger::info('Subscriber restored to SMS list.', array('list_id' => $list_id, 'user_id' => $user_id));
            }
            return self::build_add_response($restored, $restored, false, $user_id, $validation, $restored ? 'Subscriber restored successfully.' : 'Failed to restore subscriber.', $restored ? 'restored' : 'error', $existing_id);
        }

        // No existing record: insert a fresh subscription.
        $now    = self::current_time_mysql();
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (list_id, user_id, subscriber_name, phone_number, subscription_status, subscribed_at, unsubscribed_at, unsubscribed_reason) VALUES (%d, %d, %s, %s, %s, %s, %s, %s)",
                $list_id,
                $user_id,
                '',
                $formatted_phone,
                'subscribed',
                $now,
                null,
                ''
            )
        );

        if ($result !== false) {
            Logger::info('Subscriber added to SMS list.', array('list_id' => $list_id, 'user_id' => $user_id));
        }

        $new_id = $result !== false && isset($wpdb->insert_id) ? (int) $wpdb->insert_id : null;
        return self::build_add_response($result !== false, $result !== false, false, null, $validation, $result !== false ? 'Subscriber added successfully.' : 'Failed to add subscriber.', $result !== false ? 'added' : 'error', $new_id);
    }

    /**
     * @return array<string,mixed>
     */
    public static function add_standalone_subscriber(int $list_id, string $name, string $phone_number, ?string $country_code = null): array
    {
        global $wpdb;

        $table        = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $name         = sanitize_text_field($name);
        $phone_number = trim($phone_number);

        if ($name === '' || $phone_number === '') {
            return self::build_add_response(false, false, false, null, array('valid' => false, 'error' => 'Name and phone number are required.'), 'Name and phone number are required.', 'error');
        }

        $validation = self::validate_phone_number($phone_number, $country_code);

        // Ambiguous country: reject with explanation.
        if (!$validation['valid'] && isset($validation['reason_code']) && $validation['reason_code'] === 'ambiguous_country') {
            InvalidPhoneNumbers::log_invalid_number($phone_number, 'ambiguous_country', $list_id, 0);
            self::maybe_auto_block_invalid_number($phone_number);
            return self::build_add_response(false, false, false, null, $validation, 'Phone number is ambiguous: multiple countries could match.', 'error');
        }

        // Unsupported country: reject.
        if ($validation['valid']) {
            $allowed = SmsSettings::get_allowed_countries();
            if (!empty($allowed)) {
                $detected_country = isset($validation['country_iso2']) ? (string) $validation['country_iso2'] : '';
                if ($detected_country !== '' && !in_array($detected_country, $allowed, true)) {
                    InvalidPhoneNumbers::log_invalid_number($phone_number, 'unsupported_country', $list_id, 0);
                    return self::build_add_response(false, false, false, null, $validation, sprintf('Phone number country %s is not in the allowed countries list.', $detected_country), 'error');
                }
            }
        }

        // Invalid format: reject.
        if (empty($validation['valid'])) {
            $reason = (isset($validation['reason_code']) && $validation['reason_code'] !== null) ? (string) $validation['reason_code'] : 'format_invalid';
            InvalidPhoneNumbers::log_invalid_number($phone_number, $reason, $list_id, 0);
            self::maybe_auto_block_invalid_number($phone_number);
            return self::build_add_response(false, false, false, null, $validation, 'Phone number is invalid.', 'error');
        }

        $formatted_phone = (string) $validation['formatted'];

        // Blocked number: reject.
        if (InvalidPhoneNumbers::is_blocked($formatted_phone)) {
            return self::build_add_response(false, false, false, null, array(
                'valid'     => true,
                'formatted' => $formatted_phone,
                'error'     => '',
            ), 'Phone number has been blocked from subscribing.', 'error');
        }

        // Cross-user duplicate check (only subscribed user-based records are flagged).
        if (!SmsSettings::allow_duplicate_numbers()) {
            $duplicate = self::find_subscriber_by_phone($list_id, $formatted_phone);
            if (is_array($duplicate)) {
                $duplicate_user_id = isset($duplicate['user_id']) ? (int) $duplicate['user_id'] : 0;

                if ($duplicate_user_id !== 0) {
                    // Duplicate belongs to a network user — reject.
                    $user     = function_exists('get_userdata') ? get_userdata($duplicate_user_id) : null;
                    $username = is_object($user) && isset($user->user_login) ? (string) $user->user_login : ('user_id:' . $duplicate_user_id);

                    InvalidPhoneNumbers::log_invalid_number($formatted_phone, 'duplicate', $list_id, 0);
                    return self::build_add_response(
                        false,
                        false,
                        true,
                        $duplicate_user_id,
                        $validation,
                        sprintf('Phone number already subscribed to user %s. Cannot add as standalone subscriber.', $username),
                        'error'
                    );
                }
                // Duplicate is standalone (user_id = 0): fall through to the restoration path.
            }
        }

        // Look up existing standalone record (user_id = 0) regardless of status.
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE list_id = %d AND user_id = %d AND phone_number = %s",
                $list_id,
                0,
                $formatted_phone
            ),
            ARRAY_A
        );

        if (is_array($existing)) {
            $status = isset($existing['subscription_status']) ? $existing['subscription_status'] : '';

            if ($status === 'subscribed') {
                return self::build_add_response(true, false, true, 0, $validation, 'Phone number is already subscribed to this list.', 'duplicate');
            }

            // Unsubscribed (or any other non-subscribed status): restore and update name.
            $restored    = self::resubscribe_standalone($list_id, $formatted_phone);
            $existing_id = $restored && isset($existing['id']) ? (int) $existing['id'] : null;
            if ($restored) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET subscriber_name = %s WHERE list_id = %d AND user_id = %d AND phone_number = %s",
                        $name,
                        $list_id,
                        0,
                        $formatted_phone
                    )
                );
                Logger::info('Standalone subscriber restored to SMS list.', array('list_id' => $list_id, 'phone_number' => $formatted_phone));
            }
            return self::build_add_response($restored, $restored, false, 0, $validation, $restored ? 'Standalone subscriber restored successfully.' : 'Failed to restore standalone subscriber.', $restored ? 'restored' : 'error', $existing_id);
        }

        // No existing record: insert a fresh subscription.
        $now    = self::current_time_mysql();
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (list_id, user_id, subscriber_name, phone_number, subscription_status, subscribed_at, unsubscribed_at, unsubscribed_reason) VALUES (%d, %d, %s, %s, %s, %s, %s, %s)",
                $list_id,
                0,
                $name,
                $formatted_phone,
                'subscribed',
                $now,
                null,
                ''
            )
        );

        if ($result !== false) {
            Logger::info('Standalone subscriber added to SMS list.', array('list_id' => $list_id, 'phone_number' => $formatted_phone));
        }

        $new_id = $result !== false && isset($wpdb->insert_id) ? (int) $wpdb->insert_id : null;
        return self::build_add_response($result !== false, $result !== false, false, null, $validation, $result !== false ? 'Standalone subscriber added successfully.' : 'Failed to add standalone subscriber.', $result !== false ? 'added' : 'error', $new_id);
    }

    public static function remove_subscriber(int $list_id, int $user_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $result = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE list_id = %d AND user_id = %d", $list_id, $user_id)
        );

        if ($result !== false) {
            Logger::info('Subscriber removed from SMS list.', array('list_id' => $list_id, 'user_id' => $user_id));
        }

        return $result !== false;
    }

    public static function unsubscribe_user(int $list_id, int $user_id, string $reason = '')
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE list_id = %d AND user_id = %d", $list_id, $user_id)
        );
        $now = self::current_time_mysql();

        if ((int) $existing > 0) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET subscription_status = %s, unsubscribed_at = %s, unsubscribed_reason = %s WHERE list_id = %d AND user_id = %d",
                    'unsubscribed',
                    $now,
                    sanitize_text_field($reason),
                    $list_id,
                    $user_id
                )
            );
        } else {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (list_id, user_id, phone_number, subscription_status, subscribed_at, unsubscribed_at, unsubscribed_reason) VALUES (%d, %d, %s, %s, %s, %s, %s)",
                    $list_id,
                    $user_id,
                    '',
                    'unsubscribed',
                    $now,
                    $now,
                    sanitize_text_field($reason)
                )
            );
        }

        if ($result !== false) {
            Logger::info('Subscriber unsubscribed from SMS list.', array('list_id' => $list_id, 'user_id' => $user_id, 'reason' => $reason));
        }

        return $result !== false;
    }

    public static function resubscribe_user(int $list_id, int $user_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET subscription_status = %s, subscribed_at = %s, unsubscribed_at = %s, unsubscribed_reason = %s WHERE list_id = %d AND user_id = %d",
                'subscribed',
                self::current_time_mysql(),
                null,
                '',
                $list_id,
                $user_id
            )
        );

        if ($result !== false) {
            Logger::info('Subscriber restored to subscribed in SMS list.', array('list_id' => $list_id, 'user_id' => $user_id));
        }

        return $result !== false;
    }

    public static function get_subscribers(int $list_id, int $limit = 1000, int $offset = 0)
    {
        return self::get_users_by_status($list_id, 'subscribed', $limit, $offset);
    }

    public static function get_unsubscribed(int $list_id, int $limit = 1000, int $offset = 0)
    {
        return self::get_users_by_status($list_id, 'unsubscribed', $limit, $offset);
    }

    public static function get_standalone_subscribers(int $list_id, int $limit = 1000, int $offset = 0): array
    {
        return self::get_standalone_by_status($list_id, 'subscribed', $limit, $offset);
    }

    public static function get_all_subscribers_mixed(int $list_id, int $limit = 1000, int $offset = 0): array
    {
        return self::get_users_by_status($list_id, 'subscribed', $limit, $offset);
    }

    public static function get_list_subscribers_count(int $list_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE list_id = %d AND subscription_status = %s", $list_id, 'subscribed')
        );
    }

    /**
     * Search subscribers in a list by username, phone number, or subscriber name.
     *
     * @param int    $list_id  SMS subscriber list ID.
     * @param string $query    Search string (empty returns all).
     * @param string $status   Subscription status filter: 'subscribed' or 'unsubscribed'.
     * @param int    $per_page Results per page.
     * @param int    $page     1-based page number.
     * @return array{rows: array, total: int, total_pages: int, current_page: int}
     */
    public static function search_subscribers(int $list_id, string $query, string $status = 'subscribed', int $per_page = 100, int $page = 1): array
    {
        global $wpdb;

        $table       = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $users_table = $wpdb->base_prefix . 'users';

        if (!in_array($status, array('subscribed', 'unsubscribed'), true)) {
            $status = 'subscribed';
        }

        $per_page = max(1, (int) $per_page);
        $page     = max(1, (int) $page);

        $where_clauses = array('s.list_id = %d', 's.subscription_status = %s');
        $where_args    = array($list_id, $status);

        if ($query !== '') {
            $like            = '%' . strtolower($wpdb->esc_like($query)) . '%';
            $where_clauses[] = '(LOWER(u.user_login) LIKE %s OR LOWER(s.phone_number) LIKE %s OR LOWER(s.subscriber_name) LIKE %s)';
            $where_args[]    = $like;
            $where_args[]    = $like;
            $where_args[]    = $like;
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        $count_sql = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                array("SELECT COUNT(1) FROM {$table} s LEFT JOIN {$users_table} u ON s.user_id = u.ID {$where_sql}"),
                $where_args
            )
        );
        $total = (int) $wpdb->get_var($count_sql);

        $total_pages  = max(1, (int) ceil($total / $per_page));
        $current_page = min($page, $total_pages);
        $offset       = ($current_page - 1) * $per_page;

        $rows_sql = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                array("SELECT s.id, s.user_id, s.subscriber_name, s.phone_number, s.subscribed_at, s.unsubscribed_at, s.unsubscribed_reason, COALESCE(u.user_login, '') AS user_login FROM {$table} s LEFT JOIN {$users_table} u ON s.user_id = u.ID {$where_sql} ORDER BY s.id DESC LIMIT %d OFFSET %d"),
                $where_args,
                array($per_page, $offset)
            )
        );
        $rows = (array) $wpdb->get_results($rows_sql, ARRAY_A);

        foreach ($rows as &$row) {
            $row['subscriber_name'] = isset($row['subscriber_name']) ? (string) $row['subscriber_name'] : '';
            if ((int) $row['user_id'] > 0) {
                $row['subscriber_type'] = 'user';
                $row['display_name']    = isset($row['user_login']) && $row['user_login'] !== ''
                    ? (string) $row['user_login']
                    : ('user_id:' . (int) $row['user_id']);
            } else {
                $row['subscriber_type'] = 'standalone';
                $row['display_name']    = $row['subscriber_name'] !== '' ? $row['subscriber_name'] : 'Standalone Subscriber';
            }
        }
        unset($row);

        return array(
            'rows'         => $rows,
            'total'        => $total,
            'total_pages'  => $total_pages,
            'current_page' => $current_page,
        );
    }

    public static function is_subscribed(int $list_id, int $user_id): bool
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(1) FROM {$table} WHERE list_id = %d AND user_id = %d AND subscription_status = %s",
            $list_id,
            $user_id,
            'subscribed'
        ));

        return (int) $result > 0;
    }

    public static function is_unsubscribed(int $list_id, int $user_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE list_id = %d AND user_id = %d AND subscription_status = %s",
                $list_id,
                $user_id,
                'unsubscribed'
            )
        );

        return (int) $count > 0;
    }

    public static function is_standalone_subscriber(int $list_id, string $phone_number): bool
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE list_id = %d AND user_id = %d AND phone_number = %s AND subscription_status = %s",
                $list_id,
                0,
                trim($phone_number),
                'subscribed'
            )
        );

        return (int) $count > 0;
    }

    public static function remove_standalone_subscriber(int $list_id, string $phone_number): bool
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $result = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE list_id = %d AND user_id = %d AND phone_number = %s", $list_id, 0, trim($phone_number))
        );

        if ($result !== false) {
            Logger::info('Standalone subscriber removed from SMS list.', array('list_id' => $list_id, 'phone_number' => $phone_number));
        }

        return $result !== false;
    }

    /**
     * Bulk-convert standalone subscribers in a list to network user subscribers
     * whenever a matching phone number is found in the network users table.
     *
     * @return array<string,mixed> Summary with 'converted', 'not_found', 'errors' counts and 'details'.
     */
    public static function convert_standalone_to_users(int $list_id): array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE list_id = %d AND user_id = %d AND subscription_status IN ('subscribed', 'unsubscribed')",
                $list_id,
                0
            ),
            ARRAY_A
        );

        $converted = 0;
        $not_found = 0;
        $errors = 0;
        $details = array();
        $fallback_candidates = null;

        foreach ($rows as $row) {
            $phone = isset($row['phone_number']) ? trim((string) $row['phone_number']) : '';
            if ($phone === '') {
                $not_found++;
                continue;
            }

            $user_id = self::find_user_id_by_phone($phone, $fallback_candidates);
            if ($user_id <= 0) {
                $not_found++;
                continue;
            }

            $user = function_exists('get_userdata') ? get_userdata($user_id) : null;
            $display_name = '';
            if (is_object($user)) {
                if (!empty($user->display_name)) {
                    $display_name = (string) $user->display_name;
                } elseif (!empty($user->user_login)) {
                    $display_name = (string) $user->user_login;
                }
            }

            $status = isset($row['subscription_status']) ? (string) $row['subscription_status'] : 'subscribed';
            $reason = isset($row['unsubscribed_reason']) ? (string) $row['unsubscribed_reason'] : '';
            $original_name = isset($row['subscriber_name']) && $row['subscriber_name'] !== ''
                ? (string) $row['subscriber_name']
                : 'Standalone Subscriber';

            if (!self::remove_standalone_subscriber($list_id, $phone)) {
                $errors++;
                continue;
            }

            $add_result = self::add_subscriber($list_id, $user_id, $phone);
            if (empty($add_result['success'])) {
                $errors++;
                // Restore the standalone record so the subscriber is not lost,
                // preserving its original subscription status.
                $restore_result = self::add_standalone_subscriber($list_id, $original_name, $phone);
                if (!empty($restore_result['success']) && $status === 'unsubscribed') {
                    self::unsubscribe_standalone($list_id, $phone, $reason);
                }
                continue;
            }

            if ($display_name !== '') {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET subscriber_name = %s WHERE list_id = %d AND user_id = %d",
                        $display_name,
                        $list_id,
                        $user_id
                    )
                );
            }

            if ($status === 'unsubscribed') {
                self::unsubscribe_user($list_id, $user_id, $reason);
            }

            $converted++;
            $details[] = array(
                'phone_number' => $phone,
                'user_id'      => $user_id,
                'display_name' => $display_name,
            );

            Logger::info('Standalone SMS subscriber converted to network user.', array(
                'list_id'      => $list_id,
                'user_id'      => $user_id,
                'phone_number' => $phone,
            ));
        }

        return array(
            'converted' => $converted,
            'not_found' => $not_found,
            'errors'    => $errors,
            'details'   => $details,
        );
    }

    public static function unsubscribe_standalone(int $list_id, string $phone_number, string $reason = ''): bool
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $phone_number = trim($phone_number);
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT subscriber_name FROM {$table} WHERE list_id = %d AND user_id = %d AND phone_number = %s", $list_id, 0, $phone_number),
            ARRAY_A
        );
        $now = self::current_time_mysql();

        if (is_array($existing)) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET subscription_status = %s, unsubscribed_at = %s, unsubscribed_reason = %s WHERE list_id = %d AND user_id = %d AND phone_number = %s",
                    'unsubscribed',
                    $now,
                    sanitize_text_field($reason),
                    $list_id,
                    0,
                    $phone_number
                )
            );
        } else {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (list_id, user_id, subscriber_name, phone_number, subscription_status, subscribed_at, unsubscribed_at, unsubscribed_reason) VALUES (%d, %d, %s, %s, %s, %s, %s, %s)",
                    $list_id,
                    0,
                    '',
                    $phone_number,
                    'unsubscribed',
                    $now,
                    $now,
                    sanitize_text_field($reason)
                )
            );
        }

        if ($result !== false) {
            Logger::info('Standalone subscriber unsubscribed from SMS list.', array('list_id' => $list_id, 'phone_number' => $phone_number, 'reason' => $reason));
        }

        return $result !== false;
    }

    public static function resubscribe_standalone(int $list_id, string $phone_number): bool
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET subscription_status = %s, subscribed_at = %s, unsubscribed_at = %s, unsubscribed_reason = %s WHERE list_id = %d AND user_id = %d AND phone_number = %s",
                'subscribed',
                self::current_time_mysql(),
                null,
                '',
                $list_id,
                0,
                trim($phone_number)
            )
        );

        if ($result !== false) {
            Logger::info('Standalone subscriber restored to subscribed in SMS list.', array('list_id' => $list_id, 'phone_number' => $phone_number));
        }

        return $result !== false;
    }

    /**
     * Find a subscriber record by phone number within a given list, regardless
     * of whether they are a WordPress user or a standalone subscriber.
     *
     * @param int    $list_id
     * @param string $phone
     * @return array<string,mixed>|null
     */
    public static function get_subscriber_by_phone_and_list(int $list_id, string $phone): ?array
    {
        global $wpdb;

        $phone = trim($phone);
        if ($list_id <= 0 || $phone === '') {
            return null;
        }

        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, list_id, user_id, subscriber_name, phone_number, subscription_status, subscribed_at, unsubscribed_at, unsubscribed_reason"
                    . " FROM {$table} WHERE list_id = %d AND phone_number = %s ORDER BY id DESC LIMIT 1",
                $list_id,
                $phone
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Unsubscribe a subscriber identified by phone number within a given list.
     *
     * @param int    $list_id
     * @param string $phone
     * @param string $reason
     * @return bool True on success, false if not found or on error.
     */
    public static function unsubscribe_by_phone_and_list(int $list_id, string $phone, string $reason = ''): bool
    {
        global $wpdb;

        $subscriber = self::get_subscriber_by_phone_and_list($list_id, $phone);
        if (!$subscriber) {
            return false;
        }

        $table  = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $reason = function_exists('sanitize_text_field') ? sanitize_text_field($reason) : $reason;

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET subscription_status = %s, unsubscribed_at = %s, unsubscribed_reason = %s WHERE id = %d",
                'unsubscribed',
                self::current_time_mysql(),
                $reason,
                (int) $subscriber['id']
            )
        );

        if ($result !== false) {
            Logger::info('SMS subscriber unsubscribed via phone/list lookup.', array(
                'list_id'      => $list_id,
                'phone_number' => $phone,
                'reason'       => $reason,
            ));
        }

        return $result !== false;
    }

    /**
     * Update an existing subscriber's phone number (and name, for standalone
     * subscribers) identified by their subscriber row id.
     *
     * @param int         $subscriber_id Primary key of the row in mnem_sms_list_subscribers.
     * @param string      $phone_number  New phone number.
     * @param string      $name          New subscriber name (standalone subscribers only).
     * @param string|null $country_code  Optional explicit country hint (ISO-2).
     * @return array<string,mixed>
     */
    public static function update_subscriber(int $subscriber_id, string $phone_number, string $name = '', ?string $country_code = null): array
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';

        if ($subscriber_id <= 0) {
            return self::build_update_response(false, null, 'Invalid subscriber.', 'error');
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT id, list_id, user_id, subscriber_name FROM {$table} WHERE id = %d", $subscriber_id),
            ARRAY_A
        );

        if (!is_array($existing)) {
            return self::build_update_response(false, null, 'Subscriber not found.', 'error');
        }

        $list_id       = isset($existing['list_id']) ? (int) $existing['list_id'] : 0;
        $user_id       = isset($existing['user_id']) ? (int) $existing['user_id'] : 0;
        $is_standalone = $user_id === 0;

        $phone_number = trim($phone_number);
        if ($phone_number === '') {
            return self::build_update_response(false, null, 'Phone number is required.', 'error');
        }

        $name = sanitize_text_field($name);
        if ($is_standalone && $name === '') {
            return self::build_update_response(false, null, 'Subscriber name is required.', 'error');
        }

        $validation = self::validate_phone_number($phone_number, $country_code);

        // Ambiguous country: reject with explanation.
        if (!$validation['valid'] && isset($validation['reason_code']) && $validation['reason_code'] === 'ambiguous_country') {
            return self::build_update_response(false, $validation, 'Phone number is ambiguous: multiple countries could match.', 'error');
        }

        // Unsupported country: reject.
        if ($validation['valid']) {
            $allowed = SmsSettings::get_allowed_countries();
            if (!empty($allowed)) {
                $detected_country = isset($validation['country_iso2']) ? (string) $validation['country_iso2'] : '';
                if ($detected_country !== '' && !in_array($detected_country, $allowed, true)) {
                    return self::build_update_response(false, $validation, sprintf('Phone number country %s is not in the allowed countries list.', $detected_country), 'error');
                }
            }
        }

        // Invalid format: reject.
        if (empty($validation['valid'])) {
            return self::build_update_response(false, $validation, 'Phone number is invalid.', 'error');
        }

        $formatted_phone = (string) $validation['formatted'];

        // Blocked number: reject.
        if (InvalidPhoneNumbers::is_blocked($formatted_phone)) {
            return self::build_update_response(false, array(
                'valid'     => true,
                'formatted' => $formatted_phone,
                'error'     => '',
            ), 'Phone number has been blocked from subscribing.', 'error');
        }

        // Cross-subscriber duplicate check (exclude the record being edited).
        if (!SmsSettings::allow_duplicate_numbers()) {
            $duplicate = self::find_subscriber_by_phone($list_id, $formatted_phone, $subscriber_id);
            if (is_array($duplicate)) {
                return self::build_update_response(false, $validation, 'Phone number is already subscribed to this list.', 'error');
            }
        }

        $set_clauses = array('phone_number = %s');
        $set_args    = array($formatted_phone);
        if ($is_standalone) {
            $set_clauses[] = 'subscriber_name = %s';
            $set_args[]    = $name;
        }
        $set_args[] = $subscriber_id;

        $result = $wpdb->query(
            call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    array("UPDATE {$table} SET " . implode(', ', $set_clauses) . ' WHERE id = %d'),
                    $set_args
                )
            )
        );

        if ($result !== false) {
            Logger::info('SMS subscriber updated.', array(
                'list_id'       => $list_id,
                'subscriber_id' => $subscriber_id,
                'user_id'       => $user_id,
                'admin_id'      => get_current_user_id(),
            ));
        }

        return self::build_update_response($result !== false, $validation, $result !== false ? 'Subscriber updated successfully.' : 'Failed to update subscriber.', $result !== false ? 'updated' : 'error');
    }

    /**
     * @param array<string,mixed>|null $validation
     * @return array<string,mixed>
     */
    private static function build_update_response(bool $success, ?array $validation, string $message, string $action): array
    {
        return array(
            'success'         => $success,
            'action'          => $action,
            'message'         => $message,
            'phone_valid'     => $validation !== null ? !empty($validation['valid']) : false,
            'phone_error'     => $validation !== null && isset($validation['error']) ? (string) $validation['error'] : '',
            'formatted_phone' => $validation !== null && isset($validation['formatted']) ? (string) $validation['formatted'] : '',
        );
    }

    public static function import_from_csv(int $list_id, string $csv_content)
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv_content);
        $added_users = 0;
        $added_standalone = 0;
        $added = 0;
        $skipped = 0;
        $invalid = 0;
        $duplicates = 0;
        $errors = array();
        $invalid_numbers = array();

        foreach ((array) $lines as $line) {
            $identifier = trim((string) $line);
            if ($identifier === '') {
                continue;
            }

            // Support "user_id:phone_number" or "username:phone_number" or just user_id/username
            $phone_number = '';
            if (strpos($identifier, ':') !== false) {
                $parts = explode(':', $identifier, 2);
                $identifier = trim($parts[0]);
                $phone_number = trim($parts[1]);
            }

            $user_id = self::resolve_user_id($identifier);
            if ($user_id <= 0 && $phone_number !== '' && $identifier !== '') {
                $result = self::add_standalone_subscriber($list_id, $identifier, $phone_number);
                if (empty($result['success'])) {
                    ++$skipped;
                    if (!empty($result['phone_valid']) && !empty($result['is_duplicate'])) {
                        ++$duplicates;
                    }
                    if (empty($result['phone_valid'])) {
                        ++$invalid;
                        $invalid_numbers[] = $phone_number;
                    }
                    $errors[] = $identifier . ' - ' . (isset($result['message']) ? $result['message'] : 'unable to add');
                    continue;
                }
                if (!empty($result['added'])) {
                    ++$added;
                    ++$added_standalone;
                } else {
                    ++$skipped;
                    $errors[] = $identifier . ' - already in list';
                }
                continue;
            }

            if ($user_id <= 0) {
                ++$skipped;
                $errors[] = $identifier . ' - user not found';
                continue;
            }

            $result = self::add_subscriber($list_id, $user_id, $phone_number);
            if (empty($result['success'])) {
                ++$skipped;
                if (!empty($result['phone_valid']) && !empty($result['is_duplicate'])) {
                    ++$duplicates;
                }
                if (empty($result['phone_valid'])) {
                    ++$invalid;
                    $invalid_numbers[] = $phone_number;
                }
                $errors[] = $identifier . ' - ' . (isset($result['message']) ? $result['message'] : 'unable to add');
                continue;
            }
            if (!empty($result['added'])) {
                ++$added;
                ++$added_users;
            } else {
                ++$skipped;
                $errors[] = $identifier . ' - already in list';
            }
        }

        return array(
            'added' => $added,
            'added_users' => $added_users,
            'added_standalone' => $added_standalone,
            'skipped' => $skipped,
            'invalid' => $invalid,
            'duplicates' => $duplicates,
            'invalid_numbers' => $invalid_numbers,
            'errors' => $errors,
        );
    }

    public static function get_resolved_phone_number(int $user_id): string
    {
        return self::resolve_phone_number($user_id);
    }

    public static function export_to_csv(int $list_id)
    {
        $subscribers = self::get_all_subscribers_mixed($list_id, 100000, 0);
        $rows = array('type,user_id,username,subscriber_name,phone_number,subscribed_at');

        foreach ($subscribers as $subscriber) {
            $type = (int) $subscriber['user_id'] === 0 ? 'standalone' : 'user';
            $rows[] = implode(',', array(
                $type,
                (int) $subscriber['user_id'],
                self::csv_escape(isset($subscriber['user_login']) ? (string) $subscriber['user_login'] : ''),
                self::csv_escape(isset($subscriber['subscriber_name']) ? (string) $subscriber['subscriber_name'] : ''),
                self::csv_escape(isset($subscriber['phone_number']) ? (string) $subscriber['phone_number'] : ''),
                self::csv_escape(isset($subscriber['subscribed_at']) ? (string) $subscriber['subscribed_at'] : ''),
            ));
        }

        return implode("\n", $rows);
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_delete_impact(int $id, ?array $list = null): array
    {
        global $wpdb;

        $counts = array(
            'list' => is_array($list) ? 1 : (self::get($id) ? 1 : 0),
            'subscribers' => 0,
            'invalid_phones' => 0,
            'queue_items' => 0,
            'logs' => 0,
            'mapping_rows' => 0,
        );
        $notes = array();

        $subs_table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $counts['subscribers'] = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(1) FROM {$subs_table} WHERE list_id = %d", $id)
        );

        $invalid_table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        if (self::table_exists($invalid_table)) {
            $counts['invalid_phones'] = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(1) FROM {$invalid_table} WHERE list_id = %d", $id)
            );
        }

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        if (self::table_exists($queue_table) && self::table_has_column($queue_table, 'list_id')) {
            $counts['queue_items'] = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} WHERE list_id = %d", $id)
            );
        } else {
            $notes[] = 'Queue table does not currently store SMS list_id references.';
        }

        $mapping_table = $wpdb->base_prefix . 'mnem_sms_campaign_list_map';
        if (self::table_exists($mapping_table)) {
            $counts['mapping_rows'] = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(1) FROM {$mapping_table} WHERE list_id = %d", $id)
            );
        }

        $counts['logs'] = count(self::get_sms_log_ids_for_list($id));

        return array(
            'counts' => $counts,
            'notes' => $notes,
            'total_related' => array_sum($counts),
        );
    }

    /**
     * @return array<string,mixed>
     */
    public static function cleanup_orphaned_records(): array
    {
        global $wpdb;

        $result = array(
            'found' => 0,
            'cleaned' => 0,
            'errors' => array(),
            'details' => array(
                'subscribers' => 0,
                'invalid_phones' => 0,
                'queue_items' => 0,
                'logs' => 0,
            ),
            'notes' => array(),
        );

        $lists_table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $subs_table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $invalid_table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $logs_table = $wpdb->base_prefix . 'mnem_logs';
        $orphaned_log_ids = self::get_orphaned_sms_log_ids();

        $queries = array(
            'subscribers' => "DELETE s FROM {$subs_table} s LEFT JOIN {$lists_table} l ON l.id = s.list_id WHERE l.id IS NULL",
            'invalid_phones' => self::table_exists($invalid_table)
                ? "DELETE p FROM {$invalid_table} p LEFT JOIN {$lists_table} l ON l.id = p.list_id WHERE p.list_id > 0 AND l.id IS NULL"
                : '',
            'queue_items' => self::table_exists($queue_table) && self::table_has_column($queue_table, 'list_id')
                ? "DELETE q FROM {$queue_table} q LEFT JOIN {$lists_table} l ON l.id = q.list_id WHERE q.list_id > 0 AND l.id IS NULL"
                : '',
            'logs' => self::table_exists($logs_table) && !empty($orphaned_log_ids)
                ? self::prepare_delete_ids_query($logs_table, $orphaned_log_ids)
                : '',
        );

        if (!self::table_exists($queue_table) || !self::table_has_column($queue_table, 'list_id')) {
            $result['notes'][] = 'Queue orphan cleanup skipped because mnem_queue has no list_id column.';
        }

        if ($wpdb->query('START TRANSACTION') === false) {
            $result['errors'][] = 'transaction_start_failed';

            return $result;
        }

        foreach ($queries as $key => $query) {
            if ($query === '') {
                continue;
            }

            $deleted = $wpdb->query($query);
            if ($deleted === false) {
                $wpdb->query('ROLLBACK');
                $result['errors'][] = $key . '_cleanup_failed';
                Logger::error('SMS orphan cleanup rolled back.', array(
                    'failed_step' => $key,
                    'errors' => $result['errors'],
                ));

                return $result;
            }

            $result['details'][$key] = (int) $deleted;
            $result['cleaned'] += (int) $deleted;
        }

        $result['found'] = $result['cleaned'];

        if ($wpdb->query('COMMIT') === false) {
            $wpdb->query('ROLLBACK');
            $result['errors'][] = 'transaction_commit_failed';

            return $result;
        }

        Logger::info('SMS orphan cleanup completed.', array(
            'found' => $result['found'],
            'cleaned' => $result['cleaned'],
            'details' => $result['details'],
            'notes' => $result['notes'],
        ));

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public static function check_data_integrity(): array
    {
        $issues = array();
        $notes = array();

        $orphaned_subscribers = self::count_orphaned_subscribers();
        if ($orphaned_subscribers > 0) {
            $issues[] = array(
                'type' => 'orphaned_subscribers',
                'title' => 'Orphaned SMS subscribers',
                'count' => $orphaned_subscribers,
                'description' => sprintf('Found %d SMS subscriber records with missing list references.', $orphaned_subscribers),
            );
        }

        $orphaned_invalid_phones = self::count_orphaned_invalid_phone_numbers();
        if ($orphaned_invalid_phones > 0) {
            $issues[] = array(
                'type' => 'orphaned_invalid_phone_numbers',
                'title' => 'Orphaned invalid phone numbers',
                'count' => $orphaned_invalid_phones,
                'description' => sprintf('Found %d invalid phone number records with missing SMS lists.', $orphaned_invalid_phones),
            );
        }

        $orphaned_queue = self::count_orphaned_queue_items();
        if ($orphaned_queue > 0) {
            $issues[] = array(
                'type' => 'orphaned_queue_items',
                'title' => 'Orphaned SMS queue items',
                'count' => $orphaned_queue,
                'description' => sprintf('Found %d queue items referencing missing SMS lists.', $orphaned_queue),
            );
        } elseif (self::queue_table_has_no_list_id_note() !== '') {
            $notes[] = self::queue_table_has_no_list_id_note();
        }

        $orphaned_logs = count(self::get_orphaned_sms_log_ids());
        if ($orphaned_logs > 0) {
            $issues[] = array(
                'type' => 'orphaned_sms_logs',
                'title' => 'Orphaned SMS logs',
                'count' => $orphaned_logs,
                'description' => sprintf('Found %d SMS log entries referencing missing SMS lists.', $orphaned_logs),
            );
        }

        $invalid_standalone_subscribers = self::count_invalid_standalone_subscribers();
        if ($invalid_standalone_subscribers > 0) {
            $issues[] = array(
                'type' => 'invalid_standalone_subscribers',
                'title' => 'Invalid standalone subscribers',
                'count' => $invalid_standalone_subscribers,
                'description' => sprintf('Found %d standalone SMS subscribers missing a name or phone number.', $invalid_standalone_subscribers),
            );
        }

        return array(
            'issues' => $issues,
            'notes' => $notes,
            'issues_found' => count($issues),
            'orphaned_records' => array_sum(array_map(static function ($issue) {
                return isset($issue['count']) ? (int) $issue['count'] : 0;
            }, $issues)),
        );
    }

    /**
     * @return array<string,int>
     */
    public static function get_data_integrity_overview(): array
    {
        global $wpdb;

        $lists_table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $subs_table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $invalid_table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $integrity = self::check_data_integrity();

        return array(
            'total_lists' => (int) $wpdb->get_var("SELECT COUNT(1) FROM {$lists_table}"),
            'total_subscribers' => (int) $wpdb->get_var("SELECT COUNT(1) FROM {$subs_table}"),
            'total_standalone_subscribers' => (int) $wpdb->get_var("SELECT COUNT(1) FROM {$subs_table} WHERE user_id = 0"),
            'total_user_based_subscribers' => (int) $wpdb->get_var("SELECT COUNT(1) FROM {$subs_table} WHERE user_id > 0"),
            'total_invalid_phone_numbers' => self::table_exists($invalid_table)
                ? (int) $wpdb->get_var("SELECT COUNT(1) FROM {$invalid_table}")
                : 0,
            'orphaned_records' => isset($integrity['orphaned_records']) ? (int) $integrity['orphaned_records'] : 0,
        );
    }

    public static function export_cleanup_report(): string
    {
        $integrity = self::check_data_integrity();
        $lines = array(
            'SMS Data Integrity Report',
            'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC',
            '',
        );

        if (!empty($integrity['issues'])) {
            foreach ((array) $integrity['issues'] as $issue) {
                $lines[] = sprintf(
                    '- %s: %d',
                    isset($issue['title']) ? (string) $issue['title'] : 'Issue',
                    isset($issue['count']) ? (int) $issue['count'] : 0
                );
                if (!empty($issue['description'])) {
                    $lines[] = '  ' . (string) $issue['description'];
                }
            }
        } else {
            $lines[] = 'No SMS data integrity issues found.';
        }

        if (!empty($integrity['notes'])) {
            $lines[] = '';
            $lines[] = 'Notes:';
            foreach ((array) $integrity['notes'] as $note) {
                $lines[] = '- ' . (string) $note;
            }
        }

        return implode("\n", $lines);
    }

    private static function get_users_by_status(int $list_id, string $status, int $limit, int $offset)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE list_id = %d AND subscription_status = %s ORDER BY id DESC LIMIT %d OFFSET %d",
                $list_id,
                $status,
                max(1, $limit),
                max(0, $offset)
            ),
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row['subscriber_name'] = isset($row['subscriber_name']) ? (string) $row['subscriber_name'] : '';
            if ((int) $row['user_id'] > 0) {
                $user = function_exists('get_userdata') ? get_userdata((int) $row['user_id']) : null;
                $row['user_login'] = is_object($user) && isset($user->user_login) ? (string) $user->user_login : '';
                $row['subscriber_type'] = 'user';
                $row['display_name'] = $row['user_login'] !== '' ? $row['user_login'] : ('user_id:' . (int) $row['user_id']);
            } else {
                $row['user_login'] = '';
                $row['subscriber_type'] = 'standalone';
                $row['display_name'] = $row['subscriber_name'] !== '' ? $row['subscriber_name'] : 'Standalone Subscriber';
            }
        }
        unset($row);

        return $rows;
    }

    private static function get_standalone_by_status(int $list_id, string $status, int $limit, int $offset): array
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE list_id = %d AND user_id = %d AND subscription_status = %s ORDER BY id DESC LIMIT %d OFFSET %d",
                $list_id,
                0,
                $status,
                max(1, $limit),
                max(0, $offset)
            ),
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row['subscriber_name'] = isset($row['subscriber_name']) ? (string) $row['subscriber_name'] : '';
            $row['user_login'] = '';
            $row['subscriber_type'] = 'standalone';
            $row['display_name'] = $row['subscriber_name'] !== '' ? $row['subscriber_name'] : 'Standalone Subscriber';
        }
        unset($row);

        return $rows;
    }

    private static function resolve_user_id(string $identifier)
    {
        if (ctype_digit($identifier)) {
            return (int) $identifier;
        }

        if (function_exists('get_users')) {
            $users = get_users(array(
                'search' => $identifier,
                'search_columns' => array('user_login'),
                'number' => 1,
                'fields' => array('ID'),
            ));
            $user = isset($users[0]) ? $users[0] : null;
            if (is_array($user) && isset($user['ID'])) {
                return (int) $user['ID'];
            }
            if (is_object($user) && isset($user->ID)) {
                return (int) $user->ID;
            }
        }

        return 0;
    }

    private static function resolve_phone_number(int $user_id): string
    {
        foreach (array('phone_number', 'phone', 'mobile') as $meta_key) {
            if (function_exists('get_user_meta')) {
                $value = get_user_meta($user_id, $meta_key, true);
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * Search the network users table for a user whose phone-related user meta
     * matches the given phone number. Falls back to a digits-only comparison
     * so differently formatted numbers (spaces, dashes, missing '+') can still
     * be matched.
     *
     * @param array<int,array<string,mixed>>|null $fallback_candidates Reference to a
     *        cache of usermeta rows used by the last-resort fallback below. Pass the
     *        same variable across repeated calls within one operation (e.g. a bulk
     *        conversion loop) so the fallback query only runs once per operation
     *        instead of once per phone number.
     */
    private static function find_user_id_by_phone(string $phone_number, ?array &$fallback_candidates = null): int
    {
        global $wpdb;

        $phone_number = trim($phone_number);
        if ($phone_number === '') {
            return 0;
        }

        $usermeta_table = isset($wpdb->usermeta) ? $wpdb->usermeta : $wpdb->base_prefix . 'usermeta';
        $meta_keys_sql = "'phone_number', 'phone', 'mobile', 'billing_phone', 'shipping_phone'";

        $user_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$usermeta_table} WHERE meta_key IN ({$meta_keys_sql}) AND meta_value = %s LIMIT 1",
                $phone_number
            )
        );

        if ($user_id > 0) {
            return $user_id;
        }

        $normalized_target = self::normalize_phone_digits($phone_number);
        if ($normalized_target === '') {
            return 0;
        }

        // Normalize common formatting (spaces, dashes, parentheses, '+') in SQL first
        // so most differently-formatted matches are found without loading rows into PHP.
        $user_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$usermeta_table} WHERE meta_key IN ({$meta_keys_sql})"
                    . " AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(meta_value, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') = %s LIMIT 1",
                $normalized_target
            )
        );

        if ($user_id > 0) {
            return $user_id;
        }

        // Last-resort fallback for unusual formatting (dots, leading zeros, etc).
        // Bounded by PHONE_MATCH_FALLBACK_LIMIT to avoid loading the entire
        // network usermeta table into memory on large multisite installs. The
        // candidate set is cached in $fallback_candidates by the caller so it is
        // only fetched once per bulk operation rather than once per phone number.
        if ($fallback_candidates === null) {
            $fallback_candidates = (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT user_id, meta_value FROM {$usermeta_table} WHERE meta_key IN ({$meta_keys_sql}) LIMIT %d",
                    self::PHONE_MATCH_FALLBACK_LIMIT
                ),
                ARRAY_A
            );
        }

        foreach ($fallback_candidates as $candidate) {
            $candidate_value = isset($candidate['meta_value']) ? (string) $candidate['meta_value'] : '';
            if ($candidate_value === '') {
                continue;
            }
            if (self::normalize_phone_digits($candidate_value) === $normalized_target) {
                return isset($candidate['user_id']) ? (int) $candidate['user_id'] : 0;
            }
        }

        return 0;
    }

    private static function normalize_phone_digits(string $phone_number): string
    {
        return (string) preg_replace('/\D+/', '', $phone_number);
    }

    private static function csv_escape(string $value)
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private static function current_time_mysql()
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }

    private static function count_orphaned_subscribers(): int
    {
        global $wpdb;

        $lists_table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $subs_table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';

        return (int) $wpdb->get_var(
            "SELECT COUNT(1) FROM {$subs_table} s LEFT JOIN {$lists_table} l ON l.id = s.list_id WHERE l.id IS NULL"
        );
    }

    private static function count_orphaned_invalid_phone_numbers(): int
    {
        global $wpdb;

        $lists_table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $invalid_table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        if (!self::table_exists($invalid_table)) {
            return 0;
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(1) FROM {$invalid_table} p LEFT JOIN {$lists_table} l ON l.id = p.list_id WHERE p.list_id > 0 AND l.id IS NULL"
        );
    }

    private static function count_orphaned_queue_items(): int
    {
        global $wpdb;

        $lists_table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        if (!self::table_exists($queue_table) || !self::table_has_column($queue_table, 'list_id')) {
            return 0;
        }

        return (int) $wpdb->get_var(
            "SELECT COUNT(1) FROM {$queue_table} q LEFT JOIN {$lists_table} l ON l.id = q.list_id WHERE q.list_id > 0 AND l.id IS NULL"
        );
    }

    private static function count_invalid_standalone_subscribers(): int
    {
        global $wpdb;

        $subs_table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';

        return (int) $wpdb->get_var(
            "SELECT COUNT(1) FROM {$subs_table} WHERE user_id = 0 AND (TRIM(COALESCE(phone_number, '')) = '' OR TRIM(COALESCE(subscriber_name, '')) = '')"
        );
    }

    /**
     * @return array<int,int>
     */
    private static function get_sms_log_ids_for_list(int $list_id): array
    {
        global $wpdb;

        $logs_table = $wpdb->base_prefix . 'mnem_logs';
        if (!self::table_exists($logs_table)) {
            return array();
        }

        $list_id = (int) $list_id;
        $context_like_numeric = '%"list_id":' . $list_id . '%';
        $context_like_string = '%"list_id":"' . $list_id . '"%';
        $query = $wpdb->prepare(
            "SELECT id FROM {$logs_table} WHERE (message LIKE %s OR message LIKE %s) AND (context LIKE %s OR context LIKE %s)",
            '%SMS%',
            '%phone%',
            $context_like_numeric,
            $context_like_string
        );

        if (method_exists($wpdb, 'get_col')) {
            return array_map('intval', (array) $wpdb->get_col($query));
        }

        $rows = (array) $wpdb->get_results($query, ARRAY_A);

        return array_values(array_map(static function ($row) {
            return isset($row['id']) ? (int) $row['id'] : 0;
        }, $rows));
    }

    /**
     * @return array<int,int>
     */
    private static function get_orphaned_sms_log_ids(): array
    {
        global $wpdb;

        $logs_table = $wpdb->base_prefix . 'mnem_logs';
        if (!self::table_exists($logs_table)) {
            return array();
        }

        $query = $wpdb->prepare(
            "SELECT id, context FROM {$logs_table} WHERE (message LIKE %s OR message LIKE %s) AND context LIKE %s ORDER BY id DESC LIMIT %d",
            '%SMS%',
            '%phone%',
            '%"list_id":%',
            self::ORPHANED_SMS_LOG_SCAN_LIMIT
        );
        $rows = (array) $wpdb->get_results($query, ARRAY_A);
        $ids = array();
        $log_list_ids = array();

        foreach ($rows as $row) {
            $context = isset($row['context']) ? json_decode((string) $row['context'], true) : array();
            $list_id = is_array($context) && isset($context['list_id']) ? (int) $context['list_id'] : 0;
            if ($list_id > 0) {
                $log_id = isset($row['id']) ? (int) $row['id'] : 0;
                $log_list_ids[$log_id] = $list_id;
            }
        }

        $existing_list_ids = self::get_existing_list_ids(array_values($log_list_ids));
        foreach ($log_list_ids as $log_id => $list_id) {
            if (!in_array($list_id, $existing_list_ids, true)) {
                $ids[] = (int) $log_id;
            }
        }

        return array_values(array_filter($ids));
    }

    private static function queue_table_has_no_list_id_note(): string
    {
        global $wpdb;

        $queue_table = $wpdb->base_prefix . 'mnem_queue';

        if (!self::table_exists($queue_table) || self::table_has_column($queue_table, 'list_id')) {
            return '';
        }

        return 'Queue integrity checks skipped because mnem_queue does not yet have an SMS list_id column.';
    }

    /**
     * @param array<int,int> $list_ids
     * @return array<int,int>
     */
    private static function get_existing_list_ids(array $list_ids): array
    {
        global $wpdb;

        $list_ids = array_values(array_unique(array_filter(array_map('intval', $list_ids))));
        if (empty($list_ids)) {
            return array();
        }

        $table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $placeholders = implode(', ', array_fill(0, count($list_ids), '%d'));
        $query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                array("SELECT id FROM {$table} WHERE id IN ({$placeholders})"),
                $list_ids
            )
        );

        if (method_exists($wpdb, 'get_col')) {
            return array_values(array_map('intval', (array) $wpdb->get_col($query)));
        }

        $rows = (array) $wpdb->get_results($query, ARRAY_A);

        return array_values(array_filter(array_map(static function ($row) {
            return isset($row['id']) ? (int) $row['id'] : 0;
        }, $rows)));
    }

    private static function table_exists(string $table): bool
    {
        global $wpdb;

        if (!method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
            return false;
        }

        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function table_has_column(string $table, string $column): bool
    {
        global $wpdb;

        if (!self::table_exists($table) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
            return false;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(1) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND COLUMN_NAME = %s',
            $table,
            $column
        )) > 0;
    }

    /**
     * @param array<int,int> $ids
     */
    private static function prepare_delete_ids_query(string $table, array $ids): string
    {
        global $wpdb;

        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return '';
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));

        return call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                array("DELETE FROM {$table} WHERE id IN ({$placeholders})"),
                $ids
            )
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_add_response(bool $success, bool $added, bool $is_duplicate, ?int $duplicate_user_id, array $validation, string $message, string $action = '', ?int $subscriber_id = null): array
    {
        if ($action === '') {
            if (!$success) {
                $action = 'error';
            } elseif ($added) {
                $action = 'added';
            } elseif ($is_duplicate) {
                $action = 'duplicate';
            } else {
                $action = 'unknown';
            }
        }

        return array(
            'success'           => $success,
            'action'            => $action,
            'message'           => $message,
            'phone_valid'       => !empty($validation['valid']),
            'phone_error'       => isset($validation['error']) ? (string) $validation['error'] : '',
            'is_duplicate'      => $is_duplicate,
            'duplicate_user_id' => $duplicate_user_id,
            'duplicate'         => $is_duplicate,
            'existing_user_id'  => $duplicate_user_id,
            'added'             => $added,
            'formatted_phone'   => isset($validation['formatted']) ? (string) $validation['formatted'] : '',
            'subscriber_id'     => $subscriber_id,
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function find_subscriber_by_phone(int $list_id, string $phone_number, int $exclude_id = 0)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';

        $where_sql  = "list_id = %d AND phone_number = %s AND subscription_status = %s";
        $where_args = array($list_id, $phone_number, 'subscribed');

        if ($exclude_id > 0) {
            $where_sql .= ' AND id <> %d';
            $where_args[] = $exclude_id;
        }

        $row = $wpdb->get_row(
            call_user_func_array(
                array($wpdb, 'prepare'),
                array_merge(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    array("SELECT id, user_id, phone_number, subscription_status FROM {$table} WHERE {$where_sql} LIMIT 1"),
                    $where_args
                )
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Resolve and normalise a phone number through the configured validation layer.
     *
     * Handles single-country, multi-country, and validation-disabled modes.
     * Always returns a validation array — callers are responsible for acting on
     * the 'valid', 'reason_code', and related fields.
     *
     * @param string      $phone_number Raw phone number string.
     * @param string|null $country_code Optional explicit country hint (ISO-2).
     * @return array<string,mixed>
     */
    private static function validate_phone_number(string $phone_number, ?string $country_code): array
    {
        if (!SmsSettings::is_phone_validation_enabled()) {
            return array(
                'valid'                => true,
                'formatted'            => trim($phone_number),
                'error'                => '',
                'country_iso2'         => null,
                'country_calling_code' => null,
                'national_number'      => null,
                'input_format'         => 'unknown',
                'ambiguous'            => false,
                'possible_countries'   => array(),
                'reason_code'          => null,
            );
        }

        $legacy_country = SmsSettings::get_validation_country_code();

        if (SmsSettings::is_multi_country_mode() || $country_code !== null) {
            $default_country = SmsSettings::get_default_validation_country();
            return PhoneValidator::validate_with_country_hint($phone_number, $country_code, $default_country);
        }

        $base = PhoneValidator::validate_phone_number($phone_number, $legacy_country);
        return array_merge(array(
            'country_iso2'         => $legacy_country,
            'country_calling_code' => null,
            'national_number'      => null,
            'input_format'         => strpos($phone_number, '+') === 0 ? 'e164' : 'national',
            'ambiguous'            => false,
            'possible_countries'   => array(),
            'reason_code'          => $base['valid'] ? null : 'format_invalid',
        ), $base);
    }

    private static function maybe_auto_block_invalid_number(string $phone_number): void
    {
        $threshold = SmsSettings::get_auto_block_failed_attempts();
        if ($threshold < 1) {
            return;
        }

        $count = InvalidPhoneNumbers::get_invalid_count(null, 'all', array(
            'search' => PhoneValidator::format_phone_number($phone_number, SmsSettings::get_validation_country_code()) ?: $phone_number,
        ));

        if ($count >= $threshold) {
            InvalidPhoneNumbers::block_number($phone_number);
        }
    }
}
