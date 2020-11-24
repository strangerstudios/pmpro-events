<?php
/*
Plugin Name: Paid Memberships Pro - Events Add On
Plugin URI: https://www.paidmembershipspro.com/add-ons/events-for-members-only/
Description: Offer Members-only events using PMPro and popular events plugins.
Version: 1.0
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
Text Domain: pmpro-events
Domain Path: /languages

*/

function pmpro_events_load_plugin_text_domain() {
	load_plugin_textdomain( 'pmpro-events', false, basename( dirname( __FILE__ ) ) . '/languages' ); 
}
add_action( 'plugins_loaded', 'pmpro_events_load_plugin_text_domain');

define( 'PMPRO_EVENTS_BASENAME', plugin_basename( __FILE__ ) );

function pmpro_events_plugin_init() {
	// Load module based on active events plugin
	$path = dirname( __FILE__ );

	// Events Manager (https://wordpress.org/plugins/events-manager/) 
	if ( defined( 'EM_VERSION' ) ) {
		require_once( $path . '/modules/events-manager.php' );
	}
	
	// The Events Calendar by Modern Tribe (https://wordpress.org/plugins/the-events-calendar/)
	if ( class_exists( 'Tribe__Events__Main' ) ) {
		require_once( $path . '/modules/the-events-calendar.php' );
	}

	// All in One Event Calendar (https://wordpress.org/plugins/all-in-one-event-calendar/)
	if ( defined( 'AI1EC_PATH' ) ) {
		require_once( $path . '/modules/all-in-one-event-calendar.php' );
	}
	
	// Sugar Calendar Lite (https://wordpress.org/plugins/sugar-calendar-lite/)
	if ( class_exists( 'Sugar_Calendar\\Plugin' ) ) {
		require_once( $path . '/modules/sugar-calendar.php' );
	}
}
add_action( 'plugins_loaded', 'pmpro_events_plugin_init' );

/**
 * Adjusts the word content with "event" if it's an event.
 * @since 1.0
 */
function pmpro_events_pmpro_text_filter( $text ) {
	global $post;

	$event_slugs = apply_filters( 'pmpro_events_supports_event_slug', array( 'event' ) );

	if( is_singular( $event_slugs ) ) {
		$text = str_replace( 'content', 'event', $text );
	}
	return $text;
}
add_filter( 'pmpro_non_member_text_filter', 'pmpro_events_pmpro_text_filter' );
add_filter( 'pmpro_not_logged_in_text_filter', 'pmpro_events_pmpro_text_filter' );

/**
 * Runs only when the plugin is activated.
 * @since 1.0
 */
function pmpro_events_activation_hook() {
	// Create transient data.
	set_transient( 'pmpro-events-admin-notice', true, 5 );
}
register_activation_hook( PMPRO_EVENTS_BASENAME, 'pmpro_events_activation_hook' );

/**
 * Show a notice on activation.
 * @since 1.0
 */
function pmpro_events_activation_admin_notice() {
	// Check transient, if available display notice.
	if ( get_transient( 'pmpro-events-admin-notice' ) ) {

		if (  ! defined( 'EM_VERSION' ) && ! class_exists( 'Tribe__Events__Main' ) && ! defined( 'AI1EC_PATH' ) && ! class_exists( 'Sugar_Calendar\\Plugin' ) ) {
		?>
			<div class="notice notice-warning is-dismissible">
			<p><?php printf( __( "Thank you for activating the Events Add On for Paid Memberships Pro. Unfortunately it seems we weren't able to find any supported events plugin. <a href='%s' target='_blank'>For more information click here.</a>", 'pmpro-events' ), "https://www.paidmembershipspro.com/add-ons/events-for-members-only/" ); ?></p>
		</div>
		<?php
		}else{
		?>
		<div class="updated notice is-dismissible">
			<p><?php printf( __( 'Thank you for activating the Events Add On for Paid Memberships Pro. To get started, edit an event and look for the "Require Membership" box in the sidebar. <a href="%s">View more documentation here.</a>', 'pmpro-events' ), "https://www.paidmembershipspro.com/add-ons/events-for-members-only/" ); ?></p>
		</div>
		<?php
		}
	// Delete transient, only display this notice once.
	delete_transient( 'pmpro-events-admin-notice' );
	}
}
add_action( 'admin_notices', 'pmpro_events_activation_admin_notice' );

/*
Function to add links to the plugin row meta
*/
function pmpro_events_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-events.php') !== false) {
		$new_links = array(
			'<a href="' . esc_url('https://www.paidmembershipspro.com/add-ons/events-for-members-only/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('https://www.paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);

		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmpro_events_plugin_row_meta', 10, 2 );