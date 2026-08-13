<?php

defined('ABSPATH') || exit;

use MNEM\Admin\NetworkAdmin;
use PHPUnit\Framework\TestCase;

class NetworkAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_hooks'] = array();
    }

    public function test_init_registers_dashboard_and_ajax_hooks()
    {
        $admin = new NetworkAdmin();
        $admin->init();

        $this->assertArrayHasKey('admin_init', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('admin_enqueue_scripts', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('network_admin_menu', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_dashboard_stats', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_process_queue', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_retry_failed_queue', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_toggle_campaign_pause', $GLOBALS['mnem_hooks']);
    }
}
