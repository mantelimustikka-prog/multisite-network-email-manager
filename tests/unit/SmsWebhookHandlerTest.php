<?php

defined('ABSPATH') || exit;

use MNEM\RestApi;
use MNEM\SmsProviderStatusMap;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SMS delivery-status webhook handling via RestApi::handle_sms_webhook().
 */
class SmsWebhookHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['mnem_site_options'] = array();
        $GLOBALS['wpdb'] = new wpdb();
    }

    // ------------------------------------------------------------------
    // Helper: build a fake WP_REST_Request-like object
    // ------------------------------------------------------------------

    private function make_request(string $provider, array $payload, string $sig = ''): object
    {
        return new class($provider, $payload, $sig) {
            private string $provider;
            private array  $payload;
            private string $sig;

            public function __construct(string $p, array $d, string $s)
            {
                $this->provider = $p;
                $this->payload  = $d;
                $this->sig      = $s;
            }

            public function get_route(): string
            {
                return '/mnem/v1/sms-webhooks/' . $this->provider;
            }

            public function get_body(): string
            {
                return wp_json_encode($this->payload);
            }

            public function get_params(): array
            {
                return $this->payload;
            }

            public function get_header(string $name): string
            {
                return $this->sig;
            }
        };
    }

    // ------------------------------------------------------------------
    // Route parsing
    // ------------------------------------------------------------------

    public function test_unknown_provider_returns_failure(): void
    {
        $request = $this->make_request('nonexistent', array('status' => 'delivered'));
        $api     = new RestApi();
        $result  = $api->handle_sms_webhook($request);

        $this->assertFalse($result['success']);
    }

    // ------------------------------------------------------------------
    // Status mapping end-to-end
    // ------------------------------------------------------------------

    public function test_twilio_webhook_updates_queue_status_to_delivered(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(
                    'id'                => 55,
                    'status'            => 'sent',
                    'provider_metadata' => '{}',
                );
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $request = $this->make_request('twilio', array(
            'MessageStatus' => 'delivered',
            'MessageSid'    => 'SM-abc123',
            'To'            => '+15550001111',
        ), 'some-sig');

        $api    = new RestApi();
        $result = $api->handle_sms_webhook($request);

        $this->assertTrue($result['success']);
        $this->assertSame('twilio', $result['provider']);
        $this->assertSame('delivered', $result['status']);

        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('SM-abc123', $queries);
        $this->assertStringContainsString("status = 'delivered'", $queries);
    }

    public function test_twilio_undelivered_maps_to_bounce(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array('id' => 56, 'status' => 'sent', 'provider_metadata' => '{}');
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $request = $this->make_request('twilio', array(
            'MessageStatus' => 'undelivered',
            'MessageSid'    => 'SM-bounce',
            'To'            => '+15550001111',
        ), 'sig');

        $api    = new RestApi();
        $result = $api->handle_sms_webhook($request);

        $this->assertSame('bounce', $result['status']);
    }

    public function test_clicksend_submitted_maps_to_sent(): void
    {
        $request = $this->make_request('clicksend', array(
            'status'     => 'submitted',
            'message_id' => '',
            'to'         => '+447700900000',
        ));

        $api    = new RestApi();
        $result = $api->handle_sms_webhook($request);

        // No message_id → queue update skipped, but status is still mapped.
        $this->assertSame('sent', $result['status']);
    }

    public function test_vonage_expired_maps_to_bounce(): void
    {
        $request = $this->make_request('vonage', array(
            'status'    => 'expired',
            'messageId' => '',
            'to'        => '+15559999',
        ));

        $api    = new RestApi();
        $result = $api->handle_sms_webhook($request);

        $this->assertSame('bounce', $result['status']);
    }

    // ------------------------------------------------------------------
    // Idempotency: duplicate webhook should not regress status
    // ------------------------------------------------------------------

    public function test_duplicate_webhook_does_not_regress_delivered_to_sent(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_row($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                // Row already at 'delivered'
                return array('id' => 99, 'status' => 'delivered', 'provider_metadata' => '{}');
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $request = $this->make_request('twilio', array(
            'MessageStatus' => 'sent',
            'MessageSid'    => 'SM-dup',
            'To'            => '+15550001111',
        ), 'sig');

        $api = new RestApi();
        $api->handle_sms_webhook($request);

        // None of the UPDATE queries should set status = 'sent' (going backward).
        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringNotContainsString("status = 'sent'", $queries);
    }

    // ------------------------------------------------------------------
    // SmsProviderStatusMap unit tests
    // ------------------------------------------------------------------

    public function test_map_returns_empty_for_unknown_provider(): void
    {
        $this->assertSame('', SmsProviderStatusMap::map('fakeprovider', 'delivered'));
    }

    public function test_map_covers_all_tracked_providers(): void
    {
        foreach (SmsProviderStatusMap::get_tracking_providers() as $provider) {
            $map = SmsProviderStatusMap::get_status_map($provider);
            $this->assertNotEmpty($map, "Provider {$provider} should have a non-empty status map");
        }
    }
}
