<?php
/**
 * The Registrations admin page, its add/remove actions, and its CSV export.
 *
 * @since 2.0
 */

/**
 * Get the capability required to view and manage registrations.
 *
 * Managing other people's registrations is a member-management task, so it sits
 * above the bar for simply editing an event.
 *
 * @since 2.0
 *
 * @return string The capability.
 */
function pmpro_events_get_registrations_capability() {
	return apply_filters( 'pmpro_events_registrations_capability', 'edit_others_posts' );
}

/**
 * Get the URL for a row action on a registration.
 *
 * @since 2.0
 *
 * @param string $action          One of 'cancel', 'reactivate', or 'delete'.
 * @param int    $registration_id The registration to act on.
 * @param int    $event_id        The event being viewed, so we can return to it.
 * @return string The action URL.
 */
function pmpro_events_get_registration_action_url( $action, $registration_id, $event_id ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'              => 'pmpro_events_registration_action',
				'registration_action' => $action,
				'registration_id'     => (int) $registration_id,
				'event_id'            => (int) $event_id,
			),
			admin_url( 'admin-post.php' )
		),
		'pmpro_events_registration_action_' . (int) $registration_id,
		'pmpro_events_nonce'
	);
}

/**
 * Add the Registrations page under the Events menu.
 *
 * @since 2.0
 */
function pmpro_events_add_registrations_page() {
	add_submenu_page(
		'edit.php?post_type=' . PMProEvents_Event::POST_TYPE,
		__( 'Registrations', 'pmpro-events' ),
		__( 'Registrations', 'pmpro-events' ),
		pmpro_events_get_registrations_capability(),
		'pmpro-event-registrations',
		'pmpro_events_registrations_page'
	);
}
add_action( 'admin_menu', 'pmpro_events_add_registrations_page' );

/**
 * Get the notice to show for the current request.
 *
 * @since 2.0
 *
 * @return array|null Array with 'type' and 'text', or null when there is nothing to show.
 */
function pmpro_events_get_admin_notice() {
	if ( empty( $_REQUEST['pmpro_events_notice'] ) ) {
		return null;
	}

	$singular = pmpro_events_get_label( 'singular_lowercase' );

	switch ( sanitize_key( wp_unslash( $_REQUEST['pmpro_events_notice'] ) ) ) {
		case 'added':
			return array( 'type' => 'success', 'text' => __( 'Registration added.', 'pmpro-events' ) );
		case 'cancelled':
			return array( 'type' => 'success', 'text' => __( 'Registration cancelled. The spot is now available.', 'pmpro-events' ) );
		case 'reactivated':
			return array( 'type' => 'success', 'text' => __( 'Registration reactivated.', 'pmpro-events' ) );
		case 'deleted':
			return array( 'type' => 'success', 'text' => __( 'Registration deleted.', 'pmpro-events' ) );
		case 'no_user':
			return array( 'type' => 'error', 'text' => __( 'No user found with that username, email, or ID.', 'pmpro-events' ) );
		case 'duplicate':
			/* translators: %s: the singular event label, lowercased, e.g. "event". */
			return array( 'type' => 'error', 'text' => sprintf( __( 'That member is already registered for this %s.', 'pmpro-events' ), $singular ) );
		case 'registration_off':
			/* translators: 1: the singular event label, lowercased, 2: the same label repeated. */
			return array( 'type' => 'error', 'text' => sprintf( __( 'Registration is turned off for this %1$s. Enable it on the %2$s edit screen first.', 'pmpro-events' ), $singular, $singular ) );
		case 'error':
			return array( 'type' => 'error', 'text' => __( 'Something went wrong. Please try again.', 'pmpro-events' ) );
	}

	return null;
}

/**
 * Get the events to offer in the registrations page picker.
 *
 * The list is capped so that the page stays usable on a site with thousands of
 * events. The event currently being viewed is always included, so arriving from
 * a row action on an older event still shows it selected.
 *
 * @since 2.0
 *
 * @param int $event_id The event currently being viewed, if any.
 * @return PMProEvents_Event[] The events to list.
 */
function pmpro_events_get_picker_events( $event_id = 0 ) {
	/**
	 * Filter how many events the registrations page picker lists.
	 *
	 * @since 2.0
	 *
	 * @param int $limit The maximum number of events to list.
	 */
	$limit = (int) apply_filters( 'pmpro_events_picker_limit', 200 );

	$statuses = array( 'publish', 'draft', 'private', 'pending', 'future' );

	$events = PMProEvents_Event::get_events( array(
		'order'       => 'DESC',
		'limit'       => $limit,
		'post_status' => $statuses,
	) );

	// Put the event being viewed at the top if the cap left it out.
	$event_id = (int) $event_id;
	if ( ! empty( $event_id ) ) {
		foreach ( $events as $event ) {
			if ( $event->get_id() === $event_id ) {
				return $events;
			}
		}

		$current = new PMProEvents_Event( $event_id );
		if ( $current->exists() ) {
			array_unshift( $events, $current );
		}
	}

	return $events;
}

/**
 * Render the Registrations page.
 *
 * @since 2.0
 */
function pmpro_events_registrations_page() {
	if ( ! current_user_can( pmpro_events_get_registrations_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to view registrations.', 'pmpro-events' ) );
	}

	require_once( PMPRO_EVENTS_DIR . '/modules/default/class-pmproevents-registrations-list-table.php' );

	$event_id = isset( $_REQUEST['event_id'] ) ? (int) $_REQUEST['event_id'] : 0;
	$event    = $event_id ? new PMProEvents_Event( $event_id ) : null;

	$all_events = pmpro_events_get_picker_events( $event_id );
	$singular   = pmpro_events_get_label( 'singular' );

	$notice = pmpro_events_get_admin_notice();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Registrations', 'pmpro-events' ); ?></h1>

		<?php if ( ! empty( $notice ) ) { ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
				<p><?php echo esc_html( $notice['text'] ); ?></p>
			</div>
		<?php } ?>

		<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( PMProEvents_Event::POST_TYPE ); ?>" />
			<input type="hidden" name="page" value="pmpro-event-registrations" />
			<label for="pmpro_events_event_id" class="screen-reader-text">
				<?php
				/* translators: %s: the singular event label, e.g. "Event". */
				echo esc_html( sprintf( __( '%s to show registrations for', 'pmpro-events' ), $singular ) );
				?>
			</label>
			<select name="event_id" id="pmpro_events_event_id">
				<option value="0">
					<?php
					/* translators: %s: the singular event label, e.g. "Event". */
					echo esc_html( sprintf( __( '— Select %s —', 'pmpro-events' ), $singular ) );
					?>
				</option>
				<?php foreach ( $all_events as $option ) { ?>
					<option value="<?php echo esc_attr( $option->get_id() ); ?>" <?php selected( $event_id, $option->get_id() ); ?>>
						<?php
						$start = $option->get_formatted_date( 'start' );
						echo esc_html( empty( $start ) ? $option->get_title() : $option->get_title() . ' — ' . $start );
						?>
					</option>
				<?php } ?>
			</select>
			<?php submit_button( __( 'View', 'pmpro-events' ), 'secondary', '', false ); ?>
		</form>

		<?php if ( empty( $event ) || ! $event->exists() ) { ?>

			<p>
				<?php
				/* translators: %s: the plural event label, lowercased, e.g. "events". */
				echo esc_html( sprintf( __( 'Choose from the %s above to see who has registered.', 'pmpro-events' ), pmpro_events_get_label( 'plural_lowercase' ) ) );
				?>
			</p>

		<?php } else {
			$count    = $event->get_registration_count();
			$capacity = $event->get_capacity();
			?>

		<h2>
			<?php echo esc_html( $event->get_title() ); ?>
			<a href="<?php echo esc_url( get_edit_post_link( $event->get_id() ) ); ?>" class="page-title-action"><?php esc_html_e( 'Edit', 'pmpro-events' ); ?></a>
			<a href="<?php echo esc_url( pmpro_events_get_registrations_export_url( $event->get_id() ) ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'pmpro-events' ); ?></a>
		</h2>

		<p class="description">
			<?php
			if ( empty( $capacity ) ) {
				/* translators: %s: the number of registrations. */
				echo esc_html( sprintf( _n( '%s registration. Capacity is unlimited.', '%s registrations. Capacity is unlimited.', $count, 'pmpro-events' ), number_format_i18n( $count ) ) );
			} else {
				/* translators: 1: the number of registrations, 2: the event's capacity. */
				echo esc_html( sprintf( __( '%1$s of %2$s spots filled.', 'pmpro-events' ), number_format_i18n( $count ), number_format_i18n( $capacity ) ) );
			}
			?>
		</p>

		<div class="pmpro_events_add_registration">
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="pmpro_events_add_registration" />
				<input type="hidden" name="event_id" value="<?php echo esc_attr( $event->get_id() ); ?>" />
				<?php wp_nonce_field( 'pmpro_events_add_registration_' . $event->get_id(), 'pmpro_events_nonce' ); ?>
				<label for="pmpro_events_add_user"><strong><?php esc_html_e( 'Add a registration', 'pmpro-events' ); ?></strong></label>
				<input type="hidden" name="pmpro_events_user_id" value="" />
				<input type="text" id="pmpro_events_add_user" name="pmpro_events_user" class="regular-text" placeholder="<?php esc_attr_e( 'Search by name, username, email, or ID', 'pmpro-events' ); ?>" autocomplete="off" required />
				<?php submit_button( __( 'Add Registration', 'pmpro-events' ), 'secondary', '', false ); ?>
				<?php if ( ! empty( $capacity ) && $event->is_full() ) { ?>
					<p class="description"><?php esc_html_e( 'This event is at capacity. Adding a registration here will overbook it.', 'pmpro-events' ); ?></p>
				<?php } ?>
			</form>
		</div>

		<?php
		$list_table = new PMProEvents_Registrations_List_Table( $event );
		$list_table->prepare_items();
		$list_table->views();
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( PMProEvents_Event::POST_TYPE ); ?>" />
			<input type="hidden" name="page" value="pmpro-event-registrations" />
			<input type="hidden" name="event_id" value="<?php echo esc_attr( $event->get_id() ); ?>" />
			<input type="hidden" name="status" value="<?php echo esc_attr( isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : 'active' ); ?>" />
			<?php $list_table->display(); ?>
		</form>

		<?php } ?>
	</div>
	<?php
}

/**
 * Enqueue the stylesheet and the user picker on the registrations page.
 *
 * @since 2.0
 *
 * @param string $hook_suffix The current admin page.
 */
function pmpro_events_registrations_enqueue_scripts( $hook_suffix ) {
	if ( false === strpos( $hook_suffix, 'pmpro-event-registrations' ) ) {
		return;
	}

	pmpro_events_enqueue_admin_style();

	wp_enqueue_script(
		'pmpro-events-registrations',
		PMPRO_EVENTS_URL . '/js/registrations-admin.js',
		array( 'wp-i18n' ),
		PMPRO_EVENTS_VERSION,
		true
	);
	wp_set_script_translations( 'pmpro-events-registrations', 'pmpro-events', PMPRO_EVENTS_DIR . '/languages' );
	wp_localize_script(
		'pmpro-events-registrations',
		'pmproEventsRegistrations',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pmpro_events_search_users' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'pmpro_events_registrations_enqueue_scripts' );

/**
 * Send the admin back to the registrations page with a notice.
 *
 * @since 2.0
 *
 * @param int    $event_id The event to return to.
 * @param string $notice   The notice key to show.
 */
function pmpro_events_redirect_to_registrations( $event_id, $notice ) {
	wp_safe_redirect( add_query_arg( 'pmpro_events_notice', $notice, pmpro_events_get_registrations_url( $event_id ) ) );
	exit;
}

/**
 * Resolve a username, email address, or user ID to a user.
 *
 * @since 2.0
 *
 * @param string $identifier The value typed into the add-registration field.
 * @return WP_User|false The user, or false if nothing matched.
 */
function pmpro_events_find_user( $identifier ) {
	$identifier = trim( $identifier );

	if ( empty( $identifier ) ) {
		return false;
	}

	if ( is_email( $identifier ) ) {
		$user = get_user_by( 'email', $identifier );
		if ( ! empty( $user ) ) {
			return $user;
		}
	}

	$user = get_user_by( 'login', $identifier );
	if ( ! empty( $user ) ) {
		return $user;
	}

	if ( ctype_digit( $identifier ) ) {
		$user = get_user_by( 'id', (int) $identifier );
		if ( ! empty( $user ) ) {
			return $user;
		}
	}

	return false;
}

/**
 * Search members for the add-registration picker.
 *
 * Returns enough context for the admin to be sure they picked the right
 * person: name, email, membership level, and whether they already hold a spot.
 *
 * This is a custom endpoint rather than the REST users endpoint because that
 * requires list_users, a higher bar than the registrations capability.
 *
 * @since 2.0
 */
function pmpro_events_ajax_search_users() {
	check_ajax_referer( 'pmpro_events_search_users', 'nonce' );

	if ( ! current_user_can( pmpro_events_get_registrations_capability() ) ) {
		wp_send_json_error( null, 403 );
	}

	$term     = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
	$event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;

	if ( strlen( $term ) < 2 ) {
		wp_send_json_success( array() );
	}

	$user_query = new WP_User_Query( array(
		'search'         => '*' . $term . '*',
		'search_columns' => array( 'user_login', 'user_email', 'user_nicename', 'display_name' ),
		'number'         => 10,
		'orderby'        => 'display_name',
		'order'          => 'ASC',
	) );

	$users = $user_query->get_results();

	// A numeric term is probably a user ID, which the column search won't match.
	if ( ctype_digit( $term ) ) {
		$exact = get_user_by( 'id', (int) $term );
		if ( ! empty( $exact ) && ! in_array( $exact->ID, wp_list_pluck( $users, 'ID' ), true ) ) {
			array_unshift( $users, $exact );
		}
	}

	$results = array();
	foreach ( $users as $user ) {
		$levels      = function_exists( 'pmpro_getMembershipLevelsForUser' ) ? pmpro_getMembershipLevelsForUser( $user->ID ) : array();
		$level_names = empty( $levels ) ? array() : wp_list_pluck( $levels, 'name' );

		$registered = ! empty( PMProEvents_Event_Registration::get_registrations( array(
			'event_id' => $event_id,
			'user_id'  => $user->ID,
			'status'   => 'active',
			'limit'    => 1,
		) ) );

		$results[] = array(
			'id'         => $user->ID,
			'name'       => $user->display_name,
			'login'      => $user->user_login,
			'email'      => $user->user_email,
			'avatar'     => get_avatar_url( $user->ID, array( 'size' => 64 ) ),
			'level'      => implode( ', ', $level_names ),
			'registered' => $registered,
		);
	}

	wp_send_json_success( $results );
}
add_action( 'wp_ajax_pmpro_events_search_users', 'pmpro_events_ajax_search_users' );

/**
 * Add a registration from the admin.
 *
 * Admins can overbook a full event deliberately, so the capacity check is
 * bypassed here. The page warns before it happens.
 *
 * @since 2.0
 */
function pmpro_events_admin_add_registration() {
	if ( ! current_user_can( pmpro_events_get_registrations_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to add registrations.', 'pmpro-events' ) );
	}

	$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;

	check_admin_referer( 'pmpro_events_add_registration_' . $event_id, 'pmpro_events_nonce' );

	$event = new PMProEvents_Event( $event_id );

	if ( ! $event->exists() ) {
		pmpro_events_redirect_to_registrations( $event_id, 'error' );
	}

	// create() refuses to register anyone when the event has registration off.
	if ( ! $event->has_registration() ) {
		pmpro_events_redirect_to_registrations( $event_id, 'registration_off' );
	}

	// The picker resolves the user to an ID. The text field is the no-JS fallback.
	$user    = false;
	$user_id = isset( $_POST['pmpro_events_user_id'] ) ? (int) $_POST['pmpro_events_user_id'] : 0;
	if ( $user_id > 0 ) {
		$user = get_user_by( 'id', $user_id );
	}

	if ( empty( $user ) ) {
		$user = pmpro_events_find_user( isset( $_POST['pmpro_events_user'] ) ? sanitize_text_field( wp_unslash( $_POST['pmpro_events_user'] ) ) : '' );
	}

	if ( empty( $user ) ) {
		pmpro_events_redirect_to_registrations( $event_id, 'no_user' );
	}

	if ( ! empty( $event->get_registration_for_user( $user->ID ) ) ) {
		pmpro_events_redirect_to_registrations( $event_id, 'duplicate' );
	}

	$registration = PMProEvents_Event_Registration::create( $event_id, $user->ID, array( 'bypass_capacity' => true ) );

	pmpro_events_redirect_to_registrations( $event_id, empty( $registration ) ? 'error' : 'added' );
}
add_action( 'admin_post_pmpro_events_add_registration', 'pmpro_events_admin_add_registration' );

/**
 * Cancel, reactivate, or delete a registration from the admin.
 *
 * @since 2.0
 */
function pmpro_events_admin_registration_action() {
	if ( ! current_user_can( pmpro_events_get_registrations_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to manage registrations.', 'pmpro-events' ) );
	}

	$registration_id = isset( $_REQUEST['registration_id'] ) ? (int) $_REQUEST['registration_id'] : 0;
	$event_id        = isset( $_REQUEST['event_id'] ) ? (int) $_REQUEST['event_id'] : 0;

	check_admin_referer( 'pmpro_events_registration_action_' . $registration_id, 'pmpro_events_nonce' );

	$registration = new PMProEvents_Event_Registration( $registration_id );

	if ( empty( $registration->id ) ) {
		pmpro_events_redirect_to_registrations( $event_id, 'error' );
	}

	$action = isset( $_REQUEST['registration_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['registration_action'] ) ) : '';

	switch ( $action ) {
		case 'cancel':
			$notice = $registration->cancel() ? 'cancelled' : 'error';
			break;

		case 'reactivate':
			// Reactivating from the admin can overbook, same as adding.
			$notice = $registration->update_status( 'active' ) ? 'reactivated' : 'error';
			break;

		case 'delete':
			$notice = $registration->delete() ? 'deleted' : 'error';
			break;

		default:
			$notice = 'error';
			break;
	}

	pmpro_events_redirect_to_registrations( $event_id, $notice );
}
add_action( 'admin_post_pmpro_events_registration_action', 'pmpro_events_admin_registration_action' );

/**
 * Get the CSV export URL for an event's registrations.
 *
 * @since 2.0
 *
 * @param int $event_id The event ID.
 * @return string The export URL.
 */
function pmpro_events_get_registrations_export_url( $event_id ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'   => 'pmpro_events_export_registrations',
				'event_id' => (int) $event_id,
			),
			admin_url( 'admin-post.php' )
		),
		'pmpro_events_export_' . (int) $event_id,
		'pmpro_events_nonce'
	);
}

/**
 * Neutralize a CSV value that a spreadsheet would run as a formula.
 *
 * Excel and Google Sheets execute a cell that opens with =, +, - or @, and a
 * display name or username is attacker-supplied on an open-registration site.
 * A leading tab keeps the value readable while stopping it being evaluated.
 *
 * @since 2.0
 *
 * @param mixed $value The value to write.
 * @return string The value, safe to write to a CSV cell.
 */
function pmpro_events_csv_escape( $value ) {
	$value = (string) $value;

	if ( '' !== $value && false !== strpos( '=+-@', $value[0] ) ) {
		return "\t" . $value;
	}

	return $value;
}

/**
 * Stream the registrations for an event as a CSV file.
 *
 * @since 2.0
 */
function pmpro_events_export_registrations() {
	if ( ! current_user_can( pmpro_events_get_registrations_capability() ) ) {
		wp_die( esc_html__( 'You do not have permission to export registrations.', 'pmpro-events' ) );
	}

	$event_id = isset( $_REQUEST['event_id'] ) ? (int) $_REQUEST['event_id'] : 0;

	check_admin_referer( 'pmpro_events_export_' . $event_id, 'pmpro_events_nonce' );

	$event = new PMProEvents_Event( $event_id );

	if ( ! $event->exists() ) {
		wp_die( esc_html__( 'Event not found.', 'pmpro-events' ), '', array( 'response' => 404 ) );
	}

	$registrations = PMProEvents_Event_Registration::get_registrations( array(
		'event_id' => $event_id,
		'orderby'  => 'registered_at',
		'order'    => 'ASC',
	) );

	$filename = sanitize_file_name( get_post_field( 'post_name', $event_id ) );
	if ( empty( $filename ) ) {
		$filename = 'event-' . $event_id;
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '-registrations.csv"' );

	$output = fopen( 'php://output', 'w' );

	$columns = apply_filters( 'pmpro_events_export_registrations_columns', array(
		'registration_id' => __( 'Registration ID', 'pmpro-events' ),
		'user_id'         => __( 'User ID', 'pmpro-events' ),
		'username'        => __( 'Username', 'pmpro-events' ),
		'display_name'    => __( 'Name', 'pmpro-events' ),
		'email'           => __( 'Email', 'pmpro-events' ),
		'status'          => __( 'Status', 'pmpro-events' ),
		'registered_at'   => __( 'Registered (UTC)', 'pmpro-events' ),
	) );

	fputcsv( $output, array_values( $columns ) );

	foreach ( $registrations as $registration ) {
		$user = $registration->get_user();

		$row = array(
			'registration_id' => $registration->id,
			'user_id'         => $registration->user_id,
			'username'        => empty( $user ) ? '' : $user->user_login,
			'display_name'    => empty( $user ) ? '' : $user->display_name,
			'email'           => empty( $user ) ? '' : $user->user_email,
			'status'          => $registration->status,
			'registered_at'   => $registration->registered_at,
		);

		/**
		 * Filter a single row of the registrations CSV export.
		 *
		 * @since 2.0
		 *
		 * @param array                          $row          The row, keyed to match the columns.
		 * @param PMProEvents_Event_Registration $registration The registration.
		 * @param PMProEvents_Event              $event        The event.
		 */
		$row = apply_filters( 'pmpro_events_export_registrations_row', $row, $registration, $event );

		fputcsv( $output, array_map( 'pmpro_events_csv_escape', array_values( $row ) ) );
	}

	fclose( $output );
	exit;
}
add_action( 'admin_post_pmpro_events_export_registrations', 'pmpro_events_export_registrations' );
