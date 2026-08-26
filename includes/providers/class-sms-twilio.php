<?php

namespace MNEM\Providers;

defined('ABSPATH') || exit;

class SmsTwilio extends SmsBaseProvider
{
    public function get_config_schema(): array
    {
        return array(
            array('key' => 'account_sid', 'label' => 'Account SID', 'type' => 'text'),
            array('key' => 'auth_token', 'label' => 'Auth Token', 'type' => 'password'),
            array('key' => 'from_number', 'label' => 'From Number', 'type' => 'text'),
        );
    }
}
