<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

class SmsSlicktext extends SmsBaseProvider
{
    public function get_config_schema(): array
    {
        return array(
            array('key' => 'public_key', 'label' => 'Public Key', 'type' => 'text'),
            array('key' => 'private_key', 'label' => 'Private Key', 'type' => 'password'),
        );
    }
}
