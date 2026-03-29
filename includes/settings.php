<?php
/**
 * Settings and terminology helpers.
 *
 * @package PMPro_Events
 * @since 2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the singular event name.
 *
 * @since 2.0
 *
 * @return string
 */
function pmpro_events_get_singular_name() {
	return get_option( 'pmpro_events_singular_name', __( 'Event', 'pmpro-events' ) );
}

/**
 * Get the plural event name.
 *
 * @since 2.0
 *
 * @return string
 */
function pmpro_events_get_plural_name() {
	return get_option( 'pmpro_events_plural_name', __( 'Events', 'pmpro-events' ) );
}

/**
 * Register settings fields on PMPro Advanced Settings page.
 *
 * @since 2.0
 *
 * @param array $settings PMPro advanced settings fields.
 * @return array
 */
function pmpro_events_advanced_settings( $settings ) {
	$settings['pmpro_events_singular_name'] = array(
		'field_name'  => 'pmpro_events_singular_name',
		'field_type'  => 'text',
		'label'       => __( 'Event Singular Name', 'pmpro-events' ),
		'description' => __( 'Customize the singular label for events (e.g., "Session", "Webinar", "Workshop").', 'pmpro-events' ),
		'default'     => __( 'Event', 'pmpro-events' ),
	);

	$settings['pmpro_events_plural_name'] = array(
		'field_name'  => 'pmpro_events_plural_name',
		'field_type'  => 'text',
		'label'       => __( 'Event Plural Name', 'pmpro-events' ),
		'description' => __( 'Customize the plural label for events (e.g., "Sessions", "Webinars", "Workshops").', 'pmpro-events' ),
		'default'     => __( 'Events', 'pmpro-events' ),
	);

	return $settings;
}
add_filter( 'pmpro_custom_advanced_settings', 'pmpro_events_advanced_settings' );
