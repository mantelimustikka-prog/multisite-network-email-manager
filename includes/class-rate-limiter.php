<?php

namespace MNEM;

defined('ABSPATH') || exit;

class RateLimiter
{
    private const RATE_LIMIT_PREFIX = 'mnem_rate_limit_';

    public static function is_allowed(string $identifier, int $limit, int $window_seconds): bool
    {
        if ($limit <= 0) {
            return true;
        }

        $key = self::RATE_LIMIT_PREFIX . $identifier;
        $count = (int) get_transient($key);

        if ($count >= $limit) {
            return false;
        }

        return true;
    }

    public static function record_action(string $identifier, int $window_seconds): void
    {
        if ($window_seconds <= 0) {
            return;
        }

        $key = self::RATE_LIMIT_PREFIX . $identifier;
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, $window_seconds);
    }

    public static function get_count(string $identifier): int
    {
        $key = self::RATE_LIMIT_PREFIX . $identifier;
        return (int) get_transient($key);
    }

    public static function reset(string $identifier): void
    {
        $key = self::RATE_LIMIT_PREFIX . $identifier;
        delete_transient($key);
    }

    public static function get_remaining(string $identifier, int $limit): int
    {
        if ($limit <= 0) {
            return PHP_INT_MAX;
        }

        $count = self::get_count($identifier);
        return max(0, $limit - $count);
    }
}
