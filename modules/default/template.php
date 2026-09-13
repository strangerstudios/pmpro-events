<?php
/**
 * The single event template output.
 *
 * The page is laid out in two tiers, following the pattern used by mature
 * events plugins: a compact schedule and location summary directly under the
 * title so the logistics are visible without scrolling, and then a full detail
 * list repeated alongside the registration button so the member can confirm
 * what they are signing up for at the moment they decide.
 *
 * Markup uses PMPro's own components — pmpro_card, pmpro_btn, pmpro_message —
 * so the event page inherits whichever style variation the site has chosen.
 * PMPro nests all of its styles under the .pmpro class, so every block of
 * output has to be wrapped in that container to pick them up.
 *
 * @since 2.0
 */

/**
 * Add the event summary above the content and the registration card below it.
 *
 * @since 2.0
 *
 * @param string $content The post content.
 * @return string The filtered content.
 */
function pmpro_events_the_content( $content ) {
	if ( ! is_singular( PMProEvents_Event::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$event = new PMProEvents_Event( get_the_ID() );

	if ( ! $event->exists() ) {
		return $content;
	}

	// PMPro has already replaced the content with its no-access message for a
	// restricted event. Adding to it would repeat that message and leak the
	// date and location that the restriction is there to protect.
	if ( ! $event->user_can_access() ) {
		return $content;
	}

	return pmpro_events_get_event_header_html( $event ) . $content . pmpro_events_get_registration_html( $event );
}
add_filter( 'the_content', 'pmpro_events_the_content', 20 );

/**
 * Build the summary shown directly under the event title.
 *
 * Deliberately terse — when and where, and nothing else. Capacity belongs with
 * the registration button rather than up here.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_event_header_html( $event ) {
	$summary = pmpro_events_get_event_summary_html( $event );
	$message = pmpro_events_get_status_message( $event );

	if ( empty( $summary ) && empty( $message ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro' ) ); ?>">
		<div class="pmpro_events_header">
			<?php if ( ! empty( $message ) ) { ?>
				<div role="alert" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_message ' . $message['class'], $message['class'] ) ); ?>">
					<?php echo esc_html( $message['text'] ); ?>
				</div>
			<?php } ?>

			<?php echo $summary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php

	$html = ob_get_clean();

	/**
	 * Filter the event summary shown under the title.
	 *
	 * @since 2.0
	 *
	 * @param string            $html  The markup.
	 * @param PMProEvents_Event $event The event.
	 */
	return apply_filters( 'pmpro_events_event_header_html', $html, $event );
}

/**
 * Build the when/where summary line for an event.
 *
 * Shown under the single event title and for each event in the list view.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup, or an empty string when the event has no date or location.
 */
function pmpro_events_get_event_summary_html( $event ) {
	$schedule = $event->get_date_range();
	$location = pmpro_events_get_short_location( $event );

	if ( empty( $schedule ) && empty( $location ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="pmpro_events_summary">
		<?php if ( ! empty( $schedule ) ) { ?>
			<span class="pmpro_events_summary_item pmpro_events_summary_item-when">
				<span class="pmpro_events_summary_icon" aria-hidden="true">&#128197;</span>
				<span class="pmpro_events_summary_text"><?php echo esc_html( $schedule ); ?></span>
				<?php if ( ! $event->all_day ) { ?>
					<span class="pmpro_events_timezone"><?php echo esc_html( pmpro_events_get_timezone_abbreviation( $event ) ); ?></span>
				<?php } ?>
			</span>
		<?php } ?>

		<?php if ( ! empty( $location ) ) { ?>
			<span class="pmpro_events_summary_item pmpro_events_summary_item-where">
				<span class="pmpro_events_summary_icon" aria-hidden="true">&#128205;</span>
				<span class="pmpro_events_summary_text"><?php echo esc_html( $location ); ?></span>
			</span>
		<?php } ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Get a short location label for the summary line.
 *
 * The full address is saved for the detail list further down the page.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The short location.
 */
function pmpro_events_get_short_location( $event ) {
	if ( $event->is_virtual() ) {
		return __( 'Online', 'pmpro-events' );
	}

	if ( ! empty( $event->venue_name ) ) {
		return $event->venue_name;
	}

	return $event->get_location_summary();
}

/**
 * Get the timezone label to show next to a time.
 *
 * Prefers the short abbreviation, such as EDT, and falls back to the full
 * identifier for zones that don't have one.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The timezone label.
 */
function pmpro_events_get_timezone_abbreviation( $event ) {
	$date = $event->get_date( 'start' );

	if ( empty( $date ) ) {
		return $event->get_timezone()->getName();
	}

	$abbreviation = $date->format( 'T' );

	// PHP returns a numeric offset such as "+02:00" when there is no abbreviation.
	if ( empty( $abbreviation ) || preg_match( '/^[+-]/', $abbreviation ) ) {
		return $event->get_timezone()->getName();
	}

	return $abbreviation;
}

/**
 * Build the label and value rows for the event detail list.
 *
 * Single-day events collapse to a Date and Time pair. Events that span more
 * than one day get separate Start and End rows.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return array Rows of 'label' and 'value'.
 */
function pmpro_events_get_event_detail_rows( $event ) {
	$rows = array();

	$start    = $event->get_date( 'start' );
	$end      = $event->get_date( 'end' );
	$all_day  = (bool) $event->all_day;
	$timezone = $event->get_timezone();

	$date_format = get_option( 'date_format' );
	$time_format = get_option( 'time_format' );

	if ( ! empty( $start ) ) {
		$same_day = ! empty( $end ) && $start->format( 'Y-m-d' ) === $end->format( 'Y-m-d' );

		if ( $all_day ) {
			if ( empty( $end ) || $same_day ) {
				$rows[] = array(
					'label' => __( 'Date', 'pmpro-events' ),
					'value' => wp_date( $date_format, $start->getTimestamp(), $timezone ),
				);
			} else {
				$rows[] = array(
					'label' => __( 'Start', 'pmpro-events' ),
					'value' => wp_date( $date_format, $start->getTimestamp(), $timezone ),
				);
				$rows[] = array(
					'label' => __( 'End', 'pmpro-events' ),
					'value' => wp_date( $date_format, $end->getTimestamp(), $timezone ),
				);
			}
		} elseif ( $same_day ) {
			$rows[] = array(
				'label' => __( 'Date', 'pmpro-events' ),
				'value' => wp_date( $date_format, $start->getTimestamp(), $timezone ),
			);
			$rows[] = array(
				'label' => __( 'Time', 'pmpro-events' ),
				'value' => sprintf(
					/* translators: 1: start time, 2: end time, 3: timezone abbreviation. */
					_x( '%1$s to %2$s %3$s', 'event time range', 'pmpro-events' ),
					wp_date( $time_format, $start->getTimestamp(), $timezone ),
					wp_date( $time_format, $end->getTimestamp(), $timezone ),
					pmpro_events_get_timezone_abbreviation( $event )
				),
			);
		} else {
			$rows[] = array(
				'label' => __( 'Start', 'pmpro-events' ),
				'value' => wp_date( $date_format . ' ' . $time_format, $start->getTimestamp(), $timezone ) . ' ' . pmpro_events_get_timezone_abbreviation( $event ),
			);

			if ( ! empty( $end ) ) {
				$rows[] = array(
					'label' => __( 'End', 'pmpro-events' ),
					'value' => wp_date( $date_format . ' ' . $time_format, $end->getTimestamp(), $timezone ) . ' ' . pmpro_events_get_timezone_abbreviation( $event ),
				);
			}
		}
	}

	if ( $event->is_virtual() ) {
		$rows[] = array(
			'label' => __( 'Location', 'pmpro-events' ),
			'value' => __( 'Online', 'pmpro-events' ),
		);
	} elseif ( $event->is_in_person() ) {
		$location = array_filter( array( $event->venue_name, $event->venue_address ) );

		if ( ! empty( $location ) ) {
			$rows[] = array(
				'label' => __( 'Location', 'pmpro-events' ),
				'value' => implode( "\n", $location ),
			);
		}
	}

	// Only meaningful when the event caps attendance.
	if ( $event->has_registration() && ! $event->has_passed() ) {
		$remaining = $event->get_seats_remaining();

		if ( null !== $remaining ) {
			$rows[] = array(
				'label' => __( 'Spots remaining', 'pmpro-events' ),
				'value' => number_format_i18n( $remaining ),
			);
		}
	}

	/**
	 * Filter the rows shown in the event detail list.
	 *
	 * @since 2.0
	 *
	 * @param array             $rows  Rows of 'label' and 'value'.
	 * @param PMProEvents_Event $event The event.
	 */
	return apply_filters( 'pmpro_events_event_detail_rows', $rows, $event );
}

/**
 * Build the event detail list.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_event_details_html( $event ) {
	$rows = pmpro_events_get_event_detail_rows( $event );

	if ( empty( $rows ) ) {
		return '';
	}

	ob_start();
	?>
	<dl class="pmpro_events_details">
		<?php foreach ( $rows as $row ) { ?>
			<dt><?php echo esc_html( $row['label'] ); ?></dt>
			<dd><?php echo nl2br( esc_html( $row['value'] ) ); ?></dd>
		<?php } ?>
	</dl>
	<?php

	return ob_get_clean();
}

/**
 * Build the registration card shown after the event content.
 *
 * The card repeats the event details and then renders exactly one state: the
 * event has passed, the member is already registered, the event is full, the
 * visitor needs to log in, or the member can register.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_registration_html( $event ) {
	$singular = pmpro_events_get_label( 'singular_lowercase' );
	$details  = pmpro_events_get_event_details_html( $event );

	$registration = $event->has_registration() && ! $event->has_passed() ? $event->get_registration_for_user() : null;

	if ( ! $event->has_registration() && ! $event->has_passed() ) {
		// No registration to gate anything behind, so the card is purely
		// informational: the details plus the meeting link and calendar links
		// for everyone who can view the event.
		$state   = 'details';
		$title   = '';
		$actions = pmpro_events_get_event_links_html( $event );

		if ( empty( $details ) && empty( $actions ) ) {
			return '';
		}
	} elseif ( $event->has_passed() ) {
		$state = 'passed';
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		$title   = sprintf( __( 'This %s has passed', 'pmpro-events' ), $singular );
		$actions = '';
	} elseif ( ! empty( $registration ) ) {
		$state   = 'registered';
		$title   = __( "You're registered", 'pmpro-events' );
		$actions = pmpro_events_get_registered_state_html( $event );
	} elseif ( $event->is_full() ) {
		$state = 'full';
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		$title   = sprintf( __( 'This %s is full', 'pmpro-events' ), $singular );
		$actions = '';
	} elseif ( ! is_user_logged_in() ) {
		$state = 'login';
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		$title = sprintf( __( 'Register for this %s', 'pmpro-events' ), $singular );

		// Registering needs an account, so the footer offers both ways to get
		// one: a primary button to check out for the sign-up level, and a
		// secondary outline button for people who already have an account.
		// Without a sign-up level, logging in is the only path, so it gets the
		// primary button to itself.
		$signup_url = pmpro_events_get_signup_url( $event );
		if ( ! empty( $signup_url ) ) {
			$actions = sprintf(
				'<a href="%s" class="%s">%s</a> <a href="%s" class="%s">%s</a>',
				esc_url( $signup_url ),
				esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ),
				esc_html__( 'Create an account', 'pmpro-events' ),
				esc_url( wp_login_url( $event->get_permalink() ) ),
				esc_attr( pmpro_get_element_class( 'pmpro_btn pmpro_btn-outline', 'pmpro_btn' ) ),
				esc_html__( 'Log in', 'pmpro-events' )
			);
		} else {
			$actions = sprintf(
				'<a href="%s" class="%s">%s</a>',
				esc_url( wp_login_url( $event->get_permalink() ) ),
				esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ),
				esc_html__( 'Log in to register', 'pmpro-events' )
			);
		}
	} else {
		$state = 'register';
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		$title   = sprintf( __( 'Register for this %s', 'pmpro-events' ), $singular );
		$actions = pmpro_events_get_register_form_html( $event );
	}

	ob_start();
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro' ) ); ?>">
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card', 'pmpro_events_card' ) ); ?> pmpro_events_card-<?php echo esc_attr( $state ); ?>">
			<?php if ( ! empty( $title ) ) { ?>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>">
					<?php echo esc_html( $title ); ?>
				</div>
			<?php } ?>

			<?php if ( ! empty( $details ) ) { ?>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
					<?php echo $details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php } ?>

			<?php if ( ! empty( $actions ) ) { ?>
				<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_actions' ) ); ?>">
					<?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php } ?>
		</div>
	</div>
	<?php

	$html = ob_get_clean();

	/**
	 * Filter the registration card shown after the event content.
	 *
	 * @since 2.0
	 *
	 * @param string            $html  The markup.
	 * @param PMProEvents_Event $event The event.
	 * @param string            $state The state being rendered.
	 */
	return apply_filters( 'pmpro_events_registration_html', $html, $event, $state );
}

/**
 * Get the checkout URL for the configured sign-up level.
 *
 * Shown to logged-out visitors on events with registration, so they can create
 * an account instead of hunting for the levels page. Admins choose the level —
 * ideally a free one — on the Events settings page.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event the visitor came from.
 * @return string The checkout URL, or an empty string when no level is configured.
 */
function pmpro_events_get_signup_url( $event ) {
	$level_id = (int) get_option( 'pmpro_events_signup_level' );

	if ( empty( $level_id ) || ! function_exists( 'pmpro_getLevel' ) ) {
		return '';
	}

	// The level may have been deleted since it was chosen.
	if ( empty( pmpro_getLevel( $level_id ) ) ) {
		return '';
	}

	// The event rides along so that pmpro_events_confirmation_url() can send
	// the new member back here after checkout. pmpro_url() returns an empty
	// string when no checkout page is assigned, which hides the link.
	return pmpro_url( 'checkout', '?pmpro_level=' . $level_id . '&pmpro_events_event=' . $event->get_id() );
}

/**
 * Send a new member back to the event they came from after checkout.
 *
 * The checkout form posts back to its own URL, so the pmpro_events_event
 * argument added by pmpro_events_get_signup_url() is still in the request
 * when PMPro builds the confirmation redirect. Gateways that come back from
 * an offsite payment flow lose the argument and fall through to PMPro's
 * confirmation page as usual.
 *
 * @since 2.0
 *
 * @param string $url The confirmation URL.
 * @return string The filtered URL.
 */
function pmpro_events_confirmation_url( $url ) {
	$event_id = isset( $_REQUEST['pmpro_events_event'] ) ? (int) $_REQUEST['pmpro_events_event'] : 0;

	if ( empty( $event_id ) ) {
		return $url;
	}

	$event = new PMProEvents_Event( $event_id );

	if ( ! $event->exists() || 'publish' !== get_post_status( $event_id ) ) {
		return $url;
	}

	return $event->get_permalink();
}
add_filter( 'pmpro_confirmation_url', 'pmpro_events_confirmation_url' );

/**
 * Build the register button and its form.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_register_form_html( $event ) {
	ob_start();
	?>
	<form action="<?php echo esc_url( pmpro_events_get_form_action() ); ?>" method="post" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form' ) ); ?>">
		<input type="hidden" name="action" value="pmpro_events_register" />
		<input type="hidden" name="event_id" value="<?php echo esc_attr( $event->get_id() ); ?>" />
		<?php wp_nonce_field( 'pmpro_events_register_' . $event->get_id(), 'pmpro_events_nonce' ); ?>
		<button type="submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>"><?php esc_html_e( 'Register', 'pmpro-events' ); ?></button>
	</form>
	<?php

	return ob_get_clean();
}

/**
 * Build the meeting link and Add-to-Calendar links for an event.
 *
 * These reveal the meeting URL, so they are only shown to a registered
 * attendee — or, when the event isn't taking registrations, to everyone who
 * can view the event.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_event_links_html( $event ) {
	ob_start();

	if ( $event->is_virtual() && ! empty( $event->virtual_url ) ) {
		?>
		<p class="pmpro_events_meeting_url">
			<a href="<?php echo esc_url( $event->virtual_url ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Join the meeting', 'pmpro-events' ); ?></a>
		</p>
		<?php
	}

	$links = pmpro_events_get_add_to_calendar_links( $event );
	if ( ! empty( $links ) ) {
		?>
		<p class="pmpro_events_add_to_calendar">
			<strong><?php esc_html_e( 'Add to calendar:', 'pmpro-events' ); ?></strong>
			<?php
			$rendered = array();
			foreach ( $links as $key => $link ) {
				$rendered[] = sprintf(
					'<a href="%s" class="pmpro_events_add_to_calendar-%s"%s>%s</a>',
					esc_url( $link['url'] ),
					esc_attr( $key ),
					'ics' === $key ? '' : ' target="_blank" rel="noopener noreferrer"',
					esc_html( $link['label'] )
				);
			}
			echo wp_kses_post( implode( ' <span class="pmpro_card_action_separator">&middot;</span> ', $rendered ) );
			?>
		</p>
		<?php
	}

	return ob_get_clean();
}

/**
 * Build the card actions for a member who is already registered.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_registered_state_html( $event ) {
	ob_start();

	echo pmpro_events_get_event_links_html( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>

	<form action="<?php echo esc_url( pmpro_events_get_form_action() ); ?>" method="post" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form' ) ); ?>">
		<input type="hidden" name="action" value="pmpro_events_cancel" />
		<input type="hidden" name="event_id" value="<?php echo esc_attr( $event->get_id() ); ?>" />
		<?php wp_nonce_field( 'pmpro_events_cancel_' . $event->get_id(), 'pmpro_events_nonce' ); ?>
		<button type="submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn pmpro_btn-cancel' ) ); ?>"><?php esc_html_e( 'Cancel my registration', 'pmpro-events' ); ?></button>
	</form>
	<?php

	return ob_get_clean();
}

/**
 * Whether the current request renders anything this stylesheet covers.
 *
 * @since 2.0
 *
 * @return bool Whether to load the frontend styles.
 */
function pmpro_events_needs_frontend_styles() {
	if ( is_singular( PMProEvents_Event::POST_TYPE ) ) {
		return true;
	}

	// The events blocks and shortcodes can be placed on any page.
	$post = get_post();

	if ( empty( $post ) ) {
		return false;
	}

	return has_block( 'pmpro-events/my-events', $post )
		|| has_block( 'pmpro-events/events', $post )
		|| has_shortcode( (string) $post->post_content, 'pmpro_events_my_events' )
		|| has_shortcode( (string) $post->post_content, 'pmpro_events' );
}

/**
 * Enqueue the frontend styles on event pages and pages showing My Events.
 *
 * @since 2.0
 */
function pmpro_events_enqueue_styles() {
	if ( ! pmpro_events_needs_frontend_styles() ) {
		return;
	}

	wp_enqueue_style(
		'pmpro-events-frontend',
		PMPRO_EVENTS_URL . '/css/frontend.css',
		array( 'pmpro_frontend_base' ),
		PMPRO_EVENTS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'pmpro_events_enqueue_styles' );
