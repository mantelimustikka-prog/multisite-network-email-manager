<?php

namespace MNEM;

defined('ABSPATH') || exit;

class MailInterceptor
{
    /** @var bool */
    private static $bypass = false;

    public static function init()
    {
        add_filter('pre_wp_mail', array(__CLASS__, 'intercept_mail'), -999, 2);
    }

    /**
     * @param mixed                $null
     * @param array<string,mixed>  $args
     * @return mixed
     */
    public static function intercept_mail($null, $args)
    {
        if (!self::should_intercept()) {
            return $null;
        }

        if (!is_array($args)) {
            return $null;
        }

        $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
        $to = isset($args['to']) ? $args['to'] : '';
        $recipient = self::normalize_recipient($to);

        if ($recipient === '') {
            return $null;
        }

        $subject = isset($args['subject']) ? (string) $args['subject'] : '';
        $message = isset($args['message']) ? (string) $args['message'] : '';
        $headers = self::normalize_headers(isset($args['headers']) ? $args['headers'] : array());
        $attachments = self::normalize_attachments(isset($args['attachments']) ? $args['attachments'] : array());

        $from = self::extract_from($headers);
        $from_email = $from['email'] !== '' ? $from['email'] : SmtpSettings::get_sender_email();
        $from_name = $from['name'] !== '' ? $from['name'] : SmtpSettings::get_sender_name();
        if (SmtpSettings::is_force_sender_enabled()) {
            $forced_email = SmtpSettings::get_sender_email();
            $forced_name = SmtpSettings::get_sender_name();
            if ($forced_email !== '') {
                $from_email = $forced_email;
                $from_name = $forced_name;
                Logger::info('Intercepted email sender overridden by force sender setting.', array(
                    'to' => $recipient,
                    'forced_from' => $forced_email,
                ));
            }
        }

        $message = EmailFormatter::apply_global_header_footer($message);

        $queued = Queue::enqueue(
            $blog_id,
            $recipient,
            $subject,
            $message,
            0,
            array(
                'from_email' => $from_email,
                'from_name' => $from_name,
                'headers' => $headers,
                'attachments' => $attachments,
                'metadata' => array(
                    'source_ip' => self::get_client_ip(),
                    'user_id' => function_exists('get_current_user_id') ? (int) get_current_user_id() : 0,
                ),
                'source' => self::detect_source($args),
            )
        );

        if (!$queued) {
            return $null;
        }

        Logger::info(
            'Email intercepted and queued.',
            array(
                'queue_id' => (int) $queued,
                'blog_id' => $blog_id,
                'to' => $recipient,
                'subject' => $subject,
            )
        );

        return true;
    }

    public static function run_without_interception(callable $callback)
    {
        $previous = self::$bypass;
        self::$bypass = true;

        try {
            return $callback();
        } finally {
            self::$bypass = $previous;
        }
    }

    private static function should_intercept()
    {
        return !self::$bypass;
    }

    /**
     * @param mixed $to
     */
    private static function normalize_recipient($to)
    {
        if (is_array($to)) {
            $list = array_map('trim', array_map('strval', $to));
            $list = array_values(array_filter($list, static function ($value) {
                return $value !== '';
            }));

            return implode(',', $list);
        }

        return trim((string) $to);
    }

    /**
     * @param mixed $headers
     * @return array<int,string>
     */
    private static function normalize_headers($headers)
    {
        if (is_string($headers)) {
            $headers = preg_split('/\r\n|\r|\n/', $headers);
        }

        if (!is_array($headers)) {
            return array();
        }

        $normalized = array();
        foreach ($headers as $header) {
            $header = trim((string) $header);
            if ($header !== '') {
                $normalized[] = $header;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $attachments
     * @return array<int,string>
     */
    private static function normalize_attachments($attachments)
    {
        if (is_string($attachments)) {
            $attachments = array($attachments);
        }

        if (!is_array($attachments)) {
            return array();
        }

        $normalized = array();
        foreach ($attachments as $attachment) {
            $attachment = trim((string) $attachment);
            if ($attachment !== '') {
                $normalized[] = $attachment;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int,string> $headers
     * @return array{name:string,email:string}
     */
    private static function extract_from(array $headers)
    {
        foreach ($headers as $header_line) {
            if (stripos($header_line, 'From:') !== 0) {
                continue;
            }

            $value = trim(substr($header_line, 5));
            if ($value === '') {
                break;
            }

            if (preg_match('/^(.*)<([^>]+)>$/', $value, $matches)) {
                return array(
                    'name' => trim(trim($matches[1]), '"\''),
                    'email' => sanitize_email(trim($matches[2])),
                );
            }

            return array(
                'name' => '',
                'email' => sanitize_email($value),
            );
        }

        return array('name' => '', 'email' => '');
    }

    /**
     * @param array<string,mixed> $args
     */
    private static function detect_source(array $args)
    {
        if (!empty($args['campaign_id'])) {
            return 'campaign';
        }

        return 'core';
    }

    private static function get_client_ip()
    {
        if (!isset($_SERVER['REMOTE_ADDR'])) {
            return '';
        }

        return sanitize_text_field((string) $_SERVER['REMOTE_ADDR']);
    }
}
