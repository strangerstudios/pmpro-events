<?php
// In case the file is loaded directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	$submitted = isset( $_POST['pmpro_events_modules'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['pmpro_events_modules'] ) ) : array();
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
	global $msg, $msgt;

	$modules = pmpro_events_get_modules();
	$labels  = get_option( 'pmpro_events_labels', array() );

	if ( ! empty( $_REQUEST['pmpro_events_settings_saved'] ) ) {
		$msg  = true; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Read by PMPro's admin_header.php.
		$msgt = __( 'Your settings have been updated.', 'pmpro-events' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Read by PMPro's admin_header.php.
	}

	require_once PMPRO_DIR . '/adminpages/admin_header.php';
	?>
	<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="pmpro_events_save_settings" />
		<?php wp_nonce_field( 'pmpro_events_settings', 'pmpro_events_settings_nonce' ); ?>
		<hr class="wp-header-end">
		<h1><?php esc_html_e( 'Events Settings', 'pmpro-events' ); ?></h1>
		<p>
			<?php esc_html_e( 'Create members-only events with built-in registration, or restrict events created with a supported events calendar plugin.', 'pmpro-events' ); ?>
			<?php
				$pmpro_events_docs_link = '<a title="' . esc_attr__( 'Paid Memberships Pro - Events Add On Documentation', 'pmpro-events' ) . '" target="_blank" rel="nofollow noopener" href="https://www.paidmembershipspro.com/add-ons/events-for-members-only/?utm_source=plugin&utm_medium=pmpro-events-settings&utm_campaign=add-ons">' . esc_html__( 'Events Add On', 'pmpro-events' ) . '</a>';
				// translators: %s: Link to the Events Add On documentation.
				printf( esc_html__( 'Learn more about the %s.', 'pmpro-events' ), $pmpro_events_docs_link ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</p>

		<div id="pmpro-events-modules" class="pmpro_section" data-visibility="shown" data-activated="true">
			<div class="pmpro_section_toggle">
				<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
					<span class="dashicons dashicons-arrow-up-alt2"></span>
					<?php esc_html_e( 'Modules', 'pmpro-events' ); ?>
				</button>
			</div>
			<div class="pmpro_section_inside">
				<p><?php esc_html_e( 'Use the built-in events module, or let this Add On restrict events created by a supported third-party events plugin. Third-party modules load automatically when their plugin is detected.', 'pmpro-events' ); ?></p>
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
										<span class="pmpro_tag pmpro_tag-success"><?php esc_html_e( 'Auto-Enabled', 'pmpro-events' ); ?></span>
									<?php } else { ?>
										<span class="pmpro_tag pmpro_tag-info"><?php esc_html_e( 'Not installed', 'pmpro-events' ); ?></span>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'pmpro-events' ); ?>" />
				</p>
			</div> <!-- end pmpro_section_inside -->
		</div> <!-- end pmpro_section -->

		<?php if ( pmpro_events_is_module_active( 'default' ) && function_exists( 'pmpro_getAllLevels' ) ) { ?>
			<div id="pmpro-events-registration" class="pmpro_section" data-visibility="shown" data-activated="true">
				<div class="pmpro_section_toggle">
					<button class="pmpro_section-toggle-button" type="button" aria-expanded="true">
						<span class="dashicons dashicons-arrow-up-alt2"></span>
						<?php esc_html_e( 'Registration', 'pmpro-events' ); ?>
					</button>
				</div>
				<div class="pmpro_section_inside">
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="pmpro_events_signup_level"><?php esc_html_e( 'Registration Level', 'pmpro-events' ); ?></label></th>
								<td>
									<select id="pmpro_events_signup_level" name="pmpro_events_signup_level">
										<option value="0"><?php esc_html_e( 'None - Only show a log in link.', 'pmpro-events' ); ?></option>
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
									<p class="description"><?php esc_html_e( 'Logged-out visitors are asked to log in before registering. Pick a level below (ideally a free one) to also show them a "create an account" link that goes straight to checkout for that level.', 'pmpro-events' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
					<p class="submit">
						<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'pmpro-events' ); ?>" />
					</p>
				</div> <!-- end pmpro_section_inside -->
			</div> <!-- end pmpro_section -->
		<?php } ?>

		<div id="pmpro-events-terminology" class="pmpro_section" data-visibility="hidden" data-activated="true">
			<div class="pmpro_section_toggle">
				<button class="pmpro_section-toggle-button" type="button" aria-expanded="false">
					<span class="dashicons dashicons-arrow-down-alt2"></span>
					<?php esc_html_e( 'Terminology', 'pmpro-events' ); ?>
				</button>
			</div>
			<div class="pmpro_section_inside" style="display: none;">
				<p><?php esc_html_e( 'Rename "Event" throughout the admin menu, the event template, and the member account page.', 'pmpro-events' ); ?></p>
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
				<p class="submit">
					<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'pmpro-events' ); ?>" />
				</p>
			</div> <!-- end pmpro_section_inside -->
		</div> <!-- end pmpro_section -->
	</form>
	<?php
	require_once PMPRO_DIR . '/adminpages/admin_footer.php';
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
