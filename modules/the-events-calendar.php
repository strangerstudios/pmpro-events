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

	// Only integrate if we have the repository tracked.
	if ( empty( $pmpro_events_tribe_repository ) ) {
		return;
	}

	// Join the membership pages table to reference restrictions on.
	$join = "
	    LEFT JOIN `{$wpdb->pmpro_memberships_pages}` AS `pmpro_mp`
            ON `pmpro_mp`.`page_id` = `{$wpdb->posts}`.`ID`
	";

	$pmpro_events_tribe_repository->filter_query->join( $join, 'pmpro-events-access-join' );

	// The default is to always show any posts without restrictions.
	$where = 'pmpro_mp.membership_id IS NULL';

	// If user is logged in, check for their membership levels.
	if ( is_user_logged_in() ) {
		$membership_ids = pmpro_getMembershipLevelsForUser( get_current_user_id() );

		// If the user has membership levels, allow showing those restricted posts too.
		if ( ! empty( $membership_ids ) ) {
			$membership_ids = array_map( 'absint', wp_list_pluck( $membership_ids, 'id' ) );

			$where .= ' OR pmpro_mp.membership_id IN ( ' . implode( ', ', $membership_ids ) . ' )';
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
	if ( function_exists( 'pmpro_getOption' ) ) {
		$filterqueries = pmpro_getOption( "filterqueries" );
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

	// Figure out recurring events.
	if ( function_exists( 'tribe_is_recurring_event' ) ) {
		// See if recurring event (occurence) is restricted or not on the parent post.
		if ( ! is_admin() && is_single() && tribe_is_recurring_event() ) {

			// Bail if the user already doesn't have access.
			if ( ! $hasaccess ) {
				return $hasaccess;
			}

			$main_post_id = $post->_tec_occurrence->post_id;
			if ( ! pmpro_events_tribe_get_parent_event_access( $main_post_id ) ) {
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
			<?
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

	$showexcerpts = apply_filters( 'pmpro_events_tribe_events_show_excerpts', pmpro_getOption( "showexcerpts" ), $post );

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
