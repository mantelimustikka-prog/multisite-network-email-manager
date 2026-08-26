<?php

namespace MNEM;

defined('ABSPATH') || exit;

class InvalidPhoneNumbers
{
    public static function log_invalid_number($phone_number, $reason, $list_id = null, $user_id = null)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $phone_number = self::normalize_phone_number($phone_number);
        $reason = sanitize_key((string) $reason);
        $list_id = self::normalize_list_id($list_id);
        $user_id = $user_id !== null ? (int) $user_id : null;
        $now = self::current_time_mysql();
        $existing_id = self::find_existing_id($phone_number, $list_id);

        if ($existing_id > 0) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET reason = %s, user_id = %d WHERE id = %d",
                    $reason,
                    $user_id === null ? 0 : $user_id,
                    $existing_id
                )
            );
        } else {
            $result = method_exists($wpdb, 'insert')
                ? $wpdb->insert($table, array(
                    'phone_number' => $phone_number,
                    'reason' => $reason !== '' ? $reason : 'format_invalid',
                    'list_id' => $list_id,
                    'user_id' => $user_id,
                    'blocked' => 0,
                    'created_at' => $now,
                    'action_taken' => 'none',
                    'taken_by' => null,
                    'taken_at' => null,
                ))
                : false;
            $existing_id = isset($wpdb->insert_id) ? (int) $wpdb->insert_id : 0;
        }

        Logger::warning('Invalid phone number detected.', array(
            'phone_number' => $phone_number,
            'reason' => $reason,
            'list_id' => $list_id,
            'user_id' => $user_id,
        ));

        return $result === false ? false : $existing_id;
    }

    public static function block_number($phone_number)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $phone_number = self::normalize_phone_number($phone_number);
        $admin_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $now = self::current_time_mysql();
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET blocked = %d, action_taken = %s, taken_by = %d, taken_at = %s WHERE phone_number = %s",
                1,
                'blocked',
                $admin_id,
                $now,
                $phone_number
            )
        );

        if ($updated === 0 || $updated === false) {
            $logged_id = self::log_invalid_number($phone_number, 'blocked', null, null);
            if ($logged_id) {
                self::take_action((int) $logged_id, 'blocked', $admin_id);
            }
        }

        Logger::info('Admin blocked phone number.', array('phone_number' => $phone_number, 'admin_id' => $admin_id));

        return $updated !== false;
    }

    public static function unblock_number($phone_number)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $phone_number = self::normalize_phone_number($phone_number);
        $admin_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET blocked = %d, action_taken = %s, taken_by = %d, taken_at = %s WHERE phone_number = %s",
                0,
                'removed',
                $admin_id,
                self::current_time_mysql(),
                $phone_number
            )
        );

        Logger::info('Admin unblocked phone number.', array('phone_number' => $phone_number, 'admin_id' => $admin_id));

        return $result !== false;
    }

    public static function is_blocked($phone_number): bool
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $phone_number = self::normalize_phone_number($phone_number);
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE phone_number = %s AND blocked = %d",
                $phone_number,
                1
            )
        );

        return (int) $count > 0;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function get_invalid_numbers($list_id = null, $status = 'all', $limit = 100, $offset = 0, array $filters = array()): array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $where = self::build_where_clauses($list_id, $status, $filters, $wpdb);

        $query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                array("SELECT id, phone_number, reason, list_id, user_id, blocked, created_at, action_taken, taken_by, taken_at FROM {$table} {$where['sql']} ORDER BY created_at DESC LIMIT %d OFFSET %d"),
                $where['args'],
                array(max(1, (int) $limit), max(0, (int) $offset))
            )
        );

        $rows = (array) $wpdb->get_results($query, ARRAY_A);

        foreach ($rows as &$row) {
            $user = !empty($row['user_id']) && function_exists('get_userdata') ? get_userdata((int) $row['user_id']) : null;
            $admin = !empty($row['taken_by']) && function_exists('get_userdata') ? get_userdata((int) $row['taken_by']) : null;
            $row['user_login'] = is_object($user) && isset($user->user_login) ? (string) $user->user_login : '';
            $row['admin_login'] = is_object($admin) && isset($admin->user_login) ? (string) $admin->user_login : '';
            $row['status'] = !empty($row['blocked']) ? 'blocked' : 'active';
        }
        unset($row);

        return $rows;
    }

    public static function get_invalid_count($list_id = null, $status = 'all', array $filters = array()): int
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $where = self::build_where_clauses($list_id, $status, $filters, $wpdb);

        $query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                array("SELECT COUNT(1) FROM {$table} {$where['sql']}"),
                $where['args']
            )
        );

        return (int) $wpdb->get_var($query);
    }

    public static function remove_invalid_entry($id)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id = %d", (int) $id));

        Logger::info('Admin removed invalid phone entry.', array('id' => (int) $id));

        return $result !== false;
    }

    public static function take_action($id, $action_type, $admin_id)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $action_type = sanitize_key((string) $action_type);
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET action_taken = %s, taken_by = %d, taken_at = %s WHERE id = %d",
                $action_type,
                (int) $admin_id,
                self::current_time_mysql(),
                (int) $id
            )
        );

        return $result !== false;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_invalid_entry($id)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>
     */
    private static function build_where_clauses($list_id, $status, array $filters, $wpdb): array
    {
        $clauses = array();
        $args = array();

        if ($list_id !== null) {
            $clauses[] = 'list_id = %d';
            $args[] = self::normalize_list_id($list_id);
        }

        if ($status === 'blocked') {
            $clauses[] = 'blocked = %d';
            $args[] = 1;
        } elseif ($status === 'not_blocked') {
            $clauses[] = 'blocked = %d';
            $args[] = 0;
        }

        if (!empty($filters['reason'])) {
            $clauses[] = 'reason = %s';
            $args[] = sanitize_key((string) $filters['reason']);
        }

        if (!empty($filters['search'])) {
            $clauses[] = 'phone_number LIKE %s';
            $args[] = '%' . $wpdb->esc_like((string) $filters['search']) . '%';
        }

        if (!empty($filters['date_from'])) {
            $clauses[] = 'created_at >= %s';
            $args[] = sanitize_text_field((string) $filters['date_from']) . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $clauses[] = 'created_at <= %s';
            $args[] = sanitize_text_field((string) $filters['date_to']) . ' 23:59:59';
        }

        return array(
            'sql' => !empty($clauses) ? 'WHERE ' . implode(' AND ', $clauses) : '',
            'args' => $args,
        );
    }

    private static function find_existing_id(string $phone_number, $list_id): int
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_invalid_phone_numbers';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE phone_number = %s AND list_id = %d ORDER BY id DESC LIMIT 1",
                $phone_number,
                self::normalize_list_id($list_id)
            )
        );
    }

    private static function normalize_list_id($list_id): int
    {
        return $list_id === null ? 0 : max(0, (int) $list_id);
    }

    private static function normalize_phone_number($phone_number): string
    {
        $country_code = class_exists(__NAMESPACE__ . '\\SmsSettings') ? SmsSettings::get_validation_country_code() : 'US';
        $formatted = class_exists(__NAMESPACE__ . '\\PhoneValidator') ? PhoneValidator::format_phone_number($phone_number, $country_code) : '';
        if ($formatted !== '') {
            return $formatted;
        }

        $normalized = preg_replace('/[^\d+]+/', '', trim((string) $phone_number));

        return is_string($normalized) ? $normalized : trim((string) $phone_number);
    }

    private static function current_time_mysql(): string
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }
}
