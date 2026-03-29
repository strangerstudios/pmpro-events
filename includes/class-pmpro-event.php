<?php
/**
 * PMPro Event model.
 *
 * Wraps the pmpro_events custom table row with helper methods.
 *
 * @package PMPro_Events
 * @since 2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * PMPro_Event class.
 *
 * @since 2.0
 */
class PMPro_Event {

	/**
	 * @var int Row ID from pmpro_events table.
	 */
	public $id = 0;

	/**
	 * @var int Associated post ID.
	 */
	public $post_id = 0;

	/**
	 * @var string Local start datetime.
	 */
	public $start = '0000-00-00 00:00:00';

	/**
	 * @var string UTC start datetime.
	 */
	public $start_utc = '0000-00-00 00:00:00';

	/**
	 * @var string Local end datetime.
	 */
	public $end = '0000-00-00 00:00:00';

	/**
	 * @var string UTC end datetime.
	 */
	public $end_utc = '0000-00-00 00:00:00';

	/**
	 * @var string IANA timezone string.
	 */
	public $timezone = '';

	/**
	 * @var int Whether event is all day.
	 */
	public $all_day = 0;

	/**
	 * @var int Duration in minutes.
	 */
	public $duration_minutes = 0;

	/**
	 * @var int Capacity (0 = unlimited).
	 */
	public $capacity = 0;

	/**
	 * @var string Venue name.
	 */
	public $venue_name = '';

	/**
	 * @var string Venue address.
	 */
	public $venue_address = '';

	/**
	 * @var int|null Auto-created PMPro level ID.
	 */
	public $level_id = null;

	/**
	 * @var int|null Auto-created member price level ID.
	 */
	public $member_level_id = null;

	/**
	 * @var string Event status.
	 */
	public $status = 'publish';

	/**
	 * @var string UUID.
	 */
	public $uuid = '';

	/**
	 * @var string Date created.
	 */
	public $date_created = '0000-00-00 00:00:00';

	/**
	 * @var string Date modified.
	 */
	public $date_modified = '0000-00-00 00:00:00';

	/**
	 * Constructor.
	 *
	 * @since 2.0
	 *
	 * @param object|null $row Database row object.
	 */
	public function __construct( $row = null ) {
		if ( $row ) {
			foreach ( get_object_vars( $row ) as $key => $value ) {
				if ( property_exists( $this, $key ) ) {
					$this->$key = $value;
				}
			}
		}
	}

	/**
	 * Get event by post ID.
	 *
	 * @since 2.0
	 *
	 * @param int $post_id Post ID.
	 * @return PMPro_Event|null
	 */
	public static function get_by_post_id( $post_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}pmpro_events WHERE post_id = %d",
			$post_id
		) );
		return $row ? new self( $row ) : null;
	}

	/**
	 * Get event by ID.
	 *
	 * @since 2.0
	 *
	 * @param int $id Event row ID.
	 * @return PMPro_Event|null
	 */
	public static function get_by_id( $id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}pmpro_events WHERE id = %d",
			$id
		) );
		return $row ? new self( $row ) : null;
	}

	/**
	 * Sync schedule data from form values.
	 *
	 * Computes UTC times, duration, and performs upsert.
	 *
	 * @since 2.0
	 *
	 * @param array $args {
	 *     @type string $start_date  Date in Y-m-d format.
	 *     @type string $start_time  Time in H:i format.
	 *     @type string $end_date    Date in Y-m-d format.
	 *     @type string $end_time    Time in H:i format.
	 *     @type string $timezone    IANA timezone string.
	 *     @type bool   $all_day     Whether event is all day.
	 *     @type int    $capacity    Capacity (0 = unlimited).
	 *     @type string $venue_name  Venue name.
	 *     @type string $venue_address Venue address.
	 * }
	 * @return bool True on success.
	 */
	public function sync_schedule( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'start_date'    => '',
			'start_time'    => '00:00',
			'end_date'      => '',
			'end_time'      => '23:59',
			'timezone'      => wp_timezone_string(),
			'all_day'       => false,
			'capacity'      => 0,
			'venue_name'    => '',
			'venue_address' => '',
		) );

		if ( empty( $args['start_date'] ) ) {
			return false;
		}

		$end_date   = $args['end_date'] ?: $args['start_date'];
		$start_time = $args['start_time'] ?: '00:00';
		$end_time   = $args['end_time'] ?: '23:59';
		$timezone   = $args['timezone'] ?: wp_timezone_string();
		$all_day    = (bool) $args['all_day'];

		if ( $all_day ) {
			$start_time = '00:00';
			$end_time   = '23:59';
		}

		try {
			$tz       = new DateTimeZone( $timezone );
			$utc      = new DateTimeZone( 'UTC' );
			$start_dt = new DateTime( $args['start_date'] . ' ' . $start_time . ':00', $tz );
			$end_dt   = new DateTime( $end_date . ' ' . $end_time . ':59', $tz );
		} catch ( Exception $e ) {
			return false;
		}

		$start_utc = clone $start_dt;
		$start_utc->setTimezone( $utc );
		$end_utc = clone $end_dt;
		$end_utc->setTimezone( $utc );

		$duration = max( 0, (int) ( ( $end_dt->getTimestamp() - $start_dt->getTimestamp() ) / 60 ) );

		$this->start          = $start_dt->format( 'Y-m-d H:i:s' );
		$this->start_utc      = $start_utc->format( 'Y-m-d H:i:s' );
		$this->end            = $end_dt->format( 'Y-m-d H:i:s' );
		$this->end_utc        = $end_utc->format( 'Y-m-d H:i:s' );
		$this->timezone       = $timezone;
		$this->all_day        = $all_day ? 1 : 0;
		$this->duration_minutes = $duration;
		$this->capacity       = absint( $args['capacity'] );
		$this->venue_name     = sanitize_text_field( $args['venue_name'] );
		$this->venue_address  = sanitize_textarea_field( $args['venue_address'] );
		$this->date_modified  = current_time( 'mysql' );

		return $this->save();
	}

	/**
	 * Save the event to the database.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */
	public function save() {
		global $wpdb;
		$table = $wpdb->prefix . 'pmpro_events';

		if ( empty( $this->uuid ) ) {
			$this->uuid = wp_generate_uuid4();
		}

		if ( $this->date_created === '0000-00-00 00:00:00' ) {
			$this->date_created = current_time( 'mysql' );
		}

		$this->date_modified = current_time( 'mysql' );

		$data = array(
			'post_id'          => $this->post_id,
			'start'            => $this->start,
			'start_utc'        => $this->start_utc,
			'end'              => $this->end,
			'end_utc'          => $this->end_utc,
			'timezone'         => $this->timezone,
			'all_day'          => $this->all_day,
			'duration_minutes' => $this->duration_minutes,
			'capacity'         => $this->capacity,
			'venue_name'       => $this->venue_name,
			'venue_address'    => $this->venue_address,
			'level_id'         => $this->level_id,
			'member_level_id'  => $this->member_level_id,
			'status'           => $this->status,
			'uuid'             => $this->uuid,
			'date_created'     => $this->date_created,
			'date_modified'    => $this->date_modified,
		);

		if ( $this->id ) {
			$result = $wpdb->update( $table, $data, array( 'id' => $this->id ) );
			return $result !== false;
		}

		$result = $wpdb->insert( $table, $data );
		if ( $result ) {
			$this->id = $wpdb->insert_id;
			return true;
		}
		return false;
	}

	/**
	 * Get start as DateTime in event timezone.
	 *
	 * @since 2.0
	 *
	 * @return DateTime|null
	 */
	public function get_start_datetime() {
		if ( empty( $this->start ) || $this->start === '0000-00-00 00:00:00' ) {
			return null;
		}
		try {
			return new DateTime( $this->start, $this->get_timezone_object() );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Get end as DateTime in event timezone.
	 *
	 * @since 2.0
	 *
	 * @return DateTime|null
	 */
	public function get_end_datetime() {
		if ( empty( $this->end ) || $this->end === '0000-00-00 00:00:00' ) {
			return null;
		}
		try {
			return new DateTime( $this->end, $this->get_timezone_object() );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Get the timezone object.
	 *
	 * @since 2.0
	 *
	 * @return DateTimeZone
	 */
	public function get_timezone_object() {
		try {
			return new DateTimeZone( $this->timezone ?: wp_timezone_string() );
		} catch ( Exception $e ) {
			return wp_timezone();
		}
	}

	/**
	 * Get formatted schedule details string.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public function get_schedule_details() {
		$start = $this->get_start_datetime();
		if ( ! $start ) {
			return '';
		}

		$end = $this->get_end_datetime();
		if ( ! $end ) {
			$end = clone $start;
		}

		$tz        = $this->get_timezone_object();
		$now       = new DateTime( 'now', $tz );
		$same_day  = $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' );
		$this_year = $start->format( 'Y' ) === $now->format( 'Y' );
		$date_fmt  = $this_year ? 'F j' : 'F j, Y';
		$time_fmt  = get_option( 'time_format', 'g:i a' );

		if ( $this->all_day ) {
			$output = wp_date( $date_fmt, $start->getTimestamp(), $tz );
			if ( ! $same_day ) {
				$end_date_fmt = $end->format( 'Y' ) === $now->format( 'Y' ) ? 'F j' : 'F j, Y';
				$output .= ' – ' . wp_date( $end_date_fmt, $end->getTimestamp(), $tz );
			}
		} elseif ( $same_day ) {
			$output = wp_date( $date_fmt, $start->getTimestamp(), $tz );
			$output .= ' @ ' . wp_date( $time_fmt, $start->getTimestamp(), $tz );
			if ( $start->format( 'H:i' ) !== $end->format( 'H:i' ) ) {
				$output .= ' – ' . wp_date( $time_fmt, $end->getTimestamp(), $tz );
			}
		} else {
			$end_date_fmt = $end->format( 'Y' ) === $now->format( 'Y' ) ? 'F j' : 'F j, Y';
			$output  = wp_date( $date_fmt, $start->getTimestamp(), $tz );
			$output .= ' @ ' . wp_date( $time_fmt, $start->getTimestamp(), $tz );
			$output .= ' – ';
			$output .= wp_date( $end_date_fmt, $end->getTimestamp(), $tz );
			$output .= ' @ ' . wp_date( $time_fmt, $end->getTimestamp(), $tz );
		}

		return $output;
	}

	/**
	 * Get registration count.
	 *
	 * @since 2.0
	 *
	 * @return int
	 */
	public function get_registration_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}pmpro_event_registrations WHERE event_id = %d AND status = 'active'",
			$this->id
		) );
	}

	/**
	 * Check if event has remaining capacity.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */
	public function has_capacity() {
		if ( $this->capacity <= 0 ) {
			return true; // Unlimited.
		}
		return $this->get_registration_count() < $this->capacity;
	}

	/**
	 * Get remaining capacity.
	 *
	 * @since 2.0
	 *
	 * @return int|null Null if unlimited.
	 */
	public function get_remaining_capacity() {
		if ( $this->capacity <= 0 ) {
			return null;
		}
		return max( 0, $this->capacity - $this->get_registration_count() );
	}

	/**
	 * Check if a user is registered for this event.
	 *
	 * @since 2.0
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_user_registered( $user_id ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}pmpro_event_registrations WHERE event_id = %d AND user_id = %d AND status = 'active'",
			$this->id,
			$user_id
		) );
	}

	/**
	 * Check if event has passed.
	 *
	 * @since 2.0
	 *
	 * @return bool
	 */
	public function has_passed() {
		$end = $this->get_end_datetime();
		if ( ! $end ) {
			return false;
		}
		$now = new DateTime( 'now', $this->get_timezone_object() );
		return $end < $now;
	}

	/**
	 * Get all registrations for this event.
	 *
	 * @since 2.0
	 *
	 * @param string $status Filter by status. Empty for all.
	 * @return array
	 */
	public function get_registrations( $status = 'active' ) {
		global $wpdb;
		$sql = "SELECT r.*, u.user_login, u.user_email, u.display_name
			FROM {$wpdb->prefix}pmpro_event_registrations r
			LEFT JOIN {$wpdb->users} u ON r.user_id = u.ID
			WHERE r.event_id = %d";
		$params = array( $this->id );

		if ( $status ) {
			$sql .= ' AND r.status = %s';
			$params[] = $status;
		}

		$sql .= ' ORDER BY r.registered_at DESC';

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Generate Google Calendar URL.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public function get_google_calendar_url() {
		$start_dt = $this->get_start_datetime();
		$end_dt   = $this->get_end_datetime();
		if ( ! $start_dt ) {
			return '';
		}
		if ( ! $end_dt ) {
			$end_dt = clone $start_dt;
		}

		$utc = new DateTimeZone( 'UTC' );

		if ( $this->all_day ) {
			$start = $start_dt->format( 'Ymd' );
			$end_plus = clone $end_dt;
			$end_plus->modify( '+1 day' );
			$end = $end_plus->format( 'Ymd' );
		} else {
			$start_utc = clone $start_dt;
			$start_utc->setTimezone( $utc );
			$end_utc = clone $end_dt;
			$end_utc->setTimezone( $utc );
			$start = $start_utc->format( 'Ymd\THis\Z' );
			$end   = $end_utc->format( 'Ymd\THis\Z' );
		}

		$post = get_post( $this->post_id );

		return add_query_arg( array(
			'action'   => 'TEMPLATE',
			'text'     => rawurlencode( $post ? $post->post_title : '' ),
			'dates'    => $start . '/' . $end,
			'details'  => rawurlencode( $post ? wp_strip_all_tags( $post->post_excerpt ) : '' ),
			'location' => rawurlencode( $this->venue_name . ( $this->venue_address ? ', ' . $this->venue_address : '' ) ),
		), 'https://calendar.google.com/calendar/render' );
	}

	/**
	 * Generate Outlook 365 calendar URL.
	 *
	 * @since 2.0
	 *
	 * @return string
	 */
	public function get_outlook365_url() {
		$start_dt = $this->get_start_datetime();
		$end_dt   = $this->get_end_datetime();
		if ( ! $start_dt ) {
			return '';
		}
		if ( ! $end_dt ) {
			$end_dt = clone $start_dt;
		}

		$utc = new DateTimeZone( 'UTC' );
		$start_utc = clone $start_dt;
		$start_utc->setTimezone( $utc );
		$end_utc = clone $end_dt;
		$end_utc->setTimezone( $utc );

		$post = get_post( $this->post_id );

		$args = array(
			'subject'  => rawurlencode( $post ? $post->post_title : '' ),
			'startdt'  => $start_utc->format( 'Y-m-d\TH:i:s\Z' ),
			'enddt'    => $end_utc->format( 'Y-m-d\TH:i:s\Z' ),
			'body'     => rawurlencode( $post ? wp_strip_all_tags( $post->post_excerpt ) : '' ),
			'location' => rawurlencode( $this->venue_name ),
		);

		if ( $this->all_day ) {
			$args['allday'] = 'true';
		}

		return add_query_arg( $args, 'https://outlook.office.com/calendar/0/deeplink/compose' );
	}
}
