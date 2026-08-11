<?php

defined('ABSPATH') || exit;

use MNEM\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    public function test_scrub_context_redacts_sensitive_keys()
    {
        $scrubbed = Logger::scrub_context(
            array(
                'password' => 'secret',
                'smtp_password' => 'another-secret',
                'token' => 'abc123',
            )
        );

        $this->assertSame('***REDACTED***', $scrubbed['password']);
        $this->assertSame('***REDACTED***', $scrubbed['smtp_password']);
        $this->assertSame('***REDACTED***', $scrubbed['token']);
    }

    public function test_scrub_context_keeps_non_sensitive_keys()
    {
        $scrubbed = Logger::scrub_context(
            array(
                'email' => 'user@example.com',
                'status' => 'ok',
            )
        );

        $this->assertSame('user@example.com', $scrubbed['email']);
        $this->assertSame('ok', $scrubbed['status']);
    }
}
