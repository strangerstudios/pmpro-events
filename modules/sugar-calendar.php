<?php

/**
 * Add metabox to Sugar Calendar  CPT.
 */
function pmpro_events_sugar_calendar_page_meta_wrapper( ) {
	if ( defined( 'PMPRO_VERSION' ) ) {
		add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'sc_event', 'side', 'high' );
	}
}
add_action( 'admin_menu', 'pmpro_events_sugar_calendar_page_meta_wrapper' );

/**
 * Hides the event meta information displayed before the event details.
 * @since 1.0
 * Filters on https://www.paidmembershipspro.com/hook/pmpro_has_membership_access_filter/
 */
function pmpro_events_sugar_calendar_has_membership_access( $hasaccess, $post, $user, $levels ) {

	$hide_meta = apply_filters( 'pmpro_events_sugar_calendar_hide_event_details', true );

	if ( $hide_meta && $post->post_type == 'sc_event' && ! $hasaccess ) {
		remove_action( 'sc_before_event_content', 'sc_add_event_details' );
	}

	return $hasaccess;
}
add_filter( 'pmpro_has_membership_access_filter', 'pmpro_events_sugar_calendar_has_membership_access', 10, 4 );

/**
 * Removes restricted events from search and archives pages if filtering archives set inside Paid Memberships Pro.
 * @since 1.0
 * Filters on https://www.paidmembershipspro.com/hook/pmpro_search_filter_post_types/
 */
function pmpro_events_sugar_calendar_filter_archives( $post_types ) {
	
	// Add the sc_event post type to the post type filter in PMPro.
	$post_types[] = 'sc_event';

	return $post_types;
}
add_filter( 'pmpro_search_filter_post_types', 'pmpro_events_sugar_calendar_filter_archives', 10, 1 );

/**
 *  Removes restricted events from Calendar View if the user doesn't have access to an event and filtering
 * @since 1.0
 * 
 */
function pmpro_events_sugar_calendar_filter_calendar_events( $link, $event, $size ) {
	
	if ( ! function_exists( 'pmpro_has_membership_access' ) ) {
		return $link;
	}
	
	$hide_events = pmpro_getOption( 'filterqueries' );
	$hide_events = apply_filters( 'pmpro_events_sugar_calendar_filter_calendar_events', $hide_events );

	if ( ! pmpro_has_membership_access( $event ) && $hide_events ) {
		$link = NULL;
	}
	
	return $link;
}
add_filter( 'sc_event_calendar_link', 'pmpro_events_sugar_calendar_filter_calendar_events', 10, 3 );