<?php
/**
 * The module registry.
 *
 * pmpro-events can either run its own built-in "Default" events module or defer
 * to a detected third-party events plugin. The registry describes each module,
 * how to detect it, and whether the site can toggle it.
 *
 * @since 2.0
 */

/**
 * Get the list of registered modules.
 *
 * Each module is keyed by its slug and describes:
 *  - name:       Human readable module name.
 *  - toggleable: Whether the site can turn this module on and off. Third-party
 *                modules are detect-only, so they are never toggleable.
 *  - detect:     Callback that returns true when the module's events plugin is
 *                present. The Default module has no plugin to detect.
 *
 * @since 2.0
 *
 * @return array The registered modules.
 */
function pmpro_events_get_modules() {
	$modules = array(
		'default' => array(
			'name'       => __( 'Default', 'pmpro-events' ),
			'toggleable' => true,
			'detect'     => null,
		),
		'the-events-calendar' => array(
			'name'       => __( 'The Events Calendar', 'pmpro-events' ),
			'toggleable' => false,
			'detect'     => 'pmpro_events_detect_the_events_calendar',
		),
		'events-manager' => array(
			'name'       => __( 'Events Manager', 'pmpro-events' ),
			'toggleable' => false,
			'detect'     => 'pmpro_events_detect_events_manager',
		),
		'sugar-calendar' => array(
			'name'       => __( 'Sugar Calendar', 'pmpro-events' ),
			'toggleable' => false,
			'detect'     => 'pmpro_events_detect_sugar_calendar',
		),
		'all-in-one-event-calendar' => array(
			'name'       => __( 'Timely All-in-One Event Calendar', 'pmpro-events' ),
			'toggleable' => false,
			'detect'     => 'pmpro_events_detect_all_in_one_event_calendar',
		),
	);

	return apply_filters( 'pmpro_events_modules', $modules );
}

/**
 * Detect The Events Calendar.
 *
 * @since 2.0
 *
 * @return bool Whether the plugin is active.
 */
function pmpro_events_detect_the_events_calendar() {
	return class_exists( 'Tribe__Events__Main' );
}

/**
 * Detect Events Manager.
 *
 * @since 2.0
 *
 * @return bool Whether the plugin is active.
 */
function pmpro_events_detect_events_manager() {
	return defined( 'EM_VERSION' );
}

/**
 * Detect Sugar Calendar.
 *
 * @since 2.0
 *
 * @return bool Whether the plugin is active.
 */
function pmpro_events_detect_sugar_calendar() {
	return class_exists( 'Sugar_Calendar\\Plugin' );
}

/**
 * Detect the Timely All-in-One Event Calendar.
 *
 * Only versions below 3.0.0 are supported by our module.
 *
 * @since 2.0
 *
 * @return bool Whether a supported version of the plugin is active.
 */
function pmpro_events_detect_all_in_one_event_calendar() {
	return defined( 'AI1EC_PATH' ) && defined( 'AI1EC_VERSION' ) && AI1EC_VERSION < '3.0.0';
}

/**
 * Check whether a module's events plugin was detected on this site.
 *
 * @since 2.0
 *
 * @param string $module The module slug.
 * @return bool Whether the module was detected.
 */
function pmpro_events_is_module_detected( $module ) {
	$modules = pmpro_events_get_modules();

	if ( ! isset( $modules[ $module ] ) || empty( $modules[ $module ]['detect'] ) ) {
		return false;
	}

	return (bool) call_user_func( $modules[ $module ]['detect'] );
}

/**
 * Check whether any third-party events plugin was detected on this site.
 *
 * @since 2.0
 *
 * @return bool Whether a third-party events plugin was detected.
 */
function pmpro_events_has_third_party_module() {
	foreach ( pmpro_events_get_modules() as $module => $data ) {
		if ( ! empty( $data['detect'] ) && pmpro_events_is_module_detected( $module ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check whether a module should be loaded.
 *
 * Toggleable modules are stored in the pmpro_events_modules option. Detect-only
 * modules are active whenever their events plugin is present.
 *
 * @since 2.0
 *
 * @param string $module The module slug.
 * @return bool Whether the module is active.
 */
function pmpro_events_is_module_active( $module ) {
	$modules = pmpro_events_get_modules();

	if ( ! isset( $modules[ $module ] ) ) {
		return false;
	}

	if ( empty( $modules[ $module ]['toggleable'] ) ) {
		return pmpro_events_is_module_detected( $module );
	}

	$active = get_option( 'pmpro_events_modules', array() );

	return is_array( $active ) && in_array( $module, $active, true );
}

/**
 * Set the initial module configuration on first install.
 *
 * The Default module is only enabled when the site has no third-party events
 * plugin, so that activating this add-on never changes the behavior of a site
 * that is already running one.
 *
 * @since 2.0
 */
function pmpro_events_set_default_modules() {
	// Don't overwrite an existing configuration.
	if ( false !== get_option( 'pmpro_events_modules', false ) ) {
		return;
	}

	$active = pmpro_events_has_third_party_module() ? array() : array( 'default' );

	update_option( 'pmpro_events_modules', $active, 'no' );
}
