<?php

defined('ABSPATH') || exit;

use MNEM\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mnem_site_options'] = array();
    }

    public function test_get_returns_default_when_key_missing()
    {
        $this->assertSame('fallback', Settings::get('missing', 'fallback'));
    }

    public function test_set_updates_value()
    {
        Settings::set('enabled', 'yes');

        $this->assertSame('yes', Settings::get('enabled'));
    }

    public function test_delete_removes_key()
    {
        Settings::set('enabled', 'yes');
        Settings::delete('enabled');

        $this->assertSame('fallback', Settings::get('enabled', 'fallback'));
    }
}
