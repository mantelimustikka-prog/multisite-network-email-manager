<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * Centralized error logging for email send failures, provider errors,
 * queue errors, system errors, and validation errors.
 */
class ErrorLog
{
    // Error levels
    public const LEVEL_ERROR    = 'error';
    public const LEVEL_WARNING  = 'warning';
    public const LEVEL_CRITICAL = 'critical';

    // Size limits (in bytes / characters)
    public const MAX_ERROR_MESSAGE = 5000;
    public const MAX_SYSTEM_ERROR  = 3000;
    public const MAX_STACK_TRACE   = 10000;
    public const MAX_PROVIDER_ERROR = 2000;
    public const MAX_API_RESPONSE   = 20000;
    public const MAX_CONTEXT_JSON   = 10000;
    public const MAX_SUBJECT        = 1000;
    public const MAX_RECIPIENT_EMAIL = 255;
    public const MAX_SENDER_EMAIL    = 255;

    // Retention policy
    public const ERROR_LOG_RETENTION_DAYS = 30;

    // Error types
    public const TYPE_SEND_FAILED       = 'send_failed';
    public const TYPE_PROVIDER_ERROR    = 'provider_error';
    public const TYPE_QUEUE_ERROR       = 'queue_processing';
    public const TYPE_SYSTEM_ERROR      = 'system_error';
    public const TYPE_VALIDATION_ERROR  = 'validation_error';
    public const TYPE_DB_ERROR          = 'db_error';

    /**
     * Log a send failure.
     *
     * @param int    $queue_id
     * @param string $recipient_email
     * @param string $sender_email
     * @param string $subject
     * @param string $provider_type
     * @param string $error_message
     * @param array<string,mixed> $context
     */
    public static function log_send_failure(
        int $queue_id,
        string $recipient_email,
        string $sender_email,
        string $subject,
        string $provider_type,
        string $error_message,
        array $context = array()
    ): void {
        $http_status  = isset($context['http_status']) ? (int) $context['http_status'] : 0;
        $api_response = isset($context['api_response']) ? (string) $context['api_response'] : '';
        $prov_error   = isset($context['provider_error_message']) ? (string) $context['provider_error_message'] : '';
        $prov_code    = isset($context['provider_error_code']) ? (string) $context['provider_error_code'] : '';

        self::insert(array(
            'error_level'            => self::LEVEL_ERROR,
            'error_type'             => self::TYPE_SEND_FAILED,
            'error_message'          => $error_message,
            'queue_id'               => $queue_id,
            'recipient_email'        => $recipient_email,
            'sender_email'           => $sender_email,
            'subject'                => $subject,
            'provider_type'          => $provider_type,
            'http_status_code'       => $http_status,
            'api_response'           => $api_response,
            'provider_error_code'    => $prov_code,
            'provider_error_message' => $prov_error,
            'context'                => $context,
        ));
    }

    /**
     * Log a provider API error.
     *
     * @param string $provider_type
     * @param string $error_message
     * @param int    $http_status
     * @param string $api_response
     * @param string $recipient_email
     * @param array<string,mixed> $context
     */
    public static function log_provider_error(
        string $provider_type,
        string $error_message,
        int $http_status = 0,
        string $api_response = '',
        string $recipient_email = '',
        array $context = array()
    ): void {
        $prov_error = isset($context['provider_error_message']) ? (string) $context['provider_error_message'] : '';
        $prov_code  = isset($context['provider_error_code']) ? (string) $context['provider_error_code'] : '';

        self::insert(array(
            'error_level'            => self::LEVEL_ERROR,
            'error_type'             => self::TYPE_PROVIDER_ERROR,
            'error_message'          => $error_message,
            'recipient_email'        => $recipient_email,
            'provider_type'          => $provider_type,
            'http_status_code'       => $http_status,
            'api_response'           => $api_response,
            'provider_error_code'    => $prov_code,
            'provider_error_message' => $prov_error,
            'context'                => $context,
        ));
    }

    /**
     * Log a queue processing error.
     *
     * @param int    $queue_id
     * @param string $error_message
     * @param string|null $system_error  PHP exception or DB error message
     * @param array<string,mixed> $context
     */
    public static function log_queue_error(
        int $queue_id,
        string $error_message,
        ?string $system_error = null,
        array $context = array()
    ): void {
        self::insert(array(
            'error_level'   => self::LEVEL_ERROR,
            'error_type'    => self::TYPE_QUEUE_ERROR,
            'error_message' => $error_message,
            'system_error'  => $system_error,
            'queue_id'      => $queue_id,
            'context'       => $context,
        ));
    }

    /**
     * Log a PHP/database/system error, optionally from a Throwable.
     *
     * @param string         $error_message
     * @param \Throwable|null $exception
     * @param string         $error_type
     * @param array<string,mixed> $context
     */
    public static function log_system_error(
        string $error_message,
        ?\Throwable $exception = null,
        string $error_type = self::TYPE_SYSTEM_ERROR,
        array $context = array()
    ): void {
        $system_error  = null;
        $stack_trace   = null;
        $error_code    = null;

        if ($exception !== null) {
            $system_error = get_class($exception) . ': ' . $exception->getMessage();
            $stack_trace  = $exception->getTraceAsString();
            $error_code   = (string) $exception->getCode();
        }

        self::insert(array(
            'error_level'   => self::LEVEL_ERROR,
            'error_type'    => $error_type,
            'error_code'    => $error_code,
            'error_message' => $error_message,
            'system_error'  => $system_error,
            'stack_trace'   => $stack_trace,
            'context'       => $context,
        ));
    }

    /**
     * Log a validation error.
     *
     * @param string $error_message
     * @param string $field
     * @param string $value
     * @param array<string,mixed> $context
     */
    public static function log_validation_error(
        string $error_message,
        string $field,
        string $value,
        array $context = array()
    ): void {
        $context = array_merge($context, array('field' => $field, 'value' => $value));
        self::insert(array(
            'error_level'   => self::LEVEL_WARNING,
            'error_type'    => self::TYPE_VALIDATION_ERROR,
            'error_message' => $error_message,
            'context'       => $context,
        ));
    }

    /**
     * Retrieve error logs with optional filters.
     *
     * @param array<string,mixed> $filters  Supported keys: error_level, error_type, provider_type,
     *                                       recipient_email, queue_id, campaign_id, date_from,
     *                                       date_to, search
     * @param int $limit
     * @param int $offset
     * @return array<int,array<string,mixed>>
     */
    public static function get_logs(array $filters = array(), int $limit = 50, int $offset = 0): array
    {
        global $wpdb;

        $table  = $wpdb->prefix . 'mnem_error_logs';
        $clause = self::build_where_clause($filters);
        $where_sql = implode(' AND ', $clause['where']);
        $params    = $clause['params'];
        $params[]  = max(1, $limit);
        $params[]  = max(0, $offset);

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql  = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results(
            $wpdb->prepare($sql, $params), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            ARRAY_A
        );

        return is_array($rows) ? $rows : array();
    }

    /**
     * @param int $queue_id
     * @return array<int,array<string,mixed>>
     */
    public static function get_logs_for_queue(int $queue_id): array
    {
        return self::get_logs(array('queue_id' => $queue_id), 100, 0);
    }

    /**
     * @param int $campaign_id
     * @return array<int,array<string,mixed>>
     */
    public static function get_logs_for_campaign(int $campaign_id): array
    {
        return self::get_logs(array('campaign_id' => $campaign_id), 100, 0);
    }

    /**
     * @param string $email
     * @return array<int,array<string,mixed>>
     */
    public static function get_logs_for_recipient(string $email): array
    {
        return self::get_logs(array('recipient_email' => $email), 100, 0);
    }

    /**
     * Count logs matching filters.
     *
     * @param array<string,mixed> $filters
     * @return int
     */
    public static function count_logs(array $filters = array()): int
    {
        global $wpdb;

        $table     = $wpdb->prefix . 'mnem_error_logs';
        $clause    = self::build_where_clause($filters);
        $where_sql = implode(' AND ', $clause['where']);
        $params    = $clause['params'];

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(1) FROM {$table} WHERE {$where_sql}";

        if (!empty($params)) {
            $count = (int) $wpdb->get_var($wpdb->prepare($sql, $params)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        } else {
            $count = (int) $wpdb->get_var($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        return $count;
    }

    /**
     * Get a single log entry by ID.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public static function get_log(int $id): ?array
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_error_logs';
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Delete a single log entry.
     *
     * @param int $id
     * @return bool
     */
    public static function delete_log(int $id): bool
    {
        global $wpdb;

        $table  = $wpdb->prefix . 'mnem_error_logs';
        $result = $wpdb->delete($table, array('id' => $id), array('%d'));

        return $result !== false && $result > 0;
    }

    /**
     * Remove error log entries older than the given number of days.
     *
     * @param int $days
     * @return int  Number of rows deleted
     */
    public static function cleanup_old_logs(int $days = 30): int
    {
        global $wpdb;

        $table     = $wpdb->prefix . 'mnem_error_logs';
        $threshold = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $deleted = (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $threshold
            )
        );

        return $deleted;
    }

    /**
     * Get summary stats for the dashboard widget.
     *
     * @return array{errors_24h:int,most_recent:array<string,mixed>|null,most_common_type:string}
     */
    public static function get_summary(): array
    {
        global $wpdb;

        $table   = $wpdb->prefix . 'mnem_error_logs';
        $since24 = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);

        $errors_24h = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE created_at >= %s",
                $since24
            )
        );

        $most_recent = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, error_type, error_level, error_message, recipient_email, created_at FROM {$table} ORDER BY created_at DESC LIMIT %d",
                1
            ),
            ARRAY_A
        );

        $most_common_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT error_type, COUNT(1) AS cnt FROM {$table} WHERE created_at >= %s GROUP BY error_type ORDER BY cnt DESC LIMIT %d",
                $since24,
                1
            ),
            ARRAY_A
        );

        return array(
            'errors_24h'       => $errors_24h,
            'most_recent'      => is_array($most_recent) ? $most_recent : null,
            'most_common_type' => !empty($most_common_row['error_type']) ? (string) $most_common_row['error_type'] : '',
        );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build a reusable WHERE clause array from filters.
     *
     * @param array<string,mixed> $filters
     * @return array{where:string[],params:mixed[]}
     */
    private static function build_where_clause(array $filters): array
    {
        $where  = array('1=1');
        $params = array();

        if (!empty($filters['error_level'])) {
            $where[]  = 'error_level = %s';
            $params[] = $filters['error_level'];
        }

        if (!empty($filters['error_type'])) {
            $where[]  = 'error_type = %s';
            $params[] = $filters['error_type'];
        }

        if (!empty($filters['provider_type'])) {
            $where[]  = 'provider_type = %s';
            $params[] = $filters['provider_type'];
        }

        if (!empty($filters['recipient_email'])) {
            global $wpdb;
            $where[]  = 'recipient_email LIKE %s';
            $params[] = '%' . $wpdb->esc_like($filters['recipient_email']) . '%';
        }

        if (isset($filters['queue_id']) && (int) $filters['queue_id'] > 0) {
            $where[]  = 'queue_id = %d';
            $params[] = (int) $filters['queue_id'];
        }

        if (isset($filters['campaign_id']) && (int) $filters['campaign_id'] > 0) {
            $where[]  = 'campaign_id = %d';
            $params[] = (int) $filters['campaign_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[]  = 'created_at >= %s';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[]  = 'created_at <= %s';
            $params[] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            global $wpdb;
            $like     = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[]  = '(error_message LIKE %s OR recipient_email LIKE %s OR subject LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        return array('where' => $where, 'params' => $params);
    }

    /**
     * Return the fully-qualified error log table name.
     */
    private static function get_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'mnem_error_logs';
    }

    /**
     * Check whether the error log table exists in the database.
     * Result is cached per-request to avoid repeated SHOW TABLES queries.
     */
    private static function table_exists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            global $wpdb;
            $table  = self::get_table_name();
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $exists = (bool) $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $table)
            );
        }

        return $exists;
    }

    /**
     * Get the error log table size in bytes.
     *
     * @return int
     */
    public static function get_table_size(): int
    {
        global $wpdb;

        $table = self::get_table_name();

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT SUM(data_length + index_length) as size FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
                DB_NAME,
                $table
            )
        );

        return isset($result->size) ? (int) $result->size : 0;
    }

    /**
     * Get the error log table size as a human-readable string.
     *
     * @return string
     */
    public static function get_table_size_formatted(): string
    {
        $bytes = self::get_table_size();
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Truncate a string to a maximum byte length, appending a truncation marker.
     *
     * @param string $value
     * @param int    $max_length
     * @return string
     */
    private static function truncate_string(string $value, int $max_length): string
    {
        if (strlen($value) <= $max_length) {
            return $value;
        }

        $truncated = substr($value, 0, $max_length);

        if ($max_length > 20) {
            $truncated = substr($truncated, 0, -20) . '... [TRUNCATED]';
        }

        return $truncated;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function insert(array $data): void
    {
        global $wpdb;

        $table = self::get_table_name();

        if (!self::table_exists()) {
            error_log('MNEM: Error log table does not exist (' . $table . '). Has the plugin been activated/updated?');
            return;
        }

        $now = current_time('mysql', true);

        $row = array(
            'site_id'                => function_exists('get_current_network_id') ? (int) get_current_network_id() : (isset($wpdb->siteid) ? (int) $wpdb->siteid : 0),
            'blog_id'                => function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0,
            'error_level'            => isset($data['error_level']) ? (string) $data['error_level'] : self::LEVEL_ERROR,
            'error_type'             => isset($data['error_type']) ? (string) $data['error_type'] : self::TYPE_SYSTEM_ERROR,
            'error_code'             => isset($data['error_code']) ? self::truncate_string((string) $data['error_code'], 50) : null,
            'error_message'          => isset($data['error_message']) ? self::truncate_string((string) $data['error_message'], self::MAX_ERROR_MESSAGE) : '',
            'system_error'           => isset($data['system_error']) ? self::truncate_string((string) $data['system_error'], self::MAX_SYSTEM_ERROR) : null,
            'stack_trace'            => isset($data['stack_trace']) ? self::truncate_string((string) $data['stack_trace'], self::MAX_STACK_TRACE) : null,
            'queue_id'               => isset($data['queue_id']) && (int) $data['queue_id'] > 0 ? (int) $data['queue_id'] : null,
            'campaign_id'            => isset($data['campaign_id']) && (int) $data['campaign_id'] > 0 ? (int) $data['campaign_id'] : null,
            'recipient_email'        => isset($data['recipient_email']) ? self::truncate_string((string) $data['recipient_email'], self::MAX_RECIPIENT_EMAIL) : null,
            'sender_email'           => isset($data['sender_email']) ? self::truncate_string((string) $data['sender_email'], self::MAX_SENDER_EMAIL) : null,
            'subject'                => isset($data['subject']) ? self::truncate_string((string) $data['subject'], self::MAX_SUBJECT) : null,
            'provider_type'          => isset($data['provider_type']) ? (string) $data['provider_type'] : null,
            'provider_error_code'    => isset($data['provider_error_code']) ? self::truncate_string((string) $data['provider_error_code'], 50) : null,
            'provider_error_message' => isset($data['provider_error_message']) ? self::truncate_string((string) $data['provider_error_message'], self::MAX_PROVIDER_ERROR) : null,
            'http_status_code'       => isset($data['http_status_code']) && (int) $data['http_status_code'] > 0 ? (int) $data['http_status_code'] : null,
            'api_response'           => isset($data['api_response']) ? self::truncate_string((string) $data['api_response'], self::MAX_API_RESPONSE) : null,
            'context'                => !empty($data['context']) ? self::truncate_string((string) wp_json_encode($data['context']), self::MAX_CONTEXT_JSON) : null,
            'created_at'             => $now,
            'updated_at'             => $now,
        );

        // Strip null columns to let DB defaults apply where they exist.
        $row = array_filter($row, static function ($v) { return $v !== null; });

        $wpdb->insert($table, $row);

        if (isset($wpdb->last_error) && $wpdb->last_error !== '') {
            error_log(sprintf(
                'MNEM ErrorLog::insert() failed — DB error: %s | error_type: %s | message: %s',
                $wpdb->last_error,
                $data['error_type'] ?? 'unknown',
                $data['error_message'] ?? 'no message'
            ));

            if (class_exists('\MNEM\Logger')) {
                Logger::error('Error logging failed', array(
                    'db_error'      => $wpdb->last_error,
                    'error_type'    => $data['error_type'] ?? 'unknown',
                    'error_message' => $data['error_message'] ?? '',
                ));
            }
        }

        // Schedule daily cleanup if not already scheduled.
        if (function_exists('wp_next_scheduled') && !wp_next_scheduled('mnem_cleanup_error_logs')) {
            wp_schedule_event(time(), 'daily', 'mnem_cleanup_error_logs');
        }
    }
}
