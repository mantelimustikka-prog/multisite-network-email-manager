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
        $GLOBALS['mnem_site_options'][\MNEM\SmtpDiagnostics::OPTION_RATE_LIMIT] = array();
        unset($GLOBALS['mnem_last_json_response'], $GLOBALS['mnem_wp_mail_return']);
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
        $this->assertArrayHasKey('wp_ajax_mnem_process_queue_now', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_retry_failed_queue', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_toggle_campaign_pause', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_test_connection', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_send_test_email', $GLOBALS['mnem_hooks']);
    }

    public function test_ajax_send_test_email_returns_error_when_mail_fails()
    {
        $GLOBALS['mnem_wp_mail_return'] = false;
        $_POST['email'] = 'admin@example.com';

        $admin = new NetworkAdmin();
        $admin->ajax_send_test_email();

        $this->assertArrayHasKey('mnem_last_json_response', $GLOBALS);
        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(400, $GLOBALS['mnem_last_json_response']['status_code']);
    }
}
