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
}
