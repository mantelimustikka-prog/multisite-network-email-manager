<?php

defined('ABSPATH') || exit;

use MNEM\EmailFormatter;
use MNEM\SmtpSettings;
use PHPUnit\Framework\TestCase;

class EmailFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mnem_site_options'] = array();
    }

    // ------------------------------------------------------------------
    // SmtpSettings helpers
    // ------------------------------------------------------------------

    public function test_get_sender_name_returns_stored_value()
    {
        update_site_option('mnem_sender_name', 'My Company');
        $this->assertSame('My Company', SmtpSettings::get_sender_name());
    }

    public function test_get_sender_name_falls_back_to_bloginfo()
    {
        // No option set — should return get_bloginfo('name') which is 'Test Site' in the mock.
        $this->assertSame('Test Site', SmtpSettings::get_sender_name());
    }

    public function test_get_sender_email_returns_stored_value()
    {
        update_site_option('mnem_sender_email', 'sender@example.com');
        $this->assertSame('sender@example.com', SmtpSettings::get_sender_email());
    }

    public function test_get_sender_email_falls_back_to_admin_email()
    {
        $GLOBALS['mnem_site_options']['admin_email'] = 'admin@example.com';
        $this->assertSame('admin@example.com', SmtpSettings::get_sender_email());
    }

    public function test_is_global_header_footer_enabled_false_by_default()
    {
        $this->assertFalse(SmtpSettings::is_global_header_footer_enabled());
    }

    public function test_is_global_header_footer_enabled_true_when_set()
    {
        update_site_option('mnem_force_global_header_footer', 1);
        $this->assertTrue(SmtpSettings::is_global_header_footer_enabled());
    }

    public function test_is_global_header_footer_enabled_false_when_zero()
    {
        update_site_option('mnem_force_global_header_footer', 0);
        $this->assertFalse(SmtpSettings::is_global_header_footer_enabled());
    }

    // ------------------------------------------------------------------
    // EmailFormatter
    // ------------------------------------------------------------------

    public function test_apply_returns_body_unchanged_when_disabled()
    {
        $body = '<p>Hello World</p>';
        $this->assertSame($body, EmailFormatter::apply_global_header_footer($body));
    }

    public function test_apply_prepends_header_and_appends_footer()
    {
        update_site_option('mnem_force_global_header_footer', 1);
        update_site_option('mnem_global_header', '<p>Header</p>');
        update_site_option('mnem_global_footer', '<p>Footer</p>');

        $result = EmailFormatter::apply_global_header_footer('<p>Body</p>');

        $this->assertStringContainsString('<p>Header</p>', $result);
        $this->assertStringContainsString('<p>Body</p>', $result);
        $this->assertStringContainsString('<p>Footer</p>', $result);

        $pos_header = strpos($result, '<p>Header</p>');
        $pos_body   = strpos($result, '<p>Body</p>');
        $pos_footer = strpos($result, '<p>Footer</p>');

        $this->assertLessThan($pos_body, $pos_header, 'Header must appear before body');
        $this->assertGreaterThan($pos_body, $pos_footer, 'Footer must appear after body');
    }

    public function test_apply_with_empty_header_omits_leading_separator()
    {
        update_site_option('mnem_force_global_header_footer', 1);
        update_site_option('mnem_global_header', '');
        update_site_option('mnem_global_footer', '<p>Footer</p>');

        $result = EmailFormatter::apply_global_header_footer('Body');

        $this->assertStringStartsWith('Body', $result);
        $this->assertStringContainsString('<p>Footer</p>', $result);
    }

    public function test_apply_with_empty_footer_omits_trailing_separator()
    {
        update_site_option('mnem_force_global_header_footer', 1);
        update_site_option('mnem_global_header', '<p>Header</p>');
        update_site_option('mnem_global_footer', '');

        $result = EmailFormatter::apply_global_header_footer('Body');

        $this->assertStringContainsString('<p>Header</p>', $result);
        $this->assertStringEndsWith('Body', $result);
    }

    public function test_apply_collapses_excessive_newlines()
    {
        update_site_option('mnem_force_global_header_footer', 1);
        update_site_option('mnem_global_header', "Header\n\n\n\n");
        update_site_option('mnem_global_footer', "\n\n\nFooter");

        $result = EmailFormatter::apply_global_header_footer("Body");

        // Should not have 3 or more consecutive newlines.
        $this->assertSame(0, preg_match('/\n{3,}/', $result));
    }

    public function test_apply_with_both_empty_returns_body()
    {
        update_site_option('mnem_force_global_header_footer', 1);
        update_site_option('mnem_global_header', '');
        update_site_option('mnem_global_footer', '');

        $result = EmailFormatter::apply_global_header_footer('Body only');
        $this->assertSame('Body only', $result);
    }
}
