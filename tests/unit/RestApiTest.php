<?php

defined('ABSPATH') || exit;

use MNEM\RestApi;
use MNEM\SmtpSettings;
use PHPUnit\Framework\TestCase;

class RestApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options'] = array();
        $GLOBALS['wpdb'] = new wpdb();
    }

    public function test_get_status_uses_active_provider_configuration_check()
    {
        SmtpSettings::save(array(
            'provider_type' => 'sendgrid',
            'host' => 'smtp.example.com',
            'provider_config' => array(),
        ));

        $api = new RestApi();
        $status = $api->get_status();

        $this->assertArrayHasKey('smtp_configured', $status);
        $this->assertFalse($status['smtp_configured']);
    }

    public function test_handle_webhook_maps_event_directly_to_queue_status()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 91,
                    'status' => 'sent',
                    'opened' => '',
                    'clicked' => '',
                    'opens_count' => 0,
                    'clicks_count' => 0,
                    'provider_metadata' => '{}',
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $request = new class {
            public function get_route()
            {
                return '/mnem/v1/webhooks/sendgrid';
            }

            public function get_body()
            {
                return wp_json_encode(array(array(
                    'event' => 'click',
                    'email' => 'user@example.com',
                    'message-id' => 'provider-123',
                    'timestamp' => 1786881600,
                )));
            }
        };

        $api = new RestApi();
        $result = $api->handle_webhook($request);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['event_count']);
        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("provider_message_id = 'provider-123'", $queries);
        $this->assertStringContainsString("status = 'clicked'", $queries);
    }

    public function test_handle_webhook_logs_receipt_to_webhook_log_table()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 55,
                    'status' => 'sent',
                    'opened' => '',
                    'clicked' => '',
                    'opens_count' => 0,
                    'clicks_count' => 0,
                    'provider_metadata' => '{}',
                );
            }
        };

        $request = new class {
            public function get_route()
            {
                return '/mnem/v1/webhooks/brevo';
            }

            public function get_body()
            {
                return wp_json_encode(array(
                    'event' => 'delivered',
                    'email' => 'jane@example.com',
                    'message-id' => 'brevo-1',
                    'ts_event' => 1786881600,
                ));
            }
        };

        $api = new RestApi();
        $result = $api->handle_webhook($request);

        $this->assertTrue($result['success']);
        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('INSERT INTO wp_mnem_webhook_log', $queries);
        $this->assertStringContainsString('UPDATE wp_mnem_webhook_log', $queries);
    }

    public function test_handle_webhook_test_ping_is_logged_without_queue_update()
    {
        $GLOBALS['wpdb'] = new wpdb();

        $request = new class {
            public function get_route()
            {
                return '/mnem/v1/webhooks/brevo';
            }

            public function get_body()
            {
                return wp_json_encode(array('mnem_webhook_test' => true));
            }
        };

        $api = new RestApi();
        $result = $api->handle_webhook($request);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['test']);
        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('INSERT INTO wp_mnem_webhook_log', $queries);
        $this->assertStringContainsString('success = 1', $queries);
        $this->assertStringNotContainsString('wp_mnem_queue', $queries);
    }

    public function test_handle_brevo_webhook_parses_timestamp_and_ts_fields()
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id' => 99,
                    'status' => 'sent',
                    'opened' => '',
                    'clicked' => '',
                    'opens_count' => 0,
                    'clicks_count' => 0,
                    'provider_metadata' => '{}',
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        // Test with Brevo 'timestamp' field
        $request1 = new class {
            public function get_route()
            {
                return '/mnem/v1/webhooks/brevo';
            }

            public function get_body()
            {
                return wp_json_encode(array(
                    'event' => 'delivered',
                    'email' => 'brevo@example.com',
                    'message-id' => 'brevo-msg-001',
                    'timestamp' => 1786881600,
                ));
            }
        };

        $api = new RestApi();
        $result1 = $api->handle_webhook($request1);

        $this->assertTrue($result1['success']);
        $queries1 = implode("\n", $GLOBALS['wpdb']->queries);
        $expected_ts1 = gmdate('Y-m-d H:i:s', 1786881600);
        $this->assertStringContainsString("provider_message_id = 'brevo-msg-001'", $queries1);
        $this->assertStringContainsString("status = 'delivered'", $queries1);

        // Test with Brevo 'ts' fallback field
        $GLOBALS['wpdb']->queries = array();
        $request2 = new class {
            public function get_route()
            {
                return '/mnem/v1/webhooks/brevo';
            }

            public function get_body()
            {
                return wp_json_encode(array(
                    'event' => 'delivered',
                    'email' => 'brevo@example.com',
                    'message-id' => 'brevo-msg-002',
                    'ts' => 1786881600,
                ));
            }
        };

        $result2 = $api->handle_webhook($request2);
        $this->assertTrue($result2['success']);
        $queries2 = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("provider_message_id = 'brevo-msg-002'", $queries2);
    }
}
