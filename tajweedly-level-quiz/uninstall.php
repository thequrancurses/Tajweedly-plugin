<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'tjq_settings', array() );
if ( ! empty( $settings['delete_on_uninstall'] ) ) {
	global $wpdb;
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'tjq_attempts' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	delete_option( 'tjq_settings' );
	delete_option( 'tjq_db_version' );
	delete_option( 'tjq_custom_questions' );
	wp_clear_scheduled_hook( 'tjq_drip_cron' );
}
