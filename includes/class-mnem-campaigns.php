<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Campaigns {
    private $logger;

    public function __construct( MNEM_Logger $logger ) {
        $this->logger = $logger;
    }

    public function create_placeholder( string $name, array $settings = array(), string $content = '' ): int {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_campaigns';
        $wpdb->insert(
            $table,
            array(
                'name'       => sanitize_text_field( $name ),
                'status'     => 'draft',
                'settings'   => wp_json_encode( $settings ),
                'content'    => wp_kses_post( $content ),
                'created_by' => get_current_user_id(),
                'created_at' => current_time( 'mysql', true ),
                'updated_at' => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        $campaign_id = (int) $wpdb->insert_id;

        if ( $campaign_id ) {
            $this->logger->info( 'Campaign placeholder created.', array( 'campaign_id' => $campaign_id ) );
        }

        return $campaign_id;
    }
}
