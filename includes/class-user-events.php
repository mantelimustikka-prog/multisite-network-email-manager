<?php

namespace MNEM;

defined('ABSPATH') || exit;

class UserEvents
{
    public function init()
    {
        add_action('user_register', array($this, 'on_user_register'));
        add_action('deleted_user', array($this, 'on_user_deleted'));
    }

    public function on_user_register(int $user_id)
    {
        UserEventsCampaign::trigger_event('user_register', $user_id);
        Logger::info('User registered event captured.', array('user_id' => $user_id));
    }

    public function on_user_deleted(int $user_id)
    {
        UserEventsCampaign::trigger_event('user_delete', $user_id);
        Logger::info('User deleted event captured.', array('user_id' => $user_id));
    }
}
