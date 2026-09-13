<?php
/**
 * The pmpro_event post type and its meta.
 *
 * @since 2.0
 */

/**
 * Get the meta fields registered on the pmpro_event post type.
 *
 * Fields flagged as 'computed' are derived on save and should not be edited
 * directly — anything written to them is overwritten. A field can override the
 * default sanitize callback for its type with a 'sanitize' key.
 *
 * @since 2.0
 *
 * @return array The meta fields, keyed by meta key.
 */
function pmpro_events_get_meta_fields() {
	$fields = array(
		'pmpro_event_start'             => array( 'type' => 'string' ),
		'pmpro_event_start_utc'         => array( 'type' => 'string', 'computed' => true ),
		'pmpro_event_end'               => array( 'type' => 'string' ),
		'pmpro_event_end_utc'           => array( 'type' => 'string', 'computed' => true ),
		'pmpro_event_timezone'          => array( 'type' => 'string' ),
		'pmpro_event_all_day'           => array( 'type' => 'boolean' ),
		'pmpro_event_duration_minutes'  => array( 'type' => 'integer', 'computed' => true ),
		'pmpro_event_capacity'          => array( 'type' => 'integer' ),
		'pmpro_event_has_location'      => array( 'type' => 'boolean' ),
		'pmpro_event_location_type'     => array( 'type' => 'string' ),
		'pmpro_event_venue_name'        => array( 'type' => 'string' ),
		// An address is multi-line, so it can't go through sanitize_text_field.
		'pmpro_event_venue_address'     => array( 'type' => 'string', 'sanitize' => 'sanitize_textarea_field' ),
		'pmpro_event_virtual_url'       => array( 'type' => 'string', 'sanitize' => 'sanitize_url' ),
		'pmpro_event_has_registration'  => array( 'type' => 'boolean' ),
		'pmpro_event_uuid'              => array( 'type' => 'string', 'computed' => true ),
	);

	return apply_filters( 'pmpro_events_meta_fields', $fields );
}

/**
 * Register the pmpro_event post type.
 *
 * @since 2.0
 */
function pmpro_events_register_post_type() {
	$singular = pmpro_events_get_label( 'singular' );
	$plural   = pmpro_events_get_label( 'plural' );

	$singular_lower = pmpro_events_get_label( 'singular_lowercase' );
	$plural_lower   = pmpro_events_get_label( 'plural_lowercase' );

	$labels = array(
		'name'                  => $plural,
		'singular_name'         => $singular,
		'menu_name'             => $plural,
		/* translators: %s: the plural event label, e.g. "Events". */
		'all_items'             => sprintf( _x( 'All %s', 'plural event label', 'pmpro-events' ), $plural ),
		'add_new'               => __( 'Add New', 'pmpro-events' ),
		/* translators: %s: the singular event label, e.g. "Event". */
		'add_new_item'          => sprintf( _x( 'Add New %s', 'singular event label', 'pmpro-events' ), $singular ),
		/* translators: %s: the singular event label, e.g. "Event". */
		'edit_item'             => sprintf( _x( 'Edit %s', 'singular event label', 'pmpro-events' ), $singular ),
		/* translators: %s: the singular event label, e.g. "Event". */
		'new_item'              => sprintf( _x( 'New %s', 'singular event label', 'pmpro-events' ), $singular ),
		/* translators: %s: the singular event label, e.g. "Event". */
		'view_item'             => sprintf( _x( 'View %s', 'singular event label', 'pmpro-events' ), $singular ),
		/* translators: %s: the plural event label, e.g. "Events". */
		'view_items'            => sprintf( _x( 'View %s', 'plural event label', 'pmpro-events' ), $plural ),
		/* translators: %s: the plural event label, e.g. "Events". */
		'search_items'          => sprintf( _x( 'Search %s', 'plural event label', 'pmpro-events' ), $plural ),
		/* translators: %s: the plural event label, lowercased, e.g. "events". */
		'not_found'             => sprintf( _x( 'No %s found.', 'plural event label', 'pmpro-events' ), $plural_lower ),
		/* translators: %s: the plural event label, lowercased, e.g. "events". */
		'not_found_in_trash'    => sprintf( _x( 'No %s found in Trash.', 'plural event label', 'pmpro-events' ), $plural_lower ),
		/* translators: %s: the singular event label, e.g. "Event". */
		'archives'              => sprintf( _x( '%s Archives', 'singular event label', 'pmpro-events' ), $singular ),
		/* translators: %s: the singular event label, e.g. "Event". */
		'featured_image'        => sprintf( _x( '%s Image', 'singular event label', 'pmpro-events' ), $singular ),
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		'set_featured_image'    => sprintf( _x( 'Set %s image', 'singular event label', 'pmpro-events' ), $singular_lower ),
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		'remove_featured_image' => sprintf( _x( 'Remove %s image', 'singular event label', 'pmpro-events' ), $singular_lower ),
		/* translators: %s: the singular event label, e.g. "Event". */
		'item_published'        => sprintf( _x( '%s published.', 'singular event label', 'pmpro-events' ), $singular ),
		/* translators: %s: the singular event label, e.g. "Event". */
		'item_updated'          => sprintf( _x( '%s updated.', 'singular event label', 'pmpro-events' ), $singular ),
	);

	$args = array(
		'labels'       => $labels,
		'public'       => true,
		'show_ui'      => true,
		'show_in_menu' => true,
		'show_in_rest' => true,
		'menu_position' => 25,
		'menu_icon'    => 'dashicons-calendar-alt',
		// 'custom-fields' is required for the meta below to appear in the REST
		// schema. Without it the sidebar panels have nothing to bind to and the
		// editor silently discards every meta value on save.
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
		// When an Events page is assigned under Memberships > Settings > Pages,
		// that page is the events landing page. Drop the post type archive so
		// the page — typically itself at /events/ — isn't shadowed by the
		// archive rewrite rule.
		'has_archive'  => pmpro_events_get_events_page_id() ? false : 'events',
		'rewrite'      => array( 'slug' => 'event', 'with_front' => false ),
		'capability_type' => 'post',
	);

	/**
	 * Filter the arguments used to register the pmpro_event post type.
	 *
	 * @since 2.0
	 *
	 * @param array $args The register_post_type() arguments.
	 */
	$args = apply_filters( 'pmpro_events_register_post_type_args', $args );

	register_post_type( PMProEvents_Event::POST_TYPE, $args );
}
add_action( 'init', 'pmpro_events_register_post_type' );

/**
 * Register the event category taxonomy.
 *
 * Deliberately not public: categories exist to organize events in the admin
 * and to scope the Upcoming Events block to a series, not to create term
 * archive pages. Frontend archives can be enabled later without breaking
 * anything; taking them away again couldn't be.
 *
 * @since 2.0
 */
function pmpro_events_register_taxonomy() {
	$singular = pmpro_events_get_label( 'singular' );

	$labels = array(
		/* translators: %s: the singular event label, e.g. "Event". */
		'name'          => sprintf( _x( '%s Categories', 'singular event label', 'pmpro-events' ), $singular ),
		/* translators: %s: the singular event label, e.g. "Event". */
		'singular_name' => sprintf( _x( '%s Category', 'singular event label', 'pmpro-events' ), $singular ),
	);

	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => false,
		'show_ui'           => true,
		'show_in_rest'      => true,
		'show_admin_column' => true,
	);

	/**
	 * Filter the arguments used to register the event category taxonomy.
	 *
	 * @since 2.0
	 *
	 * @param array $args The register_taxonomy() arguments.
	 */
	$args = apply_filters( 'pmpro_events_register_taxonomy_args', $args );

	register_taxonomy( PMProEvents_Event::TAXONOMY, PMProEvents_Event::POST_TYPE, $args );
}
add_action( 'init', 'pmpro_events_register_taxonomy' );

/**
 * Register the event meta so the block editor sidebar panels can bind to it.
 *
 * @since 2.0
 */
function pmpro_events_register_post_meta() {
	foreach ( pmpro_events_get_meta_fields() as $key => $field ) {
		register_post_meta(
			PMProEvents_Event::POST_TYPE,
			$key,
			array(
				'type'              => $field['type'],
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => empty( $field['sanitize'] ) ? 'pmpro_events_sanitize_meta_' . $field['type'] : $field['sanitize'],
				'auth_callback'     => function() {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'pmpro_events_register_post_meta' );

/**
 * Strip meta the requester shouldn't see from REST API responses.
 *
 * The event meta is in the REST schema so the block editor can edit it, but
 * that also makes it readable at /wp/v2/pmpro_event by anyone. PMPro filters
 * content and excerpt in REST responses through the_content, but never touches
 * meta — so without this, the meeting URL the template only reveals to
 * registered attendees, and the venue address of a members-only event, would
 * both be one anonymous API request away.
 *
 * @since 2.0
 *
 * @param WP_REST_Response $response The response.
 * @param WP_Post          $post     The event.
 * @return WP_REST_Response The filtered response.
 */
function pmpro_events_rest_prepare_event( $response, $post ) {
	// Users who can edit the event need the real values in the editor.
	if ( current_user_can( 'edit_post', $post->ID ) ) {
		return $response;
	}

	$data = $response->get_data();

	if ( empty( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
		return $response;
	}

	$event = new PMProEvents_Event( $post->ID );

	// The meeting URL follows the same rule as the event page: registered
	// attendees only, or anyone who can view the event when registration is off.
	if ( isset( $data['meta']['pmpro_event_virtual_url'] ) && ! $event->user_can_view_links() ) {
		$data['meta']['pmpro_event_virtual_url'] = '';
	}

	// A restricted event only teases its date and venue name on the frontend;
	// the full address stays behind the membership requirement.
	if ( isset( $data['meta']['pmpro_event_venue_address'] ) && ! $event->user_can_access() ) {
		$data['meta']['pmpro_event_venue_address'] = '';
	}

	$response->set_data( $data );

	return $response;
}
add_filter( 'rest_prepare_' . PMProEvents_Event::POST_TYPE, 'pmpro_events_rest_prepare_event', 10, 2 );

/**
 * Sanitize a string meta value.
 *
 * @since 2.0
 *
 * @param mixed $value The value to sanitize.
 * @return string The sanitized value.
 */
function pmpro_events_sanitize_meta_string( $value ) {
	return sanitize_text_field( (string) $value );
}

/**
 * Sanitize an integer meta value.
 *
 * @since 2.0
 *
 * @param mixed $value The value to sanitize.
 * @return int The sanitized value.
 */
function pmpro_events_sanitize_meta_integer( $value ) {
	return max( 0, (int) $value );
}

/**
 * Sanitize a boolean meta value.
 *
 * @since 2.0
 *
 * @param mixed $value The value to sanitize.
 * @return bool The sanitized value.
 */
function pmpro_events_sanitize_meta_boolean( $value ) {
	return (bool) $value;
}

/**
 * Add the event post type to PMPro's list of restrictable post types.
 *
 * This is what gives the event edit screen PMPro's native Require Membership
 * panel, so restricting an event works exactly like restricting a page.
 *
 * @since 2.0
 *
 * @param array $post_types The restrictable post types.
 * @return array The filtered post types.
 */
function pmpro_events_restrictable_post_types( $post_types ) {
	$post_types[] = PMProEvents_Event::POST_TYPE;

	return $post_types;
}
add_filter( 'pmpro_restrictable_post_types', 'pmpro_events_restrictable_post_types' );

/**
 * Add the event post type to the list of post types treated as events.
 *
 * Used by the no-access message so that native events get event-specific
 * wording rather than the generic content message.
 *
 * @since 2.0
 *
 * @param array $slugs The event post type slugs.
 * @return array The filtered slugs.
 */
function pmpro_events_supports_event_slug( $slugs ) {
	$slugs[] = PMProEvents_Event::POST_TYPE;

	return $slugs;
}
add_filter( 'pmpro_events_supports_event_slug', 'pmpro_events_supports_event_slug' );

/**
 * Compute the derived meta for an event.
 *
 * The UTC dates exist so that events can be sorted and filtered by when they
 * actually happen, regardless of the timezone they were entered in.
 *
 * @since 2.0
 *
 * @param int $post_id The event ID.
 */
function pmpro_events_compute_derived_meta( $post_id ) {
	$post_id = (int) $post_id;

	if ( PMProEvents_Event::POST_TYPE !== get_post_type( $post_id ) ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	$start    = (string) get_post_meta( $post_id, 'pmpro_event_start', true );
	$end      = (string) get_post_meta( $post_id, 'pmpro_event_end', true );
	$timezone = (string) get_post_meta( $post_id, 'pmpro_event_timezone', true );
	$all_day  = (bool) get_post_meta( $post_id, 'pmpro_event_all_day', true );

	// An all-day event covers whole days in its own timezone.
	if ( $all_day ) {
		if ( ! empty( $start ) ) {
			$start = substr( $start, 0, 10 ) . ' 00:00:00';
			update_post_meta( $post_id, 'pmpro_event_start', $start );
		}
		if ( ! empty( $end ) ) {
			$end = substr( $end, 0, 10 ) . ' 23:59:59';
			update_post_meta( $post_id, 'pmpro_event_end', $end );
		}
	}

	$start_utc = pmpro_events_local_to_utc( $start, $timezone );
	$end_utc   = pmpro_events_local_to_utc( $end, $timezone );

	// An end date before the start date is not usable, so drop it.
	if ( ! empty( $start_utc ) && ! empty( $end_utc ) && $end_utc < $start_utc ) {
		$end     = '';
		$end_utc = '';
		update_post_meta( $post_id, 'pmpro_event_end', '' );
	}

	update_post_meta( $post_id, 'pmpro_event_start_utc', $start_utc );
	update_post_meta( $post_id, 'pmpro_event_end_utc', $end_utc );

	// Duration in minutes, for display and calendar invites.
	$duration = 0;
	if ( ! empty( $start_utc ) && ! empty( $end_utc ) ) {
		$duration = (int) round( ( strtotime( $end_utc . ' UTC' ) - strtotime( $start_utc . ' UTC' ) ) / 60 );
	}
	update_post_meta( $post_id, 'pmpro_event_duration_minutes', max( 0, $duration ) );

	// Every event gets a stable UUID for calendar invites.
	if ( empty( get_post_meta( $post_id, 'pmpro_event_uuid', true ) ) ) {
		update_post_meta( $post_id, 'pmpro_event_uuid', wp_generate_uuid4() );
	}

	// Capacity is meaningless without registration.
	if ( ! get_post_meta( $post_id, 'pmpro_event_has_registration', true ) ) {
		update_post_meta( $post_id, 'pmpro_event_capacity', 0 );
	}

	/**
	 * Fires after an event's derived meta has been recalculated.
	 *
	 * @since 2.0
	 *
	 * @param int $post_id The event ID.
	 */
	do_action( 'pmpro_events_derived_meta_updated', $post_id );
}

/**
 * Recalculate derived meta after a block editor save.
 *
 * The block editor writes meta over the REST API after the post itself is
 * saved, so save_post runs too early to see the new values. This hook fires
 * once the meta has been written.
 *
 * @since 2.0
 *
 * @param WP_Post $post The event that was saved.
 */
function pmpro_events_rest_after_insert( $post ) {
	pmpro_events_compute_derived_meta( $post->ID );
}
add_action( 'rest_after_insert_' . PMProEvents_Event::POST_TYPE, 'pmpro_events_rest_after_insert' );

/**
 * Recalculate derived meta after a non-REST save, such as Quick Edit or wp_insert_post().
 *
 * @since 2.0
 *
 * @param int $post_id The event ID.
 */
function pmpro_events_save_post( $post_id ) {
	// REST saves are handled by pmpro_events_rest_after_insert() instead.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	pmpro_events_compute_derived_meta( $post_id );
}
add_action( 'save_post_' . PMProEvents_Event::POST_TYPE, 'pmpro_events_save_post' );
