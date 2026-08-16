<?php

defined('ABSPATH') || exit;

use MNEM\EmailTracker;
use PHPUnit\Framework\TestCase;

class EmailTrackerTest extends TestCase
{
    public function test_add_tracking_pixel_inserts_pixel_before_body_close(): void
    {
        $body = '<html><body><p>Hello</p></body></html>';
        $tracked = EmailTracker::add_tracking_pixel($body, 42);

        $this->assertStringContainsString('/wp-json/mnem/v1/track-open?token=', $tracked);
        $this->assertStringContainsString('<img src="', $tracked);
        $this->assertStringContainsString('</body>', $tracked);
    }

    public function test_rewrite_links_for_tracking_rewrites_http_links_only(): void
    {
        $body = '<a href="https://example.com/page?a=1">Link</a><a href="mailto:test@example.com">Mail</a>';
        $tracked = EmailTracker::rewrite_links_for_tracking($body, 42);

        $this->assertStringContainsString('/wp-json/mnem/v1/track-click?token=', $tracked);
        $this->assertStringContainsString('url=', $tracked);
        $this->assertStringNotContainsString('href="https://example.com/page?a=1"', $tracked);
        $this->assertStringContainsString('href="mailto:test@example.com"', $tracked);
    }
}
