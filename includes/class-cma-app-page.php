<?php

defined( 'ABSPATH' ) || exit;

class CMA_App_Page {

	public function __construct() {
		add_action( 'init', [ $this, 'maybe_ensure_page' ], 20 );
		add_filter( 'template_include', [ $this, 'use_app_template' ] );
		add_filter( 'body_class', [ $this, 'body_class' ] );
		add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_app_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'strip_theme_chrome' ], 100 );
	}

	/**
	 * Swap the theme template for a header/footer-free canvas.
	 */
	public function use_app_template( string $template ): string {
		if ( ! CMA_Settings::is_app_page() ) {
			return $template;
		}

		$app_template = CMA_PATH . 'templates/app-page.php';

		return file_exists( $app_template ) ? $app_template : $template;
	}

	/**
	 * @param string[] $classes Body classes.
	 * @return string[]
	 */
	public function body_class( array $classes ): array {
		if ( CMA_Settings::is_app_page() ) {
			$classes[] = 'cma-app-page';
		}

		return $classes;
	}

	/**
	 * Load Bootstrap 4 + Font Awesome on the app page only.
	 *
	 * The tab shell uses Bootstrap nav-tabs and Font Awesome icons. With the
	 * theme chrome removed, those assets are no longer guaranteed by the theme.
	 */
	public function enqueue_app_assets(): void {
		if ( ! CMA_Settings::is_app_page() ) {
			return;
		}

		$this->enqueue_bootstrap();
		$this->enqueue_font_awesome();

		wp_enqueue_style(
			'cma-app-shell',
			CMA_URL . 'assets/css/app-shell.css',
			[ $this->bootstrap_css_handle() ],
			CMA_VERSION
		);

		wp_enqueue_script(
			'cma-app-shell',
			CMA_URL . 'assets/js/app-shell.js',
			[ 'jquery' ],
			CMA_VERSION,
			true
		);

		$colors = CMA_Settings::get_app_colors();
		wp_add_inline_style(
			'cma-app-shell',
			sprintf(
				'body.cma-app-page{--cma-color-primary:%1$s;--cma-color-secondary:%2$s;--cma-color-button-text:%3$s;}',
				esc_attr( $colors['primary'] ),
				esc_attr( $colors['secondary'] ),
				esc_attr( $colors['button_text'] )
			)
		);
	}

	/**
	 * Hide the WordPress admin bar so the canvas feels like an app.
	 *
	 * @param bool $show Whether to show the admin bar.
	 */
	public function hide_admin_bar( bool $show ): bool {
		if ( CMA_Settings::is_app_page() ) {
			return false;
		}

		return $show;
	}

	/**
	 * Drop theme / storefront CSS that fights the app chrome.
	 */
	public function strip_theme_chrome(): void {
		if ( ! CMA_Settings::is_app_page() ) {
			return;
		}

		$handles = [
			'wp-block-library',
			'wp-block-library-theme',
			'global-styles',
			'classic-theme-styles',
			'twentytwentyfive-style',
			'twentytwentyfour-style',
			'twentytwentythree-style',
			'woocommerce-general',
			'woocommerce-layout',
			'woocommerce-smallscreen',
			'woocommerce-blocktheme',
			'wc-blocks-style',
			'mediaelement',
			'wp-mediaelement',
		];

		foreach ( $handles as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}

	private function enqueue_bootstrap(): void {
		if ( $this->style_available( [ 'bootstrap', 'bootstrap-css', 'toolset-bootstrap', 'toolset-bootstrap-css', 'toolset_bootstrap_4' ] ) ) {
			$this->maybe_enqueue_existing_style( [ 'bootstrap', 'bootstrap-css', 'toolset-bootstrap', 'toolset-bootstrap-css', 'toolset_bootstrap_4' ] );
		} else {
			wp_enqueue_style(
				'bootstrap',
				'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css',
				[],
				'4.6.2'
			);
		}

		if ( $this->script_available( [ 'bootstrap-js', 'toolset-bootstrap-js', 'bootstrap' ] ) ) {
			$this->maybe_enqueue_existing_script( [ 'bootstrap-js', 'toolset-bootstrap-js', 'bootstrap' ] );
		} else {
			wp_enqueue_script(
				'bootstrap-js',
				'https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js',
				[ 'jquery' ],
				'4.6.2',
				true
			);
		}
	}

	private function enqueue_font_awesome(): void {
		if ( $this->style_available( [ 'font-awesome', 'fontawesome', 'font-awesome-4', 'toolset-font-awesome' ] ) ) {
			$this->maybe_enqueue_existing_style( [ 'font-awesome', 'fontawesome', 'font-awesome-4', 'toolset-font-awesome' ] );
			return;
		}

		wp_enqueue_style(
			'font-awesome',
			'https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css',
			[],
			'4.7.0'
		);
	}

	private function bootstrap_css_handle(): string {
		foreach ( [ 'bootstrap', 'bootstrap-css', 'toolset-bootstrap', 'toolset-bootstrap-css', 'toolset_bootstrap_4' ] as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'registered' ) ) {
				return $handle;
			}
		}

		return 'bootstrap';
	}

	/**
	 * @param string[] $handles Style handles.
	 */
	private function style_available( array $handles ): bool {
		foreach ( $handles as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'registered' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[] $handles Script handles.
	 */
	private function script_available( array $handles ): bool {
		foreach ( $handles as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'registered' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[] $handles Style handles.
	 */
	private function maybe_enqueue_existing_style( array $handles ): void {
		foreach ( $handles as $handle ) {
			if ( wp_style_is( $handle, 'enqueued' ) || wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
				return;
			}
		}
	}

	/**
	 * @param string[] $handles Script handles.
	 */
	private function maybe_enqueue_existing_script( array $handles ): void {
		foreach ( $handles as $handle ) {
			if ( wp_script_is( $handle, 'enqueued' ) || wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
				return;
			}
		}
	}

	public function maybe_ensure_page(): void {
		if ( absint( get_option( CMA_Settings::OPTION_PAGE_ID, 0 ) ) > 0 ) {
			return;
		}

		self::ensure_page();
	}

	/**
	 * Create the mobile app page when missing.
	 */
	public static function ensure_page(): int {
		$page_id = absint( get_option( CMA_Settings::OPTION_PAGE_ID, 0 ) );
		if ( $page_id > 0 ) {
			$page = get_post( $page_id );
			if ( $page instanceof WP_Post && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
				return $page_id;
			}
		}

		$slug = CMA_Settings::get_app_slug();
		$by_path = get_page_by_path( $slug );
		if ( $by_path instanceof WP_Post ) {
			update_option( CMA_Settings::OPTION_PAGE_ID, (int) $by_path->ID );
			update_option( CMA_Settings::OPTION_SLUG, $by_path->post_name );
			return (int) $by_path->ID;
		}

		$page_id = wp_insert_post(
			[
				'post_title'   => __( 'App', 'cem-mobile-app' ),
				'post_name'    => $slug,
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[cem_mobile_app]',
			],
			true
		);

		if ( is_wp_error( $page_id ) || $page_id <= 0 ) {
			return 0;
		}

		update_option( CMA_Settings::OPTION_PAGE_ID, (int) $page_id );
		update_option( CMA_Settings::OPTION_SLUG, $slug );

		return (int) $page_id;
	}

	/**
	 * Apply a new slug to settings and the app page.
	 */
	public static function sync_slug( string $slug ): void {
		$slug     = CMA_Settings::sanitize_slug( $slug );
		$old_slug = CMA_Settings::get_app_slug();
		$page_id  = self::ensure_page();

		update_option( CMA_Settings::OPTION_SLUG, $slug );

		if ( $page_id > 0 && $slug !== $old_slug ) {
			wp_update_post(
				[
					'ID'        => $page_id,
					'post_name' => $slug,
				]
			);
			CMA_PWA::register_rewrite_rules();
			flush_rewrite_rules( false );
			CMA_Settings::bump_sw_cache_token();
		}
	}
}
