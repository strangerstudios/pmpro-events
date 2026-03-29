<?php
/**
 * Add to Calendar dropdown template.
 *
 * @package PMPro_Events
 * @since 2.0
 *
 * @var string $google_url  Google Calendar URL.
 * @var string $outlook_url Outlook 365 URL.
 * @var string $ics_url     ICS download URL.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="pmpro-events-add-to-calendar">
	<button type="button" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn pmpro_btn-secondary' ) ); ?> pmpro-events-atc-toggle">
		<?php esc_html_e( 'Add to Calendar', 'pmpro-events' ); ?>
	</button>
	<div class="pmpro-events-atc-dropdown" style="display: none;">
		<a href="<?php echo esc_url( $google_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Google Calendar', 'pmpro-events' ); ?>
		</a>
		<a href="<?php echo esc_url( $outlook_url ); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Outlook 365', 'pmpro-events' ); ?>
		</a>
		<a href="<?php echo esc_url( $ics_url ); ?>">
			<?php esc_html_e( 'Download .ics', 'pmpro-events' ); ?>
		</a>
	</div>
</div>
<script>
document.addEventListener( 'click', function( e ) {
	var toggle = e.target.closest( '.pmpro-events-atc-toggle' );
	if ( toggle ) {
		var dropdown = toggle.nextElementSibling;
		dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
		e.preventDefault();
		return;
	}
	// Close all open dropdowns when clicking outside.
	if ( ! e.target.closest( '.pmpro-events-add-to-calendar' ) ) {
		document.querySelectorAll( '.pmpro-events-atc-dropdown' ).forEach( function( d ) {
			d.style.display = 'none';
		} );
	}
} );
</script>
