<?php
/**
 * The cancellation notification email sent to the site administrator.
 *
 * @since 2.0
 */
class PMProEvents_Email_Template_Cancellation_Admin extends PMProEvents_Email_Template_Cancellation {
	/**
	 * Get the email template slug.
	 *
	 * @since 2.0
	 *
	 * @return string The email template slug.
	 */
	public static function get_template_slug() {
		return 'pmpro_events_cancellation_admin';
	}

	/**
	 * Get the "nice name" of the email template.
	 *
	 * @since 2.0
	 *
	 * @return string The "nice name" of the email template.
	 */
	public static function get_template_name() {
		/* translators: %s: the singular event label, e.g. "Event". */
		return esc_html( sprintf( __( '%s - Registration Cancelled (admin)', 'pmpro-events' ), pmpro_events_get_label( 'singular' ) ) );
	}

	/**
	 * Get "help text" to display to the admin when editing the email template.
	 *
	 * @since 2.0
	 *
	 * @return string The "help text" to display to the admin when editing the email template.
	 */
	public static function get_template_description() {
		/* translators: %s: the singular event label, lowercased, e.g. "event". */
		return esc_html( sprintf( __( "This email is sent to the site administrator when a member's %s registration is cancelled.", 'pmpro-events' ), pmpro_events_get_label( 'singular_lowercase' ) ) );
	}

	/**
	 * Get the default subject for the email.
	 *
	 * @since 2.0
	 *
	 * @return string The default subject for the email.
	 */
	public static function get_default_subject() {
		if ( ! class_exists( 'PMPro_Liquid_Renderer' ) ) {
			// Running a version of PMPro before liquid email rendering was available.
			return esc_html__( 'Registration cancelled for !!pmpro_events_event_title!!', 'pmpro-events' );
		}

		return esc_html__( 'Registration cancelled for {{ pmpro_events_event_title }}', 'pmpro-events' );
	}

	/**
	 * Get the default body content for the email.
	 *
	 * @since 2.0
	 *
	 * @return string The default body content for the email.
	 */
	public static function get_default_body() {
		if ( ! class_exists( 'PMPro_Liquid_Renderer' ) ) {
			// Running a version of PMPro before liquid email rendering was available.
			return wp_kses_post( __( '<p>Hi !!header_name!!,</p>

<p>The registration held by <strong>!!pmpro_events_member_display_name!!</strong> (!!pmpro_events_member_email!!) for <strong>!!pmpro_events_event_title!!</strong> has been cancelled. Their spot is now available.</p>

<p><a href="!!pmpro_events_event_registrations_url!!">View all registrations for this event</a>.</p>', 'pmpro-events' ) );
		}

		return wp_kses_post( __( '<p>Hi {{ header_name }},</p>

<p>The registration held by <strong>{{ pmpro_events_member_display_name }}</strong> ({{ pmpro_events_member_email }}) for <strong>{{ pmpro_events_event_title }}</strong> has been cancelled. Their spot is now available.</p>

<p><a href="{{ pmpro_events_event_registrations_url }}">View all registrations for this event</a>.</p>', 'pmpro-events' ) );
	}

	/**
	 * Get the email template variables paired with a description of each.
	 *
	 * @since 2.0
	 *
	 * @return array The email template variables and their descriptions.
	 */
	public static function get_email_template_variables_with_description() {
		return pmpro_events_get_admin_email_variable_descriptions();
	}

	/**
	 * Get the email template variables.
	 *
	 * @since 2.0
	 *
	 * @return array The email template variables.
	 */
	public function get_email_template_variables() {
		return pmpro_events_get_admin_email_variables( $this->registration, $this->event );
	}

	/**
	 * Get the email address to send the email to.
	 *
	 * @since 2.0
	 *
	 * @return string The email address to send the email to.
	 */
	public function get_recipient_email() {
		return get_bloginfo( 'admin_email' );
	}

	/**
	 * Get the name of the email recipient.
	 *
	 * @since 2.0
	 *
	 * @return string The name of the email recipient.
	 */
	public function get_recipient_name() {
		$user = get_user_by( 'email', $this->get_recipient_email() );

		return empty( $user->display_name ) ? esc_html__( 'Admin', 'pmpro-events' ) : $user->display_name;
	}
}
