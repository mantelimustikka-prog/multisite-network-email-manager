<?php
/**
 * Campaigns — placeholder module for email campaign management.
 *
 * Future implementation will cover campaign creation, scheduling,
 * recipient lists, and reporting.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_Campaigns {

	/** Allowed status transitions: current_status => [ allowed_next_statuses ] */
	const STATUS_TRANSITIONS = array(
		'draft'     => array( 'scheduled', 'sent' ),
		'scheduled' => array( 'draft', 'sent' ),
		'sent'      => array(),
	);

	/**
	 * Retrieve a list of campaigns.
	 *
	 * @param array $args Optional query arguments: status, limit, offset.
	 * @return array Array of campaign rows.
	 */
	public static function get_campaigns( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status' => '',
			'limit'  => 20,
			'offset' => 0,
		);
		$args   = wp_parse_args( $args, $defaults );
		$table  = $wpdb->base_prefix . 'mnem_campaigns';
		$params = array();
		$where  = '';

		if ( ! empty( $args['status'] ) ) {
			$where    = ' WHERE status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		$params[] = absint( $args['limit'] );
		$params[] = absint( $args['offset'] );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}{$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			),
			ARRAY_A
		);
	}

	/**
	 * Retrieve a single campaign by ID.
	 *
	 * @param int $id Campaign ID.
	 * @return array|null Campaign row or null if not found.
	 */
	public static function get_campaign( $id ) {
		global $wpdb;
		$table = $wpdb->base_prefix . 'mnem_campaigns';
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Create a new campaign.
	 *
	 * @param array $data Campaign data: name, subject, body.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$now = current_time( 'mysql', true );

		$result = $wpdb->insert(
			$wpdb->base_prefix . 'mnem_campaigns',
			array(
				'name'       => sanitize_text_field( $data['name'] ?? '' ),
				'subject'    => sanitize_text_field( $data['subject'] ?? '' ),
				'body'       => wp_kses_post( $data['body'] ?? '' ),
				'status'     => 'draft',
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing campaign.
	 *
	 * @param int   $id   Campaign ID.
	 * @param array $data Fields to update: name, subject, body.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		$update = array( 'updated_at' => current_time( 'mysql', true ) );

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['subject'] ) ) {
			$update['subject'] = sanitize_text_field( $data['subject'] );
		}
		if ( isset( $data['body'] ) ) {
			$update['body'] = wp_kses_post( $data['body'] );
		}

		return (bool) $wpdb->update(
			$wpdb->base_prefix . 'mnem_campaigns',
			$update,
			array( 'id' => absint( $id ) ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Transition a campaign's status.
	 *
	 * @param int    $id         Campaign ID.
	 * @param string $new_status Target status.
	 * @return true|WP_Error
	 */
	public static function transition_status( $id, $new_status ) {
		global $wpdb;

		$campaign = self::get_campaign( $id );
		if ( ! $campaign ) {
			return new WP_Error( 'mnem_not_found', __( 'Campaign not found.', 'mnem' ) );
		}

		$current = $campaign['status'];
		if ( ! isset( self::STATUS_TRANSITIONS[ $current ] ) || ! in_array( $new_status, self::STATUS_TRANSITIONS[ $current ], true ) ) {
			return new WP_Error(
				'mnem_invalid_transition',
				/* translators: 1: current status, 2: target status */
				sprintf( __( 'Cannot transition from "%1$s" to "%2$s".', 'mnem' ), $current, $new_status )
			);
		}

		$wpdb->update(
			$wpdb->base_prefix . 'mnem_campaigns',
			array(
				'status'     => sanitize_key( $new_status ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}
}
