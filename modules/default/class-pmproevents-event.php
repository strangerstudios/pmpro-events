<?php
/**
 * The PMPro Event object.
 *
 * Wraps a pmpro_event post and its meta, and provides the queries used by the
 * template, the account page, and the admin screens.
 *
 * @since 2.0
 */
class PMProEvents_Event {
	/**
	 * The post type used for events.
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	const POST_TYPE = 'pmpro_event';

	/**
	 * The taxonomy used to categorize events.
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	const TAXONOMY = 'pmpro_event_category';

	/**
	 * The ID of the event.
	 *
	 * @since 2.0
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * The event's post object.
	 *
	 * @since 2.0
	 *
	 * @var WP_Post|null
	 */
	protected $post = null;

	/**
	 * The event's meta, keyed by meta key without the pmpro_event_ prefix.
	 *
	 * @since 2.0
	 *
	 * @var array
	 */
	protected $meta = array();

	/**
	 * The cached number of active registrations, or null before it is counted.
	 *
	 * A single event page asks for the count several times over — for the seats
	 * remaining, for the full check, and for the registration card — so it is
	 * counted once per object.
	 *
	 * @since 2.0
	 *
	 * @var int|null
	 */
	protected $registration_count = null;

	/**
	 * Get an event object by ID.
	 *
	 * @since 2.0
	 *
	 * @param int $event_id The event ID to populate.
	 */
	public function __construct( $event_id ) {
		if ( ! is_numeric( $event_id ) ) {
			return;
		}

		$post = get_post( (int) $event_id );

		if ( empty( $post ) || self::POST_TYPE !== $post->post_type ) {
			return;
		}

		$this->id   = (int) $post->ID;
		$this->post = $post;

		foreach ( pmpro_events_get_meta_fields() as $key => $args ) {
			$value = get_post_meta( $this->id, $key, true );

			// Cast to the type registered for this meta field.
			switch ( $args['type'] ) {
				case 'boolean':
					$value = (bool) $value;
					break;
				case 'integer':
					$value = (int) $value;
					break;
				default:
					$value = (string) $value;
					break;
			}

			$this->meta[ str_replace( 'pmpro_event_', '', $key ) ] = $value;
		}
	}

	/**
	 * Get the list of events based on passed query arguments.
	 *
	 * @since 2.0
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type string          $timeframe   'upcoming', 'past', or 'all'. Default 'all'.
	 *     @type string          $month       Limit to events starting in a month, as 'YYYY-MM'.
	 *                                        Compared against the event's own local date.
	 *     @type string|string[] $category    Limit to one or more event category slugs.
	 *     @type int             $limit       Number of events to return. 0 for all. Default 0.
	 *     @type string          $order       'ASC' or 'DESC'. Default 'ASC'.
	 *     @type string          $post_status Post status to query. Default 'publish'.
	 * }
	 * @return PMProEvents_Event[] The list of events.
	 */
	public static function get_events( $args = array() ) {
		$args = wp_parse_args( $args, array(
			'timeframe'   => 'all',
			'month'       => '',
			'category'    => '',
			'limit'       => 0,
			'order'       => 'ASC',
			'post_status' => 'publish',
		) );

		// Sorting always happens on the computed UTC start date.
		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => $args['post_status'],
			'posts_per_page' => empty( $args['limit'] ) ? -1 : (int) $args['limit'],
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => 'pmpro_event_start_utc',
			'meta_type'      => 'DATETIME',
			'orderby'        => 'meta_value',
			'order'          => 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC',
		);

		$meta_query = array();

		// Compare against the end date when we have one so that an event that is
		// currently in progress still counts as upcoming. Events with no date at
		// all match neither timeframe.
		if ( 'all' !== $args['timeframe'] ) {
			$now     = current_time( 'mysql', true );
			$compare = 'upcoming' === $args['timeframe'] ? '>=' : '<';

			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'pmpro_event_end_utc',
					'value'   => $now,
					'compare' => $compare,
					'type'    => 'DATETIME',
				),
				array(
					'relation' => 'AND',
					array(
						'key'     => 'pmpro_event_end_utc',
						'value'   => '',
						'compare' => '=',
					),
					array(
						'key'     => 'pmpro_event_start_utc',
						'value'   => $now,
						'compare' => $compare,
						'type'    => 'DATETIME',
					),
				),
			);
		}

		// The month is compared against the local start date, so each event lands
		// on the calendar date its own timezone says it starts.
		if ( preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', (string) $args['month'] ) ) {
			$meta_query[] = array(
				'key'     => 'pmpro_event_start',
				'value'   => array(
					$args['month'] . '-01 00:00:00',
					gmdate( 'Y-m-t', strtotime( $args['month'] . '-01' ) ) . ' 23:59:59',
				),
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			);
		}

		if ( ! empty( $meta_query ) ) {
			$query_args['meta_query'] = count( $meta_query ) > 1
				? array_merge( array( 'relation' => 'AND' ), $meta_query )
				: $meta_query;
		}

		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => self::TAXONOMY,
					'field'    => 'slug',
					'terms'    => array_map( 'sanitize_title', (array) $args['category'] ),
				),
			);
		}

		/**
		 * Filter the WP_Query arguments used to look up events.
		 *
		 * @since 2.0
		 *
		 * @param array $query_args The WP_Query arguments.
		 * @param array $args       The arguments passed to get_events().
		 */
		$query_args = apply_filters( 'pmpro_events_get_events_query_args', $query_args, $args );

		$query = new WP_Query( $query_args );

		$events = array();
		foreach ( $query->posts as $event_id ) {
			$event = new self( (int) $event_id );
			if ( ! empty( $event->id ) ) {
				$events[] = $event;
			}
		}

		return $events;
	}

	/**
	 * Whether this object refers to a real event.
	 *
	 * @since 2.0
	 *
	 * @return bool Whether the event exists.
	 */
	public function exists() {
		return ! empty( $this->id );
	}

	/**
	 * Get the event ID.
	 *
	 * @since 2.0
	 *
	 * @return int The event ID.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the event title.
	 *
	 * @since 2.0
	 *
	 * @return string The event title.
	 */
	public function get_title() {
		return $this->exists() ? get_the_title( $this->id ) : '';
	}

	/**
	 * Get the event permalink.
	 *
	 * @since 2.0
	 *
	 * @return string The event permalink.
	 */
	public function get_permalink() {
		return $this->exists() ? (string) get_permalink( $this->id ) : '';
	}

	/**
	 * Get the event description, used for calendar invites.
	 *
	 * @since 2.0
	 *
	 * @return string The event description.
	 */
	public function get_description() {
		if ( ! $this->exists() ) {
			return '';
		}

		$excerpt = has_excerpt( $this->id ) ? get_the_excerpt( $this->id ) : $this->post->post_content;

		return trim( wp_strip_all_tags( strip_shortcodes( $excerpt ) ) );
	}

	/**
	 * Magic getter for the event's meta values.
	 *
	 * @since 2.0
	 *
	 * @param string $name The meta key, without the pmpro_event_ prefix.
	 * @return mixed The meta value, or null if the field is not registered.
	 */
	public function __get( $name ) {
		if ( 'id' === $name ) {
			return $this->id;
		}

		if ( 'post' === $name ) {
			return $this->post;
		}

		return isset( $this->meta[ $name ] ) ? $this->meta[ $name ] : null;
	}

	/**
	 * Magic isset for the event's meta values.
	 *
	 * @since 2.0
	 *
	 * @param string $name The meta key, without the pmpro_event_ prefix.
	 * @return bool Whether the value is set.
	 */
	public function __isset( $name ) {
		return in_array( $name, array( 'id', 'post' ), true ) || isset( $this->meta[ $name ] );
	}

	/**
	 * Get the event's timezone object.
	 *
	 * @since 2.0
	 *
	 * @return DateTimeZone The event's timezone.
	 */
	public function get_timezone() {
		return pmpro_events_get_timezone( $this->__get( 'timezone' ) );
	}

	/**
	 * Get the event's start or end date as a DateTime in the event's timezone.
	 *
	 * @since 2.0
	 *
	 * @param string $which Either 'start' or 'end'.
	 * @return DateTime|false The date object, or false if the date is not set.
	 */
	public function get_date( $which = 'start' ) {
		$local = $this->__get( $which );

		if ( empty( $local ) ) {
			return false;
		}

		try {
			return new DateTime( $local, $this->get_timezone() );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Get the event's start or end date formatted for display.
	 *
	 * @since 2.0
	 *
	 * @param string $which Either 'start' or 'end'.
	 * @return string The formatted date.
	 */
	public function get_formatted_date( $which = 'start' ) {
		return pmpro_events_format_datetime( $this->__get( $which ), $this->__get( 'timezone' ), $this->__get( 'all_day' ) );
	}

	/**
	 * Get a human readable description of when the event takes place.
	 *
	 * @since 2.0
	 *
	 * @return string The formatted date range.
	 */
	public function get_date_range() {
		$start = $this->get_formatted_date( 'start' );

		if ( empty( $start ) ) {
			return '';
		}

		$end = $this->get_formatted_date( 'end' );

		if ( empty( $end ) || $end === $start ) {
			return $start;
		}

		// Drop the redundant date from the end of a single-day event.
		$start_date = $this->get_date( 'start' );
		$end_date   = $this->get_date( 'end' );
		if ( ! $this->__get( 'all_day' ) && $start_date && $end_date && $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) {
			$end = wp_date( get_option( 'time_format' ), $end_date->getTimestamp(), $this->get_timezone() );
		}

		/* translators: 1: the event's start date and time, 2: its end date and time. */
		return sprintf( _x( '%1$s to %2$s', 'event date range', 'pmpro-events' ), $start, $end );
	}

	/**
	 * Whether the event has already finished.
	 *
	 * @since 2.0
	 *
	 * @return bool Whether the event has passed.
	 */
	public function has_passed() {
		$compare = $this->__get( 'end_utc' );

		if ( empty( $compare ) ) {
			$compare = $this->__get( 'start_utc' );
		}

		if ( empty( $compare ) ) {
			return false;
		}

		return $compare < current_time( 'mysql', true );
	}

	/**
	 * Whether registration is enabled for this event.
	 *
	 * @since 2.0
	 *
	 * @return bool Whether registration is enabled.
	 */
	public function has_registration() {
		return (bool) $this->__get( 'has_registration' );
	}

	/**
	 * Get the event's capacity. 0 means unlimited.
	 *
	 * @since 2.0
	 *
	 * @return int The capacity.
	 */
	public function get_capacity() {
		return max( 0, (int) $this->__get( 'capacity' ) );
	}

	/**
	 * Get the number of active registrations for this event.
	 *
	 * The count is cached on the object. Call flush_registration_count() after
	 * writing a registration to read a fresh value.
	 *
	 * @since 2.0
	 *
	 * @return int The number of active registrations.
	 */
	public function get_registration_count() {
		if ( ! $this->exists() ) {
			return 0;
		}

		if ( null === $this->registration_count ) {
			$this->registration_count = PMProEvents_Event_Registration::get_registrations( array(
				'event_id'     => $this->id,
				'status'       => 'active',
				'return_count' => true,
			) );
		}

		return $this->registration_count;
	}

	/**
	 * Discard the cached registration count.
	 *
	 * @since 2.0
	 */
	public function flush_registration_count() {
		$this->registration_count = null;
	}

	/**
	 * Get the number of seats still available, or null when capacity is unlimited.
	 *
	 * @since 2.0
	 *
	 * @return int|null The number of seats remaining.
	 */
	public function get_seats_remaining() {
		$capacity = $this->get_capacity();

		if ( empty( $capacity ) ) {
			return null;
		}

		return max( 0, $capacity - $this->get_registration_count() );
	}

	/**
	 * Whether the event has reached its capacity.
	 *
	 * @since 2.0
	 *
	 * @return bool Whether the event is full.
	 */
	public function is_full() {
		$remaining = $this->get_seats_remaining();

		return null !== $remaining && $remaining < 1;
	}

	/**
	 * Get the active registration for a user, if any.
	 *
	 * @since 2.0
	 *
	 * @param int $user_id The user ID to check. Defaults to the current user.
	 * @return PMProEvents_Event_Registration|null The registration, or null if there isn't one.
	 */
	public function get_registration_for_user( $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $this->exists() || empty( $user_id ) ) {
			return null;
		}

		$registrations = PMProEvents_Event_Registration::get_registrations( array(
			'event_id' => $this->id,
			'user_id'  => (int) $user_id,
			'status'   => 'active',
			'limit'    => 1,
		) );

		return empty( $registrations ) ? null : $registrations[0];
	}

	/**
	 * Whether a user has access to this event under PMPro's content restrictions.
	 *
	 * @since 2.0
	 *
	 * @param int $user_id The user ID to check. Defaults to the current user.
	 * @return bool Whether the user has access.
	 */
	public function user_can_access( $user_id = null ) {
		if ( ! $this->exists() ) {
			return false;
		}

		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! function_exists( 'pmpro_has_membership_access' ) ) {
			return true;
		}

		return (bool) pmpro_has_membership_access( $this->id, $user_id );
	}

	/**
	 * Whether a user may see the meeting URL and calendar invite for this event.
	 *
	 * Registration is the ticket: an attendee gets the links. When the event
	 * isn't taking registrations there is no ticket to check, so everyone who
	 * can view the event gets them.
	 *
	 * @since 2.0
	 *
	 * @param int $user_id The user ID to check. Defaults to the current user.
	 * @return bool Whether the user may see the event links.
	 */
	public function user_can_view_links( $user_id = null ) {
		if ( ! $this->has_registration() ) {
			return $this->user_can_access( $user_id );
		}

		return ! empty( $this->get_registration_for_user( $user_id ) );
	}

	/**
	 * Whether the event is virtual.
	 *
	 * @since 2.0
	 *
	 * @return bool Whether the event is virtual.
	 */
	public function is_virtual() {
		return $this->__get( 'has_location' ) && 'virtual' === $this->__get( 'location_type' );
	}

	/**
	 * Whether the event has an in-person location.
	 *
	 * @since 2.0
	 *
	 * @return bool Whether the event is in person.
	 */
	public function is_in_person() {
		return $this->__get( 'has_location' ) && 'in_person' === $this->__get( 'location_type' );
	}

	/**
	 * Get a single-line location string for display and calendar invites.
	 *
	 * The virtual meeting URL is deliberately excluded — it is only shown to
	 * users who pass user_can_view_links().
	 *
	 * @since 2.0
	 *
	 * @return string The location.
	 */
	public function get_location_summary() {
		if ( $this->is_virtual() ) {
			return __( 'Online', 'pmpro-events' );
		}

		if ( ! $this->is_in_person() ) {
			return '';
		}

		$parts = array_filter( array( $this->__get( 'venue_name' ), $this->__get( 'venue_address' ) ) );

		return implode( ', ', array_map( 'trim', $parts ) );
	}

	/**
	 * Get the event's UUID, used as the stable identifier in calendar invites.
	 *
	 * Generated on first use for events saved before this meta existed.
	 *
	 * @since 2.0
	 *
	 * @return string The UUID.
	 */
	public function get_uuid() {
		if ( ! $this->exists() ) {
			return '';
		}

		$uuid = $this->__get( 'uuid' );

		if ( empty( $uuid ) ) {
			$uuid = wp_generate_uuid4();
			update_post_meta( $this->id, 'pmpro_event_uuid', $uuid );
			$this->meta['uuid'] = $uuid;
		}

		return $uuid;
	}
}
