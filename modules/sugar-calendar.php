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
 *  Removes restricted events from Calendar/List View if the user doesn't have access to an event and filtering
 * @since 1.0
 * 
 */
function pmpro_events_sc_filter_events_archive( $events ) {
	if ( ! function_exists( 'pmpro_has_membership_access' ) ) {
		return $events;
	}

	$filterqueries = pmpro_getOption( 'filterqueries' );
	if ( empty( $filterqueries ) ) {
		return $events;
	}

	foreach ( $events as $key => $value ) {
		if ( ! pmpro_has_membership_access( $value->object_id ) ) {
			unset( $events[$key] ); // remove from the events array if the user doesn't have access.
		}
	}
	return $events;
}
add_action( 'sc_the_events', 'pmpro_events_sc_filter_events_archive' );
