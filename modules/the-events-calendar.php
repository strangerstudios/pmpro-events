<?php
/**
 * Add Membership Levels box to The Events Calendar CPTs
 * @since 1.0
 */
function pmpro_events_tribe_events_page_meta_wrapper( ) {
	add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'tribe_events', 'side', 'high' );
}

/**
 * Hook in before getting the posts from WP_Query to automatically add the membership level check to the posts queried.
 *
 * @since 1.3
 *
 * @param WP_Query $query The query object.
 */
function pmpro_events_tribe_events_repository_handle_posts( WP_Query $query ) {
	/** @var Tribe__Events__Repositories__Event|null $pmpro_events_tribe_repository */
	global $pmpro_events_tribe_repository, $wpdb;

	// Avoid non-unique table aliases.
	static $func_run_index = 1;
	$mp_table_name = 'pmpro_mp_' . $func_run_index;
	$func_run_index++;


	// Only integrate if we have the repository tracked.
	if ( empty( $pmpro_events_tribe_repository ) ) {
		return;
	}

	// Join the membership pages table to reference restrictions on.
	$join = "
	    LEFT JOIN `{$wpdb->pmpro_memberships_pages}` AS `{$mp_table_name}`
            ON `{$mp_table_name}`.`page_id` = `{$wpdb->posts}`.`ID`
	";

	$pmpro_events_tribe_repository->filter_query->join( $join, 'pmpro-events-access-join' );

	// The default is to always show any posts without restrictions.
	$where = $mp_table_name . '.membership_id IS NULL';

	// If user is logged in, check for their membership levels.
	if ( is_user_logged_in() ) {
		$membership_ids = pmpro_getMembershipLevelsForUser( get_current_user_id() );

		// If the user has membership levels, allow showing those restricted posts too.
		if ( ! empty( $membership_ids ) ) {
			$membership_ids = array_map( 'absint', wp_list_pluck( $membership_ids, 'id' ) );

			$where .= ' OR ' . $mp_table_name . '.membership_id IN ( ' . implode( ', ', $membership_ids ) . ' )';
		}
	}

	$pmpro_events_tribe_repository->filter_query->where( $where, 'pmpro-events-access-filter' );
}

/**
 * The global variable to keep track of the Events ORM object when it's active.
 */
global $pmpro_events_tribe_repository;

/**
 * Hook into the repository object (TEC ORM) when query arguments are set up to store the object for future integration in other hooks.
 *
 * @since 1.3
 *
 * @param array                                                $query_args The query args to use when fetching events.
 * @param WP_Query                                             $query      The query object.
 * @param Tribe__Events__Repositories__Event|Tribe__Repository $repository The repository object.
 *
 * @return array The query args to use when fetching events.
 */
function pmpro_events_tribe_events_track_repository_from_query_args( $query_args, $query, $repository ) {
	global $pmpro_events_tribe_repository;

	// Only set the repository if it's the one we want.
	if ( $repository instanceof Tribe__Events__Repositories__Event ) {
		$pmpro_events_tribe_repository = $repository;
	}

	return $query_args;
}

/**
 * Stuff to run on init
 * @since 1.0
 */
function pmpro_events_tribe_events_init() {

	// Add filters for tribe events if filterqueries option is set in PMPro.
	if ( function_exists( 'pmpro_init' ) ) {
		$filterqueries = get_option( "pmpro_filterqueries" );
		if ( ! empty( $filterqueries ) ) {
			add_filter( 'tribe_get_events', 'pmpro_events_tribe_events_get_events', 10, 3 );
			add_filter( 'tribe_events_get_current_month_day', 'pmpro_events_tribe_events_get_current_month_day' );

			// TEC ORM integration.
			add_filter( 'tribe_repository_events_query_args', 'pmpro_events_tribe_events_track_repository_from_query_args', 10, 3 );
			add_action( 'tribe_repository_events_pre_count_posts', 'pmpro_events_tribe_events_repository_handle_posts' );
			add_action( 'tribe_repository_events_pre_found_posts', 'pmpro_events_tribe_events_repository_handle_posts' );
			add_action( 'tribe_repository_events_pre_get_posts', 'pmpro_events_tribe_events_repository_handle_posts' );
			add_action( 'tribe_repository_events_pre_first_post', 'pmpro_events_tribe_events_repository_handle_posts' );
			add_action( 'tribe_repository_events_pre_last_post', 'pmpro_events_tribe_events_repository_handle_posts' );
			add_action( 'tribe_repository_events_pre_get_ids_for_posts', 'pmpro_events_tribe_events_repository_handle_posts' );
		}
	}

	// Add meta boxes to edit events page
	if( is_admin() && defined( 'PMPRO_VERSION' ) ) {
		add_action( 'admin_menu', 'pmpro_events_tribe_events_page_meta_wrapper' );
	}
}
add_action( 'init', 'pmpro_events_tribe_events_init', 20 );

/**
 * Hide member content from searches via PMPro's pre_get_posts filter.
 * @since 1.0
 */
function pmpro_events_tribe_events_pmpro_search_filter_post_types( $post_types ) {
	$post_types[] = 'tribe_events';

	return $post_types;
}
add_filter( 'pmpro_search_filter_post_types', 'pmpro_events_tribe_events_pmpro_search_filter_post_types' );

/**
 * Hide member content from other event lists/etc
 * @since 1.0
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

/**
 * The tribe_events_get_current_month_day function is also used when generating the calendar view.
 * We need to filter the count to keep events from showing up there.
 * @since 1.0
 */
function pmpro_events_tribe_events_get_current_month_day($day) {

	if($day['total_events'] > 0 && !empty($day['events']->posts)) {
		$day['total_events'] = count($day['events']->posts);
	}

	return $day;
}

/**
 * Remove all Tribe Events Post Meta/Data for non-members.
 * @since 1.0
 */
function pmpro_events_tribe_events_has_access( $hasaccess, $post, $user, $levels ){
	global $wpdb;

	// Only hide sections of the page when checking the event being viewed, not other events listed on it.
	if ( ! is_admin() && is_single() && ! $hasaccess && $post->ID == get_queried_object_id() ) {

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

	// Figure out recurring events.
	if ( function_exists( 'tribe_is_recurring_event' ) ) {
		// See if recurring event (occurence) is restricted or not on the parent post.
		if ( ! is_admin() && is_single() && tribe_is_recurring_event() ) {

			// Bail if the user already doesn't have access.
			if ( ! $hasaccess ) {
				return $hasaccess;
			}

			$main_post_id = isset( $post->_tec_occurrence->post_id ) ? $post->_tec_occurrence->post_id : 0;
			if ( ! empty( $main_post_id ) && ! pmpro_events_tribe_get_parent_event_access( $main_post_id ) ) {
				$hasaccess = false;
			}
		}
	}

	return $hasaccess;
}
add_filter( 'pmpro_has_membership_access_filter_tribe_events', 'pmpro_events_tribe_events_has_access', 10, 4 );

// Let's make a note about the main POST ID here.
function pmpro_events_tribe_events_add_require_membership_message( $post ) {

	// Make sure that Events Calendar Pro is installed.
	if ( ! function_exists( 'tribe_is_recurring_event' ) ) {
		return;
	}

	// Show a notice about the main event setting.
	if ( tribe_is_recurring_event() ) {
		$parent_event_id = isset( tribe_get_event()->_tec_occurrence->post_id ) ? tribe_get_event()->_tec_occurrence->post_id : 0;

		if ( ! empty( $_REQUEST['post'] ) && ! empty( $parent_event_id ) ) {

			?>
				<style>
					#pmpro-memberships-checklist, #pmpro_page_meta p { display:none; }
				</style>
			<?php
			$parent_event_url = add_query_arg( array( 'post' => intval( $parent_event_id ), 'action' => 'edit' ), admin_url( 'post.php' ) );
			echo '<a href="' . esc_url( $parent_event_url ) . '">' . esc_html__( 'Edit the parent event for membership restrictions.', 'pmpro-events' ) . '</a>';

		}

	}

}
add_action( 'pmpro_after_require_membership_metabox', 'pmpro_events_tribe_events_add_require_membership_message', 10, 1 );

/**
 * Function to get membership access directly within the has_membership_access filter for parent events.
 *
 * @param int $post_id The post ID we need to query.
 * @param int $user_id The user ID we need to query.
 * @return bool $hasaccess Returns true or false whether or not the user has relevant levels.
 */
function pmpro_events_tribe_get_parent_event_access( $parent_id, $user_id = NULL ) {
	global $wpdb, $current_user;

	if ( empty( $user_id ) ) {
		$user_id = $current_user->ID;
	}

	// Get levels related to a post ID, and check to make sure the user has a relevant level.
	$membership_ids = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT membership_id FROM {$wpdb->pmpro_memberships_pages} WHERE page_id = %s",
			$parent_id
		),
		ARRAY_N
	);

	// No levels are required for the parent ID, let's just bail and assume they have access.
	if ( empty( $membership_ids ) ) {
		return true;
	}

	// Get all Post levels and convert them to an array so we may intersect these later.
	$post_levels = array();
	foreach( $membership_ids as $level) {
		$post_levels[] = $level[0];
	}

	// Get membership levels and convert them to an array so we may intersect these later.
	$memberships_for_user = pmpro_getMembershipLevelsForUser( $user_id );
	$members_levels = array();
	foreach( $memberships_for_user as $membership ) {
		$members_levels[] = $membership->id;
	}

	// If there is any overlap between the two arrays, assume they have access.
	if ( array_intersect( $post_levels, $members_levels ) ) {
		$hasaccess = true;
	} else {
		$hasaccess = false;
	}

	return $hasaccess;

}
/**
 * Hide content if user doesn't have access to the event. Only affects single views.
 * @since 1.1
 */
function pmpro_events_tribe_events_hide_post_meta( $html, $file, $name, $template ) {
	global $post;

	if ( $post && has_blocks( $post->ID ) ) {
		return $html;
	}

	if ( is_single() && get_post_type() === 'tribe_events' && ! pmpro_has_membership_access( $post->ID )) {
		$html = false;
	}

	return apply_filters( 'pmpro_events_tribe_post_single_html', $html, $post );
}
add_filter( 'tribe_template_pre_html', 'pmpro_events_tribe_events_hide_post_meta', 10, 4 );

/**
 * This is called if the user does not have membership level.
 * Sets the template to none.
 * @since 1.0
 * @return a blank array.
 */
function pmpro_events_tribe_events_remove_post_meta_section( $templates, $slug, $name ) {
	$r = array();
	$r = apply_filters( 'pmpro_events_tribe_events_page_modules', $r, $templates, $slug, $name );
	return $r;
}

/**
 * This is called if the user does not have membership level. Requires Event Tickets Plugin to be installed.
 * Sets the template to none.
 * @since 1.0
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
function pmpro_events_tribe_events_excerpt_filter( $excerpt, $post ) {

	$showexcerpts = apply_filters( 'pmpro_events_tribe_events_show_excerpts', get_option( "pmpro_showexcerpts" ), $post );

	if ( pmpro_has_membership_access( $post->ID ) ) {
		$excerpt = get_the_excerpt( $post );
	} elseif ( $showexcerpts && !pmpro_has_membership_access( $post->ID ) ) {
		$excerpt = get_the_excerpt( $post );
	} else {
		$excerpt = '';
	}

	return $excerpt;
}
add_filter( 'tribe_events_get_the_excerpt', 'pmpro_events_tribe_events_excerpt_filter', 10, 2 );

/**
 * Add a new column "Requires Membership" to the all events view to show required levels.
 *
 * @since 1.0
 */
function pmpro_events_tribe_events_requires_membership_columns_head( $defaults ) {
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
function pmpro_events_tribe_events_requires_membership_columns_content( $column_name, $post_ID ) {
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
add_filter( 'manage_tribe_events_posts_columns', 'pmpro_events_tribe_events_requires_membership_columns_head' );
add_action( 'manage_tribe_events_posts_custom_column', 'pmpro_events_tribe_events_requires_membership_columns_content', 10, 2 );

/**
 * Check whether the current user can see the protected details of an event.
 *
 * Recurring event occurrences are checked against the event that holds the restriction.
 *
 * @since TBD
 *
 * @param int|WP_Post $event The event ID or post object.
 * @return bool True if the user can see the event's details.
 */
function pmpro_events_tribe_events_can_view_details( $event ) {
	static $cache = array();

	if ( ! function_exists( 'pmpro_has_membership_access' ) ) {
		return true;
	}

	$event_id = $event instanceof WP_Post ? $event->ID : (int) $event;
	$event_id = (int) apply_filters( 'tec_events_custom_tables_v1_normalize_occurrence_id', $event_id );
	$user_id  = get_current_user_id();

	if ( ! isset( $cache[ $user_id ][ $event_id ] ) ) {
		// Users who can edit the event can already see its details in the admin.
		$cache[ $user_id ][ $event_id ] = current_user_can( 'edit_post', $event_id ) || pmpro_has_membership_access( $event_id );
	}

	return $cache[ $user_id ][ $event_id ];
}

/**
 * Check whether excerpts of restricted events should be shown.
 *
 * @since TBD
 *
 * @param WP_Post|null $post The event post object.
 * @return bool True if excerpts should be shown.
 */
function pmpro_events_tribe_events_show_excerpts( $post = null ) {
	return (bool) apply_filters( 'pmpro_events_tribe_events_show_excerpts', get_option( 'pmpro_showexcerpts' ), $post );
}

/**
 * Hide the cost, venue, organizer and excerpt of restricted events in calendar views, widgets and blocks.
 *
 * @since TBD
 *
 * @param WP_Post $event The event post object, decorated by tribe_get_event().
 * @return WP_Post The filtered event post object.
 */
function pmpro_events_tribe_events_hide_event_properties( $event ) {
	if ( ! $event instanceof WP_Post || ( is_admin() && ! wp_doing_ajax() ) || pmpro_events_tribe_events_can_view_details( $event ) ) {
		return $event;
	}

	// Clone the event so that the object TEC caches for this request is not changed.
	$event = clone $event;

	$event->cost            = '';
	$event->venues          = new Tribe\Events\Collections\Lazy_Post_Collection( '__return_empty_array' );
	$event->organizers      = new Tribe\Events\Collections\Lazy_Post_Collection( '__return_empty_array' );
	$event->organizer_names = new Tribe\Utils\Lazy_Collection( '__return_empty_array' );

	if ( ! pmpro_events_tribe_events_show_excerpts( $event ) ) {
		$event->excerpt = new Tribe\Utils\Lazy_String( '__return_empty_string' );
	}

	return $event;
}
add_filter( 'tribe_get_event_after', 'pmpro_events_tribe_events_hide_event_properties', 20 );

/**
 * Don't cache calendar view HTML for logged-in users, since restricted event details differ by user.
 *
 * @since TBD
 *
 * @param bool $should_cache Whether the view HTML should be cached.
 * @return bool Whether the view HTML should be cached.
 */
function pmpro_events_tribe_events_should_cache_html( $should_cache ) {
	return $should_cache && ! is_user_logged_in();
}
add_filter( 'tribe_events_views_v2_should_cache_html', 'pmpro_events_tribe_events_should_cache_html' );

/**
 * Change the calendar view HTML cache key so that HTML cached before restricted event details were hidden is not reused.
 *
 * @since TBD
 *
 * @param string $cache_key The cache key.
 * @return string The filtered cache key.
 */
function pmpro_events_tribe_events_cache_html_key( $cache_key ) {
	return $cache_key . 'pmpro';
}
add_filter( 'tribe_events_views_v2_cache_html_key', 'pmpro_events_tribe_events_cache_html_key' );

/**
 * Hide the protected details of restricted events in the TEC REST API.
 *
 * @since TBD
 *
 * @param array   $data  The event data.
 * @param WP_Post $event The event post object.
 * @return array The filtered event data.
 */
function pmpro_events_tribe_events_rest_event_data( $data, $event ) {
	if ( pmpro_events_tribe_events_can_view_details( $event ) ) {
		return $data;
	}

	$data['description']   = '';
	$data['cost']          = '';
	$data['website']       = '';
	$data['venue']         = array();
	$data['organizer']     = array();
	$data['custom_fields'] = array();

	if ( isset( $data['cost_details'] ) ) {
		$data['cost_details']['values'] = array();
	}

	if ( isset( $data['virtual_url'] ) ) {
		$data['virtual_url'] = '';
	}

	if ( ! pmpro_events_tribe_events_show_excerpts( $event ) ) {
		$data['excerpt'] = '';
	}

	unset( $data['json_ld'] );

	return $data;
}
add_filter( 'tribe_rest_event_data', 'pmpro_events_tribe_events_rest_event_data', 10, 2 );

/**
 * Remove restricted events from the TEC REST API event list if PMPro is set to filter searches and archives.
 *
 * @since TBD
 *
 * @param array $data The event list data.
 * @return array The filtered event list data.
 */
function pmpro_events_tribe_events_rest_events_archive_data( $data ) {
	if ( empty( $data['events'] ) || ! get_option( 'pmpro_filterqueries' ) ) {
		return $data;
	}

	foreach ( $data['events'] as $key => $event ) {
		if ( ! pmpro_events_tribe_events_can_view_details( $event['id'] ) ) {
			unset( $data['events'][ $key ] );
		}
	}
	$data['events'] = array_values( $data['events'] );

	return $data;
}
add_filter( 'tribe_rest_events_archive_data', 'pmpro_events_tribe_events_rest_events_archive_data' );

/**
 * Don't let the TEC REST API venue and organizer lists reveal which venue or organizer a restricted event uses.
 *
 * @since TBD
 *
 * @param WP_REST_Response|WP_Error|mixed $response The response to send. Usually empty at this point.
 * @param array                           $handler  The route handler.
 * @param WP_REST_Request                 $request  The request.
 * @return WP_REST_Response|WP_Error|mixed The response, or an error if the event is restricted.
 */
function pmpro_events_tribe_events_rest_linked_posts_by_event( $response, $handler, $request ) {
	$event_id = $request->get_param( 'event' );
	if ( empty( $event_id ) || ! is_numeric( $event_id ) || ! preg_match( '#^/(tribe/events/v1|tec/v1)/(venues|organizers)#i', $request->get_route() ) ) {
		return $response;
	}

	if ( ! pmpro_events_tribe_events_can_view_details( (int) $event_id ) ) {
		return new WP_Error( 'rest_forbidden', __( 'You must be a member to access this event.', 'pmpro-events' ), array( 'status' => rest_authorization_required_code() ) );
	}

	return $response;
}
add_filter( 'rest_request_before_callbacks', 'pmpro_events_tribe_events_rest_linked_posts_by_event', 10, 3 );

/**
 * Hide the protected details of restricted events in JSON-LD structured data.
 *
 * @since TBD
 *
 * @param object  $data The JSON-LD object for the event.
 * @param array   $args The arguments used to build the data.
 * @param WP_Post $post The event post object.
 * @return object The filtered JSON-LD object.
 */
function pmpro_events_tribe_events_json_ld_event_object( $data, $args, $post ) {
	if ( pmpro_events_tribe_events_can_view_details( $post ) ) {
		return $data;
	}

	unset( $data->location, $data->organizer, $data->offers );

	if ( ! pmpro_events_tribe_events_show_excerpts( $post ) ) {
		unset( $data->description );
	}

	return $data;
}
add_filter( 'tribe_json_ld_event_object', 'pmpro_events_tribe_events_json_ld_event_object', 10, 3 );

/**
 * Hide the protected details of restricted events in iCal exports.
 *
 * @since TBD
 *
 * @param array   $item       The iCal lines for the event.
 * @param WP_Post $event_post The event post object.
 * @return array The filtered iCal lines.
 */
function pmpro_events_tribe_events_ical_feed_item( $item, $event_post ) {
	if ( pmpro_events_tribe_events_can_view_details( $event_post ) ) {
		return $item;
	}

	unset( $item['DESCRIPTION'], $item['LOCATION'], $item['GEO'], $item['X-APPLE-STRUCTURED-LOCATION'], $item['ORGANIZER'] );

	return $item;
}
add_filter( 'tribe_ical_feed_item', 'pmpro_events_tribe_events_ical_feed_item', 20, 2 );
