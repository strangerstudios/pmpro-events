<?php
/**
 * Custom post type registration.
 *
 * @package PMPro_Events
 * @since 2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the pmpro_event post type.
 *
 * @since 2.0
 */
function pmpro_events_register_post_type() {
	$singular = pmpro_events_get_singular_name();
	$plural   = pmpro_events_get_plural_name();

	$labels = array(
		'name'               => $plural,
		'singular_name'      => $singular,
		'add_new'            => __( 'Add New', 'pmpro-events' ),
		/* translators: %s: singular event name */
		'add_new_item'       => sprintf( __( 'Add New %s', 'pmpro-events' ), $singular ),
		/* translators: %s: singular event name */
		'edit_item'          => sprintf( __( 'Edit %s', 'pmpro-events' ), $singular ),
		/* translators: %s: singular event name */
		'new_item'           => sprintf( __( 'New %s', 'pmpro-events' ), $singular ),
		/* translators: %s: plural event name */
		'all_items'          => sprintf( __( 'All %s', 'pmpro-events' ), $plural ),
		/* translators: %s: singular event name */
		'view_item'          => sprintf( __( 'View %s', 'pmpro-events' ), $singular ),
		/* translators: %s: plural event name */
		'search_items'       => sprintf( __( 'Search %s', 'pmpro-events' ), $plural ),
		/* translators: %s: plural event name (lowercase) */
		'not_found'          => sprintf( __( 'No %s found', 'pmpro-events' ), strtolower( $plural ) ),
		/* translators: %s: plural event name (lowercase) */
		'not_found_in_trash' => sprintf( __( 'No %s found in Trash', 'pmpro-events' ), strtolower( $plural ) ),
		'menu_name'          => $plural,
	);

	$args = array(
		'labels'       => $labels,
		'public'       => true,
		'show_in_menu' => 'pmpro-dashboard',
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'has_archive'  => true,
		'rewrite'      => array( 'slug' => 'events' ),
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-calendar-alt',
	);

	register_post_type( 'pmpro_event', $args );
}
add_action( 'init', 'pmpro_events_register_post_type' );

/**
 * Modify archive query to order events by start date.
 *
 * @since 2.0
 *
 * @param WP_Query $query The query object.
 */
function pmpro_events_archive_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! $query->is_post_type_archive( 'pmpro_event' ) ) {
		return;
	}

	// Join with pmpro_events table and order by start date.
	add_filter( 'posts_join', 'pmpro_events_archive_join' );
	add_filter( 'posts_orderby', 'pmpro_events_archive_orderby' );

	// Only show future events by default.
	add_filter( 'posts_where', 'pmpro_events_archive_where_future' );
}
add_action( 'pre_get_posts', 'pmpro_events_archive_query' );

/**
 * Join pmpro_events table for archive queries.
 *
 * @since 2.0
 */
function pmpro_events_archive_join( $join ) {
	global $wpdb;
	$join .= " INNER JOIN {$wpdb->prefix}pmpro_events AS pe ON {$wpdb->posts}.ID = pe.post_id";
	remove_filter( 'posts_join', 'pmpro_events_archive_join' );
	return $join;
}

/**
 * Order by start date for archive queries.
 *
 * @since 2.0
 */
function pmpro_events_archive_orderby( $orderby ) {
	$orderby = 'pe.start_utc ASC';
	remove_filter( 'posts_orderby', 'pmpro_events_archive_orderby' );
	return $orderby;
}

/**
 * Only show future events in archive.
 *
 * @since 2.0
 */
function pmpro_events_archive_where_future( $where ) {
	$now   = current_time( 'mysql', true );
	$where .= " AND pe.end_utc >= '{$now}'";
	remove_filter( 'posts_where', 'pmpro_events_archive_where_future' );
	return $where;
}
