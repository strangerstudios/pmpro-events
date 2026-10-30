<?php
// In case the file is loaded directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The single event template output.
 *
 * A single "at a glance" card sits directly under the title, before the event
 * description: the schedule, location and availability, with the registration
 * action right beside them. The logistics and the button are visible without
 * scrolling, and nothing is repeated further down the page.
 *
 * Markup uses PMPro's own components — pmpro_card, pmpro_btn, pmpro_message —
 * so the event page inherits whichever style variation the site has chosen.
 * PMPro nests all of its styles under the .pmpro class, so every block of
 * output has to be wrapped in that container to pick them up.
 *
 * @since 2.0
 */

/**
 * Add the event card above the content.
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

	return pmpro_events_get_event_header_html( $event ) . $content;
}
add_filter( 'the_content', 'pmpro_events_the_content', 20 );

/**
 * Build the header shown directly under the event title.
 *
 * Holds the result message after a register or cancel action, then the event
 * card.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_event_header_html( $event ) {
	$card    = pmpro_events_get_registration_html( $event );
	$message = pmpro_events_get_status_message( $event );

	if ( empty( $card ) && empty( $message ) ) {
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

			<?php echo $card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>
	<?php

	$html = ob_get_clean();

	/**
	 * Filter the header shown under the event title.
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
 * Shown for each event in the list view.
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
				<?php echo pmpro_events_get_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="pmpro_events_summary_text">
					<?php echo esc_html( $schedule ); ?>
					<?php if ( ! $event->all_day ) { ?>
						<span class="pmpro_events_timezone"><?php echo esc_html( pmpro_events_get_timezone_abbreviation( $event ) ); ?></span>
					<?php } ?>
				</span>
			</span>
		<?php } ?>

		<?php if ( ! empty( $location ) ) { ?>
			<span class="pmpro_events_summary_item pmpro_events_summary_item-where">
				<?php echo pmpro_events_get_icon( $event->is_virtual() ? 'video' : 'map-pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="pmpro_events_summary_text"><?php echo esc_html( $location ); ?></span>
			</span>
		<?php } ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Get a Feather icon, wrapped the way PMPro core wraps its icons.
 *
 * Icons are decorative and hidden from assistive technology; the text next to
 * each one carries the meaning.
 *
 * @since TBD
 *
 * @link https://feathericons.com/
 *
 * @param string $name The Feather icon name.
 * @return string The markup, or an empty string for an unknown icon.
 */
function pmpro_events_get_icon( $name ) {
	$paths = array(
		'calendar'      => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
		'check'         => '<polyline points="20 6 9 17 4 12"></polyline>',
		'chevron-left'  => '<polyline points="15 18 9 12 15 6"></polyline>',
		'chevron-right' => '<polyline points="9 18 15 12 9 6"></polyline>',
		'clock'         => '<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',
		'map-pin'       => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>',
		'users'         => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
		'video'         => '<polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>',
	);

	if ( empty( $paths[ $name ] ) ) {
		return '';
	}

	return sprintf(
		'<span class="pmpro_icon pmpro_icon-%1$s" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--pmpro--color--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-%1$s">%2$s</svg></span>',
		esc_attr( $name ),
		$paths[ $name ]
	);
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
 * @return array Rows keyed by name, each with a 'label', a 'value', an
 *               optional Feather 'icon' name and an optional 'link' array of
 *               'url' and 'label'.
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
				$rows['date'] = array(
					'label' => __( 'Date', 'pmpro-events' ),
					'value' => wp_date( $date_format, $start->getTimestamp(), $timezone ),
					'icon'  => 'calendar',
				);
			} else {
				$rows['start'] = array(
					'label' => __( 'Start', 'pmpro-events' ),
					'value' => wp_date( $date_format, $start->getTimestamp(), $timezone ),
					'icon'  => 'calendar',
				);
				$rows['end'] = array(
					'label' => __( 'End', 'pmpro-events' ),
					'value' => wp_date( $date_format, $end->getTimestamp(), $timezone ),
					'icon'  => 'calendar',
				);
			}
		} elseif ( $same_day ) {
			$rows['date'] = array(
				'label' => __( 'Date', 'pmpro-events' ),
				'value' => wp_date( $date_format, $start->getTimestamp(), $timezone ),
				'icon'  => 'calendar',
			);
			$rows['time'] = array(
				'label' => __( 'Time', 'pmpro-events' ),
				'value' => sprintf(
					/* translators: 1: start time, 2: end time, 3: timezone abbreviation. */
					_x( '%1$s to %2$s %3$s', 'event time range', 'pmpro-events' ),
					wp_date( $time_format, $start->getTimestamp(), $timezone ),
					wp_date( $time_format, $end->getTimestamp(), $timezone ),
					pmpro_events_get_timezone_abbreviation( $event )
				),
				'icon'  => 'clock',
			);
		} else {
			$rows['start'] = array(
				'label' => __( 'Start', 'pmpro-events' ),
				'value' => wp_date( $date_format . ' ' . $time_format, $start->getTimestamp(), $timezone ) . ' ' . pmpro_events_get_timezone_abbreviation( $event ),
				'icon'  => 'calendar',
			);

			if ( ! empty( $end ) ) {
				$rows['end'] = array(
					'label' => __( 'End', 'pmpro-events' ),
					'value' => wp_date( $date_format . ' ' . $time_format, $end->getTimestamp(), $timezone ) . ' ' . pmpro_events_get_timezone_abbreviation( $event ),
					'icon'  => 'calendar',
				);
			}
		}
	}

	if ( $event->is_virtual() ) {
		$rows['location'] = array(
			'label' => __( 'Location', 'pmpro-events' ),
			'value' => __( 'Online', 'pmpro-events' ),
			'icon'  => 'video',
		);

		// The meeting URL is the attendee's ticket, so only show it to people allowed to see it.
		if ( ! empty( $event->virtual_url ) && ! $event->has_passed() && $event->user_can_view_links() ) {
			$rows['location']['link'] = array(
				'url'   => $event->virtual_url,
				/* translators: %s: the singular event label, lowercased, e.g. "event". */
				'label' => sprintf( __( 'Join the %s', 'pmpro-events' ), pmpro_events_get_label( 'singular_lowercase' ) ),
			);
		}
	} elseif ( $event->is_in_person() ) {
		$location = array_filter( array( $event->venue_name, $event->venue_address ) );

		if ( ! empty( $location ) ) {
			$rows['location'] = array(
				'label' => __( 'Location', 'pmpro-events' ),
				'value' => implode( "\n", $location ),
				'icon'  => 'map-pin',
			);
		}
	}

	// Only meaningful while someone could still take a spot, so it's left off
	// once the event has passed or filled and for members already registered.
	if ( $event->has_registration() && ! $event->has_passed() && ! $event->is_full() && empty( $event->get_registration_for_user() ) ) {
		$remaining = $event->get_seats_remaining();
		$capacity  = $event->get_capacity();

		if ( null !== $remaining && ! empty( $capacity ) ) {
			$rows['availability'] = array(
				'label' => __( 'Availability', 'pmpro-events' ),
				'value' => sprintf(
					/* translators: 1: number of spots remaining, 2: total number of spots. */
					_n( '%1$s of %2$s spot remaining', '%1$s of %2$s spots remaining', $capacity, 'pmpro-events' ),
					number_format_i18n( $remaining ),
					number_format_i18n( $capacity )
				),
				'icon'  => 'users',
			);
		}
	}

	/**
	 * Filter the rows shown in the event detail list.
	 *
	 * @since 2.0
	 *
	 * @param array             $rows  Rows keyed by name (date, time, start, end,
	 *                                 location, availability), each with a 'label',
	 *                                 a 'value', an optional Feather 'icon' name and
	 *                                 an optional 'link' array of 'url' and 'label'.
	 * @param PMProEvents_Event $event The event.
	 */
	return apply_filters( 'pmpro_events_event_detail_rows', $rows, $event );
}

/**
 * Build the event detail list.
 *
 * Each label and value pair is wrapped in a div, which HTML allows inside a
 * dl, so the pairs can be laid out as a grid.
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
		<?php foreach ( $rows as $key => $row ) { ?>
			<div class="pmpro_events_details_item pmpro_events_details_item-<?php echo esc_attr( $key ); ?>">
				<dt>
					<?php
					if ( ! empty( $row['icon'] ) ) {
						echo pmpro_events_get_icon( $row['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					echo esc_html( $row['label'] );
					?>
				</dt>
				<dd>
					<?php
					echo nl2br( esc_html( $row['value'] ) );
					if ( ! empty( $row['link'] ) ) {
						printf(
							' <span class="pmpro_card_action_separator">&middot;</span> <a href="%s" target="_blank" rel="noopener noreferrer">%s%s</a>',
							esc_url( $row['link']['url'] ),
							esc_html( $row['link']['label'] ),
							pmpro_events_get_new_tab_text() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
					}
					?>
				</dd>
			</div>
		<?php } ?>
	</dl>
	<?php

	return ob_get_clean();
}

/**
 * Build the event card shown under the event title.
 *
 * The card is always titled with the event label, lists the event details,
 * and then renders exactly one state in its actions: the event has passed,
 * the member is already registered, the event is full, the visitor needs to
 * log in, the member can register, or the event doesn't take registrations at
 * all.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_registration_html( $event ) {
	$singular = pmpro_events_get_label( 'singular_lowercase' );
	$details  = pmpro_events_get_event_details_html( $event );

	/* translators: %s: the singular event label, e.g. "Event". */
	$title = sprintf( __( '%s Details', 'pmpro-events' ), pmpro_events_get_label( 'singular' ) );

	$registration = $event->has_registration() && ! $event->has_passed() ? $event->get_registration_for_user() : null;
	$calendar     = '';

	if ( ! $event->has_registration() && ! $event->has_passed() ) {
		// No registration to gate anything behind, so the card is purely
		// informational: the details, with the meeting link, plus calendar
		// links for everyone who can view the event.
		$state    = 'details';
		$calendar = pmpro_events_get_add_to_calendar_html( $event );
		$actions  = '';

		if ( empty( $details ) && empty( $calendar ) ) {
			return '';
		}
	} elseif ( $event->has_passed() ) {
		$state = 'passed';
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		$actions = pmpro_events_get_status_tag_html( sprintf( __( 'This %s has ended', 'pmpro-events' ), $singular ) );
	} elseif ( ! empty( $registration ) ) {
		$state    = 'registered';
		$calendar = pmpro_events_get_add_to_calendar_html( $event );
		$actions  = pmpro_events_get_registered_state_html( $event );
	} elseif ( $event->is_full() ) {
		$state = 'full';
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		$actions = pmpro_events_get_status_tag_html( sprintf( __( 'This %s is full', 'pmpro-events' ), $singular ), 'alert' );
	} elseif ( ! is_user_logged_in() ) {
		$state = 'login';

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
		$state   = 'register';
		$actions = pmpro_events_get_register_form_html( $event );
	}

	ob_start();
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card pmpro_events_card', 'pmpro_events_card' ) ); ?> pmpro_events_card-<?php echo esc_attr( $state ); ?>">
		<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>">
			<?php echo esc_html( $title ); ?>
		</div>

		<?php if ( ! empty( $details ) || ! empty( $calendar ) ) { ?>
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
				<?php echo $details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php echo $calendar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php } ?>

		<?php if ( ! empty( $actions ) ) { ?>
			<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_actions' ) ); ?>">
				<?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		<?php } ?>
	</div>
	<?php

	$html = ob_get_clean();

	/**
	 * Filter the event card shown under the event title.
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
 * Build the Add-to-Calendar links for an event.
 *
 * The calendar entries include the meeting URL, so the card only shows these
 * to people PMProEvents_Event::user_can_view_links() allows.
 *
 * @since TBD
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup, or an empty string when there are no links.
 */
function pmpro_events_get_add_to_calendar_html( $event ) {
	$links = pmpro_events_get_add_to_calendar_links( $event );

	if ( empty( $links ) ) {
		return '';
	}

	$rendered = array();
	foreach ( $links as $key => $link ) {
		$rendered[] = sprintf(
			'<a href="%s" class="pmpro_events_add_to_calendar-%s"%s>%s%s</a>',
			esc_url( $link['url'] ),
			esc_attr( $key ),
			'ics' === $key ? '' : ' target="_blank" rel="noopener noreferrer"',
			esc_html( $link['label'] ),
			'ics' === $key ? '' : pmpro_events_get_new_tab_text()
		);
	}

	return sprintf(
		'<div class="pmpro_events_add_to_calendar"><span class="pmpro_events_add_to_calendar_label">%s</span> %s</div>',
		esc_html__( 'Add to calendar:', 'pmpro-events' ),
		wp_kses_post( implode( ' ', $rendered ) )
	);
}

/**
 * Build a status bubble for the card actions.
 *
 * @since TBD
 *
 * @param string $text  The status text.
 * @param string $style Optional. A PMPro tag style, such as success or alert.
 * @param string $icon  Optional. A Feather icon name to show before the text.
 * @return string The markup.
 */
function pmpro_events_get_status_tag_html( $text, $style = '', $icon = '' ) {
	$class = empty( $style ) ? 'pmpro_tag' : 'pmpro_tag pmpro_tag-' . $style;

	return sprintf(
		'<span class="%s">%s%s</span>',
		esc_attr( pmpro_get_element_class( $class . ' pmpro_events_status', 'pmpro_events_status' ) ),
		empty( $icon ) ? '' : pmpro_events_get_icon( $icon ),
		esc_html( $text )
	);
}

/**
 * Get the screen reader note for links that open in a new tab.
 *
 * @since TBD
 *
 * @return string The markup.
 */
function pmpro_events_get_new_tab_text() {
	return ' <span class="screen-reader-text">' . esc_html__( '(opens in a new tab)', 'pmpro-events' ) . '</span>';
}

/**
 * Build the card actions for a member who is already registered.
 *
 * A status bubble in place of the Register button, with a plain text button
 * to cancel.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_registered_state_html( $event ) {
	ob_start();

	echo pmpro_events_get_status_tag_html( __( "You're registered", 'pmpro-events' ), 'success', 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<form action="<?php echo esc_url( pmpro_events_get_form_action() ); ?>" method="post" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form pmpro_events_cancel_form', 'pmpro_events_cancel_form' ) ); ?>">
		<input type="hidden" name="action" value="pmpro_events_cancel" />
		<input type="hidden" name="event_id" value="<?php echo esc_attr( $event->get_id() ); ?>" />
		<?php wp_nonce_field( 'pmpro_events_cancel_' . $event->get_id(), 'pmpro_events_nonce' ); ?>
		<button type="submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn pmpro_btn-plain' ) ); ?>"><?php esc_html_e( 'Cancel my registration', 'pmpro-events' ); ?></button>
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
