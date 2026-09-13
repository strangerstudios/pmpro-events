<?php
/*
Plugin Name: Paid Memberships Pro - Events Add On
Plugin URI: https://www.paidmembershipspro.com/add-ons/events-for-members-only/
Description: Create and restrict events with PMPro, either natively or using popular events plugins.
Version: 2.0
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
Text Domain: pmpro-events
Domain Path: /languages
Requires Plugins: paid-memberships-pro
*/

define( 'PMPRO_EVENTS_VERSION', '2.0' );
define( 'PMPRO_EVENTS_BASENAME', plugin_basename( __FILE__ ) );
define( 'PMPRO_EVENTS_DIR', dirname( __FILE__ ) );
define( 'PMPRO_EVENTS_URL', plugins_url( '', __FILE__ ) );

// Always loaded: shared helpers, the module registry, the database layer, and the settings page.
require_once( PMPRO_EVENTS_DIR . '/includes/functions.php' );
require_once( PMPRO_EVENTS_DIR . '/includes/modules.php' );
require_once( PMPRO_EVENTS_DIR . '/includes/upgradecheck.php' );
require_once( PMPRO_EVENTS_DIR . '/includes/settings.php' );

/**
 * Load the files for each active module.
 *
 * The Default module ships the native pmpro_event post type and registration
 * engine. The third-party modules are loaded when their events plugin is
 * detected, as they always have been.
 *
 * Hooked to init at priority 0 rather than plugins_loaded so that the module
 * registry's translated names don't trigger translation loading too early.
 * Every hook the modules register fires at init priority 10 or later.
 *
 * @since 1.0
 */
function pmpro_events_plugin_init() {
	$path = PMPRO_EVENTS_DIR;

	// Default module: the native pmpro_event post type. Its frontend output
	// calls PMPro functions directly, so it only loads alongside PMPro itself.
	if ( defined( 'PMPRO_VERSION' ) && pmpro_events_is_module_active( 'default' ) ) {
		require_once( $path . '/modules/default/class-pmproevents-event.php' );
		require_once( $path . '/modules/default/class-pmproevents-event-registration.php' );
		require_once( $path . '/modules/default/cpt.php' );
		require_once( $path . '/modules/default/events-page.php' );
		require_once( $path . '/modules/default/editor.php' );
		require_once( $path . '/modules/default/registration.php' );
		require_once( $path . '/modules/default/emails.php' );
		require_once( $path . '/modules/default/calendar.php' );
		require_once( $path . '/modules/default/template.php' );
		require_once( $path . '/modules/default/block-template.php' );
		require_once( $path . '/modules/default/my-events.php' );
		require_once( $path . '/modules/default/events-block.php' );

		if ( is_admin() ) {
			require_once( $path . '/modules/default/admin.php' );
			require_once( $path . '/modules/default/admin-registrations.php' );
			require_once( $path . '/modules/default/edit-member.php' );
		}
	}

	// Events Manager (https://wordpress.org/plugins/events-manager/)
	if ( pmpro_events_is_module_active( 'events-manager' ) ) {
		require_once( $path . '/modules/events-manager.php' );
	}

	// The Events Calendar by Modern Tribe (https://wordpress.org/plugins/the-events-calendar/)
	if ( pmpro_events_is_module_active( 'the-events-calendar' ) ) {
		require_once( $path . '/modules/the-events-calendar.php' );
	}

	// All in One Event Calendar (https://wordpress.org/plugins/all-in-one-event-calendar/)
	if ( pmpro_events_is_module_active( 'all-in-one-event-calendar' ) ) {
		require_once( $path . '/modules/all-in-one-event-calendar.php' );
	}

	// Sugar Calendar Lite (https://wordpress.org/plugins/sugar-calendar-lite/)
	if ( pmpro_events_is_module_active( 'sugar-calendar' ) ) {
		require_once( $path . '/modules/sugar-calendar.php' );
	}
}
add_action( 'init', 'pmpro_events_plugin_init', 0 );

/**
 * Load Plugin Text Domain for Translations.
 * @since 1.1
 */
function pmpro_events_load_plugin_text_domain() {
	load_plugin_textdomain( 'pmpro-events', false, basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'pmpro_events_load_plugin_text_domain');

/**
 * Filter the message for users without access.
 *
 * @param string $text The message for users without access.
 * @param array $level_ids The level IDs that are restricted from the content.
 * @return string The filtered message for users without access.
 */
function pmpro_events_no_access_message_body( $body, $level_ids ) {
	// We are running PMPro v3.1+, so make sure that deprecated filters don't run later.
	remove_filter( 'pmpro_non_member_text_filter', 'pmpro_events_pmpro_text_filter' );
	remove_filter( 'pmpro_not_logged_in_text_filter', 'pmpro_events_pmpro_text_filter' );

	// If this is not an event, return the default message.
	$event_slugs = apply_filters( 'pmpro_events_supports_event_slug', array( 'event' ) );
	if ( ! is_singular( $event_slugs ) ) {
		return $body;
	}

	// Generate the message for the event.
	if ( count( $level_ids ) !== 1 ) {
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		$body = '<p>' . esc_html( sprintf( __( 'You must be a member to access this %s.', 'pmpro-events' ), pmpro_events_get_label( 'singular_lowercase' ) ) ) . '</p>';
		$body .= '<p><a class="' . esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ) . '" href="!!levels_page_url!!">' . esc_html__( 'View Membership Levels', 'pmpro-events' ) . '</a></p>';
	} else {
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		$body = '<p>' . esc_html( sprintf( __( 'You must be a !!levels!! member to access this %s.', 'pmpro-events' ), pmpro_events_get_label( 'singular_lowercase' ) ) ) . '</p>';
		$body .= '<p><a class="' . esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ) . '" href="' . esc_url( pmpro_url( 'checkout', '?pmpro_level=' . $level_ids[0] ) ) . '">' . esc_html__( 'Join Now', 'pmpro-events' ) . '</a></p>';
	}

	return $body;
}
add_filter( 'pmpro_no_access_message_body', 'pmpro_events_no_access_message_body', 10, 2 ); // PMPro v3.1+.

/**
 * Adjusts the word content with "event" if it's an event.
 * @since 1.0
 */
function pmpro_events_pmpro_text_filter( $text ) {
	$event_slugs = apply_filters( 'pmpro_events_supports_event_slug', array( 'event' ) );

	if( is_singular( $event_slugs ) ) {
		$text = str_replace( 'content', pmpro_events_get_label( 'singular_lowercase' ), $text );
	}
	return $text;
}
add_filter( 'pmpro_non_member_text_filter', 'pmpro_events_pmpro_text_filter' ); // Pre-PMPro v3.1.
add_filter( 'pmpro_not_logged_in_text_filter', 'pmpro_events_pmpro_text_filter' ); // Pre-PMPro v3.1.

/**
 * Runs only when the plugin is activated.
 * @since 1.0
 */
function pmpro_events_activation_hook() {
	// Set up the default module configuration and build the registrations table.
	pmpro_events_set_default_modules();
	pmpro_events_db_delta();

	// Autoloaded, since the upgrade check reads it on every admin request.
	update_option( 'pmpro_events_db_version', PMPRO_EVENTS_VERSION, true );

	// The event post type isn't registered yet, so flush on the next request.
	update_option( 'pmpro_events_flush_rewrite_rules', 1, 'no' );

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
	if ( ! get_transient( 'pmpro-events-admin-notice' ) ) {
		return;
	}

	if ( pmpro_events_is_module_active( 'default' ) ) {
		/* translators: 1: URL of the add new event screen, 2: URL of the events settings page. */
		$message = sprintf( __( 'Thank you for activating the Events Add On for Paid Memberships Pro. To get started, <a href="%1$s">add your first event</a> or <a href="%2$s">review your settings</a>.', 'pmpro-events' ), esc_url( admin_url( 'post-new.php?post_type=pmpro_event' ) ), esc_url( admin_url( 'admin.php?page=pmpro-events-settings' ) ) );
	} elseif ( pmpro_events_has_third_party_module() ) {
		/* translators: %s: URL of the events settings page. */
		$message = sprintf( __( 'Thank you for activating the Events Add On for Paid Memberships Pro. We detected an events plugin on your site, so the built-in events module was left off. Edit an event and look for the "Require Membership" box in the sidebar, or <a href="%s">review your settings</a>.', 'pmpro-events' ), esc_url( admin_url( 'admin.php?page=pmpro-events-settings' ) ) );
	} else {
		/* translators: %s: URL of the events settings page. */
		$message = sprintf( __( 'Thank you for activating the Events Add On for Paid Memberships Pro. The built-in events module is turned off. <a href="%s">Review your settings</a> to enable it.', 'pmpro-events' ), esc_url( admin_url( 'admin.php?page=pmpro-events-settings' ) ) );
	}
	?>
	<div class="updated notice is-dismissible">
		<p><?php echo wp_kses_post( $message ); ?></p>
	</div>
	<?php

	// Delete transient, only display this notice once.
	delete_transient( 'pmpro-events-admin-notice' );
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