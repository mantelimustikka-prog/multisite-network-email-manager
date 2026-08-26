<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmsSubscriberLists
{
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

    public static function delete(int $id)
    {
        global $wpdb;
        $lists_table = $wpdb->base_prefix . 'mnem_sms_subscriber_lists';
        $subs_table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $wpdb->query($wpdb->prepare("DELETE FROM {$subs_table} WHERE list_id = %d", $id));
        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$lists_table} WHERE id = %d", $id));

        if ($result !== false) {
            Logger::info('SMS subscriber list deleted.', array('list_id' => $id, 'user_id' => get_current_user_id()));
        }

        return $result !== false;
    }

    /**
     * @return array<string,mixed>
     */
    public static function add_subscriber(int $list_id, int $user_id, string $phone_number = '')
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $country_code = SmsSettings::get_validation_country_code();

        if ($phone_number === '') {
            $phone_number = self::resolve_phone_number($user_id);
        }

        $validation = SmsSettings::is_phone_validation_enabled()
            ? PhoneValidator::validate_phone_number($phone_number, $country_code)
            : array('valid' => true, 'formatted' => trim($phone_number), 'error' => '');

        if (empty($validation['valid'])) {
            InvalidPhoneNumbers::log_invalid_number($phone_number, 'format_invalid', $list_id, $user_id);
            self::maybe_auto_block_invalid_number($phone_number);

            return self::build_add_response(false, false, false, null, $validation, 'Phone number is invalid.');
        }

        $formatted_phone = (string) $validation['formatted'];

        if (InvalidPhoneNumbers::is_blocked($formatted_phone)) {
            return self::build_add_response(false, false, false, null, array(
                'valid' => true,
                'formatted' => $formatted_phone,
                'error' => '',
            ), 'Phone number has been blocked from subscribing.');
        }

        if (!SmsSettings::allow_duplicate_numbers()) {
            $duplicate = self::find_subscriber_by_phone($list_id, $formatted_phone);
            if (is_array($duplicate) && isset($duplicate['user_id']) && (int) $duplicate['user_id'] !== $user_id) {
                InvalidPhoneNumbers::log_invalid_number($formatted_phone, 'duplicate', $list_id, $user_id);

                return self::build_add_response(false, false, true, (int) $duplicate['user_id'], $validation, 'Phone number is already subscribed to this list.');
            }
        }

        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE list_id = %d AND user_id = %d", $list_id, $user_id),
            ARRAY_A
        );

        if (is_array($existing) && isset($existing['subscription_status']) && $existing['subscription_status'] === 'unsubscribed') {
            $user = function_exists('get_userdata') ? get_userdata($user_id) : null;
            $username = is_object($user) && isset($user->user_login) ? (string) $user->user_login : ('user_id:' . $user_id);
            Logger::warning('Attempted add blocked: user is unsubscribed from SMS list.', array('list_id' => $list_id, 'user_id' => $user_id, 'username' => $username));
            return self::build_add_response(false, false, false, $user_id, $validation, sprintf('Cannot add %s - user is in unsubscribed list for this SMS list.', $username));
        }

        if (is_array($existing) && isset($existing['subscription_status']) && $existing['subscription_status'] === 'subscribed') {
            return self::build_add_response(true, false, true, $user_id, $validation, 'User is already subscribed to this SMS list.');
        }

        if (is_array($existing)) {
            $restored = self::resubscribe_user($list_id, $user_id);

            return self::build_add_response((bool) $restored, false, false, $user_id, $validation, $restored ? 'Subscriber restored successfully.' : 'Failed to restore subscriber.');
        }

        $now = self::current_time_mysql();
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (list_id, user_id, phone_number, subscription_status, subscribed_at, unsubscribed_at, unsubscribed_reason) VALUES (%d, %d, %s, %s, %s, %s, %s)",
                $list_id,
                $user_id,
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

        return self::build_add_response($result !== false, $result !== false, false, null, $validation, $result !== false ? 'Subscriber added successfully.' : 'Failed to add subscriber.');
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

    public static function get_list_subscribers_count(int $list_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE list_id = %d AND subscription_status = %s", $list_id, 'subscribed')
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

    public static function import_from_csv(int $list_id, string $csv_content)
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv_content);
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
            } else {
                ++$skipped;
                $errors[] = $identifier . ' - already in list';
            }
        }

        return array(
            'added' => $added,
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
        $subscribers = self::get_subscribers($list_id, 100000, 0);
        $rows = array('user_id,username,phone_number,subscribed_at');

        foreach ($subscribers as $subscriber) {
            $rows[] = implode(',', array(
                (int) $subscriber['user_id'],
                self::csv_escape(isset($subscriber['user_login']) ? (string) $subscriber['user_login'] : ''),
                self::csv_escape(isset($subscriber['phone_number']) ? (string) $subscriber['phone_number'] : ''),
                self::csv_escape(isset($subscriber['subscribed_at']) ? (string) $subscriber['subscribed_at'] : ''),
            ));
        }

        return implode("\n", $rows);
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
            $user = function_exists('get_userdata') ? get_userdata((int) $row['user_id']) : null;
            $row['user_login'] = is_object($user) && isset($user->user_login) ? (string) $user->user_login : '';
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

    private static function csv_escape(string $value)
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private static function current_time_mysql()
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_add_response(bool $success, bool $added, bool $is_duplicate, ?int $duplicate_user_id, array $validation, string $message): array
    {
        return array(
            'success' => $success,
            'message' => $message,
            'phone_valid' => !empty($validation['valid']),
            'phone_error' => isset($validation['error']) ? (string) $validation['error'] : '',
            'is_duplicate' => $is_duplicate,
            'duplicate_user_id' => $duplicate_user_id,
            'duplicate' => $is_duplicate,
            'existing_user_id' => $duplicate_user_id,
            'added' => $added,
            'formatted_phone' => isset($validation['formatted']) ? (string) $validation['formatted'] : '',
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function find_subscriber_by_phone(int $list_id, string $phone_number)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, phone_number, subscription_status FROM {$table} WHERE list_id = %d AND phone_number = %s AND subscription_status = %s LIMIT 1",
                $list_id,
                $phone_number,
                'subscribed'
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
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
