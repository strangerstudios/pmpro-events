<?php
/**
 * ICS calendar file download endpoint.
 *
 * @package PMPro_Events
 * @since 2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handle single-event .ics download.
 *
 * @since 2.0
 */
function pmpro_events_handle_ics_download() {
	if ( empty( $_GET['pmpro_events_ics'] ) || empty( $_GET['event_id'] ) ) {
		return;
	}

	$event_id = absint( $_GET['event_id'] );
	$event    = PMPro_Event::get_by_id( $event_id );
	if ( ! $event ) {
		return;
	}

	$post = get_post( $event->post_id );
	if ( ! $post ) {
		return;
	}

	$start_dt = $event->get_start_datetime();
	$end_dt   = $event->get_end_datetime();
	if ( ! $start_dt ) {
		return;
	}
	if ( ! $end_dt ) {
		$end_dt = clone $start_dt;
	}

	$title    = $post->post_title;
	$desc     = wp_strip_all_tags( $post->post_excerpt );
	$location = $event->venue_name . ( $event->venue_address ? ', ' . $event->venue_address : '' );
	$uid      = 'pmpro-event-' . $event->id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
	$now      = gmdate( 'Ymd\THis\Z' );

	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//PMPro Events//' . wp_parse_url( home_url(), PHP_URL_HOST ) . '//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'BEGIN:VEVENT',
		'UID:' . $uid,
		'DTSTAMP:' . $now,
	);

	if ( $event->all_day ) {
		$end_plus = clone $end_dt;
		$end_plus->modify( '+1 day' );
		$lines[] = 'DTSTART;VALUE=DATE:' . $start_dt->format( 'Ymd' );
		$lines[] = 'DTEND;VALUE=DATE:' . $end_plus->format( 'Ymd' );
	} else {
		$utc = new DateTimeZone( 'UTC' );
		$start_utc = clone $start_dt;
		$start_utc->setTimezone( $utc );
		$end_utc = clone $end_dt;
		$end_utc->setTimezone( $utc );
		$lines[] = 'DTSTART:' . $start_utc->format( 'Ymd\THis\Z' );
		$lines[] = 'DTEND:' . $end_utc->format( 'Ymd\THis\Z' );
	}

	$lines[] = 'SUMMARY:' . pmpro_events_ics_escape( $title );
	$lines[] = 'DESCRIPTION:' . pmpro_events_ics_escape( $desc );
	$lines[] = 'LOCATION:' . pmpro_events_ics_escape( $location );
	$lines[] = 'END:VEVENT';
	$lines[] = 'END:VCALENDAR';

	$ics = implode( "\r\n", $lines );
	$filename = sanitize_file_name( $title ) . '.ics';

	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	echo $ics;
	exit;
}
add_action( 'init', 'pmpro_events_handle_ics_download' );

/**
 * Escape a string for ICS format.
 *
 * @since 2.0
 *
 * @param string $str Input string.
 * @return string
 */
function pmpro_events_ics_escape( $str ) {
	return str_replace(
		array( '\\', "\n", "\r", ',', ';' ),
		array( '\\\\', "\\n", '', '\\,', '\\;' ),
		$str
	);
}
