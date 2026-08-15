<?php

namespace MNEM;

defined('ABSPATH') || exit;

class EmailTracking
{
    public const OPTION_KEEP_PREVIEWS = 'mnem_keep_email_previews';
    public const OPTION_RETENTION_DAYS = 'mnem_email_preview_retention_days';
    public const OPTION_LAST_CLEANUP_AT = 'mnem_email_preview_last_cleanup_at';

    private const TABLE_SUFFIX = 'mnem_email_tracking';
    private static $resolved_table_name = null;

    public static function is_enabled(): bool
    {
        $keep_previews = get_site_option(self::OPTION_KEEP_PREVIEWS);

        // If the option has never been set, default to enabled and persist it.
        if ($keep_previews === false) {
            update_site_option(self::OPTION_KEEP_PREVIEWS, 1);
            return true;
        }

        return (int) $keep_previews === 1;
    }

    public static function get_retention_days(): int
    {
        return max(0, (int) get_site_option(self::OPTION_RETENTION_DAYS, 30));
    }

    public static function save_settings(bool $enabled, int $retention_days): void
    {
        update_site_option(self::OPTION_KEEP_PREVIEWS, $enabled ? 1 : 0);
        update_site_option(self::OPTION_RETENTION_DAYS, max(0, $retention_days));
    }

    public static function cleanup_old_records(): void
    {
        $now_unix = time();
        $last_cleanup = (int) get_site_option(self::OPTION_LAST_CLEANUP_AT, 0);
        if (($now_unix - $last_cleanup) < 3600) {
            return;
        }

        if (!self::is_enabled()) {
            update_site_option(self::OPTION_LAST_CLEANUP_AT, $now_unix);
            return;
        }

        $retention_days = self::get_retention_days();
        if ($retention_days === 0) {
            update_site_option(self::OPTION_LAST_CLEANUP_AT, $now_unix);
            return;
        }

        global $wpdb;
        $table = self::get_table_name();
        if ($table === '') {
            return;
        }
        $cutoff = gmdate('Y-m-d H:i:s', $now_unix - ($retention_days * 86400));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );
        update_site_option(self::OPTION_LAST_CLEANUP_AT, $now_unix);
    }

    /**
     * @param array<string,mixed> $queue_row
     * @param array<string,mixed> $send_result
     * @param array<int|string,mixed> $headers
     */
    public static function store_sent_email(int $queue_id, array $queue_row, array $send_result, array $headers): void
    {
        if (!self::is_enabled()) {
            return;
        }

        global $wpdb;
        $table = self::get_table_name();
        if ($table === '') {
            return;
        }
        $now = gmdate('Y-m-d H:i:s');
        $site_id = self::resolve_site_id(null, $queue_row);
        $provider_message_id = isset($send_result['message_id']) ? (string) $send_result['message_id'] : '';

        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, queue_id, provider_message_id, recipient_email, subject, body, headers, delivery_status, open_count, open_timestamps, click_count, click_timestamps, created_at, updated_at) VALUES (%d, %d, %s, %s, %s, %s, %s, %s, %d, %s, %d, %s, %s, %s)",
                $site_id,
                $queue_id,
                $provider_message_id,
                isset($queue_row['recipient_email']) ? (string) $queue_row['recipient_email'] : '',
                isset($queue_row['subject']) ? (string) $queue_row['subject'] : '',
                isset($queue_row['body']) ? (string) $queue_row['body'] : '',
                wp_json_encode(array_values($headers)),
                !empty($send_result['success']) ? 'pending' : 'failed',
                0,
                wp_json_encode(array()),
                0,
                wp_json_encode(array()),
                $now,
                $now
            )
        );

        self::cleanup_old_records();
    }

    /**
     * @return array<string,mixed>
     */
    public static function get_history(string $search = '', int $limit = 200, ?int $site_id = null): array
    {
        global $wpdb;
        $table = self::get_table_name();
        if ($table === '') {
            return array('items' => array());
        }
        $search = trim($search);
        $limit = max(1, $limit);
        $site_id = self::resolve_site_id($site_id);

        if ($search === '') {
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT email_id, site_id, queue_id, provider_message_id, recipient_email, subject, delivery_status, open_count, open_timestamps, click_count, click_timestamps, created_at, updated_at FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d",
                    $site_id,
                    $limit
                ),
                ARRAY_A
            );
        } else {
            $escaped_search = method_exists($wpdb, 'esc_like') ? $wpdb->esc_like($search) : addcslashes($search, '%_');
            $like = '%' . $escaped_search . '%';
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT email_id, site_id, queue_id, provider_message_id, recipient_email, subject, delivery_status, open_count, open_timestamps, click_count, click_timestamps, created_at, updated_at FROM {$table} WHERE site_id = %d AND (recipient_email LIKE %s OR subject LIKE %s) ORDER BY created_at DESC LIMIT %d",
                    $site_id,
                    $like,
                    $like,
                    $limit
                ),
                ARRAY_A
            );
        }

        return array('items' => (array) $items);
    }

    /**
     * @return array{emails:int,bytes:int,formatted:string}
     */
    public static function get_storage_usage(?int $site_id = null): array
    {
        global $wpdb;
        $table = self::get_table_name();
        if ($table === '') {
            return array(
                'emails' => 0,
                'bytes' => 0,
                'formatted' => self::format_bytes(0),
            );
        }
        $site_id = self::resolve_site_id($site_id);
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(1) AS total_emails, COALESCE(SUM(COALESCE(LENGTH(recipient_email), 0) + COALESCE(LENGTH(subject), 0) + COALESCE(LENGTH(body), 0) + COALESCE(LENGTH(headers), 0) + COALESCE(LENGTH(provider_message_id), 0)), 0) AS total_bytes FROM {$table} WHERE site_id = %d",
                $site_id
            ),
            ARRAY_A
        );

        $bytes = isset($row['total_bytes']) ? (int) $row['total_bytes'] : 0;

        return array(
            'emails' => isset($row['total_emails']) ? (int) $row['total_emails'] : 0,
            'bytes' => $bytes,
            'formatted' => self::format_bytes($bytes),
        );
    }

    public static function handle_webhook_event(string $provider, string $event_type, string $recipient, string $message_id, string $timestamp = '', ?int $site_id = null): void
    {
        if (!self::is_enabled()) {
            return;
        }

        $provider = sanitize_text_field($provider);
        $event_type = sanitize_text_field($event_type);
        $recipient = sanitize_email($recipient);
        $message_id = sanitize_text_field($message_id);
        $timestamp = $timestamp !== '' ? sanitize_text_field($timestamp) : gmdate('Y-m-d H:i:s');

        $update = self::map_event_to_update($provider, $event_type);
        if ($update['status'] === '' && !$update['open'] && !$update['click']) {
            return;
        }

        global $wpdb;
        $table = self::get_table_name();
        if ($table === '') {
            return;
        }
        $site_id = $site_id !== null ? self::resolve_site_id($site_id) : null;

        $where_sql = '';
        $where_args = array();
        if ($message_id !== '') {
            $where_sql = 'provider_message_id = %s';
            $where_args[] = $message_id;
        } elseif ($recipient !== '') {
            $where_sql = 'recipient_email = %s';
            $where_args[] = $recipient;
        } else {
            return;
        }

        $row = null;
        if ($site_id !== null) {
            $scoped_args = $where_args;
            $query = "SELECT email_id, site_id, delivery_status, open_count, open_timestamps, click_count, click_timestamps FROM {$table} WHERE {$where_sql} AND site_id = %d ORDER BY created_at DESC LIMIT %d";
            $scoped_args[] = $site_id;
            $scoped_args[] = 1;
            $row = $wpdb->get_row($wpdb->prepare($query, ...$scoped_args), ARRAY_A);
        }

        if (empty($row)) {
            $fallback_args = $where_args;
            $fallback_args[] = 1;
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT email_id, site_id, delivery_status, open_count, open_timestamps, click_count, click_timestamps FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d",
                    ...$fallback_args
                ),
                ARRAY_A
            );
        }

        if (empty($row)) {
            return;
        }

        $open_count = isset($row['open_count']) ? (int) $row['open_count'] : 0;
        $click_count = isset($row['click_count']) ? (int) $row['click_count'] : 0;
        $open_timestamps = json_decode(isset($row['open_timestamps']) ? (string) $row['open_timestamps'] : '[]', true);
        $open_timestamps = is_array($open_timestamps) ? $open_timestamps : array();
        $click_timestamps = json_decode(isset($row['click_timestamps']) ? (string) $row['click_timestamps'] : '[]', true);
        $click_timestamps = is_array($click_timestamps) ? $click_timestamps : array();

        if ($update['open']) {
            ++$open_count;
            $open_timestamps[] = $timestamp;
        }

        if ($update['click']) {
            ++$click_count;
            $click_timestamps[] = $timestamp;
        }

        $delivery_status = $update['status'] !== '' ? $update['status'] : (isset($row['delivery_status']) ? (string) $row['delivery_status'] : 'pending');
        $now = gmdate('Y-m-d H:i:s');

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET delivery_status = %s, open_count = %d, open_timestamps = %s, click_count = %d, click_timestamps = %s, updated_at = %s WHERE email_id = %d AND site_id = %d",
                $delivery_status,
                $open_count,
                wp_json_encode(array_values($open_timestamps)),
                $click_count,
                wp_json_encode(array_values($click_timestamps)),
                $now,
                (int) $row['email_id'],
                isset($row['site_id']) ? (int) $row['site_id'] : self::resolve_site_id($site_id)
            )
        );
    }

    /**
     * @return array{status:string,open:bool,click:bool}
     */
    public static function map_event_to_update(string $provider, string $event_type): array
    {
        $event = strtolower(trim($event_type));
        $provider = strtolower(trim($provider));

        $delivered_events = array(
            'mailgun' => array('delivered'),
            'sendgrid' => array('delivered'),
            'brevo' => array('delivered'),
            'postmark' => array('delivery'),
            'smtp2go' => array('delivered'),
        );
        $bounce_events = array(
            'mailgun' => array('failed', 'bounced'),
            'sendgrid' => array('bounce'),
            'brevo' => array('hard_bounce'),
            'postmark' => array('bounce', 'hardbounce'),
            'smtp2go' => array('bounce'),
        );
        $failed_events = array(
            'sendgrid' => array('dropped', 'deferred'),
            'brevo' => array('blocked', 'error'),
            'postmark' => array('spamcomplaint'),
            'smtp2go' => array('failed'),
        );
        $open_events = array(
            'mailgun' => array('opened'),
            'sendgrid' => array('open'),
            'brevo' => array('opened'),
            'postmark' => array('open'),
            'smtp2go' => array('open'),
        );
        $click_events = array(
            'mailgun' => array('clicked'),
            'sendgrid' => array('click'),
            'brevo' => array('click'),
            'postmark' => array('click'),
            'smtp2go' => array('click'),
        );

        $status = '';
        if (isset($delivered_events[$provider]) && in_array($event, $delivered_events[$provider], true)) {
            $status = 'delivered';
        } elseif (isset($bounce_events[$provider]) && in_array($event, $bounce_events[$provider], true)) {
            $status = 'bounced';
        } elseif (isset($failed_events[$provider]) && in_array($event, $failed_events[$provider], true)) {
            $status = 'failed';
        }

        return array(
            'status' => $status,
            'open' => isset($open_events[$provider]) && in_array($event, $open_events[$provider], true),
            'click' => isset($click_events[$provider]) && in_array($event, $click_events[$provider], true),
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function get_email(int $email_id, ?int $site_id = null): ?array
    {
        global $wpdb;
        $table = self::get_table_name();
        if ($table === '') {
            return null;
        }
        $site_id = self::resolve_site_id($site_id);
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT email_id, site_id, queue_id, provider_message_id, recipient_email, subject, body, headers, delivery_status, open_count, open_timestamps, click_count, click_timestamps, created_at, updated_at FROM {$table} WHERE email_id = %d AND site_id = %d LIMIT %d",
                $email_id,
                $site_id,
                1
            ),
            ARRAY_A
        );

        return !empty($row) ? $row : null;
    }

    private static function format_bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = array('KB', 'MB', 'GB', 'TB');
        $size = (float) $bytes;
        foreach ($units as $unit) {
            $size /= 1024;
            if ($size < 1024 || $unit === 'TB') {
                return number_format($size, $size >= 10 ? 1 : 2) . ' ' . $unit;
            }
        }

        return $bytes . ' B';
    }

    private static function get_table_name(): string
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !property_exists($wpdb, 'prefix')) {
            self::$resolved_table_name = '';
            return '';
        }

        $network_prefix = (property_exists($wpdb, 'base_prefix') && !empty($wpdb->base_prefix))
            ? (string) $wpdb->base_prefix
            : (string) $wpdb->prefix;
        $table = $network_prefix . self::TABLE_SUFFIX;

        if (self::$resolved_table_name === $table) {
            return self::$resolved_table_name;
        }
        if (self::$resolved_table_name === '') {
            return '';
        }

        if (!method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) {
            self::$resolved_table_name = $table;
            return $table;
        }

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($table_exists === $table) {
            self::$resolved_table_name = $table;
            return $table;
        }

        if (!class_exists(__NAMESPACE__ . '\\Installer') && defined('MNEM_PLUGIN_DIR')) {
            $installer_file = MNEM_PLUGIN_DIR . 'includes/class-installer.php';
            if (file_exists($installer_file)) {
                require_once $installer_file;
            }
        }

        if (class_exists(__NAMESPACE__ . '\\Installer') && method_exists(__NAMESPACE__ . '\\Installer', 'install')) {
            Installer::install();
        }

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($table_exists === $table) {
            self::$resolved_table_name = $table;
            return $table;
        }

        self::$resolved_table_name = '';
        return '';
    }

    private static function resolve_site_id(?int $site_id = null, array $queue_row = array()): int
    {
        if ($site_id !== null && $site_id > 0) {
            return $site_id;
        }

        if (isset($queue_row['site_id']) && (int) $queue_row['site_id'] > 0) {
            return (int) $queue_row['site_id'];
        }

        if (isset($queue_row['blog_id']) && (int) $queue_row['blog_id'] > 0) {
            return (int) $queue_row['blog_id'];
        }

        return function_exists('get_current_blog_id') ? max(1, (int) get_current_blog_id()) : 1;
    }
}
