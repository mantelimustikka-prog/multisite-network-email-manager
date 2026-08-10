<?php
/**
 * Plugin uninstall cleanup.
 *
 * @package MultisiteNetworkEmailManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( function_exists( 'delete_site_option' ) ) {
	delete_site_option( 'mnem_smtp_settings' );
	delete_site_option( 'mnem_enc_key' );
	delete_site_option( 'mnem_mail_queue' );
}
