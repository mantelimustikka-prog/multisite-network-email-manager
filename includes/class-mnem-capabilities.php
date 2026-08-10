<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Capabilities {
    public const MANAGE_SETTINGS = 'manage_network_options';
    public const VIEW_LOGS = 'manage_network_options';
    public const MANAGE_QUEUE = 'manage_network_options';
    public const MANAGE_USERS = 'manage_network_users';

    public static function can( string $capability ): bool {
        return current_user_can( $capability );
    }
}
