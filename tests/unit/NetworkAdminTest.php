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
        unset($GLOBALS['mnem_last_json_response'], $GLOBALS['mnem_wp_mail_return'], $GLOBALS['mnem_last_redirect'], $GLOBALS['mnem_current_user_can'], $GLOBALS['mnem_verify_nonce']);
        $_POST = array();
        $_GET = array();
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
        $admin_init_callbacks = array_map(static function ($hook) {
            return is_array($hook['callback']) ? $hook['callback'][1] : null;
        }, $GLOBALS['mnem_hooks']['admin_init']);
        $this->assertContains('handle_queue_item_delete_action', $admin_init_callbacks);
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

    public function test_handle_queue_item_delete_action_redirects_after_single_delete()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array('id' => 12, 'site_id' => 1, 'campaign_id' => 0, 'recipient_email' => 'user@example.com', 'status' => 'pending');
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return strpos($query, 'SHOW TABLES LIKE') !== false ? 'wp_mnem_logs' : 0;
            }
        };

        $_POST = array(
            'mnem_action' => 'delete_queue_item',
            '_wpnonce' => 'test-nonce',
            'queue_id' => 12,
            'redirect_page' => 'mnem-queue',
        );

        $admin = new NetworkAdmin();
        $admin->handle_queue_item_delete_action();

        $this->assertStringContainsString('mnem_notice=queue_item_deleted', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_queue_item_delete_action_redirects_when_nothing_selected()
    {
        $_POST = array(
            'mnem_action' => 'delete_queue_items',
            '_wpnonce' => 'test-nonce',
            'redirect_page' => 'mnem-queue',
        );

        $admin = new NetworkAdmin();
        $admin->handle_queue_item_delete_action();

        $this->assertStringContainsString('mnem_notice=queue_nothing_selected', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_queue_item_delete_action_redirects_when_nonce_is_invalid()
    {
        $GLOBALS['mnem_verify_nonce'] = false;
        $_POST = array(
            'mnem_action' => 'delete_queue_item',
            '_wpnonce' => 'bad-nonce',
            'queue_id' => 12,
            'redirect_page' => 'mnem-queue',
        );

        $admin = new NetworkAdmin();
        $admin->handle_queue_item_delete_action();

        $this->assertStringContainsString('mnem_notice=queue_nonce_failed', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_queue_item_delete_action_respects_capability_checks()
    {
        $GLOBALS['mnem_current_user_can'] = false;
        $_POST = array(
            'mnem_action' => 'delete_queue_item',
            '_wpnonce' => 'test-nonce',
            'queue_id' => 12,
            'redirect_page' => 'mnem-queue',
        );

        $admin = new NetworkAdmin();
        $admin->handle_queue_item_delete_action();

        $this->assertArrayNotHasKey('mnem_last_redirect', $GLOBALS);
    }

    public function test_handle_queue_item_delete_action_redirects_after_status_delete()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_col($query)
            {
                $this->queries[] = $query;
                return array();
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 3;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return strpos($query, 'SHOW TABLES LIKE') !== false ? 'wp_mnem_logs' : 0;
            }
        };

        $_POST = array(
            'mnem_action' => 'delete_queue_by_status',
            '_wpnonce' => 'test-nonce',
            'status' => 'failed',
            'redirect_page' => 'mnem-queue',
        );

        $admin = new NetworkAdmin();
        $admin->handle_queue_item_delete_action();

        $this->assertStringContainsString('mnem_notice=queue_deleted_by_status', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('count=3', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('status=failed', $GLOBALS['mnem_last_redirect']);
    }
}
