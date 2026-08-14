<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Campaigns
{
    public const VALID_STATUSES = array('draft', 'scheduled', 'sending', 'sent', 'cancelled');
    public const VALID_RECIPIENT_SCOPES = array('all_users', 'admins', 'custom');

    public const VALID_TRANSITIONS = array(
        'draft' => array('scheduled', 'cancelled'),
        'scheduled' => array('sending', 'cancelled'),
        'sending' => array('sent', 'cancelled'),
        'sent' => array(),
        'cancelled' => array(),
    );

    public static function create(int $site_id, string $name, string $subject, string $body, array $args = array())
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_campaigns';
        $now = self::current_time_mysql();
        $recipient_list = self::sanitize_recipient_list(isset($args['recipient_list']) ? $args['recipient_list'] : array());
        $target_lists = self::sanitize_target_lists(isset($args['target_lists']) ? $args['target_lists'] : array());
        $status = isset($args['status']) && in_array($args['status'], self::VALID_STATUSES, true)
            ? $args['status']
            : 'draft';
        $scheduled_at = isset($args['scheduled_at']) && $args['scheduled_at'] !== ''
            ? sanitize_text_field((string) $args['scheduled_at'])
            : null;

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, name, subject, body, body_type, template_id, status, scheduled_at, recipient_scope, recipient_list, target_lists, total_recipients, sent_count, failed_count, enqueue_failed_count, last_send_attempt_at, sent_at, created_at, updated_at) VALUES (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d, %d, %d, %d, %s, %s, %s, %s)",
                $site_id,
                sanitize_text_field($name),
                $subject,
                $body,
                'html',
                isset($args['template_id']) ? sanitize_text_field((string) $args['template_id']) : '',
                $status,
                $scheduled_at,
                self::normalize_recipient_scope(isset($args['recipient_scope']) ? (string) $args['recipient_scope'] : 'all_users'),
                $recipient_list,
                $target_lists,
                0,
                0,
                0,
                0,
                null,
                null,
                $now,
                $now
            )
        );

        if ($result === false) {
            return false;
        }

        return isset($wpdb->insert_id) ? (int) $wpdb->insert_id : true;
    }

    public static function get(int $id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_campaigns';
        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );

        return $campaign ?: null;
    }

    public static function get_list(int $site_id, string $status = '', int $limit = 50, int $offset = 0)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_campaigns';
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        if ($status !== '' && in_array($status, self::VALID_STATUSES, true)) {
            return (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE site_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $site_id,
                    $status,
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
        }

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $site_id,
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    public static function update(int $id, array $data)
    {
        global $wpdb;

        $campaign = self::get($id);
        if (!$campaign) {
            return false;
        }

        $table = $wpdb->prefix . 'mnem_campaigns';
        $recipient_list = self::sanitize_recipient_list(isset($data['recipient_list']) ? $data['recipient_list'] : (isset($campaign['recipient_list']) ? $campaign['recipient_list'] : ''));
        $scheduled_at = isset($data['scheduled_at']) && $data['scheduled_at'] !== ''
            ? sanitize_text_field((string) $data['scheduled_at'])
            : null;
        $status = isset($data['status']) && in_array($data['status'], self::VALID_STATUSES, true)
            ? $data['status']
            : $campaign['status'];
        $target_lists = self::sanitize_target_lists(isset($data['target_lists']) ? $data['target_lists'] : (isset($campaign['target_lists']) ? $campaign['target_lists'] : ''));

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET name = %s, subject = %s, body = %s, body_type = %s, template_id = %s, status = %s, scheduled_at = %s, recipient_scope = %s, recipient_list = %s, target_lists = %s, updated_at = %s WHERE id = %d",
                sanitize_text_field(isset($data['name']) ? (string) $data['name'] : (string) $campaign['name']),
                isset($data['subject']) ? (string) $data['subject'] : (string) $campaign['subject'],
                isset($data['body']) ? (string) $data['body'] : (string) $campaign['body'],
                'html',
                isset($data['template_id']) ? sanitize_text_field((string) $data['template_id']) : (isset($campaign['template_id']) ? (string) $campaign['template_id'] : ''),
                $status,
                $scheduled_at,
                self::normalize_recipient_scope(isset($data['recipient_scope']) ? (string) $data['recipient_scope'] : (string) $campaign['recipient_scope']),
                $recipient_list,
                $target_lists,
                self::current_time_mysql(),
                $id
            )
        ) !== false;
    }

    public static function delete(int $id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_campaigns';

        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id = %d",
                $id
            )
        ) !== false;
    }

    public static function update_status(int $id, string $new_status)
    {
        global $wpdb;

        if (!in_array($new_status, self::VALID_STATUSES, true)) {
            return false;
        }

        $campaign = self::get($id);
        if (!$campaign || !self::is_valid_transition((string) $campaign['status'], $new_status)) {
            return false;
        }

        $table = $wpdb->prefix . 'mnem_campaigns';
        $sent_at = $new_status === 'sent' ? self::current_time_mysql() : (isset($campaign['sent_at']) ? $campaign['sent_at'] : null);

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, sent_at = %s, updated_at = %s WHERE id = %d",
                $new_status,
                $sent_at,
                self::current_time_mysql(),
                $id
            )
        );
    }

    public static function send_campaign(int $id, array $list_ids = array())
    {
        $campaign = self::get($id);
        if (!$campaign) {
            return array('success' => false, 'message' => 'Campaign not found.');
        }

        if ((int) get_site_option('mnem_campaign_sends_paused', 0) === 1) {
            Logger::warning('Campaign send blocked because sending is paused.', array('campaign_id' => $id));
            return array('success' => false, 'message' => 'Campaign sending is paused.');
        }

        if (in_array($campaign['status'], array('sending', 'sent', 'cancelled'), true)) {
            return array('success' => false, 'message' => 'Campaign cannot be sent from its current status.');
        }

        if ($campaign['status'] === 'draft') {
            self::update_status($id, 'scheduled');
            $campaign = self::get($id);
        }

        if (!$campaign || $campaign['status'] !== 'scheduled') {
            return array('success' => false, 'message' => 'Campaign must be scheduled before sending.');
        }

        self::mark_as_sending($id);
        $campaign = self::get($id);
        $recipients = array();
        if (!empty($list_ids)) {
            $recipients = self::get_recipient_emails_from_lists($list_ids);
        } else {
            $target_lists = isset($campaign['target_lists']) ? json_decode((string) $campaign['target_lists'], true) : array();
            if (is_array($target_lists) && !empty($target_lists)) {
                $recipients = self::get_recipient_emails_from_lists($target_lists);
            } else {
                $recipients = self::get_recipients($campaign);
            }
        }
        $queued = 0;
        $enqueue_failed = 0;

        foreach ($recipients as $recipient_email) {
            $result = Queue::enqueue(
                (int) $campaign['site_id'],
                $recipient_email,
                (string) $campaign['subject'],
                (string) $campaign['body'],
                (int) $campaign['id']
            );

            if ($result === false) {
                ++$enqueue_failed;
                Logger::warning(
                    'Failed to enqueue campaign recipient.',
                    array(
                        'campaign_id' => (int) $campaign['id'],
                        'site_id' => (int) $campaign['site_id'],
                        'recipient_email' => $recipient_email,
                    )
                );
                continue;
            }

            ++$queued;
        }

        self::update_delivery_tracking($id, $queued, 0, $enqueue_failed, self::current_time_mysql());
        Logger::info(
            'Campaign queued for delivery.',
            array(
                'campaign_id' => (int) $campaign['id'],
                'site_id' => (int) $campaign['site_id'],
                'queued' => $queued,
                'enqueue_failed' => $enqueue_failed,
                'recipient_scope' => isset($campaign['recipient_scope']) ? $campaign['recipient_scope'] : 'all_users',
                'target_lists' => isset($campaign['target_lists']) ? $campaign['target_lists'] : '[]',
            )
        );

        if ($queued === 0) {
            self::refresh_delivery_stats($id);
        }

        return array(
            'success' => true,
            'queued' => $queued,
            'failed' => $enqueue_failed,
            'total' => count($recipients),
            'message' => $queued > 0 ? 'Campaign recipients queued.' : 'No campaign recipients were queued.',
        );
    }

    public static function get_recipients($campaign)
    {
        if (is_numeric($campaign)) {
            $campaign = self::get((int) $campaign);
        }

        if (!is_array($campaign)) {
            return array();
        }

        $scope = self::normalize_recipient_scope(isset($campaign['recipient_scope']) ? (string) $campaign['recipient_scope'] : 'all_users');
        if ($scope === 'custom') {
            return self::parse_recipient_list(isset($campaign['recipient_list']) ? $campaign['recipient_list'] : '');
        }

        if (!function_exists('get_users')) {
            return array();
        }

        $args = array(
            'blog_id' => isset($campaign['site_id']) ? (int) $campaign['site_id'] : 1,
            'fields' => array('user_email'),
        );

        if ($scope === 'admins') {
            $args['role__in'] = array('Administrator', 'administrator');
        }

        $users = get_users($args);
        $recipients = array();

        foreach ((array) $users as $user) {
            $email = '';

            if (is_array($user) && isset($user['user_email'])) {
                $email = (string) $user['user_email'];
            } elseif (is_object($user) && isset($user->user_email)) {
                $email = (string) $user->user_email;
            } elseif (is_string($user)) {
                $email = $user;
            }

            $email = strtolower(trim(sanitize_email($email)));
            if ($email !== '' && is_email($email)) {
                $recipients[] = $email;
            }
        }

        return array_values(array_unique($recipients));
    }

    public static function refresh_delivery_stats(int $id)
    {
        global $wpdb;

        $campaign = self::get($id);
        if (!$campaign) {
            return false;
        }

        $queue_table = $wpdb->prefix . 'mnem_queue';
        $campaigns_table = $wpdb->prefix . 'mnem_campaigns';
        $queued_total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} WHERE campaign_id = %d", $id));
        $sent_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} WHERE campaign_id = %d AND status = %s", $id, 'sent'));
        $queue_failed_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} WHERE campaign_id = %d AND status = %s", $id, 'failed'));
        $pending_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$queue_table} WHERE campaign_id = %d AND status IN (%s, %s)", $id, 'pending', 'processing'));
        $last_attempt = $wpdb->get_var($wpdb->prepare("SELECT MAX(processed_at) FROM {$queue_table} WHERE campaign_id = %d", $id));
        $enqueue_failed = isset($campaign['enqueue_failed_count']) ? (int) $campaign['enqueue_failed_count'] : 0;
        $failed_count = $enqueue_failed + $queue_failed_count;
        $status = (string) $campaign['status'];
        $sent_at = isset($campaign['sent_at']) ? $campaign['sent_at'] : null;

        if ($status === 'sending' && $pending_count === 0) {
            $status = 'sent';
            $sent_at = self::current_time_mysql();
        }

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$campaigns_table} SET status = %s, total_recipients = %d, sent_count = %d, failed_count = %d, last_send_attempt_at = %s, sent_at = %s, updated_at = %s WHERE id = %d",
                $status,
                $queued_total,
                $sent_count,
                $failed_count,
                $last_attempt ? $last_attempt : (isset($campaign['last_send_attempt_at']) ? $campaign['last_send_attempt_at'] : null),
                $sent_at,
                self::current_time_mysql(),
                $id
            )
        );

        return $result !== false;
    }

    public static function is_valid_transition(string $current_status, string $new_status)
    {
        return isset(self::VALID_TRANSITIONS[$current_status])
            && in_array($new_status, self::VALID_TRANSITIONS[$current_status], true);
    }

    public static function normalize_recipient_scope(string $scope)
    {
        return in_array($scope, self::VALID_RECIPIENT_SCOPES, true) ? $scope : 'all_users';
    }

    private static function mark_as_sending(int $id)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_campaigns';
        $now = self::current_time_mysql();

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, sent_at = %s, last_send_attempt_at = %s, total_recipients = %d, sent_count = %d, failed_count = %d, enqueue_failed_count = %d, updated_at = %s WHERE id = %d",
                'sending',
                null,
                $now,
                0,
                0,
                0,
                0,
                $now,
                $id
            )
        );
    }

    private static function update_delivery_tracking(int $id, int $total_recipients, int $sent_count, int $failed_count, $last_attempt)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_campaigns';

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET total_recipients = %d, sent_count = %d, failed_count = %d, enqueue_failed_count = %d, last_send_attempt_at = %s, updated_at = %s WHERE id = %d",
                $total_recipients,
                $sent_count,
                $failed_count,
                $failed_count,
                $last_attempt,
                self::current_time_mysql(),
                $id
            )
        );
    }

    private static function sanitize_recipient_list($recipient_list)
    {
        $emails = self::parse_recipient_list($recipient_list);

        return implode("\n", $emails);
    }

    private static function parse_recipient_list($recipient_list)
    {
        if (is_array($recipient_list)) {
            $recipient_list = implode("\n", $recipient_list);
        }

        $items = preg_split('/[\r\n,;]+/', (string) $recipient_list);
        $emails = array();

        foreach ((array) $items as $item) {
            $email = strtolower(trim(sanitize_email($item)));
            if ($email !== '' && is_email($email)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private static function current_time_mysql()
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }

    public static function cancel_campaign(int $id)
    {
        $campaign = self::get($id);
        if (!$campaign || !self::can_cancel($id)) {
            return false;
        }

        global $wpdb;
        $queue_table = $wpdb->prefix . 'mnem_queue';
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$queue_table} WHERE campaign_id = %d AND status IN (%s, %s)",
                $id,
                'pending',
                'processing'
            )
        );

        $updated = self::update_status($id, 'cancelled');
        if ($updated !== false) {
            Logger::info('Campaign cancelled', array(
                'campaign_id' => $id,
                'queued_items_removed' => (int) $deleted,
                'cancelled_by' => get_current_user_id(),
            ));
        }

        return $updated !== false;
    }

    public static function can_cancel(int $id)
    {
        $campaign = self::get($id);

        return is_array($campaign)
            && isset($campaign['status'])
            && in_array((string) $campaign['status'], array('draft', 'scheduled', 'sending'), true);
    }

    public static function get_target_recipients(int $campaign_id)
    {
        $campaign = self::get($campaign_id);
        if (!$campaign) {
            return array();
        }

        $target_lists = isset($campaign['target_lists']) ? json_decode((string) $campaign['target_lists'], true) : array();
        if (!is_array($target_lists) || empty($target_lists)) {
            return array();
        }

        return self::get_recipients_from_lists($target_lists);
    }

    private static function get_recipients_from_lists(array $list_ids)
    {
        global $wpdb;
        $list_table = $wpdb->prefix . 'mnem_list_subscribers';
        $list_ids = array_values(array_filter(array_map('intval', $list_ids), static function ($id) {
            return $id > 0;
        }));

        if (empty($list_ids)) {
            return array();
        }

        $placeholders = implode(',', array_fill(0, count($list_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$list_table} WHERE list_id IN ({$placeholders}) AND subscription_status = %s",
            ...array_merge($list_ids, array('subscribed'))
        );
        $users = array_map('intval', (array) $wpdb->get_col($query));

        return array_values(array_unique($users));
    }

    private static function get_recipient_emails_from_lists(array $list_ids)
    {
        $user_ids = self::get_recipients_from_lists($list_ids);
        $emails = array();

        foreach ($user_ids as $user_id) {
            $user = function_exists('get_userdata') ? get_userdata((int) $user_id) : null;
            if (is_object($user) && isset($user->user_email)) {
                $email = strtolower(trim(sanitize_email((string) $user->user_email)));
                if ($email !== '' && is_email($email)) {
                    $emails[] = $email;
                }
            }
        }

        return array_values(array_unique($emails));
    }

    private static function sanitize_target_lists($target_lists)
    {
        if (is_string($target_lists)) {
            $decoded = json_decode($target_lists, true);
            if (is_array($decoded)) {
                $target_lists = $decoded;
            } elseif ($target_lists !== '') {
                $target_lists = preg_split('/[\s,]+/', $target_lists);
            } else {
                $target_lists = array();
            }
        }

        $target_lists = array_values(array_unique(array_filter(array_map('intval', (array) $target_lists), static function ($id) {
            return $id > 0;
        })));

        return wp_json_encode($target_lists);
    }
}
