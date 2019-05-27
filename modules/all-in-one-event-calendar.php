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

	$event_id = get_the_ID();

	if ( ! pmpro_has_membership_access( $event_id ) && ! empty( $r ) ){
		$r = __('This information is restricted to members only.', 'pmpro-events' );
	}

	return $r;
}
// add_filter( 'ai1ec_rendering_single_event_actions', 'pmpro_events_ai1ec_remove_event_meta', 10, 2 );
add_filter( 'ai1ec_rendering_single_event_venues', 'pmpro_events_ai1ec_remove_event_meta', 10, 2 );

function pmpro_events_ai1ec_filter_archives( $args ) {

	$filter_ai1ec_events_archive = apply_filters( 'pmpro_events_ai1ec_filter_archive', true );

	$filterqueries = pmpro_getOption("filterqueries");
	if ( empty( $filterqueries ) && $filter_ai1ec_events_archive ) {
		return $args;
	}

	$events_query_args = apply_filters( 'pmpro_events_ai1ec_query_args', 
		array(
			'post_type' => 'ai1ec_event',
			'limit' => '50'
		)
	);
	$events = get_posts( $events_query_args );

	foreach ( $events as $key => $post ) {
		if ( pmpro_has_membership_access( $post->ID ) ) {
			$args['post_ids'][] = $post->ID;
		}
	}
	return $args;
}
add_filter( 'ai1ec_view_args_array', 'pmpro_events_ai1ec_filter_archives', 10, 1 );