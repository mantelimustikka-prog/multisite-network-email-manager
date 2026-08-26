<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

class SmsSalesmsg extends SmsBaseProvider
{
    public function get_config_schema(): array
    {
        return array(
            array('key' => 'api_key', 'label' => 'API Key', 'type' => 'password'),
        );
    }
}
