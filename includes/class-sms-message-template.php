<?php

namespace MNEM;

defined('ABSPATH') || exit;

/**
 * SmsMessageTemplate - Handles variable replacement in SMS message bodies.
 *
 * Replaces {user_name}, {display_name}, {phone_number}, {site_name}, and {date} placeholders
 * with actual recipient-specific values before the message is queued.
 */
class SmsMessageTemplate
{
    /**
     * Replace template variables in an SMS message body.
     *
     * @param string $template The raw message body containing placeholders.
     * @param array  $context  Associative array with keys:
     *                          - user_name     (string) Recipient name.
     *                          - display_name  (string) Recipient display name from wp_users.
     *                          - phone_number  (string) Recipient phone number.
     *                          - site_name     (string) Site/network name.
     *                          - date          (string) Current date (Y-m-d).
     * @return string The message with placeholders replaced.
     */
    public static function replace_variables(string $template, array $context): string
    {
        $replacements = array(
            '{user_name}'    => isset($context['user_name']) ? (string) $context['user_name'] : '',
            '{display_name}' => isset($context['display_name']) ? (string) $context['display_name'] : '',
            '{phone_number}' => isset($context['phone_number']) ? (string) $context['phone_number'] : '',
            '{site_name}'    => isset($context['site_name']) ? (string) $context['site_name'] : 'Network',
            '{date}'         => isset($context['date']) ? (string) $context['date'] : gmdate('Y-m-d'),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Build a context array for a subscriber from the subscriber row.
     *
     * @param array $subscriber Subscriber row (as returned by SmsSubscriberLists::get_all_subscribers_mixed).
     * @return array Context array for replace_variables().
     */
    public static function build_context(array $subscriber): array
    {
        $user_id = isset($subscriber['user_id']) ? (int) $subscriber['user_id'] : 0;
        $display_name = '';
        $user_name = '';

        // For user-based subscribers, get wp_users data
        if ($user_id > 0 && function_exists('get_userdata')) {
            $user = get_userdata($user_id);
            if ($user) {
                $display_name = isset($user->display_name) ? (string) $user->display_name : '';
                $user_name = isset($user->user_login) ? (string) $user->user_login : '';
            }
        }

        // For standalone subscribers or if user data not found, use the subscriber name.
        if ($display_name === '') {
            if (isset($subscriber['subscriber_name'])) {
                $display_name = (string) $subscriber['subscriber_name'];
            } elseif (isset($subscriber['display_name'])) {
                $display_name = (string) $subscriber['display_name'];
            }
        }

        if ($user_name === '') {
            $user_name = $display_name;
        }

        return array(
            'user_name'    => $user_name,
            'display_name' => $display_name,
            'phone_number' => isset($subscriber['phone_number']) ? (string) $subscriber['phone_number'] : '',
            'site_name'    => function_exists('get_bloginfo') ? (string) get_bloginfo('name') : 'Network',
            'date'         => gmdate('Y-m-d'),
        );
    }
}
