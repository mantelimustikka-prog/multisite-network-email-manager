<?php

defined('ABSPATH') || exit;

use MNEM\EmailTracker;
use PHPUnit\Framework\TestCase;

class EmailTrackerTest extends TestCase
{
    public function test_add_tracking_pixel_returns_unmodified_body(): void
    {
        $body = '<html><body><p>Hello</p></body></html>';
        $tracked = EmailTracker::add_tracking_pixel($body, 42);

        $this->assertSame($body, $tracked);
    }

    public function test_rewrite_links_for_tracking_returns_unmodified_body(): void
    {
        $body = '<a href="https://example.com/page?a=1">Link</a><a href="mailto:test@example.com">Mail</a>';
        $tracked = EmailTracker::rewrite_links_for_tracking($body, 42);

        $this->assertSame($body, $tracked);
    }

    public function test_get_email_status_prioritizes_provider_status_from_database(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query, $x = 0, $y = 0)
            {
                if (strpos($query, 'opens_count') !== false) {
                    return 5;
                }
                if (strpos($query, 'clicks_count') !== false) {
                    return 2;
                }
                if (strpos($query, 'SELECT status FROM') !== false) {
                    return 'bounce';
                }
                return null;
            }

            public function prepare($query, ...$args)
            {
                return $query;
            }
        };

        $status = EmailTracker::get_email_status(42);

        $this->assertSame('Bounce', $status);
    }
}
