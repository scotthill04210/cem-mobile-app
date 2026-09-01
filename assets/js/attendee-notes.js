( function ( $ ) {
	'use strict';

	var modal = null;
	var activeEntryId = '';
	var activeTrigger = null;
	var currentSort = {
		index: 1,
		direction: 'asc',
	};
	var initializedRoots = new WeakSet();
	var authDelegatesBound = false;
	var lastTouchOpenAt = 0;
	var noteCache = {};
	var noteLoadId = 0;
	var textareaDirty = false;

	function getModal() {
		var nodes = document.querySelectorAll( '#sepa-attendee-note-modal' );
		var node = nodes.length ? nodes[ nodes.length - 1 ] : null;
		var i;
		for ( i = 0; i < nodes.length - 1; i++ ) {
			if ( nodes[ i ].parentNode ) {
				nodes[ i ].parentNode.removeChild( nodes[ i ] );
			}
		}
		if ( node && document.body && node.parentNode !== document.body ) {
			document.body.appendChild( node );
		}
		modal = node ? $( node ) : $();
		return modal;
	}

	function setFeedback( message, isError ) {
		var currentModal = getModal();
		if ( ! currentModal.length ) {
			return;
		}
		var feedback = currentModal.find( '.sepa-attendee-note-modal__feedback' );
		feedback.text( message || '' );
		feedback.toggleClass( 'is-error', !! isError );
	}

	function noteTextarea() {
		return getModal().find( '.sepa-attendee-note-modal__textarea' );
	}

	function applyScriptConfig( config ) {
		if ( ! config ) {
			return;
		}
		if ( config.nonce ) {
			SEPAAttendeeNotes.nonce = config.nonce;
		}
		if ( config.restUrl ) {
			SEPAAttendeeNotes.restUrl = config.restUrl;
		} else {
			delete SEPAAttendeeNotes.restUrl;
		}
		if ( config.restNonce ) {
			SEPAAttendeeNotes.restNonce = config.restNonce;
		} else {
			delete SEPAAttendeeNotes.restNonce;
		}
	}

	function noteRequest( method, entryId, note ) {
		var payload = {
			nonce: SEPAAttendeeNotes.nonce,
			attendee_key: String( entryId || '' ),
			entry_id: String( entryId || '' ),
		};
		var options = {
			url: SEPAAttendeeNotes.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: payload,
		};
		if ( 'GET' === method ) {
			payload.action = 'cma_attendee_notes_get_note';
			return $.ajax( options );
		}
		if ( 'DELETE' === method ) {
			payload.action = 'cma_attendee_notes_delete_note';
			return $.ajax( options );
		}
		payload.action = 'cma_attendee_notes_save_note';
		payload.note = String( note == null ? '' : note );
		return $.ajax( options );
	}

	function responseMessage( xhr, fallback ) {
		if ( xhr && xhr.responseJSON ) {
			if ( xhr.responseJSON.message ) {
				return String( xhr.responseJSON.message );
			}
			if ( xhr.responseJSON.data && xhr.responseJSON.data.message ) {
				return String( xhr.responseJSON.data.message );
			}
		}
		return fallback;
	}

	function parseNoteResponse( response ) {
		if ( ! response ) {
			return null;
		}
		if ( typeof response === 'string' ) {
			try {
				response = JSON.parse( response );
			} catch ( err ) {
				return null;
			}
		}
		if ( response.success === false ) {
			return null;
		}
		if ( response.success === true && response.data !== undefined ) {
			return response.data;
		}
		if ( response.note !== undefined || response.message !== undefined ) {
			return response;
		}
		return null;
	}

	function noteValueFromData( data ) {
		if ( ! data || data.note == null ) {
			return '';
		}
		return String( data.note );
	}

	function closeModal() {
		getModal().attr( 'hidden', true );
		activeEntryId = '';
		activeTrigger = null;
		textareaDirty = false;
		setFeedback( '' );
	}

	function openModal( entryId, attendeeName, attendeeCompany ) {
		var currentModal = getModal();
		if ( ! currentModal.length ) {
			return;
		}

		var loadId = ++noteLoadId;
		activeEntryId = String( entryId || '' );
		activeTrigger = $( '.sepa-attendee-note-trigger[data-entry-id="' + activeEntryId + '"]' ).first();
		currentModal.find( '.sepa-attendee-note-modal__attendee' ).text( attendeeName || '' );
		currentModal.find( '.sepa-attendee-note-modal__company' ).text( attendeeCompany || '' );
		textareaDirty = false;
		if ( Object.prototype.hasOwnProperty.call( noteCache, activeEntryId ) ) {
			noteTextarea().val( noteCache[ activeEntryId ] );
		} else {
			noteTextarea().val( '' );
		}
		setFeedback( 'Loading note...' );
		currentModal.removeAttr( 'hidden' );
		noteTextarea().trigger( 'focus' );

		noteRequest( 'GET', activeEntryId ).done( function ( response ) {
			if ( loadId !== noteLoadId ) {
				return;
			}
			var data = parseNoteResponse( response );
			if ( ! data ) {
				setFeedback( SEPAAttendeeNotes.strings.saveError, true );
				return;
			}
			var note = noteValueFromData( data );
			noteCache[ activeEntryId ] = note;
			if ( ! textareaDirty ) {
				noteTextarea().val( note );
			}
			setFeedback( '' );
		} ).fail( function ( xhr ) {
			if ( loadId !== noteLoadId ) {
				return;
			}
			setFeedback( responseMessage( xhr, SEPAAttendeeNotes.strings.saveError ), true );
		} );
	}

	function saveNote() {
		if ( ! activeEntryId ) {
			return;
		}

		var note = String( noteTextarea().val() || '' );
		noteCache[ activeEntryId ] = note;
		setFeedback( 'Saving...' );

		noteRequest( 'POST', activeEntryId, note ).done( function ( response ) {
			var data = parseNoteResponse( response );
			if ( data ) {
				if ( data.note != null ) {
					noteCache[ activeEntryId ] = String( data.note );
					if ( ! textareaDirty ) {
						noteTextarea().val( noteCache[ activeEntryId ] );
					}
				}
				markRowHasNote( String( noteCache[ activeEntryId ] || '' ).trim() !== '' );
				applyFilters();
				refreshNoteIndicatorsSoon();
				closeModal();
				return;
			}
			setFeedback( SEPAAttendeeNotes.strings.saveError, true );
		} ).fail( function ( xhr ) {
			setFeedback( responseMessage( xhr, SEPAAttendeeNotes.strings.saveError ), true );
		} );
	}

	function deleteNote() {
		if ( ! activeEntryId ) {
			return;
		}
		if ( ! window.confirm( SEPAAttendeeNotes.strings.deleteConfirm || 'Delete this note?' ) ) {
			return;
		}

		setFeedback( 'Deleting...' );

		noteRequest( 'DELETE', activeEntryId ).done( function ( response ) {
			var data = parseNoteResponse( response );
			if ( data ) {
				noteCache[ activeEntryId ] = '';
				noteTextarea().val( '' );
				textareaDirty = false;
				markRowHasNote( false );
				applyFilters();
				refreshNoteIndicatorsSoon();
				closeModal();
				return;
			}
			setFeedback( SEPAAttendeeNotes.strings.deleteError, true );
		} ).fail( function ( xhr ) {
			setFeedback( responseMessage( xhr, SEPAAttendeeNotes.strings.deleteError ), true );
		} );
	}

	function normalizeSortValue( value ) {
		return String( value || '' ).toLowerCase().trim();
	}

	function sortRows( columnIndex, direction ) {
		var table = document.getElementById( 'sepa-attendee-notes-table' );
		if ( ! table || ! table.tBodies || ! table.tBodies[0] ) {
			return;
		}

		var tbody = table.tBodies[0];
		// ColumnIndex is based on sortable <th> order (excluding the leading Edit button column).
		// Actual row cell index shifts by +1.
		var actualCellIndex = columnIndex + 1;
		var rows = Array.prototype.slice.call( tbody.rows );
		rows.sort( function ( a, b ) {
			var aText = normalizeSortValue( a.cells[ actualCellIndex ] ? a.cells[ actualCellIndex ].textContent : '' );
			var bText = normalizeSortValue( b.cells[ actualCellIndex ] ? b.cells[ actualCellIndex ].textContent : '' );
			if ( aText < bText ) {
				return direction === 'asc' ? -1 : 1;
			}
			if ( aText > bText ) {
				return direction === 'asc' ? 1 : -1;
			}
			return 0;
		} );

		rows.forEach( function ( row ) {
			tbody.appendChild( row );
		} );
	}

	function filterRows( query ) {
		var table = document.getElementById( 'sepa-attendee-notes-table' );
		if ( ! table || ! table.tBodies || ! table.tBodies[0] ) {
			return;
		}

		var normalizedQuery = normalizeSortValue( query );
		var companyFilter = normalizeSortValue( document.getElementById( 'sepa-attendee-notes-company' ) ? document.getElementById( 'sepa-attendee-notes-company' ).value : '' );
		var noteFilter = normalizeSortValue( document.getElementById( 'sepa-attendee-notes-note-status' ) ? document.getElementById( 'sepa-attendee-notes-note-status' ).value : 'all' );
		var rows = table.tBodies[0].rows;
		for ( var i = 0; i < rows.length; i++ ) {
			var row = rows[ i ];
			var haystack = normalizeSortValue(
				( row.cells[1] ? row.cells[1].textContent : '' ) + ' ' +
				( row.cells[2] ? row.cells[2].textContent : '' ) + ' ' +
				( row.cells[3] ? row.cells[3].textContent : '' )
			);
			var matchesSearch = ( '' === normalizedQuery || haystack.indexOf( normalizedQuery ) !== -1 );
			var rowCompany = normalizeSortValue( row.getAttribute( 'data-company' ) || '' );
			var matchesCompany = ( '' === companyFilter || rowCompany === companyFilter );
			var hasNote = String( row.getAttribute( 'data-has-note' ) || '0' ) === '1';
			var matchesNote = true;
			if ( 'has' === noteFilter ) {
				matchesNote = hasNote;
			} else if ( 'none' === noteFilter ) {
				matchesNote = ! hasNote;
			}
			row.style.display = ( matchesSearch && matchesCompany && matchesNote ) ? '' : 'none';
		}
	}

	function applyFilters() {
		var searchInput = document.getElementById( 'sepa-attendee-notes-search' );
		filterRows( searchInput ? searchInput.value : '' );
	}

	function resetTableUi() {
		var searchInput = document.getElementById( 'sepa-attendee-notes-search' );
		var companySelect = document.getElementById( 'sepa-attendee-notes-company' );
		var noteStatusSelect = document.getElementById( 'sepa-attendee-notes-note-status' );

		if ( searchInput ) {
			searchInput.value = '';
		}
		if ( companySelect ) {
			companySelect.value = '';
		}
		if ( noteStatusSelect ) {
			noteStatusSelect.value = 'all';
		}

		currentSort.index = 1;
		currentSort.direction = 'asc';
		sortRows( currentSort.index, currentSort.direction );
		updateSortIndicators();
		applyFilters();
	}

	function markRowHasNote( hasNote ) {
		var entryId = activeEntryId;
		if ( ! entryId ) {
			return;
		}
		var row = document.querySelector( '#sepa-attendee-notes-table tbody tr[data-entry-id="' + entryId + '"]' );
		if ( ! row ) {
			return;
		}
		row.setAttribute( 'data-has-note', hasNote ? '1' : '0' );
		var checkmark = row.querySelector( '.sepa-attendee-note-check' );
		if ( checkmark ) {
			checkmark.classList.toggle( 'is-visible', !! hasNote );
			checkmark.style.visibility = hasNote ? 'visible' : 'hidden';
			checkmark.style.opacity = hasNote ? '1' : '0';
		}
	}

	function syncCheckmarksFromRows( table ) {
		if ( ! table || ! table.tBodies || ! table.tBodies[0] ) {
			return;
		}
		var rows = table.tBodies[0].rows;
		for ( var i = 0; i < rows.length; i++ ) {
			var row = rows[ i ];
			var hasNote = String( row.getAttribute( 'data-has-note' ) || '0' ) === '1';
			var checkmark = row.querySelector( '.sepa-attendee-note-check' );
			if ( checkmark ) {
				checkmark.classList.toggle( 'is-visible', hasNote );
				checkmark.style.visibility = hasNote ? 'visible' : 'hidden';
				checkmark.style.opacity = hasNote ? '1' : '0';
			}
		}
	}

	function refreshNoteIndicatorsSoon() {
		var table = document.getElementById( 'sepa-attendee-notes-table' );
		if ( ! table ) {
			return;
		}
		window.setTimeout( function () {
			syncCheckmarksFromRows( table );
		}, 60 );
	}

	function updateSortIndicators() {
		var headers = document.querySelectorAll( '#sepa-attendee-notes-table thead th[data-sort-key]' );
		headers.forEach( function ( header, idx ) {
			header.classList.remove( 'is-sorted-asc', 'is-sorted-desc' );
			if ( idx === currentSort.index ) {
				header.classList.add( currentSort.direction === 'asc' ? 'is-sorted-asc' : 'is-sorted-desc' );
			}
		} );
	}

	function initAttendeeTableUi( context ) {
		var root = context || document;
		var table = root.querySelector ? root.querySelector( '#sepa-attendee-notes-table' ) : document.getElementById( 'sepa-attendee-notes-table' );
		if ( ! table ) {
			return;
		}
		var headers = table.querySelectorAll( 'thead th[data-sort-key]' );
		headers.forEach( function ( header, idx ) {
			header.classList.add( 'is-sortable' );
			header.addEventListener( 'click', function () {
				if ( currentSort.index === idx ) {
					currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
				} else {
					currentSort.index = idx;
					currentSort.direction = 'asc';
				}
				sortRows( currentSort.index, currentSort.direction );
				updateSortIndicators();
			} );
		} );

		var searchInput = root.querySelector ? root.querySelector( '#sepa-attendee-notes-search' ) : document.getElementById( 'sepa-attendee-notes-search' );
		if ( searchInput ) {
			searchInput.addEventListener( 'input', function () {
				applyFilters();
			} );
		}
		var companySelect = root.querySelector ? root.querySelector( '#sepa-attendee-notes-company' ) : document.getElementById( 'sepa-attendee-notes-company' );
		if ( companySelect ) {
			companySelect.addEventListener( 'change', applyFilters );
		}
		var noteStatusSelect = root.querySelector ? root.querySelector( '#sepa-attendee-notes-note-status' ) : document.getElementById( 'sepa-attendee-notes-note-status' );
		if ( noteStatusSelect ) {
			noteStatusSelect.addEventListener( 'change', applyFilters );
		}
		var resetButton = root.querySelector ? root.querySelector( '.sepa-attendee-notes-reset' ) : document.querySelector( '.sepa-attendee-notes-reset' );
		if ( resetButton ) {
			resetButton.addEventListener( 'click', resetTableUi );
		}

		sortRows( currentSort.index, currentSort.direction );
		updateSortIndicators();
		applyFilters();
		syncCheckmarksFromRows( table );
	}

	function showAuthMessage( form, message, isError ) {
		if ( ! form || ! form.length ) {
			return;
		}
		var card = form.closest( '.sepa-attendee-auth-card' );
		if ( ! card.length ) {
			return;
		}

		var node = card.find( '.sepa-attendee-auth-message' );
		if ( ! node.length ) {
			node = $( '<div class="sepa-attendee-auth-message"></div>' );
			card.prepend( node );
		}

		node.removeClass( 'sepa-attendee-auth-message--error sepa-attendee-auth-message--success' );
		node.addClass( isError ? 'sepa-attendee-auth-message--error' : 'sepa-attendee-auth-message--success' );
		node.text( message || '' );
	}

	function initAjaxLogin() {
		if ( authDelegatesBound ) {
			return;
		}
		authDelegatesBound = true;

		$( document ).on( 'submit', '#sepa-attendee-email-access-form, .sepa-attendee-auth-form--email', function ( event ) {
			event.preventDefault();
			var form = $( this );
			var submitBtn = form.find( 'button[type="submit"]' );
			var originalText = submitBtn.text();
			var email = String( form.find( '[name="sepa_email"]' ).val() || '' ).trim();

			showAuthMessage( form, SEPAAttendeeNotes.strings.loginWait, false );
			submitBtn.prop( 'disabled', true );

			$.post(
				SEPAAttendeeNotes.ajaxUrl,
				{
					action: 'sepa_attendee_notes_login',
					nonce: SEPAAttendeeNotes.nonce,
					email: email,
					redirect_to: form.find( '[name="sepa_attendee_redirect_to"]' ).val(),
				}
			).done( function ( response ) {
				if ( response && response.success && response.data && response.data.html ) {
					applyScriptConfig( response.data.config );
					var currentRoot = form.closest( '.sepa-attendee-notes-root' );
					var newRoot = $( response.data.html );
					if ( currentRoot.length ) {
						currentRoot.replaceWith( newRoot );
					}
					boot( newRoot.get( 0 ) || document );
					return;
				}
				showAuthMessage( form, SEPAAttendeeNotes.strings.loginError, true );
			} ).fail( function ( xhr ) {
				var message = SEPAAttendeeNotes.strings.loginError;
				if ( xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ) {
					message = String( xhr.responseJSON.data.message );
				}
				showAuthMessage( form, message, true );
			} ).always( function () {
				submitBtn.prop( 'disabled', false );
				submitBtn.text( originalText );
			} );
		} );
	}

	function boot( context ) {
		var root = context && context.nodeType ? context : document;
		initAjaxLogin();
		getModal();
		if ( initializedRoots.has( root ) ) {
			return;
		}
		initAttendeeTableUi( root );
		startIframeAutoSwap( root );
		initializedRoots.add( root );
	}

	function startIframeAutoSwap( root ) {
		var iframe = root && root.querySelector ? root.querySelector( 'iframe.sepa-attendee-wp-login-iframe' ) : null;
		if ( ! iframe ) {
			return;
		}

		// Avoid starting multiple pollers on the same root.
		if ( root && root.dataset && root.dataset.sepaIframePollStarted === '1' ) {
			return;
		}
		if ( root && root.dataset ) {
			root.dataset.sepaIframePollStarted = '1';
		}

		var attempts = 0;
		var maxAttempts = 80; // ~80 * 750ms = 60s
		var intervalMs = 750;

		var poller = window.setInterval( function () {
			attempts++;
			$.post(
				SEPAAttendeeNotes.ajaxUrl,
				{
					action: 'sepa_attendee_notes_is_logged_in',
					nonce: SEPAAttendeeNotes.nonce,
				}
			).done( function ( res ) {
				if ( ! res || ! res.success || ! res.data || ! res.data.logged_in ) {
					if ( attempts >= maxAttempts ) {
						window.clearInterval( poller );
					}
					return;
				}

				$.post(
					SEPAAttendeeNotes.ajaxUrl,
					{
						action: 'sepa_attendee_notes_get_html',
						nonce: SEPAAttendeeNotes.nonce,
					}
				).done( function ( htmlRes ) {
					if ( htmlRes && htmlRes.success && htmlRes.data && htmlRes.data.html ) {
						applyScriptConfig( htmlRes.data.config );
						var currentRoot = root;
						var newRoot = $( htmlRes.data.html );
						if ( currentRoot && currentRoot.parentNode && newRoot.length ) {
							$( currentRoot ).replaceWith( newRoot );
							boot( newRoot.get( 0 ) || document );
						}
					}
				} );

				window.clearInterval( poller );
			} ).fail( function () {
				if ( attempts >= maxAttempts ) {
					window.clearInterval( poller );
				}
			} );
		}, intervalMs );
	}

	$( function () {
		boot( document );

		$( document ).on( 'input', '.sepa-attendee-note-modal__textarea', function () {
			textareaDirty = true;
		} );

		$( document ).on( 'touchend click', '.sepa-attendee-note-trigger', function ( event ) {
			if ( 'touchend' === event.type ) {
				lastTouchOpenAt = Date.now();
				event.preventDefault();
			} else if ( Date.now() - lastTouchOpenAt < 700 ) {
				// Ignore synthetic click that often follows touchend on mobile.
				return;
			}

			var trigger = $( this );
			var entryId = String( trigger.attr( 'data-entry-id' ) || '' );
			var name = String( trigger.attr( 'data-attendee-name' ) || '' );
			var company = String( trigger.attr( 'data-attendee-company' ) || '' );
			openModal( entryId, name, company );
		} );

		$( document ).on( 'click', '.sepa-attendee-note-modal__close, .sepa-attendee-note-modal__backdrop', closeModal );
		$( document ).on( 'click', '.sepa-attendee-note-save', saveNote );
		$( document ).on( 'click', '.sepa-attendee-note-delete', deleteNote );
	} );
}( jQuery ) );
