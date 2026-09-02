<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SubscriberLists
{
    public static function create(string $name, string $description = '')
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_subscriber_lists';
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

        Logger::info('Subscriber list created.', array('name' => $name, 'user_id' => get_current_user_id()));

        return isset($wpdb->insert_id) ? (int) $wpdb->insert_id : true;
    }

    public static function get(int $id)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_subscriber_lists';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    public static function get_all()
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_subscriber_lists';

        return (array) $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", 500),
            ARRAY_A
        );
    }

    public static function update(int $id, string $name, string $description)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_subscriber_lists';
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
            Logger::info('Subscriber list updated.', array('list_id' => $id, 'user_id' => get_current_user_id()));
        }

        return $result !== false;
    }

    public static function delete(int $id)
    {
        global $wpdb;
        $lists_table = $wpdb->base_prefix . 'mnem_subscriber_lists';
        $subs_table = $wpdb->base_prefix . 'mnem_list_subscribers';
        $wpdb->query($wpdb->prepare("DELETE FROM {$subs_table} WHERE list_id = %d", $id));
        $result = $wpdb->query($wpdb->prepare("DELETE FROM {$lists_table} WHERE id = %d", $id));

        if ($result !== false) {
            Logger::info('Subscriber list deleted.', array('list_id' => $id, 'user_id' => get_current_user_id()));
        }

        return $result !== false;
    }

    public static function add_subscriber(int $list_id, int $user_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE list_id = %d AND user_id = %d", $list_id, $user_id),
            ARRAY_A
        );

        if (is_array($existing) && isset($existing['subscription_status']) && $existing['subscription_status'] === 'unsubscribed') {
            $user = function_exists('get_userdata') ? get_userdata($user_id) : null;
            $username = is_object($user) && isset($user->user_login) ? (string) $user->user_login : ('user_id:' . $user_id);
            Logger::warning('Attempted add blocked: user is unsubscribed from list.', array('list_id' => $list_id, 'user_id' => $user_id, 'username' => $username));
            return new \WP_Error('mnem_user_unsubscribed', sprintf('Cannot add %s - user is in unsubscribed list for this list.', $username));
        }

        if (is_array($existing) && isset($existing['subscription_status']) && $existing['subscription_status'] === 'subscribed') {
            return false;
        }

        if (is_array($existing)) {
            return self::resubscribe_user($list_id, $user_id);
        }

        $now = self::current_time_mysql();
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (list_id, user_id, subscription_status, subscribed_at, unsubscribed_at, unsubscribed_reason) VALUES (%d, %d, %s, %s, %s, %s)",
                $list_id,
                $user_id,
                'subscribed',
                $now,
                null,
                ''
            )
        );

        if ($result !== false) {
            Logger::info('Subscriber added to list.', array('list_id' => $list_id, 'user_id' => $user_id));
        }

        return $result !== false;
    }

    public static function remove_subscriber(int $list_id, int $user_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
        $result = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE list_id = %d AND user_id = %d", $list_id, $user_id)
        );

        if ($result !== false) {
            Logger::info('Subscriber removed from list.', array('list_id' => $list_id, 'user_id' => $user_id));
        }

        return $result !== false;
    }

    public static function unsubscribe_user(int $list_id, int $user_id, string $reason = '')
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
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
                    "INSERT INTO {$table} (list_id, user_id, subscription_status, subscribed_at, unsubscribed_at, unsubscribed_reason) VALUES (%d, %d, %s, %s, %s, %s)",
                    $list_id,
                    $user_id,
                    'unsubscribed',
                    $now,
                    $now,
                    sanitize_text_field($reason)
                )
            );
        }

        if ($result !== false) {
            Logger::info('Subscriber unsubscribed from list.', array('list_id' => $list_id, 'user_id' => $user_id, 'reason' => $reason));
        }

        return $result !== false;
    }

    public static function resubscribe_user(int $list_id, int $user_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
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
            Logger::info('Subscriber restored to subscribed.', array('list_id' => $list_id, 'user_id' => $user_id));
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
        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(1) FROM {$table} WHERE list_id = %d AND subscription_status = %s", $list_id, 'subscribed')
        );
    }

    public static function is_subscribed(int $list_id, int $user_id): bool
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
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
        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
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

    /**
     * Remove an email address from every subscriber list.
     *
     * @return int Number of list membership rows removed.
     */
    public static function remove_subscriber_by_email(string $email): int
    {
        global $wpdb;

        $email = trim($email);
        if ($email === '') {
            return 0;
        }

        $user = function_exists('get_user_by') ? get_user_by('email', $email) : null;
        $user_id = is_object($user) && isset($user->ID) ? (int) $user->ID : 0;
        if ($user_id <= 0) {
            Logger::warning('Subscriber removal by email skipped because no user was found.', array('email' => $email));
            return 0;
        }

        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
        $removed = $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE user_id = %d", $user_id)
        );

        if ($removed === false) {
            Logger::error('Subscriber removal by email failed.', array('email' => $email, 'user_id' => $user_id));
            return 0;
        }

        Logger::info('Subscriber removed from all lists by email.', array(
            'email' => $email,
            'user_id' => $user_id,
            'removed_count' => (int) $removed,
        ));

        return (int) $removed;
    }

    public static function get_user_lists(int $user_id)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
        return (array) $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d ORDER BY list_id ASC", $user_id),
            ARRAY_A
        );
    }

    public static function bulk_add_subscribers(int $list_id, array $user_ids)
    {
        $added = 0;
        $errors = array();
        foreach ($user_ids as $user_id) {
            $result = self::add_subscriber($list_id, (int) $user_id);
            if ($result instanceof \WP_Error) {
                $errors[] = $result->get_error_message();
                continue;
            }
            if ($result) {
                ++$added;
            }
        }

        return array('added' => $added, 'errors' => $errors);
    }

    public static function bulk_remove_subscribers(int $list_id, array $user_ids)
    {
        $removed = 0;
        foreach ($user_ids as $user_id) {
            if (self::remove_subscriber($list_id, (int) $user_id)) {
                ++$removed;
            }
        }

        return $removed;
    }

    public static function import_from_csv(int $list_id, string $csv_content)
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv_content);
        $added = 0;
        $skipped = 0;
        $errors = array();

        foreach ((array) $lines as $line) {
            $identifier = trim((string) $line);
            if ($identifier === '') {
                continue;
            }

            $user_id = self::resolve_user_id($identifier);
            if ($user_id <= 0) {
                ++$skipped;
                $errors[] = $identifier . ' - user not found';
                continue;
            }

            $result = self::add_subscriber($list_id, $user_id);
            if ($result instanceof \WP_Error) {
                ++$skipped;
                $errors[] = $identifier . ' - ' . $result->get_error_message();
                continue;
            }
            if ($result) {
                ++$added;
            } else {
                ++$skipped;
                $errors[] = $identifier . ' - already in list';
            }
        }

        return array(
            'added' => $added,
            'skipped' => $skipped,
            'errors' => $errors,
        );
    }

    public static function export_to_csv(int $list_id)
    {
        $subscribers = self::get_subscribers($list_id, 100000, 0);
        $rows = array('user_id,username,email,subscribed_at');

        foreach ($subscribers as $subscriber) {
            $rows[] = implode(',', array(
                (int) $subscriber['user_id'],
                self::csv_escape(isset($subscriber['user_login']) ? (string) $subscriber['user_login'] : ''),
                self::csv_escape(isset($subscriber['user_email']) ? (string) $subscriber['user_email'] : ''),
                self::csv_escape(isset($subscriber['subscribed_at']) ? (string) $subscriber['subscribed_at'] : ''),
            ));
        }

        return implode("\n", $rows);
    }

    private static function get_users_by_status(int $list_id, string $status, int $limit, int $offset)
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_list_subscribers';
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
            $row['user_email'] = is_object($user) && isset($user->user_email) ? (string) $user->user_email : '';
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

    private static function csv_escape(string $value)
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    private static function current_time_mysql()
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }
}
