<?php
/**
 * Level auto-creation and group management.
 *
 * @package PMPro_Events
 * @since 2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create the Events level group if it doesn't exist.
 *
 * @since 2.0
 *
 * @return int Group ID.
 */
function pmpro_events_maybe_create_level_group() {
	$group_id = get_option( 'pmpro_events_level_group_id', 0 );

	// Verify the group still exists.
	if ( $group_id && function_exists( 'pmpro_get_level_group' ) ) {
		$group = pmpro_get_level_group( $group_id );
		if ( $group ) {
			return $group_id;
		}
	}

	// Create new group.
	if ( function_exists( 'pmpro_create_level_group' ) ) {
		$singular = pmpro_events_get_singular_name();
		$plural   = pmpro_events_get_plural_name();
		$group_id = pmpro_create_level_group( $plural, true );
		if ( $group_id ) {
			update_option( 'pmpro_events_level_group_id', $group_id );
			return $group_id;
		}
	}

	return 0;
}

/**
 * Create or update the PMPro level for an event.
 *
 * @since 2.0
 *
 * @param int        $post_id Post ID of the event.
 * @param PMPro_Event $event   Event object.
 * @return int|false Level ID or false on failure.
 */
function pmpro_events_sync_level( $post_id, $event ) {
	$post  = get_post( $post_id );
	$price = get_post_meta( $post_id, '_pmpro_event_price', true );

	if ( ! $post ) {
		return false;
	}

	$price = floatval( $price );

	// Create or update the level.
	if ( $event->level_id ) {
		// Update existing level.
		$level = new PMPro_Membership_Level( $event->level_id );
		if ( $level->id ) {
			$level->name            = $post->post_title;
			$level->initial_payment = $price;
			$level->billing_amount  = 0;
			$level->save();

			// Update access restrictions.
			if ( function_exists( 'pmpro_update_post_level_restrictions' ) ) {
				$level_ids = array( $event->level_id );
				if ( $event->member_level_id ) {
					$level_ids[] = $event->member_level_id;
				}
				pmpro_update_post_level_restrictions( $post_id, $level_ids );
			}

			return $level->id;
		}
	}

	// Create new level.
	$level = new PMPro_Membership_Level();
	/* translators: %s: event title */
	$level->name            = sprintf( __( '%s (Event)', 'pmpro-events' ), $post->post_title );
	$level->description     = '';
	$level->initial_payment = $price;
	$level->billing_amount  = 0;
	$level->cycle_number    = 0;
	$level->cycle_period    = '';
	$level->billing_limit   = 0;
	$level->trial_amount    = 0;
	$level->trial_limit     = 0;
	$level->allow_signups   = 1;
	$level->expiration_number = 0;
	$level->expiration_period = '';
	$level->save();

	if ( ! $level->id ) {
		return false;
	}

	// Add to events level group.
	$group_id = pmpro_events_maybe_create_level_group();
	if ( $group_id && function_exists( 'pmpro_add_level_to_group' ) ) {
		pmpro_add_level_to_group( $level->id, $group_id );
	}

	// Set post access restrictions.
	$level_ids = array( $level->id );

	// Update event record.
	$event->level_id = $level->id;

	return $level->id;
}

/**
 * Create or update the member-price level for an event.
 *
 * @since 2.0
 *
 * @param int        $post_id Post ID of the event.
 * @param PMPro_Event $event   Event object.
 * @return int|false Level ID or false on failure.
 */
function pmpro_events_sync_member_level( $post_id, $event ) {
	$post         = get_post( $post_id );
	$member_price = get_post_meta( $post_id, '_pmpro_event_member_price', true );

	if ( ! $post || $member_price === '' ) {
		// No member price set. If a member level exists, deactivate it.
		if ( $event->member_level_id ) {
			$level = new PMPro_Membership_Level( $event->member_level_id );
			if ( $level->id ) {
				$level->allow_signups = 0;
				$level->save();
			}
			$event->member_level_id = null;
		}
		return false;
	}

	$member_price = floatval( $member_price );

	if ( $event->member_level_id ) {
		// Update existing member level.
		$level = new PMPro_Membership_Level( $event->member_level_id );
		if ( $level->id ) {
			/* translators: %s: event title */
			$level->name            = sprintf( __( '%s (Member)', 'pmpro-events' ), $post->post_title );
			$level->initial_payment = $member_price;
			$level->billing_amount  = 0;
			$level->allow_signups   = 1;
			$level->save();
			return $level->id;
		}
	}

	// Create new member level.
	$level = new PMPro_Membership_Level();
	/* translators: %s: event title */
	$level->name            = sprintf( __( '%s (Member)', 'pmpro-events' ), $post->post_title );
	$level->description     = '';
	$level->initial_payment = $member_price;
	$level->billing_amount  = 0;
	$level->cycle_number    = 0;
	$level->cycle_period    = '';
	$level->billing_limit   = 0;
	$level->trial_amount    = 0;
	$level->trial_limit     = 0;
	$level->allow_signups   = 1;
	$level->expiration_number = 0;
	$level->expiration_period = '';
	$level->save();

	if ( ! $level->id ) {
		return false;
	}

	// Add to events level group.
	$group_id = pmpro_events_maybe_create_level_group();
	if ( $group_id && function_exists( 'pmpro_add_level_to_group' ) ) {
		pmpro_add_level_to_group( $level->id, $group_id );
	}

	$event->member_level_id = $level->id;

	return $level->id;
}

/**
 * Handle event deletion — deactivate associated levels.
 *
 * @since 2.0
 *
 * @param int $post_id Post ID.
 */
function pmpro_events_on_trash( $post_id ) {
	if ( get_post_type( $post_id ) !== 'pmpro_event' ) {
		return;
	}

	$event = PMPro_Event::get_by_post_id( $post_id );
	if ( ! $event ) {
		return;
	}

	// Deactivate levels (don't delete — orders reference them).
	foreach ( array( $event->level_id, $event->member_level_id ) as $level_id ) {
		if ( ! $level_id ) {
			continue;
		}
		$level = new PMPro_Membership_Level( $level_id );
		if ( $level->id ) {
			$level->allow_signups = 0;
			$level->save();
		}
	}
}
add_action( 'wp_trash_post', 'pmpro_events_on_trash' );
