<?php

defined('ABSPATH') || exit;

use MNEM\Admin\AdminMenu;
use PHPUnit\Framework\TestCase;

class AdminMenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_menu_pages'] = array();
        $GLOBALS['mnem_submenu_pages'] = array();
    }

    public function test_register_menus_uses_network_email_manager_and_removes_smtp_submenus()
    {
        $menu = new AdminMenu();
        $menu->register_menus();

        $this->assertNotEmpty($GLOBALS['mnem_menu_pages']);
        $this->assertSame('Network Email Manager', $GLOBALS['mnem_menu_pages'][0][1]);

        $submenu_slugs = array_map(static function ($submenu) {
            return $submenu[4];
        }, $GLOBALS['mnem_submenu_pages']);

        $this->assertNotContains('mnem-smtp-settings', $submenu_slugs);
        $this->assertNotContains('mnem-smtp-diagnostics', $submenu_slugs);
    }
}

