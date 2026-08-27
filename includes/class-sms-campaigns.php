<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * SmsCampaigns model class.
 *
 * Handles CRUD operations, lifecycle management, queue integration,
 * and delivery tracking for SMS campaigns.
 */
class SmsCampaigns
{
    public const VALID_STATUSES = array('draft', 'scheduled', 'sending', 'paused', 'completed', 'cancelled');

    public const VALID_TRANSITIONS = array(
        'draft'     => array('scheduled', 'cancelled'),
        'scheduled' => array('sending', 'cancelled'),
        'sending'   => array('paused', 'completed', 'cancelled'),
        'paused'    => array('sending', 'cancelled'),
        'completed' => array(),
        'cancelled' => array(),
    );

    // ---------------------------------------------------------------------------
    // CRUD
    // ---------------------------------------------------------------------------

    /**
     * Create a new SMS campaign.
     *
     * @param int   $site_id
     * @param array $data  Array with keys: name, message_body, sms_list_id,
     *                     and optional description, status, scheduled_at, created_by.
     * @return int|false  Insert ID on success, false on failure.
     */
    public static function create(int $site_id, array $data)
    {
        global $wpdb;

        $name         = isset($data['name']) ? sanitize_text_field((string) $data['name']) : '';
        $message_body = isset($data['message_body'])
            ? (function_exists('sanitize_textarea_field')
                ? sanitize_textarea_field((string) $data['message_body'])
                : (string) $data['message_body'])
            : '';
        $sms_list_id  = isset($data['sms_list_id']) ? (int) $data['sms_list_id'] : 0;
        $created_by   = isset($data['created_by'])
            ? (int) $data['created_by']
            : (function_exists('get_current_user_id') ? (int) get_current_user_id() : 0);

        $validation = self::validate_campaign_data(array(
            'name'         => $name,
            'message_body' => $message_body,
            'sms_list_id'  => $sms_list_id,
        ));

        if (!$validation['valid']) {
            return false;
        }

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $now   = self::current_time_mysql();

        $status = isset($data['status']) && in_array($data['status'], self::VALID_STATUSES, true)
            ? $data['status']
            : 'draft';

        $scheduled_at = isset($data['scheduled_at']) && $data['scheduled_at'] !== ''
            ? sanitize_text_field((string) $data['scheduled_at'])
            : null;

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, name, description, message_body, sms_list_id, status, scheduled_at, created_by, created_at, updated_at) VALUES (%d, %s, %s, %s, %d, %s, %s, %d, %s, %s)",
                $site_id,
                $name,
                isset($data['description']) ? (string) $data['description'] : '',
                (string) $message_body,
                $sms_list_id,
                $status,
                $scheduled_at,
                $created_by,
                $now,
                $now
            )
        );

        if ($result === false) {
            return false;
        }

        $insert_id = isset($wpdb->insert_id) ? (int) $wpdb->insert_id : 0;

        Logger::info('SMS campaign created.', array(
            'campaign_id' => $insert_id,
            'site_id'     => $site_id,
            'name'        => $name,
            'created_by'  => $created_by,
        ));

        return $insert_id > 0 ? $insert_id : false;
    }

    /**
     * Get a single SMS campaign by ID.
     *
     * @param int $id
     * @return array|null
     */
    public static function get(int $id)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Get a paginated list of SMS campaigns.
     *
     * @param int    $site_id
     * @param string $status  Optional status filter.
     * @param int    $limit
     * @param int    $offset
     * @return array
     */
    public static function get_list(int $site_id, string $status = '', int $limit = 50, int $offset = 0)
    {
        global $wpdb;

        $table  = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $limit  = max(1, $limit);
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

    /**
     * Update an SMS campaign.
     *
     * @param int   $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data)
    {
        global $wpdb;

        $campaign = self::get($id);
        if (!$campaign) {
            return false;
        }

        if (in_array((string) $campaign['status'], array('cancelled', 'completed'), true)) {
            return false;
        }

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';

        $name = isset($data['name'])
            ? sanitize_text_field((string) $data['name'])
            : (string) $campaign['name'];

        $message_body = isset($data['message_body'])
            ? (string) $data['message_body']
            : (string) $campaign['message_body'];

        $sms_list_id = isset($data['sms_list_id'])
            ? (int) $data['sms_list_id']
            : (int) $campaign['sms_list_id'];

        $status = isset($data['status']) && in_array($data['status'], self::VALID_STATUSES, true)
            ? $data['status']
            : (string) $campaign['status'];

        $scheduled_at = isset($data['scheduled_at']) && $data['scheduled_at'] !== ''
            ? sanitize_text_field((string) $data['scheduled_at'])
            : null;

        $description = isset($data['description'])
            ? (string) $data['description']
            : (string) $campaign['description'];

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET name = %s, description = %s, message_body = %s, sms_list_id = %d, status = %s, scheduled_at = %s, updated_at = %s WHERE id = %d",
                $name,
                $description,
                $message_body,
                $sms_list_id,
                $status,
                $scheduled_at,
                self::current_time_mysql(),
                $id
            )
        ) !== false;
    }

    /**
     * Delete an SMS campaign.
     *
     * @param int $id
     * @return bool
     */
    public static function delete(int $id)
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';

        Logger::info('SMS campaign deleted.', array('campaign_id' => $id));

        return $wpdb->query(
            $wpdb->prepare("DELETE FROM {$table} WHERE id = %d", $id)
        ) !== false;
    }

    // ---------------------------------------------------------------------------
    // Status helpers
    // ---------------------------------------------------------------------------

    /**
     * Update campaign status, respecting valid transitions.
     *
     * @param int    $id
     * @param string $new_status
     * @return bool
     */
    public static function update_status(int $id, string $new_status)
    {
        if (!in_array($new_status, self::VALID_STATUSES, true)) {
            return false;
        }

        $campaign = self::get($id);
        if (!$campaign || !self::is_valid_transition((string) $campaign['status'], $new_status)) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';
        $now   = self::current_time_mysql();

        // Only set started_at the first time the campaign enters 'sending' state.
        if ($new_status === 'sending' && $campaign['started_at'] === null) {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, started_at = %s, updated_at = %s WHERE id = %d",
                    $new_status,
                    $now,
                    $now,
                    $id
                )
            );
        } elseif ($new_status === 'completed') {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, completed_at = %s, updated_at = %s WHERE id = %d",
                    $new_status,
                    $now,
                    $now,
                    $id
                )
            );
        } else {
            $result = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s, updated_at = %s WHERE id = %d",
                    $new_status,
                    $now,
                    $id
                )
            );
        }

        return $result !== false;
    }

    /**
     * Check whether a status transition is valid.
     *
     * @param string $current
     * @param string $next
     * @return bool
     */
    public static function is_valid_transition(string $current, string $next)
    {
        return isset(self::VALID_TRANSITIONS[$current])
            && in_array($next, self::VALID_TRANSITIONS[$current], true);
    }

    // ---------------------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------------------

    /**
     * Send an SMS campaign immediately.
     *
     * @param int $id
     * @return array  Result with 'success' and 'message' keys.
     */
    public static function send_now(int $id)
    {
        $campaign = self::get($id);
        if (!$campaign) {
            return array('success' => false, 'message' => 'SMS campaign not found.');
        }

        if (in_array((string) $campaign['status'], array('sending', 'completed', 'cancelled'), true)) {
            return array('success' => false, 'message' => 'SMS campaign cannot be sent from its current status.');
        }

        if ((string) $campaign['status'] === 'draft') {
            self::update_status($id, 'scheduled');
            $campaign = self::get($id);
        }

        if (!$campaign || (string) $campaign['status'] !== 'scheduled') {
            return array('success' => false, 'message' => 'SMS campaign must be scheduled before sending.');
        }

        $queued = self::queue_recipients($id);

        if ($queued === false) {
            return array('success' => false, 'message' => 'Failed to queue SMS campaign recipients.');
        }

        self::update_status($id, 'sending');

        Logger::info('SMS campaign send initiated.', array(
            'campaign_id' => $id,
            'queued'      => $queued,
        ));

        return array('success' => true, 'message' => 'SMS campaign send has been queued.', 'queued' => $queued);
    }

    /**
     * Schedule an SMS campaign for later delivery.
     *
     * @param int    $id
     * @param string $scheduled_at MySQL datetime string.
     * @return bool
     */
    public static function schedule(int $id, string $scheduled_at)
    {
        $campaign = self::get($id);
        if (!$campaign || (string) $campaign['status'] !== 'draft') {
            return false;
        }

        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = 'scheduled', scheduled_at = %s, updated_at = %s WHERE id = %d",
                sanitize_text_field($scheduled_at),
                self::current_time_mysql(),
                $id
            )
        );

        if ($result !== false) {
            Logger::info('SMS campaign scheduled.', array('campaign_id' => $id, 'scheduled_at' => $scheduled_at));
        }

        return $result !== false;
    }

    /**
     * Pause a sending SMS campaign.
     *
     * @param int $id
     * @return bool
     */
    public static function pause(int $id)
    {
        $result = self::update_status($id, 'paused');
        if ($result) {
            Logger::info('SMS campaign paused.', array('campaign_id' => $id));
        }
        return $result;
    }

    /**
     * Resume a paused SMS campaign.
     *
     * @param int $id
     * @return bool
     */
    public static function resume(int $id)
    {
        $result = self::update_status($id, 'sending');
        if ($result) {
            Logger::info('SMS campaign resumed.', array('campaign_id' => $id));
        }
        return $result;
    }

    /**
     * Cancel an SMS campaign.
     *
     * @param int $id
     * @return bool
     */
    public static function cancel(int $id)
    {
        $result = self::update_status($id, 'cancelled');
        if ($result) {
            Logger::info('SMS campaign cancelled.', array('campaign_id' => $id));
        }
        return $result;
    }

    /**
     * Check whether a campaign can currently be cancelled.
     *
     * @param int $id
     * @return bool
     */
    public static function can_cancel(int $id)
    {
        $campaign = self::get($id);
        if (!$campaign) {
            return false;
        }
        return self::is_valid_transition((string) $campaign['status'], 'cancelled');
    }

    // ---------------------------------------------------------------------------
    // Queue integration
    // ---------------------------------------------------------------------------

    /**
     * Queue all recipients for an SMS campaign.
     *
     * @param int $id
     * @return int|false  Number of items queued, or false on failure.
     */
    public static function queue_recipients(int $id)
    {
        global $wpdb;

        $campaign = self::get($id);
        if (!$campaign) {
            return false;
        }

        $sms_list_id = (int) $campaign['sms_list_id'];
        $queue_table = $wpdb->base_prefix . 'mnem_queue';
        $subscribers = SmsSubscriberLists::get_all_subscribers_mixed($sms_list_id, 100000, 0);

        if (empty($subscribers)) {
            return 0;
        }

        $queued  = 0;
        $site_id = (int) $campaign['site_id'];
        $now     = self::current_time_mysql();

        foreach ($subscribers as $subscriber) {
            $phone  = (string) $subscriber['phone_number'];
            $result = $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$queue_table} (site_id, phone_number, body, status, message_type, sms_campaign_id, created_at, updated_at) VALUES (%d, %s, %s, 'pending', 'sms', %d, %s, %s)",
                    $site_id,
                    $phone,
                    (string) $campaign['message_body'],
                    $id,
                    $now,
                    $now
                )
            );

            if ($result !== false) {
                ++$queued;
            }
        }

        self::update_delivery_tracking($id, $queued, 0, 0, $now);

        return $queued;
    }

    /**
     * Update delivery tracking counters for a campaign.
     *
     * @param int    $id
     * @param int    $total_recipients
     * @param int    $sent_count
     * @param int    $failed_count
     * @param string $started_at
     * @return bool
     */
    public static function update_delivery_tracking(int $id, int $total_recipients, int $sent_count, int $failed_count, string $started_at = '')
    {
        global $wpdb;
        $table = $wpdb->base_prefix . 'mnem_sms_campaigns';

        if ($started_at !== '') {
            return $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET total_recipients = %d, sent_count = %d, failed_count = %d, started_at = COALESCE(started_at, %s), updated_at = %s WHERE id = %d",
                    $total_recipients,
                    $sent_count,
                    $failed_count,
                    $started_at,
                    self::current_time_mysql(),
                    $id
                )
            ) !== false;
        }

        return $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET total_recipients = %d, sent_count = %d, failed_count = %d, updated_at = %s WHERE id = %d",
                $total_recipients,
                $sent_count,
                $failed_count,
                self::current_time_mysql(),
                $id
            )
        ) !== false;
    }

    // ---------------------------------------------------------------------------
    // Stats & reporting
    // ---------------------------------------------------------------------------

    /**
     * Get delivery statistics for a campaign.
     *
     * @param int $id
     * @return array
     */
    public static function get_delivery_stats(int $id)
    {
        $campaign = self::get($id);
        if (!$campaign) {
            return array();
        }

        return array(
            'total_recipients' => (int) $campaign['total_recipients'],
            'sent_count'       => (int) $campaign['sent_count'],
            'failed_count'     => (int) $campaign['failed_count'],
            'bounce_count'     => (int) $campaign['bounce_count'],
            'pending_count'    => max(0, (int) $campaign['total_recipients'] - (int) $campaign['sent_count'] - (int) $campaign['failed_count']),
            'status'           => (string) $campaign['status'],
        );
    }

    // ---------------------------------------------------------------------------
    // Preview & test
    // ---------------------------------------------------------------------------

    /**
     * Get a preview of recipients for a campaign (first N subscribers).
     *
     * @param int $id
     * @param int $limit
     * @return array
     */
    public static function get_recipient_preview(int $id, int $limit = 20)
    {
        global $wpdb;

        $campaign = self::get($id);
        if (!$campaign) {
            return array();
        }

        $sms_list_id = (int) $campaign['sms_list_id'];
        $limit       = max(1, min(100, $limit));
        return SmsSubscriberLists::get_all_subscribers_mixed($sms_list_id, $limit, 0);
    }

    /**
     * Get total recipient count for a campaign.
     *
     * @param int $id
     * @return int
     */
    public static function get_recipient_count(int $id)
    {
        global $wpdb;

        $campaign = self::get($id);
        if (!$campaign) {
            return 0;
        }

        $sms_list_id = (int) $campaign['sms_list_id'];
        return SmsSubscriberLists::get_list_subscribers_count($sms_list_id);
    }

    /**
     * Send a test SMS for a campaign.
     *
     * @param int    $id
     * @param string $phone_number
     * @return array  Result with 'success' and 'message' keys.
     */
    public static function send_test(int $id, string $phone_number)
    {
        $campaign = self::get($id);
        if (!$campaign) {
            return array('success' => false, 'message' => 'SMS campaign not found.');
        }

        $phone_number = sanitize_text_field($phone_number);
        if ($phone_number === '') {
            return array('success' => false, 'message' => 'Phone number is required.');
        }

        $message = (string) $campaign['message_body'];

        if (!class_exists('\MNEM\SmsProviderManager')) {
            return array('success' => false, 'message' => 'SMS provider manager not available.');
        }

        $provider = \MNEM\SmsProviderManager::get_active_provider();
        if (!$provider) {
            return array('success' => false, 'message' => 'No SMS provider configured.');
        }

        $result = $provider->send_sms($phone_number, $message);

        if ($result) {
            Logger::info('SMS campaign test sent.', array(
                'campaign_id'  => $id,
                'phone_number' => $phone_number,
            ));
            return array('success' => true, 'message' => 'Test SMS sent successfully.');
        }

        return array('success' => false, 'message' => 'Failed to send test SMS. Check provider configuration.');
    }

    // ---------------------------------------------------------------------------
    // Validation & helpers
    // ---------------------------------------------------------------------------

    /**
     * Validate campaign data.
     *
     * @param array $data
     * @return array  Array with 'valid' (bool) and 'errors' (array).
     */
    public static function validate_campaign_data(array $data)
    {
        $errors = array();

        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        if ($name === '') {
            $errors[] = 'Campaign name is required.';
        } elseif (mb_strlen($name) > 255) {
            $errors[] = 'Campaign name must be 255 characters or fewer.';
        }

        $message_body = isset($data['message_body']) ? trim((string) $data['message_body']) : '';
        if ($message_body === '') {
            $errors[] = 'Message body is required.';
        }

        if (isset($data['sms_list_id']) && (int) $data['sms_list_id'] <= 0) {
            $errors[] = 'A valid SMS subscriber list must be selected.';
        }

        return array(
            'valid'  => empty($errors),
            'errors' => $errors,
        );
    }

    /**
     * Calculate the number of SMS segments for a message.
     *
     * Standard SMS is 160 chars (GSM-7). Unicode messages use 70 chars/segment.
     * Multi-part concatenation headers reduce usable chars to 153 / 67.
     *
     * @param string $message
     * @return array  Array with 'segments', 'chars', 'chars_per_segment'.
     */
    public static function calculate_segments(string $message)
    {
        $length = mb_strlen($message);

        // Check for non-GSM-7 characters (simple heuristic: any char > 0x7F or special).
        $is_unicode = (bool) preg_match('/[^\x00-\x7F]/', $message);

        if ($is_unicode) {
            $single_max = 70;
            $multi_max  = 67;
        } else {
            $single_max = 160;
            $multi_max  = 153;
        }

        if ($length <= $single_max) {
            return array(
                'segments'         => 1,
                'chars'            => $length,
                'chars_per_segment' => $single_max,
            );
        }

        $segments = (int) ceil($length / $multi_max);

        return array(
            'segments'         => $segments,
            'chars'            => $length,
            'chars_per_segment' => $multi_max,
        );
    }

    /**
     * Format a campaign array for display.
     *
     * @param array $campaign
     * @return array
     */
    public static function format_for_display(array $campaign)
    {
        $campaign['total_recipients'] = (int) ($campaign['total_recipients'] ?? 0);
        $campaign['sent_count']       = (int) ($campaign['sent_count'] ?? 0);
        $campaign['failed_count']     = (int) ($campaign['failed_count'] ?? 0);
        $campaign['bounce_count']     = (int) ($campaign['bounce_count'] ?? 0);
        $campaign['pending_count']    = max(0, $campaign['total_recipients'] - $campaign['sent_count'] - $campaign['failed_count']);

        return $campaign;
    }

    // ---------------------------------------------------------------------------
    // Utilities
    // ---------------------------------------------------------------------------

    /**
     * Get current MySQL timestamp.
     *
     * @return string
     */
    private static function current_time_mysql()
    {
        return function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
    }
}
