<?php
/*
	* Add Membership Levels box to The Events Calendar by Modern Tribe CPTs
	* Hide member events from non-members.
*/

/*
	Add Membership Levels box to The Events Calendar CPTs
*/
function pmpro_events_tribe_events_page_meta_wrapper( ) {
	add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'tribe_events', 'side' );
}

/*
	Stuff to run on init
*/
function pmpro_events_tribe_events_init() {		

	/*
		If PMPro Option to filter is set.
		- Redirect single event page
		- Filter tripe_get_events
	*/
	if ( function_exists( 'pmpro_getOption' ) ) {
		$filterqueries = pmpro_getOption( "filterqueries" );
		$filter_archives = apply_filters( 'pmpro_events_tribe_events_filter_archives', true );
		if ( $filter_archives && ! empty( $filterqueries ) ) {			
			add_filter( 'tribe_get_events', 'pmpro_events_tribe_events_get_events', 10, 3 );
			add_filter( 'tribe_events_get_current_month_day', 'pmpro_events_tribe_events_get_current_month_day' );
		}
	}
	
	/*
		Add meta boxes to edit events page
	*/
	if( is_admin() ) {
		add_action( 'admin_menu', 'pmpro_events_tribe_events_page_meta_wrapper' );
	}
}
add_action( 'init', 'pmpro_events_tribe_events_init', 20 );

/*
 	Hide member content from searches via PMPro's pre_get_posts filter.
*/
function pmpro_events_tribe_events_pmpro_search_filter_post_types( $post_types ) {

	$filter_archives = apply_filters( 'pmpro_events_tribe_events_filter_archives', true );

	if ( $filter_archives ) {
		$post_types[] = 'tribe_events';
	}

	return $post_types;
}
add_filter( 'pmpro_search_filter_post_types', 'pmpro_events_tribe_events_pmpro_search_filter_post_types' );

/*
	Hide member content from other event lists/etc
*/
function pmpro_events_tribe_events_get_events( $events, $args, $full ) {
	
	//make sure PMPro is active
	if(!function_exists('pmpro_has_membership_access'))
		return $events;
		
	if(!empty($events) && !empty($events->posts)) {
		$newposts = array();
		foreach($events->posts as $post) {
			if(pmpro_has_membership_access($post->ID))
				$newposts[] = $post;
		}
		
		$events->posts = $newposts;
		$events->post_count = count($newposts);
	}		
	
	return $events;
}

/*
	The tribe_events_get_current_month_day function is also used when generating the calendar view.
	We need to filter the count to keep events from showing up there.
*/
function pmpro_events_tribe_events_get_current_month_day($day) {

	if($day['total_events'] > 0 && !empty($day['events']->posts)) {
		$day['total_events'] = count($day['events']->posts);
	}	
	
	return $day;
}

/**
 * Remove all Tribe Events Post Meta/Data for non-members.
 */
function pmpro_events_tribe_events_has_access( $hasaccess, $post, $user, $levels ){

	if ( ! is_admin() && is_single() && ! $hasaccess ) {

		// remove sections of single event if the user doesn't have access.
		add_filter( 'tribe_get_template_part_templates', 'pmpro_events_tribe_events_remove_post_meta_section', 10, 3 );
		add_filter( 'tribe_events_ical_single_event_links', '__return_false' );
		add_filter( 'tribe_get_cost', '__return_false' );
		add_filter( 'tribe_events_event_schedule_details', '__return_false' );

		// Integrates with Events Tickets Extension for The Events Calendar. Hides RSVP/Ticket purchase.
		if( class_exists( 'Tribe__Tickets__Main' ) ) {
			add_filter( 'tribe_events_tickets_template_tickets/rsvp.php', 'pmpro_events_tribe_events_tickets_remove_module' );
			add_filter( 'tribe_events_tickets_template_tickets/tpp.php', 'pmpro_events_tribe_events_tickets_remove_module' );

		}	
	}

	return $hasaccess;
}
add_filter( 'pmpro_has_membership_access_filter_tribe_events', 'pmpro_events_tribe_events_has_access', 10, 4 );

/**
 * This is called if the user does not have membership level.
 * Sets the template to none.
 * @return a blank array.
 */
function pmpro_events_tribe_events_remove_post_meta_section( $templates, $slug, $name ) {
	$r = array();
	$r = apply_filters( 'pmpro_events_tribe_events_page_modules', $r, $templates );
	return $r;		
}

/**
 * This is called if the user does not have membership level. Requires Event Tickets Plugin to be installed.
 * Sets the template to none.
 * @return a blank string.
 */
function pmpro_events_tribe_events_tickets_remove_module( $modules ) {
	$r = '';
	$r = apply_filters( 'pmpro_events_tribe_events_tickets_page_modules', $r, $modules );
	return $r;
}

/**
 * Adjust the filter of the the events to ensure it sticks to what we've set inside the event settings.
 * @since 1.0
 */
function pmpro_events_tribe_events_excerpt_filter( $excerpt ) {

	$excerpt = get_the_excerpt();

	return $excerpt;	
}
add_filter( 'tribe_events_get_the_excerpt', 'pmpro_events_tribe_events_excerpt_filter' );
