<?php
/*
Plugin Name: Paid Memberships Pro - Events Add On
Plugin URI: https://www.paidmembershipspro.com/add-ons/events-for-members-only/
Description: A first-party events system for PMPro. Create events, sell tickets via PMPro checkout, and track registrations.
Version: 2.0-alpha
Author: Paid Memberships Pro
Author URI: https://www.paidmembershipspro.com
Text Domain: pmpro-events
Domain Path: /languages
*/

defined( 'ABSPATH' ) || exit;

define( 'PMPRO_EVENTS_VERSION', '2.0-alpha' );
define( 'PMPRO_EVENTS_BASENAME', plugin_basename( __FILE__ ) );
define( 'PMPRO_EVENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PMPRO_EVENTS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if PMPro is active and meets minimum version.
 *
 * @since 2.0
 *
 * @return bool
 */
function pmpro_events_check_dependencies() {
	if ( ! defined( 'PMPRO_VERSION' ) ) {
		return false;
	}
	return version_compare( PMPRO_VERSION, '3.0', '>=' );
}

/**
 * Show admin notice if PMPro is not active.
 *
 * @since 2.0
 */
function pmpro_events_admin_notice_no_pmpro() {
	if ( pmpro_events_check_dependencies() ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'PMPro Events requires Paid Memberships Pro 3.0 or later.', 'pmpro-events' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'pmpro_events_admin_notice_no_pmpro' );

/**
 * Initialize the plugin.
 *
 * @since 2.0
 */
function pmpro_events_init() {
	if ( ! pmpro_events_check_dependencies() ) {
		return;
	}

	// Core.
	require_once PMPRO_EVENTS_DIR . 'includes/db.php';
	require_once PMPRO_EVENTS_DIR . 'includes/cpt.php';
	require_once PMPRO_EVENTS_DIR . 'includes/class-pmpro-event.php';
	require_once PMPRO_EVENTS_DIR . 'includes/settings.php';
	require_once PMPRO_EVENTS_DIR . 'includes/levels.php';
	require_once PMPRO_EVENTS_DIR . 'includes/registration.php';
	require_once PMPRO_EVENTS_DIR . 'includes/template.php';
	require_once PMPRO_EVENTS_DIR . 'includes/ics.php';

	// Admin.
	if ( is_admin() ) {
		require_once PMPRO_EVENTS_DIR . 'includes/admin.php';
	}
}
add_action( 'plugins_loaded', 'pmpro_events_init' );

/**
 * Run on activation.
 *
 * @since 2.0
 */
function pmpro_events_activate() {
	if ( ! pmpro_events_check_dependencies() ) {
		return;
	}

	require_once PMPRO_EVENTS_DIR . 'includes/settings.php';
	require_once PMPRO_EVENTS_DIR . 'includes/db.php';
	pmpro_events_create_tables();

	require_once PMPRO_EVENTS_DIR . 'includes/cpt.php';
	pmpro_events_register_post_type();

	require_once PMPRO_EVENTS_DIR . 'includes/levels.php';
	pmpro_events_maybe_create_level_group();

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'pmpro_events_activate' );

/**
 * Load text domain.
 *
 * @since 2.0
 */
function pmpro_events_load_textdomain() {
	load_plugin_textdomain( 'pmpro-events', false, basename( PMPRO_EVENTS_DIR ) . '/languages' );
}
add_action( 'init', 'pmpro_events_load_textdomain' );

/**
 * Enqueue frontend assets.
 *
 * @since 2.0
 */
function pmpro_events_enqueue_assets() {
	if ( ! is_singular( 'pmpro_event' ) ) {
		return;
	}

	wp_enqueue_style(
		'pmpro-events',
		PMPRO_EVENTS_URL . 'css/pmpro-events.css',
		array(),
		PMPRO_EVENTS_VERSION
	);

	wp_enqueue_script(
		'pmpro-events-ticket-picker',
		PMPRO_EVENTS_URL . 'js/ticket-picker.js',
		array(),
		PMPRO_EVENTS_VERSION,
		true
	);

	global $post;
	$event = PMPro_Event::get_by_post_id( $post->ID );
	if ( $event ) {
		wp_localize_script( 'pmpro-events-ticket-picker', 'pmpro_events', array(
			'checkout_url' => pmpro_url( 'checkout' ),
			'level_id'     => $event->level_id,
			'member_level_id' => $event->member_level_id,
		) );
	}
}
add_action( 'wp_enqueue_scripts', 'pmpro_events_enqueue_assets' );

/**
 * Enqueue admin assets.
 *
 * @since 2.0
 */
function pmpro_events_admin_enqueue_assets( $hook ) {
	global $post_type;
	if ( 'pmpro_event' !== $post_type ) {
		return;
	}

	wp_enqueue_style(
		'pmpro-events-admin',
		PMPRO_EVENTS_URL . 'css/pmpro-events.css',
		array(),
		PMPRO_EVENTS_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'pmpro_events_admin_enqueue_assets' );

/**
 * Plugin row meta links.
 *
 * @since 2.0
 */
function pmpro_events_plugin_row_meta( $links, $file ) {
	if ( strpos( $file, 'pmpro-events.php' ) !== false ) {
		$links[] = '<a href="' . esc_url( 'https://www.paidmembershipspro.com/add-ons/events-for-members-only/' ) . '">' . esc_html__( 'Docs', 'pmpro-events' ) . '</a>';
		$links[] = '<a href="' . esc_url( 'https://www.paidmembershipspro.com/support/' ) . '">' . esc_html__( 'Support', 'pmpro-events' ) . '</a>';
	}
	return $links;
}
add_filter( 'plugin_row_meta', 'pmpro_events_plugin_row_meta', 10, 2 );
