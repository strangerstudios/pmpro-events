<?php
/**
 * Registration handler via Action Scheduler.
 *
 * @package PMPro_Events
 * @since 2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue registration job after checkout.
 *
 * @since 2.0
 *
 * @param int $user_id User ID.
 */
function pmpro_events_after_checkout( $user_id ) {
	// Get the level the user just checked out for.
	$checkout_level = pmpro_getMembershipLevelForUser( $user_id );
	if ( ! $checkout_level ) {
		return;
	}

	$level_id = $checkout_level->id;

	// Find events linked to this level.
	global $wpdb;
	$events = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, post_id FROM {$wpdb->prefix}pmpro_events
		WHERE level_id = %d OR member_level_id = %d",
		$level_id,
		$level_id
	) );

	if ( empty( $events ) ) {
		return;
	}

	// Get the order.
	$order = new MemberOrder();
	$order->getLastMemberOrder( $user_id );
	$order_id = $order->id ?? 0;

	foreach ( $events as $event_row ) {
		// Schedule via Action Scheduler if available.
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				'pmpro_events_register_attendee',
				array(
					'event_id' => (int) $event_row->id,
					'user_id'  => $user_id,
					'order_id' => $order_id,
				),
				'pmpro_events'
			);
		} else {
			// Fallback: register immediately.
			pmpro_events_process_registration( (int) $event_row->id, $user_id, $order_id );
		}
	}
}
add_action( 'pmpro_after_checkout', 'pmpro_events_after_checkout' );

/**
 * Action Scheduler callback to process a registration.
 *
 * @since 2.0
 *
 * @param int $event_id Event row ID.
 * @param int $user_id  User ID.
 * @param int $order_id PMPro order ID.
 */
function pmpro_events_handle_async_registration( $event_id, $user_id, $order_id ) {
	pmpro_events_process_registration( $event_id, $user_id, $order_id );
}
add_action( 'pmpro_events_register_attendee', 'pmpro_events_handle_async_registration', 10, 3 );

/**
 * Process a single registration.
 *
 * @since 2.0
 *
 * @param int $event_id Event row ID.
 * @param int $user_id  User ID.
 * @param int $order_id PMPro order ID.
 * @return bool True if registration was created.
 */
function pmpro_events_process_registration( $event_id, $user_id, $order_id ) {
	$event = PMPro_Event::get_by_id( $event_id );
	if ( ! $event ) {
		return false;
	}

	// Already registered?
	if ( $event->is_user_registered( $user_id ) ) {
		return false;
	}

	// Check capacity.
	if ( ! $event->has_capacity() ) {
		/**
		 * Fires when registration is rejected due to capacity.
		 *
		 * @since 2.0
		 *
		 * @param PMPro_Event $event   Event object.
		 * @param int         $user_id User ID.
		 */
		do_action( 'pmpro_events_registration_full', $event, $user_id );
		return false;
	}

	// Insert registration.
	global $wpdb;
	$result = $wpdb->insert(
		$wpdb->prefix . 'pmpro_event_registrations',
		array(
			'event_id'      => $event->id,
			'post_id'       => $event->post_id,
			'user_id'       => $user_id,
			'order_id'      => $order_id,
			'status'        => 'active',
			'registered_at' => current_time( 'mysql' ),
		)
	);

	if ( $result ) {
		/**
		 * Fires after a successful event registration.
		 *
		 * @since 2.0
		 *
		 * @param PMPro_Event $event   Event object.
		 * @param int         $user_id User ID.
		 * @param int         $order_id Order ID.
		 */
		do_action( 'pmpro_events_registration_complete', $event, $user_id, $order_id );
		return true;
	}

	return false;
}

/**
 * One-click registration for users who already have the event level.
 *
 * @since 2.0
 */
function pmpro_events_handle_one_click_registration() {
	if ( ! isset( $_POST['pmpro_events_register_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['pmpro_events_register_nonce'], 'pmpro_events_register' ) ) {
		return;
	}

	$post_id = absint( $_POST['pmpro_events_post_id'] ?? 0 );
	$user_id = get_current_user_id();

	if ( ! $post_id || ! $user_id ) {
		return;
	}

	$event = PMPro_Event::get_by_post_id( $post_id );
	if ( ! $event ) {
		return;
	}

	// Verify user has the level.
	$user_levels = pmpro_getMembershipLevelsForUser( $user_id );
	$user_level_ids = wp_list_pluck( $user_levels, 'id' );

	$has_access = in_array( $event->level_id, $user_level_ids ) || in_array( $event->member_level_id, $user_level_ids );
	if ( ! $has_access ) {
		return;
	}

	pmpro_events_process_registration( $event->id, $user_id, 0 );

	wp_safe_redirect( get_permalink( $post_id ) . '?registered=1' );
	exit;
}
add_action( 'init', 'pmpro_events_handle_one_click_registration' );

/**
 * Add "My Events" to the PMPro Account page.
 *
 * @since 2.0
 */
function pmpro_events_account_section() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	global $wpdb;
	$registrations = $wpdb->get_results( $wpdb->prepare(
		"SELECT r.*, e.start, e.timezone, e.all_day
		FROM {$wpdb->prefix}pmpro_event_registrations r
		INNER JOIN {$wpdb->prefix}pmpro_events e ON r.event_id = e.id
		WHERE r.user_id = %d AND r.status = 'active' AND e.end_utc >= %s
		ORDER BY e.start_utc ASC",
		$user_id,
		current_time( 'mysql', true )
	) );

	if ( empty( $registrations ) ) {
		return;
	}

	$singular = pmpro_events_get_singular_name();
	$plural   = pmpro_events_get_plural_name();
	?>
	<div id="pmpro_account-events" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card', 'pmpro_account-events' ) ); ?>">
		<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>">
			<?php
			/* translators: %s: plural event name */
			printf( esc_html__( 'My %s', 'pmpro-events' ), esc_html( $plural ) );
			?>
		</h2>
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
			<table class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_table' ) ); ?>">
				<thead>
					<tr>
						<th><?php echo esc_html( $singular ); ?></th>
						<th><?php esc_html_e( 'Date', 'pmpro-events' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pmpro-events' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $registrations as $reg ) :
						$event = PMPro_Event::get_by_id( $reg->event_id );
						$post  = get_post( $reg->post_id );
						if ( ! $event || ! $post ) continue;
						?>
						<tr>
							<td><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( $post->post_title ); ?></a></td>
							<td><?php echo esc_html( $event->get_schedule_details() ); ?></td>
							<td><?php esc_html_e( 'Confirmed', 'pmpro-events' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}
add_action( 'pmpro_account_bullets_bottom', 'pmpro_events_account_section' );
