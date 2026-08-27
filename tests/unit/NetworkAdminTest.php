<?php

defined('ABSPATH') || exit;

use MNEM\Admin\NetworkAdmin;
use PHPUnit\Framework\TestCase;

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value)
    {
        return trim((string) $value);
    }
}

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
        $GLOBALS['mnem_site_options'] = array();
        $GLOBALS['mnem_site_options'][\MNEM\SmtpDiagnostics::OPTION_RATE_LIMIT] = array();
        $GLOBALS['mnem_transients'] = array();
        unset($GLOBALS['mnem_last_json_response'], $GLOBALS['mnem_wp_mail_return'], $GLOBALS['mnem_last_redirect'], $GLOBALS['mnem_current_user_can'], $GLOBALS['mnem_verify_nonce'], $GLOBALS['mnem_current_user_email'], $GLOBALS['mnem_current_user_id'], $GLOBALS['mnem_deleted_users']);
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
        $this->assertArrayHasKey('wp_ajax_mnem_bulk_add_sms_subscribers', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_invalid_phone_list', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_invalid_phone_action', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_invalid_phone_delete_user', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_send_sms_test', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_get_sms_campaign_stats', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_preview_sms_recipients', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_get_sms_list_info', $GLOBALS['mnem_hooks']);
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
        $this->assertContains('handle_invalid_phone_action', $admin_init_callbacks);
        $this->assertContains('handle_sms_data_integrity_action', $admin_init_callbacks);
        $this->assertContains('handle_sms_campaign_action', $admin_init_callbacks);
        $this->assertContains('auto_update_sms_campaign_statuses', $admin_init_callbacks);
    }

    public function test_auto_update_sms_campaign_statuses_runs_on_sms_campaigns_page()
    {
        $_GET['page'] = 'mnem-sms-campaigns';
        $queries = array();

        $GLOBALS['wpdb'] = new class($queries) extends wpdb {
            private $queries_ref;

            public function __construct(array &$queries_ref)
            {
                $this->queries_ref = &$queries_ref;
            }

            public function get_col($query)
            {
                $this->queries_ref[] = $query;
                return array();
            }
        };

        $admin = new NetworkAdmin();
        $admin->auto_update_sms_campaign_statuses();

        $this->assertStringContainsString("FROM wp_mnem_sms_campaigns WHERE site_id = 1 AND status = 'sending'", implode("\n", $queries));
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

    public function test_handle_sms_campaign_action_creates_campaign_from_posted_data()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public string $lastQuery = '';

            public function query($query)
            {
                $this->lastQuery = $query;
                $this->insert_id = 27;
                return 1;
            }
        };

        $_POST = array(
            'mnem_action' => 'save_sms_campaign',
            '_wpnonce' => 'test-nonce',
            'redirect_page' => 'mnem-sms-campaigns',
            'name' => 'Controller Campaign',
            'description' => 'Created from controller',
            'message_body' => 'Hello from controller',
            'sms_list_id' => 4,
            'status' => 'draft',
            'scheduled_at' => '',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_campaign_action();

        $this->assertStringContainsString('mnem_notice=sms_campaign_created', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString("'Controller Campaign'", $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString("'Hello from controller'", $GLOBALS['wpdb']->lastQuery);
        $this->assertStringContainsString(', 4,', $GLOBALS['wpdb']->lastQuery);
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

    public function test_handle_subscriber_list_action_unsubscribes_user_with_default_reason()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $_POST = array(
            'mnem_action' => 'subscriber_unsubscribe_user',
            '_wpnonce' => 'test-nonce',
            'list_id' => 5,
            'user_id' => 7,
            'unsubscribe_reason' => '',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_subscriber_list_action();

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("unsubscribed_reason = 'Unsubscribed by admin'", $queries);
        $this->assertStringContainsString('mnem_notice=subscriber_unsubscribed', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('list_id=5', $GLOBALS['mnem_last_redirect']);
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
    }

    public function test_handle_bulk_add_sms_subscribers_returns_summary()
    {
        $GLOBALS['mnem_user_data'] = array(
            7 => (object) array('ID' => 7, 'user_login' => 'alice', 'user_email' => 'alice@example.com'),
        );
        $GLOBALS['mnem_user_meta'] = array(
            7 => array('phone_number' => '2345678901'),
        );
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array();
            }

            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };
        $_POST = array(
            'list_id' => 5,
            'user_ids' => '7',
            'skip_existing' => '0',
            'skip_unsubscribed' => '0',
            'phone_handling' => 'skip',
            'nonce' => 'test-nonce',
        );

        $admin = new NetworkAdmin();
        $admin->handle_bulk_add_sms_subscribers();

        $this->assertTrue($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(1, $GLOBALS['mnem_last_json_response']['data']['added']);
        $this->assertSame(0, $GLOBALS['mnem_last_json_response']['data']['invalid']);
    }

    public function test_ajax_get_invalid_phone_numbers_returns_items()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    array(
                        'id' => 2,
                        'phone_number' => '+12345678901',
                        'reason' => 'duplicate',
                        'list_id' => 5,
                        'user_id' => 0,
                        'blocked' => 0,
                        'created_at' => '2026-08-26 10:00:00',
                        'action_taken' => 'none',
                        'taken_by' => 0,
                        'taken_at' => null,
                    ),
                );
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };
        $_GET = array(
            'nonce' => 'test-nonce',
            'page_number' => 1,
            'per_page' => 20,
        );

        $admin = new NetworkAdmin();
        $admin->ajax_get_invalid_phone_numbers();

        $this->assertTrue($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(1, $GLOBALS['mnem_last_json_response']['data']['total']);
        $this->assertSame('+12345678901', $GLOBALS['mnem_last_json_response']['data']['items'][0]['phone_number']);
    }

    public function test_ajax_get_queue_preview_returns_stored_body_as_is()
    {
        // The queue preview must show the exact body stored in the database.
        // Header/footer are stored in the body at send-time via Queue::send_now(),
        // so the preview must NOT reconstruct them from current settings.
        update_site_option('mnem_force_global_header_footer', 1);
        update_site_option('mnem_global_header', '<p>Header</p>');
        update_site_option('mnem_global_footer', '<p>Footer</p>');

        $stored_body = '<p>Header</p><p>Body</p><p>Footer</p>';

        $GLOBALS['wpdb'] = new class ($stored_body) extends wpdb {
            private string $stored_body;

            public function __construct(string $stored_body)
            {
                $this->stored_body = $stored_body;
            }

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
                    'body' => $this->stored_body,
                    'from_email' => 'from@example.com',
                    'from_name' => 'From Name',
                    'headers' => '[]',
                    'status' => 'sent',
                    'attempts' => 1,
                    'scheduled_at' => '2026-08-17 12:00:00',
                    'sent_at' => '2026-08-17 12:05:00',
                    'opened' => 0,
                    'clicked' => 0,
                    'opens_count' => 0,
                    'clicks_count' => 0,
                    'created_at' => '2026-08-17 11:55:00',
                    'provider_message_id' => '',
                    'provider_metadata' => '{}',
                );
            }
        };
        $_POST['queue_id'] = 18;

        $admin = new NetworkAdmin();
        $admin->ajax_get_queue_preview();

        $this->assertTrue($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame($stored_body, $GLOBALS['mnem_last_json_response']['data']['body']);
    }

    public function test_handle_send_campaign_test_email_wraps_body_with_global_header_footer_when_forced()
    {
        update_site_option('mnem_force_global_header_footer', 1);
        update_site_option('mnem_global_header', '<p>Header</p>');
        update_site_option('mnem_global_footer', '<p>Footer</p>');

        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 5,
                    'subject' => 'Subject',
                    'body' => '<p>Body</p>',
                );
            }
        };
        $_POST = array(
            'campaign_id' => 5,
            'test_email' => 'tester@example.com',
        );

        $admin = new NetworkAdmin();
        $admin->handle_send_campaign_test_email();

        $this->assertTrue($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame('tester@example.com', $GLOBALS['mnem_last_wp_mail']['to']);
        $body = $GLOBALS['mnem_last_wp_mail']['message'];
        $this->assertStringContainsString('<p>Header</p>', $body);
        $this->assertStringContainsString('<p>Body</p>', $body);
        $this->assertStringContainsString('<p>Footer</p>', $body);
    }

    public function test_handle_preview_campaign_test_email_wraps_body_with_global_header_footer_when_forced()
    {
        update_site_option('mnem_force_global_header_footer', 1);
        update_site_option('mnem_global_header', '<p>Header</p>');
        update_site_option('mnem_global_footer', '<p>Footer</p>');
        $GLOBALS['mnem_current_user_email'] = 'previewer@example.com';

        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 7,
                    'subject' => 'Subject',
                    'body' => '<p>Body</p>',
                );
            }
        };
        $_POST = array(
            'campaign_id' => 7,
        );

        $admin = new NetworkAdmin();
        $admin->handle_preview_campaign_test_email();

        $this->assertTrue($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame('previewer@example.com', $GLOBALS['mnem_last_json_response']['data']['to']);
        $body = $GLOBALS['mnem_last_json_response']['data']['body'];
        $this->assertStringContainsString('<p>Header</p>', $body);
        $this->assertStringContainsString('<p>Body</p>', $body);
        $this->assertStringContainsString('<p>Footer</p>', $body);
    }

    // SMS subscriber list action handler tests

    public function test_handle_sms_subscriber_list_action_ignores_non_sms_actions()
    {
        $_POST = array(
            'mnem_action' => 'subscriber_save_list',
            '_wpnonce' => 'test-nonce',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertArrayNotHasKey('mnem_last_redirect', $GLOBALS);
    }

    public function test_handle_sms_subscriber_list_action_respects_capability_check()
    {
        $GLOBALS['mnem_current_user_can'] = false;
        $_POST = array(
            'mnem_action' => 'sms_subscriber_save_list',
            '_wpnonce' => 'test-nonce',
            'name' => 'My SMS List',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertArrayNotHasKey('mnem_last_redirect', $GLOBALS);
    }

    public function test_handle_sms_subscriber_list_action_fails_on_invalid_nonce()
    {
        $GLOBALS['mnem_verify_nonce'] = false;
        $_POST = array(
            'mnem_action' => 'sms_subscriber_save_list',
            '_wpnonce' => 'bad-nonce',
            'name' => 'My SMS List',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertStringContainsString('mnem_notice=sms_subscriber_operation_failed', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('page=mnem-sms-subscriber-lists', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_creates_new_list()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public $insert_id = 42;

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_save_list',
            '_wpnonce' => 'test-nonce',
            'list_id' => 0,
            'name' => 'New SMS List',
            'description' => 'A test SMS list',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertStringContainsString('mnem_notice=sms_subscriber_list_saved', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('page=mnem-sms-subscriber-lists', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_deletes_list()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_sms_subscriber_lists WHERE id = 3') !== false) {
                    return array('id' => 3, 'name' => 'Delete Me');
                }

                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_sms_list_subscribers WHERE list_id = 3') !== false) {
                    return 4;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_invalid_phone_numbers'") !== false) {
                    return 'wp_mnem_invalid_phone_numbers';
                }
                if (strpos($query, 'FROM wp_mnem_invalid_phone_numbers WHERE list_id = 3') !== false) {
                    return 2;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_logs'") !== false) {
                    return 'wp_mnem_logs';
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_queue'") !== false) {
                    return 'wp_mnem_queue';
                }
                if (strpos($query, 'COLUMN_NAME = \'list_id\'') !== false && strpos($query, 'wp_mnem_queue') !== false) {
                    return 0;
                }

                return 0;
            }

            public function get_col($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'SELECT id FROM wp_mnem_logs') !== false) {
                    return array(55);
                }

                return array();
            }

            public function query($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'DELETE FROM wp_mnem_invalid_phone_numbers WHERE list_id = 3') !== false) {
                    return 2;
                }
                if (strpos($query, 'DELETE FROM wp_mnem_logs WHERE id IN (55)') !== false) {
                    return 1;
                }
                if (strpos($query, 'DELETE FROM wp_mnem_sms_list_subscribers WHERE list_id = 3') !== false) {
                    return 4;
                }
                if (strpos($query, 'DELETE FROM wp_mnem_sms_subscriber_lists WHERE id = 3') !== false) {
                    return 1;
                }
                return 1;
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_delete_list',
            '_wpnonce' => 'test-nonce',
            'list_id' => 3,
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertStringContainsString('mnem_notice=sms_subscriber_list_deleted', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('deleted_total=7', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('page=mnem-sms-subscriber-lists', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_requires_confirmation_for_large_delete()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_sms_subscriber_lists WHERE id = 9') !== false) {
                    return array('id' => 9, 'name' => 'Big List');
                }

                return null;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_sms_list_subscribers WHERE list_id = 9') !== false) {
                    return 120;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_invalid_phone_numbers'") !== false) {
                    return 'wp_mnem_invalid_phone_numbers';
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_logs'") !== false) {
                    return 'wp_mnem_logs';
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_queue'") !== false) {
                    return 'wp_mnem_queue';
                }
                if (strpos($query, 'COLUMN_NAME = \'list_id\'') !== false && strpos($query, 'wp_mnem_queue') !== false) {
                    return 0;
                }

                return 0;
            }

            public function get_col($query)
            {
                $this->queries[] = $query;
                return array();
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_delete_list',
            '_wpnonce' => 'test-nonce',
            'list_id' => 9,
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertStringContainsString('mnem_notice=sms_subscriber_delete_confirmation_required', $GLOBALS['mnem_last_redirect']);
        $this->assertStringNotContainsString('DELETE FROM wp_mnem_sms_subscriber_lists', implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_handle_sms_data_integrity_action_stores_check_results()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                if (strpos($query, 'FROM wp_mnem_sms_list_subscribers s LEFT JOIN wp_mnem_sms_subscriber_lists l') !== false) {
                    return 0;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_invalid_phone_numbers'") !== false) {
                    return 'wp_mnem_invalid_phone_numbers';
                }
                if (strpos($query, 'FROM wp_mnem_invalid_phone_numbers p LEFT JOIN wp_mnem_sms_subscriber_lists l') !== false) {
                    return 0;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_queue'") !== false) {
                    return 'wp_mnem_queue';
                }
                if (strpos($query, 'COLUMN_NAME = \'list_id\'') !== false && strpos($query, 'wp_mnem_queue') !== false) {
                    return 0;
                }
                if (strpos($query, "SHOW TABLES LIKE 'wp_mnem_logs'") !== false) {
                    return 'wp_mnem_logs';
                }
                if (strpos($query, 'SELECT COUNT(1) FROM wp_mnem_sms_subscriber_lists') !== false) {
                    return 1;
                }
                if (strpos($query, 'SELECT COUNT(1) FROM wp_mnem_sms_list_subscribers') !== false) {
                    return 4;
                }
                if (strpos($query, 'SELECT COUNT(1) FROM wp_mnem_invalid_phone_numbers') !== false) {
                    return 2;
                }

                return 0;
            }

            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array();
            }
        };

        $_POST = array(
            'mnem_sms_integrity_action' => 'check_sms_data_integrity',
            'mnem_sms_integrity_nonce' => 'test-nonce',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_data_integrity_action();

        $this->assertStringContainsString('mnem_notice=sms_integrity_checked', $GLOBALS['mnem_last_redirect']);
        $this->assertNotEmpty($GLOBALS['mnem_transients']['mnem_sms_integrity_result_1']['value']);
    }

    public function test_handle_sms_subscriber_list_action_adds_user_by_id_with_phone_number()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_add_user',
            '_wpnonce' => 'test-nonce',
            'list_id' => 2,
            'user_identifier' => '10',
            'phone_number' => '+358401234567',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('+358401234567', $queries);
        $this->assertStringContainsString('mnem_notice=sms_subscriber_added', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_rejects_unknown_user_identifier()
    {
        $_POST = array(
            'mnem_action' => 'sms_subscriber_add_user',
            '_wpnonce' => 'test-nonce',
            'list_id' => 2,
            'user_identifier' => 'nonexistent_user_xyz',
            'phone_number' => '',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertStringContainsString('mnem_notice=sms_subscriber_operation_failed', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_removes_user()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_remove_user',
            '_wpnonce' => 'test-nonce',
            'list_id' => 4,
            'user_id' => 9,
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertStringContainsString('mnem_notice=sms_subscriber_removed', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('list_id=4', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_unsubscribes_user_with_default_reason()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_unsubscribe_user',
            '_wpnonce' => 'test-nonce',
            'list_id' => 5,
            'user_id' => 7,
            'unsubscribe_reason' => '',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("unsubscribed_reason = 'Unsubscribed by admin'", $queries);
        $this->assertStringContainsString('mnem_notice=sms_subscriber_unsubscribed', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('list_id=5', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_unsubscribes_user_with_custom_reason()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 1;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_unsubscribe_user',
            '_wpnonce' => 'test-nonce',
            'list_id' => 5,
            'user_id' => 7,
            'unsubscribe_reason' => 'Customer request',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("unsubscribed_reason = 'Customer request'", $queries);
        $this->assertStringContainsString('mnem_notice=sms_subscriber_unsubscribed', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_unsubscribe_fails_when_not_subscribed()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_unsubscribe_user',
            '_wpnonce' => 'test-nonce',
            'list_id' => 5,
            'user_id' => 99,
            'unsubscribe_reason' => '',
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertStringContainsString('mnem_notice=sms_subscriber_operation_failed', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_restores_user()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_restore_user',
            '_wpnonce' => 'test-nonce',
            'list_id' => 6,
            'user_id' => 11,
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertStringContainsString('mnem_notice=sms_subscriber_restored', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('list_id=6', $GLOBALS['mnem_last_redirect']);
    }

    public function test_handle_sms_subscriber_list_action_imports_csv()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_var($query)
            {
                $this->queries[] = $query;
                return 0;
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $_POST = array(
            'mnem_action' => 'sms_subscriber_import_csv',
            '_wpnonce' => 'test-nonce',
            'list_id' => 2,
            'csv_content' => "10:+358401111111\n20:+358402222222",
        );

        $admin = new TestableNetworkAdmin();
        $admin->handle_sms_subscriber_list_action();

        $this->assertStringContainsString('mnem_notice=sms_subscriber_csv_imported', $GLOBALS['mnem_last_redirect']);
        $this->assertStringContainsString('list_id=2', $GLOBALS['mnem_last_redirect']);
    }

    public function test_init_registers_test_sms_connection_hook()
    {
        $admin = new NetworkAdmin();
        $admin->init();

        $this->assertArrayHasKey('wp_ajax_mnem_test_sms_connection', $GLOBALS['mnem_hooks']);
    }

    public function test_ajax_test_sms_connection_without_configured_provider()
    {
        $GLOBALS['mnem_site_options'][\MNEM\SmsSettings::OPTION_PROVIDER] = '';

        $admin = new NetworkAdmin();
        $admin->ajax_test_sms_connection();

        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(400, $GLOBALS['mnem_last_json_response']['status_code']);
        $this->assertSame('No SMS provider configured.', $GLOBALS['mnem_last_json_response']['data']['message']);
    }

    public function test_ajax_test_sms_connection_with_invalid_provider_key()
    {
        $GLOBALS['mnem_site_options'][\MNEM\SmsSettings::OPTION_PROVIDER] = 'nonexistent_provider';

        $admin = new NetworkAdmin();
        $admin->ajax_test_sms_connection();

        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(400, $GLOBALS['mnem_last_json_response']['status_code']);
        $this->assertSame('SMS provider not available.', $GLOBALS['mnem_last_json_response']['data']['message']);
    }

    public function test_ajax_test_sms_connection_with_valid_provider_returns_provider_result()
    {
        $GLOBALS['mnem_site_options'][\MNEM\SmsSettings::OPTION_PROVIDER] = 'twilio';

        $admin = new NetworkAdmin();
        $admin->ajax_test_sms_connection();

        // Provider now has a real implementation; with no credentials it returns a specific error.
        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(400, $GLOBALS['mnem_last_json_response']['status_code']);
        $this->assertStringContainsString('required', $GLOBALS['mnem_last_json_response']['data']['message']);
    }

    public function test_ajax_test_sms_connection_insufficient_permissions()
    {
        $GLOBALS['mnem_current_user_can'] = false;

        $admin = new NetworkAdmin();
        $admin->ajax_test_sms_connection();

        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
    }
}
