<?php

/**
 * Add metabox to All-In-One-Event Calendar CPT.
 */
function pmpro_events_ai1ec_page_meta_wrapper( ) {
	add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'ai1ec_event', 'side' );
}
add_action( 'admin_menu', 'pmpro_events_ai1ec_page_meta_wrapper' );