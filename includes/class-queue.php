<?php

namespace MNEM;

defined('ABSPATH') || exit;

class Queue
{
    public const MAX_ATTEMPTS = 3;
    public const BACKOFF_BASE = 300;

    public static function enqueue(int $site_id, string $email, string $subject, string $body)
    {
        global $wpdb;

        $email = strtolower(trim(sanitize_email($email)));
        if ($email === '' || !is_email($email)) {
            return false;
        }

        if (self::is_suppressed($site_id, $email)) {
            Logger::info('Skipped queue insert for suppressed recipient.', array('site_id' => $site_id, 'email' => $email));
            return false;
        }

        $table = $wpdb->prefix . 'mnem_queue';
        $scheduled_at = self::current_time_mysql();
        $created_at = self::current_time_mysql();

        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (site_id, recipient_email, subject, body, status, attempts, scheduled_at, created_at) VALUES (%d, %s, %s, %s, %s, %d, %s, %s)",
                $site_id,
                $email,
                $subject,
                $body,
                'pending',
                0,
                $scheduled_at,
                $created_at
            )
        );

        if ($result === false) {
            Logger::error('Failed to enqueue email.', array('site_id' => $site_id, 'email' => $email));
            return false;
        }

        return isset($wpdb->insert_id) ? (int) $wpdb->insert_id : true;
    }

    public static function process_batch(int $limit = 20)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_queue';
        $now = self::current_time_mysql();
        $limit = max(1, $limit);

        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE status = %s AND scheduled_at <= %s AND attempts < %d ORDER BY scheduled_at ASC LIMIT %d",
                'pending',
                $now,
                self::MAX_ATTEMPTS,
                $limit
            )
        );

        if (empty($ids)) {
            return 0;
        }

        $processed = 0;

        foreach ($ids as $id) {
            $claimed = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status = %s WHERE id = %d AND status = %s",
                    'processing',
                    (int) $id,
                    'pending'
                )
            );

            if (!$claimed) {
                continue;
            }

            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, site_id, recipient_email, subject, body, attempts FROM {$table} WHERE id = %d",
                    (int) $id
                ),
                ARRAY_A
            );

            if (empty($row)) {
                continue;
            }

            $sent = wp_mail($row['recipient_email'], $row['subject'], $row['body']);
            $attempts = (int) $row['attempts'] + 1;
            $processed_at = self::current_time_mysql();

            if ($sent) {
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET status = %s, attempts = %d, processed_at = %s WHERE id = %d",
                        'sent',
                        $attempts,
                        $processed_at,
                        (int) $id
                    )
                );
            } else {
                $next_status = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
                $next_scheduled = $attempts >= self::MAX_ATTEMPTS ? $processed_at : self::calculate_next_attempt($attempts);

                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table} SET status = %s, attempts = %d, scheduled_at = %s, processed_at = %s WHERE id = %d",
                        $next_status,
                        $attempts,
                        $next_scheduled,
                        $processed_at,
                        (int) $id
                    )
                );
            }

            ++$processed;
        }

        return $processed;
    }

    public static function calculate_next_attempt(int $attempts)
    {
        $delay = self::BACKOFF_BASE * (2 ** $attempts);

        return gmdate('Y-m-d H:i:s', time() + $delay);
    }

    public static function is_suppressed(int $site_id, string $email)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'mnem_suppression';
        $email = strtolower(trim(sanitize_email($email)));
        $found = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(1) FROM {$table} WHERE site_id = %d AND email = %s",
                $site_id,
                $email
            )
        );

        return (int) $found > 0;
    }

    private static function current_time_mysql()
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }
}
