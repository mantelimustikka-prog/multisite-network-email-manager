<?php

defined('ABSPATH') || exit;

use MNEM\SmsMessageTemplate;
use PHPUnit\Framework\TestCase;

class SmsMessageTemplateTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // replace_variables — basic replacements
    // ---------------------------------------------------------------------------

    public function test_replaces_user_name()
    {
        $result = SmsMessageTemplate::replace_variables('Hello {user_name}!', array(
            'user_name'    => 'John Smith',
            'phone_number' => '+1234567890',
            'site_name'    => 'My Site',
            'date'         => '2026-08-28',
        ));

        $this->assertSame('Hello John Smith!', $result);
    }

    public function test_replaces_phone_number()
    {
        $result = SmsMessageTemplate::replace_variables('Your phone: {phone_number}', array(
            'user_name'    => 'Jane',
            'phone_number' => '+9876543210',
            'site_name'    => 'My Site',
            'date'         => '2026-08-28',
        ));

        $this->assertSame('Your phone: +9876543210', $result);
    }

    public function test_replaces_site_name()
    {
        $result = SmsMessageTemplate::replace_variables('From {site_name}', array(
            'user_name'    => '',
            'phone_number' => '',
            'site_name'    => 'Acme Corp',
            'date'         => '2026-08-28',
        ));

        $this->assertSame('From Acme Corp', $result);
    }

    public function test_replaces_date()
    {
        $result = SmsMessageTemplate::replace_variables('Date: {date}', array(
            'user_name'    => '',
            'phone_number' => '',
            'site_name'    => '',
            'date'         => '2026-08-28',
        ));

        $this->assertSame('Date: 2026-08-28', $result);
    }

    public function test_replaces_multiple_variables()
    {
        $result = SmsMessageTemplate::replace_variables(
            'Hello {user_name}, your phone {phone_number} is confirmed at {site_name} on {date}.',
            array(
                'user_name'    => 'John',
                'phone_number' => '+1111111111',
                'site_name'    => 'TestSite',
                'date'         => '2026-08-28',
            )
        );

        $this->assertSame('Hello John, your phone +1111111111 is confirmed at TestSite on 2026-08-28.', $result);
    }

    // ---------------------------------------------------------------------------
    // replace_variables — missing / empty context
    // ---------------------------------------------------------------------------

    public function test_missing_user_name_replaced_with_empty_string()
    {
        $result = SmsMessageTemplate::replace_variables('Hello {user_name}', array(
            'phone_number' => '+1234567890',
            'site_name'    => 'Site',
            'date'         => '2026-08-28',
        ));

        $this->assertSame('Hello ', $result);
    }

    public function test_missing_phone_number_replaced_with_empty_string()
    {
        $result = SmsMessageTemplate::replace_variables('{phone_number}', array(
            'user_name' => 'Bob',
            'site_name' => 'Site',
            'date'      => '2026-08-28',
        ));

        $this->assertSame('', $result);
    }

    public function test_missing_site_name_falls_back_to_network()
    {
        $result = SmsMessageTemplate::replace_variables('{site_name}', array(
            'user_name'    => 'Bob',
            'phone_number' => '',
            'date'         => '2026-08-28',
        ));

        $this->assertSame('Network', $result);
    }

    public function test_empty_user_name_is_empty_string()
    {
        $result = SmsMessageTemplate::replace_variables('Hi {user_name}', array(
            'user_name'    => '',
            'phone_number' => '',
            'site_name'    => 'S',
            'date'         => '2026-01-01',
        ));

        $this->assertSame('Hi ', $result);
    }

    public function test_no_variables_in_template_returns_unchanged()
    {
        $template = 'No placeholders here.';
        $result   = SmsMessageTemplate::replace_variables($template, array(
            'user_name'    => 'Alice',
            'phone_number' => '+1',
            'site_name'    => 'Site',
            'date'         => '2026-08-28',
        ));

        $this->assertSame($template, $result);
    }

    public function test_empty_template_returns_empty_string()
    {
        $result = SmsMessageTemplate::replace_variables('', array(
            'user_name'    => 'Alice',
            'phone_number' => '+1',
            'site_name'    => 'Site',
            'date'         => '2026-08-28',
        ));

        $this->assertSame('', $result);
    }

    // ---------------------------------------------------------------------------
    // build_context
    // ---------------------------------------------------------------------------

    public function test_build_context_uses_display_name()
    {
        $subscriber = array(
            'display_name'  => 'Jane Doe',
            'phone_number'  => '+2222222222',
            'subscriber_type' => 'user',
        );

        $context = SmsMessageTemplate::build_context($subscriber);

        $this->assertSame('Jane Doe', $context['user_name']);
        $this->assertSame('+2222222222', $context['phone_number']);
    }

    public function test_build_context_standalone_subscriber_uses_subscriber_name()
    {
        $subscriber = array(
            'display_name'    => 'Bob Johnson',
            'phone_number'    => '+3333333333',
            'subscriber_type' => 'standalone',
        );

        $context = SmsMessageTemplate::build_context($subscriber);

        $this->assertSame('Bob Johnson', $context['user_name']);
    }

    public function test_build_context_missing_display_name_is_empty()
    {
        $context = SmsMessageTemplate::build_context(array(
            'phone_number' => '+4444444444',
        ));

        $this->assertSame('', $context['user_name']);
    }

    public function test_build_context_site_name_uses_get_bloginfo()
    {
        // bootstrap.php stubs get_bloginfo('name') to return 'Test Site'
        $context = SmsMessageTemplate::build_context(array(
            'display_name' => 'Alice',
            'phone_number' => '+1',
        ));

        $this->assertSame('Test Site', $context['site_name']);
    }

    public function test_build_context_date_is_today()
    {
        $context = SmsMessageTemplate::build_context(array(
            'display_name' => 'Alice',
            'phone_number' => '+1',
        ));

        $this->assertRegExp('/^\d{4}-\d{2}-\d{2}$/', $context['date']);
    }

    // ---------------------------------------------------------------------------
    // Integration: build_context + replace_variables
    // ---------------------------------------------------------------------------

    public function test_full_variable_replacement_via_build_context()
    {
        $subscriber = array(
            'display_name' => 'John Smith',
            'phone_number' => '+1111111111',
        );

        $context = SmsMessageTemplate::build_context($subscriber);
        $result  = SmsMessageTemplate::replace_variables(
            'Hello {user_name}, verify {phone_number} on {date} at {site_name}.',
            $context
        );

        $this->assertStringContainsString('Hello John Smith', $result);
        $this->assertStringContainsString('+1111111111', $result);
        $this->assertStringContainsString('Test Site', $result);
        $this->assertStringNotContainsString('{user_name}', $result);
        $this->assertStringNotContainsString('{phone_number}', $result);
        $this->assertStringNotContainsString('{site_name}', $result);
        $this->assertStringNotContainsString('{date}', $result);
    }
}
