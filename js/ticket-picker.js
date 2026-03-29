/**
 * PMPro Events — Ticket Picker
 *
 * Handles ticket option selection on the single event page.
 *
 * @since 2.0
 */
( function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		// Free for members checkbox toggle in admin.
		var freeCheckbox = document.getElementById( 'pmpro_event_free_for_members' );
		var memberPriceInput = document.getElementById( 'pmpro_event_member_price' );

		if ( freeCheckbox && memberPriceInput ) {
			freeCheckbox.addEventListener( 'change', function() {
				if ( this.checked ) {
					memberPriceInput.value = '0';
					memberPriceInput.disabled = true;
				} else {
					memberPriceInput.disabled = false;
					if ( memberPriceInput.value === '0' ) {
						memberPriceInput.value = '';
					}
				}
			} );

			// Set initial state.
			if ( freeCheckbox.checked ) {
				memberPriceInput.disabled = true;
			}
		}

		// All day checkbox toggle in admin.
		var allDayCheckbox = document.querySelector( 'input[name="pmpro_event_all_day"]' );
		var startTime = document.getElementById( 'pmpro_event_start_time' );
		var endTime = document.getElementById( 'pmpro_event_end_time' );

		if ( allDayCheckbox && startTime && endTime ) {
			allDayCheckbox.addEventListener( 'change', function() {
				startTime.style.display = this.checked ? 'none' : '';
				endTime.style.display = this.checked ? 'none' : '';
			} );

			// Set initial state.
			if ( allDayCheckbox.checked ) {
				startTime.style.display = 'none';
				endTime.style.display = 'none';
			}
		}
	} );
} )();
