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
		Filter searches if PMPro Option to filter is set, and lists of events at a location.
	*/
	if(function_exists('pmpro_init')) {
		add_filter('em_events_get','pmpro_events_events_manager_em_events_get', 10, 2);
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

		$event_string = pmpro_get_no_access_message( $event_string, $post_membership_levels_ids, $post_membership_levels_names );
	}
	return $event_string;
}
add_action( 'em_event_output', 'pmpro_events_events_manager_em_event_output', 1, 4 );

/**
 * Hide booking form and replace with the pmpro content message for non-members.
 * @since 1.0
 */
function pmpro_events_events_manager_output_placeholder( $replace, $EM_Event, $result ) {
	if ( function_exists( 'pmpro_hasMembershipLevel' ) && ! pmpro_has_membership_access( $EM_Event->post_id ) ) {
		$hasaccess = pmpro_has_membership_access( $EM_Event->post_id, NULL, true );
		if ( is_array( $hasaccess ) ) {
			//returned an array to give us the membership level values
			$post_membership_levels_ids = $hasaccess[1];
			$post_membership_levels_names = $hasaccess[2];
			$hasaccess = $hasaccess[0];
		}
		switch ( $result ) {
			case '#_BOOKINGFORM':
				if ( empty( $hasaccess ) ) {
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
	//don't do anything in the admin, but do filter AJAX requests
	if( is_admin() && ! wp_doing_ajax() ) {
		return $events;
	}
	
	//make sure PMPro is activated
	if( !function_exists( 'pmpro_hasMembershipLevel' ) ) {
		return $events;
	}

	// Unless PMPro is set to filter searches, only filter lists of events at a location, which would reveal a restricted event's location.
	if ( ! get_option( 'pmpro_filterqueries' ) && ! array_filter( array_intersect_key( (array) $args, array_flip( array( 'location', 'location_id', 'has_location', 'town', 'state', 'country', 'region', 'near', 'geo' ) ) ) ) ) {
		return $events;
	}
	   
	$newevents = array();
	foreach($events as $event) {
		 if( pmpro_events_events_manager_can_view_details( $event ) ) {
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

	// Only change the page when checking the event being viewed, not other events listed on it.
	if ( ! is_admin() && is_single() && ! $hasaccess && $post->ID == get_queried_object_id() ) {
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
	
	if ( function_exists( 'pmpro_init' ) ) {
		$showexcerpts = get_option( "pmpro_showexcerpts" );
	
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

	if ( function_exists( 'pmpro_init' ) ) {		
		$filterqueries = get_option("pmpro_filterqueries");

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

/**
 * Check whether the current user can see the protected details of an event.
 *
 * @since TBD
 *
 * @param EM_Event $EM_Event The event.
 * @return bool True if the user can see the event's details.
 */
function pmpro_events_events_manager_can_view_details( $EM_Event ) {
	static $cache = array();

	if ( ! function_exists( 'pmpro_has_membership_access' ) || empty( $EM_Event->post_id ) ) {
		return true;
	}

	$user_id = get_current_user_id();
	if ( ! isset( $cache[ $user_id ][ $EM_Event->post_id ] ) ) {
		// Users who can edit the event can already see its details in the admin.
		$cache[ $user_id ][ $EM_Event->post_id ] = current_user_can( 'edit_post', $EM_Event->post_id ) || pmpro_has_membership_access( $EM_Event->post_id );
	}

	return $cache[ $user_id ][ $EM_Event->post_id ];
}

/**
 * Hide the protected details of restricted events everywhere Events Manager outputs them:
 * lists, calendars, search results, widgets, the events page, RSS and iCal feeds.
 *
 * @since TBD
 *
 * @param string   $replace          The placeholder's output.
 * @param EM_Event $EM_Event         The event.
 * @param string   $full_result      The full placeholder, including any arguments.
 * @param string   $target           The output target, such as html, rss, ical or email.
 * @param array    $placeholder_atts The placeholder name and its arguments.
 * @return string The filtered placeholder output.
 */
function pmpro_events_events_manager_hide_placeholders( $replace, $EM_Event, $full_result, $target = 'html', $placeholder_atts = array() ) {
	// Skip admin screens and booking emails (raw is used for email subjects). Emails go to people who already booked, and reminders are sent by cron without a logged-in user.
	if ( ( is_admin() && ! wp_doing_ajax() ) || in_array( $target, array( 'email', 'raw' ), true ) || wp_doing_cron() || ! $EM_Event instanceof EM_Event || pmpro_events_events_manager_can_view_details( $EM_Event ) ) {
		return $replace;
	}

	$placeholder = ! empty( $placeholder_atts[0] ) ? $placeholder_atts[0] : $full_result;

	// Never show the full event notes. Show a trimmed excerpt only if PMPro is set to show excerpts.
	if ( in_array( $placeholder, array( '#_EVENTNOTES', '#_NOTES' ), true ) ) {
		return '';
	}
	if ( in_array( $placeholder, array( '#_EVENTEXCERPT', '#_EVENTEXCERPTCUT', '#_EXCERPT' ), true ) ) {
		if ( ! get_option( 'pmpro_showexcerpts' ) ) {
			return '';
		}

		$excerpt_args   = ! empty( $placeholder_atts[1] ) ? explode( ',', $placeholder_atts[1] ) : array();
		$excerpt_length = ! empty( $excerpt_args[0] ) && is_numeric( $excerpt_args[0] ) ? (int) $excerpt_args[0] : 55;
		$excerpt_more   = ! empty( $excerpt_args[1] ) ? $excerpt_args[1] : apply_filters( 'em_excerpt_more', ' [...]' );
		return $EM_Event->output_excerpt( $excerpt_length, $excerpt_more, true );
	}

	// Hide the location, contact, custom attributes, attendees, prices, bookings and calendar links (which include the event's details).
	// Location placeholders that don't start with #_LOCATION are also passed to the location's output, such as #_LATT and #_NEXTEVENTS.
	$hidden_prefixes = array( '#_LOCATION', '#_EVENTLOCATION', '#_ADDRESS', '#_TOWN', '#_MAP', '#_LATT', '#_DESCRIPTION', '#_NEXTEVENTS', '#_PASTEVENTS', '#_ALLEVENTS', '#_CONTACT', '#_ATT', '#_BOOK', '#_ADDBOOKING', '#_REMOVEBOOKING', '#_EVENTPRICE', '#_AVAILABLE', '#_RESERVED', '#_UNAVAILABLE', '#_PENDING', '#_SPACES', '#_SEATS', '#_EVENTGCAL', '#_EVENTOUTLOOK', '#_EVENTOFFICE365', '#_EVENTADDTOCALENDAR' );
	foreach ( $hidden_prefixes as $hidden_prefix ) {
		if ( 0 === strpos( $placeholder, $hidden_prefix ) ) {
			return '';
		}
	}

	return $replace;
}
add_filter( 'em_event_output_placeholder', 'pmpro_events_events_manager_hide_placeholders', 999, 5 );

/**
 * Hide conditional sections that depend on the protected details of restricted events,
 * such as {has_location}, {fully_booked}, {is_free} or {has_att_name}, along with their opposites.
 *
 * @since TBD
 *
 * @param bool     $show_condition Whether to show the conditional section.
 * @param string   $condition      The condition name.
 * @param string   $full_match     The full conditional section.
 * @param EM_Event $EM_Event       The event.
 * @return bool Whether to show the conditional section.
 */
function pmpro_events_events_manager_hide_conditions( $show_condition, $condition, $full_match, $EM_Event ) {
	if ( ! $show_condition || ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() || ! $EM_Event instanceof EM_Event || pmpro_events_events_manager_can_view_details( $EM_Event ) ) {
		return $show_condition;
	}

	$hidden_conditions = array( 'has_bookings', 'no_bookings', 'has_spaces', 'fully_booked', 'bookings_open', 'bookings_closed', 'is_free', 'is_free_now', 'not_free', 'not_free_now', 'has_location', 'no_location', 'has_location_venue', 'no_location_venue', 'has_event_location', 'no_event_location' );
	if ( in_array( $condition, $hidden_conditions, true ) || 0 === strpos( $condition, 'has_att_' ) || 0 === strpos( $condition, 'no_att_' ) ) {
		return false;
	}

	return $show_condition;
}
add_filter( 'em_event_output_show_condition', 'pmpro_events_events_manager_hide_conditions', 10, 4 );

/**
 * Remove the location and organizer of restricted events from iCal feeds.
 *
 * @since TBD
 *
 * @param string   $output   The iCal output for the event.
 * @param EM_Event $EM_Event The event.
 * @return string The filtered iCal output.
 */
function pmpro_events_events_manager_ical_event_output( $output, $EM_Event ) {
	if ( pmpro_events_events_manager_can_view_details( $EM_Event ) ) {
		return $output;
	}

	// Remove each line along with any folded continuation lines, which start with a space.
	return preg_replace( '/\r?\n(LOCATION|GEO|X-APPLE-STRUCTURED-LOCATION|ORGANIZER)[;:][^\r\n]*(\r?\n [^\r\n]*)*/', '', $output );
}
add_filter( 'em_ical_event_output_content', 'pmpro_events_events_manager_ical_event_output', 10, 2 );

/**
 * Don't load the booking form of a restricted event via AJAX for non-members.
 *
 * @since TBD
 *
 * @param EM_Event $EM_Event The event.
 */
function pmpro_events_events_manager_ajax_output_booking_form( $EM_Event ) {
	if ( ! pmpro_events_events_manager_can_view_details( $EM_Event ) ) {
		wp_die( esc_html__( 'You must be a member to access this event.', 'pmpro-events' ), '', array( 'response' => 403 ) );
	}
}
add_action( 'em_ajax_output_booking_form', 'pmpro_events_events_manager_ajax_output_booking_form' );

/**
 * Don't let non-members book restricted events.
 *
 * @since TBD
 *
 * @param bool       $valid      Whether the booking is valid.
 * @param EM_Booking $EM_Booking The booking.
 * @return bool Whether the booking is valid.
 */
function pmpro_events_events_manager_booking_validate( $valid, $EM_Booking ) {
	// Let booking managers add bookings for others.
	if ( ! $valid || current_user_can( 'manage_others_bookings' ) ) {
		return $valid;
	}

	if ( ! pmpro_events_events_manager_can_view_details( $EM_Booking->get_event() ) ) {
		$EM_Booking->add_error( __( 'You must be a member to access this event.', 'pmpro-events' ) );
		return false;
	}

	return $valid;
}
add_filter( 'em_booking_validate', 'pmpro_events_events_manager_booking_validate', 10, 2 );

/**
 * Hide the protected details of restricted events in the Events Manager API.
 *
 * @since TBD
 *
 * @param array    $api      The event data.
 * @param EM_Event $EM_Event The event.
 * @param string   $context  The request context.
 * @return array The filtered event data.
 */
function pmpro_events_events_manager_api_prepare_event( $api, $EM_Event, $context = 'view' ) {
	// Don't trust the edit context here, since event lists accept it from anyone. Users who can edit the event can view its details.
	if ( pmpro_events_events_manager_can_view_details( $EM_Event ) ) {
		return $api;
	}

	$api['content']  = '';
	$api['location'] = false;
	unset( $api['owner'], $api['bookings'], $api['location_type'] );

	return $api;
}
add_filter( 'em_api_prepare_event', 'pmpro_events_events_manager_api_prepare_event', 10, 3 );

/**
 * Hide the tickets of restricted events in the Events Manager API availability data.
 *
 * @since TBD
 *
 * @param array    $availability The availability data.
 * @param EM_Event $EM_Event     The event.
 * @return array The filtered availability data.
 */
function pmpro_events_events_manager_api_event_availability( $availability, $EM_Event ) {
	if ( ! pmpro_events_events_manager_can_view_details( $EM_Event ) ) {
		$availability['tickets'] = array();
	}

	return $availability;
}
add_filter( 'em_api_event_availability', 'pmpro_events_events_manager_api_event_availability', 10, 2 );

/**
 * Hide the price and description of tickets for restricted events in the Events Manager API.
 *
 * @since TBD
 *
 * @param array     $api       The ticket data.
 * @param EM_Ticket $EM_Ticket The ticket.
 * @param string    $context   The request context.
 * @return array The filtered ticket data.
 */
function pmpro_events_events_manager_api_prepare_ticket( $api, $EM_Ticket, $context = 'view' ) {
	if ( pmpro_events_events_manager_can_view_details( $EM_Ticket->get_event() ) ) {
		return $api;
	}

	$api['description'] = '';
	unset( $api['price'] );

	return $api;
}
add_filter( 'em_api_prepare_ticket', 'pmpro_events_events_manager_api_prepare_ticket', 10, 3 );

/**
 * Hide the tickets, prices and spaces of restricted events in the Events Manager API booking requirements.
 *
 * @since TBD
 *
 * @param array    $payload  The booking requirements.
 * @param EM_Event $EM_Event The event.
 * @return array The filtered booking requirements.
 */
function pmpro_events_events_manager_api_booking_requirements( $payload, $EM_Event ) {
	if ( ! pmpro_events_events_manager_can_view_details( $EM_Event ) ) {
		$payload['tickets'] = array();
		unset( $payload['spaces_available'], $payload['payment'] );
	}

	return $payload;
}
add_filter( 'em_api_booking_requirements', 'pmpro_events_events_manager_api_booking_requirements', 10, 2 );
