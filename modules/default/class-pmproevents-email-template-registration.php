<?php
/**
 * The registration confirmation email.
 *
 * @since 2.0
 */
class PMProEvents_Email_Template_Registration extends PMPro_Email_Template {
	/**
	 * The registration being confirmed.
	 *
	 * @since 2.0
	 *
	 * @var PMProEvents_Event_Registration
	 */
	protected $registration;

	/**
	 * The event being registered for.
	 *
	 * @since 2.0
	 *
	 * @var PMProEvents_Event
	 */
	protected $event;

	/**
	 * Constructor.
	 *
	 * @since 2.0
	 *
	 * @param PMProEvents_Event_Registration $registration The registration being confirmed.
	 * @param PMProEvents_Event              $event        The event being registered for.
	 */
	public function __construct( $registration, $event = null ) {
		$this->registration = $registration;
		$this->event        = empty( $event ) ? $registration->get_event() : $event;
	}

	/**
	 * Get the email template slug.
	 *
	 * @since 2.0
	 *
	 * @return string The email template slug.
	 */
	public static function get_template_slug() {
		return 'pmpro_events_registration';
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
		return esc_html( sprintf( __( '%s - Registration Confirmation', 'pmpro-events' ), pmpro_events_get_label( 'singular' ) ) );
	}

	/**
	 * Get "help text" to display to the admin when editing the email template.
	 *
	 * @since 2.0
	 *
	 * @return string The "help text" to display to the admin when editing the email template.
	 */
	public static function get_template_description() {
		/* translators: %s: the plural event label, lowercased, e.g. "events". */
		return esc_html( sprintf( __( 'This email is sent to a member when they register for %s, including when an administrator registers them.', 'pmpro-events' ), pmpro_events_get_label( 'plural_lowercase' ) ) );
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
			return esc_html__( "You're registered for !!pmpro_events_event_title!!", 'pmpro-events' );
		}

		return esc_html__( "You're registered for {{ pmpro_events_event_title }}", 'pmpro-events' );
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

<p>You\'re registered for <strong>!!pmpro_events_event_title!!</strong>. Here are the details:</p>

<p><strong>When:</strong> !!pmpro_events_event_date!!<br />
<strong>Where:</strong> !!pmpro_events_event_location!!</p>

<p>!!pmpro_events_event_link!!</p>

<p>If you can no longer attend, you can cancel your registration from the !!pmpro_events_event_title!! page.</p>', 'pmpro-events' ) );
		}

		return wp_kses_post( __( '<p>Hi {{ header_name }},</p>

<p>You\'re registered for <strong>{{ pmpro_events_event_title }}</strong>. Here are the details:</p>

<p><strong>When:</strong> {{ pmpro_events_event_date }}<br />
<strong>Where:</strong> {{ pmpro_events_event_location }}</p>

<p>{{ pmpro_events_event_link }}</p>

<p>If you can no longer attend, you can cancel your registration from the {{ pmpro_events_event_title }} page.</p>', 'pmpro-events' ) );
	}

	/**
	 * Get the email template variables paired with a description of each.
	 *
	 * @since 2.0
	 *
	 * @return array The email template variables and their descriptions.
	 */
	public static function get_email_template_variables_with_description() {
		return pmpro_events_get_email_variable_descriptions( true );
	}

	/**
	 * Get the email template variables.
	 *
	 * @since 2.0
	 *
	 * @return array The email template variables.
	 */
	public function get_email_template_variables() {
		return pmpro_events_get_email_variables( $this->event );
	}

	/**
	 * Get the email address to send the email to.
	 *
	 * @since 2.0
	 *
	 * @return string The email address to send the email to.
	 */
	public function get_recipient_email() {
		$user = $this->registration->get_user();

		return empty( $user ) ? '' : $user->user_email;
	}

	/**
	 * Get the name of the email recipient.
	 *
	 * @since 2.0
	 *
	 * @return string The name of the email recipient.
	 */
	public function get_recipient_name() {
		$user = $this->registration->get_user();

		return empty( $user->display_name ) ? esc_html__( 'Member', 'pmpro-events' ) : $user->display_name;
	}

	/**
	 * Get the arguments used to build a test email.
	 *
	 * @since 2.0
	 *
	 * @return array The constructor arguments.
	 */
	public static function get_test_email_constructor_args() {
		// Empty objects. The variable helpers fall back to placeholders.
		return array( new PMProEvents_Event_Registration( 0 ), new PMProEvents_Event( 0 ) );
	}
}
