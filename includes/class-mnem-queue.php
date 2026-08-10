<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Queue {
    public const CRON_HOOK = 'mnem_process_queue';

    private $settings;
    private $logger;
    private $suppression;

    public function __construct( MNEM_Settings $settings, MNEM_Logger $logger, MNEM_Suppression $suppression ) {
        $this->settings    = $settings;
        $this->logger      = $logger;
        $this->suppression = $suppression;
    }

    public function hooks(): void {
        add_action( self::CRON_HOOK, array( $this, 'process' ) );
        self::register_schedule();
        self::schedule();
    }

    public static function register_schedule(): void {
        add_filter(
            'cron_schedules',
            static function ( array $schedules ): array {
                if ( ! isset( $schedules['minute'] ) ) {
                    $schedules['minute'] = array(
                        'interval' => MINUTE_IN_SECONDS,
                        'display'  => __( 'Every Minute', 'multisite-network-email-manager' ),
                    );
                }

                return $schedules;
            }
        );
    }

    public static function schedule(): void {
        self::register_schedule();

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'minute', self::CRON_HOOK );
        }
    }

    public function enqueue( string $recipient, string $subject, string $body, array $headers = array(), array $attachments = array(), ?string $available_at = null ): int {
        global $wpdb;

        $recipient = sanitize_email( $recipient );
        if ( '' === $recipient ) {
            return 0;
        }

        if ( $this->suppression->is_suppressed( $recipient ) ) {
            return 0;
        }

        $table = $wpdb->base_prefix . 'mnem_queue';
        $wpdb->insert(
            $table,
            array(
                'recipient'    => $recipient,
                'subject'      => sanitize_text_field( $subject ),
                'body'         => wp_kses_post( $body ),
                'headers'      => wp_json_encode( $headers ),
                'attachments'  => wp_json_encode( $attachments ),
                'status'       => 'pending',
                'attempts'     => 0,
                'available_at' => $available_at ?: current_time( 'mysql', true ),
                'created_at'   => current_time( 'mysql', true ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        return (int) $wpdb->insert_id;
    }

    public function process(): void {
        global $wpdb;

        $table      = $wpdb->base_prefix . 'mnem_queue';
        $batch_size = max( 1, absint( $this->settings->get( 'queue_batch_size', 20 ) ) );
        $items      = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s AND available_at <= %s ORDER BY id ASC LIMIT %d",
                'pending',
                current_time( 'mysql', true ),
                $batch_size
            ),
            ARRAY_A
        );

        foreach ( $items as $item ) {
            if ( $this->suppression->is_suppressed( $item['recipient'] ) ) {
                $this->mark_item( (int) $item['id'], 'suppressed', 'Recipient is suppressed.' );
                continue;
            }

            $headers     = json_decode( (string) $item['headers'], true ) ?: array();
            $attachments = json_decode( (string) $item['attachments'], true ) ?: array();
            $sent        = wp_mail( $item['recipient'], $item['subject'], $item['body'], $headers, $attachments );

            if ( $sent ) {
                $this->mark_item( (int) $item['id'], 'sent' );
            } else {
                $this->mark_item( (int) $item['id'], 'failed', 'wp_mail returned false.' );
            }
        }
    }

    private function mark_item( int $id, string $status, string $last_error = '' ): void {
        global $wpdb;

        $table = $wpdb->base_prefix . 'mnem_queue';
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET status = %s, attempts = attempts + 1, last_error = %s, processed_at = %s WHERE id = %d",
                sanitize_key( $status ),
                $last_error,
                current_time( 'mysql', true ),
                $id
            )
        );

        $this->logger->info(
            'Queue item updated.',
            array(
                'queue_id' => $id,
                'status'   => $status,
            )
        );
    }
}
