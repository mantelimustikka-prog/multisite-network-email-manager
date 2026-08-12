<?php

class MNEM_Suppression_List
{
    protected $wpdb;
    protected $table_name;

    public function __construct($database = null, $table_name = '')
    {
        global $wpdb;
        $this->wpdb = $database ?: $wpdb;
        $tables = MNEM_Installer::table_names($this->wpdb);
        $this->table_name = $table_name ?: $tables['suppressions'];
    }

    public function add($email, $reason = '')
    {
        $email = $this->normalize_email($email);
        if ('' === $email || ! $this->wpdb) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->find_by_email($email);
        $data = array(
            'email' => $email,
            'reason' => (string) $reason,
            'updated_at_gmt' => $now,
        );

        if ($existing) {
            return false !== $this->wpdb->update($this->table_name, $data, array('id' => (int) $existing['id']));
        }

        $data['created_at_gmt'] = $now;

        return false !== $this->wpdb->insert($this->table_name, $data);
    }

    public function remove($email)
    {
        $email = $this->normalize_email($email);
        if ('' === $email || ! $this->wpdb) {
            return false;
        }

        return false !== $this->wpdb->delete($this->table_name, array('email' => $email));
    }

    public function is_suppressed($email)
    {
        return (bool) $this->find_by_email($email);
    }

    public function all($limit = 100)
    {
        if (! $this->wpdb || ! method_exists($this->wpdb, 'get_results')) {
            return array();
        }

        $limit = max(1, (int) $limit);
        $table = $this->table_name;

        return (array) $this->wpdb->get_results("SELECT * FROM {$table} ORDER BY updated_at_gmt DESC LIMIT {$limit}", ARRAY_A);
    }

    protected function find_by_email($email)
    {
        if (! $this->wpdb || ! method_exists($this->wpdb, 'get_row')) {
            return null;
        }

        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE email = %s LIMIT 1", $email);

        return $this->wpdb->get_row($sql, ARRAY_A);
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
