<?php
/**
 * Duplicate an event from the admin.
 *
 * Recurring series, repeat workshops, and "same event, new date" are the
 * common case for a site running events, so the Events list and the event edit
 * screen offer a Duplicate action. The copy is created as a draft with the
 * source event's content, meta, categories, featured image, and membership
 * restrictions, so the site owner only needs to change what differs.
 *
 * @since 2.1
 */

/**
 * Get the meta keys that are never copied to a duplicate.
 *
 * Derived meta is recalculated for the copy, the UUID has to be unique for
 * calendar invites to treat the copy as a separate event, and editor locks
 * belong to the source post.
 *
 * @since 2.1
 *
 * @return string[] The meta keys to skip.
 */
function pmpro_events_get_duplicate_skipped_meta_keys() {
	$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date' );

	foreach ( pmpro_events_get_meta_fields() as $key => $field ) {
		if ( ! empty( $field['computed'] ) ) {
			$skip[] = $key;
		}
	}

	/**
	 * Filter the meta keys that are not copied when an event is duplicated.
	 *
	 * @since 2.1
	 *
	 * @param string[] $skip The meta keys to skip.
	 */
	return (array) apply_filters( 'pmpro_events_duplicate_skipped_meta_keys', $skip );
}

/**
 * Duplicate an event.
 *
 * The copy is always a draft owned by the current user, titled after the
 * source with "(Copy)" appended. Registrations are not copied: they belong to
 * the occurrence people signed up for, not to the event as a template.
 *
 * @since 2.1
 *
 * @param int $event_id The event to duplicate.
 * @return int|WP_Error The ID of the new event, or a WP_Error on failure.
 */
function pmpro_events_duplicate_event( $event_id ) {
	$source = get_post( (int) $event_id );

	if ( empty( $source ) || PMProEvents_Event::POST_TYPE !== $source->post_type ) {
		return new WP_Error( 'pmpro_events_invalid_event', __( 'That event could not be found.', 'pmpro-events' ) );
	}

	$new_post = array(
		'post_type'    => $source->post_type,
		'post_status'  => 'draft',
		/* translators: %s: the title of the event being duplicated. */
		'post_title'   => sprintf( _x( '%s (Copy)', 'title of a duplicated event', 'pmpro-events' ), $source->post_title ),
		'post_content' => $source->post_content,
		'post_excerpt' => $source->post_excerpt,
		'post_author'  => get_current_user_id() ? get_current_user_id() : $source->post_author,
		'post_parent'  => $source->post_parent,
		'menu_order'   => $source->menu_order,
		'comment_status' => $source->comment_status,
		'ping_status'  => $source->ping_status,
	);

	/**
	 * Filter the post array used to create a duplicated event.
	 *
	 * @since 2.1
	 *
	 * @param array   $new_post The arguments passed to wp_insert_post().
	 * @param WP_Post $source   The event being duplicated.
	 */
	$new_post = apply_filters( 'pmpro_events_duplicate_post_args', $new_post, $source );

	$new_id = wp_insert_post( wp_slash( $new_post ), true );

	if ( is_wp_error( $new_id ) ) {
		return $new_id;
	}

	// Meta, minus the keys that must stay unique to the source. The insert above
	// already ran the save_post hooks, which write defaults such as a zero
	// capacity, so each key is cleared before the source values are added or
	// a single-value key would end up with two rows and read back the default.
	$skip = pmpro_events_get_duplicate_skipped_meta_keys();
	foreach ( (array) get_post_meta( $source->ID ) as $key => $values ) {
		if ( in_array( $key, $skip, true ) ) {
			continue;
		}
		delete_post_meta( $new_id, $key );
		foreach ( (array) $values as $value ) {
			add_post_meta( $new_id, $key, maybe_unserialize( $value ) );
		}
	}

	// Every taxonomy attached to events, not just the plugin's own categories.
	foreach ( get_object_taxonomies( $source->post_type ) as $taxonomy ) {
		$terms = wp_get_object_terms( $source->ID, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			wp_set_object_terms( $new_id, $terms, $taxonomy );
		}
	}

	// PMPro stores Require Membership settings in its own table, not in meta.
	pmpro_events_copy_level_restrictions( $source->ID, $new_id );

	// Recalculate the UTC dates and duration from the copied meta, and give the copy its own UUID.
	pmpro_events_compute_derived_meta( $new_id );

	/**
	 * Fires after an event has been duplicated.
	 *
	 * @since 2.1
	 *
	 * @param int $new_id    The ID of the new event.
	 * @param int $source_id The ID of the event that was duplicated.
	 */
	do_action( 'pmpro_events_event_duplicated', $new_id, $source->ID );

	return $new_id;
}

/**
 * Copy PMPro's per-post level restrictions from one post to another.
 *
 * @since 2.1
 *
 * @param int $source_id The post to copy restrictions from.
 * @param int $target_id The post to copy restrictions to.
 */
function pmpro_events_copy_level_restrictions( $source_id, $target_id ) {
	global $wpdb;

	if ( empty( $wpdb->pmpro_memberships_pages ) ) {
		return;
	}

	$level_ids = $wpdb->get_col( $wpdb->prepare(
		"SELECT membership_id FROM {$wpdb->pmpro_memberships_pages} WHERE page_id = %d",
		(int) $source_id
	) );

	if ( empty( $level_ids ) ) {
		return;
	}

	$level_ids = array_map( 'intval', $level_ids );

	if ( function_exists( 'pmpro_update_post_level_restrictions' ) ) {
		pmpro_update_post_level_restrictions( (int) $target_id, $level_ids );
		return;
	}

	// PMPro before 3.6.
	foreach ( $level_ids as $level_id ) {
		$wpdb->insert(
			$wpdb->pmpro_memberships_pages,
			array( 'membership_id' => $level_id, 'page_id' => (int) $target_id ),
			array( '%d', '%d' )
		);
	}
}

/**
 * Can the current user duplicate this event?
 *
 * Duplicating reads the source and creates a new post, so it needs both.
 *
 * @since 2.1
 *
 * @param int $event_id The event to check.
 * @return bool Whether the user can duplicate it.
 */
function pmpro_events_current_user_can_duplicate( $event_id ) {
	$post_type = get_post_type_object( PMProEvents_Event::POST_TYPE );

	if ( empty( $post_type ) ) {
		return false;
	}

	return current_user_can( 'edit_post', (int) $event_id ) && current_user_can( $post_type->cap->create_posts );
}

/**
 * Get the nonced URL that duplicates an event.
 *
 * @since 2.1
 *
 * @param int $event_id The event to duplicate.
 * @return string The URL.
 */
function pmpro_events_get_duplicate_url( $event_id ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'   => 'pmpro_events_duplicate',
				'event_id' => (int) $event_id,
			),
			admin_url( 'admin-post.php' )
		),
		'pmpro_events_duplicate_' . (int) $event_id,
		'pmpro_events_nonce'
	);
}

/**
 * Handle the Duplicate action and send the user to the copy's edit screen.
 *
 * @since 2.1
 */
function pmpro_events_admin_duplicate() {
	$event_id = empty( $_GET['event_id'] ) ? 0 : (int) $_GET['event_id'];

	check_admin_referer( 'pmpro_events_duplicate_' . $event_id, 'pmpro_events_nonce' );

	if ( empty( $event_id ) || ! pmpro_events_current_user_can_duplicate( $event_id ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to duplicate this event.', 'pmpro-events' ), 403 );
	}

	$new_id = pmpro_events_duplicate_event( $event_id );

	if ( is_wp_error( $new_id ) ) {
		wp_safe_redirect( add_query_arg(
			array(
				'post_type'           => PMProEvents_Event::POST_TYPE,
				'pmpro_events_notice' => 'duplicate_failed',
			),
			admin_url( 'edit.php' )
		) );
		exit;
	}

	wp_safe_redirect( add_query_arg( 'pmpro_events_notice', 'duplicated', get_edit_post_link( $new_id, 'raw' ) ) );
	exit;
}
add_action( 'admin_post_pmpro_events_duplicate', 'pmpro_events_admin_duplicate' );

/**
 * Add a Duplicate link to each event's row actions.
 *
 * @since 2.1
 *
 * @param array   $actions The row actions.
 * @param WP_Post $post    The post.
 * @return array The filtered row actions.
 */
function pmpro_events_duplicate_row_action( $actions, $post ) {
	if ( PMProEvents_Event::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
		return $actions;
	}

	if ( ! pmpro_events_current_user_can_duplicate( $post->ID ) ) {
		return $actions;
	}

	$actions['pmpro_event_duplicate'] = sprintf(
		'<a href="%s" aria-label="%s">%s</a>',
		esc_url( pmpro_events_get_duplicate_url( $post->ID ) ),
		/* translators: %s: the event title. */
		esc_attr( sprintf( __( 'Duplicate &#8220;%s&#8221;', 'pmpro-events' ), get_the_title( $post ) ) ),
		esc_html__( 'Duplicate', 'pmpro-events' )
	);

	return $actions;
}
add_filter( 'post_row_actions', 'pmpro_events_duplicate_row_action', 20, 2 );

/**
 * Add a Duplicate bulk action to the Events list.
 *
 * @since 2.1
 *
 * @param array $actions The bulk actions.
 * @return array The filtered bulk actions.
 */
function pmpro_events_duplicate_bulk_action( $actions ) {
	$post_type = get_post_type_object( PMProEvents_Event::POST_TYPE );

	if ( ! empty( $post_type ) && current_user_can( $post_type->cap->create_posts ) ) {
		$actions['pmpro_events_duplicate'] = __( 'Duplicate', 'pmpro-events' );
	}

	return $actions;
}
add_filter( 'bulk_actions-edit-' . PMProEvents_Event::POST_TYPE, 'pmpro_events_duplicate_bulk_action' );

/**
 * Duplicate every selected event and return to the list.
 *
 * @since 2.1
 *
 * @param string $redirect_to The URL to redirect to.
 * @param string $action      The bulk action being run.
 * @param int[]  $post_ids    The selected events.
 * @return string The redirect URL.
 */
function pmpro_events_handle_duplicate_bulk_action( $redirect_to, $action, $post_ids ) {
	if ( 'pmpro_events_duplicate' !== $action ) {
		return $redirect_to;
	}

	$count = 0;
	foreach ( (array) $post_ids as $post_id ) {
		if ( ! pmpro_events_current_user_can_duplicate( $post_id ) ) {
			continue;
		}
		if ( ! is_wp_error( pmpro_events_duplicate_event( $post_id ) ) ) {
			$count++;
		}
	}

	$redirect_to = remove_query_arg( array( 'pmpro_events_notice', 'pmpro_events_duplicated' ), $redirect_to );

	return add_query_arg(
		array(
			'pmpro_events_notice'     => 'bulk_duplicated',
			'pmpro_events_duplicated' => $count,
			'post_status'             => 'draft',
		),
		$redirect_to
	);
}
add_filter( 'handle_bulk_actions-edit-' . PMProEvents_Event::POST_TYPE, 'pmpro_events_handle_duplicate_bulk_action', 10, 3 );

/**
 * Add a Duplicate item to the admin bar while editing an event.
 *
 * The block editor has no submit box to hang a link on, so the admin bar is
 * where the action lives on the edit screen itself.
 *
 * @since 2.1
 *
 * @param WP_Admin_Bar $wp_admin_bar The admin bar.
 */
function pmpro_events_duplicate_admin_bar( $wp_admin_bar ) {
	if ( ! is_admin() ) {
		return;
	}

	$screen = get_current_screen();

	if ( empty( $screen ) || 'post' !== $screen->base || PMProEvents_Event::POST_TYPE !== $screen->post_type ) {
		return;
	}

	$post = get_post();

	if ( empty( $post ) || 'auto-draft' === $post->post_status || ! pmpro_events_current_user_can_duplicate( $post->ID ) ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'pmpro-events-duplicate',
			/* translators: %s: the singular event label, e.g. "Event". */
			'title' => sprintf( _x( 'Duplicate %s', 'singular event label', 'pmpro-events' ), pmpro_events_get_label( 'singular' ) ),
			'href'  => pmpro_events_get_duplicate_url( $post->ID ),
		)
	);
}
add_action( 'admin_bar_menu', 'pmpro_events_duplicate_admin_bar', 81 );

/**
 * Show the outcome of a duplicate on the edit screen and the Events list.
 *
 * @since 2.1
 */
function pmpro_events_duplicate_admin_notices() {
	if ( empty( $_GET['pmpro_events_notice'] ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( empty( $screen ) || PMProEvents_Event::POST_TYPE !== $screen->post_type ) {
		return;
	}

	$singular       = pmpro_events_get_label( 'singular' );
	$singular_lower = pmpro_events_get_label( 'singular_lowercase' );
	$plural_lower   = pmpro_events_get_label( 'plural_lowercase' );

	switch ( sanitize_key( wp_unslash( $_GET['pmpro_events_notice'] ) ) ) {
		case 'duplicated':
			if ( 'post' !== $screen->base ) {
				return;
			}
			/* translators: %s: the singular event label, e.g. "Event". */
			$text = sprintf( __( '%s duplicated. You are now editing the copy: update the title, dates, and details, then publish it when it is ready.', 'pmpro-events' ), $singular );
			$type = 'success';
			break;

		case 'bulk_duplicated':
			if ( 'edit' !== $screen->base ) {
				return;
			}
			$count = empty( $_GET['pmpro_events_duplicated'] ) ? 0 : (int) $_GET['pmpro_events_duplicated'];
			/* translators: 1: number of events duplicated, 2: the plural event label, lowercased, e.g. "events". */
			$text = sprintf( _n( '%1$s %2$s duplicated as a draft.', '%1$s %2$s duplicated as drafts.', $count, 'pmpro-events' ), number_format_i18n( $count ), 1 === $count ? $singular_lower : $plural_lower );
			$type = $count > 0 ? 'success' : 'error';
			break;

		case 'duplicate_failed':
			if ( 'edit' !== $screen->base ) {
				return;
			}
			/* translators: %s: the singular event label, lowercased, e.g. "event". */
			$text = sprintf( __( 'The %s could not be duplicated. Please try again.', 'pmpro-events' ), $singular_lower );
			$type = 'error';
			break;

		default:
			return;
	}

	printf(
		'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
		esc_attr( $type ),
		esc_html( $text )
	);
}
add_action( 'admin_notices', 'pmpro_events_duplicate_admin_notices' );
