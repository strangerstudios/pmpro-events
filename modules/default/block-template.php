<?php
/**
 * A block template for single events.
 *
 * Without this, block themes fall back to their generic single-post template,
 * which renders post meta that makes no sense for an event — Twenty Twenty-Five
 * outputs "Written by {author} in {category}", and an event has neither.
 *
 * Plugin-registered block templates are a WordPress 6.7 feature, and themes can
 * still override this with their own single-pmpro_event.html. Classic themes are
 * unaffected and keep using their own single template.
 *
 * @since 2.0
 */

/**
 * Register the single event block template.
 *
 * @since 2.0
 */
function pmpro_events_register_block_template() {
	// Added in WordPress 6.7. Older installs keep the theme's own template.
	if ( ! function_exists( 'register_block_template' ) ) {
		return;
	}

	// Classic themes with a theme.json get 'block-templates' support from core,
	// which would make this template win over their single.php. Its header and
	// footer template parts don't exist there, so only register for block themes.
	if ( ! wp_is_block_theme() ) {
		return;
	}

	/**
	 * Filter whether to register the built-in single event block template.
	 *
	 * Return false to let the theme's template handle events instead.
	 *
	 * @since 2.0
	 *
	 * @param bool $register Whether to register the template. Default true.
	 */
	if ( ! apply_filters( 'pmpro_events_register_block_template', true ) ) {
		return;
	}

	$content = '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->
<main class="wp-block-group">
<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:post-title {"level":1} /-->

<!-- wp:post-featured-image /-->

<!-- wp:post-content {"layout":{"type":"constrained"}} /-->

<!-- wp:spacer {"height":"40px"} -->
<div style="height:40px" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';

	// The slug after the // has to match a template hierarchy slug, which is how
	// resolve_block_template() finds it.
	register_block_template(
		'pmpro-events//single-' . PMProEvents_Event::POST_TYPE,
		array(
			/* translators: %s: the singular event label, e.g. "Event". */
			'title'       => sprintf( __( 'Single %s', 'pmpro-events' ), pmpro_events_get_label( 'singular' ) ),
			/* translators: %s: the singular event label, lowercased. */
			'description' => sprintf( __( 'Displays a single %s, with its schedule, location, and registration.', 'pmpro-events' ), pmpro_events_get_label( 'singular_lowercase' ) ),
			'content'     => $content,
			'post_types'  => array( PMProEvents_Event::POST_TYPE ),
			'plugin'      => 'pmpro-events',
		)
	);
}
add_action( 'init', 'pmpro_events_register_block_template', 30 );
