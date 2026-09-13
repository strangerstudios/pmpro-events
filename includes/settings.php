<?php
/**
 * The Events settings page, shown as a submenu under Memberships.
 *
 * @since 2.0
 */

/**
 * Add the settings page to the Memberships menu.
 *
 * @since 2.0
 */
function pmpro_events_admin_menu() {
	add_submenu_page(
		'pmpro-dashboard',
		__( 'Events Settings', 'pmpro-events' ),
		__( 'Events', 'pmpro-events' ),
		'manage_options',
		'pmpro-events-settings',
		'pmpro_events_settings_page'
	);
}
add_action( 'admin_menu', 'pmpro_events_admin_menu', 20 );

/**
 * Save the settings page.
 *
 * @since 2.0
 */
function pmpro_events_save_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage these settings.', 'pmpro-events' ) );
	}

	check_admin_referer( 'pmpro_events_settings', 'pmpro_events_settings_nonce' );

	// Only toggleable modules can be changed from this page.
	$active = array();
	$submitted = isset( $_POST['pmpro_events_modules'] ) ? (array) $_POST['pmpro_events_modules'] : array();
	foreach ( pmpro_events_get_modules() as $module => $data ) {
		if ( ! empty( $data['toggleable'] ) && in_array( $module, $submitted, true ) ) {
			$active[] = $module;
		}
	}
	update_option( 'pmpro_events_modules', $active, 'no' );

	// Terminology. An empty value falls back to the default label.
	$labels = array(
		'singular' => isset( $_POST['pmpro_events_label_singular'] ) ? sanitize_text_field( wp_unslash( $_POST['pmpro_events_label_singular'] ) ) : '',
		'plural'   => isset( $_POST['pmpro_events_label_plural'] ) ? sanitize_text_field( wp_unslash( $_POST['pmpro_events_label_plural'] ) ) : '',
	);
	update_option( 'pmpro_events_labels', $labels, 'no' );

	// The level behind the "create an account" link shown to logged-out
	// visitors. Only present when the Default module's section was rendered, so
	// don't zero it out when the field is missing.
	if ( isset( $_POST['pmpro_events_signup_level'] ) ) {
		update_option( 'pmpro_events_signup_level', (int) $_POST['pmpro_events_signup_level'], 'no' );
	}

	// The rewrite rules depend on the settings above, but this request loaded
	// modules based on the old configuration — flushing now would build rules
	// without the event post type. Flush on the next request instead, once the
	// right modules are active.
	update_option( 'pmpro_events_flush_rewrite_rules', 1, 'no' );

	wp_safe_redirect( add_query_arg( 'pmpro_events_settings_saved', 1, admin_url( 'admin.php?page=pmpro-events-settings' ) ) );
	exit;
}
add_action( 'admin_post_pmpro_events_save_settings', 'pmpro_events_save_settings' );

/**
 * Render the settings page.
 *
 * @since 2.0
 */
function pmpro_events_settings_page() {
	$modules = pmpro_events_get_modules();
	$labels  = get_option( 'pmpro_events_labels', array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Events Settings', 'pmpro-events' ); ?></h1>

		<?php if ( ! empty( $_REQUEST['pmpro_events_settings_saved'] ) ) { ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'pmpro-events' ); ?></p></div>
		<?php } ?>

		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<input type="hidden" name="action" value="pmpro_events_save_settings" />
			<?php wp_nonce_field( 'pmpro_events_settings', 'pmpro_events_settings_nonce' ); ?>

			<h2><?php esc_html_e( 'Modules', 'pmpro-events' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Use the built-in events module, or let this add-on restrict events created by a supported third-party events plugin. Third-party modules load automatically when their plugin is detected.', 'pmpro-events' ); ?></p>

			<table class="widefat striped pmpro_events_modules_table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Module', 'pmpro-events' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'pmpro-events' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $modules as $module => $data ) { ?>
						<tr>
							<td><strong><?php echo esc_html( $data['name'] ); ?></strong></td>
							<td>
								<?php if ( ! empty( $data['toggleable'] ) ) { ?>
									<label>
										<input type="checkbox" name="pmpro_events_modules[]" value="<?php echo esc_attr( $module ); ?>" <?php checked( pmpro_events_is_module_active( $module ) ); ?> />
										<?php esc_html_e( 'Enabled', 'pmpro-events' ); ?>
									</label>
								<?php } elseif ( pmpro_events_is_module_detected( $module ) ) { ?>
									<span class="pmpro_events-badge pmpro_events-badge-detected"><?php esc_html_e( 'Detected', 'pmpro-events' ); ?></span>
								<?php } else { ?>
									<span class="pmpro_events-badge"><?php esc_html_e( 'Not installed', 'pmpro-events' ); ?></span>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>

			<?php if ( pmpro_events_is_module_active( 'default' ) && function_exists( 'pmpro_getAllLevels' ) ) { ?>
				<h2><?php esc_html_e( 'Registration', 'pmpro-events' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Logged-out visitors are asked to log in before registering. Choose a level here — ideally a free one — to also offer them a "create an account" link that goes straight to checkout for that level.', 'pmpro-events' ); ?></p>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="pmpro_events_signup_level"><?php esc_html_e( 'Sign-Up Level', 'pmpro-events' ); ?></label></th>
							<td>
								<select id="pmpro_events_signup_level" name="pmpro_events_signup_level">
									<option value="0"><?php esc_html_e( 'None — only show a log in link', 'pmpro-events' ); ?></option>
									<?php
									$signup_level = (int) get_option( 'pmpro_events_signup_level' );
									$all_levels   = pmpro_getAllLevels( true, true );
									if ( function_exists( 'pmpro_sort_levels_by_order' ) ) {
										$all_levels = pmpro_sort_levels_by_order( $all_levels );
									}
									foreach ( $all_levels as $level ) {
										?>
										<option value="<?php echo esc_attr( $level->id ); ?>" <?php selected( $signup_level, (int) $level->id ); ?>><?php echo esc_html( $level->name ); ?></option>
										<?php
									}
									?>
								</select>
							</td>
						</tr>
					</tbody>
				</table>
			<?php } ?>

			<h2><?php esc_html_e( 'Terminology', 'pmpro-events' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Rename "Event" throughout the admin menu, the event template, and the member account page.', 'pmpro-events' ); ?></p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="pmpro_events_label_singular"><?php esc_html_e( 'Singular Name', 'pmpro-events' ); ?></label></th>
						<td>
							<input type="text" id="pmpro_events_label_singular" name="pmpro_events_label_singular" class="regular-text" value="<?php echo esc_attr( isset( $labels['singular'] ) ? $labels['singular'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Event', 'pmpro-events' ); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pmpro_events_label_plural"><?php esc_html_e( 'Plural Name', 'pmpro-events' ); ?></label></th>
						<td>
							<input type="text" id="pmpro_events_label_plural" name="pmpro_events_label_plural" class="regular-text" value="<?php echo esc_attr( isset( $labels['plural'] ) ? $labels['plural'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Events', 'pmpro-events' ); ?>" />
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Enqueue the admin stylesheet on the settings page.
 *
 * @since 2.0
 *
 * @param string $hook_suffix The current admin page.
 */
function pmpro_events_settings_enqueue_styles( $hook_suffix ) {
	if ( 'memberships_page_pmpro-events-settings' !== $hook_suffix ) {
		return;
	}

	pmpro_events_enqueue_admin_style();
}
add_action( 'admin_enqueue_scripts', 'pmpro_events_settings_enqueue_styles' );
