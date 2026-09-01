<?php

defined( 'ABSPATH' ) || exit;

class CMA_Shortcodes {

	public function __construct() {
		add_shortcode( 'cem_mobile_app', [ $this, 'render_app_shell' ] );
		add_shortcode( 'browser_only', [ $this, 'browser_only' ] );
		add_shortcode( 'standalone_only', [ $this, 'standalone_only' ] );
		add_shortcode( 'app_notifications', [ $this, 'recent_notifications' ] );
		add_shortcode( 'app_notifications_enable', [ $this, 'notifications_enable' ] );
		add_shortcode( 'app_home_screen_message', [ $this, 'home_screen_message' ] );
		add_shortcode( 'app_home_screen_images', [ $this, 'home_screen_images' ] );
		add_shortcode( 'app_install_button', [ $this, 'install_button' ] );
	}

	public function browser_only( $atts, $content = null ): string {
		unset( $atts );
		if ( null === $content ) {
			return '';
		}

		return '<div class="maps-browser-only">' . do_shortcode( $content ) . '</div>';
	}

	public function standalone_only( $atts, $content = null ): string {
		unset( $atts );
		if ( null === $content ) {
			return '';
		}

		return '<div class="maps-standalone-only">' . do_shortcode( $content ) . '</div>';
	}

	public function render_app_shell(): string {
		$schedule_id = CMA_Settings::get_schedule_id();

		if ( $schedule_id <= 0 ) {
			return $this->render_no_schedule_state();
		}

		$schedule   = '[cem_schedule id="' . absint( $schedule_id ) . '"]';
		$sponsors   = '[cem_schedule_sponsors id="' . absint( $schedule_id ) . '"]';
		$home_title = CMA_Settings::get_event_title();
		if ( '' === $home_title ) {
			$home_title = (string) get_bloginfo( 'name' );
		}
		$has_maps = [] !== CMA_Settings::get_home_screen_image_ids();

		ob_start();
		?>
		<div class="cma-app-shell">
			<?php echo $this->render_brand_header( $home_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
			<div class="cma-app-body">
				<div class="tab-content" id="eventTabContent">
					<div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
						[browser_only]<button type="button" class="cma-app-cta" onclick="document.querySelector('#apps-tab').click()"><i class="fa fa-mobile" aria-hidden="true"></i> <?php esc_html_e( 'Save to Home Screen', 'cem-mobile-app' ); ?></button>[/browser_only]
						[app_home_screen_message]
						<section class="cma-app-section">
							<h2 class="cma-app-section-title"><?php esc_html_e( 'Event App Features', 'cem-mobile-app' ); ?></h2>
							<ul class="cma-app-features">
								<li>
									<button type="button" class="cma-app-features__btn" onclick="document.querySelector('#schedule-tab').click()">
										<span class="cma-app-features__icon"><i class="fa fa-calendar" aria-hidden="true"></i></span>
										<span class="cma-app-features__label"><?php esc_html_e( 'Event Schedule', 'cem-mobile-app' ); ?></span>
										<i class="fa fa-chevron-right cma-app-features__chevron" aria-hidden="true"></i>
									</button>
								</li>
								<li>
									<button type="button" class="cma-app-features__btn" onclick="document.querySelector('#notes-tab').click()">
										<span class="cma-app-features__icon"><i class="fa fa-sticky-note-o" aria-hidden="true"></i></span>
										<span class="cma-app-features__label"><?php esc_html_e( 'Attendee Notes', 'cem-mobile-app' ); ?></span>
										<i class="fa fa-chevron-right cma-app-features__chevron" aria-hidden="true"></i>
									</button>
								</li>
								<li>
									<button type="button" class="cma-app-features__btn" onclick="document.querySelector('#notifications-tab').click()">
										<span class="cma-app-features__icon"><i class="fa fa-bell" aria-hidden="true"></i></span>
										<span class="cma-app-features__label"><?php esc_html_e( 'Event Notifications', 'cem-mobile-app' ); ?></span>
										<i class="fa fa-chevron-right cma-app-features__chevron" aria-hidden="true"></i>
									</button>
								</li>
								<?php if ( $has_maps ) : ?>
								<li>
									<button type="button" class="cma-app-features__btn" onclick="document.querySelector('#map-tab').click()">
										<span class="cma-app-features__icon"><i class="fa fa-map" aria-hidden="true"></i></span>
										<span class="cma-app-features__label"><?php esc_html_e( 'Event Maps', 'cem-mobile-app' ); ?></span>
										<i class="fa fa-chevron-right cma-app-features__chevron" aria-hidden="true"></i>
									</button>
								</li>
								<?php endif; ?>
							</ul>
						</section>
						<section class="cma-app-section cma-app-section--sponsors">
							<h2 class="cma-app-section-title"><?php esc_html_e( 'Event Sponsors', 'cem-mobile-app' ); ?></h2>
							<div class="cma-app-card">
								<?php echo $sponsors; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode markup. ?>
							</div>
						</section>
					</div>
					<div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
						<?php echo $schedule; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode markup. ?>
					</div>
					<div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
						[sepa_attendee_notes]
					</div>
					<div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
						[app_notifications live_refresh="yes"]
						[app_notifications_enable label="Turn On Alerts"]
						<p class="cma-app-hint"><?php esc_html_e( 'Notifications only work on Android and iPhone after you add this app to the home screen.', 'cem-mobile-app' ); ?></p>
					</div>
					<?php if ( $has_maps ) : ?>
					<div class="tab-pane fade" id="map" role="tabpanel" aria-labelledby="map-tab">
						[app_home_screen_images size="large" columns="2"]
					</div>
					<?php endif; ?>
					<div class="tab-pane fade" id="apps" role="tabpanel" aria-labelledby="apps-tab">
						[app_install_button label_android="Install App" label_ios="Add on iPhone"]
					</div>
				</div>
			</div>
			<nav class="cma-app-nav-wrap" aria-label="<?php esc_attr_e( 'App', 'cem-mobile-app' ); ?>">
				<ul class="nav nav-tabs cma-app-nav" id="eventTabs" role="tablist">
					<li class="nav-item">
						<a class="nav-link active" id="home-tab" data-toggle="tab" data-cma-title="<?php echo esc_attr( $home_title ); ?>" href="#home" role="tab" aria-controls="home" aria-selected="true"><i class="fa fa-home" aria-hidden="true"></i><span class="cma-app-nav-label"><?php esc_html_e( 'Home', 'cem-mobile-app' ); ?></span></a>
					</li>
					<li class="nav-item">
						<a class="nav-link" id="schedule-tab" data-toggle="tab" data-cma-title="<?php esc_attr_e( 'Schedule', 'cem-mobile-app' ); ?>" href="#schedule" role="tab" aria-controls="schedule" aria-selected="false"><i class="fa fa-calendar" aria-hidden="true"></i><span class="cma-app-nav-label"><?php esc_html_e( 'Schedule', 'cem-mobile-app' ); ?></span></a>
					</li>
					<li class="nav-item">
						<a class="nav-link" id="notes-tab" data-toggle="tab" data-cma-title="<?php esc_attr_e( 'Notes', 'cem-mobile-app' ); ?>" href="#notes" role="tab" aria-controls="notes" aria-selected="false"><i class="fa fa-sticky-note-o" aria-hidden="true"></i><span class="cma-app-nav-label"><?php esc_html_e( 'Notes', 'cem-mobile-app' ); ?></span></a>
					</li>
					<?php if ( $has_maps ) : ?>
					<li class="nav-item">
						<a class="nav-link" id="map-tab" data-toggle="tab" data-cma-title="<?php esc_attr_e( 'Map', 'cem-mobile-app' ); ?>" href="#map" role="tab" aria-controls="map" aria-selected="false"><i class="fa fa-map" aria-hidden="true"></i><span class="cma-app-nav-label"><?php esc_html_e( 'Map', 'cem-mobile-app' ); ?></span></a>
					</li>
					<?php endif; ?>
					<li class="nav-item">
						<a class="nav-link" id="notifications-tab" data-toggle="tab" data-cma-title="<?php esc_attr_e( 'Alerts', 'cem-mobile-app' ); ?>" href="#notifications" role="tab" aria-controls="notifications" aria-selected="false"><i class="fa fa-bell" aria-hidden="true"></i><span class="cma-app-nav-label"><?php esc_html_e( 'Alerts', 'cem-mobile-app' ); ?></span></a>
					</li>
					<li class="nav-item maps-browser-only">
						<a class="nav-link" id="apps-tab" data-toggle="tab" data-cma-title="<?php esc_attr_e( 'Install', 'cem-mobile-app' ); ?>" href="#apps" role="tab" aria-controls="apps" aria-selected="false"><i class="fa fa-th" aria-hidden="true"></i><span class="cma-app-nav-label"><?php esc_html_e( 'Install', 'cem-mobile-app' ); ?></span></a>
					</li>
				</ul>
			</nav>
		</div>
		<style>
			#mapLightbox.modal { background: rgba(0,0,0,.85); }
		</style>
		<?php
		return do_shortcode( (string) ob_get_clean() );
	}

	private function render_brand_header( string $title ): string {
		$logo_id  = CMA_Settings::get_header_logo_id();
		$logo_url = $logo_id ? CMA_Settings::get_app_image_url( $logo_id ) : '';

		if ( '' === $logo_url && '' === $title ) {
			return '';
		}

		ob_start();
		?>
		<header class="cma-app-header cma-app-brand">
			<?php if ( '' !== $logo_url ) : ?>
				<div class="cma-app-brand-logo">
					<img class="cma-app-brand-logo-img" src="<?php echo esc_url( $logo_url ); ?>" alt="" />
				</div>
			<?php endif; ?>
			<?php if ( '' !== $title ) : ?>
				<h1 class="cma-app-brand-title"><?php echo esc_html( $title ); ?></h1>
			<?php endif; ?>
		</header>
		<?php

		return (string) ob_get_clean();
	}

	private function render_no_schedule_state(): string {
		$message = CMA_Settings::get_no_schedule_message();

		ob_start();
		?>
		<div class="cma-app-shell cma-app-shell--no-schedule">
			<?php echo $this->render_brand_header( '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper. ?>
			<div class="cma-app-body">
				<div class="cma-no-schedule-message">
					<?php echo wp_kses_post( wpautop( $message ) ); ?>
				</div>
				[browser_only]
				<div class="cma-no-schedule-install">
					[app_install_button label_android="Install App" label_ios="Add on iPhone"]
				</div>
				[/browser_only]
			</div>
		</div>
		<?php
		return do_shortcode( (string) ob_get_clean() );
	}

	public function recent_notifications( $atts ): string {
		$atts = shortcode_atts(
			[
				'limit'           => 5,
				'show_date'       => 'yes',
				'local_time'      => 'yes',
				'live_refresh'    => 'no',
				'refresh_seconds' => 30,
			],
			$atts,
			'app_notifications'
		);

		$limit      = max( 1, min( 50, absint( $atts['limit'] ) ) );
		$show_date  = in_array( strtolower( (string) $atts['show_date'] ), [ '1', 'yes', 'true' ], true );
		$local_time = ! in_array( strtolower( (string) $atts['local_time'] ), [ '0', 'no', 'false' ], true );
		$live       = in_array( strtolower( (string) $atts['live_refresh'] ), [ '1', 'yes', 'true' ], true );
		$interval   = max( 10, min( 300, absint( $atts['refresh_seconds'] ) ) );

		if ( $show_date && $local_time ) {
			wp_enqueue_script(
				'maps-notifications-time',
				CMA_URL . 'assets/js/notifications-time.js',
				[],
				CMA_VERSION,
				true
			);
		}

		if ( $live ) {
			$live_deps = ( $show_date && $local_time ) ? [ 'maps-notifications-time' ] : [];
			wp_enqueue_script(
				'maps-notifications-live',
				CMA_URL . 'assets/js/notifications-live.js',
				$live_deps,
				CMA_VERSION,
				true
			);
			wp_localize_script(
				'maps-notifications-live',
				'CMANotificationsLive',
				[
					'restUrl' => rest_url( 'cma/v1/recent-notifications' ),
				]
			);
		}

		$content  = CMA_Notifications::get_recent_markup( $limit, $show_date, $local_time );
		$output   = '<div class="maps-app-notifications-recent" data-live="' . esc_attr( $live ? 'yes' : 'no' ) . '" data-limit="' . esc_attr( (string) $limit ) . '" data-show-date="' . esc_attr( $show_date ? 'yes' : 'no' ) . '" data-local-time="' . esc_attr( $local_time ? 'yes' : 'no' ) . '" data-refresh-seconds="' . esc_attr( (string) $interval ) . '">';
		$output  .= $content;
		$output  .= '</div>';

		return $output;
	}

	public function notifications_enable( $atts ): string {
		$atts = shortcode_atts(
			[
				'label' => __( 'Enable Notifications', 'cem-mobile-app' ),
			],
			$atts,
			'app_notifications_enable'
		);

		wp_enqueue_script(
			'maps-notifications-optin',
			CMA_URL . 'assets/js/notifications-optin.js',
			[ 'maps-onesignal-sdk' ],
			CMA_VERSION,
			true
		);
		wp_localize_script(
			'maps-notifications-optin',
			'MAPSNotificationsOptin',
			[
				'debugMode' => CMA_Settings::is_onesignal_debug(),
			]
		);

		$output  = '<div class="maps-notifications-optin">';
		$output .= '<button type="button" class="maps-enable-notifications-button btn btn-sm btn-primary">' . esc_html( $atts['label'] ) . '</button>';
		$output .= '<p class="maps-enable-notifications-ios-hint" hidden>' . esc_html__( 'iPhone: open this from your Home Screen app (not Safari tab) to enable notifications.', 'cem-mobile-app' ) . '</p>';
		$output .= '<p class="maps-enable-notifications-message" aria-live="polite"></p>';
		$output .= '<p class="maps-enable-notifications-debug" aria-live="polite"></p>';
		if ( CMA_Settings::is_onesignal_debug() ) {
			$output .= '<pre class="maps-notifications-env-debug" aria-live="polite"></pre>';
		}
		$output .= '</div>';

		return $output;
	}

	public function home_screen_message(): string {
		$message = trim( (string) get_option( 'maps_home_screen_message', '' ) );
		if ( '' === $message ) {
			return '';
		}

		return '<div class="maps-app-home-screen-message">' . do_shortcode( wpautop( $message ) ) . '</div>';
	}

	public function home_screen_images( $atts ): string {
		$atts = shortcode_atts(
			[
				'size'    => 'medium',
				'columns' => 3,
			],
			$atts,
			'app_home_screen_images'
		);

		$ids = CMA_Settings::get_home_screen_image_ids();
		if ( empty( $ids ) ) {
			return '';
		}

		$size    = sanitize_key( $atts['size'] );
		$columns = max( 1, min( 6, absint( $atts['columns'] ) ) );
		$allowed = [ 'thumbnail', 'medium', 'medium_large', 'large', 'full' ];
		if ( ! in_array( $size, $allowed, true ) ) {
			$size = 'medium';
		}

		wp_enqueue_style(
			'maps-home-screen-images',
			CMA_URL . 'assets/css/home-screen-images.css',
			[],
			CMA_VERSION
		);
		wp_enqueue_script(
			'maps-home-screen-images',
			CMA_URL . 'assets/js/home-screen-images.js',
			[],
			CMA_VERSION,
			true
		);

		$uid    = 'maps-home-images-' . wp_unique_id();
		$output = '<div class="maps-home-images" id="' . esc_attr( $uid ) . '" style="--maps-home-images-cols:' . esc_attr( (string) $columns ) . '">';
		$output .= '<div class="maps-home-images__grid">';

		foreach ( $ids as $image_id ) {
			$file_url = CMA_Settings::get_app_image_url( $image_id );
			$thumb    = wp_get_attachment_image_src( $image_id, $size );
			$full     = wp_get_attachment_image_src( $image_id, 'large' );
			$thumb_url = ( $thumb && ! empty( $thumb[0] ) && (int) ( $thumb[1] ?? 0 ) > 1 ) ? $thumb[0] : $file_url;
			$full_url  = ( $full && ! empty( $full[0] ) ) ? $full[0] : $file_url;
			if ( ! $thumb_url ) {
				continue;
			}
			$alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			$alt = is_string( $alt ) ? $alt : '';

			$output .= '<div class="maps-home-images__item">';
			$output .= '<button type="button" class="maps-home-images__thumb" data-full="' . esc_url( $full_url ) . '">';
			$output .= '<img src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" />';
			$output .= '</button>';
			$output .= '</div>';
		}

		$output .= '</div>';
		$output .= '<div class="maps-home-images-modal" hidden>';
		$output .= '<div class="maps-home-images-modal__backdrop" aria-hidden="true"></div>';
		$output .= '<figure class="maps-home-images-modal__dialog" role="dialog" aria-modal="true" aria-label="' . esc_attr__( 'Image preview', 'cem-mobile-app' ) . '">';
		$output .= '<button type="button" class="maps-home-images-modal__close" aria-label="' . esc_attr__( 'Close', 'cem-mobile-app' ) . '">&times;</button>';
		$output .= '<img class="maps-home-images-modal__img" src="" alt="" />';
		$output .= '</figure>';
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	public function install_button( $atts ): string {
		$atts = shortcode_atts(
			[
				'label_android' => __( 'Install App', 'cem-mobile-app' ),
				'label_ios'     => __( 'Add to Home Screen', 'cem-mobile-app' ),
			],
			$atts,
			'app_install_button'
		);

		wp_enqueue_script(
			'maps-install-prompt',
			CMA_URL . 'assets/js/install-prompt.js',
			[],
			CMA_VERSION . '.' . filemtime( CMA_PATH . 'assets/js/install-prompt.js' ),
			true
		);

		$app_url           = CMA_Settings::get_app_url();
		$app_url_display   = preg_replace( '#^https?:#', '', $app_url );
		$safari_guide_html = $this->safari_guide_html( $app_url, (string) $app_url_display );

		$output  = '<div class="maps-install-app" data-label-android="' . esc_attr( $atts['label_android'] ) . '" data-label-ios="' . esc_attr( $atts['label_ios'] ) . '">';
		$output .= '<button type="button" class="maps-install-app-button btn btn-sm btn-primary">' . esc_html( $atts['label_android'] ) . '</button>';
		$output .= '<p class="maps-install-app-message" aria-live="polite"></p>';
		$output .= '<p class="maps-install-ios-hint maps-install-ios-only" hidden>';
		$output .= esc_html(
			sprintf(
				/* translators: %s: app URL */
				__( 'iPhone: open %s in Safari, then use Share > Add to Home Screen.', 'cem-mobile-app' ),
				$app_url_display
			)
		);
		$output .= '</p>';
		$output .= '<p class="maps-install-ios-only" hidden><button type="button" class="maps-copy-app-url-button btn btn-sm btn-outline-secondary" data-app-url="' . esc_attr( $app_url ) . '">' . esc_html__( 'Copy app URL', 'cem-mobile-app' ) . '</button></p>';
		$output .= '<div class="maps-install-safari-guide maps-install-safari-only" hidden>';
		$output .= $safari_guide_html;
		$output .= '</div>';
		$output .= '</div>';

		return $output;
	}

	private function safari_guide_html( string $app_url, string $app_url_display ): string {
		unset( $app_url );

		ob_start();
		?>
<style>
	.maps-install-safari-guide {
		max-width: 680px;
		margin: 1rem auto 0;
		background: #ffffff;
		border: 2px solid #111111;
		padding: 0;
		font-family: "IBM Plex Sans", sans-serif;
		font-size: 0.92rem;
		color: #111111;
	}
	.maps-install-safari-guide * { box-sizing: border-box; }
	.maps-install-safari-guide .guide-header {
		border-bottom: 2px solid #111111;
		padding: 1.25rem 1.5rem 1rem;
		text-align: center;
	}
	.maps-install-safari-guide .guide-header h1 {
		font-family: "IBM Plex Mono", monospace;
		font-weight: 700;
		font-size: 1.45rem;
		letter-spacing: -0.02em;
		margin-bottom: 0.4rem;
		color: #111111;
	}
	.maps-install-safari-guide .compatibility-bar { font-size: 0.78rem; color: #555555; letter-spacing: 0.04em; }
	.maps-install-safari-guide .compatibility-bar span { margin: 0 0.4rem; color: #888888; }
	.maps-install-safari-guide .step-block { border-bottom: 1.5px solid #bbbbbb; padding: 1.25rem 1.5rem; }
	.maps-install-safari-guide .step-block:last-of-type { border-bottom: none; }
	.maps-install-safari-guide .step-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
	.maps-install-safari-guide .step-number {
		width: 32px; height: 32px; background: #111111; color: #ffffff; border-radius: 50%;
		display: flex; align-items: center; justify-content: center;
		font-family: "IBM Plex Mono", monospace; font-weight: 700; font-size: 1rem; flex-shrink: 0;
	}
	.maps-install-safari-guide .step-title { font-family: "IBM Plex Mono", monospace; font-weight: 700; font-size: 1.1rem; margin: 0; }
	.maps-install-safari-guide .step-desc { color: #2a2a2a; margin-bottom: 1rem; line-height: 1.55; }
	.maps-install-safari-guide .guide-row { display: flex; gap: 0.75rem; align-items: flex-start; flex-wrap: wrap; }
	.maps-install-safari-guide .phone-mockup {
		width: 90px; border: 2.5px solid #111111; border-radius: 10px; background: #f0f0f0; padding: 6px 5px; flex-shrink: 0;
	}
	.maps-install-safari-guide .phone-screen {
		background: #ffffff; border: 1.5px solid #cccccc; border-radius: 4px; height: 110px;
		display: flex; align-items: flex-start; padding: 4px 5px; font-size: 0.55rem; color: #555555;
		font-family: "IBM Plex Mono", monospace; overflow: hidden; word-break: break-all;
	}
	.maps-install-safari-guide .phone-home-btn { width: 22px; height: 4px; background: #cccccc; border-radius: 2px; margin: 5px auto 2px; }
	.maps-install-safari-guide .phone-toolbar { display: flex; justify-content: center; gap: 8px; padding: 4px 4px 2px; }
	.maps-install-safari-guide .toolbar-icon { width: 18px; height: 18px; }
	.maps-install-safari-guide .info-box {
		border: 1.5px solid #111111; background: #f0f0f0; padding: 0.6rem 0.85rem; font-size: 0.78rem;
		line-height: 1.5; color: #2a2a2a; flex: 1; min-width: 260px;
	}
	.maps-install-safari-guide .info-box-title { font-family: "IBM Plex Mono", monospace; font-weight: 700; font-size: 0.78rem; margin-bottom: 0.3rem; color: #111111; }
	.maps-install-safari-guide .info-box p { margin: 0; }
	.maps-install-safari-guide .info-box p + p { margin-top: 0.2rem; }
	.maps-install-safari-guide .share-sheet { border: 1.5px solid #111111; background: #ffffff; font-size: 0.78rem; width: 200px; }
	.maps-install-safari-guide .share-sheet-header { background: #f0f0f0; border-bottom: 1px solid #bbbbbb; padding: 0.3rem 0.65rem; font-size: 0.7rem; color: #555555; font-family: "IBM Plex Mono", monospace; }
	.maps-install-safari-guide .share-sheet-item { padding: 0.4rem 0.65rem; border-bottom: 1px solid #f0f0f0; color: #2a2a2a; display: flex; align-items: center; gap: 0.5rem; }
	.maps-install-safari-guide .share-sheet-item.highlight { background: #111111; color: #ffffff; font-weight: 600; border: none; }
	.maps-install-safari-guide .item-icon { font-size: 1rem; width: 18px; text-align: center; }
	.maps-install-safari-guide .after-tap-box {
		border: 1.5px solid #111111; background: #f0f0f0; padding: 0.6rem 0.85rem; font-size: 0.78rem;
		line-height: 1.6; color: #2a2a2a; flex: 1; min-width: 260px;
	}
	.maps-install-safari-guide .after-tap-box .info-box-title { margin-bottom: 0.4rem; }
	.maps-install-safari-guide .after-tap-box p { margin: 0; padding: 0.2rem 0; border-bottom: 1px dashed #bbbbbb; }
	.maps-install-safari-guide .after-tap-box p:last-child { border-bottom: none; }
	.maps-install-safari-guide .arrow-label { font-family: "IBM Plex Mono", monospace; font-size: 0.72rem; color: #555555; margin-top: 0.5rem; }
	.maps-install-safari-guide .guide-footer { border-top: 2px solid #111111; padding: 0.85rem 1.5rem; text-align: center; font-size: 0.78rem; color: #555555; background: #f0f0f0; }
	.maps-install-safari-guide .guide-footer strong { font-family: "IBM Plex Mono", monospace; color: #111111; font-size: 0.82rem; }
</style>
<div class="guide-header">
	<h1>How to Add This Page to Your iPhone Home Screen</h1>
	<div class="compatibility-bar">Works on all iPhones <span>&bull;</span> iOS 12 and newer <span>&bull;</span> Safari only</div>
</div>
<div class="step-block">
	<div class="step-header"><div class="step-number">1</div><h2 class="step-title">Open the page in Safari</h2></div>
	<p class="step-desc">Safari is Apple&apos;s built-in browser &mdash; the blue compass icon.<br>Chrome, Firefox, and Edge will <strong>NOT</strong> show the Add option.</p>
	<div class="guide-row">
		<div class="phone-mockup">
			<div class="phone-screen"><?php echo esc_html( $app_url_display ); ?></div>
			<div class="phone-home-btn"></div>
		</div>
		<div class="info-box">
			<div class="info-box-title">iOS version notes:</div>
			<p>iOS 12&ndash;14: Safari icon is on your home screen by default.</p>
			<p>iOS 15+: Safari may be in your App Library &mdash; swipe left on all home screen pages to find it.</p>
			<p><em>Tip: Type <?php echo esc_html( $app_url_display ); ?> directly into Safari&apos;s address bar.</em></p>
		</div>
	</div>
</div>
<div class="step-block">
	<div class="step-header"><div class="step-number">2</div><h2 class="step-title">Tap the Share button</h2></div>
	<p class="step-desc">Look for a box with an upward arrow &mdash; it is always at the bottom center of the Safari screen.</p>
	<div class="guide-row">
		<div class="phone-mockup">
			<div class="phone-screen" style="height:95px;"></div>
			<div class="phone-toolbar">
				<svg class="toolbar-icon" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
				<svg class="toolbar-icon" viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="2.5"><rect x="5" y="9" width="14" height="12" rx="1"/><polyline points="12 2 12 13"/><polyline points="9 5 12 2 15 5"/></svg>
				<svg class="toolbar-icon" viewBox="0 0 24 24" fill="none" stroke="#555" stroke-width="2.5"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
			</div>
			<div class="phone-home-btn"></div>
		</div>
		<div class="info-box">
			<div class="info-box-title">Where to find it:</div>
			<p>iOS 12&ndash;14: Bottom toolbar, center button (box + arrow).</p>
			<p>iOS 15+: Same location &mdash; bottom center of Safari.</p>
			<p>iOS 17+: May also appear if you tap the address bar area and scroll right in the toolbar options.</p>
			<p><em>If toolbar is hidden: scroll up the page to reveal it.</em></p>
		</div>
	</div>
</div>
<div class="step-block">
	<div class="step-header"><div class="step-number">3</div><h2 class="step-title">Tap "Add to Home Screen"</h2></div>
	<p class="step-desc">A menu slides up from the bottom. Scroll down until "Add to Home Screen" appears, then tap it.</p>
	<div class="guide-row">
		<div>
			<div class="share-sheet">
				<div class="share-sheet-header">Share Sheet (scroll down to find option)</div>
				<div class="share-sheet-item"><span class="item-icon">&#128279;</span> Copy Link</div>
				<div class="share-sheet-item highlight"><span class="item-icon">&#8853;</span> Add to Home Screen</div>
				<div class="share-sheet-item"><span class="item-icon">&#128278;</span> Add Bookmark</div>
			</div>
			<div class="arrow-label">&rarr; tap to proceed</div>
		</div>
		<div class="after-tap-box">
			<div class="info-box-title">After tapping Add:</div>
			<p>A name field appears &mdash; you can shorten it if you wish.</p>
			<p>Tap "Add" (top right corner).</p>
			<p>The icon now appears on your home screen like a real app.</p>
			<p>Tap it any time for quick access.</p>
		</div>
	</div>
</div>
<div class="guide-footer">
	<p class="mb-1">This only needs to be done once per device.</p>
	<strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
</div>
		<?php
		return (string) ob_get_clean();
	}
}
