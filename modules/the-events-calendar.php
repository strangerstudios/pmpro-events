<?php
/*
	* Add Membership Levels box to The Events Calendar by Modern Tribe CPTs
	* Hide member events from non-members.
*/

/*
	Add Membership Levels box to The Events Calendar CPTs
*/
function pmpro_events_page_meta_wrapper( ) {
	add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'tribe_events', 'side' );
}

/*
	Stuff to run on init
*/
function pmpro_events_calendar_init() {		
	/*
		If PMPro Option to filter is set.
		- Redirect single event page
		- Filter tripe_get_events
	*/
	if(function_exists('pmpro_getOption')) {
		$filterqueries = pmpro_getOption("filterqueries");
		if(!empty($filterqueries)) {			
			add_action('template_redirect', 'pmpro_events_calendar_template_redirect');
			add_filter( 'tribe_get_events', 'pmpro_events_tribe_get_events', 10, 3 );
			add_filter('tribe_events_get_current_month_day', 'pmpro_events_tribe_events_get_current_month_day');
		}
	}
	
	/*
		Add meta boxes to edit events page
	*/
	if( is_admin() ) {
		add_action( 'admin_menu', 'pmpro_events_page_meta_wrapper' );
	}
}
add_action( 'init', 'pmpro_events_calendar_init', 20 );

/*
	Hide member events from non-members.
*/
function pmpro_events_calendar_template_redirect()
{
	$queried_object = get_queried_object();
		
	if( !is_admin() && !empty($queried_object) && isset($queried_object->post_type) && ($queried_object->post_type == "tribe_events") && !pmpro_has_membership_access() )
	{
		wp_redirect(pmpro_url( 'levels' ));
		exit;
	}
}

/*
 	Hide member content from searches via PMPro's pre_get_posts filter.
*/
function pmpro_events_calendar_pmpro_search_filter_post_types( $post_types ) {
	$post_types[] = 'tribe_events';
	return $post_types;
}
add_filter( 'pmpro_search_filter_post_types', 'pmpro_events_calendar_pmpro_search_filter_post_types' );

/*
	Hide member content from other event lists/etc
*/
function pmpro_events_tribe_get_events( $events, $args, $full ) {
	
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


