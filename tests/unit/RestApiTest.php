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
}
