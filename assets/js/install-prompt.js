( function () {
	'use strict';

	var deferredPrompt = null;

	function isIOS() {
		var ua = window.navigator.userAgent || '';
		var platform = window.navigator.platform || '';
		return /iPad|iPhone|iPod/.test( ua ) || ( platform === 'MacIntel' && window.navigator.maxTouchPoints > 1 );
	}

	function isSafariIOS() {
		if ( ! isIOS() ) {
			return false;
		}
		var ua = window.navigator.userAgent || '';
		return /Safari/i.test( ua ) && ! /CriOS|FxiOS|EdgiOS|OPiOS|DuckDuckGo|GSA/i.test( ua );
	}

	function isStandalone() {
		return window.matchMedia( '(display-mode: standalone)' ).matches || window.navigator.standalone === true;
	}

	function setMessage( container, message ) {
		var node = container.querySelector( '.maps-install-app-message' );
		if ( node ) {
			node.textContent = message;
		}
	}

	function getCopyButton( container ) {
		return container.querySelector( '.maps-copy-app-url-button' );
	}

	function getInstallButton( container ) {
		return container.querySelector( '.maps-install-app-button' );
	}

	function updateButtonLabel( container ) {
		var button = getInstallButton( container );
		if ( ! button ) {
			return;
		}

		if ( isIOS() ) {
			button.textContent = container.getAttribute( 'data-label-ios' ) || button.textContent;
			return;
		}

		button.textContent = container.getAttribute( 'data-label-android' ) || button.textContent;
	}

	function showIOSHelpers( container ) {
		if ( ! container ) {
			return;
		}
		var nodes = container.querySelectorAll( '.maps-install-ios-only' );
		nodes.forEach( function ( node ) {
			if ( isSafariIOS() ) {
				node.hidden = true;
				return;
			}
			node.hidden = false;
		} );
	}

	function showSafariGuide( container ) {
		if ( ! container || ! isSafariIOS() ) {
			return;
		}
		var nodes = container.querySelectorAll( '.maps-install-safari-only' );
		nodes.forEach( function ( node ) {
			node.hidden = false;
		} );
	}

	function wireContainer( container ) {
		var button = getInstallButton( container );
		if ( ! button ) {
			return;
		}
		var copyButton = getCopyButton( container );

		updateButtonLabel( container );

		if ( copyButton ) {
			copyButton.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var appUrl = copyButton.getAttribute( 'data-app-url' ) || window.location.href;
				if ( navigator.clipboard && typeof navigator.clipboard.writeText === 'function' ) {
					navigator.clipboard.writeText( appUrl ).then( function () {
						setMessage( container, 'URL copied. Paste it into Safari, then tap Share > Add to Home Screen.' );
					} ).catch( function () {
						setMessage( container, 'Copy failed. Press and hold this link in your browser, copy it, then paste in Safari.' );
					} );
					return;
				}
				setMessage( container, 'Copy is not supported here. Press and hold this page URL, copy it, then paste in Safari.' );
			} );
		}

		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();

			if ( isStandalone() ) {
				setMessage( container, 'App is already on your Home Screen.' );
				return;
			}

			if ( isIOS() ) {
				showIOSHelpers( container );
				showSafariGuide( container );
				if ( isSafariIOS() ) {
					setMessage( container, 'Tap Share, then "Add to Home Screen".' );
				} else {
					setMessage( container, 'Copy the /app URL below, open Safari, paste it, then tap Share > Add to Home Screen.' );
				}
				return;
			}

			if ( deferredPrompt ) {
				deferredPrompt.prompt();
				deferredPrompt.userChoice.finally( function () {
					deferredPrompt = null;
				} );
				return;
			}

			setMessage( container, 'Use your browser menu and tap "Install app" or "Add to Home Screen".' );
		} );
	}

	window.addEventListener( 'beforeinstallprompt', function ( event ) {
		event.preventDefault();
		deferredPrompt = event;
	} );

	function init() {
		var containers = document.querySelectorAll( '.maps-install-app' );
		containers.forEach( wireContainer );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
