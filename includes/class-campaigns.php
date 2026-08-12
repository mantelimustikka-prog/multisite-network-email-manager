<?php

class MNEM_Campaigns
{
    protected $wpdb;
    protected $table_name;

    public function __construct($database = null, $table_name = '')
    {
        global $wpdb;
        $this->wpdb = $database ?: $wpdb;
        $tables = MNEM_Installer::table_names($this->wpdb);
        $this->table_name = $table_name ?: $tables['campaigns'];
    }

    public function create(array $campaign)
    {
        if (! $this->wpdb) {
            return false;
        }

        $now = gmdate('Y-m-d H:i:s');
        $data = array(
            'name' => isset($campaign['name']) ? (string) $campaign['name'] : '',
            'subject' => isset($campaign['subject']) ? (string) $campaign['subject'] : '',
            'content' => isset($campaign['content']) ? (string) $campaign['content'] : '',
            'status' => isset($campaign['status']) ? (string) $campaign['status'] : 'draft',
            'created_at_gmt' => $now,
            'updated_at_gmt' => $now,
        );

        $result = $this->wpdb->insert($this->table_name, $data);

        return false === $result ? false : (int) $this->wpdb->insert_id;
    }

    public function update($id, array $campaign)
    {
        if (! $this->wpdb) {
            return false;
        }

        $allowed_fields = array('name', 'subject', 'content', 'status');
        $campaign = array_intersect_key($campaign, array_flip($allowed_fields));
        $campaign['updated_at_gmt'] = gmdate('Y-m-d H:i:s');

        return false !== $this->wpdb->update($this->table_name, $campaign, array('id' => (int) $id));
    }

    public function transition($id, $new_status)
    {
        $campaign = $this->get($id);
        if (! $campaign || ! $this->can_transition($campaign['status'], $new_status)) {
            return false;
        }

        return $this->update($id, array('status' => $new_status));
    }

    public function get($id)
    {
        if (! $this->wpdb || ! method_exists($this->wpdb, 'get_row')) {
            return null;
        }

        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d LIMIT 1", (int) $id);

        return $this->wpdb->get_row($sql, ARRAY_A);
    }

    public function all($limit = 100)
    {
        if (! $this->wpdb || ! method_exists($this->wpdb, 'get_results')) {
            return array();
        }

        $limit = max(1, (int) $limit);

        return (array) $this->wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY updated_at_gmt DESC LIMIT {$limit}", ARRAY_A);
    }

    public function can_transition($from_status, $to_status)
    {
        $transitions = array(
            'draft' => array('scheduled', 'cancelled'),
            'scheduled' => array('sending', 'cancelled'),
            'sending' => array('completed', 'cancelled'),
            'completed' => array(),
            'cancelled' => array(),
        );

        return in_array($to_status, isset($transitions[$from_status]) ? $transitions[$from_status] : array(), true);
    }
}
