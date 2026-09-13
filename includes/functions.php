<?php
/**
 * Shared helpers used by every module.
 *
 * @since 2.0
 */

/**
 * Enqueue the admin stylesheet.
 *
 * Shared by the settings page and the registrations page, which are the only
 * two screens this add-on styles.
 *
 * @since 2.0
 */
function pmpro_events_enqueue_admin_style() {
	wp_enqueue_style(
		'pmpro-events-admin',
		PMPRO_EVENTS_URL . '/css/admin.css',
		array(),
		PMPRO_EVENTS_VERSION
	);
}

/**
 * Flush rewrite rules once, on the first request where they would differ.
 *
 * Activation and a settings save can't flush directly — the event post type
 * isn't registered during either request, so an immediate flush would build
 * rules without it. Instead of only honoring a one-shot flag (which gets
 * consumed even on a request where the post type never registered, e.g. when
 * this plugin is activated before PMPro), track the rewrite state the rules
 * were last built for and flush whenever it changes: an upgrade from 1.x, the
 * Default module toggling, PMPro activating or deactivating, or the events
 * page setting moving the archive.
 *
 * This runs after init 10, when the active modules have registered their post
 * types. It's always loaded, so the flush also happens when the Default module
 * was just turned off.
 *
 * @since 2.0
 */
function pmpro_events_maybe_flush_rewrite_rules() {
	// The literal post type name, since the class only loads with the module.
	$post_type = get_post_type_object( 'pmpro_event' );

	$state = empty( $post_type )
		? 'none'
		: 'event:' . ( empty( $post_type->has_archive ) ? 'no-archive' : $post_type->has_archive );

	if ( ! get_option( 'pmpro_events_flush_rewrite_rules' ) && get_option( 'pmpro_events_rewrite_state' ) === $state ) {
		return;
	}

	flush_rewrite_rules();

	// Autoloaded, since it is compared on every request.
	update_option( 'pmpro_events_rewrite_state', $state, true );
	delete_option( 'pmpro_events_flush_rewrite_rules' );
}
add_action( 'init', 'pmpro_events_maybe_flush_rewrite_rules', 20 );

/**
 * Get the configured terminology for events.
 *
 * Sites can rename "Event" to "Session", "Webinar", or anything else. The label
 * is used for the admin menu, the frontend template, and the account page.
 *
 * @since 2.0
 *
 * @param string $type One of 'singular', 'plural', 'singular_lowercase', or 'plural_lowercase'.
 * @return string The label.
 */
function pmpro_events_get_label( $type = 'singular' ) {
	$labels = get_option( 'pmpro_events_labels', array() );

	$singular = empty( $labels['singular'] ) ? __( 'Event', 'pmpro-events' ) : $labels['singular'];
	$plural   = empty( $labels['plural'] ) ? __( 'Events', 'pmpro-events' ) : $labels['plural'];

	switch ( $type ) {
		case 'plural':
			$label = $plural;
			break;
		case 'singular_lowercase':
			$label = pmpro_events_lowercase_label( $singular );
			break;
		case 'plural_lowercase':
			$label = pmpro_events_lowercase_label( $plural );
			break;
		case 'singular':
		default:
			$label = $singular;
			break;
	}

	/**
	 * Filter the event terminology.
	 *
	 * @since 2.0
	 *
	 * @param string $label The label to use.
	 * @param string $type  The label type being requested.
	 */
	return apply_filters( 'pmpro_events_label', $label, $type );
}

/**
 * Lowercase a label for use mid-sentence.
 *
 * Labels that look like acronyms or proper nouns are left alone.
 *
 * @since 2.0
 *
 * @param string $label The label to lowercase.
 * @return string The lowercased label.
 */
function pmpro_events_lowercase_label( $label ) {
	// Leave multi-capital labels such as "AGM" or "VIP Session" alone.
	if ( preg_match( '/[A-Z].*[A-Z]/', $label ) ) {
		return $label;
	}

	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label );
}

/**
 * Get the URL of PMPro's Edit Member screen for a user.
 *
 * Falls back to the WordPress user editor on installs that predate the Edit
 * Member screen.
 *
 * @since 2.0
 *
 * @param int    $user_id The user ID.
 * @param string $panel   Optional panel slug to open on arrival.
 * @return string The edit URL.
 */
function pmpro_events_get_member_edit_url( $user_id, $panel = '' ) {
	$user_id = (int) $user_id;

	if ( ! class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		return (string) get_edit_user_link( $user_id );
	}

	$args = array(
		'page'    => 'pmpro-member',
		'user_id' => $user_id,
	);

	if ( ! empty( $panel ) ) {
		$args['pmpro_member_edit_panel'] = $panel;
	}

	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * Get the URL of the registrations page, optionally for a specific event.
 *
 * Lives here rather than with the admin screens because the admin notification
 * emails link to it, and those can be triggered from any context.
 *
 * @since 2.0
 *
 * @param int $event_id The event to view registrations for. 0 for the event picker.
 * @return string The URL.
 */
function pmpro_events_get_registrations_url( $event_id = 0 ) {
	$args = array(
		'post_type' => PMProEvents_Event::POST_TYPE,
		'page'      => 'pmpro-event-registrations',
	);

	if ( ! empty( $event_id ) ) {
		$args['event_id'] = (int) $event_id;
	}

	return add_query_arg( $args, admin_url( 'edit.php' ) );
}

/**
 * Get the DateTimeZone to use for an event.
 *
 * Falls back to the site timezone when the event has no timezone of its own.
 *
 * @since 2.0
 *
 * @param string $timezone The timezone string stored on the event.
 * @return DateTimeZone The timezone object.
 */
function pmpro_events_get_timezone( $timezone = '' ) {
	if ( ! empty( $timezone ) ) {
		try {
			return new DateTimeZone( $timezone );
		} catch ( Exception $e ) {
			// Fall through to the site timezone.
		}
	}

	return wp_timezone();
}

/**
 * Check whether the site's timezone is a named zone rather than a raw UTC offset.
 *
 * Raw offsets can't account for daylight saving time, so the event editor warns
 * when the site is configured with one.
 *
 * @since 2.0
 *
 * @return bool Whether the site uses a named timezone.
 */
function pmpro_events_site_has_named_timezone() {
	$timezone_string = get_option( 'timezone_string' );

	return ! empty( $timezone_string );
}

/**
 * Get the list of timezone choices for the event editor.
 *
 * @since 2.0
 *
 * @return array List of arrays with 'label' and 'value' keys.
 */
function pmpro_events_get_timezone_choices() {
	$choices = array(
		array(
			/* translators: %s: the site's timezone, e.g. "America/New_York". */
			'label' => sprintf( __( 'Site Default (%s)', 'pmpro-events' ), wp_timezone_string() ),
			'value' => '',
		),
	);

	foreach ( timezone_identifiers_list() as $identifier ) {
		$choices[] = array(
			'label' => str_replace( '_', ' ', $identifier ),
			'value' => $identifier,
		);
	}

	return $choices;
}

/**
 * Convert a local datetime string in a given timezone to a UTC datetime string.
 *
 * @since 2.0
 *
 * @param string $datetime The local datetime string, in Y-m-d H:i:s or ISO 8601 format.
 * @param string $timezone The timezone that $datetime is expressed in.
 * @return string The UTC datetime string in Y-m-d H:i:s format, or an empty string on failure.
 */
function pmpro_events_local_to_utc( $datetime, $timezone = '' ) {
	if ( empty( $datetime ) ) {
		return '';
	}

	try {
		$date = new DateTime( $datetime, pmpro_events_get_timezone( $timezone ) );
	} catch ( Exception $e ) {
		return '';
	}

	$date->setTimezone( new DateTimeZone( 'UTC' ) );

	return $date->format( 'Y-m-d H:i:s' );
}

/**
 * Format an event datetime for display.
 *
 * @since 2.0
 *
 * @param string $datetime The local datetime string stored on the event.
 * @param string $timezone The event's timezone.
 * @param bool   $all_day  Whether the event is an all-day event.
 * @return string The formatted datetime.
 */
function pmpro_events_format_datetime( $datetime, $timezone = '', $all_day = false ) {
	if ( empty( $datetime ) ) {
		return '';
	}

	try {
		$date = new DateTime( $datetime, pmpro_events_get_timezone( $timezone ) );
	} catch ( Exception $e ) {
		return '';
	}

	$format = $all_day ? get_option( 'date_format' ) : get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

	return wp_date( $format, $date->getTimestamp(), pmpro_events_get_timezone( $timezone ) );
}
