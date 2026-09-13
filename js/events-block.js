/**
 * The Upcoming Events block.
 *
 * A dynamic block rendered by pmpro_events_render_events(). The sidebar
 * switches between the list and calendar views, caps how many events the
 * list shows, and scopes either view to an event category.
 */
( function ( blocks, blockEditor, components, data, element, serverSideRender, i18n ) {
	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType( 'pmpro-events/events', {
		title: __( 'Upcoming Events', 'pmpro-events' ),
		description: __(
			'A list or monthly calendar of upcoming events.',
			'pmpro-events'
		),
		icon: 'calendar-alt',
		category: 'pmpro',
		keywords: [
			__( 'events', 'pmpro-events' ),
			__( 'calendar', 'pmpro-events' ),
			__( 'schedule', 'pmpro-events' ),
		],
		supports: {
			html: false,
		},
		attributes: {
			view: {
				type: 'string',
				default: 'list',
			},
			limit: {
				type: 'number',
				default: 10,
			},
			category: {
				type: 'string',
				default: '',
			},
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
			var categories = data.useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'taxonomy', 'pmpro_event_category', {
					per_page: -1,
					hide_empty: false,
				} );
			}, [] );

			var categoryOptions = [
				{ label: __( 'All categories', 'pmpro-events' ), value: '' },
			].concat(
				( categories || [] ).map( function ( term ) {
					return { label: term.name, value: term.slug };
				} )
			);

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
							placeholder: __( 'Upcoming Events', 'pmpro-events' ),
							help: __( 'Leave blank for the default title.', 'pmpro-events' ),
							onChange: function ( title ) {
								props.setAttributes( { title: title } );
							},
						} ),
					el( components.SelectControl, {
						label: __( 'View', 'pmpro-events' ),
						value: props.attributes.view,
						options: [
							{ label: __( 'List', 'pmpro-events' ), value: 'list' },
							{ label: __( 'Calendar', 'pmpro-events' ), value: 'calendar' },
						],
						onChange: function ( view ) {
							props.setAttributes( { view: view } );
						},
					} ),
					'list' === props.attributes.view &&
						el( components.RangeControl, {
							label: __( 'Number of events', 'pmpro-events' ),
							min: 1,
							max: 50,
							value: props.attributes.limit,
							onChange: function ( limit ) {
								props.setAttributes( { limit: limit } );
							},
						} ),
					categoryOptions.length > 1 &&
						el( components.SelectControl, {
							label: __( 'Category', 'pmpro-events' ),
							value: props.attributes.category,
							options: categoryOptions,
							onChange: function ( category ) {
								props.setAttributes( { category: category } );
							},
						} )
				)
			);

			return el(
				'div',
				blockEditor.useBlockProps(),
				controls,
				el(
					components.Disabled,
					null,
					el( serverSideRender, {
						block: 'pmpro-events/events',
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
	window.wp.data,
	window.wp.element,
	window.wp.serverSideRender,
	window.wp.i18n
);
