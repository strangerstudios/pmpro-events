<?php
/*
	* Add Membership Levels box to The Events Calendar by Modern Tribe CPTs
	* Hide member events from non-members.
*/

/*
	Add Membership Levels box to The Events Calendar CPTs
*/
function pmpro_events_page_meta_wrapper()
{
	add_meta_box('pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'tribe_events', 'side');	
}
function pmpro_events_init()
{
	if (is_admin())
	{
		add_action('admin_menu', 'pmpro_events_page_meta_wrapper');
	}
}
add_action("init", "pmpro_events_init", 20);

/*
	Hide member events from non-members.
*/
function pmpro_the_events_calendar_template_redirect()
{
	global $post;	
	if(!is_admin() && isset($post->post_type) && ($post->post_type == "tribe_events") && !pmpro_has_membership_access())
	{
		wp_redirect(pmpro_url("levels"));
		exit;
	}
}

/*
 	Hide member content from searches.
*/
function pmpro_the_events_calendar_tribe_get_events($args, $full)
{
	//don't do anything in the admin
	if(is_admin())
		return $args;
 
	//which events are restricted
	
	
	/*
	global $wpdb, $current_user;	
	$sqlQuery = "SELECT DISTINCT(mp.page_id) FROM $wpdb->pmpro_memberships_pages mp LEFT JOIN $wpdb->posts p ON mp.page_id = p.ID WHERE p.post_type IN('tribe_events') ";
	if(!empty($current_user->membership_level->id))
		$sqlQuery .= " AND mp.membership_id <> '" . $current_user->membership_level->id . "' ";
	$restricted_events = $wpdb->get_col($sqlQuery);
	
	//remove restricted events	
	$recurrence_events = array();
	$newevents = array();
	foreach($events as $event)
	{
		//if the event is recurring, get the post id of it's parent
		if(!empty($event->recurrence_id) && empty($recurrence_events[$event->recurrence_id]))
		{
			//set post id for recurrence event in the recurrence events array
			$recurrence_events[$event->recurrence_id] = $wpdb->get_var("SELECT post_id FROM " . $wpdb->prefix . "em_events WHERE event_id = '" . $event->recurrence_id . "' LIMIT 1");						
		}
		
		if(!in_array($event->post_id, $restricted_events) && (empty($recurrence_events[$event->recurrence_id]) || !in_array($recurrence_events[$event->recurrence_id], $restricted_events)))
			$newevents[] = $event;
	}
	
	return $newevents;
	*/
	return $args;
}

/*
	Filter searches and redirect sinle event page if PMPro Option to filter is set.
*/
$filterqueries = pmpro_getOption("filterqueries");
if(!empty($filterqueries))
{
	add_filter('tribe_get_events','pmpro_the_events_calendar_tribe_get_events', 10, 2);
	//add_action('wp', 'pmpro_the_events_calendar_template_redirect');
}