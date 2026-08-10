<?php
/**
 * Suppression — email suppression list management.
 *
 * Emails on this list are never sent to, regardless of campaign or queue.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_Suppression {

	/**
	 * Check whether an email address is suppressed.
	 *
	 * @param string $email Email to check.
	 * @return bool
	 */
	public static function is_suppressed( $email ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return false;
		}

		$table = $wpdb->base_prefix . 'mnem_suppression';
		$count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE email = %s", $email ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return (int) $count > 0;
	}

	/**
	 * Add an email to the suppression list.
	 *
	 * @param string $email  Email address.
	 * @param string $reason Reason for suppression (optional).
	 * @return int|false Inserted row ID or false on failure.
	 */
	public static function add( $email, $reason = '' ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return false;
		}

		$table = $wpdb->base_prefix . 'mnem_suppression';

		$result = $wpdb->replace(
			$table,
			array(
				'email'      => $email,
				'reason'     => sanitize_text_field( $reason ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Remove an email from the suppression list.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function remove( $email ) {
		global $wpdb;

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return false;
		}

		$table = $wpdb->base_prefix . 'mnem_suppression';
		return (bool) $wpdb->delete( $table, array( 'email' => $email ), array( '%s' ) );
	}

	/**
	 * Get all suppressed emails.
	 *
	 * @param int $limit  Max number to return.
	 * @param int $offset Pagination offset.
	 * @return array
	 */
	public static function get_list( $limit = 100, $offset = 0 ) {
		global $wpdb;

		$table = $wpdb->base_prefix . 'mnem_suppression';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT email, reason, created_at FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $limit ),
				absint( $offset )
			),
			ARRAY_A
		);
	}
}
