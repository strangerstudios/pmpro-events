<?php
/**
 * Admin metaboxes, list columns, and CSV export.
 *
 * @package PMPro_Events
 * @since 2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register metaboxes.
 *
 * @since 2.0
 */
function pmpro_events_add_meta_boxes() {
	$singular = pmpro_events_get_singular_name();

	add_meta_box(
		'pmpro_event_details',
		/* translators: %s: singular event name */
		sprintf( __( '%s Details', 'pmpro-events' ), $singular ),
		'pmpro_events_meta_box_details',
		'pmpro_event',
		'normal',
		'high'
	);

	add_meta_box(
		'pmpro_event_pricing',
		/* translators: %s: singular event name */
		sprintf( __( '%s Pricing', 'pmpro-events' ), $singular ),
		'pmpro_events_meta_box_pricing',
		'pmpro_event',
		'normal',
		'default'
	);

	// Only show registrations for published events.
	global $post;
	if ( $post && $post->post_status === 'publish' ) {
		add_meta_box(
			'pmpro_event_registrations',
			__( 'Registrations', 'pmpro-events' ),
			'pmpro_events_meta_box_registrations',
			'pmpro_event',
			'normal',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'pmpro_events_add_meta_boxes' );

/**
 * Event Details metabox.
 *
 * @since 2.0
 *
 * @param WP_Post $post Current post.
 */
function pmpro_events_meta_box_details( $post ) {
	wp_nonce_field( 'pmpro_events_save', '_pmpro_events_nonce' );

	$event = PMPro_Event::get_by_post_id( $post->ID );

	$start_date    = '';
	$start_time    = '09:00';
	$end_date      = '';
	$end_time      = '17:00';
	$timezone      = wp_timezone_string();
	$all_day       = false;
	$capacity      = 0;
	$venue_name    = '';
	$venue_address = '';

	if ( $event ) {
		$start_dt = $event->get_start_datetime();
		$end_dt   = $event->get_end_datetime();
		if ( $start_dt ) {
			$start_date = $start_dt->format( 'Y-m-d' );
			$start_time = $start_dt->format( 'H:i' );
		}
		if ( $end_dt ) {
			$end_date = $end_dt->format( 'Y-m-d' );
			$end_time = $end_dt->format( 'H:i' );
		}
		$timezone      = $event->timezone ?: $timezone;
		$all_day       = (bool) $event->all_day;
		$capacity      = $event->capacity;
		$venue_name    = $event->venue_name;
		$venue_address = $event->venue_address;
	}

	// Check for UTC offset timezone.
	$tz_warning = preg_match( '/^UTC[+-]?\d*$/', $timezone ) || $timezone === 'UTC';
	$timezones  = timezone_identifiers_list();

	?>
	<table class="form-table pmpro-events-details-table">
		<tr>
			<th scope="row"><label for="pmpro_event_start_date"><?php esc_html_e( 'Start', 'pmpro-events' ); ?></label></th>
			<td>
				<input type="date" id="pmpro_event_start_date" name="pmpro_event_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
				<input type="time" id="pmpro_event_start_time" name="pmpro_event_start_time" value="<?php echo esc_attr( $start_time ); ?>" class="pmpro-events-time-input" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pmpro_event_end_date"><?php esc_html_e( 'End', 'pmpro-events' ); ?></label></th>
			<td>
				<input type="date" id="pmpro_event_end_date" name="pmpro_event_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
				<input type="time" id="pmpro_event_end_time" name="pmpro_event_end_time" value="<?php echo esc_attr( $end_time ); ?>" class="pmpro-events-time-input" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pmpro_event_timezone"><?php esc_html_e( 'Timezone', 'pmpro-events' ); ?></label></th>
			<td>
				<select id="pmpro_event_timezone" name="pmpro_event_timezone" style="max-width: 400px;">
					<?php foreach ( $timezones as $tz ) : ?>
						<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( $timezone, $tz ); ?>>
							<?php echo esc_html( $tz ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( $tz_warning ) : ?>
					<p class="description" style="color: #d63638;">
						<?php esc_html_e( 'Warning: Your WordPress timezone is set to a UTC offset. Set a named timezone (e.g. America/New_York) in Settings > General for accurate event scheduling.', 'pmpro-events' ); ?>
					</p>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"></th>
			<td>
				<label>
					<input type="checkbox" name="pmpro_event_all_day" value="1" <?php checked( $all_day ); ?> />
					<?php esc_html_e( 'All Day Event', 'pmpro-events' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pmpro_event_venue_name"><?php esc_html_e( 'Venue Name', 'pmpro-events' ); ?></label></th>
			<td>
				<input type="text" id="pmpro_event_venue_name" name="pmpro_event_venue_name" value="<?php echo esc_attr( $venue_name ); ?>" class="regular-text" />
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pmpro_event_venue_address"><?php esc_html_e( 'Address', 'pmpro-events' ); ?></label></th>
			<td>
				<textarea id="pmpro_event_venue_address" name="pmpro_event_venue_address" rows="3" class="large-text"><?php echo esc_textarea( $venue_address ); ?></textarea>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pmpro_event_capacity"><?php esc_html_e( 'Capacity', 'pmpro-events' ); ?></label></th>
			<td>
				<input type="number" id="pmpro_event_capacity" name="pmpro_event_capacity" value="<?php echo esc_attr( $capacity ); ?>" min="0" step="1" />
				<p class="description"><?php esc_html_e( '0 = unlimited', 'pmpro-events' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}

/**
 * Event Pricing metabox.
 *
 * @since 2.0
 *
 * @param WP_Post $post Current post.
 */
function pmpro_events_meta_box_pricing( $post ) {
	$price        = get_post_meta( $post->ID, '_pmpro_event_price', true );
	$member_price = get_post_meta( $post->ID, '_pmpro_event_member_price', true );
	$free_for_members = $member_price === '0' || $member_price === '0.00';

	$currency_symbol = function_exists( 'pmpro_get_currency_symbol' ) ? pmpro_get_currency_symbol() : '$';
	?>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="pmpro_event_price"><?php esc_html_e( 'Price', 'pmpro-events' ); ?></label></th>
			<td>
				<span class="pmpro-events-currency"><?php echo esc_html( $currency_symbol ); ?></span>
				<input type="number" id="pmpro_event_price" name="pmpro_event_price" value="<?php echo esc_attr( $price ); ?>" min="0" step="0.01" />
				<p class="description"><?php esc_html_e( 'Non-member price. Set to 0 for a free event.', 'pmpro-events' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="pmpro_event_member_price"><?php esc_html_e( 'Member Price', 'pmpro-events' ); ?></label></th>
			<td>
				<label style="display: block; margin-bottom: 8px;">
					<input type="checkbox" id="pmpro_event_free_for_members" name="pmpro_event_free_for_members" value="1" <?php checked( $free_for_members ); ?> />
					<?php esc_html_e( 'Free for members', 'pmpro-events' ); ?>
				</label>
				<span class="pmpro-events-currency"><?php echo esc_html( $currency_symbol ); ?></span>
				<input type="number" id="pmpro_event_member_price" name="pmpro_event_member_price" value="<?php echo esc_attr( $member_price ); ?>" min="0" step="0.01" />
				<p class="description"><?php esc_html_e( 'Leave empty for no member pricing. Set to 0 for free for members.', 'pmpro-events' ); ?></p>
			</td>
		</tr>
	</table>
	<p class="description">
		<?php esc_html_e( 'Publishing creates a membership level for this event. Registrants check out for this level.', 'pmpro-events' ); ?>
	</p>
	<?php
}

/**
 * Registrations metabox.
 *
 * @since 2.0
 *
 * @param WP_Post $post Current post.
 */
function pmpro_events_meta_box_registrations( $post ) {
	$event = PMPro_Event::get_by_post_id( $post->ID );
	if ( ! $event ) {
		echo '<p>' . esc_html__( 'No event data found. Save the event first.', 'pmpro-events' ) . '</p>';
		return;
	}

	$registrations = $event->get_registrations();
	$count         = $event->get_registration_count();
	$remaining     = $event->get_remaining_capacity();

	// Capacity display.
	if ( $remaining === null ) {
		/* translators: %d: registration count */
		$capacity_text = sprintf( __( '%d registered (unlimited)', 'pmpro-events' ), $count );
	} else {
		/* translators: 1: registration count, 2: total capacity */
		$capacity_text = sprintf( __( '%1$d of %2$d spots filled', 'pmpro-events' ), $count, $event->capacity );
	}
	?>
	<p><strong><?php echo esc_html( $capacity_text ); ?></strong></p>

	<?php if ( ! empty( $registrations ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'User', 'pmpro-events' ); ?></th>
					<th><?php esc_html_e( 'Email', 'pmpro-events' ); ?></th>
					<th><?php esc_html_e( 'Registered', 'pmpro-events' ); ?></th>
					<th><?php esc_html_e( 'Order', 'pmpro-events' ); ?></th>
					<th><?php esc_html_e( 'Status', 'pmpro-events' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $registrations as $reg ) : ?>
					<tr>
						<td><?php echo esc_html( $reg->display_name ?: $reg->user_login ); ?></td>
						<td><?php echo esc_html( $reg->user_email ); ?></td>
						<td><?php echo esc_html( $reg->registered_at ); ?></td>
						<td>
							<?php if ( $reg->order_id ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=pmpro-orders&order=' . $reg->order_id ) ); ?>">
									#<?php echo esc_html( $reg->order_id ); ?>
								</a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( ucfirst( $reg->status ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'No registrations yet.', 'pmpro-events' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $registrations ) ) : ?>
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=pmpro_events_export_csv&event_id=' . $event->id . '&_wpnonce=' . wp_create_nonce( 'pmpro_events_export' ) ) ); ?>" class="button">
				<?php esc_html_e( 'Download CSV', 'pmpro-events' ); ?>
			</a>
		</p>
	<?php endif;
}

/**
 * Save metabox data.
 *
 * @since 2.0
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 */
function pmpro_events_save_meta( $post_id, $post ) {
	if ( ! isset( $_POST['_pmpro_events_nonce'] ) || ! wp_verify_nonce( $_POST['_pmpro_events_nonce'], 'pmpro_events_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( $post->post_type !== 'pmpro_event' ) {
		return;
	}

	// Save pricing meta.
	$price = sanitize_text_field( $_POST['pmpro_event_price'] ?? '' );
	$member_price = sanitize_text_field( $_POST['pmpro_event_member_price'] ?? '' );

	// If "free for members" is checked, set member price to 0.
	if ( ! empty( $_POST['pmpro_event_free_for_members'] ) ) {
		$member_price = '0';
	}

	update_post_meta( $post_id, '_pmpro_event_price', $price );
	update_post_meta( $post_id, '_pmpro_event_member_price', $member_price );

	// Get or create event record.
	$event = PMPro_Event::get_by_post_id( $post_id );
	if ( ! $event ) {
		$event = new PMPro_Event();
		$event->post_id = $post_id;
	}

	// Sync schedule.
	$start_date = sanitize_text_field( $_POST['pmpro_event_start_date'] ?? '' );
	if ( $start_date ) {
		$event->sync_schedule( array(
			'start_date'    => $start_date,
			'start_time'    => sanitize_text_field( $_POST['pmpro_event_start_time'] ?? '09:00' ),
			'end_date'      => sanitize_text_field( $_POST['pmpro_event_end_date'] ?? '' ),
			'end_time'      => sanitize_text_field( $_POST['pmpro_event_end_time'] ?? '17:00' ),
			'timezone'      => sanitize_text_field( $_POST['pmpro_event_timezone'] ?? wp_timezone_string() ),
			'all_day'       => ! empty( $_POST['pmpro_event_all_day'] ),
			'capacity'      => absint( $_POST['pmpro_event_capacity'] ?? 0 ),
			'venue_name'    => sanitize_text_field( $_POST['pmpro_event_venue_name'] ?? '' ),
			'venue_address' => sanitize_textarea_field( $_POST['pmpro_event_venue_address'] ?? '' ),
		) );
	}

	// Sync levels on publish.
	if ( $post->post_status === 'publish' ) {
		$level_id = pmpro_events_sync_level( $post_id, $event );
		if ( $level_id && $event->level_id != $level_id ) {
			$event->level_id = $level_id;
		}

		// Handle member level.
		if ( $member_price !== '' ) {
			$member_level_id = pmpro_events_sync_member_level( $post_id, $event );
			if ( $member_level_id ) {
				$event->member_level_id = $member_level_id;
			}
		} else {
			// Clear member level if no member price.
			pmpro_events_sync_member_level( $post_id, $event );
		}

		// Update access restrictions with all event level IDs.
		if ( function_exists( 'pmpro_update_post_level_restrictions' ) ) {
			$level_ids = array();
			if ( $event->level_id ) {
				$level_ids[] = $event->level_id;
			}
			if ( $event->member_level_id ) {
				$level_ids[] = $event->member_level_id;
			}
			pmpro_update_post_level_restrictions( $post_id, $level_ids );
		}

		// Save updated level IDs.
		$event->save();
	}
}
add_action( 'save_post_pmpro_event', 'pmpro_events_save_meta', 10, 2 );

/**
 * Add custom columns to the events list table.
 *
 * @since 2.0
 *
 * @param array $columns Existing columns.
 * @return array
 */
function pmpro_events_list_columns( $columns ) {
	$new_columns = array();
	foreach ( $columns as $key => $label ) {
		$new_columns[ $key ] = $label;
		if ( $key === 'title' ) {
			$new_columns['pmpro_event_start']         = __( 'Start Date', 'pmpro-events' );
			$new_columns['pmpro_event_capacity']      = __( 'Capacity', 'pmpro-events' );
			$new_columns['pmpro_event_registrations'] = __( 'Registrations', 'pmpro-events' );
		}
	}
	// Remove date column (we have start date).
	unset( $new_columns['date'] );
	return $new_columns;
}
add_filter( 'manage_pmpro_event_posts_columns', 'pmpro_events_list_columns' );

/**
 * Render custom column content.
 *
 * @since 2.0
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function pmpro_events_list_column_content( $column, $post_id ) {
	$event = PMPro_Event::get_by_post_id( $post_id );

	switch ( $column ) {
		case 'pmpro_event_start':
			echo $event ? esc_html( $event->get_schedule_details() ) : '&mdash;';
			break;

		case 'pmpro_event_capacity':
			if ( ! $event ) {
				echo '&mdash;';
			} elseif ( $event->capacity <= 0 ) {
				esc_html_e( 'Unlimited', 'pmpro-events' );
			} else {
				$remaining = $event->get_remaining_capacity();
				/* translators: 1: remaining spots, 2: total capacity */
				printf( esc_html__( '%1$d / %2$d', 'pmpro-events' ), $remaining, $event->capacity );
			}
			break;

		case 'pmpro_event_registrations':
			echo $event ? esc_html( $event->get_registration_count() ) : '0';
			break;
	}
}
add_action( 'manage_pmpro_event_posts_custom_column', 'pmpro_events_list_column_content', 10, 2 );

/**
 * Make columns sortable.
 *
 * @since 2.0
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function pmpro_events_sortable_columns( $columns ) {
	$columns['pmpro_event_start'] = 'pmpro_event_start';
	return $columns;
}
add_filter( 'manage_edit-pmpro_event_sortable_columns', 'pmpro_events_sortable_columns' );

/**
 * Handle sorting by start date.
 *
 * @since 2.0
 *
 * @param WP_Query $query Query object.
 */
function pmpro_events_admin_sort( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->get( 'post_type' ) !== 'pmpro_event' ) {
		return;
	}

	$orderby = $query->get( 'orderby' );
	if ( $orderby === 'pmpro_event_start' || empty( $orderby ) ) {
		add_filter( 'posts_join', 'pmpro_events_archive_join' );
		add_filter( 'posts_orderby', function( $orderby_sql ) use ( $query ) {
			$order = $query->get( 'order' ) ?: 'ASC';
			return "pe.start_utc {$order}";
		} );
	}
}
add_action( 'pre_get_posts', 'pmpro_events_admin_sort' );

/**
 * CSV export handler.
 *
 * @since 2.0
 */
function pmpro_events_export_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}

	check_ajax_referer( 'pmpro_events_export', '_wpnonce' );

	$event_id = absint( $_GET['event_id'] ?? 0 );
	$event = PMPro_Event::get_by_id( $event_id );
	if ( ! $event ) {
		wp_die( 'Event not found' );
	}

	$registrations = $event->get_registrations( '' ); // All statuses.
	$post = get_post( $event->post_id );
	$filename = sanitize_file_name( ( $post ? $post->post_title : 'event' ) . '-registrations.csv' );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'User', 'Email', 'Registered', 'Order ID', 'Status' ) );

	foreach ( $registrations as $reg ) {
		fputcsv( $output, array(
			$reg->display_name ?: $reg->user_login,
			$reg->user_email,
			$reg->registered_at,
			$reg->order_id,
			$reg->status,
		) );
	}

	fclose( $output );
	exit;
}
add_action( 'wp_ajax_pmpro_events_export_csv', 'pmpro_events_export_csv' );
