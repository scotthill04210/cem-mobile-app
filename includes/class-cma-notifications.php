<?php

defined( 'ABSPATH' ) || exit;

class CMA_Notifications {

	public function __construct() {
		add_action( 'admin_post_cma_clear_app_cache', [ $this, 'handle_clear_app_cache' ] );
		add_action( 'admin_post_cma_create_notification', [ $this, 'handle_create_notification' ] );
		add_action( 'admin_post_cma_delete_notification', [ $this, 'handle_delete_notification' ] );
		add_action( 'admin_post_cma_bulk_delete_notifications', [ $this, 'handle_bulk_delete_notifications' ] );
		add_action( 'cma_send_scheduled_notification', [ $this, 'send_scheduled_notification' ], 10, 1 );
		add_action( 'maps_send_scheduled_notification', [ $this, 'send_scheduled_notification' ], 10, 1 );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'maps_notifications';
	}

	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			schedule_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title text NOT NULL,
			body longtext NOT NULL,
			target_url text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'draft',
			created_at datetime NOT NULL,
			scheduled_for datetime NULL,
			sent_at datetime NULL,
			last_error text NULL,
			onesignal_response longtext NULL,
			PRIMARY KEY  (id),
			KEY schedule_id (schedule_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	public static function ensure_columns(): void {
		global $wpdb;

		$table_name = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection.
		$columns = $wpdb->get_results( 'SHOW COLUMNS FROM `' . esc_sql( $table_name ) . "` LIKE 'onesignal_response'" );
		if ( empty( $columns ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time ALTER.
			$wpdb->query( 'ALTER TABLE `' . esc_sql( $table_name ) . '` ADD COLUMN onesignal_response longtext NULL AFTER last_error' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection.
		$schedule_col = $wpdb->get_results( 'SHOW COLUMNS FROM `' . esc_sql( $table_name ) . "` LIKE 'schedule_id'" );
		if ( empty( $schedule_col ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time ALTER.
			$wpdb->query( 'ALTER TABLE `' . esc_sql( $table_name ) . '` ADD COLUMN schedule_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER id' );
		}

		self::assign_unscoped_rows_to_current_schedule();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection.
		$indexes  = $wpdb->get_results( 'SHOW INDEX FROM `' . esc_sql( $table_name ) . '`' );
		$has_sid  = false;
		if ( is_array( $indexes ) ) {
			foreach ( $indexes as $idx ) {
				if ( isset( $idx->Key_name ) && 'schedule_id' === (string) $idx->Key_name ) {
					$has_sid = true;
					break;
				}
			}
		}
		if ( ! $has_sid ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Filter index.
			$wpdb->query( 'ALTER TABLE `' . esc_sql( $table_name ) . '` ADD KEY schedule_id (schedule_id)' );
		}
	}

	private static function assign_unscoped_rows_to_current_schedule(): void {
		$current = CMA_Settings::get_schedule_id();
		if ( $current <= 0 ) {
			return;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time backfill of rows created before schedule scoping.
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE `' . esc_sql( self::table_name() ) . '` SET schedule_id = %d WHERE schedule_id = 0',
				$current
			)
		);
	}

	public static function admin_redirect( string $notice ): void {
		wp_safe_redirect( CMA_Admin::content_url( $notice ) );
		exit;
	}

	public function handle_clear_app_cache(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cem-mobile-app' ) );
		}

		check_admin_referer( 'cma_clear_app_cache' );
		CMA_Settings::bump_sw_cache_token();
		wp_safe_redirect( CMA_Admin::settings_url( 'cache_cleared' ) );
		exit;
	}

	public function handle_create_notification(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cem-mobile-app' ) );
		}

		check_admin_referer( 'cma_create_notification' );

		global $wpdb;

		$event_schedule_id = CMA_Settings::get_schedule_id();
		if ( $event_schedule_id <= 0 ) {
			self::admin_redirect( 'no_schedule' );
		}

		$title       = sanitize_text_field( wp_unslash( $_POST['maps_notification_title'] ?? '' ) );
		$body        = sanitize_textarea_field( wp_unslash( $_POST['maps_notification_body'] ?? '' ) );
		$target_url  = esc_url_raw( wp_unslash( $_POST['maps_notification_target_url'] ?? '' ) );
		$schedule_at = sanitize_text_field( wp_unslash( $_POST['maps_notification_schedule_at'] ?? '' ) );

		if ( '' === $title || '' === $body ) {
			self::admin_redirect( 'missing_fields' );
		}

		if ( '' === $target_url ) {
			$target_url = CMA_Settings::get_app_url();
		}

		$now = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table insert.
		$inserted = $wpdb->insert(
			self::table_name(),
			[
				'schedule_id'   => $event_schedule_id,
				'title'         => $title,
				'body'          => $body,
				'target_url'    => $target_url,
				'status'        => 'pending',
				'created_at'    => $now,
				'scheduled_for' => null,
			],
			[ '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		if ( ! $inserted ) {
			self::admin_redirect( 'save_failed' );
		}

		$notification_id = absint( $wpdb->insert_id );
		$scheduled_ts    = $schedule_at ? self::parse_datetime_local_to_timestamp( $schedule_at ) : false;

		if ( false !== $scheduled_ts && $scheduled_ts > time() + 60 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update.
			$wpdb->update(
				self::table_name(),
				[
					'status'        => 'scheduled',
					'scheduled_for' => wp_date( 'Y-m-d H:i:s', $scheduled_ts ),
				],
				[ 'id' => $notification_id ],
				[ '%s', '%s' ],
				[ '%d' ]
			);

			wp_schedule_single_event( $scheduled_ts, 'cma_send_scheduled_notification', [ $notification_id ] );
			self::admin_redirect( 'scheduled' );
		}

		$send_result = $this->process_single_notification( $notification_id );
		self::admin_redirect( $send_result ? 'sent' : 'failed' );
	}

	public function handle_delete_notification(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cem-mobile-app' ) );
		}

		check_admin_referer( 'cma_delete_notification' );

		$notification_id = absint( wp_unslash( $_POST['notification_id'] ?? 0 ) );
		$schedule_id     = CMA_Settings::get_schedule_id();
		if ( $notification_id <= 0 || $schedule_id <= 0 ) {
			self::admin_redirect( 'delete_failed' );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$deleted = $wpdb->delete(
			self::table_name(),
			[
				'id'          => $notification_id,
				'schedule_id' => $schedule_id,
			],
			[ '%d', '%d' ]
		);

		self::admin_redirect( $deleted ? 'deleted' : 'delete_failed' );
	}

	public function handle_bulk_delete_notifications(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'cem-mobile-app' ) );
		}

		check_admin_referer( 'cma_bulk_delete_notifications' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to positive integers below.
		$raw_ids = isset( $_POST['notification_ids'] ) ? (array) wp_unslash( $_POST['notification_ids'] ) : [];
		$ids     = array_values(
			array_filter(
				array_map( 'absint', $raw_ids ),
				static fn( $id ) => $id > 0
			)
		);

		$schedule_id = CMA_Settings::get_schedule_id();
		if ( empty( $ids ) || $schedule_id <= 0 ) {
			self::admin_redirect( 'delete_failed' );
		}

		global $wpdb;
		$table_name = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic IN list; IDs bound by prepare().
		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM `' . esc_sql( $table_name ) . '` WHERE schedule_id = %d AND id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
				$schedule_id,
				...$ids
			)
		);

		self::admin_redirect( ( is_numeric( $deleted ) && absint( $deleted ) > 0 ) ? 'deleted' : 'delete_failed' );
	}

	public function send_scheduled_notification( mixed $notification_id ): void {
		$this->process_single_notification( absint( $notification_id ) );
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'cma/v1',
			'/recent-notifications',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_recent_notifications' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function rest_recent_notifications( WP_REST_Request $request ): WP_REST_Response {
		$limit       = max( 1, min( 50, absint( $request->get_param( 'limit' ) ) ) );
		$show_date   = in_array( strtolower( (string) $request->get_param( 'show_date' ) ), [ '1', 'yes', 'true' ], true );
		$local_param = (string) $request->get_param( 'local_time' );
		$local_time  = ( '' === $local_param )
			? true
			: ! in_array( strtolower( $local_param ), [ '0', 'no', 'false' ], true );

		return new WP_REST_Response(
			[
				'html' => self::get_recent_markup( $limit, $show_date, $local_time ),
			],
			200
		);
	}

	/**
	 * @return object[]
	 */
	public static function get_recent_rows( int $limit = 20 ): array {
		global $wpdb;

		$limit       = max( 1, min( 50, $limit ) );
		$schedule_id = CMA_Settings::get_schedule_id();
		if ( $schedule_id <= 0 ) {
			return [];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin listing.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( self::table_name() ) . '` WHERE schedule_id = %d ORDER BY id DESC LIMIT %d',
				$schedule_id,
				$limit
			)
		);

		return is_array( $rows ) ? $rows : [];
	}

	public static function get_recent_markup( int $limit, bool $show_date, bool $use_local_time = true ): string {
		global $wpdb;

		$limit       = max( 1, min( 50, $limit ) );
		$schedule_id = CMA_Settings::get_schedule_id();
		if ( $schedule_id <= 0 ) {
			return '<p>' . esc_html__( 'No notifications yet.', 'cem-mobile-app' ) . '</p>';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Front-end listing.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, title, body, target_url, sent_at FROM `' . esc_sql( self::table_name() ) . '` WHERE schedule_id = %d AND status = %s ORDER BY sent_at DESC, id DESC LIMIT %d',
				$schedule_id,
				'sent',
				$limit
			)
		);

		if ( empty( $rows ) ) {
			return '<p>' . esc_html__( 'No notifications yet.', 'cem-mobile-app' ) . '</p>';
		}

		$output = '<div class="maps-notifications-list">';
		foreach ( $rows as $row ) {
			$output .= '<article class="maps-notification-item">';
			$output .= '<h3>' . esc_html( $row->title ) . '</h3>';
			$output .= '<p>' . esc_html( $row->body ) . '</p>';
			if ( ! empty( $row->target_url ) ) {
				$output .= '<p><a class="maps-notification-open-link" href="' . esc_url( $row->target_url ) . '">' . esc_html__( 'Open', 'cem-mobile-app' ) . '</a></p>';
			}
			if ( $show_date && ! empty( $row->sent_at ) ) {
				$site_formatted = self::format_site_datetime( $row->sent_at );
				if ( $use_local_time ) {
					$iso = self::datetime_to_iso8601( $row->sent_at );
					if ( '' !== $iso ) {
						$output .= '<small><time class="maps-notification-sent-at" datetime="' . esc_attr( $iso ) . '">' . esc_html( $site_formatted ) . '</time></small>';
					} else {
						$output .= '<small>' . esc_html( $site_formatted ) . '</small>';
					}
				} else {
					$output .= '<small>' . esc_html( $site_formatted ) . '</small>';
				}
			}
			$output .= '</article>';
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * @return array{success:bool,error:?WP_Error,raw:string,data:?array}
	 */
	public static function send_onesignal_notification( string $title, string $body, string $target_url ): array {
		$app_id  = CMA_Settings::get_onesignal_app_id();
		$api_key = CMA_Settings::get_onesignal_rest_api_key();
		if ( '' === $app_id || '' === $api_key ) {
			return [
				'success' => false,
				'error'   => new WP_Error( 'missing_keys', __( 'OneSignal keys are missing.', 'cem-mobile-app' ) ),
				'raw'     => '',
				'data'    => null,
			];
		}

		$test_subscription_id = trim( (string) get_option( 'maps_onesignal_test_subscription_id', '' ) );
		$use_test_id          = 1 === absint( get_option( 'maps_onesignal_use_test_subscription_id', 0 ) );

		$payload = [
			'app_id'         => $app_id,
			'target_channel' => 'push',
			'headings'       => [ 'en' => $title ],
			'contents'       => [ 'en' => $body ],
			'url'            => $target_url,
		];

		if ( $use_test_id && '' !== $test_subscription_id ) {
			$payload['include_subscription_ids'] = [ $test_subscription_id ];
		} else {
			$payload['included_segments'] = [ 'Subscribed Users' ];
		}

		$send_payload = static function ( array $current_payload ) use ( $api_key ): array {
			$response = wp_remote_post(
				'https://api.onesignal.com/notifications',
				[
					'headers' => [
						'Authorization' => 'Key ' . $api_key,
						'Content-Type'  => 'application/json; charset=utf-8',
					],
					'timeout' => 15,
					'body'    => wp_json_encode( $current_payload ),
				]
			);

			if ( is_wp_error( $response ) ) {
				return [
					'success' => false,
					'error'   => $response,
					'raw'     => '',
					'data'    => null,
				];
			}

			$code     = wp_remote_retrieve_response_code( $response );
			$body_raw = wp_remote_retrieve_body( $response );
			if ( $code < 200 || $code > 299 ) {
				return [
					'success' => false,
					'error'   => new WP_Error( 'onesignal_http_error', $body_raw ),
					'raw'     => $body_raw,
					'data'    => null,
				];
			}

			$data = json_decode( $body_raw, true );
			if ( is_array( $data ) ) {
				if ( isset( $data['errors'] ) ) {
					return [
						'success' => false,
						'error'   => new WP_Error( 'onesignal_api_error', wp_json_encode( $data['errors'] ) ),
						'raw'     => $body_raw,
						'data'    => $data,
					];
				}

				if ( array_key_exists( 'recipients', $data ) && absint( $data['recipients'] ) < 1 ) {
					return [
						'success' => false,
						'error'   => new WP_Error(
							'onesignal_no_recipients',
							__( 'OneSignal accepted the message but delivered it to 0 recipients. Confirm the device is subscribed in OneSignal and that your segment targeting matches web subscribers.', 'cem-mobile-app' )
						),
						'raw'  => $body_raw,
						'data' => $data,
					];
				}
			}

			return [
				'success' => true,
				'error'   => null,
				'raw'     => $body_raw,
				'data'    => is_array( $data ) ? $data : null,
			];
		};

		$result = $send_payload( $payload );

		if ( ! $use_test_id && ! $result['success'] && ! empty( $result['data']['errors'] ) && is_array( $result['data']['errors'] ) ) {
			$errors = wp_json_encode( $result['data']['errors'] );
			if ( is_string( $errors ) && false !== stripos( $errors, 'all included players are not subscribed' ) ) {
				foreach ( [ 'Total Subscriptions', 'All Subscriptions' ] as $segment_name ) {
					$fallback_payload                      = $payload;
					$fallback_payload['included_segments'] = [ $segment_name ];
					$fallback_result                       = $send_payload( $fallback_payload );
					if ( $fallback_result['success'] ) {
						return $fallback_result;
					}
					$result = $fallback_result;
				}
			}
		}

		return $result;
	}

	public function process_single_notification( int $notification_id ): bool {
		global $wpdb;

		$table_name = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Single row fetch.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM `' . esc_sql( $table_name ) . '` WHERE id = %d',
				$notification_id
			)
		);

		if ( ! $row ) {
			return false;
		}

		$result       = self::send_onesignal_notification( $row->title, $row->body, $row->target_url );
		$response_raw = isset( $result['raw'] ) ? (string) $result['raw'] : '';
		$success      = ! empty( $result['success'] );
		$error        = ( isset( $result['error'] ) && $result['error'] instanceof WP_Error ) ? $result['error'] : null;

		if ( $success ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Status update.
			$wpdb->update(
				$table_name,
				[
					'status'             => 'sent',
					'sent_at'            => current_time( 'mysql' ),
					'last_error'         => null,
					'onesignal_response' => $response_raw ? $response_raw : null,
				],
				[ 'id' => $notification_id ],
				[ '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);

			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Failure status update.
		$wpdb->update(
			$table_name,
			[
				'status'             => 'failed',
				'last_error'         => $error ? $error->get_error_message() : __( 'Unknown error', 'cem-mobile-app' ),
				'onesignal_response' => $response_raw ? $response_raw : null,
			],
			[ 'id' => $notification_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return false;
	}

	public static function parse_datetime_local_to_timestamp( string $datetime_local ): int|false {
		$datetime_local = trim( $datetime_local );
		if ( '' === $datetime_local ) {
			return false;
		}

		if ( function_exists( 'wp_timezone' ) ) {
			$tz = wp_timezone();
			if ( $tz ) {
				foreach ( [ 'Y-m-d\TH:i', 'Y-m-d\TH:i:s' ] as $format ) {
					$dt = date_create_immutable_from_format( $format, $datetime_local, $tz );
					if ( $dt instanceof DateTimeImmutable ) {
						return $dt->getTimestamp();
					}
				}
			}
		}

		$normalized = str_replace( 'T', ' ', $datetime_local );
		$ts         = strtotime( $normalized );

		return ( false === $ts ) ? false : (int) $ts;
	}

	public static function format_site_datetime( string $mysql_datetime ): string {
		$mysql_datetime = trim( $mysql_datetime );
		if ( '' === $mysql_datetime ) {
			return '';
		}

		if ( function_exists( 'wp_timezone' ) ) {
			$tz = wp_timezone();
			$dt = date_create_immutable_from_format( 'Y-m-d H:i:s', $mysql_datetime, $tz );
			if ( ! $dt instanceof DateTimeImmutable ) {
				$dt = date_create_immutable( $mysql_datetime, $tz );
			}
			if ( $dt instanceof DateTimeImmutable ) {
				return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $dt->getTimestamp() );
			}
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $mysql_datetime ) );
	}

	public static function datetime_to_iso8601( string $mysql_datetime ): string {
		$mysql_datetime = trim( $mysql_datetime );
		if ( '' === $mysql_datetime || ! function_exists( 'wp_timezone' ) ) {
			return '';
		}

		$tz = wp_timezone();
		$dt = date_create_immutable_from_format( 'Y-m-d H:i:s', $mysql_datetime, $tz );
		if ( ! $dt instanceof DateTimeImmutable ) {
			$fallback = date_create_immutable( $mysql_datetime, $tz );
			$dt       = ( $fallback instanceof DateTimeImmutable ) ? $fallback : null;
		}
		if ( ! $dt instanceof DateTimeImmutable ) {
			return '';
		}

		return $dt->format( DATE_ATOM );
	}
}
