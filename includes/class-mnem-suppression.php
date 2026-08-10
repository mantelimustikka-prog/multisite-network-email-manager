<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Suppression {
    public function is_suppressed( string $email ): bool {
        global $wpdb;

        $email = sanitize_email( $email );
        if ( '' === $email ) {
            return false;
        }

        $table = $wpdb->base_prefix . 'mnem_suppressions';
        $found = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE email = %s LIMIT 1", $email ) );

        return ! empty( $found );
    }

    public function add( string $email, string $reason, string $source = 'manual', array $metadata = array() ): bool {
        global $wpdb;

        $email = sanitize_email( $email );
        if ( '' === $email ) {
            return false;
        }

        $table = $wpdb->base_prefix . 'mnem_suppressions';

        $result = $wpdb->replace(
            $table,
            array(
                'email'      => $email,
                'reason'     => sanitize_text_field( $reason ),
                'source'     => sanitize_key( $source ),
                'metadata'   => wp_json_encode( $metadata ),
                'created_at' => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );

        return false !== $result;
    }
}
