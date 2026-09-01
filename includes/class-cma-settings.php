<?php

defined( 'ABSPATH' ) || exit;

class CMA_Settings {

	public const OPTION_SLUG                = 'cma_app_page_slug';
	public const OPTION_PAGE_ID             = 'cma_app_page_id';
	public const OPTION_SCHEDULE_ID         = 'cma_schedule_id';
	public const OPTION_NO_SCHEDULE_MESSAGE = 'cma_no_schedule_message';
	public const OPTION_HEADER_LOGO_ID      = 'cma_header_logo_id';
	public const OPTION_EVENT_TITLE         = 'cma_event_title';
	public const OPTION_COLOR_PRIMARY       = 'cma_color_primary';
	public const OPTION_COLOR_SECONDARY     = 'cma_color_secondary';
	public const OPTION_COLOR_BUTTON_TEXT   = 'cma_color_button_text';

	public const DEFAULT_COLOR_PRIMARY     = '#1e4b8a';
	public const DEFAULT_COLOR_SECONDARY   = '#007aff';
	public const DEFAULT_COLOR_BUTTON_TEXT = '#ffffff';

	public static function get_default_no_schedule_message(): string {
		return __( 'Check back soon for the next event.', 'cem-mobile-app' );
	}

	public static function get_no_schedule_message(): string {
		$stored = get_option( self::OPTION_NO_SCHEDULE_MESSAGE, null );
		if ( ! is_string( $stored ) || '' === trim( $stored ) ) {
			return self::get_default_no_schedule_message();
		}

		return $stored;
	}

	public static function get_app_slug(): string {
		$slug = sanitize_title( (string) get_option( self::OPTION_SLUG, 'app' ) );

		return '' !== $slug ? $slug : 'app';
	}

	public static function sanitize_slug( mixed $slug ): string {
		$slug = sanitize_title( (string) $slug );

		return '' !== $slug ? $slug : 'app';
	}

	public static function get_app_path(): string {
		return '/' . trim( self::get_app_slug(), '/' ) . '/';
	}

	public static function get_app_url(): string {
		return home_url( self::get_app_path() );
	}

	public static function get_schedule_id(): int {
		return absint( get_option( self::OPTION_SCHEDULE_ID, 0 ) );
	}

	public static function get_schedule_title(): string {
		$schedule_id = self::get_schedule_id();
		if ( $schedule_id <= 0 ) {
			return '';
		}

		return trim( (string) get_the_title( $schedule_id ) );
	}

	/**
	 * Header event title: optional override, otherwise the selected schedule name.
	 */
	public static function get_event_title(): string {
		$custom = trim( (string) get_option( self::OPTION_EVENT_TITLE, '' ) );
		if ( '' !== $custom ) {
			return $custom;
		}

		return self::get_schedule_title();
	}

	public static function sanitize_app_color( mixed $color, string $default ): string {
		$color = sanitize_hex_color( is_string( $color ) ? $color : '' );

		return is_string( $color ) && '' !== $color ? strtolower( $color ) : $default;
	}

	/**
	 * @return array{primary:string,secondary:string,button_text:string}
	 */
	public static function get_app_colors(): array {
		return [
			'primary'     => self::sanitize_app_color( get_option( self::OPTION_COLOR_PRIMARY, self::DEFAULT_COLOR_PRIMARY ), self::DEFAULT_COLOR_PRIMARY ),
			'secondary'   => self::sanitize_app_color( get_option( self::OPTION_COLOR_SECONDARY, self::DEFAULT_COLOR_SECONDARY ), self::DEFAULT_COLOR_SECONDARY ),
			'button_text' => self::sanitize_app_color( get_option( self::OPTION_COLOR_BUTTON_TEXT, self::DEFAULT_COLOR_BUTTON_TEXT ), self::DEFAULT_COLOR_BUTTON_TEXT ),
		];
	}

	public static function get_header_logo_id(): int {
		$id = absint( get_option( self::OPTION_HEADER_LOGO_ID, 0 ) );
		if ( $id && self::is_app_image_attachment( $id ) ) {
			return $id;
		}

		return 0;
	}

	public static function is_app_image_attachment( int $id ): bool {
		if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
			return false;
		}

		$mime = (string) get_post_mime_type( $id );
		if ( str_starts_with( $mime, 'image/' ) ) {
			return true;
		}

		$file = (string) get_attached_file( $id );
		$ext  = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );

		return in_array( $ext, [ 'svg', 'svgz' ], true );
	}

	/**
	 * Public file URL for an app image. Avoids WP/SVG Support 1×1 “size” metadata.
	 */
	public static function get_app_image_url( int $id ): string {
		if ( ! self::is_app_image_attachment( $id ) ) {
			return '';
		}

		$url = wp_get_attachment_url( $id );

		return $url ? (string) $url : '';
	}

	public static function set_header_logo_id( int $id ): void {
		if ( $id > 0 && self::is_app_image_attachment( $id ) ) {
			update_option( self::OPTION_HEADER_LOGO_ID, $id );
		} else {
			update_option( self::OPTION_HEADER_LOGO_ID, 0 );
		}

		self::bump_sw_cache_token();
	}

	public static function add_home_screen_image_id( int $id ): void {
		if ( ! self::is_app_image_attachment( $id ) ) {
			return;
		}

		$ids   = self::get_home_screen_image_ids();
		$ids[] = $id;
		update_option( 'maps_home_screen_image_ids', array_values( array_unique( $ids ) ) );
		self::bump_sw_cache_token();
	}

	public static function remove_home_screen_image_id( int $id ): void {
		$ids = array_values(
			array_filter(
				self::get_home_screen_image_ids(),
				static function ( $saved ) use ( $id ) {
					return (int) $saved !== $id;
				}
			)
		);
		update_option( 'maps_home_screen_image_ids', $ids );
		self::bump_sw_cache_token();
	}

	public static function is_app_page(): bool {
		$slug    = self::get_app_slug();
		$page_id = absint( get_option( self::OPTION_PAGE_ID, 0 ) );

		if ( $page_id > 0 && is_page( $page_id ) ) {
			return true;
		}

		return is_page( $slug );
	}

	public static function get_onesignal_app_id(): string {
		return trim( (string) get_option( 'maps_onesignal_app_id', '' ) );
	}

	public static function get_onesignal_rest_api_key(): string {
		return trim( (string) get_option( 'maps_onesignal_rest_api_key', '' ) );
	}

	public static function is_onesignal_debug(): bool {
		return 1 === absint( get_option( 'maps_onesignal_debug_mode', 0 ) );
	}

	/**
	 * Public origin (scheme + host) for service workers / OneSignal.
	 */
	public static function get_public_origin(): string {
		$home_parts = wp_parse_url( home_url( '/' ) );
		$scheme     = isset( $home_parts['scheme'] ) ? $home_parts['scheme'] : 'https';
		$home_host  = isset( $home_parts['host'] ) ? strtolower( (string) $home_parts['host'] ) : '';

		if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && isset( $_SERVER['HTTP_HOST'] ) ) {
			$current_host = strtolower( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_HOST'] ) ) );
			if ( '' !== $current_host && '' !== $home_host && $current_host !== $home_host ) {
				$home_host = $current_host;
			}

			if ( is_ssl() ) {
				$scheme = 'https';
			}
		}

		if ( '' === $home_host ) {
			return home_url( '/' );
		}

		return $scheme . '://' . $home_host;
	}

	/**
	 * Site icon URL at a given size.
	 */
	public static function get_site_icon_url( int $size = 192 ): string {
		$site_icon_id = absint( get_option( 'site_icon' ) );
		if ( ! $site_icon_id ) {
			return '';
		}

		$icon_url = wp_get_attachment_image_url( $site_icon_id, [ $size, $size ] );
		if ( ! $icon_url ) {
			$icon_url = wp_get_attachment_image_url( $site_icon_id, 'full' );
		}

		return $icon_url ? (string) $icon_url : '';
	}

	/**
	 * @return int[]
	 */
	public static function get_home_screen_image_ids(): array {
		$ids = get_option( 'maps_home_screen_image_ids', [] );
		if ( ! is_array( $ids ) ) {
			return [];
		}

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

		return array_values(
			array_filter(
				$ids,
				static function ( $id ) {
					return $id && self::is_app_image_attachment( $id );
				}
			)
		);
	}

	/**
	 * @param mixed $raw Raw POST value.
	 * @return int[]
	 */
	public static function sanitize_home_screen_image_ids( mixed $raw ): array {
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$ids = [];
		foreach ( $raw as $id ) {
			$id = absint( $id );
			if ( ! $id || ! self::is_app_image_attachment( $id ) ) {
				continue;
			}
			if ( is_admin() && ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			$ids[] = $id;
		}

		return array_values( array_unique( $ids ) );
	}

	public static function get_sw_cache_token(): string {
		$token = get_option( 'maps_sw_cache_token', '' );
		if ( '' === $token ) {
			$token = (string) time();
			update_option( 'maps_sw_cache_token', $token );
		}

		return sanitize_key( $token );
	}

	public static function bump_sw_cache_token(): void {
		update_option( 'maps_sw_cache_token', (string) time() );
	}

	/**
	 * Persist plugin settings (page slug, logo, OneSignal) from the CEM settings tab POST.
	 */
	public static function save_plugin_settings_from_request(): bool {
		$slug = self::sanitize_slug( wp_unslash( $_POST['cma_app_page_slug'] ?? 'app' ) );
		CMA_App_Page::sync_slug( $slug );

		$logo_id = array_key_exists( 'cma_header_logo_id', $_POST ) ? absint( $_POST['cma_header_logo_id'] ) : null;
		if ( null !== $logo_id ) {
			if ( $logo_id && self::is_app_image_attachment( $logo_id ) ) {
				if ( ! is_admin() || current_user_can( 'edit_post', $logo_id ) ) {
					update_option( self::OPTION_HEADER_LOGO_ID, $logo_id );
				}
			} else {
				update_option( self::OPTION_HEADER_LOGO_ID, 0 );
			}
		}

		update_option( 'maps_onesignal_app_id', sanitize_text_field( wp_unslash( $_POST['maps_onesignal_app_id'] ?? '' ) ) );
		$rest_key = sanitize_text_field( wp_unslash( $_POST['maps_onesignal_rest_api_key'] ?? '' ) );
		if ( '' !== $rest_key ) {
			update_option( 'maps_onesignal_rest_api_key', $rest_key );
		}

		update_option( 'maps_onesignal_debug_mode', empty( $_POST['maps_onesignal_debug_mode'] ) ? 0 : 1 );
		update_option( 'maps_onesignal_test_subscription_id', sanitize_text_field( wp_unslash( $_POST['maps_onesignal_test_subscription_id'] ?? '' ) ) );
		update_option( 'maps_onesignal_use_test_subscription_id', empty( $_POST['maps_onesignal_use_test_subscription_id'] ) ? 0 : 1 );

		if ( array_key_exists( 'cma_color_primary', $_POST ) || array_key_exists( 'cma_color_secondary', $_POST ) || array_key_exists( 'cma_color_button_text', $_POST ) ) {
			$previous = self::get_app_colors();
			$colors   = [
				'primary'     => self::sanitize_app_color( wp_unslash( $_POST['cma_color_primary'] ?? '' ), self::DEFAULT_COLOR_PRIMARY ),
				'secondary'   => self::sanitize_app_color( wp_unslash( $_POST['cma_color_secondary'] ?? '' ), self::DEFAULT_COLOR_SECONDARY ),
				'button_text' => self::sanitize_app_color( wp_unslash( $_POST['cma_color_button_text'] ?? '' ), self::DEFAULT_COLOR_BUTTON_TEXT ),
			];
			update_option( self::OPTION_COLOR_PRIMARY, $colors['primary'] );
			update_option( self::OPTION_COLOR_SECONDARY, $colors['secondary'] );
			update_option( self::OPTION_COLOR_BUTTON_TEXT, $colors['button_text'] );
			if ( $previous !== $colors ) {
				self::bump_sw_cache_token();
			}
		}

		return true;
	}

	/**
	 * Persist event-facing app content from the App Content panel POST.
	 */
	public static function save_event_details_from_request(): bool {
		$schedule_id = absint( $_POST['cma_schedule_id'] ?? 0 );
		if ( $schedule_id > 0 && class_exists( 'CEM_Schedule_CPT' ) ) {
			$post = get_post( $schedule_id );
			if ( ! $post || CEM_Schedule_CPT::POST_TYPE !== $post->post_type ) {
				$schedule_id = 0;
			}
		}
		update_option( self::OPTION_SCHEDULE_ID, $schedule_id );

		$event_title = sanitize_text_field( wp_unslash( $_POST['cma_event_title'] ?? '' ) );
		update_option( self::OPTION_EVENT_TITLE, $event_title );

		$no_schedule = wp_kses_post( wp_unslash( $_POST['cma_no_schedule_message'] ?? '' ) );
		if ( '' === trim( wp_strip_all_tags( $no_schedule ) ) ) {
			$no_schedule = self::get_default_no_schedule_message();
		}
		update_option( self::OPTION_NO_SCHEDULE_MESSAGE, $no_schedule );

		$message = wp_kses_post( wp_unslash( $_POST['cma_home_screen_message'] ?? '' ) );
		update_option( 'maps_home_screen_message', $message );

		if ( array_key_exists( 'maps_home_screen_image_ids', $_POST ) ) {
			$raw_image_ids = $_POST['maps_home_screen_image_ids'];
			if ( ! is_array( $raw_image_ids ) ) {
				$raw_image_ids = [];
			}
			update_option( 'maps_home_screen_image_ids', self::sanitize_home_screen_image_ids( $raw_image_ids ) );
		}

		return true;
	}

	/**
	 * Persist Mobile App settings from the CEM settings tab POST.
	 *
	 * @deprecated 1.2.0 Use save_plugin_settings_from_request() or save_event_details_from_request().
	 */
	public static function save_from_request(): bool {
		self::save_plugin_settings_from_request();
		self::save_event_details_from_request();

		return true;
	}
}
