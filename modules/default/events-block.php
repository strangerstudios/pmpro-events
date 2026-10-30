<?php
// In case the file is loaded directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Upcoming Events block and shortcode, with a list view and a calendar view.
 *
 * Restricted events are listed by default — their single pages show PMPro's
 * no-access message and a join prompt, which is the teaser behavior PMPro uses
 * everywhere else. When the site enables PMPro's "Filter searches and
 * archives?" advanced setting, events the visitor can't access are hidden
 * here too, matching how that setting treats every other query.
 *
 * @since 2.0
 */

/**
 * Register the Upcoming Events block.
 *
 * @since 2.0
 */
function pmpro_events_register_events_block() {
	wp_register_script(
		'pmpro-events-events-block',
		PMPRO_EVENTS_URL . '/js/events-block.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-element', 'wp-server-side-render', 'wp-i18n' ),
		PMPRO_EVENTS_VERSION,
		true
	);
	wp_set_script_translations( 'pmpro-events-events-block', 'pmpro-events', PMPRO_EVENTS_DIR . '/languages' );

	// The frontend styles under an editor-only handle. Attaching it to the
	// block gets it injected into the editor iframe, where the theme's PMPro
	// styles aren't loaded — the CSS custom property fallbacks carry it there.
	wp_register_style(
		'pmpro-events-editor-preview',
		PMPRO_EVENTS_URL . '/css/frontend.css',
		array(),
		PMPRO_EVENTS_VERSION
	);

	register_block_type(
		'pmpro-events/events',
		array(
			'api_version'     => 3,
			'editor_script'   => 'pmpro-events-events-block',
			'editor_style'    => 'pmpro-events-editor-preview',
			'render_callback' => 'pmpro_events_render_events',
			'attributes'      => array(
				'view'      => array(
					'type'    => 'string',
					'default' => 'list',
				),
				'limit'     => array(
					'type'    => 'number',
					'default' => 10,
				),
				'category'  => array(
					'type'    => 'string',
					'default' => '',
				),
				'showTitle' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'title'     => array(
					'type'    => 'string',
					'default' => '',
				),
				'showToggle' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
		)
	);
}
add_action( 'init', 'pmpro_events_register_events_block' );

/**
 * Render the [pmpro_events] shortcode.
 *
 * @since 2.0
 *
 * @param array $atts The shortcode attributes.
 * @return string The markup.
 */
function pmpro_events_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'view'       => 'list',
		'limit'      => 10,
		'category'   => '',
		'title'       => '',
		'show_title'  => true,
		'show_toggle' => true,
	), $atts, 'pmpro_events' );

	// The block attribute names, so both spellings resolve the same way.
	$atts['showTitle']  = $atts['show_title'];
	$atts['showToggle'] = $atts['show_toggle'];

	return pmpro_events_render_events( $atts );
}
add_shortcode( 'pmpro_events', 'pmpro_events_shortcode' );

/**
 * Resolve the title to show above a block's output.
 *
 * Both events blocks let the site hide the title or replace the default one.
 * The shortcodes pass show_title as a string, so it is validated as a boolean.
 *
 * @since 2.0
 *
 * @param array  $attributes The block or shortcode attributes.
 * @param string $default    The title used when no custom title is set.
 * @return string The title to display, or an empty string to show none.
 */
function pmpro_events_resolve_block_title( $attributes, $default ) {
	$show = isset( $attributes['showTitle'] ) ? filter_var( $attributes['showTitle'], FILTER_VALIDATE_BOOLEAN ) : true;

	if ( ! $show ) {
		return '';
	}

	return empty( $attributes['title'] ) ? $default : (string) $attributes['title'];
}

/**
 * Render the Upcoming Events block and shortcode.
 *
 * @since 2.0
 *
 * @param array $attributes The block or shortcode attributes.
 * @return string The markup.
 */
function pmpro_events_render_events( $attributes ) {
	static $instance = 0;
	$instance++;

	// Navigation links jump back to this block instead of the top of the page.
	$anchor = 'pmpro_events-' . $instance;

	$show_toggle = isset( $attributes['showToggle'] ) ? filter_var( $attributes['showToggle'], FILTER_VALIDATE_BOOLEAN ) : true;

	$view = isset( $attributes['view'] ) && 'calendar' === $attributes['view'] ? 'calendar' : 'list';
	if ( $show_toggle ) {
		$view = pmpro_events_get_requested_view( $view );
	}

	$limit = isset( $attributes['limit'] ) ? max( 1, (int) $attributes['limit'] ) : 10;

	// Comma-separated slugs, so the shortcode can cover multiple categories.
	$category = isset( $attributes['category'] ) ? array_filter( array_map( 'trim', explode( ',', (string) $attributes['category'] ) ) ) : array();

	$title = pmpro_events_resolve_block_title(
		$attributes,
		/* translators: %s: the plural event label, e.g. "Events". */
		sprintf( _x( 'Upcoming %s', 'plural event label', 'pmpro-events' ), pmpro_events_get_label( 'plural' ) )
	);

	$content = 'calendar' === $view
		? pmpro_events_get_events_calendar_html( $category, $anchor )
		: pmpro_events_get_events_list_html( $limit, $category );

	// PMPro nests all of its styles under the .pmpro class, so the output has
	// to be wrapped in that container to pick them up.
	ob_start();
	?>
	<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro' ) ); ?>">
		<section id="<?php echo esc_attr( $anchor ); ?>" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section pmpro_events_events', 'pmpro_events_events' ) ); ?>">
			<?php if ( ! empty( $title ) || $show_toggle ) { ?>
				<div class="pmpro_events_events_header">
					<?php if ( ! empty( $title ) ) { ?>
						<h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_section_title pmpro_font-x-large' ) ); ?>"><?php echo esc_html( $title ); ?></h2>
					<?php } ?>

					<?php
					if ( $show_toggle ) {
						echo pmpro_events_get_view_toggle_html( $view, $anchor ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}
					?>
				</div>
			<?php } ?>

			<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Get the view the visitor picked with the List/Calendar toggle.
 *
 * @since TBD
 *
 * @param string $default The block's own view, used when nothing was picked.
 * @return string The view, 'list' or 'calendar'.
 */
function pmpro_events_get_requested_view( $default ) {
	$view = isset( $_GET['pmpro_events_view'] ) ? sanitize_key( wp_unslash( $_GET['pmpro_events_view'] ) ) : '';

	return in_array( $view, array( 'list', 'calendar' ), true ) ? $view : $default;
}

/**
 * Build the List/Calendar toggle.
 *
 * Plain links, so switching views works without JavaScript and each view has
 * its own URL.
 *
 * @since TBD
 *
 * @param string $current The view being shown.
 * @param string $anchor  The id of the block, so the page lands back on it.
 * @return string The markup.
 */
function pmpro_events_get_view_toggle_html( $current, $anchor ) {
	$views = array(
		'list'     => __( 'List', 'pmpro-events' ),
		'calendar' => __( 'Calendar', 'pmpro-events' ),
	);

	$links = array();
	foreach ( $views as $view => $label ) {
		// The month only applies to the calendar, so the list drops it.
		$url = 'list' === $view
			? add_query_arg( 'pmpro_events_view', $view, remove_query_arg( 'pmpro_events_month' ) )
			: add_query_arg( 'pmpro_events_view', $view );

		$links[] = sprintf(
			'<a href="%1$s" class="pmpro_events_view_toggle_link%2$s"%3$s>%4$s</a>',
			esc_url( $url . '#' . $anchor ),
			$view === $current ? ' is-current' : '',
			$view === $current ? ' aria-current="page"' : '',
			esc_html( $label )
		);
	}

	return sprintf(
		'<nav class="pmpro_events_view_toggle" aria-label="%1$s">%2$s</nav>',
		/* translators: %s: the plural event label, e.g. "Events". */
		esc_attr( sprintf( __( '%s view', 'pmpro-events' ), pmpro_events_get_label( 'plural' ) ) ),
		implode( '', $links )
	);
}

/**
 * Hide events the visitor can't access when PMPro is filtering queries.
 *
 * PMPro's "Filter searches and archives?" advanced setting hides restricted
 * content from every list it controls. Our queries don't run through PMPro,
 * so the same rule is applied here.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event[] $events The events to filter.
 * @return PMProEvents_Event[] The events the current visitor may see.
 */
function pmpro_events_filter_hidden_events( $events ) {
	if ( ! get_option( 'pmpro_filterqueries' ) ) {
		return $events;
	}

	return array_values( array_filter( $events, function( $event ) {
		return $event->user_can_access();
	} ) );
}

/**
 * Build the upcoming events list.
 *
 * @since 2.0
 *
 * @param int      $limit    The number of events to show.
 * @param string[] $category Optional category slugs to limit the list to.
 * @return string The markup.
 */
function pmpro_events_get_events_list_html( $limit, $category = array() ) {
	// Query without a limit so that access filtering doesn't leave the list
	// short, then cut it down after.
	$events = PMProEvents_Event::get_events( array(
		'timeframe' => 'upcoming',
		'category'  => $category,
		'order'     => 'ASC',
	) );

	$events = array_slice( pmpro_events_filter_hidden_events( $events ), 0, $limit );

	if ( empty( $events ) ) {
		$label = pmpro_events_get_label( 'plural_lowercase' );
		$term  = 1 === count( $category ) ? get_term_by( 'slug', reset( $category ), PMProEvents_Event::TAXONOMY ) : false;

		if ( $term ) {
			/* translators: %1$s: the plural event label, lowercased, e.g. "events". %2$s: the category name. */
			$message = sprintf( __( 'There are no upcoming %1$s in %2$s.', 'pmpro-events' ), $label, $term->name );
		} elseif ( ! empty( $category ) ) {
			/* translators: %s: the plural event label, lowercased, e.g. "events". */
			$message = sprintf( __( 'There are no upcoming %s in the selected categories.', 'pmpro-events' ), $label );
		} else {
			/* translators: %s: the plural event label, lowercased, e.g. "events". */
			$message = sprintf( __( 'There are no upcoming %s.', 'pmpro-events' ), $label );
		}

		return pmpro_events_get_empty_card_html( $message );
	}

	return pmpro_events_get_event_cards_html( $events );
}

/**
 * Build a card holding an empty-state message.
 *
 * Shared by the Upcoming Events list and the My Events block, so an empty list
 * looks the same in both.
 *
 * @since TBD
 *
 * @param string $message The message.
 * @return string The markup.
 */
function pmpro_events_get_empty_card_html( $message ) {
	return sprintf(
		'<div class="%1$s"><div class="%2$s"><p class="pmpro_events_list_empty">%3$s</p></div></div>',
		esc_attr( pmpro_get_element_class( 'pmpro_card' ) ),
		esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ),
		esc_html( $message )
	);
}

/**
 * Build a list of event cards, each linking to its event.
 *
 * Shared by the Upcoming Events list view and the My Events block, so a
 * member's own events look the same as the site-wide list.
 *
 * @since 2.0
 *
 * @param PMProEvents_Event[] $events The events to list.
 * @return string The markup.
 */
function pmpro_events_get_event_cards_html( $events ) {
	ob_start();
	?>
	<div class="pmpro_events_list">
		<?php
		foreach ( $events as $event ) {
			$summary = pmpro_events_get_event_summary_html( $event );
			?>
			<article class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card pmpro_events_list_card', 'pmpro_events_list_card' ) ); ?>">
				<h3 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_title pmpro_font-large' ) ); ?>">
					<a href="<?php echo esc_url( $event->get_permalink() ); ?>"><?php echo esc_html( $event->get_title() ); ?></a>
				</h3>

				<?php if ( ! empty( $summary ) ) { ?>
					<div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
						<?php echo $summary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php } ?>
			</article>
		<?php } ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Build the calendar's month navigation.
 *
 * Arrows on either side of the month, with their names as screen reader text,
 * and a Today link when another month is showing.
 *
 * @since TBD
 *
 * @param DateTime $first    The first day of the month being shown.
 * @param string   $hash     The anchor to add to each link, or an empty string.
 * @param string   $position Where the navigation sits, 'top' or 'bottom'.
 * @return string The markup.
 */
function pmpro_events_get_calendar_nav_html( $first, $hash, $position ) {
	$previous = ( clone $first )->modify( '-1 month' )->format( 'Y-m' );
	$next     = ( clone $first )->modify( '+1 month' )->format( 'Y-m' );
	$is_now   = wp_date( 'Y-m' ) === $first->format( 'Y-m' );

	ob_start();
	?>
	<div class="pmpro_events_calendar_nav pmpro_events_calendar_nav-<?php echo esc_attr( $position ); ?>">
		<div class="pmpro_events_calendar_pager">
			<a class="pmpro_events_calendar_arrow" href="<?php echo esc_url( add_query_arg( 'pmpro_events_month', $previous ) . $hash ); ?>">
				<?php echo pmpro_events_get_icon( 'chevron-left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Previous month', 'pmpro-events' ); ?></span>
			</a>
			<span class="pmpro_events_calendar_month"><?php echo esc_html( wp_date( 'F Y', $first->getTimestamp(), $first->getTimezone() ) ); ?></span>
			<a class="pmpro_events_calendar_arrow" href="<?php echo esc_url( add_query_arg( 'pmpro_events_month', $next ) . $hash ); ?>">
				<?php echo pmpro_events_get_icon( 'chevron-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="screen-reader-text"><?php esc_html_e( 'Next month', 'pmpro-events' ); ?></span>
			</a>
		</div>
		<?php if ( ! $is_now ) { ?>
			<a class="pmpro_events_calendar_today" href="<?php echo esc_url( remove_query_arg( 'pmpro_events_month' ) . $hash ); ?>"><?php esc_html_e( 'Today', 'pmpro-events' ); ?></a>
		<?php } ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Build the card shown when hovering or focusing an event in the calendar.
 *
 * The card repeats what the event page shows, so it is hidden from assistive
 * technology; the event link itself leads to the same details.
 *
 * @since TBD
 *
 * @param PMProEvents_Event $event The event.
 * @return string The markup.
 */
function pmpro_events_get_calendar_card_html( $event ) {
	$rows         = pmpro_events_get_event_detail_rows( $event );
	$registration = $event->has_registration() && ! $event->has_passed() ? $event->get_registration_for_user() : null;

	ob_start();
	?>
	<div class="pmpro_events_calendar_card" aria-hidden="true">
		<span class="pmpro_events_calendar_card_title"><?php echo esc_html( $event->get_title() ); ?></span>
		<?php echo pmpro_events_get_event_summary_html( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php
		if ( ! empty( $registration ) ) {
			echo pmpro_events_get_status_tag_html( __( "You're registered", 'pmpro-events' ), 'success', 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} elseif ( ! empty( $rows['availability'] ) ) {
			?>
			<span class="pmpro_events_summary_item pmpro_events_summary_item-availability">
				<?php echo pmpro_events_get_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="pmpro_events_summary_text"><?php echo esc_html( $rows['availability']['value'] ); ?></span>
			</span>
			<?php
		}
		?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * Get the month the calendar view should show.
 *
 * @since 2.0
 *
 * @return string The month, as 'YYYY-MM'.
 */
function pmpro_events_get_calendar_month() {
	$month = isset( $_GET['pmpro_events_month'] ) ? sanitize_text_field( wp_unslash( $_GET['pmpro_events_month'] ) ) : '';

	if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
		$month = wp_date( 'Y-m' );
	}

	return $month;
}

/**
 * Build the monthly calendar of events.
 *
 * @since 2.0
 *
 * @param string[] $category Optional category slugs to limit the calendar to.
 * @param string   $anchor   Optional. The id of the block, so month links land back on it.
 * @return string The markup.
 */
function pmpro_events_get_events_calendar_html( $category = array(), $anchor = '' ) {
	global $wp_locale;

	$hash = empty( $anchor ) ? '' : '#' . $anchor;

	$timezone = wp_timezone();
	$month    = pmpro_events_get_calendar_month();
	$first    = new DateTime( $month . '-01', $timezone );
	$today    = wp_date( 'Y-m-d' );

	// Group this month's events by the local date they start on.
	$events = PMProEvents_Event::get_events( array(
		'month'    => $month,
		'category' => $category,
		'order'    => 'ASC',
	) );
	$events = pmpro_events_filter_hidden_events( $events );

	$by_day = array();
	foreach ( $events as $event ) {
		$by_day[ substr( (string) $event->start, 0, 10 ) ][] = $event;
	}

	// Lay out the weeks the way the site starts them.
	$start_of_week = (int) get_option( 'start_of_week', 0 );
	$lead_blanks   = ( (int) $first->format( 'w' ) - $start_of_week + 7 ) % 7;
	$days_in_month = (int) $first->format( 't' );


	ob_start();
	?>
	<div class="pmpro_events_calendar">
		<?php echo pmpro_events_get_calendar_nav_html( $first, $hash, 'top' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<table>
			<thead>
				<tr>
					<?php for ( $i = 0; $i < 7; $i++ ) { ?>
						<th scope="col"><?php echo esc_html( $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( ( $start_of_week + $i ) % 7 ) ) ); ?></th>
					<?php } ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<?php
					$cell = 0;

					for ( $i = 0; $i < $lead_blanks; $i++, $cell++ ) {
						echo '<td class="pmpro_events_calendar_blank"></td>';
					}

					for ( $day = 1; $day <= $days_in_month; $day++, $cell++ ) {
						if ( $cell > 0 && 0 === $cell % 7 ) {
							echo '</tr><tr>';
						}

						$date    = $month . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
						$classes = array_filter( array(
							$date === $today ? 'is-today' : '',
							isset( $by_day[ $date ] ) ? 'has-events' : '',
							// Event cards in the last two columns open leftward to stay on screen.
							$cell % 7 >= 5 ? 'opens-left' : '',
						) );
						?>
						<td<?php echo empty( $classes ) ? '' : ' class="' . esc_attr( implode( ' ', $classes ) ) . '"'; ?>>
							<span class="pmpro_events_calendar_day"><?php echo esc_html( number_format_i18n( $day ) ); ?></span>
							<?php // Replaces the day number when narrow screens stack the days into a list. ?>
							<span class="pmpro_events_calendar_date"><?php echo esc_html( wp_date( 'l, F j', ( clone $first )->setDate( (int) $first->format( 'Y' ), (int) $first->format( 'n' ), $day )->getTimestamp(), $timezone ) ); ?></span>
							<?php foreach ( isset( $by_day[ $date ] ) ? $by_day[ $date ] : array() as $event ) { ?>
								<div class="pmpro_events_calendar_item">
									<a class="pmpro_events_calendar_event" href="<?php echo esc_url( $event->get_permalink() ); ?>">
										<?php
										$start = $event->get_date( 'start' );
										if ( ! $event->all_day && ! empty( $start ) ) {
											echo '<span class="pmpro_events_calendar_time">' . esc_html( wp_date( get_option( 'time_format' ), $start->getTimestamp(), $event->get_timezone() ) ) . '</span> ';
										}
										echo esc_html( $event->get_title() );
										?>
									</a>
									<?php echo pmpro_events_get_calendar_card_html( $event ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</div>
							<?php } ?>
						</td>
						<?php
					}

					while ( 0 !== $cell % 7 ) {
						echo '<td class="pmpro_events_calendar_blank"></td>';
						$cell++;
					}
					?>
				</tr>
			</tbody>
		</table>

		<?php // Repeated after the stacked day list on narrow screens, where the top is far away. ?>
		<?php echo pmpro_events_get_calendar_nav_html( $first, $hash, 'bottom' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

		<?php if ( empty( $by_day ) ) { ?>
			<p class="pmpro_events_calendar_empty">
				<?php
				/* translators: 1: the plural event label, lowercased, e.g. "events". 2: the month and year. */
				echo esc_html( sprintf( __( 'There are no %1$s in %2$s.', 'pmpro-events' ), pmpro_events_get_label( 'plural_lowercase' ), wp_date( 'F Y', $first->getTimestamp(), $timezone ) ) );
				?>
			</p>
		<?php } ?>
	</div>
	<?php

	return ob_get_clean();
}
