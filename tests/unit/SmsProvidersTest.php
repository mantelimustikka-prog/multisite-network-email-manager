<?php

defined('ABSPATH') || exit;

use MNEM\Providers\SmsTwilio;
use MNEM\Providers\SmsClicksend;
use MNEM\Providers\SmsTextmagic;
use MNEM\Providers\SmsSimpletexting;
use MNEM\Providers\SmsMessagedesk;
use MNEM\Providers\SmsEztexting;
use MNEM\Providers\SmsSalesmsg;
use MNEM\Providers\SmsTextline;
use MNEM\Providers\SmsSlicktext;
use MNEM\Providers\SmsTextedly;
use MNEM\Providers\SmsTextus;
use MNEM\Providers\SmsVonage;
use MNEM\SmsProviderStatusMap;
use PHPUnit\Framework\TestCase;

/**
 * Tests for all 12 SMS provider implementations.
 */
class SmsProvidersTest extends TestCase
{
    // ------------------------------------------------------------------
    // Helper: provider returns error when credentials are missing
    // ------------------------------------------------------------------

    private function assert_missing_credentials_error(object $provider): void
    {
        $result = $provider->test_connection();
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
    }

    private function assert_missing_credentials_send_error(object $provider): void
    {
        $result = $provider->send('+15550001111', 'Hello');
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['message']);
    }

    // ------------------------------------------------------------------
    // Twilio
    // ------------------------------------------------------------------

    public function test_twilio_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsTwilio());
    }

    public function test_twilio_send_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_send_error(new SmsTwilio());
    }

    public function test_twilio_provider_key(): void
    {
        $this->assertSame('twilio', SmsTwilio::get_provider_key());
    }

    public function test_twilio_parse_delivery_status(): void
    {
        $data = array(
            'MessageStatus' => 'delivered',
            'MessageSid'    => 'SM123',
            'To'            => '+15550001111',
        );
        $parsed = SmsTwilio::parse_delivery_status($data);
        $this->assertSame('delivered', $parsed['status']);
        $this->assertSame('SM123', $parsed['message_id']);
        $this->assertSame('+15550001111', $parsed['phone']);
    }

    public function test_twilio_parse_delivery_status_handles_missing_keys(): void
    {
        $parsed = SmsTwilio::parse_delivery_status(array());
        $this->assertSame('', $parsed['status']);
        $this->assertSame('', $parsed['message_id']);
        $this->assertSame('', $parsed['phone']);
    }

    public function test_twilio_verify_webhook_signature_rejects_empty(): void
    {
        $this->assertFalse(SmsTwilio::verify_webhook_signature('payload', ''));
    }

    public function test_twilio_verify_webhook_signature_accepts_non_empty(): void
    {
        $this->assertTrue(SmsTwilio::verify_webhook_signature('payload', 'some-sig'));
    }

    // ------------------------------------------------------------------
    // ClickSend
    // ------------------------------------------------------------------

    public function test_clicksend_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsClicksend());
    }

    public function test_clicksend_send_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_send_error(new SmsClicksend());
    }

    public function test_clicksend_provider_key(): void
    {
        $this->assertSame('clicksend', SmsClicksend::get_provider_key());
    }

    public function test_clicksend_parse_delivery_status(): void
    {
        $data = array(
            'status'     => 'delivered',
            'message_id' => 'cs-456',
            'to'         => '+447700900000',
        );
        $parsed = SmsClicksend::parse_delivery_status($data);
        $this->assertSame('delivered', $parsed['status']);
        $this->assertSame('cs-456', $parsed['message_id']);
        $this->assertSame('+447700900000', $parsed['phone']);
    }

    // ------------------------------------------------------------------
    // TextMagic
    // ------------------------------------------------------------------

    public function test_textmagic_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsTextmagic());
    }

    public function test_textmagic_provider_key(): void
    {
        $this->assertSame('textmagic', SmsTextmagic::get_provider_key());
    }

    public function test_textmagic_parse_delivery_status(): void
    {
        $parsed = SmsTextmagic::parse_delivery_status(array('status' => 'd', 'id' => '789', 'receiver' => '+1555'));
        $this->assertSame('d', $parsed['status']);
        $this->assertSame('789', $parsed['message_id']);
        $this->assertSame('+1555', $parsed['phone']);
    }

    // ------------------------------------------------------------------
    // SimpleTexting
    // ------------------------------------------------------------------

    public function test_simpletexting_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsSimpletexting());
    }

    public function test_simpletexting_provider_key(): void
    {
        $this->assertSame('simpletexting', SmsSimpletexting::get_provider_key());
    }

    public function test_simpletexting_parse_delivery_status(): void
    {
        $parsed = SmsSimpletexting::parse_delivery_status(array('status' => 'DELIVERED', 'id' => 'st-1', 'contactPhone' => '+1555'));
        $this->assertSame('DELIVERED', $parsed['status']);
        $this->assertSame('st-1', $parsed['message_id']);
    }

    // ------------------------------------------------------------------
    // MessageDesk
    // ------------------------------------------------------------------

    public function test_messagedesk_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsMessagedesk());
    }

    public function test_messagedesk_provider_key(): void
    {
        $this->assertSame('messagedesk', SmsMessagedesk::get_provider_key());
    }

    // ------------------------------------------------------------------
    // EZTexting
    // ------------------------------------------------------------------

    public function test_eztexting_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsEztexting());
    }

    public function test_eztexting_provider_key(): void
    {
        $this->assertSame('eztexting', SmsEztexting::get_provider_key());
    }

    // ------------------------------------------------------------------
    // Salesmsg
    // ------------------------------------------------------------------

    public function test_salesmsg_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsSalesmsg());
    }

    public function test_salesmsg_provider_key(): void
    {
        $this->assertSame('salesmsg', SmsSalesmsg::get_provider_key());
    }

    // ------------------------------------------------------------------
    // Textline
    // ------------------------------------------------------------------

    public function test_textline_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsTextline());
    }

    public function test_textline_provider_key(): void
    {
        $this->assertSame('textline', SmsTextline::get_provider_key());
    }

    public function test_textline_parse_delivery_status_maps_event_type(): void
    {
        $parsed = SmsTextline::parse_delivery_status(array('type' => 'message.delivered', 'uuid' => 'tl-9', 'phone_number' => '+1555'));
        $this->assertSame('delivered', $parsed['status']);
        $this->assertSame('tl-9', $parsed['message_id']);
    }

    // ------------------------------------------------------------------
    // SlickText
    // ------------------------------------------------------------------

    public function test_slicktext_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsSlicktext());
    }

    public function test_slicktext_provider_key(): void
    {
        $this->assertSame('slicktext', SmsSlicktext::get_provider_key());
    }

    public function test_slicktext_parse_delivery_status(): void
    {
        $parsed = SmsSlicktext::parse_delivery_status(array('status' => 'DELIVERED', 'messageId' => 'sk-5', 'recipient' => '+1555'));
        $this->assertSame('delivered', $parsed['status']); // strtolower applied
        $this->assertSame('sk-5', $parsed['message_id']);
    }

    // ------------------------------------------------------------------
    // Textedly
    // ------------------------------------------------------------------

    public function test_textedly_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsTextedly());
    }

    public function test_textedly_provider_key(): void
    {
        $this->assertSame('textedly', SmsTextedly::get_provider_key());
    }

    // ------------------------------------------------------------------
    // TextUs
    // ------------------------------------------------------------------

    public function test_textus_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsTextus());
    }

    public function test_textus_provider_key(): void
    {
        $this->assertSame('textus', SmsTextus::get_provider_key());
    }

    // ------------------------------------------------------------------
    // Vonage
    // ------------------------------------------------------------------

    public function test_vonage_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_error(new SmsVonage());
    }

    public function test_vonage_send_returns_error_when_credentials_missing(): void
    {
        $this->assert_missing_credentials_send_error(new SmsVonage());
    }

    public function test_vonage_provider_key(): void
    {
        $this->assertSame('vonage', SmsVonage::get_provider_key());
    }

    public function test_vonage_parse_delivery_status(): void
    {
        $parsed = SmsVonage::parse_delivery_status(array('status' => 'delivered', 'messageId' => 'vg-1', 'to' => '+1555'));
        $this->assertSame('delivered', $parsed['status']);
        $this->assertSame('vg-1', $parsed['message_id']);
        $this->assertSame('+1555', $parsed['phone']);
    }

    public function test_vonage_verify_webhook_signature_rejects_when_no_key(): void
    {
        // When no key is configured, signature verification should fail.
        $this->assertFalse(SmsVonage::verify_webhook_signature('payload', ''));
    }

    // ------------------------------------------------------------------
    // SmsProviderStatusMap
    // ------------------------------------------------------------------

    public function test_status_map_twilio_delivered(): void
    {
        $this->assertSame('delivered', SmsProviderStatusMap::map('twilio', 'delivered'));
    }

    public function test_status_map_twilio_undelivered_maps_to_bounce(): void
    {
        $this->assertSame('bounce', SmsProviderStatusMap::map('twilio', 'undelivered'));
    }

    public function test_status_map_twilio_failed_maps_to_failed(): void
    {
        $this->assertSame('failed', SmsProviderStatusMap::map('twilio', 'failed'));
    }

    public function test_status_map_twilio_sent(): void
    {
        $this->assertSame('sent', SmsProviderStatusMap::map('twilio', 'sent'));
    }

    public function test_status_map_clicksend_submitted_maps_to_sent(): void
    {
        $this->assertSame('sent', SmsProviderStatusMap::map('clicksend', 'submitted'));
    }

    public function test_status_map_vonage_submitted_maps_to_sent(): void
    {
        $this->assertSame('sent', SmsProviderStatusMap::map('vonage', 'submitted'));
    }

    public function test_status_map_vonage_expired_maps_to_bounce(): void
    {
        $this->assertSame('bounce', SmsProviderStatusMap::map('vonage', 'expired'));
    }

    public function test_status_map_unknown_provider_returns_empty(): void
    {
        $this->assertSame('', SmsProviderStatusMap::map('nonexistent', 'delivered'));
    }

    public function test_status_map_unknown_status_returns_empty(): void
    {
        $this->assertSame('', SmsProviderStatusMap::map('twilio', 'unknown_status'));
    }

    public function test_supports_tracking_returns_true_for_known_providers(): void
    {
        foreach (array('twilio', 'clicksend', 'textmagic', 'vonage') as $p) {
            $this->assertTrue(SmsProviderStatusMap::supports_tracking($p), "Provider {$p} should support tracking");
        }
    }

    public function test_supports_tracking_returns_false_for_unknown(): void
    {
        $this->assertFalse(SmsProviderStatusMap::supports_tracking('nonexistent'));
    }

    public function test_all_12_providers_are_in_tracking_map(): void
    {
        $expected = array('twilio', 'clicksend', 'textmagic', 'simpletexting', 'messagedesk', 'eztexting', 'salesmsg', 'textline', 'slicktext', 'textedly', 'textus', 'vonage');
        $tracking = SmsProviderStatusMap::get_tracking_providers();
        foreach ($expected as $provider) {
            $this->assertContains($provider, $tracking, "Provider {$provider} should be in tracking map");
        }
    }
}
