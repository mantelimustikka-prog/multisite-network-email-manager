<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Logger {
    public function log( string $level, string $message, array $context = array() ): void {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_logs';
        $data  = array(
            'level'      => sanitize_key( $level ),
            'message'    => wp_strip_all_tags( $message ),
            'context'    => wp_json_encode( $context ),
            'created_at' => current_time( 'mysql', true ),
        );

        $result = $wpdb->insert(
            $table,
            $data,
            array( '%s', '%s', '%s', '%s' )
        );

        if ( false === $result ) {
            error_log( sprintf( 'MNEM [%s] %s', $level, $message ) );
        }
    }

    public function info( string $message, array $context = array() ): void {
        $this->log( 'info', $message, $context );
    }

    public function warning( string $message, array $context = array() ): void {
        $this->log( 'warning', $message, $context );
    }

    public function error( string $message, array $context = array() ): void {
        $this->log( 'error', $message, $context );
    }

    public function latest( int $limit = 20 ): array {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_logs';
        $limit = max( 1, absint( $limit ) );

        return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d", $limit ), ARRAY_A );
    }
}
