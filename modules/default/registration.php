<?php
/**
 * Frontend registration and cancellation handling.
 *
 * @since 2.0
 */

/**
 * Get the URL that a registration form posts to.
 *
 * @since 2.0
 *
 * @return string The form action URL.
 */
function pmpro_events_get_form_action() {
	return admin_url( 'admin-post.php' );
}

/**
 * Redirect back to an event with a status message.
 *
 * @since 2.0
 *
 * @param int    $event_id The event to return to.
 * @param string $message  The message key to show.
 */
function pmpro_events_redirect_to_event( $event_id, $message ) {
	$url = get_permalink( $event_id );

	if ( empty( $url ) ) {
		$url = home_url();
	}

	// The nonce ties the message to the visitor who performed the action, so a
	// copied or shared URL doesn't replay it for someone else.
	$url = add_query_arg(
		array(
			'pmpro_events_message'       => $message,
			'pmpro_events_message_nonce' => wp_create_nonce( 'pmpro_events_message_' . $message . '_' . $event_id ),
		),
		$url
	);

	wp_safe_redirect( $url );
	exit;
}

/**
 * Handle a registration request.
 *
 * @since 2.0
 */
function pmpro_events_handle_register() {
	$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;

	if ( empty( $event_id ) ) {
		wp_safe_redirect( home_url() );
		exit;
	}

	check_admin_referer( 'pmpro_events_register_' . $event_id, 'pmpro_events_nonce' );

	if ( ! is_user_logged_in() ) {
		pmpro_events_redirect_to_event( $event_id, 'error' );
	}

	$event = new PMProEvents_Event( $event_id );

	// The event has to exist, be open for registration, and not be over.
	if ( ! $event->exists() || ! $event->has_registration() || $event->has_passed() ) {
		pmpro_events_redirect_to_event( $event_id, 'error' );
	}

	// The user has to be able to see the event to register for it.
	if ( ! $event->user_can_access() ) {
		pmpro_events_redirect_to_event( $event_id, 'no_access' );
	}

	if ( $event->is_full() ) {
		pmpro_events_redirect_to_event( $event_id, 'full' );
	}

	$registration = PMProEvents_Event_Registration::create( $event_id, get_current_user_id() );

	if ( empty( $registration ) ) {
		// create() re-checks capacity, so a failure here is most likely a race
		// for the last seat. Re-count to see whether that is what happened.
		$event->flush_registration_count();
		pmpro_events_redirect_to_event( $event_id, $event->is_full() ? 'full' : 'error' );
	}

	pmpro_events_redirect_to_event( $event_id, 'registered' );
}
add_action( 'admin_post_pmpro_events_register', 'pmpro_events_handle_register' );
add_action( 'admin_post_nopriv_pmpro_events_register', 'pmpro_events_handle_register' );

/**
 * Handle a cancellation request.
 *
 * @since 2.0
 */
function pmpro_events_handle_cancel() {
	$event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;

	if ( empty( $event_id ) ) {
		wp_safe_redirect( home_url() );
		exit;
	}

	check_admin_referer( 'pmpro_events_cancel_' . $event_id, 'pmpro_events_nonce' );

	if ( ! is_user_logged_in() ) {
		pmpro_events_redirect_to_event( $event_id, 'error' );
	}

	$event        = new PMProEvents_Event( $event_id );
	$registration = $event->exists() ? $event->get_registration_for_user() : null;

	if ( empty( $registration ) || ! $registration->cancel() ) {
		pmpro_events_redirect_to_event( $event_id, 'error' );
	}

	pmpro_events_redirect_to_event( $event_id, 'cancelled' );
}
add_action( 'admin_post_pmpro_events_cancel', 'pmpro_events_handle_cancel' );
add_action( 'admin_post_nopriv_pmpro_events_cancel', 'pmpro_events_handle_cancel' );

/**
 * Get the status message to show for the current request.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event being viewed.
 * @return array|null Array with 'class' and 'text', or null if there is no message.
 */
function pmpro_events_get_status_message( $event ) {
	if ( empty( $_REQUEST['pmpro_events_message'] ) || empty( $_REQUEST['pmpro_events_message_nonce'] ) ) {
		return null;
	}

	$message = sanitize_key( wp_unslash( $_REQUEST['pmpro_events_message'] ) );

	// Only show the message to the visitor who performed the action it
	// describes. The message names the action, so it can't be swapped for a
	// different one either.
	$nonce = sanitize_text_field( wp_unslash( $_REQUEST['pmpro_events_message_nonce'] ) );
	if ( ! wp_verify_nonce( $nonce, 'pmpro_events_message_' . $message . '_' . $event->get_id() ) ) {
		return null;
	}

	$singular = pmpro_events_get_label( 'singular_lowercase' );

	switch ( $message ) {
		case 'registered':
			return array(
				'class' => 'pmpro_success',
				/* translators: %s: the singular event label. */
				'text' => sprintf( __( 'You are registered for this %s.', 'pmpro-events' ), $singular ),
			);
		case 'cancelled':
			return array(
				'class' => 'pmpro_success',
				/* translators: %s: the singular event label. */
				'text' => sprintf( __( 'Your registration for this %s has been cancelled.', 'pmpro-events' ), $singular ),
			);
		case 'full':
			return array(
				'class' => 'pmpro_error',
				/* translators: %s: the singular event label. */
				'text' => sprintf( __( 'Sorry, this %s is now full.', 'pmpro-events' ), $singular ),
			);
		case 'no_access':
			return array(
				'class' => 'pmpro_error',
				/* translators: %s: the singular event label. */
				'text' => sprintf( __( 'You do not have access to this %s.', 'pmpro-events' ), $singular ),
			);
		case 'error':
			return array(
				'class' => 'pmpro_error',
				'text' => __( 'Something went wrong. Please try again.', 'pmpro-events' ),
			);
	}

	return null;
}

/**
 * Cancel a member's registrations when they lose access to an event.
 *
 * When a level expires or is cancelled, the seats that member was holding for
 * upcoming events are given back. Sites that would rather hold the seat can
 * return false from pmpro_events_cancel_registrations_on_access_loss.
 *
 * @since 2.0
 *
 * @param int $level_id The level ID that was changed to.
 * @param int $user_id  The user whose level changed.
 */
function pmpro_events_cancel_registrations_on_access_loss( $level_id, $user_id ) {
	/**
	 * Filter whether losing access to an event also cancels the registration.
	 *
	 * @since 2.0
	 *
	 * @param bool $cancel   Whether to cancel the registration. Default true.
	 * @param int  $user_id  The user whose level changed.
	 * @param int  $level_id The level ID that was changed to.
	 */
	if ( ! apply_filters( 'pmpro_events_cancel_registrations_on_access_loss', true, $user_id, $level_id ) ) {
		return;
	}

	$registrations = PMProEvents_Event_Registration::get_registrations( array(
		'user_id' => (int) $user_id,
		'status'  => 'active',
	) );

	foreach ( $registrations as $registration ) {
		$event = $registration->get_event();

		// Past events keep their attendance record.
		if ( ! $event->exists() || $event->has_passed() ) {
			continue;
		}

		if ( ! $event->user_can_access( $user_id ) ) {
			$registration->cancel();
		}
	}
}
add_action( 'pmpro_after_change_membership_level', 'pmpro_events_cancel_registrations_on_access_loss', 10, 2 );
