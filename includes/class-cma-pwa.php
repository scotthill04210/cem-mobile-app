<?php

defined( 'ABSPATH' ) || exit;

class CMA_PWA {

	public function __construct() {
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'init', [ self::class, 'register_rewrite_rules' ] );
		add_action( 'init', [ $this, 'intercept_onesignal_worker_filenames' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_proxy_onesignal_asset' ], 0 );
		add_action( 'template_redirect', [ $this, 'maybe_serve_pwa_assets' ], 0 );
		add_action( 'wp_head', [ $this, 'inject_mobile_app_meta' ], 1 );
		add_action( 'wp_head', [ $this, 'inject_display_mode_classes_script' ], 5 );
		add_action( 'wp_head', [ $this, 'inject_display_mode_wrapper_styles' ], 6 );
		add_action( 'wp_footer', [ $this, 'register_service_worker' ], 100 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_onesignal_sdk' ] );
	}

	/**
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = 'cma_manifest';
		$vars[] = 'cma_sw';
		$vars[] = 'cma_onesignal_sw';
		$vars[] = 'cma_onesignal_updater_sw';
		$vars[] = 'cma_os_proxy';

		return $vars;
	}

	public static function register_rewrite_rules(): void {
		$slug = CMA_Settings::get_app_slug();
		add_rewrite_rule( '^OneSignalSDKWorker\.js$', 'index.php?cma_onesignal_sw=1', 'top' );
		add_rewrite_rule( '^OneSignalSDKUpdaterWorker\.js$', 'index.php?cma_onesignal_updater_sw=1', 'top' );
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/OneSignalSDKWorker\.js$', 'index.php?cma_onesignal_sw=1', 'top' );
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/OneSignalSDKUpdaterWorker\.js$', 'index.php?cma_onesignal_updater_sw=1', 'top' );
	}

	/**
	 * @return array<string, string>
	 */
	private function get_onesignal_proxy_targets(): array {
		return [
			'page_es6' => 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.es6.js?v=160603',
			'sw'       => 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js',
		];
	}

	public function maybe_proxy_onesignal_asset(): void {
		$key = sanitize_key( (string) get_query_var( 'cma_os_proxy' ) );
		if ( '' === $key ) {
			return;
		}

		$targets = $this->get_onesignal_proxy_targets();
		if ( ! isset( $targets[ $key ] ) ) {
			status_header( 404 );
			exit;
		}

		$response = wp_remote_get(
			$targets[ $key ],
			[
				'timeout' => 20,
				'headers' => [
					'Accept' => '*/*',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			status_header( 502 );
			exit;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code > 299 ) {
			status_header( 502 );
			exit;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			status_header( 502 );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Proxied JavaScript bytes.
		echo $body;
		exit;
	}

	public function maybe_serve_pwa_assets(): void {
		if ( absint( get_query_var( 'cma_manifest' ) ) ) {
			$this->serve_manifest();
		}

		if ( absint( get_query_var( 'cma_sw' ) ) ) {
			$this->serve_service_worker();
		}

		if ( absint( get_query_var( 'cma_onesignal_sw' ) ) ) {
			$this->serve_onesignal_service_worker();
		}

		if ( absint( get_query_var( 'cma_onesignal_updater_sw' ) ) ) {
			$this->serve_onesignal_updater_service_worker();
		}
	}

	public function intercept_onesignal_worker_filenames(): void {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
			return;
		}
		if ( '' === CMA_Settings::get_onesignal_app_id() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only path inspection.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '';
		if ( '' === $uri ) {
			return;
		}

		$path = wp_parse_url( $uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return;
		}

		$file = basename( $path );
		if ( 0 === strcasecmp( 'OneSignalSDKWorker.js', $file ) ) {
			$this->serve_onesignal_service_worker();
		}
		if ( 0 === strcasecmp( 'OneSignalSDKUpdaterWorker.js', $file ) ) {
			$this->serve_onesignal_updater_service_worker();
		}
	}

	private function serve_onesignal_service_worker(): void {
		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: ' . CMA_Settings::get_app_path() );
		$sw_url = esc_url_raw( CMA_Settings::get_public_origin() . '/?cma_os_proxy=sw' );
		echo "importScripts('" . esc_js( $sw_url ) . "');";
		exit;
	}

	private function serve_onesignal_updater_service_worker(): void {
		$this->serve_onesignal_service_worker();
	}

	private function serve_manifest(): void {
		$app_name    = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$icon_192    = CMA_Settings::get_site_icon_url( 192 );
		$icon_512    = CMA_Settings::get_site_icon_url( 512 );
		$theme_color = '#ffffff';
		$app_url     = CMA_Settings::get_app_url();

		$icons = [];
		if ( $icon_192 ) {
			$icons[] = [
				'src'   => $icon_192,
				'sizes' => '192x192',
				'type'  => 'image/png',
			];
		}
		if ( $icon_512 ) {
			$icons[] = [
				'src'   => $icon_512,
				'sizes' => '512x512',
				'type'  => 'image/png',
			];
		}

		$manifest = [
			'name'             => $app_name,
			'short_name'       => substr( $app_name, 0, 12 ),
			'start_url'        => $app_url,
			'scope'            => $app_url,
			'display'          => 'standalone',
			'background_color' => $theme_color,
			'theme_color'      => $theme_color,
			'icons'            => $icons,
		];

		nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		echo wp_json_encode( $manifest );
		exit;
	}

	private function serve_service_worker(): void {
		$sw_version = CMA_VERSION;
		$app_url    = esc_url_raw( CMA_Settings::get_app_url() );
		$app_path   = CMA_Settings::get_app_path();
		$slug_path  = '/' . trim( $app_path, '/' );
		$cache_name = 'cma-app-shell-' . $sw_version . '-' . CMA_Settings::get_sw_cache_token();

		$sw_source  = "self.addEventListener('install', function(event) {\n";
		$sw_source .= "\tevent.waitUntil(\n";
		$sw_source .= "\t\tcaches.open('" . esc_js( $cache_name ) . "').then(function(cache) {\n";
		$sw_source .= "\t\t\treturn cache.addAll(['" . esc_js( $app_url ) . "']);\n";
		$sw_source .= "\t\t})\n";
		$sw_source .= "\t);\n";
		$sw_source .= "\tself.skipWaiting();\n";
		$sw_source .= "});\n\n";
		$sw_source .= "self.addEventListener('activate', function(event) {\n";
		$sw_source .= "\tevent.waitUntil(\n";
		$sw_source .= "\t\tcaches.keys().then(function(keys) {\n";
		$sw_source .= "\t\t\treturn Promise.all(\n";
		$sw_source .= "\t\t\t\tkeys.map(function(key) {\n";
		$sw_source .= "\t\t\t\t\tif (key !== '" . esc_js( $cache_name ) . "') {\n";
		$sw_source .= "\t\t\t\t\t\treturn caches.delete(key);\n";
		$sw_source .= "\t\t\t\t\t}\n";
		$sw_source .= "\t\t\t\t\treturn Promise.resolve();\n";
		$sw_source .= "\t\t\t\t})\n";
		$sw_source .= "\t\t\t);\n";
		$sw_source .= "\t\t})\n";
		$sw_source .= "\t);\n";
		$sw_source .= "\tself.clients.claim();\n";
		$sw_source .= "});\n\n";
		$sw_source .= "self.addEventListener('fetch', function(event) {\n";
		$sw_source .= "\tvar requestUrl = new URL(event.request.url);\n";
		$sw_source .= "\tvar path = requestUrl.pathname;\n";
		$sw_source .= "\tvar slugPath = '" . esc_js( $slug_path ) . "';\n";
		$sw_source .= "\tif (path !== slugPath && path.indexOf(slugPath + '/') !== 0) {\n";
		$sw_source .= "\t\treturn;\n";
		$sw_source .= "\t}\n\n";
		$sw_source .= "\tvar isDocument = event.request.mode === 'navigate' || event.request.destination === 'document';\n";
		$sw_source .= "\tif (isDocument) {\n";
		$sw_source .= "\t\tevent.respondWith(\n";
		$sw_source .= "\t\t\tfetch(event.request)\n";
		$sw_source .= "\t\t\t\t.then(function(networkResponse) {\n";
		$sw_source .= "\t\t\t\t\tif (networkResponse && networkResponse.status === 200) {\n";
		$sw_source .= "\t\t\t\t\t\tvar responseClone = networkResponse.clone();\n";
		$sw_source .= "\t\t\t\t\t\tcaches.open('" . esc_js( $cache_name ) . "').then(function(cache) {\n";
		$sw_source .= "\t\t\t\t\t\t\tcache.put(event.request, responseClone);\n";
		$sw_source .= "\t\t\t\t\t\t});\n";
		$sw_source .= "\t\t\t\t\t}\n";
		$sw_source .= "\t\t\t\t\treturn networkResponse;\n";
		$sw_source .= "\t\t\t\t})\n";
		$sw_source .= "\t\t\t\t.catch(function() {\n";
		$sw_source .= "\t\t\t\t\treturn caches.match(event.request).then(function(cachedDoc) {\n";
		$sw_source .= "\t\t\t\t\t\treturn cachedDoc || caches.match('" . esc_js( $app_url ) . "');\n";
		$sw_source .= "\t\t\t\t\t});\n";
		$sw_source .= "\t\t\t\t})\n";
		$sw_source .= "\t\t);\n";
		$sw_source .= "\t\treturn;\n";
		$sw_source .= "\t}\n\n";
		$sw_source .= "\tevent.respondWith(\n";
		$sw_source .= "\t\tcaches.match(event.request).then(function(cachedResponse) {\n";
		$sw_source .= "\t\t\tif (cachedResponse) {\n";
		$sw_source .= "\t\t\t\treturn cachedResponse;\n";
		$sw_source .= "\t\t\t}\n";
		$sw_source .= "\t\t\treturn fetch(event.request)\n";
		$sw_source .= "\t\t\t\t.then(function(networkResponse) {\n";
		$sw_source .= "\t\t\t\t\tif (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {\n";
		$sw_source .= "\t\t\t\t\t\treturn networkResponse;\n";
		$sw_source .= "\t\t\t\t\t}\n";
		$sw_source .= "\t\t\t\t\tvar responseClone = networkResponse.clone();\n";
		$sw_source .= "\t\t\t\t\tcaches.open('" . esc_js( $cache_name ) . "').then(function(cache) {\n";
		$sw_source .= "\t\t\t\t\t\tcache.put(event.request, responseClone);\n";
		$sw_source .= "\t\t\t\t\t});\n";
		$sw_source .= "\t\t\t\t\treturn networkResponse;\n";
		$sw_source .= "\t\t\t\t})\n";
		$sw_source .= "\t\t\t\t.catch(function() {\n";
		$sw_source .= "\t\t\t\t\treturn caches.match('" . esc_js( $app_url ) . "');\n";
		$sw_source .= "\t\t\t\t});\n";
		$sw_source .= "\t\t})\n";
		$sw_source .= "\t);\n";
		$sw_source .= "});\n";

		nocache_headers();
		header( 'Content-Type: application/javascript; charset=utf-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Generated JavaScript.
		echo $sw_source;
		exit;
	}

	public function inject_mobile_app_meta(): void {
		if ( ! CMA_Settings::is_app_page() ) {
			return;
		}
		?>
		<link rel="manifest" href="<?php echo esc_url( home_url( '/?cma_manifest=1' ) ); ?>" />
		<meta name="mobile-web-app-capable" content="yes" />
		<meta name="apple-mobile-web-app-capable" content="yes" />
		<meta name="apple-mobile-web-app-status-bar-style" content="default" />
		<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
		<meta name="format-detection" content="telephone=no" />
		<meta name="theme-color" content="#f2f2f7" />
		<?php
		$apple_icon = CMA_Settings::get_site_icon_url( 180 );
		if ( $apple_icon ) :
			?>
			<link rel="apple-touch-icon" href="<?php echo esc_url( $apple_icon ); ?>" />
			<?php
		endif;
	}

	public function register_service_worker(): void {
		if ( ! CMA_Settings::is_app_page() ) {
			return;
		}

		if ( '' !== CMA_Settings::get_onesignal_app_id() ) {
			return;
		}

		$scope_path = wp_parse_url( CMA_Settings::get_app_url(), PHP_URL_PATH );
		?>
		<script>
		( function () {
			if ( 'serviceWorker' in navigator ) {
				window.addEventListener( 'load', function () {
					navigator.serviceWorker.register(
						'<?php echo esc_url( home_url( '/?cma_sw=1' ) ); ?>',
						{ scope: '<?php echo esc_js( is_string( $scope_path ) ? $scope_path : CMA_Settings::get_app_path() ); ?>' }
					).catch( function () {} );
				} );
			}
		}() );
		</script>
		<?php
	}

	public function inject_display_mode_classes_script(): void {
		if ( ! CMA_Settings::is_app_page() ) {
			return;
		}
		?>
		<script>
		( function () {
			var isStandalone = window.matchMedia( '(display-mode: standalone)' ).matches || window.navigator.standalone === true;
			var root = document.documentElement;
			if ( ! root ) {
				return;
			}

			root.classList.add( isStandalone ? 'maps-display-standalone' : 'maps-display-browser' );
			root.classList.add( /iPad|iPhone|iPod/.test( window.navigator.userAgent || '' ) ? 'maps-ios-device' : 'maps-non-ios-device' );

			function applyBodyClasses() {
				if ( ! document.body ) {
					return;
				}
				document.body.classList.add( isStandalone ? 'maps-display-standalone' : 'maps-display-browser' );
				document.body.classList.add( /iPad|iPhone|iPod/.test( window.navigator.userAgent || '' ) ? 'maps-ios-device' : 'maps-non-ios-device' );
			}

			if ( document.readyState === 'loading' ) {
				document.addEventListener( 'DOMContentLoaded', applyBodyClasses );
			} else {
				applyBodyClasses();
			}
		}() );
		</script>
		<?php
	}

	public function inject_display_mode_wrapper_styles(): void {
		if ( ! CMA_Settings::is_app_page() ) {
			return;
		}
		?>
		<style id="maps-display-mode-wrapper-styles">
			.maps-browser-only,
			.maps-standalone-only {
				display: none !important;
			}
			.maps-display-browser .maps-browser-only {
				display: block !important;
			}
			.maps-display-standalone .maps-standalone-only {
				display: block !important;
			}
		</style>
		<?php
	}

	public function enqueue_onesignal_sdk(): void {
		if ( ! CMA_Settings::is_app_page() ) {
			return;
		}

		$app_id = CMA_Settings::get_onesignal_app_id();
		if ( '' === $app_id ) {
			return;
		}

		$worker_script_url  = 'index.php/OneSignalSDKWorker.js';
		$worker_updater_url = 'index.php/OneSignalSDKUpdaterWorker.js';
		$scope_path         = wp_parse_url( CMA_Settings::get_app_url(), PHP_URL_PATH );
		if ( ! is_string( $scope_path ) || '' === $scope_path ) {
			$scope_path = CMA_Settings::get_app_path();
		} else {
			$scope_path = '/' . trim( $scope_path, '/' ) . '/';
		}

		$debug_mode     = CMA_Settings::is_onesignal_debug();
		$onesignal_page = CMA_Settings::get_public_origin() . '/?cma_os_proxy=page_es6';
		wp_enqueue_script(
			'maps-onesignal-sdk',
			$onesignal_page,
			[],
			CMA_VERSION,
			[
				'in_footer' => true,
			]
		);

		$inline_script  = "window.OneSignalDeferred = window.OneSignalDeferred || [];\n";
		$inline_script .= 'window.mapsOneSignalDebug = ' . ( $debug_mode ? 'true' : 'false' ) . ";\n";
		$inline_script .= "window.mapsOneSignalInitError = '';\n";
		$inline_script .= "window.mapsOneSignalScriptLoadState = 'pending';\n";
		$inline_script .= "window.addEventListener('error', function(event) {\n";
		$inline_script .= "\tvar target = event && event.target ? event.target : null;\n";
		$inline_script .= "\tif (target && target.tagName === 'SCRIPT' && target.src && (target.id === 'maps-onesignal-sdk-js' || target.src.indexOf('OneSignalSDK') !== -1)) {\n";
		$inline_script .= "\t\twindow.mapsOneSignalScriptLoadState = 'script_error';\n";
		$inline_script .= "\t\twindow.mapsOneSignalInitError = 'Failed to load OneSignal script: ' + target.src;\n";
		$inline_script .= "\t}\n";
		$inline_script .= "}, true);\n";
		$inline_script .= "window.addEventListener('unhandledrejection', function(event) {\n";
		$inline_script .= "\tif (window.mapsOneSignalDebug) {\n";
		$inline_script .= "\t\tvar reason = event && event.reason ? (event.reason.message || String(event.reason)) : 'unknown rejection';\n";
		$inline_script .= "\t\tconsole.error('[MAPS] Unhandled rejection', reason);\n";
		$inline_script .= "\t\tif (!window.mapsOneSignalInitError) { window.mapsOneSignalInitError = 'Unhandled rejection: ' + reason; }\n";
		$inline_script .= "\t}\n";
		$inline_script .= "});\n";
		$inline_script .= "window.mapsOneSignalReady = window.mapsOneSignalReady || new Promise(function(resolve, reject) {\n";
		$inline_script .= "\tvar mapsInitDone = false;\n";
		$inline_script .= "\tvar mapsInitTimer = window.setTimeout(function() {\n";
		$inline_script .= "\t\tif (mapsInitDone) { return; }\n";
		$inline_script .= "\t\twindow.mapsOneSignalInitError = 'OneSignal SDK did not initialize in time (likely blocked or not loaded).';\n";
		$inline_script .= "\t\tif (window.mapsOneSignalDebug) { console.error('[MAPS] OneSignal init timeout'); }\n";
		$inline_script .= "\t\treject(new Error('maps_onesignal_init_timeout'));\n";
		$inline_script .= "\t}, 10000);\n";
		$inline_script .= "OneSignalDeferred.push(async function(OneSignal) {\n";
		$inline_script .= "\ttry {\n";
		$inline_script .= "\t\tif (window.mapsOneSignal && window.mapsOneSignal === OneSignal) {\n";
		$inline_script .= "\t\t\tmapsInitDone = true;\n";
		$inline_script .= "\t\t\twindow.clearTimeout(mapsInitTimer);\n";
		$inline_script .= "\t\t\twindow.mapsOneSignalScriptLoadState = 'loaded';\n";
		$inline_script .= "\t\t\tif (window.mapsOneSignalDebug) { console.log('[MAPS] OneSignal already initialized; reusing instance'); }\n";
		$inline_script .= "\t\t\tresolve(OneSignal);\n";
		$inline_script .= "\t\t\treturn;\n";
		$inline_script .= "\t\t}\n";
		$inline_script .= "\t\tif ('serviceWorker' in navigator && navigator.serviceWorker.getRegistrations) {\n";
		$inline_script .= "\t\t\tvar registrations = await navigator.serviceWorker.getRegistrations();\n";
		$inline_script .= "\t\t\tfor (var i = 0; i < registrations.length; i++) {\n";
		$inline_script .= "\t\t\t\tvar reg = registrations[i];\n";
		$inline_script .= "\t\t\t\tvar scriptUrl = (reg.active && reg.active.scriptURL) || (reg.installing && reg.installing.scriptURL) || (reg.waiting && reg.waiting.scriptURL) || '';\n";
		$inline_script .= "\t\t\t\tif (scriptUrl.indexOf('maps_sw=1') !== -1 || scriptUrl.indexOf('cma_sw=1') !== -1) {\n";
		$inline_script .= "\t\t\t\t\tawait reg.unregister();\n";
		$inline_script .= "\t\t\t\t\tif (window.mapsOneSignalDebug) { console.log('[MAPS] Unregistered legacy app service worker', scriptUrl); }\n";
		$inline_script .= "\t\t\t\t}\n";
		$inline_script .= "\t\t\t}\n";
		$inline_script .= "\t\t}\n";
		$inline_script .= "\t\ttry {\n";
		$inline_script .= "\t\t\tawait OneSignal.init({\n";
		$inline_script .= "\t\t\t\tappId: '" . esc_js( $app_id ) . "',\n";
		$inline_script .= "\t\t\t\tserviceWorkerPath: '" . esc_js( $worker_script_url ) . "',\n";
		$inline_script .= "\t\t\t\tserviceWorkerUpdaterPath: '" . esc_js( $worker_updater_url ) . "',\n";
		$inline_script .= "\t\t\t\tserviceWorkerParam: { scope: '" . esc_js( $scope_path ) . "' },\n";
		$inline_script .= "\t\t\t\twelcomeNotification: { disable: true }\n";
		$inline_script .= "\t\t\t});\n";
		$inline_script .= "\t\t} catch (initError) {\n";
		$inline_script .= "\t\t\tvar msg = initError && initError.message ? initError.message : String(initError);\n";
		$inline_script .= "\t\t\tif (msg && msg.toLowerCase().indexOf('already initialized') !== -1) {\n";
		$inline_script .= "\t\t\t\tif (window.mapsOneSignalDebug) { console.warn('[MAPS] OneSignal init skipped (already initialized)', initError); }\n";
		$inline_script .= "\t\t\t} else {\n";
		$inline_script .= "\t\t\t\tthrow initError;\n";
		$inline_script .= "\t\t\t}\n";
		$inline_script .= "\t\t}\n";
		$inline_script .= "\t\tmapsInitDone = true;\n";
		$inline_script .= "\t\twindow.clearTimeout(mapsInitTimer);\n";
		$inline_script .= "\t\twindow.mapsOneSignal = OneSignal;\n";
		$inline_script .= "\t\twindow.mapsOneSignalScriptLoadState = 'loaded';\n";
		$inline_script .= "\t\tif (window.mapsOneSignalDebug) { console.log('[MAPS] OneSignal init success'); }\n";
		$inline_script .= "\t\tresolve(OneSignal);\n";
		$inline_script .= "\t} catch (error) {\n";
		$inline_script .= "\t\tmapsInitDone = true;\n";
		$inline_script .= "\t\twindow.clearTimeout(mapsInitTimer);\n";
		$inline_script .= "\t\twindow.mapsOneSignalInitError = error && error.message ? error.message : 'OneSignal init failed with unknown error.';\n";
		$inline_script .= "\t\tif (window.mapsOneSignalDebug) { console.error('[MAPS] OneSignal init failed', error); }\n";
		$inline_script .= "\t\treject(error);\n";
		$inline_script .= "\t}\n";
		$inline_script .= "});\n";
		$inline_script .= "});";
		wp_add_inline_script( 'maps-onesignal-sdk', $inline_script, 'after' );
	}
}
