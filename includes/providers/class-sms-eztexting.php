<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

class SmsEztexting extends SmsBaseProvider
{
    public function get_config_schema(): array
    {
        return array(
            array('key' => 'username', 'label' => 'Username', 'type' => 'text'),
            array('key' => 'password', 'label' => 'Password', 'type' => 'password'),
        );
    }
}
