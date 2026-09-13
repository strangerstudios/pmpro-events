/**
 * The user picker on the Registrations admin page.
 *
 * Enhances the add-registration text field into a search-as-you-type picker.
 * Matches show the member's avatar, email, and membership level; selecting one
 * fills the pmpro_events_user_id hidden field. Without JavaScript the plain
 * text field posts as before.
 */
( function ( i18n ) {
	var __ = i18n.__;
	var settings = window.pmproEventsRegistrations || {};

	var input = document.getElementById( 'pmpro_events_add_user' );
	if ( ! input || ! settings.ajaxUrl ) {
		return;
	}

	var form = input.closest( 'form' );
	var hiddenId = form.querySelector( 'input[name="pmpro_events_user_id"]' );
	var eventField = form.querySelector( 'input[name="event_id"]' );
	var eventId = eventField ? eventField.value : 0;

	// The results dropdown sits directly under the input.
	var wrap = document.createElement( 'div' );
	wrap.className = 'pmpro_events_user_search';
	input.parentNode.insertBefore( wrap, input );
	wrap.appendChild( input );

	var list = document.createElement( 'ul' );
	list.className = 'pmpro_events_user_search_results';
	list.setAttribute( 'role', 'listbox' );
	list.hidden = true;
	wrap.appendChild( list );

	var results = [];
	var activeIndex = -1;
	var searchTimer = null;
	var lastTerm = '';

	input.setAttribute( 'role', 'combobox' );
	input.setAttribute( 'aria-expanded', 'false' );
	input.setAttribute( 'aria-autocomplete', 'list' );

	function closeList() {
		list.hidden = true;
		list.innerHTML = '';
		input.setAttribute( 'aria-expanded', 'false' );
		results = [];
		activeIndex = -1;
	}

	function renderList() {
		list.innerHTML = '';

		if ( ! results.length ) {
			var none = document.createElement( 'li' );
			none.className = 'pmpro_events_user_search_none';
			none.textContent = __( 'No members found.', 'pmpro-events' );
			list.appendChild( none );
		}

		results.forEach( function ( user, index ) {
			var item = document.createElement( 'li' );
			item.setAttribute( 'role', 'option' );

			var avatar = document.createElement( 'img' );
			avatar.src = user.avatar;
			avatar.alt = '';
			item.appendChild( avatar );

			var text = document.createElement( 'span' );
			text.className = 'pmpro_events_user_search_text';

			var name = document.createElement( 'strong' );
			name.textContent = user.name || user.login;
			text.appendChild( name );

			var meta = document.createElement( 'span' );
			meta.textContent = user.email + ( user.level ? ' — ' + user.level : '' );
			text.appendChild( meta );

			item.appendChild( text );

			if ( user.registered ) {
				var badge = document.createElement( 'span' );
				badge.className = 'pmpro_events_user_search_badge';
				badge.textContent = __( 'Already registered', 'pmpro-events' );
				item.appendChild( badge );
			}

			item.addEventListener( 'mousedown', function ( event ) {
				// mousedown, so the click beats the input's blur.
				event.preventDefault();
				select( index );
			} );

			list.appendChild( item );
		} );

		list.hidden = false;
		input.setAttribute( 'aria-expanded', 'true' );
	}

	function setActive( index ) {
		activeIndex = index;
		Array.prototype.forEach.call( list.children, function ( item, i ) {
			item.classList.toggle( 'is-active', i === index );
		} );
	}

	function select( index ) {
		var user = results[ index ];
		if ( ! user ) {
			return;
		}

		closeList();

		hiddenId.value = user.id;
		input.value = user.login;
	}

	function search( term ) {
		var url = settings.ajaxUrl +
			'?action=pmpro_events_search_users' +
			'&nonce=' + encodeURIComponent( settings.nonce ) +
			'&event_id=' + encodeURIComponent( eventId ) +
			'&term=' + encodeURIComponent( term );

		window.fetch( url )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( response ) {
				// Ignore responses that arrive after the input changed again.
				if ( term !== lastTerm || ! response.success ) {
					return;
				}

				results = response.data;
				renderList();
			} );
	}

	input.addEventListener( 'input', function () {
		var term = input.value.trim();
		lastTerm = term;

		// Anything typed after a selection is a new search.
		hiddenId.value = '';

		window.clearTimeout( searchTimer );

		if ( term.length < 2 ) {
			closeList();
			return;
		}

		searchTimer = window.setTimeout( function () {
			search( term );
		}, 250 );
	} );

	input.addEventListener( 'keydown', function ( event ) {
		if ( list.hidden ) {
			return;
		}

		if ( 'ArrowDown' === event.key ) {
			event.preventDefault();
			setActive( Math.min( activeIndex + 1, results.length - 1 ) );
		} else if ( 'ArrowUp' === event.key ) {
			event.preventDefault();
			setActive( Math.max( activeIndex - 1, 0 ) );
		} else if ( 'Enter' === event.key && activeIndex >= 0 ) {
			event.preventDefault();
			select( activeIndex );
		} else if ( 'Escape' === event.key ) {
			closeList();
		}
	} );

	input.addEventListener( 'blur', function () {
		closeList();
	} );
} )( window.wp.i18n );
