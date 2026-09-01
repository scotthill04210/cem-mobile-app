( function ( window ) {
	'use strict';

	function formatOne( el ) {
		var iso = el.getAttribute( 'datetime' );
		if ( ! iso ) {
			return;
		}
		var d = new Date( iso );
		if ( isNaN( d.getTime() ) ) {
			return;
		}
		el.textContent = d.toLocaleString( undefined, {
			dateStyle: 'medium',
			timeStyle: 'short',
		} );
	}

	function mapsFormatNotificationTimes( root ) {
		var scope = root && root.querySelectorAll ? root : document;
		var nodes = scope.querySelectorAll( 'time.maps-notification-sent-at[datetime]' );
		for ( var i = 0; i < nodes.length; i++ ) {
			formatOne( nodes[ i ] );
		}
	}

	window.mapsFormatNotificationTimes = mapsFormatNotificationTimes;

	function boot() {
		mapsFormatNotificationTimes( document );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}( window ) );
