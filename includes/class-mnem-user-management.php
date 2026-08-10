<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_User_Management {
    private $settings;
    private $logger;

    public function __construct( MNEM_Settings $settings, MNEM_Logger $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    public function create_rule( string $name, string $trigger_event, string $action, array $settings = array() ): int {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_user_rules';
        $wpdb->insert(
            $table,
            array(
                'name'          => sanitize_text_field( $name ),
                'trigger_event' => sanitize_key( $trigger_event ),
                'action'        => sanitize_key( $action ),
                'is_enabled'    => 1,
                'settings'      => wp_json_encode( $settings ),
                'created_at'    => current_time( 'mysql', true ),
                'updated_at'    => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public function record_event( int $user_id, string $event_type, array $details = array(), string $action_taken = 'recorded' ): int {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_user_events';
        $wpdb->insert(
            $table,
            array(
                'user_id'      => $user_id,
                'event_type'   => sanitize_key( $event_type ),
                'action_taken' => sanitize_key( $action_taken ),
                'details'      => wp_json_encode( $details ),
                'created_at'   => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public function execute_action( int $user_id, string $action, array $context = array() ): bool {
        $user = get_userdata( $user_id );

        if ( ! $user ) {
            return false;
        }

        if ( 'notify_admin' === $action ) {
            $this->notify_admin( $user, $context );
            $this->record_event( $user_id, 'action_notify_admin', $context, 'notified' );
            return true;
        }

        if ( 'suspend' === $action ) {
            update_user_meta( $user_id, 'mnem_suspended', 1 );
            $this->notify_admin( $user, array_merge( $context, array( 'message' => 'User suspended by rule.' ) ) );
            $this->record_event( $user_id, 'action_suspend', $context, 'suspended' );
            $this->logger->warning( 'User suspended by advanced user management rule.', array( 'user_id' => $user_id ) );
            return true;
        }

        if ( 'delete' === $action ) {
            $this->notify_admin( $user, array_merge( $context, array( 'message' => 'Delete action requested.' ) ) );

            if ( ! $this->settings->get( 'allow_user_deletion', false ) ) {
                $this->record_event( $user_id, 'action_delete', $context, 'blocked' );
                $this->logger->warning( 'Delete action blocked because user deletion is disabled.', array( 'user_id' => $user_id ) );
                return false;
            }

            if ( ! function_exists( 'wp_delete_user' ) ) {
                require_once ABSPATH . 'wp-admin/includes/user.php';
            }

            $deleted = wp_delete_user( $user_id );
            $this->record_event( $user_id, 'action_delete', $context, $deleted ? 'deleted' : 'failed' );
            $this->logger->warning( 'Delete action executed for user.', array( 'user_id' => $user_id ) );
            return (bool) $deleted;
        }

        return false;
    }

    private function notify_admin( WP_User $user, array $context = array() ): void {
        $recipient = $this->settings->get( 'admin_notice_email', get_site_option( 'admin_email', '' ) );

        if ( ! $recipient ) {
            return;
        }

        $message = sprintf(
            "User action review for #%d (%s).

Context: %s",
            $user->ID,
            $user->user_email,
            wp_json_encode( $context )
        );

        wp_mail( $recipient, __( 'Multisite Network Email Manager user action notice', 'multisite-network-email-manager' ), $message );
    }
}
