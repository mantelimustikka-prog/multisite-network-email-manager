<?php

namespace MNEM;

defined('ABSPATH') || exit;

class EmailTemplates
{
    public const OPTION_KEY = 'mnem_email_templates';

    public const DEFAULT_TEMPLATES = array(
        'welcome' => array(
            'name' => 'Welcome Email',
            'subject' => 'Welcome to {site_name}!',
            'body' => '<h2>Welcome, {user_name}!</h2><p>Thank you for joining our community...</p>',
        ),
        'newsletter' => array(
            'name' => 'Newsletter',
            'subject' => "This Week's Newsletter - {date}",
            'body' => "<h1>Newsletter</h1><p>Here are this week's highlights...</p>",
        ),
        'alert' => array(
            'name' => 'Alert',
            'subject' => 'Important Notice',
            'body' => '<div style="background: #fff3cd; padding: 10px;"><h3>Alert</h3><p>Important information...</p></div>',
        ),
        'announcement' => array(
            'name' => 'Announcement',
            'subject' => 'Announcement from {site_name}',
            'body' => '<h2>Announcement</h2><p>We are pleased to announce...</p>',
        ),
    );

    public static function get_template(string $template_id)
    {
        $templates = self::get_all_templates();

        return isset($templates[$template_id]) ? $templates[$template_id] : null;
    }

    public static function get_all_templates()
    {
        $saved = get_site_option(self::OPTION_KEY, array());
        $saved = is_array($saved) ? $saved : array();
        $templates = self::DEFAULT_TEMPLATES;

        foreach ($saved as $template_id => $template) {
            if (!is_array($template)) {
                continue;
            }
            $templates[$template_id] = array(
                'name' => isset($template['name']) ? (string) $template['name'] : '',
                'subject' => isset($template['subject']) ? (string) $template['subject'] : '',
                'body' => isset($template['body']) ? (string) $template['body'] : '',
                'is_custom' => !isset(self::DEFAULT_TEMPLATES[$template_id]),
                'created_at' => isset($template['created_at']) ? (string) $template['created_at'] : '',
            );
        }

        foreach ($templates as $template_id => &$template) {
            if (!isset($template['is_custom'])) {
                $template['is_custom'] = false;
            }
            if (!isset($template['created_at'])) {
                $template['created_at'] = '';
            }
            $template['id'] = $template_id;
        }
        unset($template);

        return $templates;
    }

    public static function save_template(string $template_id, string $name, string $subject, string $body)
    {
        $template_id = self::sanitize_template_id($template_id !== '' ? $template_id : $name);
        if ($template_id === '') {
            return false;
        }

        $saved = get_site_option(self::OPTION_KEY, array());
        $saved = is_array($saved) ? $saved : array();
        $existing = isset($saved[$template_id]) && is_array($saved[$template_id]) ? $saved[$template_id] : array();

        $saved[$template_id] = array(
            'name' => sanitize_text_field($name),
            'subject' => sanitize_text_field($subject),
            'body' => function_exists('wp_kses_post') ? wp_kses_post($body) : $body,
            'created_at' => isset($existing['created_at']) ? (string) $existing['created_at'] : self::current_time_mysql(),
            'updated_at' => self::current_time_mysql(),
        );

        Logger::info('Email template saved.', array('template_id' => $template_id, 'user_id' => get_current_user_id()));

        return update_site_option(self::OPTION_KEY, $saved);
    }

    public static function delete_custom_template(string $template_id)
    {
        if (isset(self::DEFAULT_TEMPLATES[$template_id])) {
            return false;
        }

        $saved = get_site_option(self::OPTION_KEY, array());
        $saved = is_array($saved) ? $saved : array();

        if (!isset($saved[$template_id])) {
            return false;
        }

        unset($saved[$template_id]);
        Logger::info('Custom email template deleted.', array('template_id' => $template_id, 'user_id' => get_current_user_id()));

        return update_site_option(self::OPTION_KEY, $saved);
    }

    public static function reset_to_default(string $template_id)
    {
        if (!isset(self::DEFAULT_TEMPLATES[$template_id])) {
            return false;
        }

        $saved = get_site_option(self::OPTION_KEY, array());
        $saved = is_array($saved) ? $saved : array();
        unset($saved[$template_id]);
        Logger::info('Built-in template reset to default.', array('template_id' => $template_id, 'user_id' => get_current_user_id()));

        return update_site_option(self::OPTION_KEY, $saved);
    }

    public static function get_available_variables()
    {
        return array(
            '{user_name}' => 'Recipient display name',
            '{user_email}' => 'Recipient email',
            '{site_name}' => 'Current site name',
            '{date}' => 'Current date (Y-m-d)',
        );
    }

    public static function replace_variables(string $content, array $variables)
    {
        $defaults = array(
            '{user_name}' => '',
            '{user_email}' => '',
            '{site_name}' => function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '',
            '{date}' => gmdate('Y-m-d'),
        );

        $pairs = array_merge($defaults, $variables);
        $replacements = array();
        foreach ($pairs as $key => $value) {
            $replacements[(string) $key] = (string) $value;
        }

        return strtr($content, $replacements);
    }

    private static function sanitize_template_id(string $template_id)
    {
        $template_id = strtolower(trim($template_id));
        $template_id = preg_replace('/[^a-z0-9_\-]+/', '-', $template_id);

        return trim((string) $template_id, '-');
    }

    private static function current_time_mysql()
    {
        return function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
    }
}
