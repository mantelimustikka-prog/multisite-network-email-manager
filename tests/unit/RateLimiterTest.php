<?php

defined('ABSPATH') || exit;

use MNEM\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mnem_transients'] = array();
    }

    public function test_is_allowed_and_record_action_enforce_limit()
    {
        $identifier = 'unit-test-limit';

        $this->assertTrue(RateLimiter::is_allowed($identifier, 2, 60));
        RateLimiter::record_action($identifier, 60);
        $this->assertTrue(RateLimiter::is_allowed($identifier, 2, 60));
        RateLimiter::record_action($identifier, 60);
        $this->assertFalse(RateLimiter::is_allowed($identifier, 2, 60));
    }

    public function test_get_remaining_and_reset()
    {
        $identifier = 'unit-test-reset';

        RateLimiter::record_action($identifier, 60);
        $this->assertSame(4, RateLimiter::get_remaining($identifier, 5));
        $this->assertSame(1, RateLimiter::get_count($identifier));

        RateLimiter::reset($identifier);
        $this->assertSame(0, RateLimiter::get_count($identifier));
    }
}
