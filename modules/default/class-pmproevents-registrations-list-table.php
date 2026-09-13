<?php
/**
 * The registrations list table.
 *
 * @since 2.0
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Lists the registrants for a single event.
 *
 * @since 2.0
 */
class PMProEvents_Registrations_List_Table extends WP_List_Table {
	/**
	 * The event being viewed.
	 *
	 * @since 2.0
	 *
	 * @var PMProEvents_Event
	 */
	protected $event;

	/**
	 * Set up the list table.
	 *
	 * @since 2.0
	 *
	 * @param PMProEvents_Event $event The event being viewed.
	 */
	public function __construct( $event ) {
		$this->event = $event;

		parent::__construct( array(
			'singular' => 'pmpro_event_registration',
			'plural'   => 'pmpro_event_registrations',
			'ajax'     => false,
		) );
	}

	/**
	 * Define the table columns.
	 *
	 * @since 2.0
	 *
	 * @return array The columns.
	 */
	public function get_columns() {
		return array(
			'user'          => __( 'Member', 'pmpro-events' ),
			'email'         => __( 'Email', 'pmpro-events' ),
			'registered_at' => __( 'Registered', 'pmpro-events' ),
			'status'        => __( 'Status', 'pmpro-events' ),
		);
	}

	/**
	 * Define the sortable columns.
	 *
	 * @since 2.0
	 *
	 * @return array The sortable columns.
	 */
	public function get_sortable_columns() {
		return array(
			'registered_at' => array( 'registered_at', true ),
			'status'        => array( 'status', false ),
		);
	}

	/**
	 * Prepare the registrations for display.
	 *
	 * @since 2.0
	 */
	public function prepare_items() {
		$per_page     = 20;
		$current_page = $this->get_pagenum();

		$status = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : 'active';
		$status = in_array( $status, array( 'active', 'cancelled' ), true ) ? $status : 'active';

		$query_args = array(
			'event_id' => $this->event->get_id(),
			'status'   => $status,
		);

		$total_items = PMProEvents_Event_Registration::get_registrations( array_merge( $query_args, array( 'return_count' => true ) ) );

		$this->items = PMProEvents_Event_Registration::get_registrations( array_merge( $query_args, array(
			'limit'   => $per_page,
			'offset'  => ( $current_page - 1 ) * $per_page,
			'orderby' => isset( $_REQUEST['orderby'] ) ? sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ) : 'registered_at',
			'order'   => isset( $_REQUEST['order'] ) ? sanitize_key( wp_unslash( $_REQUEST['order'] ) ) : 'DESC',
		) ) );

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $total_items / $per_page ),
		) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Render the views that switch between active and cancelled registrations.
	 *
	 * @since 2.0
	 *
	 * @return array The views.
	 */
	protected function get_views() {
		$current = isset( $_REQUEST['status'] ) ? sanitize_key( wp_unslash( $_REQUEST['status'] ) ) : 'active';
		$base    = pmpro_events_get_registrations_url( $this->event->get_id() );

		$views = array();
		foreach ( array( 'active' => __( 'Active', 'pmpro-events' ), 'cancelled' => __( 'Cancelled', 'pmpro-events' ) ) as $status => $label ) {
			$count = PMProEvents_Event_Registration::get_registrations( array(
				'event_id'     => $this->event->get_id(),
				'status'       => $status,
				'return_count' => true,
			) );

			$views[ $status ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%s)</span></a>',
				esc_url( add_query_arg( 'status', $status, $base ) ),
				$current === $status ? ' class="current"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $count ) )
			);
		}

		return $views;
	}

	/**
	 * Render the Member column.
	 *
	 * @since 2.0
	 *
	 * @param PMProEvents_Event_Registration $item The registration.
	 * @return string The column contents.
	 */
	public function column_user( $item ) {
		$user = $item->get_user();

		$actions = array();

		if ( 'cancelled' === $item->status ) {
			$actions['reactivate'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( pmpro_events_get_registration_action_url( 'reactivate', $item->id, $this->event->get_id() ) ),
				esc_html__( 'Reactivate', 'pmpro-events' )
			);
		} else {
			$actions['cancel'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( pmpro_events_get_registration_action_url( 'cancel', $item->id, $this->event->get_id() ) ),
				esc_html__( 'Cancel', 'pmpro-events' )
			);
		}

		$actions['delete'] = sprintf(
			'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( pmpro_events_get_registration_action_url( 'delete', $item->id, $this->event->get_id() ) ),
			esc_js( __( 'Permanently delete this registration? Cancelling keeps a record instead.', 'pmpro-events' ) ),
			esc_html__( 'Delete', 'pmpro-events' )
		);

		if ( empty( $user ) ) {
			return esc_html__( 'Deleted user', 'pmpro-events' ) . $this->row_actions( $actions );
		}

		return sprintf(
			'<a href="%s">%s</a>%s',
			esc_url( pmpro_events_get_member_edit_url( $user->ID, 'pmpro-events' ) ),
			esc_html( $user->display_name ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render the Email column.
	 *
	 * @since 2.0
	 *
	 * @param PMProEvents_Event_Registration $item The registration.
	 * @return string The column contents.
	 */
	public function column_email( $item ) {
		$user = $item->get_user();

		return empty( $user ) ? '&mdash;' : esc_html( $user->user_email );
	}

	/**
	 * Render the Registered column.
	 *
	 * @since 2.0
	 *
	 * @param PMProEvents_Event_Registration $item The registration.
	 * @return string The column contents.
	 */
	public function column_registered_at( $item ) {
		if ( empty( $item->registered_at ) || '0000-00-00 00:00:00' === $item->registered_at ) {
			return '&mdash;';
		}

		return esc_html( wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			strtotime( $item->registered_at . ' UTC' )
		) );
	}

	/**
	 * Render the Status column.
	 *
	 * @since 2.0
	 *
	 * @param PMProEvents_Event_Registration $item The registration.
	 * @return string The column contents.
	 */
	public function column_status( $item ) {
		return 'cancelled' === $item->status
			? esc_html__( 'Cancelled', 'pmpro-events' )
			: esc_html__( 'Active', 'pmpro-events' );
	}

	/**
	 * Message shown when the event has no registrations.
	 *
	 * @since 2.0
	 */
	public function no_items() {
		esc_html_e( 'No registrations found.', 'pmpro-events' );
	}
}
