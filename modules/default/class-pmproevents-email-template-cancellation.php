<?php
/**
 * The registration cancellation email.
 *
 * @since 2.0
 */
class PMProEvents_Email_Template_Cancellation extends PMPro_Email_Template {
	/**
	 * The registration that was cancelled.
	 *
	 * @since 2.0
	 *
	 * @var PMProEvents_Event_Registration
	 */
	protected $registration;

	/**
	 * The event the registration was for.
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
	 * @param PMProEvents_Event_Registration $registration The registration that was cancelled.
	 * @param PMProEvents_Event              $event        The event the registration was for.
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
		return 'pmpro_events_cancellation';
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
		return esc_html( sprintf( __( '%s - Registration Cancelled', 'pmpro-events' ), pmpro_events_get_label( 'singular' ) ) );
	}

	/**
	 * Get "help text" to display to the admin when editing the email template.
	 *
	 * @since 2.0
	 *
	 * @return string The "help text" to display to the admin when editing the email template.
	 */
	public static function get_template_description() {
		/* translators: 1: the singular event label, lowercased, 2: the same label repeated. */
		return esc_html( sprintf( __( 'This email is sent to a member when their %1$s registration is cancelled, whether they cancelled it themselves, an administrator cancelled it, or they lost access to the %2$s.', 'pmpro-events' ), pmpro_events_get_label( 'singular_lowercase' ), pmpro_events_get_label( 'singular_lowercase' ) ) );
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
			return esc_html__( 'Your registration for !!pmpro_events_event_title!! has been cancelled', 'pmpro-events' );
		}

		return esc_html__( 'Your registration for {{ pmpro_events_event_title }} has been cancelled', 'pmpro-events' );
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

<p>Your registration for <strong>!!pmpro_events_event_title!!</strong> on !!pmpro_events_event_date!! has been cancelled, and your spot has been released.</p>

<p>If this was a mistake, you can register again from the !!pmpro_events_event_title!! page.</p>

<p>!!pmpro_events_event_link!!</p>', 'pmpro-events' ) );
		}

		return wp_kses_post( __( '<p>Hi {{ header_name }},</p>

<p>Your registration for <strong>{{ pmpro_events_event_title }}</strong> on {{ pmpro_events_event_date }} has been cancelled, and your spot has been released.</p>

<p>If this was a mistake, you can register again from the {{ pmpro_events_event_title }} page.</p>

<p>{{ pmpro_events_event_link }}</p>', 'pmpro-events' ) );
	}

	/**
	 * Get the email template variables paired with a description of each.
	 *
	 * The meeting URL is deliberately not offered here — the member no longer
	 * holds a spot.
	 *
	 * @since 2.0
	 *
	 * @return array The email template variables and their descriptions.
	 */
	public static function get_email_template_variables_with_description() {
		return pmpro_events_get_email_variable_descriptions( false );
	}

	/**
	 * Get the email template variables.
	 *
	 * @since 2.0
	 *
	 * @return array The email template variables.
	 */
	public function get_email_template_variables() {
		$variables = pmpro_events_get_email_variables( $this->event );

		// Someone who no longer has a spot shouldn't be sent the meeting URL.
		$variables['pmpro_events_event_meeting_url'] = '';

		return $variables;
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
