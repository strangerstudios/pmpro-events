<?php

/**
 * Add metabox to Sugar Calendar  CPT.
 */
function pmpro_events_sugar_calendar_page_meta_wrapper( ) {
	add_meta_box( 'pmpro_page_meta', 'Require Membership', 'pmpro_page_meta', 'sc_event', 'side' );
}
add_action( 'admin_menu', 'pmpro_events_sugar_calendar_page_meta_wrapper' );

/* This is the action to hide the event details from before the event content:
//remove_action( 'sc_before_event_content', 'sc_add_event_details' );
