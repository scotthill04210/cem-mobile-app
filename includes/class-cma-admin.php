<?php

defined( 'ABSPATH' ) || exit;

class CMA_Admin {

	public const TAB_ID              = 'mobile-app';
	public const PANEL_ID            = 'app-content';
	public const TAB_EVENT_DETAILS   = 'cma-event-details';
	public const TAB_NOTIFICATIONS   = 'cma-app-notifications';

	public function __construct() {
		add_filter( 'cem_settings_tabs', [ $this, 'register_tab_slug' ] );
		add_filter( 'cem_settings_nav_tabs', [ $this, 'register_nav_tab' ] );
		add_filter( 'cem_settings_tab_html', [ $this, 'render_tab' ], 10, 2 );
		add_filter( 'cem_save_settings_tab', [ $this, 'save_tab' ], 10, 2 );
		add_filter( 'cem_sidebar_nav_items', [ $this, 'register_sidebar_item' ] );
		add_filter( 'cem_panels', [ $this, 'register_panel' ] );
		add_filter( 'cem_panel_html', [ $this, 'render_panel' ], 10, 2 );
		add_filter( 'cem_documentation_tabs', [ $this, 'register_documentation_tab' ] );
		add_filter( 'cem_documentation_tab_html', [ $this, 'render_documentation_tab' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 100 );
		add_action( 'wp_footer', [ $this, 'enqueue_assets' ], 5 );
		add_action( 'admin_footer', [ $this, 'enqueue_assets' ], 5 );
		add_action( 'admin_notices', [ $this, 'maybe_notice' ] );
		add_action( 'wp_ajax_cma_upload_image', [ $this, 'ajax_upload_image' ] );
		add_action( 'wp_ajax_cma_remove_image', [ $this, 'ajax_remove_image' ] );
	}

	/**
	 * @param string[] $tabs Tab slugs.
	 * @return string[]
	 */
	public function register_tab_slug( array $tabs ): array {
		$tabs[] = self::TAB_ID;
		$tabs[] = self::TAB_EVENT_DETAILS;
		$tabs[] = self::TAB_NOTIFICATIONS;

		return $tabs;
	}

	/**
	 * @param array<int, array{id?:string,label?:string}> $tabs Nav tabs.
	 * @return array<int, array{id:string,label:string}>
	 */
	public function register_nav_tab( array $tabs ): array {
		$tabs[] = [
			'id'    => self::TAB_ID,
			'label' => __( 'Mobile App Settings', 'cem-mobile-app' ),
		];

		return $tabs;
	}

	/**
	 * @param array<int, array{id?:string,label?:string,icon?:string}> $items Sidebar items.
	 * @return array<int, array{id:string,label:string,icon:string}>
	 */
	public function register_sidebar_item( array $items ): array {
		$out = [];
		foreach ( $items as $item ) {
			$out[] = $item;
			if ( 'dashboard' === ( $item['id'] ?? '' ) ) {
				$out[] = [
					'id'    => self::PANEL_ID,
					'label' => __( 'App Content', 'cem-mobile-app' ),
					'icon'  => 'dashicons-smartphone',
				];
			}
		}

		return $out;
	}

	/**
	 * @param string[] $panels Panel slugs.
	 * @return string[]
	 */
	public function register_panel( array $panels ): array {
		$panels[] = self::PANEL_ID;

		return $panels;
	}

	public function render_panel( string $html, string $panel ): string {
		if ( self::PANEL_ID !== $panel ) {
			return $html;
		}

		ob_start();
		include CMA_PATH . 'admin/views/panel-app-content.php';

		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, string> $tabs Slug => label.
	 * @return array<string, string>
	 */
	public function register_documentation_tab( array $tabs ): array {
		$tabs[ self::TAB_ID ] = __( 'Mobile App', 'cem-mobile-app' );

		return $tabs;
	}

	public function render_documentation_tab( string $html, string $slug ): string {
		if ( self::TAB_ID !== $slug ) {
			return $html;
		}

		ob_start();
		include CMA_PATH . 'admin/views/tab-documentation.php';

		return (string) ob_get_clean();
	}

	public function render_tab( string $html, string $tab ): string {
		$view = '';
		if ( self::TAB_ID === $tab ) {
			$view = CMA_PATH . 'admin/views/tab-mobile-app.php';
		} elseif ( self::TAB_EVENT_DETAILS === $tab ) {
			$view = CMA_PATH . 'admin/views/tab-event-details.php';
		} elseif ( self::TAB_NOTIFICATIONS === $tab ) {
			$view = CMA_PATH . 'admin/views/tab-app-notifications.php';
		}

		if ( '' === $view ) {
			return $html;
		}

		ob_start();
		include $view;

		return (string) ob_get_clean();
	}

	public function save_tab( bool $handled, string $tab ): bool {
		if ( self::TAB_ID === $tab ) {
			return CMA_Settings::save_plugin_settings_from_request();
		}

		if ( self::TAB_EVENT_DETAILS === $tab ) {
			return CMA_Settings::save_event_details_from_request();
		}

		return $handled;
	}

	public function enqueue_assets( $hook = null ): void {
		if ( wp_script_is( 'cma-admin', 'enqueued' ) ) {
			return;
		}

		if ( ! $this->should_enqueue_assets( is_string( $hook ) ? $hook : '' ) ) {
			return;
		}

		wp_enqueue_style(
			'maps-home-screen-images-admin',
			CMA_URL . 'assets/css/home-screen-images.css',
			[],
			CMA_VERSION
		);

		$deps = [ 'jquery' ];
		if ( wp_script_is( 'cem-admin-js', 'registered' ) || wp_script_is( 'cem-admin-js', 'enqueued' ) ) {
			$deps[] = 'cem-admin-js';
		}

		wp_enqueue_script(
			'cma-admin',
			CMA_URL . 'assets/js/cma-admin.js',
			$deps,
			CMA_VERSION,
			true
		);
		wp_localize_script(
			'cma-admin',
			'CMAAdmin',
			[
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'cma_upload_image' ),
				'removeLabel'  => __( 'Remove', 'cem-mobile-app' ),
				'uploading'    => __( 'Uploading…', 'cem-mobile-app' ),
				'uploadFailed' => __( 'Upload failed.', 'cem-mobile-app' ),
				'saved'            => __( 'Saved.', 'cem-mobile-app' ),
				'noticeCache'      => __( 'App cache was invalidated. Open the app page on devices to rebuild with fresh content.', 'cem-mobile-app' ),
				'noticeSent'       => __( 'Notification sent.', 'cem-mobile-app' ),
				'noticeScheduled'  => __( 'Notification scheduled.', 'cem-mobile-app' ),
				'noticeFailed'     => __( 'Notification failed to send. Check recent row error details and OneSignal keys.', 'cem-mobile-app' ),
				'noticeMissing'    => __( 'Title and body are required.', 'cem-mobile-app' ),
				'noticeNoSchedule' => __( 'Select an event schedule in Event Details before sending notifications.', 'cem-mobile-app' ),
				'noticeSaveFailed' => __( 'Unable to save notification.', 'cem-mobile-app' ),
				'noticeDeleted'    => __( 'Notification deleted.', 'cem-mobile-app' ),
				'noticeDeleteFail' => __( 'Unable to delete notification.', 'cem-mobile-app' ),
			]
		);
	}

	public function ajax_upload_image(): void {
		if ( class_exists( 'CEM_Capabilities' ) ) {
			CEM_Capabilities::require_configure();
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to upload images.', 'cem-mobile-app' ) ], 403 );
		}

		check_ajax_referer( 'cma_upload_image', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to upload files.', 'cem-mobile-app' ) ], 403 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No file was uploaded.', 'cem-mobile-app' ) ], 400 );
		}

		$upload = $_FILES['file'];
		if ( UPLOAD_ERR_OK !== (int) ( $upload['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			wp_send_json_error( [ 'message' => __( 'No file was uploaded.', 'cem-mobile-app' ) ], 400 );
		}

		$tmp      = (string) ( $upload['tmp_name'] ?? '' );
		$filename = sanitize_file_name( (string) ( $upload['name'] ?? '' ) );
		$ext      = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		$allowed  = [ 'jpg', 'jpeg', 'jpe', 'gif', 'png', 'webp', 'svg' ];

		if ( '' === $ext || ! in_array( $ext, $allowed, true ) ) {
			wp_send_json_error( [ 'message' => __( 'Please upload a JPG, PNG, GIF, WebP, or SVG image.', 'cem-mobile-app' ) ], 400 );
		}

		if ( 'svg' === $ext && ! $this->sanitize_uploaded_svg( $tmp ) ) {
			wp_send_json_error( [ 'message' => __( 'That SVG could not be used. Please upload a simple image SVG without scripts.', 'cem-mobile-app' ) ], 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		add_filter( 'upload_mimes', [ $this, 'allow_svg_mime' ], 100 );
		add_filter( 'wp_check_filetype_and_ext', [ $this, 'allow_svg_filetype' ], 99, 4 );
		add_filter( 'wp_prevent_unsupported_mime_type_uploads', [ $this, 'allow_svg_unsupported_mime' ], 10, 2 );

		// Sideload after our nonce/cap checks. SVG Support's upload prefilter expects the
		// Media Library `media-form` nonce and rejects AJAX uploads with "Security check failed."
		$file_array = [
			'name'     => $filename,
			'type'     => (string) ( $upload['type'] ?? '' ),
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => (int) ( $upload['size'] ?? 0 ),
		];

		$attachment_id = media_handle_sideload( $file_array, 0 );

		remove_filter( 'upload_mimes', [ $this, 'allow_svg_mime' ], 100 );
		remove_filter( 'wp_check_filetype_and_ext', [ $this, 'allow_svg_filetype' ], 99 );
		remove_filter( 'wp_prevent_unsupported_mime_type_uploads', [ $this, 'allow_svg_unsupported_mime' ], 10 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ], 400 );
		}

		$attachment_id = (int) $attachment_id;
		$mime          = (string) get_post_mime_type( $attachment_id );
		$is_svg        = 'image/svg+xml' === $mime || 'svg' === $ext;

		if ( ! wp_attachment_is_image( $attachment_id ) && ! $is_svg ) {
			wp_delete_attachment( $attachment_id, true );
			wp_send_json_error( [ 'message' => __( 'Please upload an image file.', 'cem-mobile-app' ) ], 400 );
		}

		$file_url  = CMA_Settings::get_app_image_url( $attachment_id );
		$thumb     = wp_get_attachment_image_src( $attachment_id, 'thumbnail' );
		$medium    = wp_get_attachment_image_src( $attachment_id, 'medium' );
		$url       = ( $medium && ! empty( $medium[0] ) && (int) ( $medium[1] ?? 0 ) > 1 ) ? $medium[0] : $file_url;
		$thumb_url = ( $thumb && ! empty( $thumb[0] ) && (int) ( $thumb[1] ?? 0 ) > 1 ) ? $thumb[0] : $url;

		$context = sanitize_key( wp_unslash( $_POST['context'] ?? '' ) );
		if ( 'logo' === $context ) {
			CMA_Settings::set_header_logo_id( $attachment_id );
		} elseif ( 'map' === $context ) {
			CMA_Settings::add_home_screen_image_id( $attachment_id );
		}

		wp_send_json_success(
			[
				'id'    => $attachment_id,
				'url'   => $url,
				'thumb' => $thumb_url,
				'saved' => true,
			]
		);
	}

	public function ajax_remove_image(): void {
		if ( class_exists( 'CEM_Capabilities' ) ) {
			CEM_Capabilities::require_configure();
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to change these images.', 'cem-mobile-app' ) ], 403 );
		}

		check_ajax_referer( 'cma_upload_image', 'nonce' );

		$context = sanitize_key( wp_unslash( $_POST['context'] ?? '' ) );
		$id      = absint( $_POST['id'] ?? 0 );

		if ( 'logo' === $context ) {
			CMA_Settings::set_header_logo_id( 0 );
		} elseif ( 'map' === $context ) {
			CMA_Settings::remove_home_screen_image_id( $id );
		} else {
			wp_send_json_error( [ 'message' => __( 'Invalid image context.', 'cem-mobile-app' ) ], 400 );
		}

		wp_send_json_success( [ 'saved' => true ] );
	}

	/**
	 * @param array<string, string> $mimes Mime map.
	 * @return array<string, string>
	 */
	public function allow_svg_mime( array $mimes ): array {
		$mimes['svg'] = 'image/svg+xml';

		return $mimes;
	}

	/**
	 * WordPress often reports SVG as text/html; force the real type during our upload.
	 *
	 * @param array{ext?:string,type?:string,proper_filename?:string}|false $data File data.
	 * @param string                                                       $file Full path.
	 * @param string                                                       $filename Original name.
	 * @param array<string, string>|null                                   $mimes Mime map.
	 * @return array{ext?:string,type?:string,proper_filename?:string}|false
	 */
	public function allow_svg_filetype( $data, string $file, string $filename, $mimes ) {
		unset( $file, $mimes );
		$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'svg' !== $ext ) {
			return $data;
		}

		if ( ! is_array( $data ) ) {
			$data = [];
		}

		$data['ext']             = 'svg';
		$data['type']            = 'image/svg+xml';
		$data['proper_filename'] = $data['proper_filename'] ?? $filename;

		return $data;
	}

	/**
	 * WordPress 6.8+ blocks image types that cannot generate raster sub-sizes.
	 *
	 * @param bool        $check_mime Whether to prevent the upload.
	 * @param string|null $mime_type  Detected mime type.
	 */
	public function allow_svg_unsupported_mime( $check_mime, $mime_type = null ) {
		if ( is_string( $mime_type ) && in_array( $mime_type, [ 'image/svg+xml', 'image/svg' ], true ) ) {
			return false;
		}

		return $check_mime;
	}

	private function sanitize_uploaded_svg( string $path ): bool {
		if ( '' === $path || ! is_readable( $path ) ) {
			return false;
		}

		$svg = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temp upload.
		if ( false === $svg || ! preg_match( '/<svg\b/i', $svg ) ) {
			return false;
		}

		if ( preg_match( '/<(\?xml|!doctype)/i', $svg ) && preg_match( '/<!ENTITY/i', $svg ) ) {
			return false;
		}

		$svg = preg_replace( '#<script\b[^>]*>[\s\S]*?</script>#i', '', $svg ) ?? $svg;
		$svg = preg_replace( '#<foreignObject\b[^>]*>[\s\S]*?</foreignObject>#i', '', $svg ) ?? $svg;
		$svg = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg ) ?? $svg;
		$svg = preg_replace( '/javascript\s*:/i', '', $svg ) ?? $svg;

		return false !== file_put_contents( $path, $svg ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local temp upload.
	}

	public function maybe_notice(): void {
		$notice = sanitize_text_field( wp_unslash( $_GET['cma_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $notice ) {
			return;
		}

		$map = [
			'cache_cleared'  => __( 'App cache was invalidated. Open the app page on devices to rebuild with fresh content.', 'cem-mobile-app' ),
			'sent'           => __( 'Notification sent.', 'cem-mobile-app' ),
			'scheduled'      => __( 'Notification scheduled.', 'cem-mobile-app' ),
			'failed'         => __( 'Notification failed to send. Check recent row error details and OneSignal keys.', 'cem-mobile-app' ),
			'missing_fields' => __( 'Title and body are required.', 'cem-mobile-app' ),
			'no_schedule'    => __( 'Select an event schedule in Event Details before sending notifications.', 'cem-mobile-app' ),
			'save_failed'    => __( 'Unable to save notification.', 'cem-mobile-app' ),
			'deleted'        => __( 'Notification deleted.', 'cem-mobile-app' ),
			'delete_failed'  => __( 'Unable to delete notification.', 'cem-mobile-app' ),
		];

		if ( ! isset( $map[ $notice ] ) ) {
			return;
		}

		$type = in_array( $notice, [ 'failed', 'missing_fields', 'no_schedule', 'save_failed', 'delete_failed' ], true ) ? 'warning' : 'success';
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			esc_html( $map[ $notice ] )
		);
	}

	public static function manager_url( string $hash = 'settings', string $notice = '' ): string {
		$parent = 'edit.php?post_type=tribe_events';
		if ( class_exists( 'Tribe__Events__Main' ) ) {
			$parent = 'edit.php?post_type=' . Tribe__Events__Main::POSTTYPE;
		}

		$url = admin_url( $parent );
		$url = add_query_arg( [ 'page' => 'convention-event-manager' ], $url );
		if ( '' !== $notice ) {
			$url = add_query_arg( 'cma_notice', $notice, $url );
		}

		$hash = sanitize_key( $hash );

		return $url . ( '' !== $hash ? '#' . $hash : '' );
	}

	public static function settings_url( string $notice = '' ): string {
		return self::manager_url( 'settings', $notice );
	}

	public static function content_url( string $notice = '' ): string {
		return self::manager_url( self::PANEL_ID, $notice );
	}

	private function should_enqueue_assets( string $hook ): bool {
		if ( wp_script_is( 'cem-admin-js', 'enqueued' ) ) {
			return true;
		}

		if ( $this->is_cem_admin_screen( $hook ) ) {
			return true;
		}

		if ( is_admin() ) {
			return false;
		}

		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'cem_manager_dashboard' ) ) {
			return true;
		}

		return false;
	}

	private function is_cem_admin_screen( string $hook ): bool {
		if ( str_contains( $hook, 'convention-event-manager' ) ) {
			return true;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return 'convention-event-manager' === $page;
	}
}
