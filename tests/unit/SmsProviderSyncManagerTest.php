<?php

defined('ABSPATH') || exit;

use MNEM\SmsProviderSyncManager;
use PHPUnit\Framework\TestCase;

class SmsProviderSyncManagerTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mnem_site_options']['mnem_sms_config'] = wp_json_encode(array(
            'textmagic' => array(
                'username' => base64_encode('demo'),
                'api_key' => base64_encode('secret'),
            ),
        ));
        $GLOBALS['mnem_http_response'] = array(
            'response' => array('code' => 200),
            'body' => '{"status":"r"}',
        );
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mnem_http_response']);
    }

    public function test_manual_sync_updates_raw_and_canonical_status(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(array(
                    'id' => 42,
                    'status' => 'sent',
                    'provider_status' => '',
                    'provider_message_id' => 'tm-42',
                    'sync_attempts' => 0,
                ));
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = (new SmsProviderSyncManager())->sync_statuses_from_provider('textmagic', 100);

        $this->assertSame(1, $result['checked']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, $result['rejected']);
        $queries = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString("status = 'rejected'", $queries);
        $this->assertStringContainsString("provider_status = 'r'", $queries);
        $this->assertStringContainsString('sync_attempts = 0', $queries);
    }

    public function test_dry_run_reports_without_updating_queue(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(array(
                    'id' => 43,
                    'status' => 'sent',
                    'provider_status' => '',
                    'provider_message_id' => 'tm-43',
                    'sync_attempts' => 0,
                ));
            }
        };

        $result = (new SmsProviderSyncManager())->sync_statuses_from_provider(
            'textmagic',
            100,
            array('dry_run' => true)
        );

        $this->assertSame(1, $result['updated']);
        $this->assertStringNotContainsString('UPDATE ', implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_sync_does_not_overwrite_a_concurrent_webhook_update(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                return array(array(
                    'id' => 44,
                    'status' => 'sent',
                    'provider_status' => '',
                    'provider_message_id' => 'tm-44',
                    'sync_attempts' => 0,
                ));
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 0;
            }
        };

        $result = (new SmsProviderSyncManager())->sync_statuses_from_provider('textmagic', 100);

        $this->assertSame(0, $result['updated']);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString("WHERE id = 44 AND status = 'sent'", implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_sync_does_not_regress_delivered_status_to_sent(): void
    {
        $GLOBALS['mnem_http_response']['body'] = '{"status":"s"}';
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                return array(array(
                    'id' => 45,
                    'status' => 'delivered',
                    'provider_status' => 'd',
                    'provider_message_id' => 'tm-45',
                    'sync_attempts' => 0,
                ));
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 1;
            }
        };

        $result = (new SmsProviderSyncManager())->sync_statuses_from_provider('textmagic', 100);

        $this->assertSame('delivered', $result['changes'][0]['new_status']);
        $this->assertStringContainsString("SET status = 'delivered'", implode("\n", $GLOBALS['wpdb']->queries));
    }
}
