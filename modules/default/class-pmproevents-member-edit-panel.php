<?php
/**
 * The Events panel shown on PMPro's Edit Member screen.
 *
 * @since 2.0
 */
class PMProEvents_Member_Edit_Panel extends PMPro_Member_Edit_Panel {
	/**
	 * Set up the panel.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		$this->slug  = 'pmpro-events';
		$this->title = pmpro_events_get_label( 'plural' );
	}

	/**
	 * Display the panel contents.
	 *
	 * @since 2.0
	 */
	protected function display_panel_contents() {
		$user = self::get_user();

		if ( empty( $user ) ) {
			return;
		}

		echo pmpro_events_get_member_registrations_html( $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
