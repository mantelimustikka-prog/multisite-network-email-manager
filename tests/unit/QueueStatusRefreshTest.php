<?php

defined('ABSPATH') || exit;

use MNEM\Admin\NetworkAdmin;
use PHPUnit\Framework\TestCase;

class QueueStatusRefreshTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_hooks'] = array();
        $GLOBALS['mnem_current_user_can'] = true;
        unset($GLOBALS['mnem_last_json_response'], $GLOBALS['mnem_http_response']);
        $_POST = array();
        $GLOBALS['wpdb'] = new wpdb();
    }

    protected function tearDown(): void
    {
        $_POST = array();
        unset($GLOBALS['mnem_current_user_can'], $GLOBALS['mnem_http_response'], $GLOBALS['mnem_site_options']['mnem_smtp_settings']);
        parent::tearDown();
    }

    public function test_init_registers_status_refresh_and_webhook_test_ajax_hooks()
    {
        $admin = new NetworkAdmin();
        $admin->init();

        $this->assertArrayHasKey('wp_ajax_mnem_refresh_queue_statuses', $GLOBALS['mnem_hooks']);
        $this->assertArrayHasKey('wp_ajax_mnem_test_webhook_endpoint', $GLOBALS['mnem_hooks']);
    }

    public function test_refresh_queue_statuses_rejects_empty_selection()
    {
        $_POST['queue_ids'] = array();

        $admin = new NetworkAdmin();
        $admin->ajax_refresh_queue_statuses();

        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(400, $GLOBALS['mnem_last_json_response']['status_code']);
    }

    public function test_refresh_queue_statuses_reports_updated_items()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'status' => 'sent',
                    'provider_type' => 'brevo',
                    'provider_message_id' => 'msg-42',
                    'recipient_email' => 'jane@example.com',
                );
            }
        };

        $GLOBALS['mnem_site_options']['mnem_smtp_settings'] = array(
            'provider_type' => 'brevo',
            'provider_config' => array(
                'brevo' => array('api_key' => base64_encode('brevo-key')),
            ),
        );
        $GLOBALS['mnem_http_response'] = array(
            'response' => array('code' => 200),
            'body' => wp_json_encode(array('events' => array(array('event' => 'delivered')))),
        );

        $_POST['queue_ids'] = array('12', '12', '0');

        $admin = new NetworkAdmin();
        $admin->ajax_refresh_queue_statuses();

        $response = $GLOBALS['mnem_last_json_response'];
        $this->assertTrue($response['success']);
        $this->assertSame(1, $response['data']['checked']);
        $this->assertSame(1, $response['data']['updated']);
        $this->assertSame(12, $response['data']['items'][0]['id']);
        $this->assertSame('delivered', $response['data']['items'][0]['status']);
        $this->assertTrue($response['data']['items'][0]['changed']);
        $this->assertStringContainsString("status = 'delivered'", implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_test_webhook_endpoint_rejects_unknown_provider()
    {
        $_POST['provider'] = 'not-a-provider';

        $admin = new NetworkAdmin();
        $admin->ajax_test_webhook_endpoint();

        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
        $this->assertSame(400, $GLOBALS['mnem_last_json_response']['status_code']);
    }

    public function test_test_webhook_endpoint_reports_success_for_2xx_response()
    {
        $_POST['provider'] = 'brevo';
        $GLOBALS['mnem_http_response'] = array(
            'response' => array('code' => 200),
            'body' => '{"success":true}',
        );

        $admin = new NetworkAdmin();
        $admin->ajax_test_webhook_endpoint();

        $response = $GLOBALS['mnem_last_json_response'];
        $this->assertTrue($response['success']);
        $this->assertSame(200, $response['data']['code']);
        $this->assertStringContainsString('mnem/v1/webhooks/brevo', $response['data']['url']);
    }

    public function test_test_webhook_endpoint_reports_failure_for_error_response()
    {
        $_POST['provider'] = 'brevo';
        $GLOBALS['mnem_http_response'] = array(
            'response' => array('code' => 404),
            'body' => '',
        );

        $admin = new NetworkAdmin();
        $admin->ajax_test_webhook_endpoint();

        $this->assertFalse($GLOBALS['mnem_last_json_response']['success']);
    }
}
