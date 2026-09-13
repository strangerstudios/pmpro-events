<?php
/**
 * Enqueue the block editor sidebar panels for the event edit screen.
 *
 * @since 2.0
 */

/**
 * Enqueue the event editor script.
 *
 * @since 2.0
 */
function pmpro_events_enqueue_block_editor_assets() {
	$screen = get_current_screen();

	if ( empty( $screen ) || PMProEvents_Event::POST_TYPE !== $screen->post_type ) {
		return;
	}

	$asset_file = PMPRO_EVENTS_DIR . '/build/index.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require( $asset_file );

	wp_enqueue_script(
		'pmpro-events-editor',
		PMPRO_EVENTS_URL . '/build/index.js',
		$asset['dependencies'],
		$asset['version'],
		true
	);

	wp_localize_script(
		'pmpro-events-editor',
		'pmproEventsEditor',
		array(
			'labels' => array(
				'singular' => pmpro_events_get_label( 'singular' ),
				'plural'   => pmpro_events_get_label( 'plural' ),
			),
			'timezones'            => pmpro_events_get_timezone_choices(),
			'siteTimezone'         => wp_timezone_string(),
			'siteHasNamedTimezone' => pmpro_events_site_has_named_timezone(),
		)
	);

	wp_set_script_translations( 'pmpro-events-editor', 'pmpro-events', PMPRO_EVENTS_DIR . '/languages' );
}
add_action( 'enqueue_block_editor_assets', 'pmpro_events_enqueue_block_editor_assets' );
