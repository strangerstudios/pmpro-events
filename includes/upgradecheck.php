<?php
/**
 * Database setup for the event registrations table.
 *
 * @since 2.0
 */

/**
 * Register the registrations table on $wpdb.
 *
 * @since 2.0
 */
function pmpro_events_set_table_names() {
	global $wpdb;

	$wpdb->pmpro_event_registrations = $wpdb->prefix . 'pmpro_event_registrations';
}
pmpro_events_set_table_names();

/**
 * Make sure the registrations table is set up correctly.
 *
 * @since 2.0
 */
function pmpro_events_db_delta() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	global $wpdb;
	$wpdb->hide_errors();
	pmpro_events_set_table_names();

	$sqlQuery = "
		CREATE TABLE `" . $wpdb->pmpro_event_registrations . "` (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`event_id` bigint(20) unsigned NOT NULL,
			`user_id` bigint(20) unsigned NOT NULL,
			`status` varchar(20) NOT NULL DEFAULT 'active',
			`registered_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (`id`),
			KEY `user_id` (`user_id`),
			UNIQUE KEY `event_user` (`event_id`,`user_id`)
		);
	";
	dbDelta( $sqlQuery );
}

/**
 * Run the table setup whenever the plugin version changes.
 *
 * @since 2.0
 */
function pmpro_events_check_for_upgrades() {
	$db_version = get_option( 'pmpro_events_db_version' );

	if ( PMPRO_EVENTS_VERSION === $db_version ) {
		return;
	}

	pmpro_events_db_delta();

	// Autoloaded, since this check runs on every admin request.
	update_option( 'pmpro_events_db_version', PMPRO_EVENTS_VERSION, true );

	// Make sure a site upgrading from an earlier version has a module configuration.
	pmpro_events_set_default_modules();
}

// Check if the DB needs to be upgraded. This may set the default module
// configuration, which reads the translated module registry, so it runs on
// init rather than plugins_loaded. It is hooked before the module loader in
// pmpro-events.php so that it runs first at the same priority.
if ( is_admin() || defined( 'WP_CLI' ) ) {
	add_action( 'init', 'pmpro_events_check_for_upgrades', 0 );
}
