<?php

class MNEM_User_Events
{
    protected $logger;

    public function __construct(MNEM_Logger $logger = null)
    {
        $this->logger = $logger ?: new MNEM_Logger();
    }

    public function register()
    {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('user_register', array($this, 'handle_user_register'), 10, 1);
        add_action('profile_update', array($this, 'handle_profile_update'), 10, 2);
        add_action('deleted_user', array($this, 'handle_deleted_user'), 10, 1);
        add_action('remove_user_from_blog', array($this, 'handle_remove_user_from_blog'), 10, 2);
    }

    public function handle_user_register($user_id)
    {
        $this->logger->log('info', 'User registered event captured.', array('user_id' => (int) $user_id));
    }

    public function handle_profile_update($user_id)
    {
        $this->logger->log('info', 'User profile updated event captured.', array('user_id' => (int) $user_id));
    }

    public function handle_deleted_user($user_id)
    {
        $this->logger->log('info', 'User deleted event captured.', array('user_id' => (int) $user_id));
    }

    public function handle_remove_user_from_blog($user_id, $blog_id)
    {
        $this->logger->log('info', 'User removed from site event captured.', array('user_id' => (int) $user_id, 'blog_id' => (int) $blog_id));
    }
}
