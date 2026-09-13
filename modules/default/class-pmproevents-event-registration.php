<?php
/**
 * The PMPro Event Registration object.
 *
 * Every write to the pmpro_event_registrations table goes through this class.
 * Nothing else should insert or update rows directly.
 *
 * @since 2.0
 */
class PMProEvents_Event_Registration {
	/**
	 * The ID of the registration.
	 *
	 * @since 2.0
	 *
	 * @var int
	 */
	protected $id = 0;

	/**
	 * The event ID that this registration is for.
	 *
	 * @since 2.0
	 *
	 * @var int
	 */
	protected $event_id = 0;

	/**
	 * The user ID that this registration belongs to.
	 *
	 * @since 2.0
	 *
	 * @var int
	 */
	protected $user_id = 0;

	/**
	 * The status of the registration.
	 * 'active' if the user holds a spot, 'cancelled' if they gave it up.
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	protected $status = '';

	/**
	 * The datetime when the registration was created, in UTC.
	 *
	 * @since 2.0
	 *
	 * @var string
	 */
	protected $registered_at = '';

	/**
	 * Get a registration object by ID.
	 *
	 * @since 2.0
	 *
	 * @param int $registration_id The registration ID to populate.
	 */
	public function __construct( $registration_id ) {
		global $wpdb;

		if ( ! is_numeric( $registration_id ) ) {
			return;
		}

		$data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->pmpro_event_registrations} WHERE id = %d",
				(int) $registration_id
			)
		);

		if ( ! empty( $data ) ) {
			$this->id            = (int) $data->id;
			$this->event_id      = (int) $data->event_id;
			$this->user_id       = (int) $data->user_id;
			$this->status        = $data->status;
			$this->registered_at = $data->registered_at;
		}
	}

	/**
	 * Get the list of registrations based on passed query arguments.
	 *
	 * @since 2.0
	 *
	 * @param array $args {
	 *     Optional. Query arguments.
	 *
	 *     @type int|int[] $event_id     Limit to one or more event IDs.
	 *     @type int|int[] $user_id      Limit to one or more user IDs.
	 *     @type string    $status       Limit to a status, 'active' or 'cancelled'.
	 *     @type int       $limit        Number of registrations to return. 0 for all.
	 *     @type int       $offset       Number of registrations to skip.
	 *     @type string    $orderby      Column to order by. Default 'registered_at'.
	 *     @type string    $order        'ASC' or 'DESC'. Default 'DESC'.
	 *     @type bool      $return_count Whether to return a count instead of objects.
	 * }
	 * @return PMProEvents_Event_Registration[]|int The registrations, or the count if $args['return_count'] is true.
	 */
	public static function get_registrations( $args = array() ) {
		global $wpdb;

		$sql_query = empty( $args['return_count'] )
			? "SELECT id FROM {$wpdb->pmpro_event_registrations}"
			: "SELECT COUNT(id) FROM {$wpdb->pmpro_event_registrations}";

		$limit  = empty( $args['limit'] ) ? 0 : (int) $args['limit'];
		$offset = empty( $args['offset'] ) ? 0 : (int) $args['offset'];

		$prepared = array();
		$where    = array();

		// Filter by ID.
		if ( isset( $args['id'] ) ) {
			$where[]    = 'id = %d';
			$prepared[] = (int) $args['id'];
		}

		// Filter by event ID.
		if ( isset( $args['event_id'] ) ) {
			if ( is_array( $args['event_id'] ) ) {
				if ( empty( $args['event_id'] ) ) {
					return empty( $args['return_count'] ) ? array() : 0;
				}
				$placeholders = implode( ',', array_fill( 0, count( $args['event_id'] ), '%d' ) );
				$where[]      = "event_id IN ($placeholders)";
				$prepared     = array_merge( $prepared, array_map( 'intval', $args['event_id'] ) );
			} else {
				$where[]    = 'event_id = %d';
				$prepared[] = (int) $args['event_id'];
			}
		}

		// Filter by user ID.
		if ( isset( $args['user_id'] ) ) {
			if ( is_array( $args['user_id'] ) ) {
				if ( empty( $args['user_id'] ) ) {
					return empty( $args['return_count'] ) ? array() : 0;
				}
				$placeholders = implode( ',', array_fill( 0, count( $args['user_id'] ), '%d' ) );
				$where[]      = "user_id IN ($placeholders)";
				$prepared     = array_merge( $prepared, array_map( 'intval', $args['user_id'] ) );
			} else {
				$where[]    = 'user_id = %d';
				$prepared[] = (int) $args['user_id'];
			}
		}

		// Filter by status.
		if ( isset( $args['status'] ) ) {
			$where[]    = 'status = %s';
			$prepared[] = $args['status'];
		}

		// Maybe filter the data.
		if ( ! empty( $where ) ) {
			$sql_query .= ' WHERE ' . implode( ' AND ', $where );
		}

		// If we're not counting, order the results and add pagination.
		if ( empty( $args['return_count'] ) ) {
			$allowed_orderby = array( 'id', 'event_id', 'user_id', 'status', 'registered_at' );
			$orderby         = isset( $args['orderby'] ) && in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'registered_at';
			$order           = isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

			$sql_query .= ' ORDER BY ' . $orderby . ' ' . $order . ', id ' . $order;

			if ( ! empty( $limit ) ) {
				$sql_query .= ' LIMIT %d OFFSET %d';
				$prepared[] = $limit;
				$prepared[] = $offset;
			}
		}

		// Prepare the query.
		if ( ! empty( $prepared ) ) {
			$sql_query = $wpdb->prepare( $sql_query, $prepared );
		}

		// If we're just counting, return the count.
		if ( ! empty( $args['return_count'] ) ) {
			return (int) $wpdb->get_var( $sql_query );
		}

		$registration_ids = $wpdb->get_col( $sql_query );

		if ( empty( $registration_ids ) ) {
			return array();
		}

		$registrations = array();
		foreach ( $registration_ids as $registration_id ) {
			$registration = new self( (int) $registration_id );
			if ( ! empty( $registration->id ) ) {
				$registrations[] = $registration;
			}
		}

		return $registrations;
	}

	/**
	 * Register a user for an event.
	 *
	 * This is the only method that creates registrations. It enforces the
	 * event's capacity, prevents duplicate registrations, and reactivates a
	 * previously cancelled registration rather than inserting a second row.
	 *
	 * @since 2.0
	 *
	 * @param int   $event_id The event to register for.
	 * @param int   $user_id  The user to register.
	 * @param array $args {
	 *     Optional. Additional options.
	 *
	 *     @type bool $bypass_capacity Whether to allow the registration past the
	 *                                 event's capacity. Used by the admin screens,
	 *                                 where overbooking is a deliberate choice.
	 *                                 Default false.
	 * }
	 * @return PMProEvents_Event_Registration|false The registration, or false on failure.
	 */
	public static function create( $event_id, $user_id, $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array( 'bypass_capacity' => false ) );

		$event_id = (int) $event_id;
		$user_id  = (int) $user_id;

		if ( $event_id <= 0 || $user_id <= 0 ) {
			return false;
		}

		// Bail if the event doesn't exist.
		$event = new PMProEvents_Event( $event_id );
		if ( ! $event->exists() ) {
			return false;
		}

		// Bail if registration is not enabled for this event.
		if ( ! $event->has_registration() ) {
			return false;
		}

		// Bail if the user already holds an active registration.
		$active = self::get_registrations( array(
			'event_id' => $event_id,
			'user_id'  => $user_id,
			'status'   => 'active',
			'limit'    => 1,
		) );
		if ( ! empty( $active ) ) {
			return false;
		}

		// A cancelled row is reactivated instead of inserted, since a plain
		// INSERT would fail against the event_user unique key.
		$cancelled = self::get_registrations( array(
			'event_id' => $event_id,
			'user_id'  => $user_id,
			'status'   => 'cancelled',
			'limit'    => 1,
		) );

		// Check capacity. A capacity of 0 means unlimited.
		if ( empty( $args['bypass_capacity'] ) && $event->is_full() ) {
			/**
			 * Fires when a user tries to register for an event that is at capacity.
			 *
			 * @since 2.0
			 *
			 * @param int $event_id The event ID.
			 * @param int $user_id  The user ID.
			 */
			do_action( 'pmpro_events_registration_full', $event_id, $user_id );
			return false;
		}

		if ( ! empty( $cancelled ) ) {
			$registration = $cancelled[0];
			if ( ! $registration->update_status( 'active' ) ) {
				return false;
			}
		} else {
			$wpdb->insert(
				$wpdb->pmpro_event_registrations,
				array(
					'event_id'      => $event_id,
					'user_id'       => $user_id,
					'status'        => 'active',
					'registered_at' => current_time( 'mysql', true ),
				),
				array(
					'%d',
					'%d',
					'%s',
					'%s',
				)
			);

			// The insert can fail if a row was created for this event and user
			// between our lookup above and this write.
			if ( empty( $wpdb->insert_id ) ) {
				return false;
			}

			$registration = new self( $wpdb->insert_id );
		}

		/**
		 * Fires after a user is successfully registered for an event.
		 *
		 * Confirmation emails and calendar invites hang here.
		 *
		 * @since 2.0
		 *
		 * @param PMProEvents_Event_Registration $registration The new registration.
		 * @param PMProEvents_Event              $event        The event that was registered for.
		 */
		do_action( 'pmpro_events_registration_complete', $registration, $event );

		return $registration;
	}

	/**
	 * Update the status of this registration.
	 *
	 * @since 2.0
	 *
	 * @param string $status The new status, 'active' or 'cancelled'.
	 * @return bool Whether the status was updated.
	 */
	public function update_status( $status ) {
		global $wpdb;

		if ( empty( $this->id ) || ! in_array( $status, array( 'active', 'cancelled' ), true ) ) {
			return false;
		}

		if ( $status === $this->status ) {
			return true;
		}

		$data    = array( 'status' => $status );
		$formats = array( '%s' );

		// Reactivating a cancelled registration starts a fresh registration date.
		if ( 'active' === $status ) {
			$data['registered_at'] = current_time( 'mysql', true );
			$formats[]             = '%s';
		}

		$updated = $wpdb->update(
			$wpdb->pmpro_event_registrations,
			$data,
			array( 'id' => $this->id ),
			$formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			return false;
		}

		$previous_status = $this->status;
		$this->status    = $status;
		if ( isset( $data['registered_at'] ) ) {
			$this->registered_at = $data['registered_at'];
		}

		/**
		 * Fires after a registration's status changes.
		 *
		 * @since 2.0
		 *
		 * @param PMProEvents_Event_Registration $registration    The registration.
		 * @param string                         $status          The new status.
		 * @param string                         $previous_status The status before this change.
		 */
		do_action( 'pmpro_events_registration_status_updated', $this, $status, $previous_status );

		return true;
	}

	/**
	 * Cancel this registration and free up the seat.
	 *
	 * @since 2.0
	 *
	 * @return bool Whether the registration was cancelled.
	 */
	public function cancel() {
		if ( ! $this->update_status( 'cancelled' ) ) {
			return false;
		}

		/**
		 * Fires after a registration is cancelled.
		 *
		 * @since 2.0
		 *
		 * @param PMProEvents_Event_Registration $registration The cancelled registration.
		 */
		do_action( 'pmpro_events_registration_cancelled', $this );

		return true;
	}

	/**
	 * Permanently delete this registration.
	 *
	 * Cancelling is usually the right call, since it keeps a record that the
	 * member had a spot. Deleting is for cleaning up mistakes.
	 *
	 * @since 2.0
	 *
	 * @return bool Whether the registration was deleted.
	 */
	public function delete() {
		global $wpdb;

		if ( empty( $this->id ) ) {
			return false;
		}

		$deleted = $wpdb->delete(
			$wpdb->pmpro_event_registrations,
			array( 'id' => $this->id ),
			array( '%d' )
		);

		if ( empty( $deleted ) ) {
			return false;
		}

		/**
		 * Fires after a registration is deleted.
		 *
		 * @since 2.0
		 *
		 * @param int $registration_id The deleted registration ID.
		 * @param int $event_id        The event the registration was for.
		 * @param int $user_id         The user the registration belonged to.
		 */
		do_action( 'pmpro_events_registration_deleted', $this->id, $this->event_id, $this->user_id );

		$this->id = 0;

		return true;
	}

	/**
	 * Get the event that this registration is for.
	 *
	 * @since 2.0
	 *
	 * @return PMProEvents_Event The event.
	 */
	public function get_event() {
		return new PMProEvents_Event( $this->event_id );
	}

	/**
	 * Get the user that this registration belongs to.
	 *
	 * @since 2.0
	 *
	 * @return WP_User|false The user, or false if the user no longer exists.
	 */
	public function get_user() {
		return get_userdata( $this->user_id );
	}

	/**
	 * Magic getter to retrieve protected properties.
	 *
	 * @since 2.0
	 *
	 * @param string $name The property to retrieve.
	 * @return mixed The property value, or null if it isn't set.
	 */
	public function __get( $name ) {
		return isset( $this->$name ) ? $this->$name : null;
	}

	/**
	 * Magic isset to check protected properties.
	 *
	 * @since 2.0
	 *
	 * @param string $name The property to check.
	 * @return bool Whether the property is set.
	 */
	public function __isset( $name ) {
		return isset( $this->$name );
	}
}
