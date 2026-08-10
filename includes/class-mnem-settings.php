<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Settings {
    public const OPTION_KEY = 'mnem_settings';

    public function defaults(): array {
        return array(
            'smtp_enabled'          => false,
            'smtp_provider'         => 'custom',
            'smtp_host'             => '',
            'smtp_port'             => 587,
            'smtp_encryption'       => 'tls',
            'smtp_username'         => '',
            'smtp_password'         => '',
            'from_email'            => '',
            'from_name'             => '',
            'queue_batch_size'      => 20,
            'admin_notice_email'    => (string) get_site_option( 'admin_email', '' ),
            'allow_user_deletion'   => false,
            'default_delete_action' => 'notify',
        );
    }

    public function all(): array {
        return wp_parse_args( (array) get_site_option( self::OPTION_KEY, array() ), $this->defaults() );
    }

    public function get( string $key, $default = null ) {
        $settings = $this->all();

        return $settings[ $key ] ?? $default;
    }

    public function update( array $settings ): array {
        $sanitized = $this->sanitize( $settings );
        update_site_option( self::OPTION_KEY, $sanitized );

        return $sanitized;
    }

    public function sanitize( array $settings ): array {
        $defaults = $this->defaults();
        $settings = wp_parse_args( $settings, $defaults );

        return array(
            'smtp_enabled'          => ! empty( $settings['smtp_enabled'] ),
            'smtp_provider'         => sanitize_key( $settings['smtp_provider'] ),
            'smtp_host'             => sanitize_text_field( $settings['smtp_host'] ),
            'smtp_port'             => max( 1, absint( $settings['smtp_port'] ) ),
            'smtp_encryption'       => in_array( $settings['smtp_encryption'], array( 'none', 'ssl', 'tls' ), true ) ? $settings['smtp_encryption'] : 'tls',
            'smtp_username'         => sanitize_text_field( $settings['smtp_username'] ),
            'smtp_password'         => sanitize_text_field( $settings['smtp_password'] ),
            'from_email'            => sanitize_email( $settings['from_email'] ),
            'from_name'             => sanitize_text_field( $settings['from_name'] ),
            'queue_batch_size'      => max( 1, absint( $settings['queue_batch_size'] ) ),
            'admin_notice_email'    => sanitize_email( $settings['admin_notice_email'] ),
            'allow_user_deletion'   => ! empty( $settings['allow_user_deletion'] ),
            'default_delete_action' => in_array( $settings['default_delete_action'], array( 'notify', 'delete' ), true ) ? $settings['default_delete_action'] : 'notify',
        );
    }
}
