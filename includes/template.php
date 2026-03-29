<?php
/**
 * Template loading and single event output.
 *
 * @package PMPro_Events
 * @since 2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load custom template for single events.
 *
 * @since 2.0
 *
 * @param string $template Template path.
 * @return string
 */
function pmpro_events_template_include( $template ) {
	if ( ! is_singular( 'pmpro_event' ) ) {
		return $template;
	}

	// Check theme first.
	$theme_template = locate_template( array( 'pmpro-event.php', 'single-pmpro_event.php' ) );
	if ( $theme_template ) {
		return $theme_template;
	}

	// Use plugin template.
	$plugin_template = PMPRO_EVENTS_DIR . 'templates/single-pmpro_event.php';
	if ( file_exists( $plugin_template ) ) {
		return $plugin_template;
	}

	return $template;
}
add_filter( 'template_include', 'pmpro_events_template_include' );

/**
 * Filter the_content for single events to append registration block.
 *
 * @since 2.0
 *
 * @param string $content Post content.
 * @return string
 */
function pmpro_events_filter_content( $content ) {
	if ( ! is_singular( 'pmpro_event' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	global $post;
	$event = PMPro_Event::get_by_post_id( $post->ID );
	if ( ! $event ) {
		return $content;
	}

	ob_start();
	pmpro_events_render_event_meta( $event );
	$meta_html = ob_get_clean();

	ob_start();
	pmpro_events_render_registration_block( $event );
	$registration_html = ob_get_clean();

	return $meta_html . $content . $registration_html;
}
add_filter( 'the_content', 'pmpro_events_filter_content' );

/**
 * Render event meta (date, location).
 *
 * @since 2.0
 *
 * @param PMPro_Event $event Event object.
 */
function pmpro_events_render_event_meta( $event ) {
	$schedule = $event->get_schedule_details();
	$singular = pmpro_events_get_singular_name();
	?>
	<div class="pmpro-event-meta">
		<?php if ( $schedule ) : ?>
			<div class="pmpro-event-date">
				<strong><?php esc_html_e( 'Date:', 'pmpro-events' ); ?></strong>
				<?php echo esc_html( $schedule ); ?>
			</div>
		<?php endif; ?>
		<?php if ( $event->venue_name ) : ?>
			<div class="pmpro-event-location">
				<strong><?php esc_html_e( 'Location:', 'pmpro-events' ); ?></strong>
				<?php echo esc_html( $event->venue_name ); ?>
				<?php if ( $event->venue_address ) : ?>
					<br /><?php echo nl2br( esc_html( $event->venue_address ) ); ?>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $event->capacity > 0 ) : ?>
			<div class="pmpro-event-capacity">
				<strong><?php esc_html_e( 'Capacity:', 'pmpro-events' ); ?></strong>
				<?php
				$remaining = $event->get_remaining_capacity();
				if ( $remaining === 0 ) {
					esc_html_e( 'Sold Out', 'pmpro-events' );
				} else {
					/* translators: %d: remaining spots */
					printf( esc_html__( '%d spots remaining', 'pmpro-events' ), $remaining );
				}
				?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the registration block based on state.
 *
 * @since 2.0
 *
 * @param PMPro_Event $event Event object.
 */
function pmpro_events_render_registration_block( $event ) {
	$user_id = get_current_user_id();
	$singular = pmpro_events_get_singular_name();

	// State 5: Event passed.
	if ( $event->has_passed() ) {
		?>
		<div class="pmpro-event-registration pmpro-event-passed">
			<p>
				<?php
				/* translators: %s: singular event name (lowercase) */
				printf( esc_html__( 'This %s has passed.', 'pmpro-events' ), esc_html( strtolower( $singular ) ) );
				?>
			</p>
		</div>
		<?php
		return;
	}

	// State 4: Sold out.
	if ( ! $event->has_capacity() ) {
		?>
		<div class="pmpro-event-registration pmpro-event-sold-out">
			<p><?php esc_html_e( 'Sold Out', 'pmpro-events' ); ?></p>
		</div>
		<?php
		return;
	}

	// State 3: Already registered.
	if ( $user_id && $event->is_user_registered( $user_id ) ) {
		?>
		<div class="pmpro-event-registration pmpro-event-registered">
			<p><?php esc_html_e( "You're registered!", 'pmpro-events' ); ?></p>
			<p><?php echo esc_html( $event->get_schedule_details() ); ?></p>
			<?php pmpro_events_render_add_to_calendar( $event ); ?>
		</div>
		<?php
		return;
	}

	// Check if user has the event level.
	if ( $user_id ) {
		$user_levels    = pmpro_getMembershipLevelsForUser( $user_id );
		$user_level_ids = wp_list_pluck( $user_levels, 'id' );
		$has_event_level  = in_array( $event->level_id, $user_level_ids );
		$has_member_level = $event->member_level_id && in_array( $event->member_level_id, $user_level_ids );

		// State 2: Has level, not registered — one-click register.
		if ( $has_event_level || $has_member_level ) {
			?>
			<div class="pmpro-event-registration pmpro-event-one-click">
				<form method="post">
					<?php wp_nonce_field( 'pmpro_events_register', 'pmpro_events_register_nonce' ); ?>
					<input type="hidden" name="pmpro_events_post_id" value="<?php echo esc_attr( $event->post_id ); ?>" />
					<button type="submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>">
						<?php esc_html_e( 'Register Now', 'pmpro-events' ); ?>
					</button>
				</form>
			</div>
			<?php
			return;
		}
	}

	// State 1: Not registered, needs checkout. Show ticket picker.
	$price        = get_post_meta( $event->post_id, '_pmpro_event_price', true );
	$member_price = get_post_meta( $event->post_id, '_pmpro_event_member_price', true );
	$currency_symbol = function_exists( 'pmpro_get_currency_symbol' ) ? pmpro_get_currency_symbol() : '$';
	$checkout_url = pmpro_url( 'checkout' );

	?>
	<div class="pmpro-event-registration pmpro-event-checkout">
		<div class="pmpro-event-ticket-options" id="pmpro-event-ticket-options">
			<?php if ( $event->level_id ) : ?>
				<div class="pmpro-event-ticket-option">
					<strong>
						<?php
						if ( floatval( $price ) > 0 ) {
							echo esc_html( $currency_symbol . number_format( floatval( $price ), 2 ) );
						} else {
							esc_html_e( 'Free', 'pmpro-events' );
						}
						?>
					</strong>
					<a href="<?php echo esc_url( add_query_arg( 'pmpro_level', $event->level_id, $checkout_url ) ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>">
						<?php esc_html_e( 'Register', 'pmpro-events' ); ?>
					</a>
				</div>
			<?php endif; ?>

			<?php if ( $event->member_level_id && $member_price !== '' ) : ?>
				<div class="pmpro-event-ticket-option pmpro-event-ticket-member">
					<strong>
						<?php
						/* translators: %s: formatted price */
						printf(
							esc_html__( 'Member Price: %s', 'pmpro-events' ),
							floatval( $member_price ) > 0
								? esc_html( $currency_symbol . number_format( floatval( $member_price ), 2 ) )
								: esc_html__( 'Free', 'pmpro-events' )
						);
						?>
					</strong>
					<?php if ( $user_id ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'pmpro_level', $event->member_level_id, $checkout_url ) ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn' ) ); ?>">
							<?php esc_html_e( 'Register as Member', 'pmpro-events' ); ?>
						</a>
					<?php else : ?>
						<a href="<?php echo esc_url( wp_login_url( get_permalink( $event->post_id ) ) ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn pmpro_btn-secondary' ) ); ?>">
							<?php esc_html_e( 'Log In for Member Price', 'pmpro-events' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php
			// Cross-sell: free for members state.
			if ( $event->member_level_id && $member_price === '0' && ! $user_id ) : ?>
				<div class="pmpro-event-cross-sell">
					<p>
						<?php
						$member_level = new PMPro_Membership_Level( $event->member_level_id );
						/* translators: %s: membership level name */
						printf(
							esc_html__( 'This event is free with a membership. %s', 'pmpro-events' ),
							'<a href="' . esc_url( pmpro_url( 'levels' ) ) . '">' . esc_html__( 'View membership options', 'pmpro-events' ) . '</a>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Render the "Add to Calendar" dropdown.
 *
 * @since 2.0
 *
 * @param PMPro_Event $event Event object.
 */
function pmpro_events_render_add_to_calendar( $event ) {
	$google_url  = $event->get_google_calendar_url();
	$outlook_url = $event->get_outlook365_url();
	$ics_url     = add_query_arg( array(
		'pmpro_events_ics' => '1',
		'event_id'         => $event->id,
	), home_url( '/' ) );

	if ( ! $google_url ) {
		return;
	}

	$template = PMPRO_EVENTS_DIR . 'templates/add-to-calendar.php';
	if ( file_exists( $template ) ) {
		include $template;
	}
}
