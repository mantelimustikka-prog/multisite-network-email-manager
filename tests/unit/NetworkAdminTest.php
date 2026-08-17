<?php

defined('ABSPATH') || exit;

use MNEM\Admin\NetworkAdmin;
use PHPUnit\Framework\TestCase;

class TestableNetworkAdmin extends NetworkAdmin
{
    protected function exit_after_redirect()
    {
    }
}

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
        $this->assertArrayHasKey('wp_ajax_mnem_send_campaign_test_email', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_preview_campaign_test_email', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_send_queue_item_now', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_table_diagnostics_recreate', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_table_diagnostics_optimize', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_table_diagnostics_repair', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_table_diagnostics_export', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_load_batch_users', $GLOBALS['mnem_hooks']);
        $this->assertArrayNotHasKey('wp_ajax_mnem_campaign_send_test_email', $GLOBALS['mnem_hooks']);
        $this->assertArrayNotHasKey('wp_ajax_mnem_campaign_preview_test_email', $GLOBALS['mnem_hooks']);
        $this->assertArrayNotHasKey('wp_ajax_mnem_get_error_details', $GLOBALS['mnem_hooks']);
        $this->assertArrayNotHasKey('wp_ajax_mnem_delete_error_log', $GLOBALS['mnem_hooks']);
        $this->assertArrayNotHasKey('wp_ajax_mnem_export_error_logs', $GLOBALS['mnem_hooks']);
        $this->assertArrayNotHasKey('wp_ajax_mnem_clear_old_errors', $GLOBALS['mnem_hooks']);
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

    public function test_ajax_send_queue_item_now_rejects_invalid_queue_id()
    {
        $_POST['queue_id'] = 0;

        $admin = new NetworkAdmin();
        $admin->ajax_send_queue_item_now();

        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(400, $GLOBALS['mnem_last_json_response']['status_code']);
        $this->assertSame('Invalid queue ID.', $GLOBALS['mnem_last_json_response']['data']['message']);
    }

    public function test_ajax_send_queue_item_now_returns_success_notice_for_send_now()
    {
        $GLOBALS['mnem_site_options']['mnem_smtp_settings'] = array(
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'from_email' => 'sender@example.test',
            'from_name' => 'Sender',
            'provider_type' => 'smtp',
            'provider_config' => array(),
            'fallback_provider' => '',
            'fallback_enabled' => false,
        );
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 18,
                    'site_id' => 1,
                    'blog_id' => 1,
                    'campaign_id' => 0,
                    'recipient_email' => 'user@example.com',
                    'subject' => 'Subject',
                    'body' => 'Body',
                    'from_email' => 'from@example.com',
                    'from_name' => 'From Name',
                    'headers' => '[]',
                    'attachments' => '[]',
                    'metadata' => '{}',
                    'attempts' => 1,
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };
        $_POST['queue_id'] = 18;

        $admin = new NetworkAdmin();
        $admin->ajax_send_queue_item_now();

        $this->assertTrue($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame('queue_item_sent_now', $GLOBALS['mnem_last_json_response']['data']['notice']);
        $this->assertSame('user@example.com', $GLOBALS['mnem_last_wp_mail']['to']);
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

        $admin = new TestableNetworkAdmin();
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

        $admin = new TestableNetworkAdmin();
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

        $admin = new TestableNetworkAdmin();
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

        $admin = new TestableNetworkAdmin();
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

        $admin = new TestableNetworkAdmin();
        $admin->handle_queue_item_delete_action();

        $this->assertStringContainsString('mnem_notice=queue_deleted_by_status', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('count=3', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('status=failed', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_save_sender_settings_saves_force_sender_option()
    {
        $_POST = array(
            'mnem_action' => 'save_sender_settings',
            '_wpnonce' => 'test-nonce',
            'sender_name' => 'Forced Name',
            'sender_email' => 'forced@example.com',
            'force_sender_settings' => '1',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_save_sender_settings();

        $this->assertSame('Forced Name', get_site_option('mnem_sender_name'));
        $this->assertSame('forced@example.com', get_site_option('mnem_sender_email'));
        $this->assertSame(1, get_site_option(\MNEM\SmtpSettings::OPTION_FORCE_SENDER));
        $this->assertStringContainsString('mnem_notice=sender_settings_saved', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_save_general_settings_saves_campaign_rate_limits()
    {
        $_POST = array(
            'mnem_action' => 'save_general_settings',
            '_wpnonce' => 'test-nonce',
            'mnem_queue_retention_days' => 120,
            'mnem_campaign_rate_limit_per_minute' => 75,
            'mnem_campaign_rate_limit_per_hour' => 1200,
            'mnem_campaign_rate_limit_per_day' => 15000,
            'mnem_campaign_delay_between_sends' => 250,
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_save_general_settings();

        $this->assertSame(120, \MNEM\SmtpSettings::get_queue_retention_days());
        $this->assertSame(75, \MNEM\SmtpSettings::get_campaign_rate_limit_per_minute());
        $this->assertSame(1200, \MNEM\SmtpSettings::get_campaign_rate_limit_per_hour());
        $this->assertSame(15000, \MNEM\SmtpSettings::get_campaign_rate_limit_per_day());
        $this->assertSame(250, \MNEM\SmtpSettings::get_campaign_delay_between_sends());
        $this->assertStringContainsString('mnem_notice=general_settings_saved', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_load_batch_users_rejects_invalid_batch_sizes()
    {
        $_POST = array(
            'batch_size' => 750,
            'offset' => 0,
        );

        $admin = new NetworkAdmin();
        $admin->handle_load_batch_users();

        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(400, $GLOBALS['mnem_last_json_response']['status_code']);
        $this->assertSame('Invalid batch size', $GLOBALS['mnem_last_json_response']['data']['message']);
    }

    public function test_handle_load_batch_users_returns_batch_payload_for_allowed_sizes()
    {
        $_POST = array(
            'batch_size' => 500,
            'offset' => 1000,
        );

        $admin = new NetworkAdmin();
        $admin->handle_load_batch_users();

        $this->assertTrue($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(500, $GLOBALS['mnem_last_json_response']['data']['batch_size']);
        $this->assertSame(1000, $GLOBALS['mnem_last_json_response']['data']['offset']);
        $this->assertSame(1000, $GLOBALS['mnem_last_json_response']['data']['next_offset']);
        $this->assertSame(0, $GLOBALS['mnem_last_json_response']['data']['loaded']);
        $this->assertFalse($GLOBALS['mnem_last_json_response']['data']['has_more']);
        $this->assertIsArray($GLOBALS['mnem_last_json_response']['data']['users']);
    }
}
