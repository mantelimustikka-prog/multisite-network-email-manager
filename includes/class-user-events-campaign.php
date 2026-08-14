<?php

namespace MNEM;

defined('ABSPATH') || exit;

class UserEventsCampaign
{
    public const OPTION_RULES = 'mnem_user_event_rules';
    public const OPTION_FAILED_RULE_TRIGGERS = 'mnem_user_event_rule_failures';
    public const EVENT_TYPES = array('user_register', 'user_delete', 'user_role_change');

    public static function get_rules()
    {
        $raw = get_site_option(self::OPTION_RULES, '[]');
        $rules = json_decode((string) $raw, true);

        return is_array($rules) ? array_values($rules) : array();
    }

    public static function save_rules(array $rules)
    {
        $validated = array();

        foreach ($rules as $rule) {
            $normalized = self::validate_rule($rule);
            if (!empty($normalized)) {
                $validated[] = $normalized;
            }
        }

        return update_site_option(self::OPTION_RULES, wp_json_encode($validated));
    }

    public static function add_rule(array $rule)
    {
        $normalized = self::validate_rule($rule);
        if (empty($normalized)) {
            return false;
        }

        $rules = self::get_rules();
        $rules[] = $normalized;

        return self::save_rules($rules);
    }

    public static function upsert_rule(array $rule)
    {
        $normalized = self::validate_rule($rule);
        if (empty($normalized)) {
            return false;
        }

        $rules = self::get_rules();
        $updated = false;
        foreach ($rules as $index => $existing) {
            if (isset($existing['id']) && isset($normalized['id']) && (string) $existing['id'] === (string) $normalized['id']) {
                $rules[$index] = $normalized;
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $rules[] = $normalized;
        }

        return self::save_rules($rules);
    }

    public static function delete_rule(string $rule_id)
    {
        $rule_id = sanitize_text_field($rule_id);
        $rules = self::get_rules();
        $next_rules = array_values(array_filter($rules, static function ($rule) use ($rule_id) {
            return !isset($rule['id']) || (string) $rule['id'] !== $rule_id;
        }));

        return self::save_rules($next_rules);
    }

    public static function trigger_event(string $event_type, int $user_id, array $context = array())
    {
        $event_type = sanitize_text_field($event_type);
        if (!in_array($event_type, self::EVENT_TYPES, true) || $user_id <= 0) {
            return 0;
        }

        $site_id = isset($context['site_id']) ? (int) $context['site_id'] : (function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1);
        $user = isset($context['user']) ? $context['user'] : (function_exists('get_userdata') ? get_userdata($user_id) : null);
        $user_email = self::extract_user_email($user);

        if ($user_email === '') {
            Logger::warning('User event campaign skipped due to missing recipient email.', array('event_type' => $event_type, 'user_id' => $user_id));
            return 0;
        }

        $sent = 0;
        foreach (self::get_rules() as $rule) {
            if (!self::rule_matches($rule, $event_type, $user, $site_id)) {
                continue;
            }

            $campaign_id = isset($rule['campaign_id']) ? (int) $rule['campaign_id'] : 0;
            if ($campaign_id <= 0) {
                self::increment_failure_count();
                continue;
            }

            $result = Campaigns::send_campaign($campaign_id, array($user_email));
            if (!empty($result['success'])) {
                ++$sent;
                Logger::info('User event campaign triggered.', array('event_type' => $event_type, 'user_id' => $user_id, 'campaign_id' => $campaign_id));
                continue;
            }

            self::increment_failure_count();
            Logger::warning('User event campaign trigger failed.', array('event_type' => $event_type, 'user_id' => $user_id, 'campaign_id' => $campaign_id));
        }

        return $sent;
    }

    public static function rule_matches(array $rule, string $event_type, $user, int $site_id)
    {
        if (empty($rule['enabled']) || !isset($rule['event_type']) || $rule['event_type'] !== $event_type) {
            return false;
        }

        $conditions = isset($rule['conditions']) && is_array($rule['conditions']) ? $rule['conditions'] : array();
        $condition_site = isset($conditions['site_id']) ? (string) $conditions['site_id'] : 'any';
        if ($condition_site !== 'any' && (int) $condition_site !== $site_id) {
            return false;
        }

        $condition_role = isset($conditions['role']) ? (string) $conditions['role'] : 'any';
        if ($condition_role === 'any') {
            return true;
        }

        $roles = self::extract_user_roles($user);
        return in_array($condition_role, $roles, true);
    }

    public static function dry_run(array $rule)
    {
        $rule = self::validate_rule($rule);
        if (empty($rule)) {
            return array();
        }

        if (!function_exists('get_users')) {
            return array();
        }

        $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $users = (array) get_users(array('blog_id' => $site_id));
        $matches = array();

        foreach ($users as $user) {
            if (!isset($user->ID) || !self::rule_matches($rule, $rule['event_type'], $user, $site_id)) {
                continue;
            }

            $email = self::extract_user_email($user);
            if ($email !== '') {
                $matches[] = $email;
            }
        }

        return array_values(array_unique($matches));
    }

    public static function validate_rule($rule)
    {
        if (!is_array($rule)) {
            return array();
        }

        $event_type = isset($rule['event_type']) ? sanitize_text_field((string) $rule['event_type']) : '';
        if (!in_array($event_type, self::EVENT_TYPES, true)) {
            return array();
        }

        $campaign_id = isset($rule['campaign_id']) ? (int) $rule['campaign_id'] : 0;
        if ($campaign_id <= 0) {
            return array();
        }

        $conditions = isset($rule['conditions']) && is_array($rule['conditions']) ? $rule['conditions'] : array();
        $role = isset($conditions['role']) ? sanitize_text_field((string) $conditions['role']) : 'any';
        $site = isset($conditions['site_id']) ? sanitize_text_field((string) $conditions['site_id']) : 'any';

        return array(
            'id' => isset($rule['id']) && $rule['id'] !== '' ? sanitize_text_field((string) $rule['id']) : self::generate_rule_id(),
            'event_type' => $event_type,
            'campaign_id' => $campaign_id,
            'enabled' => !empty($rule['enabled']),
            'conditions' => array(
                'role' => $role === '' ? 'any' : $role,
                'site_id' => $site === '' ? 'any' : $site,
            ),
        );
    }

    private static function increment_failure_count()
    {
        $count = (int) get_site_option(self::OPTION_FAILED_RULE_TRIGGERS, 0) + 1;
        update_site_option(self::OPTION_FAILED_RULE_TRIGGERS, $count);
    }

    private static function extract_user_email($user)
    {
        $email = '';
        if (is_object($user) && isset($user->user_email)) {
            $email = (string) $user->user_email;
        } elseif (is_array($user) && isset($user['user_email'])) {
            $email = (string) $user['user_email'];
        }

        $email = strtolower(trim(sanitize_email($email)));
        return is_email($email) ? $email : '';
    }

    private static function extract_user_roles($user)
    {
        if (is_object($user) && isset($user->roles) && is_array($user->roles)) {
            return array_values(array_map('strval', $user->roles));
        }

        if (is_array($user) && isset($user['roles']) && is_array($user['roles'])) {
            return array_values(array_map('strval', $user['roles']));
        }

        return array();
    }

    private static function generate_rule_id()
    {
        return function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('mnem_rule_', true);
    }
}
