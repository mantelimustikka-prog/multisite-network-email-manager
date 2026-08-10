<?php
/**
 * User Management — placeholder for advanced user-triggered email workflows.
 *
 * Future implementation will provide rule-based user event capture
 * (registration, login, profile update, role change) and the ability to
 * trigger notifications, suspensions, or deletions based on those events.
 *
 * @package MNEM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MNEM_User_Management {

	/**
	 * Register hooks for user events.
	 *
	 * @placeholder — wire to rule engine when implemented.
	 */
	public static function init() {
		// User registration event.
		add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 10, 1 );

		// User deletion event.
		add_action( 'delete_user', array( __CLASS__, 'on_user_delete' ), 10, 1 );

		// Role change event.
		add_action( 'set_user_role', array( __CLASS__, 'on_role_change' ), 10, 3 );
	}

	/**
	 * Handle user registration.
	 *
	 * @param int $user_id New user ID.
	 */
	public static function on_user_register( $user_id ) {
		/**
		 * Action fired when a user registers, for custom workflow hooks.
		 *
		 * @param int $user_id New user ID.
		 */
		do_action( 'mnem_user_registered', absint( $user_id ) );
		MNEM_Logger::info( 'user_management', 'User registered', array( 'user_id' => $user_id ) );
	}

	/**
	 * Handle user deletion.
	 *
	 * @param int $user_id Deleted user ID.
	 */
	public static function on_user_delete( $user_id ) {
		/**
		 * Action fired before a user is deleted.
		 *
		 * @param int $user_id User ID.
		 */
		do_action( 'mnem_user_deleted', absint( $user_id ) );
		MNEM_Logger::info( 'user_management', 'User deleted', array( 'user_id' => $user_id ) );
	}

	/**
	 * Handle role change.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $new_role New role slug.
	 * @param array  $old_roles Previous roles.
	 */
	public static function on_role_change( $user_id, $new_role, $old_roles ) {
		/**
		 * Action fired when a user's role changes.
		 *
		 * @param int    $user_id   User ID.
		 * @param string $new_role  New role.
		 * @param array  $old_roles Old roles.
		 */
		do_action( 'mnem_user_role_changed', absint( $user_id ), sanitize_key( $new_role ), $old_roles );
		MNEM_Logger::info(
			'user_management',
			'User role changed',
			array(
				'user_id'  => $user_id,
				'new_role' => $new_role,
			)
		);
	}
}
