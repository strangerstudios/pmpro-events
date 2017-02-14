<?php
/*
Plugin Name: Paid Memberships Pro - Events Add On
Plugin URI: http://www.paidmembershipspro.com/pmpro-events/
Description: Offer Members-only Events using PMPro and popular events plugins.
Version: .1
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/

function pmpro_events_plugin_init() {
	//Load module based on active events plugin
	$path = dirname(__FILE__);

	//Events Manager (https://wordpress.org/plugins/events-manager/) 
	if(defined('EM_VERSION'))
		require_once($path . "/modules/events-manager.php");
	
	//The Events Calendar by Modern Tribe (https://wordpress.org/plugins/the-events-calendar/)
	if(class_exists('Tribe__Events__Main'))
		require_once($path . "/modules/the-events-calendar.php"); 
}
add_action( 'plugins_loaded', 'pmpro_events_plugin_init' );
	
/*
Function to add links to the plugin row meta
*/
function pmpro_events_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-events.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plus-add-ons/members-events/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',			
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmpro_events_plugin_row_meta', 10, 2);