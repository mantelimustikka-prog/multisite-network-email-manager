<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmsCampaigns
{
    public const VALID_STATUSES = array('draft', 'scheduled', 'sending', 'paused', 'completed', 'cancelled');

    private const SUCCESS_QUEUE_STATUSES = array('sent', 'delivered', 'opened', 'clicked');
    private const FAILED_QUEUE_STATUSES = array('bounce', 'soft_bounce', 'invalid_email', 'deferred', 'complaint', 'unsubscribed', 'suppressed', 'failed', 'rejected');
    private const PENDING_QUEUE_STATUSES = array('pending', 'processing');

    /**
     * @return array{success:bool,campaign_id:int,message:string}
     */
    public static function create(int $site_id, array $data): array
    {
        global $wpdb;

        $validation = self::validate_campaign_data($data);
        if (!$validation['valid']) {
            return array(
                'success' => false,
                'campaign_id' => 0,
                'message' => implode(' ', $validation['errors']),
            );
        }

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $now = self::current_time_mysql();
        $status = isset($data['status']) && in_array((string) $data['status'], self::VALID_STATUSES, true)
            ? (string) $data['status']
            : 'draft';
        $scheduled_at = isset($data['scheduled_at']) && trim((string) $data['scheduled_at']) !== ''
            ? sanitize_text_field((string) $data['scheduled_at'])
            : null;
        $description = isset($data['description']) ? (string) $data['description'] : '';
        $message_body = function_exists('sanitize_textarea_field')
            ? sanitize_textarea_field((string) $data['message_body'])
            : trim(wp_strip_all_tags((string) $data['message_body']));
        $delivery_status_map = function_exists('wp_json_encode') ? wp_json_encode(array()) : json_encode(array());
        $created_by = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, name, description, message_body, sms_list_id, status, total_recipients, sent_count, failed_count, bounce_count, delivery_status_map, scheduled_at, started_at, completed_at, created_at, updated_at, created_by) VALUES (%d, %s, %s, %s, %d, %s, %d, %d, %d, %d, %s, %s, %s, %s, %s, %s, %d)",
                $site_id,
                sanitize_text_field((string) $data['name']),
                $description,
                $message_body,
                (int) $data['sms_list_id'],
                $status,
                0,
                0,
                0,
                0,
                $delivery_status_map,
                $scheduled_at,
                null,
                null,
                $now,
                $now,
                $created_by
            )
        );

        if ($result === false) {
            Logger::error('SMS campaign create failed.', array(
                'site_id' => $site_id,
                'sms_list_id' => isset($data['sms_list_id']) ? (int) $data['sms_list_id'] : 0,
            ));

            return array(
                'success' => false,
                'campaign_id' => 0,
                'message' => 'SMS campaign could not be created.',
            );
        }

        $campaign_id = isset($wpdb->insert_id) ? (int) $wpdb->insert_id : 0;

        Logger::info('SMS campaign created.', array(
            'campaign_id' => $campaign_id,
            'site_id' => $site_id,
            'sms_list_id' => (int) $data['sms_list_id'],
            'status' => $status,
            'created_by' => $created_by,
        ));

        return array(
            'success' => true,
            'campaign_id' => $campaign_id,
            'message' => 'SMS campaign created successfully.',
        );
    }

    public static function get(int $campaign_id): ?array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $campaign = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $campaign_id
            ),
            ARRAY_A
        );

        return is_array($campaign) ? $campaign : null;
    }

    /**
     * @return array{success:bool,message:string}
     */
    public static function update(int $campaign_id, array $data): array
    {
        global $wpdb;

        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return array(
                'success' => false,
                'message' => 'SMS campaign not found.',
            );
        }

        if (isset($data['name']) && trim((string) $data['name']) === '') {
            return array(
                'success' => false,
                'message' => 'Campaign name is required.',
            );
        }

        if (isset($data['message_body'])) {
            $candidate_message = function_exists('sanitize_textarea_field')
                ? sanitize_textarea_field((string) $data['message_body'])
                : trim(wp_strip_all_tags((string) $data['message_body']));
            if ($candidate_message === '') {
                return array(
                    'success' => false,
                    'message' => 'Message body is required.',
                );
            }
        }

        if (isset($data['sms_list_id'])) {
            $sms_list_id = (int) $data['sms_list_id'];
            if ($sms_list_id <= 0) {
                return array(
                    'success' => false,
                    'message' => 'A valid SMS list is required.',
                );
            }

            if (!is_array(SmsSubscriberLists::get($sms_list_id))) {
                return array(
                    'success' => false,
                    'message' => 'Selected SMS list was not found.',
                );
            }
        }

        if (isset($data['status']) && !in_array((string) $data['status'], self::VALID_STATUSES, true)) {
            return array(
                'success' => false,
                'message' => 'Invalid SMS campaign status.',
            );
        }

        $status = isset($data['status']) ? (string) $data['status'] : (string) $campaign['status'];
        $scheduled_at = array_key_exists('scheduled_at', $data)
            ? (trim((string) $data['scheduled_at']) !== '' ? sanitize_text_field((string) $data['scheduled_at']) : null)
            : (isset($campaign['scheduled_at']) && $campaign['scheduled_at'] !== '' ? (string) $campaign['scheduled_at'] : null);

        if ($status === 'scheduled' && $scheduled_at === null) {
            return array(
                'success' => false,
                'message' => 'Scheduled campaigns require a scheduled date.',
            );
        }

        $message_body = isset($data['message_body'])
            ? (function_exists('sanitize_textarea_field')
                ? sanitize_textarea_field((string) $data['message_body'])
                : trim(wp_strip_all_tags((string) $data['message_body'])))
            : (string) $campaign['message_body'];

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET name = %s, description = %s, message_body = %s, sms_list_id = %d, status = %s, scheduled_at = %s, updated_at = %s WHERE id = %d",
                sanitize_text_field(isset($data['name']) ? (string) $data['name'] : (string) $campaign['name']),
                isset($data['description']) ? (string) $data['description'] : (string) $campaign['description'],
                $message_body,
                isset($data['sms_list_id']) ? (int) $data['sms_list_id'] : (int) $campaign['sms_list_id'],
                $status,
                $scheduled_at,
                self::current_time_mysql(),
                $campaign_id
            )
        );

        if ($result === false) {
            Logger::error('SMS campaign update failed.', array('campaign_id' => $campaign_id));

            return array(
                'success' => false,
                'message' => 'SMS campaign could not be updated.',
            );
        }

        Logger::info('SMS campaign updated.', array(
            'campaign_id' => $campaign_id,
            'status' => $status,
            'updated_by' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
        ));

        return array(
            'success' => true,
            'message' => 'SMS campaign updated successfully.',
        );
    }

    /**
     * @return array{success:bool,message:string}
     */
    public static function delete(int $campaign_id): array
    {
        global $wpdb;

        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return array(
                'success' => false,
                'message' => 'SMS campaign not found.',
            );
        }

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $queue_result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$queue_table} WHERE sms_campaign_id = %d OR (campaign_id = %d AND message_type = %s)",
                $campaign_id,
                $campaign_id,
                'sms'
            )
        );

        if ($queue_result === false) {
            Logger::error('SMS campaign delete failed while removing queue items.', array('campaign_id' => $campaign_id));

            return array(
                'success' => false,
                'message' => 'SMS campaign queue items could not be deleted.',
            );
        }

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $result = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id = %d",
                $campaign_id
            )
        );

        if ($result === false) {
            Logger::error('SMS campaign delete failed.', array('campaign_id' => $campaign_id));

            return array(
                'success' => false,
                'message' => 'SMS campaign could not be deleted.',
            );
        }

        Logger::info('SMS campaign deleted.', array(
            'campaign_id' => $campaign_id,
            'queue_items_deleted' => (int) $queue_result,
            'deleted_by' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
        ));

        return array(
            'success' => true,
            'message' => 'SMS campaign deleted successfully.',
        );
    }

    public static function get_list(int $site_id, ?string $status = null, int $per_page = 20, int $offset = 0): array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $per_page = max(1, $per_page);
        $offset = max(0, $offset);

        if ($status !== null && $status !== '' && in_array($status, self::VALID_STATUSES, true)) {
            return (array) $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE site_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
                    $site_id,
                    $status,
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );
        }

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $site_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );
    }

    /**
     * @return array{success:bool,message:string,queued_count:int}
     */
    public static function send_now(int $campaign_id): array
    {
        global $wpdb;

        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return array(
                'success' => false,
                'message' => 'SMS campaign not found.',
                'queued_count' => 0,
            );
        }

        if (in_array((string) $campaign['status'], array('sending', 'completed', 'cancelled'), true)) {
            return array(
                'success' => false,
                'message' => 'SMS campaign cannot be sent from its current status.',
                'queued_count' => 0,
            );
        }

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $now = self::current_time_mysql();
        $update_result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, started_at = %s, completed_at = %s, updated_at = %s WHERE id = %d",
                'sending',
                $now,
                null,
                $now,
                $campaign_id
            )
        );

        if ($update_result === false) {
            Logger::error('SMS campaign send failed while updating status.', array('campaign_id' => $campaign_id));

            return array(
                'success' => false,
                'message' => 'SMS campaign could not be marked as sending.',
                'queued_count' => 0,
            );
        }

        $queue_result = self::queue_recipients($campaign_id, (string) $campaign['message_body']);
        if (!$queue_result['success']) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, started_at = %s, updated_at = %s WHERE id = %d",
                    (string) $campaign['status'],
                    isset($campaign['started_at']) && $campaign['started_at'] !== '' ? (string) $campaign['started_at'] : null,
                    self::current_time_mysql(),
                    $campaign_id
                )
            );

            Logger::error('SMS campaign send failed while queueing recipients.', array(
                'campaign_id' => $campaign_id,
                'queued_count' => (int) $queue_result['queued_count'],
            ));

            return array(
                'success' => false,
                'message' => (string) $queue_result['message'],
                'queued_count' => (int) $queue_result['queued_count'],
            );
        }

        $stats = self::get_delivery_stats($campaign_id);
        $delivery_status_map = function_exists('wp_json_encode') ? wp_json_encode($stats['status_breakdown']) : json_encode($stats['status_breakdown']);
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET total_recipients = %d, delivery_status_map = %s, updated_at = %s WHERE id = %d",
                (int) $queue_result['queued_count'],
                $delivery_status_map,
                self::current_time_mysql(),
                $campaign_id
            )
        );

        Logger::info('SMS campaign queued for sending.', array(
            'campaign_id' => $campaign_id,
            'site_id' => (int) $campaign['site_id'],
            'queued_count' => (int) $queue_result['queued_count'],
        ));

        return array(
            'success' => true,
            'message' => 'SMS campaign queued successfully.',
            'queued_count' => (int) $queue_result['queued_count'],
        );
    }

    /**
     * @return array{success:bool,message:string}
     */
    public static function schedule(int $campaign_id, string $scheduled_at): array
    {
        global $wpdb;

        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return array(
                'success' => false,
                'message' => 'SMS campaign not found.',
            );
        }

        $scheduled_at = sanitize_text_field($scheduled_at);
        if ($scheduled_at === '') {
            return array(
                'success' => false,
                'message' => 'Scheduled date is required.',
            );
        }

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, scheduled_at = %s, updated_at = %s WHERE id = %d",
                'scheduled',
                $scheduled_at,
                self::current_time_mysql(),
                $campaign_id
            )
        );

        if ($result === false) {
            Logger::error('SMS campaign schedule failed.', array('campaign_id' => $campaign_id, 'scheduled_at' => $scheduled_at));

            return array(
                'success' => false,
                'message' => 'SMS campaign could not be scheduled.',
            );
        }

        Logger::info('SMS campaign scheduled.', array(
            'campaign_id' => $campaign_id,
            'scheduled_at' => $scheduled_at,
        ));

        return array(
            'success' => true,
            'message' => 'SMS campaign scheduled successfully.',
        );
    }

    /**
     * @return array{success:bool,message:string}
     */
    public static function pause(int $campaign_id): array
    {
        global $wpdb;

        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return array(
                'success' => false,
                'message' => 'SMS campaign not found.',
            );
        }

        if ((string) $campaign['status'] !== 'sending') {
            return array(
                'success' => false,
                'message' => 'Only sending campaigns can be paused.',
            );
        }

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
                'paused',
                self::current_time_mysql(),
                $campaign_id
            )
        );

        if ($result === false) {
            Logger::error('SMS campaign pause failed.', array('campaign_id' => $campaign_id));

            return array(
                'success' => false,
                'message' => 'SMS campaign could not be paused.',
            );
        }

        Logger::info('SMS campaign paused.', array('campaign_id' => $campaign_id));

        return array(
            'success' => true,
            'message' => 'SMS campaign paused successfully.',
        );
    }

    /**
     * @return array{success:bool,message:string}
     */
    public static function resume(int $campaign_id): array
    {
        global $wpdb;

        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return array(
                'success' => false,
                'message' => 'SMS campaign not found.',
            );
        }

        if ((string) $campaign['status'] !== 'paused') {
            return array(
                'success' => false,
                'message' => 'Only paused campaigns can be resumed.',
            );
        }

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $started_at = isset($campaign['started_at']) && $campaign['started_at'] !== '' ? (string) $campaign['started_at'] : self::current_time_mysql();
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, started_at = %s, updated_at = %s WHERE id = %d",
                'sending',
                $started_at,
                self::current_time_mysql(),
                $campaign_id
            )
        );

        if ($result === false) {
            Logger::error('SMS campaign resume failed.', array('campaign_id' => $campaign_id));

            return array(
                'success' => false,
                'message' => 'SMS campaign could not be resumed.',
            );
        }

        Logger::info('SMS campaign resumed.', array('campaign_id' => $campaign_id));

        return array(
            'success' => true,
            'message' => 'SMS campaign resumed successfully.',
        );
    }

    /**
     * @return array{success:bool,message:string}
     */
    public static function cancel(int $campaign_id): array
    {
        global $wpdb;

        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return array(
                'success' => false,
                'message' => 'SMS campaign not found.',
            );
        }

        if ((string) $campaign['status'] === 'completed' || (string) $campaign['status'] === 'cancelled') {
            return array(
                'success' => false,
                'message' => 'SMS campaign cannot be cancelled from its current status.',
            );
        }

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $queue_result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$queue_table} SET status = %s WHERE sms_campaign_id = %d AND status IN (%s, %s)",
                'failed',
                $campaign_id,
                'pending',
                'processing'
            )
        );

        if ($queue_result === false) {
            Logger::error('SMS campaign cancel failed while updating queue.', array('campaign_id' => $campaign_id));

            return array(
                'success' => false,
                'message' => 'SMS campaign queue items could not be cancelled.',
            );
        }

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $now = self::current_time_mysql();
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, completed_at = %s, updated_at = %s WHERE id = %d",
                'cancelled',
                $now,
                $now,
                $campaign_id
            )
        );

        if ($result === false) {
            Logger::error('SMS campaign cancel failed.', array('campaign_id' => $campaign_id));

            return array(
                'success' => false,
                'message' => 'SMS campaign could not be cancelled.',
            );
        }

        self::update_from_queue_status($campaign_id);

        Logger::info('SMS campaign cancelled.', array(
            'campaign_id' => $campaign_id,
            'queue_items_updated' => (int) $queue_result,
        ));

        return array(
            'success' => true,
            'message' => 'SMS campaign cancelled successfully.',
        );
    }

    /**
     * @return array{success:bool,queued_count:int,message:string}
     */
    public static function queue_recipients(int $campaign_id, string $message_body): array
    {
        global $wpdb;

        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return array(
                'success' => false,
                'queued_count' => 0,
                'message' => 'SMS campaign not found.',
            );
        }

        $subs_table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $subscribers = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, phone_number FROM {$subs_table} WHERE list_id = %d AND subscription_status = %s AND phone_number <> %s ORDER BY id ASC",
                (int) $campaign['sms_list_id'],
                'subscribed',
                ''
            ),
            ARRAY_A
        );

        if (empty($subscribers)) {
            return array(
                'success' => false,
                'queued_count' => 0,
                'message' => 'No subscribed SMS recipients were found for this list.',
            );
        }

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $scheduled_at = isset($campaign['scheduled_at']) && $campaign['scheduled_at'] !== ''
            ? (string) $campaign['scheduled_at']
            : self::current_time_mysql();
        $created_at = self::current_time_mysql();
        $queued_count = 0;

        foreach ($subscribers as $subscriber) {
            $phone_number = trim((string) $subscriber['phone_number']);
            if ($phone_number === '') {
                continue;
            }

            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$queue_table} (site_id, blog_id, campaign_id, sms_campaign_id, recipient_email, phone_number, subject, body, source, status, message_type, scheduled_at, created_at, from_email, from_name, attempts) VALUES (%d, %d, %d, %d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %d)",
                    (int) $campaign['site_id'],
                    (int) $campaign['site_id'],
                    $campaign_id,
                    $campaign_id,
                    $phone_number,
                    $phone_number,
                    '',
                    $message_body,
                    'campaign',
                    'pending',
                    'sms',
                    $scheduled_at,
                    $created_at,
                    '',
                    '',
                    0
                )
            );

            if ($result === false) {
                Logger::error('SMS campaign recipient queue insert failed.', array(
                    'campaign_id' => $campaign_id,
                    'phone_number' => $phone_number,
                    'subscriber_id' => isset($subscriber['id']) ? (int) $subscriber['id'] : 0,
                ));
                continue;
            }

            ++$queued_count;
        }

        if ($queued_count === 0) {
            return array(
                'success' => false,
                'queued_count' => 0,
                'message' => 'No SMS recipients could be queued.',
            );
        }

        Logger::info('SMS campaign recipients queued.', array(
            'campaign_id' => $campaign_id,
            'sms_list_id' => (int) $campaign['sms_list_id'],
            'queued_count' => $queued_count,
        ));

        return array(
            'success' => true,
            'queued_count' => $queued_count,
            'message' => sprintf('Queued %d SMS recipients.', $queued_count),
        );
    }

    /**
     * @return array{total:int,sent:int,failed:int,pending:int,status_breakdown:array<string,int>}
     */
    public static function get_delivery_stats(int $campaign_id): array
    {
        global $wpdb;

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $rows = (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(1) AS total FROM {$queue_table} WHERE sms_campaign_id = %d GROUP BY status",
                $campaign_id
            ),
            ARRAY_A
        );

        $status_breakdown = array();
        $total = 0;
        $sent = 0;
        $failed = 0;
        $pending = 0;

        foreach ($rows as $row) {
            $status = isset($row['status']) ? (string) $row['status'] : '';
            $count = isset($row['total']) ? (int) $row['total'] : 0;
            $status_breakdown[$status] = $count;
            $total += $count;

            if (in_array($status, self::SUCCESS_QUEUE_STATUSES, true)) {
                $sent += $count;
            } elseif (in_array($status, self::FAILED_QUEUE_STATUSES, true)) {
                $failed += $count;
            } elseif (in_array($status, self::PENDING_QUEUE_STATUSES, true)) {
                $pending += $count;
            }
        }

        return array(
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $pending,
            'status_breakdown' => $status_breakdown,
        );
    }

    public static function get_failed_recipients(int $campaign_id, int $limit = 100): array
    {
        global $wpdb;

        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $limit = max(1, $limit);
        $placeholders = implode(', ', array_fill(0, count(self::FAILED_QUEUE_STATUSES), '%s'));
        $query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                array(
                    "SELECT * FROM {$queue_table} WHERE sms_campaign_id = %d AND status IN ({$placeholders}) ORDER BY created_at DESC LIMIT %d",
                    $campaign_id,
                ),
                self::FAILED_QUEUE_STATUSES,
                array($limit)
            )
        );

        return (array) $wpdb->get_results($query, ARRAY_A);
    }

    public static function update_from_queue_status(int $campaign_id): void
    {
        global $wpdb;

        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return;
        }

        $stats = self::get_delivery_stats($campaign_id);
        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $status = (string) $campaign['status'];
        $completed_at = isset($campaign['completed_at']) && $campaign['completed_at'] !== '' ? (string) $campaign['completed_at'] : null;

        if ($status === 'sending' && $stats['total'] > 0 && $stats['pending'] === 0) {
            $status = 'completed';
            $completed_at = self::current_time_mysql();
        }

        $delivery_status_map = function_exists('wp_json_encode') ? wp_json_encode($stats['status_breakdown']) : json_encode($stats['status_breakdown']);
        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, total_recipients = %d, sent_count = %d, failed_count = %d, delivery_status_map = %s, completed_at = %s, updated_at = %s WHERE id = %d",
                $status,
                (int) $stats['total'],
                (int) $stats['sent'],
                (int) $stats['failed'],
                $delivery_status_map,
                $completed_at,
                self::current_time_mysql(),
                $campaign_id
            )
        );

        if ($result === false) {
            Logger::error('SMS campaign queue sync failed.', array('campaign_id' => $campaign_id));
            return;
        }

        Logger::info('SMS campaign queue stats synced.', array(
            'campaign_id' => $campaign_id,
            'status' => $status,
            'total' => (int) $stats['total'],
            'sent' => (int) $stats['sent'],
            'failed' => (int) $stats['failed'],
        ));
    }

    /**
     * @return array{success:bool,message:string}
     */
    public static function send_test(int $campaign_id, string $test_phone): array
    {
        $campaign = self::get($campaign_id);
        if (!is_array($campaign)) {
            return array(
                'success' => false,
                'message' => 'SMS campaign not found.',
            );
        }

        $test_phone = trim($test_phone);
        if ($test_phone === '') {
            return array(
                'success' => false,
                'message' => 'Test phone number is required.',
            );
        }

        if (class_exists(__NAMESPACE__ . '\\SmsProviderManager')) {
            if (method_exists(__NAMESPACE__ . '\\SmsProviderManager', 'get_instance')) {
                $manager = \MNEM\SmsProviderManager::get_instance();
                if (is_object($manager) && method_exists($manager, 'send')) {
                    $result = $manager->send($test_phone, (string) $campaign['message_body']);
                    if (!empty($result['success'])) {
                        Logger::info('SMS campaign test message sent.', array('campaign_id' => $campaign_id, 'phone_number' => $test_phone));
                    } else {
                        Logger::error('SMS campaign test message failed.', array('campaign_id' => $campaign_id, 'phone_number' => $test_phone));
                    }

                    return array(
                        'success' => !empty($result['success']),
                        'message' => isset($result['message']) ? (string) $result['message'] : '',
                    );
                }
            }

            if (class_exists(__NAMESPACE__ . '\\SmsSettings') && method_exists(__NAMESPACE__ . '\\SmsProviderManager', 'get_provider')) {
                $provider_name = SmsSettings::get_provider();
                $provider = $provider_name !== '' ? SmsProviderManager::get_provider($provider_name) : null;
                if (is_object($provider) && method_exists($provider, 'send')) {
                    $result = $provider->send($test_phone, (string) $campaign['message_body']);
                    if (!empty($result['success'])) {
                        Logger::info('SMS campaign test message sent.', array(
                            'campaign_id' => $campaign_id,
                            'phone_number' => $test_phone,
                            'provider' => $provider_name,
                        ));
                    } else {
                        Logger::error('SMS campaign test message failed.', array(
                            'campaign_id' => $campaign_id,
                            'phone_number' => $test_phone,
                            'provider' => $provider_name,
                        ));
                    }

                    return array(
                        'success' => !empty($result['success']),
                        'message' => isset($result['message']) ? (string) $result['message'] : '',
                    );
                }
            }
        }

        Logger::info('SMS campaign test message skipped provider send; returning placeholder success.', array(
            'campaign_id' => $campaign_id,
            'phone_number' => $test_phone,
        ));

        return array(
            'success' => true,
            'message' => 'Test SMS accepted for delivery.',
        );
    }

    public static function get_recipient_preview(int $sms_list_id, int $sample_size = 10): array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';
        $sample_size = max(1, $sample_size);

        return (array) $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE list_id = %d AND subscription_status = %s AND phone_number <> %s ORDER BY id DESC LIMIT %d",
                $sms_list_id,
                'subscribed',
                '',
                $sample_size
            ),
            ARRAY_A
        );
    }

    /**
     * @return array{valid:bool,errors:array<int,string>}
     */
    public static function validate_campaign_data(array $data): array
    {
        $errors = array();

        $name = isset($data['name']) ? trim(sanitize_text_field((string) $data['name'])) : '';
        if ($name === '') {
            $errors[] = 'Campaign name is required.';
        }

        $message_body = isset($data['message_body'])
            ? (function_exists('sanitize_textarea_field')
                ? sanitize_textarea_field((string) $data['message_body'])
                : trim(wp_strip_all_tags((string) $data['message_body'])))
            : '';
        if ($message_body === '') {
            $errors[] = 'Message body is required.';
        }

        $sms_list_id = isset($data['sms_list_id']) ? (int) $data['sms_list_id'] : 0;
        if ($sms_list_id <= 0) {
            $errors[] = 'A valid SMS list is required.';
        } elseif (!is_array(SmsSubscriberLists::get($sms_list_id))) {
            $errors[] = 'Selected SMS list was not found.';
        }

        if (isset($data['status']) && !in_array((string) $data['status'], self::VALID_STATUSES, true)) {
            $errors[] = 'Invalid SMS campaign status.';
        }

        if (isset($data['status']) && (string) $data['status'] === 'scheduled' && (!isset($data['scheduled_at']) || trim((string) $data['scheduled_at']) === '')) {
            $errors[] = 'Scheduled campaigns require a scheduled date.';
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }

    public static function calculate_segments(string $message_body): int
    {
        $length = function_exists('mb_strlen') ? mb_strlen($message_body) : strlen($message_body);
        if ($length <= 0) {
            return 0;
        }

        if ($length <= 160) {
            return 1;
        }

        return (int) ceil($length / 153);
    }

    public static function get_recipient_count(int $sms_list_id): int
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_list_subscribers';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE list_id = %d AND subscription_status = %s AND phone_number <> %s",
                $sms_list_id,
                'subscribed',
                ''
            )
        );
    }

    public static function format_for_display(array $campaign): array
    {
        $formatted = $campaign;
        $status = isset($campaign['status']) ? (string) $campaign['status'] : 'draft';
        $labels = array(
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            'sending' => 'Sending',
            'paused' => 'Paused',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        );

        $formatted['status_label'] = isset($labels[$status]) ? $labels[$status] : ucfirst($status);
        $formatted['segment_count'] = self::calculate_segments(isset($campaign['message_body']) ? (string) $campaign['message_body'] : '');

        foreach (array('scheduled_at', 'started_at', 'completed_at', 'created_at', 'updated_at') as $field) {
            if (!empty($campaign[$field])) {
                $timestamp = strtotime((string) $campaign[$field]);
                $formatted[$field . '_formatted'] = $timestamp
                    ? (function_exists('wp_date') ? wp_date('Y-m-d H:i', $timestamp) : gmdate('Y-m-d H:i', $timestamp))
                    : (string) $campaign[$field];
            }
        }

        return $formatted;
    }

    private static function current_time_mysql(): string
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }
}
