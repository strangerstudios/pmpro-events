/**
 * The My Events block.
 *
 * A dynamic block rendered by pmpro_events_render_my_events(), so the editor
 * preview shows the real output for the logged-in user. Written against the
 * wp globals rather than built — it is small enough that a build step would
 * be more code than the block.
 */
( function ( blocks, blockEditor, components, element, serverSideRender, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'pmpro-events/my-events', {
		title: __( 'My Events', 'pmpro-events' ),
		description: __(
			'The upcoming events that the current member is registered for.',
			'pmpro-events'
		),
		icon: 'calendar-alt',
		category: 'pmpro',
		keywords: [
			__( 'events', 'pmpro-events' ),
			__( 'registrations', 'pmpro-events' ),
		],
		supports: {
			html: false,
			multiple: false,
		},
		attributes: {
			showTitle: {
				type: 'boolean',
				default: true,
			},
			title: {
				type: 'string',
				default: '',
			},
		},
		edit: function ( props ) {
			var controls = el(
				blockEditor.InspectorControls,
				null,
				el(
					components.PanelBody,
					{ title: __( 'Settings', 'pmpro-events' ) },
					el( components.ToggleControl, {
						label: __( 'Show title', 'pmpro-events' ),
						checked: props.attributes.showTitle,
						onChange: function ( showTitle ) {
							props.setAttributes( { showTitle: showTitle } );
						},
					} ),
					props.attributes.showTitle &&
						el( components.TextControl, {
							label: __( 'Title', 'pmpro-events' ),
							value: props.attributes.title,
							placeholder: __( 'My Events', 'pmpro-events' ),
							help: __( 'Leave blank for the default title.', 'pmpro-events' ),
							onChange: function ( title ) {
								props.setAttributes( { title: title } );
							},
						} )
				)
			);

			// useBlockProps() is what makes the block selectable in the editor,
			// and Disabled keeps the preview's event links from eating clicks.
			return el(
				'div',
				blockEditor.useBlockProps(),
				controls,
				el(
					components.Disabled,
					null,
					el( serverSideRender, {
						block: 'pmpro-events/my-events',
						attributes: props.attributes,
					} )
				)
			);
		},
	} );
} )(
	window.wp.blocks,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.element,
	window.wp.serverSideRender,
	window.wp.i18n
);
