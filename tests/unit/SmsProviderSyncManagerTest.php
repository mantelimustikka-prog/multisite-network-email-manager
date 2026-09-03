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

    public function test_sync_trusts_provider_status_even_when_it_regresses_from_delivered_to_sent(): void
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

        $this->assertSame('sent', $result['changes'][0]['new_status']);
        $this->assertStringContainsString("SET status = 'sent'", implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_sync_trusts_provider_status_even_when_it_regresses_from_rejected_to_sent(): void
    {
        $GLOBALS['mnem_http_response']['body'] = '{"status":"s"}';
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                return array(array(
                    'id' => 48,
                    'status' => 'rejected',
                    'provider_status' => 'r',
                    'provider_message_id' => 'tm-48',
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

        $this->assertSame('sent', $result['changes'][0]['new_status']);
        $this->assertStringContainsString("SET status = 'sent'", implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_sync_trusts_provider_status_even_when_it_regresses_from_failed_to_rejected(): void
    {
        $GLOBALS['mnem_http_response']['body'] = '{"status":"r"}';
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                return array(array(
                    'id' => 49,
                    'status' => 'failed',
                    'provider_status' => 'f',
                    'provider_message_id' => 'tm-49',
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

        $this->assertSame('rejected', $result['changes'][0]['new_status']);
        $this->assertStringContainsString("SET status = 'rejected'", implode("\n", $GLOBALS['wpdb']->queries));
    }

    public function test_sync_errors_are_included_in_result(): void
    {
        $GLOBALS['mnem_http_response'] = array(
            'response' => array('code' => 401),
            'body' => '{"message":"Invalid credentials"}',
        );
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                return array(array(
                    'id' => 46,
                    'status' => 'sent',
                    'provider_status' => 's',
                    'provider_message_id' => 'tm-46',
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
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('SMS #46', (string) $result['errors'][0]);
    }

    public function test_sync_error_is_reported_when_provider_does_not_support_lookup(): void
    {
        $GLOBALS['wpdb'] = new class extends wpdb {
        };

        $result = (new SmsProviderSyncManager())->sync_statuses_from_provider('messagedesk', 100);

        $this->assertSame(0, $result['checked']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_unmapped_status_with_success_reports_warning_not_error(): void
    {
        // Provider returns success=true but status cannot be mapped (e.g., unknown/future status code)
        $GLOBALS['mnem_http_response'] = array(
            'response' => array('code' => 200),
            'body' => '{"status":"z"}',  // 'z' is not in the TextMagic status map
        );
        $GLOBALS['wpdb'] = new class extends wpdb {
            public function get_results($query, $output = OBJECT)
            {
                $this->queries[] = $query;
                return array(array(
                    'id' => 47,
                    'status' => 'sent',
                    'provider_status' => '',
                    'provider_message_id' => 'tm-47',
                    'sync_attempts' => 0,
                ));
            }

            public function query($query)
            {
                $this->queries[] = $query;
                return 0;  // No update should occur
            }
        };

        $result = (new SmsProviderSyncManager())->sync_statuses_from_provider('textmagic', 100);

        // Should be treated as a warning, not an error
        $this->assertEmpty($result['errors'], 'Unmapped but successful status should not produce an error');
        $this->assertNotEmpty($result['warnings'], 'Unmapped status should produce a warning');
        $this->assertStringContainsString('SMS #47', $result['warnings'][0]);
        $this->assertStringContainsString('unmapped status', $result['warnings'][0]);
        // No UPDATE query should have been executed (continue skipped it)
        $update_queries = array_filter($GLOBALS['wpdb']->queries, function ($q) {
            return stripos($q, 'UPDATE') !== false;
        });
        $this->assertEmpty($update_queries, 'Unmapped status should not trigger a queue update');
    }
}
