<?php

namespace MNEM;

defined('ABSPATH') || exit;

class EmailFormatter
{
    /**
     * Wrap email body with the global header and footer if the feature is enabled.
     *
     * @param string $body The original email body.
     * @return string The wrapped (or original) email body.
     */
    public static function apply_global_header_footer($body)
    {
        if (!SmtpSettings::is_global_header_footer_enabled()) {
            return $body;
        }

        $header = function_exists('wp_kses_post')
            ? wp_kses_post((string) get_site_option('mnem_global_header', ''))
            : (string) get_site_option('mnem_global_header', '');

        $footer = function_exists('wp_kses_post')
            ? wp_kses_post((string) get_site_option('mnem_global_footer', ''))
            : (string) get_site_option('mnem_global_footer', '');

        $header = trim($header);
        $footer = trim($footer);

        $parts = array();

        if ($header !== '') {
            $parts[] = $header;
        }

        $parts[] = (string) $body;

        if ($footer !== '') {
            $parts[] = $footer;
        }

        $combined = implode("\n\n", $parts);

        // Collapse excessive blank lines (3+) down to two.
        return preg_replace('/\n{3,}/', "\n\n", $combined);
    }
}
