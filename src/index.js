/**
 * Event edit screen sidebar panels.
 *
 * The event edit screen is the block editor document sidebar rather than PHP
 * metaboxes. All three panels are registered through a single plugin and bind
 * directly to the post meta registered in modules/default/cpt.php.
 */
import { registerPlugin } from '@wordpress/plugins';
import {
	PluginDocumentSettingPanel as EditorDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import {
	Notice,
	PanelRow,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

// PluginDocumentSettingPanel moved from @wordpress/edit-post to @wordpress/editor
// in WordPress 6.6. Read the pre-6.6 location off the global instead of
// importing it: an import would make wp-edit-post a dependency of this script
// on every install, and the block editor has already loaded it wherever the
// fallback is needed.
const PluginDocumentSettingPanel =
	EditorDocumentSettingPanel ||
	window.wp?.editPost?.PluginDocumentSettingPanel;

const settings = window.pmproEventsEditor || {};
const labels = settings.labels || { singular: 'Event', plural: 'Events' };
const timezones = settings.timezones || [];

/**
 * Convert a stored meta datetime (Y-m-d H:i:s) into an input value.
 *
 * @param {string}  value  The stored value.
 * @param {boolean} allDay Whether the event is an all-day event.
 * @return {string} The value for the input.
 */
const metaToInput = ( value, allDay ) => {
	if ( ! value ) {
		return '';
	}

	return allDay ? value.slice( 0, 10 ) : value.slice( 0, 16 ).replace( ' ', 'T' );
};

/**
 * Convert an input value back into a stored meta datetime (Y-m-d H:i:s).
 *
 * @param {string}  value  The input value.
 * @param {boolean} allDay Whether the event is an all-day event.
 * @return {string} The value to store in meta.
 */
const inputToMeta = ( value, allDay ) => {
	if ( ! value ) {
		return '';
	}

	if ( allDay ) {
		return `${ value.slice( 0, 10 ) } 00:00:00`;
	}

	const normalized = value.replace( 'T', ' ' );

	return normalized.length === 16 ? `${ normalized }:00` : normalized;
};

/**
 * Hook to read and write the event's meta.
 *
 * @return {Array} The meta object and its setter.
 */
const useEventMeta = () => {
	const postType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);
	const [ meta, setMeta ] = useEntityProp( 'postType', postType, 'meta' );

	const updateMeta = ( updates ) => setMeta( { ...meta, ...updates } );

	return [ meta || {}, updateMeta ];
};

/**
 * The Event Details panel: when the event happens.
 *
 * @return {Element} The panel.
 */
const EventDetailsPanel = () => {
	const [ meta, updateMeta ] = useEventMeta();

	const allDay = !! meta.pmpro_event_all_day;

	return (
		<PluginDocumentSettingPanel
			name="pmpro-events-details"
			title={ sprintf(
				/* translators: %s: the singular event label, e.g. "Event". */
				__( '%s Details', 'pmpro-events' ),
				labels.singular
			) }
			className="pmpro-events-panel"
		>
			{ ! settings.siteHasNamedTimezone && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Your site timezone is set to a fixed UTC offset, which cannot adjust for daylight saving time. Choose a named city or region under Settings → General for accurate event times.',
						'pmpro-events'
					) }
				</Notice>
			) }

			<PanelRow>
				<ToggleControl
					label={ __( 'All-day event', 'pmpro-events' ) }
					checked={ allDay }
					onChange={ ( value ) =>
						updateMeta( {
							pmpro_event_all_day: value,
							// Re-normalize the stored dates for the new mode.
							pmpro_event_start: inputToMeta(
								metaToInput( meta.pmpro_event_start, value ),
								value
							),
							pmpro_event_end: inputToMeta(
								metaToInput( meta.pmpro_event_end, value ),
								value
							),
						} )
					}
				/>
			</PanelRow>

			<TextControl
				label={ allDay ? __( 'Start Date', 'pmpro-events' ) : __( 'Start Date and Time', 'pmpro-events' ) }
				type={ allDay ? 'date' : 'datetime-local' }
				value={ metaToInput( meta.pmpro_event_start, allDay ) }
				onChange={ ( value ) =>
					updateMeta( { pmpro_event_start: inputToMeta( value, allDay ) } )
				}
			/>

			<TextControl
				label={ allDay ? __( 'End Date', 'pmpro-events' ) : __( 'End Date and Time', 'pmpro-events' ) }
				type={ allDay ? 'date' : 'datetime-local' }
				value={ metaToInput( meta.pmpro_event_end, allDay ) }
				onChange={ ( value ) =>
					updateMeta( { pmpro_event_end: inputToMeta( value, allDay ) } )
				}
				help={ __( 'Optional. Leave blank for an event with no set end.', 'pmpro-events' ) }
			/>

			<SelectControl
				label={ __( 'Timezone', 'pmpro-events' ) }
				value={ meta.pmpro_event_timezone || '' }
				options={ timezones }
				onChange={ ( value ) => updateMeta( { pmpro_event_timezone: value } ) }
			/>
		</PluginDocumentSettingPanel>
	);
};

/**
 * The Location panel: where the event happens, in person or online.
 *
 * @return {Element} The panel.
 */
const LocationPanel = () => {
	const [ meta, updateMeta ] = useEventMeta();

	const hasLocation = !! meta.pmpro_event_has_location;
	const locationType = meta.pmpro_event_location_type || 'in_person';
	const hasRegistration = !! meta.pmpro_event_has_registration;

	return (
		<PluginDocumentSettingPanel
			name="pmpro-events-location"
			title={ __( 'Location', 'pmpro-events' ) }
			className="pmpro-events-panel"
		>
			<PanelRow>
				<ToggleControl
					label={ __( 'This event has a location', 'pmpro-events' ) }
					checked={ hasLocation }
					onChange={ ( value ) =>
						updateMeta( {
							pmpro_event_has_location: value,
							pmpro_event_location_type: value ? locationType : '',
						} )
					}
				/>
			</PanelRow>

			{ hasLocation && (
				<>
					<SelectControl
						label={ __( 'Location Type', 'pmpro-events' ) }
						value={ locationType }
						options={ [
							{ label: __( 'In Person', 'pmpro-events' ), value: 'in_person' },
							{ label: __( 'Virtual', 'pmpro-events' ), value: 'virtual' },
						] }
						onChange={ ( value ) =>
							updateMeta( { pmpro_event_location_type: value } )
						}
					/>

					{ 'virtual' === locationType ? (
						<TextControl
							label={ __( 'Meeting or Stream URL', 'pmpro-events' ) }
							type="url"
							value={ meta.pmpro_event_virtual_url || '' }
							onChange={ ( value ) =>
								updateMeta( { pmpro_event_virtual_url: value } )
							}
							help={
								hasRegistration
									? __( 'Only shown to people who are registered for this event.', 'pmpro-events' )
									: __( 'Registration is disabled, so this is shown to everyone who can view this event.', 'pmpro-events' )
							}
						/>
					) : (
						<>
							<TextControl
								label={ __( 'Venue Name', 'pmpro-events' ) }
								value={ meta.pmpro_event_venue_name || '' }
								onChange={ ( value ) =>
									updateMeta( { pmpro_event_venue_name: value } )
								}
							/>
							<TextareaControl
								label={ __( 'Venue Address', 'pmpro-events' ) }
								value={ meta.pmpro_event_venue_address || '' }
								onChange={ ( value ) =>
									updateMeta( { pmpro_event_venue_address: value } )
								}
								rows={ 3 }
							/>
						</>
					) }
				</>
			) }
		</PluginDocumentSettingPanel>
	);
};

/**
 * The Registration panel: whether members can claim a spot, and how many.
 *
 * @return {Element} The panel.
 */
const RegistrationPanel = () => {
	const [ meta, updateMeta ] = useEventMeta();

	const hasRegistration = !! meta.pmpro_event_has_registration;

	return (
		<PluginDocumentSettingPanel
			name="pmpro-events-registration"
			title={ __( 'Registration', 'pmpro-events' ) }
			className="pmpro-events-panel"
		>
			<PanelRow>
				<ToggleControl
					label={ __( 'Enable registration', 'pmpro-events' ) }
					checked={ hasRegistration }
					onChange={ ( value ) =>
						updateMeta( { pmpro_event_has_registration: value } )
					}
					help={ __(
						'Let members who can view this event claim a spot. Use the Require Membership panel to control who that is.',
						'pmpro-events'
					) }
				/>
			</PanelRow>

			{ hasRegistration && (
				<TextControl
					label={ __( 'Capacity', 'pmpro-events' ) }
					type="number"
					min="0"
					step="1"
					value={ String( meta.pmpro_event_capacity || 0 ) }
					onChange={ ( value ) =>
						updateMeta( {
							pmpro_event_capacity: Math.max( 0, parseInt( value, 10 ) || 0 ),
						} )
					}
					help={ __( 'The number of people who can register. Use 0 for unlimited.', 'pmpro-events' ) }
				/>
			) }
		</PluginDocumentSettingPanel>
	);
};

/**
 * All three panels are registered through a single plugin.
 *
 * @return {Element} The panels.
 */
const PMProEventsPanels = () => (
	<>
		<EventDetailsPanel />
		<LocationPanel />
		<RegistrationPanel />
	</>
);

// Nothing to hang the panels on, on an install older than the fallback above
// covers. Bail rather than render an undefined component.
if ( PluginDocumentSettingPanel ) {
	registerPlugin( 'pmpro-events', { render: PMProEventsPanels } );
}
