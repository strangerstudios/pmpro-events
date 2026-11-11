<?php

/**
 * Add metabox to Sugar Calendar  CPT.
 * @since 1.0
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

	// Only change the page when checking the event being viewed, not other events listed on it.
	if ( ! empty( $post->post_type ) && $post->post_type == 'sc_event' && ! $hasaccess && $post->ID == get_queried_object_id() ) {
		// Filter to show event details to non-members for restricted events.
		if ( apply_filters( 'pmpro_events_sc_hide_event_meta', true ) ) {
			// Remove the event details.
			remove_action( 'sc_before_event_content', 'sc_add_event_details' );

			// Remove the ticketing.
			remove_action( 'sc_after_event_content', 'Sugar_Calendar\AddOn\Ticketing\Frontend\Single\display' );
			remove_action( 'wp_footer', 'Sugar_Calendar\AddOn\Ticketing\Frontend\Modal\display', 10 );

			// Remove the map.
			if ( function_exists( 'sugar_calendar' ) && method_exists( sugar_calendar(), 'get_common_features' ) ) {
				remove_action( 'sc_after_event_content', array( sugar_calendar()->get_common_features()->get_feature( 'GoogleMaps' ), 'show_map' ) );
			}
		}
	}

	return $hasaccess;
}
add_filter( 'pmpro_has_membership_access_filter', 'pmpro_events_sugar_calendar_has_membership_access', 10, 4 );

/**
 * Check whether the current user can see the protected details of an event.
 *
 * @since 1.6.2
 *
 * @param int $post_id The event's post ID.
 * @return bool True if the user can see the event's details.
 */
function pmpro_events_sc_can_view_details( $post_id ) {
	static $cache = array();

	if ( ! function_exists( 'pmpro_has_membership_access' ) || empty( $post_id ) ) {
		return true;
	}

	$user_id = get_current_user_id();
	if ( ! isset( $cache[ $user_id ][ $post_id ] ) ) {
		// Users who can edit the event can already see its details in the admin.
		$cache[ $user_id ][ $post_id ] = current_user_can( 'edit_post', $post_id ) || pmpro_has_membership_access( $post_id );
	}

	return $cache[ $user_id ][ $post_id ];
}

/**
 * Hide the description and location of restricted events wherever Sugar Calendar loads them
 * (calendars, event lists, popovers, iCal downloads and integrations).
 * Also removes restricted events from Calendar/List View if PMPro is set to filter searches and archives.
 * @since 1.0
 */
function pmpro_events_sc_filter_events_archive( $events ) {

	if ( ! function_exists( 'pmpro_has_membership_access' ) ) {
		return $events;
	}

	// Don't do anything in the admin or in scheduled tasks, but do filter AJAX requests.
	if ( ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() ) {
		return $events;
	}

	$filterqueries = get_option( 'pmpro_filterqueries' );

	foreach ( $events as $key => $value ) {
		$post_id = (int) $value->object_id;

		if ( pmpro_events_sc_can_view_details( $post_id ) ) {
			continue;
		}

		if ( ! empty( $filterqueries ) && $post_id !== get_queried_object_id() ) {
			unset( $events[$key] ); // remove from the events array if the user doesn't have access.
			continue;
		}

		// Clone the event so that a cached copy is not changed.
		$events[ $key ]           = clone $value;
		$events[ $key ]->content  = '';
		$events[ $key ]->location = '';
	}
	return $events;
}
add_action( 'sc_the_events', 'pmpro_events_sc_filter_events_archive' );

/**
 * Hide the excerpt of restricted posts in calendar popovers and event lists, unless PMPro is set to show excerpts.
 *
 * @since 1.6.2
 *
 * @param string $excerpt The excerpt.
 * @param int    $post_id The post ID.
 * @return string The filtered excerpt.
 */
function pmpro_events_sc_get_event_excerpt( $excerpt, $post_id ) {
	if ( get_option( 'pmpro_showexcerpts' ) || pmpro_events_sc_can_view_details( $post_id ) ) {
		return $excerpt;
	}

	return '';
}
add_filter( 'sugar_calendar_helpers_get_event_excerpt', 'pmpro_events_sc_get_event_excerpt', 10, 2 );

/**
 * Don't output structured data for restricted events, since it includes the description, price and join link.
 *
 * @since 1.6.2
 *
 * @param bool   $enabled Whether to output structured data.
 * @param object $event   The event.
 * @return bool Whether to output structured data.
 */
function pmpro_events_sc_structured_data_enabled( $enabled, $event ) {
	return $enabled && pmpro_events_sc_can_view_details( $event->object_id );
}
add_filter( 'sugar_calendar_structured_data_enabled', 'pmpro_events_sc_structured_data_enabled', 10, 2 );

/**
 * Hide the protected details of restricted events in Sugar Calendar abilities.
 *
 * @since 1.6.2
 *
 * @param array  $out   The event data.
 * @param object $event The event.
 * @return array The filtered event data.
 */
function pmpro_events_sc_abilities_formatter_event( $out, $event ) {
	if ( pmpro_events_sc_can_view_details( $event->object_id ) ) {
		return $out;
	}

	$out['content']  = '';
	$out['location'] = '';
	$out['venue_id'] = 0;
	$out['venue']    = '';
	if ( ! get_option( 'pmpro_showexcerpts' ) ) {
		$out['excerpt'] = '';
	}

	return $out;
}
add_filter( 'sugar_calendar_integrations_abilities_formatter_event', 'pmpro_events_sc_abilities_formatter_event', 10, 2 );

/**
 * Don't let non-members get ticket prices or buy tickets for restricted events.
 *
 * @since 1.6.2
 */
function pmpro_events_sc_block_ticket_checkout() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Only reading the event ID to block the request.
	$event_id = 0;
	if ( isset( $_POST['sc_et_action'] ) && 'checkout' === $_POST['sc_et_action'] && ! empty( $_POST['sc_et_event_id'] ) ) {
		$event_id = absint( $_POST['sc_et_event_id'] );
	} elseif ( wp_doing_ajax() && isset( $_REQUEST['action'] ) && in_array( $_REQUEST['action'], array( 'sc_et_get_price', 'sc_et_stripe_fetch_data' ), true ) && ! empty( $_POST['event_id'] ) ) {
		$event_id = absint( $_POST['event_id'] );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	if ( empty( $event_id ) || ! function_exists( 'sugar_calendar_get_event' ) ) {
		return;
	}

	$event = sugar_calendar_get_event( $event_id );
	if ( ! empty( $event->object_id ) && ! pmpro_events_sc_can_view_details( $event->object_id ) ) {
		wp_die( esc_html__( 'You must be a member to access this event.', 'pmpro-events' ), '', array( 'response' => 403 ) );
	}
}
add_action( 'init', 'pmpro_events_sc_block_ticket_checkout', 5 );
