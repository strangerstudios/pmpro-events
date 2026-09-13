<?php
/**
 * Admin list table columns for the events post type.
 *
 * @since 2.0
 */

/**
 * Add the event columns to the events list table.
 *
 * @since 2.0
 *
 * @param array $columns The existing columns.
 * @return array The filtered columns.
 */
function pmpro_events_manage_posts_columns( $columns ) {
	$new_columns = array();

	foreach ( $columns as $key => $label ) {
		$new_columns[ $key ] = $label;

		// Slot our columns in right after the title.
		if ( 'title' === $key ) {
			$new_columns['pmpro_event_start']         = __( 'Start Date', 'pmpro-events' );
			$new_columns['pmpro_event_capacity']      = __( 'Capacity', 'pmpro-events' );
			$new_columns['pmpro_event_registrations'] = __( 'Registrations', 'pmpro-events' );
		}
	}

	return $new_columns;
}
add_filter( 'manage_' . PMProEvents_Event::POST_TYPE . '_posts_columns', 'pmpro_events_manage_posts_columns' );

/**
 * Render the event columns.
 *
 * Each row renders three of our columns, so the event is built once and reused
 * rather than re-reading every meta field three times over.
 *
 * @since 2.0
 *
 * @param string $column  The column being rendered.
 * @param int    $post_id The event ID.
 */
function pmpro_events_manage_posts_custom_column( $column, $post_id ) {
	static $current_id = 0;
	static $event = null;

	if ( 0 !== strpos( $column, 'pmpro_event_' ) ) {
		return;
	}

	if ( $current_id !== $post_id || null === $event ) {
		$current_id = $post_id;
		$event      = new PMProEvents_Event( $post_id );
	}

	if ( ! $event->exists() ) {
		return;
	}

	switch ( $column ) {
		case 'pmpro_event_start':
			$start = $event->get_formatted_date( 'start' );
			echo esc_html( empty( $start ) ? '—' : $start );
			break;

		case 'pmpro_event_capacity':
			if ( ! $event->has_registration() ) {
				echo '—';
			} else {
				$capacity = $event->get_capacity();
				echo esc_html( empty( $capacity ) ? __( 'Unlimited', 'pmpro-events' ) : number_format_i18n( $capacity ) );
			}
			break;

		case 'pmpro_event_registrations':
			if ( ! $event->has_registration() ) {
				echo '—';
				break;
			}

			$count    = $event->get_registration_count();
			$capacity = $event->get_capacity();
			$label    = empty( $capacity )
				? number_format_i18n( $count )
				: sprintf( '%s / %s', number_format_i18n( $count ), number_format_i18n( $capacity ) );

			printf(
				'<a href="%s">%s</a>',
				esc_url( pmpro_events_get_registrations_url( $post_id ) ),
				esc_html( $label )
			);
			break;
	}
}
add_action( 'manage_' . PMProEvents_Event::POST_TYPE . '_posts_custom_column', 'pmpro_events_manage_posts_custom_column', 10, 2 );

/**
 * Make the start date column sortable.
 *
 * @since 2.0
 *
 * @param array $columns The sortable columns.
 * @return array The filtered columns.
 */
function pmpro_events_sortable_columns( $columns ) {
	$columns['pmpro_event_start'] = 'pmpro_event_start';

	return $columns;
}
add_filter( 'manage_edit-' . PMProEvents_Event::POST_TYPE . '_sortable_columns', 'pmpro_events_sortable_columns' );

/**
 * Sort the events list table by the computed UTC start date.
 *
 * @since 2.0
 *
 * @param WP_Query $query The query being run.
 */
function pmpro_events_sort_by_start_date( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( PMProEvents_Event::POST_TYPE !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( 'pmpro_event_start' !== $query->get( 'orderby' ) ) {
		return;
	}

	$query->set( 'meta_key', 'pmpro_event_start_utc' );
	$query->set( 'meta_type', 'DATETIME' );
	$query->set( 'orderby', 'meta_value' );
}
add_action( 'pre_get_posts', 'pmpro_events_sort_by_start_date' );

/**
 * Add a Registrations link to the row actions for each event.
 *
 * @since 2.0
 *
 * @param array   $actions The row actions.
 * @param WP_Post $post    The event.
 * @return array The filtered row actions.
 */
function pmpro_events_row_actions( $actions, $post ) {
	if ( PMProEvents_Event::POST_TYPE !== $post->post_type ) {
		return $actions;
	}

	if ( ! get_post_meta( $post->ID, 'pmpro_event_has_registration', true ) ) {
		return $actions;
	}

	$actions['pmpro_event_registrations'] = sprintf(
		'<a href="%s">%s</a>',
		esc_url( pmpro_events_get_registrations_url( $post->ID ) ),
		esc_html__( 'Registrations', 'pmpro-events' )
	);

	return $actions;
}
add_filter( 'post_row_actions', 'pmpro_events_row_actions', 10, 2 );
