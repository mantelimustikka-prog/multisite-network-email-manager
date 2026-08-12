<?php

if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($value)
    {
        return json_encode($value);
    }
}

if (! function_exists('mnem_assert_true')) {
    function mnem_assert_true($condition, $message = 'Expected condition to be true.')
    {
        if (! $condition) {
            throw new Exception($message);
        }
    }
}

if (! function_exists('mnem_assert_false')) {
    function mnem_assert_false($condition, $message = 'Expected condition to be false.')
    {
        if ($condition) {
            throw new Exception($message);
        }
    }
}

if (! function_exists('mnem_assert_same')) {
    function mnem_assert_same($expected, $actual, $message = '')
    {
        if ($expected !== $actual) {
            throw new Exception($message ?: sprintf('Expected %s but got %s.', var_export($expected, true), var_export($actual, true)));
        }
    }
}

if (! function_exists('mnem_assert_string_starts_with')) {
    function mnem_assert_string_starts_with($prefix, $value, $message = '')
    {
        if (0 !== strpos($value, $prefix)) {
            throw new Exception($message ?: sprintf('Expected %s to start with %s.', $value, $prefix));
        }
    }
}

require dirname(__DIR__) . '/includes/class-settings.php';
require dirname(__DIR__) . '/includes/class-logger.php';
require dirname(__DIR__) . '/includes/class-installer.php';
require dirname(__DIR__) . '/includes/class-smtp-settings.php';
require dirname(__DIR__) . '/includes/class-suppression-list.php';
require dirname(__DIR__) . '/includes/class-campaigns.php';
require dirname(__DIR__) . '/includes/class-queue.php';
