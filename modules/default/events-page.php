<?php
// In case the file is loaded directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Events page setting under Memberships > Settings > Pages.
 *
 * Registered through PMPro's extra page settings, so it behaves like every
 * other add-on page: admins can assign an existing page or have one generated,
 * and the setting is stored as pmpro_events_page_id and exposed through
 * $pmpro_pages['events'] and pmpro_url( 'events' ).
 *
 * @since 2.0
 */

/**
 * Get the assigned Events page ID.
 *
 * @since 2.0
 *
 * @return int The page ID, or 0 when no published Events page is assigned.
 */
function pmpro_events_get_events_page_id() {
	$page_id = (int) get_option( 'pmpro_events_page_id' );

	if ( empty( $page_id ) || 'publish' !== get_post_status( $page_id ) ) {
		return 0;
	}

	return $page_id;
}

/**
 * Get the slug for the event post type archive.
 *
 * When an Events page is assigned, the archive takes that page's own path and
 * pmpro_events_serve_events_page() shows the page there. Without the archive,
 * themes and plugins that build an "Events" breadcrumb or menu link from the
 * post type archive would drop it. Without moving it, the archive's rewrite
 * rule would shadow a page that lives at /events/.
 *
 * @since TBD
 *
 * @return string The archive slug.
 */
function pmpro_events_get_archive_slug() {
	$page_id = pmpro_events_get_events_page_id();

	return empty( $page_id ) ? 'events' : get_page_uri( $page_id );
}

/**
 * Show the Events page for requests to the event archive.
 *
 * Only the archive itself is swapped. Single events, the events feed, and
 * searches keep their own handling.
 *
 * @since TBD
 *
 * @param array $query_vars The parsed query variables.
 * @return array The filtered query variables.
 */
function pmpro_events_serve_events_page( $query_vars ) {
	// The admin Events list runs through the same request parsing.
	if ( is_admin() || empty( $query_vars['post_type'] ) || PMProEvents_Event::POST_TYPE !== $query_vars['post_type'] ) {
		return $query_vars;
	}

	foreach ( array( PMProEvents_Event::POST_TYPE, 'name', 'p', 'feed', 's' ) as $key ) {
		if ( isset( $query_vars[ $key ] ) && '' !== $query_vars[ $key ] ) {
			return $query_vars;
		}
	}

	$page_id = pmpro_events_get_events_page_id();

	if ( empty( $page_id ) ) {
		return $query_vars;
	}

	return array( 'page_id' => $page_id );
}
add_filter( 'request', 'pmpro_events_serve_events_page' );

/**
 * Register the Events page with PMPro's page settings.
 *
 * The generated page leads with the member's own registrations, then the
 * site-wide list under an "All Events" title so the two sections read as a
 * pair. Both blocks default their own titles, so only the list needs one set.
 *
 * @since 2.0
 *
 * @param array $pages The extra pages, keyed by page name.
 * @return array The filtered pages.
 */
function pmpro_events_extra_page_settings( $pages ) {
	$plural = pmpro_events_get_label( 'plural' );

	/* translators: %s: the plural event label, e.g. "Events". */
	$list_attributes = wp_json_encode( array( 'title' => sprintf( _x( 'All %s', 'plural event label', 'pmpro-events' ), $plural ) ) );

	$pages['events'] = array(
		'title'   => $plural,
		'content' => "<!-- wp:pmpro-events/my-events /-->\n\n<!-- wp:pmpro-events/events {$list_attributes} /-->",
		'hint'    => __( 'Include the Upcoming Events block or the [pmpro_events] shortcode. The My Events block shows logged-in members their own registrations.', 'pmpro-events' ),
	);

	return $pages;
}
add_filter( 'pmpro_extra_page_settings', 'pmpro_events_extra_page_settings' );

/**
 * Link to the Events page from the Member Links section of the account page.
 *
 * @since 2.0
 */
function pmpro_events_member_links_top() {
	if ( empty( pmpro_events_get_events_page_id() ) ) {
		return;
	}
	?>
	<li>
		<a href="<?php echo esc_url( pmpro_url( 'events' ) ); ?>">
			<?php
			/* translators: %s: the plural event label, e.g. "Events". */
			echo esc_html( sprintf( _x( 'View %s', 'plural event label', 'pmpro-events' ), pmpro_events_get_label( 'plural' ) ) );
			?>
		</a>
	</li>
	<?php
}
add_action( 'pmpro_member_links_top', 'pmpro_events_member_links_top' );
