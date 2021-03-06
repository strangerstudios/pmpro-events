<?php
/**
 * Add Membership Levels box to The Events Calendar CPTs
 * @since 1.0
 */
function pmpro_events_tribe_events_page_meta_wrapper( ) {
	add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'tribe_events', 'side', 'high' );
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
 * Hide content if user doesn't have access to the event. Only affects single views.
 * @since 1.1
 */
function pmpro_events_tribe_events_hide_post_meta( $html, $file, $name, $template ) {
	global $post;

	if ( has_blocks( $post->ID ) ) {
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
