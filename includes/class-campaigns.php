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

	/**
	 * Retrieve a list of campaigns.
	 *
	 * @param array $args {
	 *     Optional query arguments.
	 *     @type string $status Filter by status (draft, scheduled, sent).
	 *     @type int    $limit  Number of campaigns to return.
	 *     @type int    $offset Offset for pagination.
	 * }
	 * @return array Array of campaign rows.
	 */
	public static function get_campaigns( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status' => '',
			'limit'  => 20,
			'offset' => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		$table = $wpdb->base_prefix . 'mnem_campaigns';
		$query = "SELECT * FROM {$table}";
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$query   .= ' WHERE status = %s';
			$params[] = sanitize_text_field( $args['status'] );
		}

		$query   .= ' ORDER BY created_at DESC LIMIT %d OFFSET %d';
		$params[] = absint( $args['limit'] );
		$params[] = absint( $args['offset'] );

		if ( ! empty( $params ) ) {
			return $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Create a new campaign.
	 *
	 * @param array $data Campaign data.
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'mnem_campaigns';
		$now   = current_time( 'mysql', true );

		$result = $wpdb->insert(
			$table,
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
}
