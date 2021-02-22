<?php
/*
	* Add Membership Levels box to Events Manager CPTs
	* Hide member events from non-members.
*/

/**
 * Add Membership Levels box to Events Manager CPTs
 * @since 1.0
 */
function pmpro_events_events_manager_page_meta_wrapper() {
	add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'event', 'side', 'high' );
	add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'event-recurring', 'side', 'high' );	
}

/**
 * Stuff to run on init
 * @since 1.0
 */
function pmpro_events_events_manager_init() {
	/*
		Filter searches and redirect single event page if PMPro Option to filter is set.
	*/
	if(function_exists('pmpro_getOption')) {
		$filterqueries = pmpro_getOption("filterqueries");
		if(!empty($filterqueries)) {
			add_filter('em_events_get','pmpro_events_events_manager_em_events_get', 10, 2);
		}
	}
	
	/*
		Add meta boxes to edit events page
	*/
	if ( is_admin() && defined( 'PMPRO_VERSION' ) ) {
		add_action( 'admin_menu', 'pmpro_events_events_manager_page_meta_wrapper' );
	}
}
add_action( 'init', 'pmpro_events_events_manager_init', 20 );

/**
 * Add pmpro content message for non-members before event details.
 * @since 1.0
 */
function pmpro_events_events_manager_em_event_output( $event_string, $post, $format, $target ) {
	global $current_user;
	if( function_exists( 'pmpro_hasMembershipLevel' ) && !pmpro_has_membership_access( $post->post_id ) && is_singular( array( 'event' ) ) && in_the_loop() ) {
		$hasaccess = pmpro_has_membership_access($post->post_id, NULL, true);
		if(is_array($hasaccess)) {
			//returned an array to give us the membership level values
			$post_membership_levels_ids = $hasaccess[1];
			$post_membership_levels_names = $hasaccess[2];
			$hasaccess = $hasaccess[0];
		}
		if(empty($post_membership_levels_ids)) {
			$post_membership_levels_ids = array();
		}
		if(empty($post_membership_levels_names)) {
			$post_membership_levels_names = array();
		}
	
		 //hide levels which don't allow signups by default
		if(!apply_filters("pmpro_membership_content_filter_disallowed_levels", false, $post_membership_levels_ids, $post_membership_levels_names)) {
			foreach($post_membership_levels_ids as $key=>$id) {
				//does this level allow registrations?
				$level_obj = pmpro_getLevel($id);
				if(!$level_obj->allow_signups) {
					unset($post_membership_levels_ids[$key]);
					unset($post_membership_levels_names[$key]);
				}
			}
		}
	
		$pmpro_content_message_pre = '<div class="pmpro_content_message">';
		$pmpro_content_message_post = '</div>';
		$content = '';
		$sr_search = array("!!levels!!", "!!referrer!!");
		$sr_replace = array(pmpro_implodeToEnglish($post_membership_levels_names), esc_url(site_url($_SERVER['REQUEST_URI'])));
		//get the correct message to show at the bottom
		if($current_user->ID) {
			//not a member
			$newcontent = apply_filters( 'pmpro_non_member_text_filter', stripslashes(pmpro_getOption( 'nonmembertext' )));
			$content .= $pmpro_content_message_pre . str_replace($sr_search, $sr_replace, $newcontent) . $pmpro_content_message_post;
		} else {
			//not logged in!
			$newcontent = apply_filters( 'pmpro_not_logged_in_text_filter', stripslashes(pmpro_getOption( 'notloggedintext' )));
			$content .= $pmpro_content_message_pre . str_replace($sr_search, $sr_replace, $newcontent) . $pmpro_content_message_post;
		}
		$event_string = $event_string . $content;
	}
	return $event_string;
}
add_action( 'em_event_output', 'pmpro_events_events_manager_em_event_output', 1, 4 );

/**
 * Hide booking form and replace with the pmpro content message for non-members.
 * @since 1.0
 */
function pmpro_events_events_manager_output_placeholder( $replace, $EM_Event, $result ) {
	global $wp_query, $wp_rewrite, $post, $current_user;
	if( function_exists( 'pmpro_hasMembershipLevel' ) && !pmpro_has_membership_access( $post->post_id ) ) {
		$hasaccess = pmpro_has_membership_access($post->post_id, NULL, true);		
		if(is_array($hasaccess)) {
			//returned an array to give us the membership level values
			$post_membership_levels_ids = $hasaccess[1];
			$post_membership_levels_names = $hasaccess[2];
			$hasaccess = $hasaccess[0];
		}
		switch( $result ) {
			case '#_BOOKINGFORM':
				if(empty($hasaccess)) {
					$replace = '';	
					break;	
				}
		}
	}
	return $replace;
}
add_filter( 'em_event_output_placeholder', 'pmpro_events_events_manager_output_placeholder', 1, 3 );

/**
 * Hide member events from non-members.
 * @since 1.0
 */
function pmpro_events_events_manager_template_redirect() {
	global $post;	
	if(!is_admin() && isset($post->post_type) && ($post->post_type == "event" || $post->post_type == "event-recurring") && !pmpro_has_membership_access()) {
		wp_redirect(pmpro_url("levels"));
		exit;
	}
}

/**
 * Hide member content from searches.
 * @since 1.0
 */
function pmpro_events_events_manager_em_events_get($events, $args) {
	//don't do anything in the admin
	if(is_admin()) {
		return $events;
	}
	
	//make sure PMPro is activated
	if( !function_exists( 'pmpro_hasMembershipLevel' ) ) {
		return $events;
	}
	   
	$newevents = array();
	foreach($events as $event) {
		 if( pmpro_has_membership_access( $event->post_id ) ) {
			$newevents[] = $event;
		}
	}	
	
	return $newevents;
}

/**
 * Remove template parts from Events Manager for non-members.
 * @since 1.0
 * @return boolean $hasaccess returns the current access for a user for an event.
 */
function pmpro_events_events_manager_has_access( $hasaccess, $post, $user, $levels ) {

	if ( ! is_admin() && is_single() && ! $hasaccess ) {
		remove_filter( 'the_content', array( 'EM_Event_Post','the_content' ) );
		add_filter( 'em_event_output', 'pmpro_events_events_manager_event_output', 10, 4);
	}

	return $hasaccess;

}
add_filter( 'pmpro_has_membership_access_filter_event', 'pmpro_events_events_manager_has_access', 10, 4 );

/**
 * Only return the event's title for non-members.
 * @since 1.0
 * @todo if this is not called, the PMPro restricted content message appends to the event's title.
 * @return object $content->post_title The events title.
 */
function pmpro_events_events_manager_event_output( $event_string, $content, $format, $target ) {
	return $content->post_title;
}

/**
 * Hide excerpt for non-members if set in Paid Memberships Pro Advanced settings. 
 * @since 1.0
 * @return string The excerpt string.
 */
function pmpro_events_events_manager_hide_excerpts( $result, $event, $placeholder, $target='html' ) {
	
	if ( function_exists( 'pmpro_getOption' ) ) {
		$showexcerpts = pmpro_getOption( "showexcerpts" );
	
		if( in_array($placeholder, array("#_EXCERPT",'#_EVENTEXCERPT','#_EVENTEXCERPTCUT', "#_LOCATIONEXCERPT")) && $target == 'html' && !pmpro_has_membership_access( $event->ID ) && '1' !== $showexcerpts ){
			$result = '';
		}
	}
	
	return $result;
}
add_filter('em_category_output_placeholder','pmpro_events_events_manager_hide_excerpts',1,4);
add_filter('em_event_output_placeholder','pmpro_events_events_manager_hide_excerpts',1,4);
add_filter('em_location_output_placeholder','pmpro_events_events_manager_hide_excerpts',1,4);

/**
 * Filter the Calendar page to hide events for non-members.
 * @since 1.0
 * @return array $event The event array object.
 */
function pmpro_events_events_manager_filter_calendar_page( $event ) {

	if ( function_exists( 'pmpro_getOption' ) ) {		
		$filterqueries = pmpro_getOption("filterqueries");

		// Filter events from calendar page if the member doesn't meet the requirements.
		if ( ! pmpro_has_membership_access( $event['post_id'] ) && ! empty( $filterqueries ) ) {
			unset( $event );
			$event = null;
		}
	}
	
	return $event;
}
add_filter( 'em_calendar_output_loop_start', 'pmpro_events_events_manager_filter_calendar_page', 10, 1 );

/**
 * Add a new column "Requires Membership" to the all events view to show required levels.
 *
 * @since 1.0
 */
function pmpro_events_events_manager_requires_membership_columns_head( $defaults ) {
	if ( defined( 'PMPRO_VERSION' ) ) {
		$defaults['requires_membership'] = 'Requires Membership';
	}
    return $defaults;
}

/**
 * Get the column data for the "Requires Membership" custom column.
 *
 * @since 1.0
 */
function pmpro_events_events_manager_requires_membership_columns_content( $column_name, $post_ID ) {
	if ( $column_name == 'requires_membership' ) {
	    global $membership_levels, $wpdb;
		$post_levels = $wpdb->get_col("SELECT membership_id FROM {$wpdb->pmpro_memberships_pages} WHERE page_id = '{$post_ID}'");
		$protected_levels = array();
		foreach ( $membership_levels as $level ) {
			if ( in_array( $level->id, $post_levels ) ) {
				$protected_levels[] = $level->name;
			}
		}
		if ( ! empty( $protected_levels ) ) {
			echo wp_kses_post( implode( ', ', $protected_levels) );
		} else {
			echo '&mdash;';
		}
	}
}
add_filter( 'manage_event_posts_columns', 'pmpro_events_events_manager_requires_membership_columns_head' );
add_action( 'manage_event_posts_custom_column', 'pmpro_events_events_manager_requires_membership_columns_content', 10, 2 );

/**
 * Apply membership requirements to recurring event posts.
 *
 * @param bool      $save_ok   Save OK?
 * @param \EM_Event $event     Event
 * @param array     $event_ids Event IDs
 * @param array     $post_ids  Post IDs
 * @return bool     $save_ok
 */
function pmpro_events_events_manager_em_event_save_events($save_ok, $event, $event_ids, $post_ids) {
	global $wpdb;

	if ( empty ( $event->post_id ) ) {
		return $save_ok;
	}

	// fetch membership requirements for main event entry
	$membership_requirements = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->pmpro_memberships_pages} WHERE page_id = %s",
			$event->post_id
		)
	);
	if ( empty( $membership_requirements ) ) {
		return $save_ok;
	}

	// remove all memberships for the individual event posts
	$deletion_query = sprintf(
		"DELETE FROM {$wpdb->pmpro_memberships_pages} WHERE page_id IN (%s)",
		implode( ",", $post_ids )
	);
	$wpdb->query( $deletion_query );

	// prepare a bulk insert since we may have up to hundreds of recurring events
	$inserts = array();
	foreach( $post_ids as $event_post_id ) {
		foreach( $membership_requirements as $membership_requirement ) {
			$inserts[] = $wpdb->prepare(
				"('%s', '%s')",
				intval( $membership_requirement->membership_id ),
				intval( $event_post_id )
			);
		}
	}

	$inserts_sql = "INSERT INTO {$wpdb->pmpro_memberships_pages} (membership_id, page_id) VALUES " . implode( ',', $inserts );
	$wpdb->query( $inserts_sql );

	return $save_ok;
}
add_filter( 'em_event_save_events', 'pmpro_events_events_manager_em_event_save_events', 10, 4 );
