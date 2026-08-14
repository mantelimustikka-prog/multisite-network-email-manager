<?php

defined('ABSPATH') || exit;

use MNEM\EmailTemplates;
use PHPUnit\Framework\TestCase;

class EmailTemplatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options'] = array();
    }

    public function test_get_all_templates_includes_defaults()
    {
        $templates = EmailTemplates::get_all_templates();

        $this->assertArrayHasKey('welcome', $templates);
        $this->assertArrayHasKey('newsletter', $templates);
    }

    public function test_save_and_delete_custom_template()
    {
        $saved = EmailTemplates::save_template('custom-news', 'Custom News', 'Subject', '<p>Body</p>');
        $this->assertTrue($saved);

        $template = EmailTemplates::get_template('custom-news');
        $this->assertSame('Custom News', $template['name']);

        $deleted = EmailTemplates::delete_custom_template('custom-news');
        $this->assertTrue($deleted);
        $this->assertNull(EmailTemplates::get_template('custom-news'));
    }

    public function test_replace_variables_replaces_known_tokens()
    {
        $output = EmailTemplates::replace_variables(
            'Hi {user_name}, welcome to {site_name} at {date}.',
            array('{user_name}' => 'Alice')
        );

        $this->assertStringContainsString('Alice', $output);
        $this->assertStringNotContainsString('{site_name}', $output);
        $this->assertStringNotContainsString('{date}', $output);
    }
}
