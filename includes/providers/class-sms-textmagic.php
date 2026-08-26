<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

class SmsTextmagic extends SmsBaseProvider
{
    public function get_config_schema(): array
    {
        return array(
            array('key' => 'username', 'label' => 'Username', 'type' => 'text'),
            array('key' => 'api_key', 'label' => 'API Key', 'type' => 'password'),
        );
    }
}
