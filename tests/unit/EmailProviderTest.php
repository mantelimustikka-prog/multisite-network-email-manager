<?php

defined('ABSPATH') || exit;

use MNEM\BrevoProvider;
use MNEM\EmailProvider;
use MNEM\MailgunProvider;
use MNEM\PostmarkProvider;
use MNEM\ProviderManager;
use MNEM\SendgridProvider;
use MNEM\SmtpProvider;
use MNEM\SmtpSettings;
use MNEM\Smtp2goProvider;
use PHPUnit\Framework\TestCase;

/**
 * Helper to build a mock WP HTTP response.
 *
 * @param int    $code
 * @param string $body
 * @param array<string,string>  $headers
 * @return array<string,mixed>
 */
function mnem_mock_http_response(int $code, string $body = '', array $headers = array()): array
{
    return array(
        'response' => array('code' => $code, 'message' => ''),
        'body'     => $body,
        'headers'  => $headers,
    );
}

class EmailProviderTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mnem_site_options'] = array();
        unset($GLOBALS['mnem_http_response']);
        ProviderManager::flush_instances();
    }

    // -------------------------------------------------------------------------
    // EmailProvider abstract contract
    // -------------------------------------------------------------------------

    public function test_smtp_provider_name()
    {
        $p = new SmtpProvider(array('host' => 'smtp.example.com'));
        $this->assertSame('smtp', $p->get_provider_name());
    }

    public function test_mailgun_provider_name()
    {
        $p = new MailgunProvider(array());
        $this->assertSame('mailgun', $p->get_provider_name());
    }

    public function test_sendgrid_provider_name()
    {
        $p = new SendgridProvider(array());
        $this->assertSame('sendgrid', $p->get_provider_name());
    }

    public function test_brevo_provider_name()
    {
        $p = new BrevoProvider(array());
        $this->assertSame('brevo', $p->get_provider_name());
    }

    public function test_postmark_provider_name()
    {
        $p = new PostmarkProvider(array());
        $this->assertSame('postmark', $p->get_provider_name());
    }

    public function test_smtp2go_provider_name()
    {
        $p = new Smtp2goProvider(array());
        $this->assertSame('smtp2go', $p->get_provider_name());
    }

    // -------------------------------------------------------------------------
    // validate_config()
    // -------------------------------------------------------------------------

    public function test_smtp_validate_config_requires_host()
    {
        $this->assertFalse((new SmtpProvider(array()))->validate_config());
        $this->assertTrue((new SmtpProvider(array('host' => 'smtp.example.com')))->validate_config());
    }

    public function test_mailgun_validate_config_requires_api_key_and_domain()
    {
        $this->assertFalse((new MailgunProvider(array()))->validate_config());
        $this->assertFalse((new MailgunProvider(array('api_key' => 'key')))->validate_config());
        $this->assertFalse((new MailgunProvider(array('domain' => 'example.com')))->validate_config());
        $this->assertTrue((new MailgunProvider(array('api_key' => 'key', 'domain' => 'example.com')))->validate_config());
    }

    public function test_sendgrid_validate_config_requires_api_key()
    {
        $this->assertFalse((new SendgridProvider(array()))->validate_config());
        $this->assertTrue((new SendgridProvider(array('api_key' => 'SG.test')))->validate_config());
    }

    public function test_brevo_validate_config_requires_api_key()
    {
        $this->assertFalse((new BrevoProvider(array()))->validate_config());
        $this->assertTrue((new BrevoProvider(array('api_key' => 'xkeysib-abc')))->validate_config());
    }

    public function test_postmark_validate_config_requires_server_token()
    {
        $this->assertFalse((new PostmarkProvider(array()))->validate_config());
        $this->assertTrue((new PostmarkProvider(array('server_token' => 'tok')))->validate_config());
    }

    public function test_smtp2go_validate_config_requires_api_key()
    {
        $this->assertFalse((new Smtp2goProvider(array()))->validate_config());
        $this->assertTrue((new Smtp2goProvider(array('api_key' => 'api-key')))->validate_config());
    }

    // -------------------------------------------------------------------------
    // get_config_fields()
    // -------------------------------------------------------------------------

    public function test_smtp_has_required_config_fields()
    {
        $fields = (new SmtpProvider(array()))->get_config_fields();
        $this->assertArrayHasKey('host', $fields);
        $this->assertArrayHasKey('port', $fields);
        $this->assertArrayHasKey('encryption', $fields);
        $this->assertArrayHasKey('username', $fields);
        $this->assertArrayHasKey('password', $fields);
    }

    public function test_mailgun_has_required_config_fields()
    {
        $fields = (new MailgunProvider(array()))->get_config_fields();
        $this->assertArrayHasKey('api_key', $fields);
        $this->assertArrayHasKey('domain', $fields);
    }

    public function test_sendgrid_has_required_config_fields()
    {
        $fields = (new SendgridProvider(array()))->get_config_fields();
        $this->assertArrayHasKey('api_key', $fields);
    }

    public function test_brevo_has_required_config_fields()
    {
        $fields = (new BrevoProvider(array()))->get_config_fields();
        $this->assertArrayHasKey('api_key', $fields);
    }

    public function test_postmark_has_required_config_fields()
    {
        $fields = (new PostmarkProvider(array()))->get_config_fields();
        $this->assertArrayHasKey('server_token', $fields);
    }

    public function test_smtp2go_has_required_config_fields()
    {
        $fields = (new Smtp2goProvider(array()))->get_config_fields();
        $this->assertArrayHasKey('api_key', $fields);
    }

    // -------------------------------------------------------------------------
    // send() — error cases (missing config)
    // -------------------------------------------------------------------------

    public function test_smtp_send_fails_without_host()
    {
        $result = (new SmtpProvider(array()))->send('to@example.com', 'Subject', 'Body');
        $this->assertFalse($result['success']);
        $this->assertSame('smtp', $result['provider']);
    }

    public function test_mailgun_send_fails_without_config()
    {
        $result = (new MailgunProvider(array()))->send('to@example.com', 'Subject', 'Body');
        $this->assertFalse($result['success']);
        $this->assertSame('mailgun', $result['provider']);
    }

    public function test_sendgrid_send_fails_without_config()
    {
        $result = (new SendgridProvider(array()))->send('to@example.com', 'Subject', 'Body');
        $this->assertFalse($result['success']);
    }

    public function test_brevo_send_fails_without_config()
    {
        $result = (new BrevoProvider(array()))->send('to@example.com', 'Subject', 'Body');
        $this->assertFalse($result['success']);
    }

    public function test_postmark_send_fails_without_config()
    {
        $result = (new PostmarkProvider(array()))->send('to@example.com', 'Subject', 'Body');
        $this->assertFalse($result['success']);
    }

    public function test_smtp2go_send_fails_without_config()
    {
        $result = (new Smtp2goProvider(array()))->send('to@example.com', 'Subject', 'Body');
        $this->assertFalse($result['success']);
    }

    // -------------------------------------------------------------------------
    // send() — success via mocked HTTP
    // -------------------------------------------------------------------------

    public function test_smtp_send_success()
    {
        // wp_mail returns true (from bootstrap stub).
        $result = (new SmtpProvider(array('host' => 'smtp.example.com')))->send('to@example.com', 'Subj', 'Body');
        $this->assertTrue($result['success']);
        $this->assertSame('smtp', $result['provider']);
    }

    public function test_mailgun_send_success()
    {
        $GLOBALS['mnem_http_response'] = mnem_mock_http_response(
            200,
            json_encode(array('id' => '<test@mailgun>', 'message' => 'Queued. Thank you.'))
        );
        $p = new MailgunProvider(array(
            'api_key'    => 'key-test',
            'domain'     => 'example.com',
            'from_email' => 'from@example.com',
        ));
        $result = $p->send('to@example.com', 'Subj', 'Body');
        $this->assertTrue($result['success']);
        $this->assertSame('<test@mailgun>', $result['message_id']);
    }

    public function test_sendgrid_send_success()
    {
        $GLOBALS['mnem_http_response'] = mnem_mock_http_response(
            202,
            '',
            array('x-message-id' => 'sg-message-id-123')
        );
        $p = new SendgridProvider(array(
            'api_key'    => 'SG.test',
            'from_email' => 'from@example.com',
        ));
        $result = $p->send('to@example.com', 'Subj', 'Body');
        $this->assertTrue($result['success']);
        $this->assertSame('sg-message-id-123', $result['message_id']);
    }

    public function test_brevo_send_success()
    {
        $GLOBALS['mnem_http_response'] = mnem_mock_http_response(
            201,
            json_encode(array('messageId' => 'brevo-msg-id-456'))
        );
        $p = new BrevoProvider(array(
            'api_key'    => 'xkeysib-abc',
            'from_email' => 'from@example.com',
        ));
        $result = $p->send('to@example.com', 'Subj', 'Body');
        $this->assertTrue($result['success']);
        $this->assertSame('brevo-msg-id-456', $result['message_id']);
    }

    public function test_postmark_send_success()
    {
        $GLOBALS['mnem_http_response'] = mnem_mock_http_response(
            200,
            json_encode(array('MessageID' => 'pm-uuid-789', 'ErrorCode' => 0))
        );
        $p = new PostmarkProvider(array(
            'server_token' => 'tok-test',
            'from_email'   => 'from@example.com',
        ));
        $result = $p->send('to@example.com', 'Subj', 'Body');
        $this->assertTrue($result['success']);
        $this->assertSame('pm-uuid-789', $result['message_id']);
    }

    public function test_smtp2go_send_success()
    {
        $GLOBALS['mnem_http_response'] = mnem_mock_http_response(
            200,
            json_encode(array('data' => array('succeeded' => 1, 'request_id' => 'smtp2go-req-001')))
        );
        $p = new Smtp2goProvider(array(
            'api_key'    => 'api-key-test',
            'from_email' => 'from@example.com',
        ));
        $result = $p->send('to@example.com', 'Subj', 'Body');
        $this->assertTrue($result['success']);
        $this->assertSame('smtp2go-req-001', $result['message_id']);
    }

    // -------------------------------------------------------------------------
    // send() — API error responses
    // -------------------------------------------------------------------------

    public function test_mailgun_send_api_error()
    {
        $GLOBALS['mnem_http_response'] = mnem_mock_http_response(
            401,
            json_encode(array('message' => 'Forbidden'))
        );
        $p = new MailgunProvider(array('api_key' => 'bad', 'domain' => 'example.com', 'from_email' => 'a@b.com'));
        $result = $p->send('to@example.com', 'Subj', 'Body');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('401', $result['message']);
    }

    public function test_sendgrid_send_api_error()
    {
        $GLOBALS['mnem_http_response'] = mnem_mock_http_response(400, json_encode(array('errors' => array())));
        $p = new SendgridProvider(array('api_key' => 'SG.bad', 'from_email' => 'a@b.com'));
        $result = $p->send('to@example.com', 'Subj', 'Body');
        $this->assertFalse($result['success']);
    }

    public function test_mailgun_send_wp_error()
    {
        $GLOBALS['mnem_http_response'] = new \WP_Error('http_failed', 'cURL error 6: Could not resolve host');
        $p = new MailgunProvider(array('api_key' => 'key', 'domain' => 'example.com', 'from_email' => 'a@b.com'));
        $result = $p->send('to@example.com', 'Subj', 'Body');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('HTTP error', $result['message']);
    }

    // -------------------------------------------------------------------------
    // test_connection()
    // -------------------------------------------------------------------------

    public function test_smtp_test_connection_without_host()
    {
        $result = (new SmtpProvider(array()))->test_connection();
        $this->assertFalse($result['success']);
    }

    public function test_smtp_test_connection_with_host()
    {
        $result = (new SmtpProvider(array('host' => 'smtp.example.com')))->test_connection();
        $this->assertTrue($result['success']);
    }

    public function test_mailgun_test_connection_success()
    {
        $GLOBALS['mnem_http_response'] = mnem_mock_http_response(200, '{}');
        $p = new MailgunProvider(array('api_key' => 'key', 'domain' => 'example.com'));
        $result = $p->test_connection();
        $this->assertTrue($result['success']);
    }

    public function test_mailgun_test_connection_failure()
    {
        $GLOBALS['mnem_http_response'] = mnem_mock_http_response(403, '{}');
        $p = new MailgunProvider(array('api_key' => 'bad', 'domain' => 'example.com'));
        $result = $p->test_connection();
        $this->assertFalse($result['success']);
    }

    public function test_mailgun_test_connection_no_config()
    {
        $result = (new MailgunProvider(array()))->test_connection();
        $this->assertFalse($result['success']);
    }

    // -------------------------------------------------------------------------
    // ProviderManager
    // -------------------------------------------------------------------------

    public function test_provider_manager_get_available_providers_lists_all()
    {
        $providers = ProviderManager::get_available_providers();
        $this->assertArrayHasKey('smtp', $providers);
        $this->assertArrayHasKey('mailgun', $providers);
        $this->assertArrayHasKey('sendgrid', $providers);
        $this->assertArrayHasKey('brevo', $providers);
        $this->assertArrayHasKey('postmark', $providers);
        $this->assertArrayHasKey('smtp2go', $providers);
        $this->assertCount(6, $providers);
    }

    public function test_provider_manager_get_provider_returns_correct_class()
    {
        $smtp = ProviderManager::get_provider('smtp', array('host' => 'smtp.example.com'));
        $this->assertInstanceOf(SmtpProvider::class, $smtp);

        $mg = ProviderManager::get_provider('mailgun', array('api_key' => 'k', 'domain' => 'd'));
        $this->assertInstanceOf(MailgunProvider::class, $mg);

        $sg = ProviderManager::get_provider('sendgrid', array('api_key' => 'k'));
        $this->assertInstanceOf(SendgridProvider::class, $sg);

        $br = ProviderManager::get_provider('brevo', array('api_key' => 'k'));
        $this->assertInstanceOf(BrevoProvider::class, $br);

        $pm = ProviderManager::get_provider('postmark', array('server_token' => 't'));
        $this->assertInstanceOf(PostmarkProvider::class, $pm);

        $s2g = ProviderManager::get_provider('smtp2go', array('api_key' => 'k'));
        $this->assertInstanceOf(Smtp2goProvider::class, $s2g);
    }

    public function test_provider_manager_get_provider_returns_null_for_unknown()
    {
        $result = ProviderManager::get_provider('nonexistent', array());
        $this->assertNull($result);
    }

    public function test_provider_manager_send_email_uses_primary_smtp()
    {
        SmtpSettings::save(array(
            'provider_type' => 'smtp',
            'host'          => 'smtp.example.com',
        ));
        ProviderManager::flush_instances();

        $result = ProviderManager::send_email('to@example.com', 'Subj', 'Body');
        $this->assertTrue($result['success']);
        $this->assertSame('smtp', $result['provider']);
    }

    public function test_provider_manager_send_email_returns_error_for_unknown_provider()
    {
        SmtpSettings::save(array('provider_type' => 'nonexistent'));
        ProviderManager::flush_instances();

        $result = ProviderManager::send_email('to@example.com', 'Subj', 'Body');
        $this->assertFalse($result['success']);
    }

    public function test_provider_manager_fallback_on_primary_failure()
    {
        // Primary: mailgun (no config so it fails), fallback: smtp
        SmtpSettings::save(array(
            'provider_type'    => 'mailgun',
            'fallback_enabled' => true,
            'fallback_provider' => 'smtp',
            'host'             => 'smtp.example.com',
        ));
        ProviderManager::flush_instances();

        $result = ProviderManager::send_email('to@example.com', 'Subj', 'Body');
        // Mailgun has no config, will fail, fallback to SMTP which succeeds via wp_mail stub.
        $this->assertTrue($result['success']);
        $this->assertSame('smtp', $result['provider']);
        $this->assertArrayHasKey('fallback_from', $result['metadata']);
    }

    public function test_provider_manager_no_fallback_when_disabled()
    {
        SmtpSettings::save(array(
            'provider_type'     => 'mailgun',
            'fallback_enabled'  => false,
            'fallback_provider' => 'smtp',
        ));
        ProviderManager::flush_instances();

        $result = ProviderManager::send_email('to@example.com', 'Subj', 'Body');
        $this->assertFalse($result['success']);
        $this->assertSame('mailgun', $result['provider']);
    }

    // -------------------------------------------------------------------------
    // SmtpSettings — multi-provider fields
    // -------------------------------------------------------------------------

    public function test_smtp_settings_default_provider_type()
    {
        $settings = SmtpSettings::get_all();
        $this->assertSame('smtp', $settings['provider_type']);
    }

    public function test_smtp_settings_save_provider_type()
    {
        SmtpSettings::save(array('provider_type' => 'mailgun'));
        $this->assertSame('mailgun', SmtpSettings::get('provider_type'));
    }

    public function test_smtp_settings_rejects_invalid_provider_type()
    {
        SmtpSettings::save(array('provider_type' => 'unknown_xyz'));
        $this->assertSame('smtp', SmtpSettings::get('provider_type'));
    }

    public function test_smtp_settings_save_mailgun_api_key_encoded()
    {
        SmtpSettings::save(array(
            'provider_type'   => 'mailgun',
            'provider_config' => array(
                'mailgun' => array('api_key' => 'myapikey', 'domain' => 'mg.example.com'),
            ),
        ));
        $config = SmtpSettings::get('provider_config');
        $this->assertSame(base64_encode('myapikey'), $config['mailgun']['api_key']);
        $this->assertSame('mg.example.com', $config['mailgun']['domain']);
    }

    public function test_smtp_settings_save_postmark_server_token_encoded()
    {
        SmtpSettings::save(array(
            'provider_type'   => 'postmark',
            'provider_config' => array(
                'postmark' => array('server_token' => 'my-token'),
            ),
        ));
        $config = SmtpSettings::get('provider_config');
        $this->assertSame(base64_encode('my-token'), $config['postmark']['server_token']);
    }

    public function test_smtp_settings_fallback_fields()
    {
        SmtpSettings::save(array(
            'fallback_enabled'  => true,
            'fallback_provider' => 'smtp',
        ));
        $this->assertTrue((bool) SmtpSettings::get('fallback_enabled'));
        $this->assertSame('smtp', SmtpSettings::get('fallback_provider'));
    }

    public function test_smtp_settings_rejects_invalid_fallback_provider()
    {
        SmtpSettings::save(array('fallback_provider' => 'invalid_xyz'));
        $this->assertSame('', SmtpSettings::get('fallback_provider'));
    }
}
