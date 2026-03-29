<?php
/**
 * Database table creation and management.
 *
 * @package PMPro_Events
 * @since 2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create custom database tables.
 *
 * @since 2.0
 */
function pmpro_events_create_tables() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$wpdb->prefix}pmpro_events (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		post_id bigint(20) unsigned NOT NULL,
		start datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		start_utc datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		end datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		end_utc datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		timezone varchar(155) NOT NULL DEFAULT '',
		all_day tinyint(1) NOT NULL DEFAULT 0,
		duration_minutes int(10) unsigned NOT NULL DEFAULT 0,
		capacity int NOT NULL DEFAULT 0,
		venue_name varchar(255) NOT NULL DEFAULT '',
		venue_address text NOT NULL,
		level_id bigint(20) unsigned DEFAULT NULL,
		member_level_id bigint(20) unsigned DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'publish',
		uuid varchar(100) NOT NULL DEFAULT '',
		date_created datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		date_modified datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		UNIQUE KEY post_id (post_id),
		KEY event_times (start, end),
		KEY event_level (level_id),
		KEY event_status (status)
	) $charset_collate;";

	dbDelta( $sql );

	$sql = "CREATE TABLE {$wpdb->prefix}pmpro_event_registrations (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_id bigint(20) unsigned NOT NULL,
		post_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		order_id bigint(20) unsigned NOT NULL DEFAULT 0,
		status varchar(20) NOT NULL DEFAULT 'active',
		registered_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY event_id (event_id),
		KEY user_id (user_id),
		KEY order_id (order_id),
		UNIQUE KEY event_user (event_id, user_id)
	) $charset_collate;";

	dbDelta( $sql );

	update_option( 'pmpro_events_db_version', PMPRO_EVENTS_VERSION );
}

/**
 * Check DB version and upgrade if needed.
 *
 * @since 2.0
 */
function pmpro_events_check_db_version() {
	$installed_version = get_option( 'pmpro_events_db_version', '' );
	if ( $installed_version !== PMPRO_EVENTS_VERSION ) {
		pmpro_events_create_tables();
	}
}
add_action( 'admin_init', 'pmpro_events_check_db_version' );
