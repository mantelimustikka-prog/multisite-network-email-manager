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

        $table       = $wpdb->base_prefix . 'mnem_queue';
        $batch_size  = max( 1, absint( $this->settings->get( 'queue_batch_size', 20 ) ) );
        $max_attempts = max( 1, absint( $this->settings->get( 'queue_max_attempts', 5 ) ) );
        $now         = current_time( 'mysql', true );

        // Claim a batch atomically: flip status to 'processing' so a second
        // overlapping cron run cannot pick up the same items.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                    SET status = 'processing', processed_at = %s
                  WHERE status = 'pending'
                    AND available_at <= %s
                  ORDER BY id ASC
                  LIMIT %d",
                $now,
                $now,
                $batch_size
            )
        );

        $items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'processing' AND processed_at = %s ORDER BY id ASC",
                $now
            ),
            ARRAY_A
        );

        foreach ( $items as $item ) {
            try {
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
                    $this->handle_failure( (int) $item['id'], (int) $item['attempts'], $max_attempts, 'wp_mail returned false.' );
                }
            } catch ( \Throwable $e ) {
                $this->handle_failure( (int) $item['id'], (int) $item['attempts'], $max_attempts, $e->getMessage() );
            }
        }
    }

    /**
     * Re-schedule a failed item with exponential back-off or mark it dead once
     * max attempts have been exhausted.
     *
     * Back-off schedule (attempts already recorded before this call):
     *   attempt 1 → retry in  1 min
     *   attempt 2 → retry in  2 min
     *   attempt 3 → retry in  4 min
     *   attempt 4 → retry in  8 min
     *   …capped at 60 min per interval
     */
    private function handle_failure( int $id, int $previous_attempts, int $max_attempts, string $error ): void {
        global $wpdb;

        $new_attempts = $previous_attempts + 1;

        if ( $new_attempts >= $max_attempts ) {
            $this->mark_item( $id, 'dead', $error );
            $this->logger->error(
                'Queue item permanently failed (dead).',
                array(
                    'queue_id' => $id,
                    'attempts' => $new_attempts,
                    'error'    => $error,
                )
            );
            return;
        }

        $delay_minutes = min( 60, (int) pow( 2, $previous_attempts ) );
        $available_at  = gmdate( 'Y-m-d H:i:s', time() + $delay_minutes * MINUTE_IN_SECONDS );

        $table = $wpdb->base_prefix . 'mnem_queue';
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                    SET status = 'pending', attempts = %d, last_error = %s,
                        available_at = %s, processed_at = NULL
                  WHERE id = %d",
                $new_attempts,
                $error,
                $available_at,
                $id
            )
        );

        $this->logger->warning(
            'Queue item failed; scheduled for retry.',
            array(
                'queue_id'      => $id,
                'attempts'      => $new_attempts,
                'retry_in_min'  => $delay_minutes,
                'error'         => $error,
            )
        );
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
