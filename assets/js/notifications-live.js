( function () {
	'use strict';

	function toBoolean( value ) {
		return value === 'yes' || value === '1' || value === 'true';
	}

	function refreshContainer( container ) {
		var limit = parseInt( container.getAttribute( 'data-limit' ) || '5', 10 );
		var showDate = container.getAttribute( 'data-show-date' ) || 'yes';
		var localTime = container.getAttribute( 'data-local-time' ) || 'yes';
		var restBase = ( window.CMANotificationsLive && window.CMANotificationsLive.restUrl ) ? window.CMANotificationsLive.restUrl : ( window.location.origin + '/wp-json/cma/v1/recent-notifications' );
		var endpoint = restBase + ( restBase.indexOf( '?' ) === -1 ? '?' : '&' ) + 'limit=' + encodeURIComponent( limit ) + '&show_date=' + encodeURIComponent( showDate ) + '&local_time=' + encodeURIComponent( localTime );

		fetch( endpoint, {
			cache: 'no-store',
			credentials: 'same-origin',
			headers: {
				'Accept': 'application/json',
			},
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Request failed' );
				}
				return response.json();
			} )
			.then( function ( data ) {
				if ( data && typeof data.html === 'string' ) {
					container.innerHTML = data.html;
					if ( window.mapsFormatNotificationTimes ) {
						window.mapsFormatNotificationTimes( container );
					}
				}
			} )
			.catch( function () {
				// Keep existing markup if request fails.
			} );
	}

	function initLiveNotifications() {
		var containers = document.querySelectorAll( '.maps-app-notifications-recent' );
		containers.forEach( function ( container ) {
			if ( ! toBoolean( ( container.getAttribute( 'data-live' ) || 'no' ).toLowerCase() ) ) {
				return;
			}

			var seconds = parseInt( container.getAttribute( 'data-refresh-seconds' ) || '30', 10 );
			var intervalMs = Math.max( 10000, Math.min( 300000, seconds * 1000 ) );

			window.setInterval( function () {
				refreshContainer( container );
			}, intervalMs );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', initLiveNotifications );
}() );
