<?php

namespace MNEM;

defined('ABSPATH') || exit;

class SmsProviderSyncManager
{
    /**
     * Synchronize SMS queue rows with a provider.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function sync_statuses_from_provider(string $provider, int $limit = 100, array $options = array()): array
    {
        global $wpdb;

        $provider = sanitize_key($provider);
        $limit = max(1, min(1000, $limit));
        $dry_run = !empty($options['dry_run']);
        $summary = array(
            'checked' => 0,
            'updated' => 0,
            'delivered' => 0,
            'failed' => 0,
            'bounced' => 0,
            'rejected' => 0,
            'errors' => array(),
            'warnings' => array(),
            'changes' => array(),
        );

        $provider_instance = SmsProviderManager::get_provider($provider);
        if (
            $provider_instance === null
            || !method_exists($provider_instance, 'supports_message_status_lookup')
            || !$provider_instance->supports_message_status_lookup()
        ) {
            $summary['errors'][] = __('The selected SMS provider does not support status lookup.', 'multisite-network-email-manager');
            return $summary;
        }

        $table = $wpdb->base_prefix . 'mnem_sms_queue';
        $where = array('provider_type = %s', "provider_message_id <> ''");
        $args = array($provider);

        if (!empty($options['date_from'])) {
            $where[] = 'created_at >= %s';
            $args[] = (string) $options['date_from'] . ' 00:00:00';
        }
        if (!empty($options['date_to'])) {
            $where[] = 'created_at <= %s';
            $args[] = (string) $options['date_to'] . ' 23:59:59';
        }
        if (!empty($options['queue_ids'])) {
            $ids = array_values(array_filter(array_map('intval', (array) $options['queue_ids'])));
            if (empty($ids)) {
                $summary['errors'][] = __('No valid SMS queue rows were selected.', 'multisite-network-email-manager');
                return $summary;
            }
            $where[] = 'id IN (' . implode(',', $ids) . ')';
        }

        $args[] = $limit;
        $query = call_user_func_array(
            array($wpdb, 'prepare'),
            array_merge(
                array(
                    "SELECT id, status, provider_status, provider_message_id, sync_attempts
                    FROM {$table}
                    WHERE " . implode(' AND ', $where) . '
                    ORDER BY created_at DESC, id DESC
                    LIMIT %d',
                ),
                $args
            )
        );
        $rows = (array) $wpdb->get_results($query, ARRAY_A);

        foreach ($rows as $row) {
            ++$summary['checked'];
            $result = $provider_instance->get_message_status((string) $row['provider_message_id']);
            $raw_status = !empty($result['provider_status']) ? (string) $result['provider_status'] : '';
            $canonical = SmsProviderStatusMap::map($provider, $raw_status);

            // Distinguish genuine provider failures from successful status retrieval with unmapped status.
            if (empty($result['success'])) {
                // Real provider/API failure.
                $error = !empty($result['message'])
                    ? (string) $result['message']
                    : __('The provider returned an unknown SMS status.', 'multisite-network-email-manager');
                $summary['errors'][] = sprintf('SMS #%d: %s', (int) $row['id'], $error);
                if (!$dry_run) {
                    $this->record_failure((int) $row['id'], (int) $row['sync_attempts'], $error);
                }
                continue;
            }

            if ($canonical === '') {
                // Provider returned success but the status code is unmapped (new/unknown status).
                // Log as warning, skip row, do not retry.
                $summary['warnings'][] = sprintf(
                    __('SMS #%d: provider returned unmapped status "%s".', 'multisite-network-email-manager'),
                    (int) $row['id'],
                    $raw_status
                );
                Logger::warning('SMS provider returned an unmapped status.', array(
                    'source' => isset($options['source']) ? (string) $options['source'] : 'manual',
                    'queue_id' => (int) $row['id'],
                    'provider' => $provider,
                    'provider_message_id' => (string) $row['provider_message_id'],
                    'provider_status' => $raw_status,
                ));
                continue;
            }

            // Status was retrieved successfully and mapped. The provider's status is the
            // source of truth and is stored directly without any lifecycle validation.
            $resolved_status = $canonical;
            $changed = $resolved_status !== (string) $row['status'] || $raw_status !== (string) $row['provider_status'];
            if ($dry_run) {
                if ($changed) {
                    $this->add_change_to_summary($summary, $row, $resolved_status, $raw_status);
                }
            } else {
                $checked_at = $this->current_time_mysql();
                $updated = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table}
                    SET status = %s, provider_status = %s, provider_status_checked_at = %s,
                        last_sync_error = NULL, sync_attempts = 0
                    WHERE id = %d",
                    $resolved_status,
                    $raw_status,
                    $checked_at,
                    (int) $row['id']
                ));

                if ($updated === false) {
                    $error = __('Could not update the SMS queue row.', 'multisite-network-email-manager');
                    $summary['errors'][] = sprintf('SMS #%d: %s', (int) $row['id'], $error);
                    $this->record_failure((int) $row['id'], (int) $row['sync_attempts'], $error);
                    continue;
                }
                if ($changed) {
                    $this->add_change_to_summary($summary, $row, $resolved_status, $raw_status);
                }

                Logger::info('SMS provider status synchronized.', array(
                    'source' => isset($options['source']) ? (string) $options['source'] : 'manual',
                    'queue_id' => (int) $row['id'],
                    'provider' => $provider,
                    'provider_message_id' => (string) $row['provider_message_id'],
                    'provider_status' => $raw_status,
                    'old_status' => (string) $row['status'],
                    'new_status' => $resolved_status,
                ));
            }
        }

        return $summary;
    }

    /**
     * @param array<string,mixed> $summary
     * @param array<string,mixed> $row
     */
    private function add_change_to_summary(array &$summary, array $row, string $canonical, string $raw_status): void
    {
        ++$summary['updated'];
        if (isset($summary[$canonical])) {
            ++$summary[$canonical];
        } elseif ($canonical === 'bounce') {
            ++$summary['bounced'];
        }
        $summary['changes'][] = array(
            'id' => (int) $row['id'],
            'old_status' => (string) $row['status'],
            'new_status' => $canonical,
            'provider_status' => $raw_status,
        );
    }

    /** @return array<string,mixed> */
    public function sync_single(int $queue_id, bool $dry_run = false): array
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_queue';
        $provider = (string) $wpdb->get_var($wpdb->prepare(
            "SELECT provider_type FROM {$table} WHERE id = %d",
            $queue_id
        ));
        if ($provider === '') {
            return array(
                'checked' => 0,
                'updated' => 0,
                'errors' => array(__('SMS provider information is missing.', 'multisite-network-email-manager')),
            );
        }

        return $this->sync_statuses_from_provider($provider, 1, array(
            'queue_ids' => array($queue_id),
            'dry_run' => $dry_run,
            'source' => 'single_refresh',
        ));
    }

    public function retry_failed_syncs(int $limit = 100): int
    {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_sms_queue';
        $ids = (array) $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table}
            WHERE sync_attempts > 0 AND sync_attempts < 3
            AND provider_type <> '' AND provider_message_id <> ''
            ORDER BY provider_status_checked_at ASC, id ASC
            LIMIT %d",
            max(1, min(1000, $limit))
        ));
        $updated = 0;

        foreach ($ids as $id) {
            $result = $this->sync_single((int) $id);
            $updated += isset($result['updated']) ? (int) $result['updated'] : 0;
        }

        return $updated;
    }

    private function record_failure(int $queue_id, int $previous_attempts, string $error): void
    {
        global $wpdb;

        $attempts = $previous_attempts + 1;
        $table = $wpdb->base_prefix . 'mnem_sms_queue';
        if ($attempts >= 3) {
            $query = $wpdb->prepare(
                "UPDATE {$table}
                SET sync_attempts = %d, provider_status_checked_at = %s, last_sync_error = %s
                WHERE id = %d",
                $attempts,
                $this->current_time_mysql(),
                $error,
                $queue_id
            );
        } else {
            $query = $wpdb->prepare(
                "UPDATE {$table}
                SET sync_attempts = %d, provider_status_checked_at = %s, last_sync_error = NULL
                WHERE id = %d",
                $attempts,
                $this->current_time_mysql(),
                $queue_id
            );
        }
        $wpdb->query($query);

        Logger::warning('SMS provider status synchronization failed.', array(
            'queue_id' => $queue_id,
            'sync_attempts' => $attempts,
            'error' => $error,
        ));
    }

    private function current_time_mysql(): string
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }
}
