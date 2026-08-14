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
                'api_key' => 'my-api-key',
                'server_token' => 'srv-token',
            )
        );

        $this->assertSame('***REDACTED***', $scrubbed['password']);
        $this->assertSame('***REDACTED***', $scrubbed['smtp_password']);
        $this->assertSame('***REDACTED***', $scrubbed['token']);
        $this->assertSame('***REDACTED***', $scrubbed['api_key']);
        $this->assertSame('***REDACTED***', $scrubbed['server_token']);
    }

    public function test_scrub_context_keeps_non_sensitive_keys()
    {
        $scrubbed = Logger::scrub_context(
            array(
                'email' => 'user@example.com',
                'status' => 'ok',
                'error' => 'Something went wrong',
                'provider_message' => 'Brevo returned HTTP 401',
                'http_code' => 401,
                'recipient' => 'user@example.com',
            )
        );

        $this->assertSame('user@example.com', $scrubbed['email']);
        $this->assertSame('ok', $scrubbed['status']);
        $this->assertSame('Something went wrong', $scrubbed['error']);
        $this->assertSame('Brevo returned HTTP 401', $scrubbed['provider_message']);
        $this->assertSame(401, $scrubbed['http_code']);
        $this->assertSame('user@example.com', $scrubbed['recipient']);
    }

    public function test_scrub_keys_uses_exact_match_not_substring()
    {
        $scrubbed = Logger::scrub_context(
            array(
                'error'          => 'Should NOT be scrubbed',
                'provider_error' => 'Should NOT be scrubbed',
                'error_message'  => 'Should NOT be scrubbed',
                'api_key_length' => 32,
            )
        );

        $this->assertSame('Should NOT be scrubbed', $scrubbed['error']);
        $this->assertSame('Should NOT be scrubbed', $scrubbed['provider_error']);
        $this->assertSame('Should NOT be scrubbed', $scrubbed['error_message']);
        $this->assertSame(32, $scrubbed['api_key_length']);
    }
}
