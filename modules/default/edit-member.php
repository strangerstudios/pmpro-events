<?php
/**
 * The Events panel on PMPro's Edit Member screen.
 *
 * @since 2.0
 */

/**
 * Register the Events panel.
 *
 * @since 2.0
 *
 * @param array $panels The registered panels.
 * @return array The filtered panels.
 */
function pmpro_events_member_edit_panels( $panels ) {
	if ( ! class_exists( 'PMProEvents_Member_Edit_Panel' ) && class_exists( 'PMPro_Member_Edit_Panel' ) ) {
		require_once( PMPRO_EVENTS_DIR . '/modules/default/class-pmproevents-member-edit-panel.php' );
	}

	if ( class_exists( 'PMProEvents_Member_Edit_Panel' ) ) {
		$panels[] = new PMProEvents_Member_Edit_Panel();
	}

	return $panels;
}
add_filter( 'pmpro_member_edit_panels', 'pmpro_events_member_edit_panels' );

/**
 * Build the list of a member's registrations for the Edit Member screen.
 *
 * @since 2.0
 *
 * @param int $user_id The user to list registrations for.
 * @return string The markup.
 */
function pmpro_events_get_member_registrations_html( $user_id ) {
	$registrations = PMProEvents_Event_Registration::get_registrations( array(
		'user_id' => (int) $user_id,
		'orderby' => 'registered_at',
		'order'   => 'DESC',
	) );

	if ( empty( $registrations ) ) {
		ob_start();
		?>
		<p>
			<?php
			/* translators: %s: the singular event label, lowercased, e.g. "event". */
			echo esc_html( sprintf( __( 'This member has no %s registrations.', 'pmpro-events' ), pmpro_events_get_label( 'singular_lowercase' ) ) );
			?>
		</p>
		<?php
		return ob_get_clean();
	}

	ob_start();
	?>
	<table class="wp-list-table widefat striped fixed">
		<thead>
			<tr>
				<th scope="col"><?php echo esc_html( pmpro_events_get_label( 'singular' ) ); ?></th>
				<th scope="col"><?php esc_html_e( 'Date', 'pmpro-events' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Registered', 'pmpro-events' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'pmpro-events' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $registrations as $registration ) { ?>
				<?php
				$event = $registration->get_event();

				// The event may have been deleted out from under the registration.
				if ( ! $event->exists() ) {
					?>
					<tr>
						<td colspan="3">
							<em>
								<?php
								/* translators: 1: the singular event label, lowercased, 2: the deleted event's ID. */
								echo esc_html( sprintf( __( 'Deleted %1$s (#%2$d)', 'pmpro-events' ), pmpro_events_get_label( 'singular_lowercase' ), $registration->event_id ) );
								?>
							</em>
						</td>
						<td><?php echo 'cancelled' === $registration->status ? esc_html__( 'Cancelled', 'pmpro-events' ) : esc_html__( 'Active', 'pmpro-events' ); ?></td>
					</tr>
					<?php
					continue;
				}
				?>
				<tr>
					<td>
						<a href="<?php echo esc_url( get_edit_post_link( $event->get_id() ) ); ?>"><?php echo esc_html( $event->get_title() ); ?></a>
						<div class="row-actions">
							<span><a href="<?php echo esc_url( pmpro_events_get_registrations_url( $event->get_id() ) ); ?>"><?php esc_html_e( 'Registrations', 'pmpro-events' ); ?></a></span>
						</div>
					</td>
					<td>
						<?php
						$start = $event->get_formatted_date( 'start' );
						echo esc_html( empty( $start ) ? '—' : $start );
						?>
					</td>
					<td>
						<?php
						if ( empty( $registration->registered_at ) || '0000-00-00 00:00:00' === $registration->registered_at ) {
							echo '&mdash;';
						} else {
							echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $registration->registered_at . ' UTC' ) ) );
						}
						?>
					</td>
					<td>
						<?php
						if ( 'cancelled' === $registration->status ) {
							esc_html_e( 'Cancelled', 'pmpro-events' );
						} elseif ( $event->has_passed() ) {
							esc_html_e( 'Attended', 'pmpro-events' );
						} else {
							esc_html_e( 'Active', 'pmpro-events' );
						}
						?>
					</td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php

	return ob_get_clean();
}
