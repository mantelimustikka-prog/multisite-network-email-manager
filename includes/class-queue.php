<?php

class MNEM_Queue
{
    protected $wpdb;
    protected $table_name;
    protected $suppression_list;
    protected $logger;

    public function __construct(MNEM_Suppression_List $suppression_list = null, MNEM_Logger $logger = null, $database = null, $table_name = '')
    {
        global $wpdb;
        $this->wpdb = $database ?: $wpdb;
        $tables = MNEM_Installer::table_names($this->wpdb);
        $this->table_name = $table_name ?: $tables['queue'];
        $this->suppression_list = $suppression_list ?: new MNEM_Suppression_List($this->wpdb);
        $this->logger = $logger ?: new MNEM_Logger();
    }

    public function enqueue($recipient, $subject, $body, array $headers = array(), $campaign_id = 0, $max_attempts = 3)
    {
        if (! $this->wpdb) {
            return false;
        }

        $recipient = $this->normalize_email($recipient);
        if ('' === $recipient || $this->suppression_list->is_suppressed($recipient)) {
            return false;
        }

        $dedupe_key = $this->build_dedupe_key($recipient, $subject, $body, $campaign_id);
        if ($this->has_duplicate($dedupe_key)) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');

        return false !== $this->wpdb->insert($this->table_name, array(
            'recipient' => $recipient,
            'subject' => (string) $subject,
            'body' => (string) $body,
            'headers' => wp_json_encode($headers),
            'campaign_id' => (int) $campaign_id,
            'status' => 'queued',
            'attempts' => 0,
            'max_attempts' => max(1, (int) $max_attempts),
            'next_attempt_gmt' => $now,
            'dedupe_key' => $dedupe_key,
            'created_at_gmt' => $now,
            'updated_at_gmt' => $now,
        ));
    }

    public function process_batch($limit = 10, callable $sender = null)
    {
        $processed = array('sent' => 0, 'failed' => 0, 'skipped' => 0);
        foreach ($this->due_items($limit) as $item) {
            if ($this->suppression_list->is_suppressed($item['recipient'])) {
                $this->mark_skipped($item['id'], 'suppressed');
                $processed['skipped']++;
                continue;
            }

            if (! $this->lock_item($item['id'])) {
                $processed['skipped']++;
                continue;
            }

            $result = $sender ? (bool) call_user_func($sender, $item) : $this->send_item($item);

            if ($result) {
                $this->mark_sent($item['id']);
                $processed['sent']++;
                continue;
            }

            $this->retry_or_fail($item);
            $processed['failed']++;
        }

        return $processed;
    }

    public function retry_delay_seconds($attempts)
    {
        $attempts = max(1, (int) $attempts);

        return (int) min(HOUR_IN_SECONDS * 24, pow(2, $attempts - 1) * MINUTE_IN_SECONDS * 5);
    }

    public function build_dedupe_key($recipient, $subject, $body, $campaign_id = 0)
    {
        return hash('sha256', implode('|', array(strtolower((string) $recipient), (string) $campaign_id, (string) $subject, (string) $body)));
    }

    public function all($limit = 100)
    {
        if (! $this->wpdb || ! method_exists($this->wpdb, 'get_results')) {
            return array();
        }

        $limit = max(1, (int) $limit);

        return (array) $this->wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY updated_at_gmt DESC LIMIT {$limit}", ARRAY_A);
    }

    public function count()
    {
        if (! $this->wpdb || ! method_exists($this->wpdb, 'get_var')) {
            return 0;
        }

        return (int) $this->wpdb->get_var("SELECT COUNT(1) FROM {$this->table_name}");
    }

    protected function due_items($limit)
    {
        if (! $this->wpdb || ! method_exists($this->wpdb, 'get_results')) {
            return array();
        }

        $limit = max(1, (int) $limit);
        $now = gmdate('Y-m-d H:i:s');
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE status IN ('queued','retrying') AND (next_attempt_gmt IS NULL OR next_attempt_gmt <= %s) ORDER BY id ASC LIMIT %d",
            $now,
            $limit
        );

        return (array) $this->wpdb->get_results($sql, ARRAY_A);
    }

    protected function has_duplicate($dedupe_key)
    {
        if (! $this->wpdb || ! method_exists($this->wpdb, 'get_var')) {
            return false;
        }

        $sql = $this->wpdb->prepare(
            "SELECT COUNT(1) FROM {$this->table_name} WHERE dedupe_key = %s AND status IN ('queued','retrying','processing','sent')",
            $dedupe_key
        );

        return (int) $this->wpdb->get_var($sql) > 0;
    }

    protected function lock_item($id)
    {
        if (! $this->wpdb || ! method_exists($this->wpdb, 'prepare') || ! method_exists($this->wpdb, 'query')) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');
        $sql = $this->wpdb->prepare(
            "UPDATE {$this->table_name} SET status = %s, locked_at_gmt = %s, updated_at_gmt = %s WHERE id = %d AND status IN ('queued','retrying')",
            'processing',
            $now,
            $now,
            (int) $id
        );

        return (int) $this->wpdb->query($sql) > 0;
    }

    protected function mark_sent($id)
    {
        return $this->update_item($id, array(
            'status' => 'sent',
            'sent_at_gmt' => gmdate('Y-m-d H:i:s'),
            'last_error' => '',
        ));
    }

    protected function mark_skipped($id, $reason)
    {
        return $this->update_item($id, array(
            'status' => 'skipped',
            'last_error' => (string) $reason,
        ));
    }

    protected function retry_or_fail(array $item)
    {
        $attempts = (int) $item['attempts'] + 1;
        $max_attempts = max(1, (int) $item['max_attempts']);

        if ($attempts >= $max_attempts) {
            return $this->update_item($item['id'], array(
                'status' => 'failed',
                'attempts' => $attempts,
                'last_error' => 'Maximum attempts reached.',
            ));
        }

        return $this->update_item($item['id'], array(
            'status' => 'retrying',
            'attempts' => $attempts,
            'next_attempt_gmt' => gmdate('Y-m-d H:i:s', time() + $this->retry_delay_seconds($attempts)),
            'last_error' => 'Send failed; retry scheduled.',
        ));
    }

    protected function update_item($id, array $data)
    {
        if (! $this->wpdb) {
            return false;
        }

        $data['updated_at_gmt'] = gmdate('Y-m-d H:i:s');

        return false !== $this->wpdb->update($this->table_name, $data, array('id' => (int) $id));
    }

    protected function send_item(array $item)
    {
        $headers = json_decode(isset($item['headers']) ? (string) $item['headers'] : '[]', true);
        if (! is_array($headers)) {
            $headers = array();
        }

        if (function_exists('wp_mail')) {
            $result = (bool) wp_mail($item['recipient'], $item['subject'], $item['body'], $headers);
            if (! $result) {
                $this->logger->log('error', 'Queue send failed', array('recipient' => $item['recipient'], 'campaign_id' => $item['campaign_id']));
            }

            return $result;
        }

        return false;
    }

    protected function normalize_email($email)
    {
        $email = strtolower(trim((string) $email));
        if (function_exists('sanitize_email')) {
            $email = sanitize_email($email);
        }

        return $email;
    }
}
