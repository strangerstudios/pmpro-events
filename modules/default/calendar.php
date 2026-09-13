<?php
/**
 * ICS downloads and Add-to-Calendar links.
 *
 * These are only offered to a user who is registered for the event.
 *
 * @since 2.0
 */

/**
 * Get the start and end DateTime objects to use in a calendar invite.
 *
 * An event with no end date is treated as one hour long so that the invite
 * lands on the attendee's calendar as a real block of time.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return array|false Array with 'start' and 'end' DateTime objects, or false if the event has no start date.
 */
function pmpro_events_get_calendar_dates( $event ) {
	$start = $event->get_date( 'start' );

	if ( empty( $start ) ) {
		return false;
	}

	$end = $event->get_date( 'end' );

	if ( empty( $end ) ) {
		$end = clone $start;
		if ( $event->all_day ) {
			$end->setTime( 23, 59, 59 );
		} else {
			$end->modify( '+1 hour' );
		}
	}

	return array( 'start' => $start, 'end' => $end );
}

/**
 * Get the URL that downloads the ICS file for an event.
 *
 * @since 2.0
 *
 * @param int $event_id The event ID.
 * @return string The download URL.
 */
function pmpro_events_get_ics_url( $event_id ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'   => 'pmpro_events_ics',
				'event_id' => (int) $event_id,
			),
			admin_url( 'admin-post.php' )
		),
		'pmpro_events_ics_' . (int) $event_id,
		'pmpro_events_nonce'
	);
}

/**
 * Escape a value for inclusion in an ICS property.
 *
 * @since 2.0
 *
 * @param string $value The value to escape.
 * @return string The escaped value.
 */
function pmpro_events_ics_escape( $value ) {
	$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
	$value = str_replace( array( '\\', ';', ',' ), array( '\\\\', '\\;', '\\,' ), $value );

	return str_replace( "\n", '\\n', $value );
}

/**
 * Fold an ICS line to 75 octets, as required by RFC 5545.
 *
 * RFC 5545 counts octets rather than characters, but a fold must still land on
 * a character boundary — splitting a multibyte character produces an invalid
 * file, which any non-ASCII event title would otherwise trigger.
 *
 * @since 2.0
 *
 * @param string $line The line to fold.
 * @return string The folded line.
 */
function pmpro_events_ics_fold( $line ) {
	// Continuation lines begin with a single space, which costs one of the 75.
	$limit  = 75;
	$folded = '';

	while ( strlen( $line ) > $limit ) {
		$length = $limit;

		// A continuation byte here means the cut lands inside a character, so
		// step back until it doesn't.
		while ( $length > 0 && 0x80 === ( ord( $line[ $length ] ) & 0xC0 ) ) {
			$length--;
		}

		// A single character wider than the limit can't be folded any further.
		if ( 0 === $length ) {
			break;
		}

		$folded .= substr( $line, 0, $length ) . "\r\n ";
		$line    = substr( $line, $length );
		$limit   = 74;
	}

	return $folded . $line;
}

/**
 * Build the ICS file contents for an event.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The ICS file contents, or an empty string if the event has no start date.
 */
function pmpro_events_get_ics_content( $event ) {
	$dates = pmpro_events_get_calendar_dates( $event );

	if ( empty( $dates ) ) {
		return '';
	}

	$all_day = (bool) $event->all_day;
	$host    = wp_parse_url( home_url(), PHP_URL_HOST );

	// The meeting URL belongs in the invite, since only attendees can get here.
	$location = $event->is_virtual() ? $event->virtual_url : $event->get_location_summary();

	$description = $event->get_description();
	if ( $event->is_virtual() && ! empty( $event->virtual_url ) ) {
		/* translators: %s: the meeting or stream URL for a virtual event. */
		$description = trim( $description . "\n\n" . sprintf( __( 'Join here: %s', 'pmpro-events' ), $event->virtual_url ) );
	}

	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Paid Memberships Pro//Events//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'BEGIN:VEVENT',
		'UID:' . $event->get_uuid() . '@' . $host,
		'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
	);

	if ( $all_day ) {
		// DTEND is exclusive for all-day events, so add a day.
		$end_date = clone $dates['end'];
		$end_date->modify( '+1 day' );

		$lines[] = 'DTSTART;VALUE=DATE:' . $dates['start']->format( 'Ymd' );
		$lines[] = 'DTEND;VALUE=DATE:' . $end_date->format( 'Ymd' );
	} else {
		$start_utc = clone $dates['start'];
		$end_utc   = clone $dates['end'];
		$start_utc->setTimezone( new DateTimeZone( 'UTC' ) );
		$end_utc->setTimezone( new DateTimeZone( 'UTC' ) );

		$lines[] = 'DTSTART:' . $start_utc->format( 'Ymd\THis\Z' );
		$lines[] = 'DTEND:' . $end_utc->format( 'Ymd\THis\Z' );
	}

	$lines[] = 'SUMMARY:' . pmpro_events_ics_escape( $event->get_title() );

	if ( ! empty( $description ) ) {
		$lines[] = 'DESCRIPTION:' . pmpro_events_ics_escape( $description );
	}

	if ( ! empty( $location ) ) {
		$lines[] = 'LOCATION:' . pmpro_events_ics_escape( $location );
	}

	$lines[] = 'URL:' . $event->get_permalink();
	$lines[] = 'END:VEVENT';
	$lines[] = 'END:VCALENDAR';

	/**
	 * Filter the lines that make up an event's ICS file.
	 *
	 * @since 2.0
	 *
	 * @param array             $lines The unfolded ICS lines.
	 * @param PMProEvents_Event $event The event.
	 */
	$lines = apply_filters( 'pmpro_events_ics_lines', $lines, $event );

	return implode( "\r\n", array_map( 'pmpro_events_ics_fold', $lines ) ) . "\r\n";
}

/**
 * Serve the ICS download.
 *
 * @since 2.0
 */
function pmpro_events_download_ics() {
	$event_id = isset( $_REQUEST['event_id'] ) ? (int) $_REQUEST['event_id'] : 0;

	if ( empty( $event_id ) || ! isset( $_REQUEST['pmpro_events_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['pmpro_events_nonce'] ) ), 'pmpro_events_ics_' . $event_id ) ) {
		wp_die( esc_html__( 'Your link has expired. Please reload the event page and try again.', 'pmpro-events' ), '', array( 'response' => 403 ) );
	}

	$event = new PMProEvents_Event( $event_id );

	if ( ! $event->exists() ) {
		wp_die( esc_html__( 'Event not found.', 'pmpro-events' ), '', array( 'response' => 404 ) );
	}

	// The invite can contain the meeting URL, so it goes to registered
	// attendees — or, when the event isn't taking registrations, to anyone who
	// can view the event.
	if ( ! $event->user_can_view_links() ) {
		wp_die( esc_html__( 'Sorry, you do not have access to this invite. Try reloading the event page.', 'pmpro-events' ), '', array( 'response' => 403 ) );
	}

	$content = pmpro_events_get_ics_content( $event );

	if ( empty( $content ) ) {
		wp_die( esc_html__( 'This event does not have a date set.', 'pmpro-events' ), '', array( 'response' => 404 ) );
	}

	$filename = sanitize_file_name( get_post_field( 'post_name', $event_id ) );
	if ( empty( $filename ) ) {
		$filename = 'event-' . $event_id;
	}

	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '.ics"' );
	header( 'Content-Length: ' . strlen( $content ) );

	echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}
add_action( 'admin_post_pmpro_events_ics', 'pmpro_events_download_ics' );
// Logged-out visitors can download the invite for an event that isn't taking
// registrations, so the handler has to answer nopriv requests too.
add_action( 'admin_post_nopriv_pmpro_events_ics', 'pmpro_events_download_ics' );

/**
 * Get the Add-to-Calendar links for an event.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return array Links keyed by service, each with 'label' and 'url'.
 */
function pmpro_events_get_add_to_calendar_links( $event ) {
	$dates = pmpro_events_get_calendar_dates( $event );

	if ( empty( $dates ) ) {
		return array();
	}

	$all_day = (bool) $event->all_day;

	$start_utc = clone $dates['start'];
	$end_utc   = clone $dates['end'];
	$start_utc->setTimezone( new DateTimeZone( 'UTC' ) );
	$end_utc->setTimezone( new DateTimeZone( 'UTC' ) );

	$title       = $event->get_title();
	$description = $event->get_description();
	$location    = $event->is_virtual() ? $event->virtual_url : $event->get_location_summary();

	// Google uses compact dates, and treats the all-day end date as exclusive.
	if ( $all_day ) {
		$google_end = clone $dates['end'];
		$google_end->modify( '+1 day' );
		$google_dates = $dates['start']->format( 'Ymd' ) . '/' . $google_end->format( 'Ymd' );
	} else {
		$google_dates = $start_utc->format( 'Ymd\THis\Z' ) . '/' . $end_utc->format( 'Ymd\THis\Z' );
	}

	$google_url = add_query_arg(
		rawurlencode_deep( array(
			'action'   => 'TEMPLATE',
			'text'     => $title,
			'dates'    => $google_dates,
			'details'  => $description,
			'location' => $location,
		) ),
		'https://calendar.google.com/calendar/render'
	);

	// The Outlook deep links share a query format and take ISO 8601 dates.
	$outlook_args = rawurlencode_deep( array(
		'path'    => '/calendar/action/compose',
		'rru'     => 'addevent',
		'subject' => $title,
		'startdt' => $all_day ? $dates['start']->format( 'Y-m-d' ) : $start_utc->format( 'Y-m-d\TH:i:s\Z' ),
		'enddt'   => $all_day ? $dates['end']->format( 'Y-m-d' ) : $end_utc->format( 'Y-m-d\TH:i:s\Z' ),
		'body'    => $description,
		'location' => $location,
	) );

	if ( $all_day ) {
		$outlook_args['allday'] = 'true';
	}

	$links = array(
		'google' => array(
			'label' => __( 'Google Calendar', 'pmpro-events' ),
			'url'   => $google_url,
		),
		'outlook365' => array(
			'label' => __( 'Outlook 365', 'pmpro-events' ),
			'url'   => add_query_arg( $outlook_args, 'https://outlook.office.com/calendar/0/deeplink/compose' ),
		),
		'outlooklive' => array(
			'label' => __( 'Outlook.com', 'pmpro-events' ),
			'url'   => add_query_arg( $outlook_args, 'https://outlook.live.com/calendar/0/deeplink/compose' ),
		),
		'ics' => array(
			'label' => __( 'Download .ics', 'pmpro-events' ),
			'url'   => pmpro_events_get_ics_url( $event->get_id() ),
		),
	);

	/**
	 * Filter the Add-to-Calendar links shown to a registered attendee.
	 *
	 * @since 2.0
	 *
	 * @param array             $links The links, keyed by service.
	 * @param PMProEvents_Event $event The event.
	 */
	return apply_filters( 'pmpro_events_add_to_calendar_links', $links, $event );
}
