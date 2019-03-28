<?php

/**
 * Add metabox to All-In-One-Event Calendar CPT.
 */
function pmpro_events_ai1ec_page_meta_wrapper( ) {
	add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'ai1ec_event', 'side' );
}
add_action( 'admin_menu', 'pmpro_events_ai1ec_page_meta_wrapper' );

/**
 * Remove event meta data.
 */
function pmpro_events_ai1ec_remove_event_meta( $r, $event ) {

	if ( ! pmpro_has_membership_access( $event->ID ) ){
		$r = '';
	}

	return $r;
}
add_filter( 'ai1ec_rendering_single_event_actions', 'pmpro_events_ai1ec_remove_event_meta', 10, 2 );
add_filter( 'ai1ec_rendering_single_event_venues', 'pmpro_events_ai1ec_remove_event_meta', 10, 2 );

/**
 * Remove event time for non-members.
 */
function pmpro_events_ai1ec_remove_event_time( $output, $event, $start_date_display ) {

	if ( ! pmpro_has_membership_access( $event->ID ) ){
		$output = '';
	}

	return $output;
}
add_filter( 'ai1ec_get_timespan_html', 'pmpro_events_ai1ec_remove_event_time', 10, 3 );
