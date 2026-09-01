( function () {
	'use strict';

	var debugMode = !! ( window.MAPSNotificationsOptin && window.MAPSNotificationsOptin.debugMode );

	function isIOS() {
		var ua = window.navigator.userAgent || '';
		var platform = window.navigator.platform || '';
		return /iPad|iPhone|iPod/.test( ua ) || ( platform === 'MacIntel' && window.navigator.maxTouchPoints > 1 );
	}

	function isStandalone() {
		return window.matchMedia( '(display-mode: standalone)' ).matches || window.navigator.standalone === true;
	}

	function getMessageNode( button ) {
		if ( ! button || ! button.parentElement ) {
			return null;
		}
		return button.parentElement.querySelector( '.maps-enable-notifications-message' );
	}

	function setMessage( button, message ) {
		var messageNode = getMessageNode( button );
		if ( messageNode ) {
			messageNode.textContent = message;
		}
	}

	function getDebugNode( button ) {
		if ( ! button || ! button.parentElement ) {
			return null;
		}
		return button.parentElement.querySelector( '.maps-enable-notifications-debug' );
	}

	function setDebug( button, message ) {
		if ( ! debugMode ) {
			return;
		}
		var debugNode = getDebugNode( button );
		if ( debugNode ) {
			debugNode.textContent = '[Debug] ' + message;
		}
		if ( window.console && typeof window.console.log === 'function' ) {
			window.console.log( '[MAPS]', message );
		}
	}

	function getEnvDebugNode( button ) {
		if ( ! button || ! button.parentElement ) {
			return null;
		}
		return button.parentElement.querySelector( '.maps-notifications-env-debug' );
	}

	function getPushSubscriptionDebugLines() {
		var lines = [];
		lines.push( '--- Web push subscription (OneSignal) ---' );
		var os = window.mapsOneSignal;
		if ( ! os ) {
			lines.push( 'window.mapsOneSignal: (not set yet — wait for init or check init error above)' );
			return lines;
		}
		if ( ! os.User || ! os.User.PushSubscription ) {
			lines.push( 'User.PushSubscription: (unavailable on this SDK build)' );
			return lines;
		}
		var ps = os.User.PushSubscription;
		var id = ps.id;
		var optedIn = typeof ps.optedIn === 'undefined' ? '' : String( ps.optedIn );
		lines.push( 'Subscription ID (paste into WP setting): ' + ( id ? String( id ) : '(not assigned yet)' ) );
		lines.push( 'optedIn: ' + optedIn );
		lines.push( 'Console: window.mapsOneSignalReady.then(function(){ console.log(window.mapsOneSignal.User.PushSubscription.id); });' );
		return lines;
	}

	function wirePushSubscriptionChangeListener() {
		if ( window.mapsPushSubscriptionDebugListenerBound ) {
			return;
		}
		waitForOneSignalReady( 20000 ).then( function ( OneSignal ) {
			if ( ! OneSignal || ! OneSignal.User || ! OneSignal.User.PushSubscription ) {
				return;
			}
			var ps = OneSignal.User.PushSubscription;
			if ( typeof ps.addEventListener !== 'function' ) {
				return;
			}
			window.mapsPushSubscriptionDebugListenerBound = true;
			ps.addEventListener( 'change', function () {
				writeEnvironmentDebug();
			} );
		} ).catch( function () {} );
	}

	function pollSubscriptionIdForButton( button, OneSignal, attempt ) {
		if ( ! debugMode || ! button || ! OneSignal || ! OneSignal.User || ! OneSignal.User.PushSubscription ) {
			return;
		}
		var maxAttempts = 40;
		var n = typeof attempt === 'number' ? attempt : 0;
		var id = OneSignal.User.PushSubscription.id;
		if ( id ) {
			setDebug( button, 'Subscription ID: ' + String( id ) + ' (copy into Settings > Test Web Subscription ID)' );
			writeEnvironmentDebug();
			return;
		}
		if ( n >= maxAttempts ) {
			setDebug( button, 'Subscription ID still empty after waiting. Check env debug block, confirm permission + SW, or copy ID from OneSignal Audience.' );
			writeEnvironmentDebug();
			return;
		}
		window.setTimeout( function () {
			pollSubscriptionIdForButton( button, OneSignal, n + 1 );
		}, 500 );
	}

	function writeEnvironmentDebug() {
		if ( ! debugMode ) {
			return;
		}

		var nodes = document.querySelectorAll( '.maps-notifications-env-debug' );
		if ( ! nodes.length ) {
			return;
		}

		var lines = [];
		lines.push( 'Environment Debug' );
		lines.push( '-----------------' );
		lines.push( 'Standalone mode: ' + String( isStandalone() ) );
		lines.push( 'Service Worker API: ' + String( 'serviceWorker' in window.navigator ) );
		lines.push( 'OneSignal script state: ' + String( window.mapsOneSignalScriptLoadState || '' ) );
		lines.push( 'OneSignal init error: ' + String( window.mapsOneSignalInitError || '' ) );

		var subLines = getPushSubscriptionDebugLines();
		for ( var s = 0; s < subLines.length; s++ ) {
			lines.push( subLines[ s ] );
		}

		nodes.forEach( function ( node ) {
			node.textContent = lines.join( '\n' );
		} );
	}

	function revealIOSHints() {
		if ( ! isIOS() ) {
			return;
		}
		var hints = document.querySelectorAll( '.maps-enable-notifications-ios-hint' );
		hints.forEach( function ( hint ) {
			if ( ! isStandalone() ) {
				hint.hidden = false;
			}
		} );
	}

	function shouldKeepEnableButtonVisible() {
		return isIOS() && ! isStandalone();
	}

	function hideEnableButton( button, message ) {
		if ( ! button ) {
			return;
		}
		button.hidden = true;
		if ( message ) {
			setMessage( button, message );
		}
		if ( button.parentElement ) {
			var hint = button.parentElement.querySelector( '.maps-enable-notifications-ios-hint' );
			if ( hint ) {
				hint.hidden = true;
			}
		}
	}

	function applyPermissionStateToButton( button, OneSignal ) {
		if ( ! button || ! OneSignal || ! OneSignal.Notifications ) {
			return;
		}
		if ( shouldKeepEnableButtonVisible() ) {
			return;
		}
		if ( OneSignal.Notifications.permission ) {
			hideEnableButton( button, 'Notifications are already enabled on this device.' );
			pollSubscriptionIdForButton( button, OneSignal, 0 );
		}
	}

	function checkEnabledButtonsOnLoad() {
		var buttons = document.querySelectorAll( '.maps-enable-notifications-button' );
		if ( ! buttons.length ) {
			return;
		}

		waitForOneSignalReady( 10000 ).then( function ( OneSignal ) {
			buttons.forEach( function ( button ) {
				applyPermissionStateToButton( button, OneSignal );
			} );
			writeEnvironmentDebug();
		} ).catch( function () {} );
	}

	function waitForOneSignalReady( timeoutMs ) {
		var readyPromise = window.mapsOneSignalReady;
		if ( ! readyPromise || typeof readyPromise.then !== 'function' ) {
			return Promise.reject( new Error( 'not_ready' ) );
		}

		if ( window.mapsOneSignal && window.mapsOneSignal.Notifications ) {
			return Promise.resolve( window.mapsOneSignal );
		}

		return Promise.race( [
			readyPromise,
			new Promise( function ( resolve, reject ) {
				window.setTimeout( function () {
					reject( new Error( 'timeout' ) );
				}, timeoutMs );
			} ),
		] );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.maps-enable-notifications-button' );
		if ( ! button ) {
			return;
		}

		event.preventDefault();

		if ( isIOS() && ! isStandalone() ) {
			setDebug( button, 'Blocked: iOS Safari tab mode (not standalone)' );
			setMessage( button, 'On iPhone, first open this from your Home Screen app (Add to Home Screen), then tap again.' );
			return;
		}

		button.disabled = true;
		setDebug( button, 'Button clicked; waiting for OneSignal ready promise' );
		setMessage( button, 'Checking notification permission...' );

		waitForOneSignalReady( 8000 ).then( async function ( OneSignal ) {
			try {
				if ( ! OneSignal || ! OneSignal.Notifications ) {
					setDebug( button, 'OneSignal loaded but Notifications API unavailable' );
					setMessage( button, 'Notifications are not available on this device/browser.' );
					return;
				}

				var permission = OneSignal.Notifications.permission;
				setDebug( button, 'Current permission state: ' + String( permission ) );
				if ( permission ) {
					hideEnableButton( button, 'Notifications are already enabled on this device.' );
					pollSubscriptionIdForButton( button, OneSignal, 0 );
					writeEnvironmentDebug();
					return;
				}

				await OneSignal.Notifications.requestPermission();
				var updatedPermission = OneSignal.Notifications.permission;
				setDebug( button, 'Permission state after request: ' + String( updatedPermission ) );
				if ( updatedPermission ) {
					hideEnableButton( button, 'Notifications enabled successfully.' );
					pollSubscriptionIdForButton( button, OneSignal, 0 );
					writeEnvironmentDebug();
				} else {
					setMessage( button, 'Permission was not granted. On iPhone, open this as a Home Screen app, then try again.' );
				}
			} catch ( error ) {
				setDebug( button, 'Permission request error: ' + ( error && error.message ? error.message : 'unknown' ) );
				setMessage( button, 'Unable to enable notifications right now.' );
			} finally {
				button.disabled = false;
			}
		} ).catch( function () {
			button.disabled = false;
			if ( isIOS() && ! isStandalone() ) {
				setDebug( button, 'Ready promise failed while iOS not standalone' );
				setMessage( button, 'On iPhone, notifications require the Home Screen app. Open from Home Screen and try again.' );
				return;
			}
			setDebug( button, 'OneSignal ready promise timed out or failed: ' + ( window.mapsOneSignalInitError || 'no error details available' ) );
			if ( window.mapsOneSignalInitError ) {
				setMessage( button, 'Unable to initialize notifications: ' + window.mapsOneSignalInitError );
				return;
			}
			setMessage( button, 'Notifications are still loading. Reload /app and try again.' );
		} );
	} );

	revealIOSHints();
	checkEnabledButtonsOnLoad();
	wirePushSubscriptionChangeListener();
	writeEnvironmentDebug();
	window.setTimeout( writeEnvironmentDebug, 1500 );
	window.setTimeout( writeEnvironmentDebug, 5000 );
	window.setTimeout( writeEnvironmentDebug, 12000 );
	if ( 'serviceWorker' in window.navigator && window.navigator.serviceWorker.getRegistrations ) {
		window.navigator.serviceWorker.getRegistrations().then( function ( regs ) {
			var urls = regs.map( function ( reg ) {
				var sw = reg.active || reg.installing || reg.waiting;
				return sw && sw.scriptURL ? sw.scriptURL : '(unknown)';
			} );
			window.mapsOneSignalInitError = window.mapsOneSignalInitError || '';
			var debugNodes = document.querySelectorAll( '.maps-notifications-env-debug' );
			debugNodes.forEach( function ( node ) {
				node.textContent += '\nSW registrations: ' + ( urls.length ? urls.join( ' | ' ) : '(none)' );
			} );
		} ).catch( function () {} );
	}
}() );
