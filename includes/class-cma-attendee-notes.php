<?php

defined( 'ABSPATH' ) || exit;

class CMA_Attendee_Notes {

	public function __construct() {
		add_action( 'init', [ self::class, 'register_caps' ], 11 );
		add_action( 'init', [ $this, 'handle_frontend_auth' ], 1 );
		add_shortcode( 'sepa_attendee_notes', [ $this, 'shortcode' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
		add_action( 'admin_post_sepa_attendee_notes_export_csv', [ $this, 'export_csv' ] );

		add_action( 'wp_ajax_nopriv_sepa_attendee_notes_login', [ $this, 'ajax_login' ] );
		add_action( 'wp_ajax_nopriv_sepa_attendee_notes_register', [ $this, 'ajax_register' ] );
		add_action( 'wp_ajax_nopriv_sepa_attendee_notes_lost_password', [ $this, 'ajax_lost_password' ] );
		add_action( 'wp_ajax_sepa_attendee_notes_get_html', [ $this, 'ajax_get_html' ] );
		add_action( 'wp_ajax_nopriv_sepa_attendee_notes_is_logged_in', [ $this, 'ajax_is_logged_in' ] );
		add_action( 'wp_ajax_sepa_attendee_notes_is_logged_in', [ $this, 'ajax_is_logged_in' ] );
		add_action( 'wp_ajax_cma_attendee_notes_get_note', [ $this, 'ajax_get_note' ] );
		add_action( 'wp_ajax_cma_attendee_notes_save_note', [ $this, 'ajax_save_note' ] );
		add_action( 'wp_ajax_cma_attendee_notes_delete_note', [ $this, 'ajax_delete_note' ] );
		add_action( 'wp_ajax_sepa_attendee_notes_get_note', [ $this, 'ajax_get_note' ], 1 );
		add_action( 'wp_ajax_sepa_attendee_notes_save_note', [ $this, 'ajax_save_note' ], 1 );
		add_action( 'wp_ajax_sepa_attendee_notes_delete_note', [ $this, 'ajax_delete_note' ], 1 );
	}

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'cma_attendee_notes';
	}

	public static function create_table(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			schedule_id bigint(20) unsigned NOT NULL DEFAULT 0,
			attendee_key varchar(191) NOT NULL,
			note longtext NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_schedule_attendee (user_id, schedule_id, attendee_key),
			KEY attendee_key (attendee_key),
			KEY schedule_id (schedule_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Add schedule_id on existing installs and migrate unscoped rows to the current event.
	 */
	public static function ensure_columns(): void {
		global $wpdb;

		$table_name = self::table_name();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection.
		$schedule_col = $wpdb->get_results( 'SHOW COLUMNS FROM `' . esc_sql( $table_name ) . "` LIKE 'schedule_id'" );
		if ( empty( $schedule_col ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time ALTER.
			$wpdb->query( 'ALTER TABLE `' . esc_sql( $table_name ) . '` ADD COLUMN schedule_id bigint(20) unsigned NOT NULL DEFAULT 0 AFTER user_id' );
		}

		self::assign_unscoped_rows_to_current_schedule();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema inspection.
		$indexes = $wpdb->get_results( 'SHOW INDEX FROM `' . esc_sql( $table_name ) . '`' );
		$has_old = false;
		$has_new = false;
		$has_sid = false;
		if ( is_array( $indexes ) ) {
			foreach ( $indexes as $idx ) {
				$name = isset( $idx->Key_name ) ? (string) $idx->Key_name : '';
				if ( 'user_attendee' === $name ) {
					$has_old = true;
				}
				if ( 'user_schedule_attendee' === $name ) {
					$has_new = true;
				}
				if ( 'schedule_id' === $name ) {
					$has_sid = true;
				}
			}
		}

		if ( $has_old ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Drop the pre-schedule unique key.
			$wpdb->query( 'ALTER TABLE `' . esc_sql( $table_name ) . '` DROP INDEX user_attendee' );
		}
		if ( ! $has_new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Unique per user, event, and attendee.
			$wpdb->query( 'ALTER TABLE `' . esc_sql( $table_name ) . '` ADD UNIQUE KEY user_schedule_attendee (user_id, schedule_id, attendee_key)' );
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

	public static function register_caps(): void {
		foreach ( self::passwordless_allowed_roles() as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role ) {
				$role->add_cap( 'sepa_attendee_notes_use', true );
			}
		}
	}

	/**
	 * @return string[]
	 */
	public static function passwordless_allowed_roles(): array {
		return [ 'member', 'subscriber' ];
	}

	public static function current_user_can_use(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user instanceof WP_User ) {
			return false;
		}

		$allowed = user_can( $user, 'sepa_attendee_notes_use' ) || self::user_may_passwordless_access( $user );

		return (bool) apply_filters( 'sepa_attendee_notes_current_user_can_use', $allowed, $user );
	}

	public static function user_may_passwordless_access( WP_User $user ): bool {
		$roles = array_values( array_filter( (array) $user->roles ) );

		return (bool) array_intersect( self::passwordless_allowed_roles(), $roles );
	}

	public static function non_member_access_message(): string {
		return __( 'This email is linked to a SEPA staff account. Please use a different email address—not your SEPA employee email.', 'cem-mobile-app' );
	}

	public function shortcode( $atts ): string {
		unset( $atts );

		if ( ! is_user_logged_in() ) {
			$this->enqueue_assets();
			return '<div class="sepa-attendee-notes-root">' . $this->render_auth_block() . '</div>';
		}

		if ( ! self::current_user_can_use() ) {
			$this->enqueue_assets();
			return '<div class="sepa-attendee-notes-root"><p>' . esc_html( self::non_member_access_message() ) . '</p></div>';
		}

		$schedule_id = CMA_Settings::get_schedule_id();
		if ( $schedule_id <= 0 ) {
			return '<div class="cma-no-schedule-message">' . wp_kses_post( wpautop( CMA_Settings::get_no_schedule_message() ) ) . '</div>';
		}

		$attendees = CMA_Attendees::get_for_schedule( $schedule_id );
		$companies = [];
		$keys      = [];
		foreach ( $attendees as $attendee ) {
			$keys[] = $attendee['key'];
			if ( '' !== $attendee['company'] ) {
				$companies[ $attendee['company'] ] = $attendee['company'];
			}
		}
		ksort( $companies, SORT_NATURAL | SORT_FLAG_CASE );

		$user_note_map = array_fill_keys( $this->user_note_keys( get_current_user_id(), $keys ), true );

		$export_url = wp_nonce_url(
			add_query_arg(
				[
					'action' => 'sepa_attendee_notes_export_csv',
				],
				admin_url( 'admin-post.php' )
			),
			'sepa_attendee_notes_export_csv'
		);

		$this->enqueue_assets();

		ob_start();
		?>
		<div class="sepa-attendee-notes-wrap">
			<div class="sepa-attendee-notes-toolbar">
				<label for="sepa-attendee-notes-search"><?php esc_html_e( 'Search attendees:', 'cem-mobile-app' ); ?></label>
				<input type="search" id="sepa-attendee-notes-search" class="sepa-attendee-notes-search" placeholder="<?php echo esc_attr__( 'Search attendees', 'cem-mobile-app' ); ?>" />
				<label for="sepa-attendee-notes-company"><?php esc_html_e( 'Company:', 'cem-mobile-app' ); ?></label>
				<select id="sepa-attendee-notes-company" class="sepa-attendee-notes-company">
					<option value=""><?php esc_html_e( 'All companies', 'cem-mobile-app' ); ?></option>
					<?php foreach ( $companies as $company ) : ?>
						<option value="<?php echo esc_attr( $company ); ?>"><?php echo esc_html( $company ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="sepa-attendee-notes-note-status"><?php esc_html_e( 'Note status:', 'cem-mobile-app' ); ?></label>
				<select id="sepa-attendee-notes-note-status" class="sepa-attendee-notes-note-status">
					<option value="all"><?php esc_html_e( 'All attendees', 'cem-mobile-app' ); ?></option>
					<option value="has"><?php esc_html_e( 'Has notes', 'cem-mobile-app' ); ?></option>
					<option value="none"><?php esc_html_e( 'No notes yet', 'cem-mobile-app' ); ?></option>
				</select>
				<button type="button" class="btn btn-outline-secondary btn-sm sepa-attendee-notes-reset"><?php esc_html_e( 'Reset Filters', 'cem-mobile-app' ); ?></button>
				<a class="btn btn-primary btn-sm sepa-attendee-notes-export" href="<?php echo esc_url( $export_url ); ?>"><?php esc_html_e( 'Export My Notes (CSV)', 'cem-mobile-app' ); ?></a>
			</div>
			<table id="sepa-attendee-notes-table" class="sepa-attendee-notes-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Edit', 'cem-mobile-app' ); ?></th>
						<th data-sort-key="first_name"><?php esc_html_e( 'First Name', 'cem-mobile-app' ); ?></th>
						<th data-sort-key="last_name"><?php esc_html_e( 'Last Name', 'cem-mobile-app' ); ?></th>
						<th data-sort-key="company"><?php esc_html_e( 'Company', 'cem-mobile-app' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $attendees as $attendee ) : ?>
						<?php
						$has_note      = isset( $user_note_map[ $attendee['key'] ] );
						$company_short = ( strlen( $attendee['company'] ) > 8 ) ? substr( $attendee['company'], 0, 8 ) . '...' : $attendee['company'];
						?>
						<tr data-entry-id="<?php echo esc_attr( $attendee['key'] ); ?>" data-company="<?php echo esc_attr( strtolower( $attendee['company'] ) ); ?>" data-has-note="<?php echo $has_note ? '1' : '0'; ?>">
							<td class="sepa-notes-cell">
								<button type="button" class="sepa-attendee-note-trigger" data-entry-id="<?php echo esc_attr( $attendee['key'] ); ?>" data-attendee-name="<?php echo esc_attr( $attendee['name'] ); ?>" data-attendee-company="<?php echo esc_attr( $attendee['company'] ); ?>" aria-label="<?php esc_attr_e( 'Open attendee notes', 'cem-mobile-app' ); ?>">
									<span class="dashicons dashicons-edit" aria-hidden="true"></span>
								</button>
							</td>
							<td class="sepa-attendee-first-name-cell">
								<?php echo esc_html( $attendee['first_name'] ); ?>
								<span class="sepa-attendee-note-check <?php echo $has_note ? 'is-visible' : ''; ?>" aria-hidden="true" style="<?php echo $has_note ? 'visibility:visible;opacity:1;' : 'visibility:hidden;opacity:0;'; ?>">✔</span>
							</td>
							<td><?php echo esc_html( $attendee['last_name'] ); ?></td>
							<td title="<?php echo esc_attr( $attendee['company'] ); ?>"><?php echo esc_html( $company_short ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div id="sepa-attendee-note-modal" class="sepa-attendee-note-modal" hidden>
			<div class="sepa-attendee-note-modal__backdrop"></div>
			<div class="sepa-attendee-note-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="sepa-attendee-note-modal-title">
				<div class="sepa-attendee-note-modal__header">
					<h3 id="sepa-attendee-note-modal-title"><?php esc_html_e( 'Attendee Notes', 'cem-mobile-app' ); ?></h3>
					<button type="button" class="sepa-attendee-note-modal__close" aria-label="<?php esc_attr_e( 'Close', 'cem-mobile-app' ); ?>">&times;</button>
				</div>
				<div class="sepa-attendee-note-modal__content">
					<p class="sepa-attendee-note-modal__attendee"></p>
					<p class="sepa-attendee-note-modal__company"></p>
					<textarea class="sepa-attendee-note-modal__textarea" rows="8" placeholder="<?php esc_attr_e( 'Add your private notes for this attendee...', 'cem-mobile-app' ); ?>"></textarea>
					<p class="sepa-attendee-note-modal__feedback" aria-live="polite"></p>
				</div>
				<div class="sepa-attendee-note-modal__actions">
					<button type="button" class="btn btn-success btn-sm sepa-attendee-note-save"><?php esc_html_e( 'Save Note', 'cem-mobile-app' ); ?></button>
					<button type="button" class="btn btn-danger btn-sm sepa-attendee-note-delete"><?php esc_html_e( 'Delete Note', 'cem-mobile-app' ); ?></button>
				</div>
			</div>
		</div>
		<?php
		return '<div class="sepa-attendee-notes-root">' . (string) ob_get_clean() . '</div>';
	}

	public function enqueue_assets(): void {
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style(
			'sepa-attendee-notes-app',
			CMA_URL . 'assets/css/attendee-notes.css',
			[],
			CMA_VERSION . '.' . filemtime( CMA_PATH . 'assets/css/attendee-notes.css' )
		);
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script(
			'sepa-attendee-notes-app',
			CMA_URL . 'assets/js/attendee-notes.js',
			[ 'jquery' ],
			CMA_VERSION . '.' . filemtime( CMA_PATH . 'assets/js/attendee-notes.js' ),
			true
		);
		wp_localize_script( 'sepa-attendee-notes-app', 'SEPAAttendeeNotes', $this->script_config() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function script_config(): array {
		$config = [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'sepa_attendee_notes_nonce' ),
			'strings' => [
				'saveError'            => __( 'Unable to save note.', 'cem-mobile-app' ),
				'deleteError'          => __( 'Unable to delete note.', 'cem-mobile-app' ),
				'searchLabel'          => __( 'Search attendees:', 'cem-mobile-app' ),
				'loginError'           => __( 'Could not sign you in. Check your email and try again.', 'cem-mobile-app' ),
				'loginWait'            => __( 'Signing you in...', 'cem-mobile-app' ),
				'registerWait'         => __( 'Creating your account...', 'cem-mobile-app' ),
				'registerDefaultError' => __( 'Could not create your account. Please try again.', 'cem-mobile-app' ),
				'lostPassWait'         => __( 'Sending reset email...', 'cem-mobile-app' ),
				'lostPassDefaultError' => __( 'Could not send reset email. Try again.', 'cem-mobile-app' ),
				'deleteConfirm'        => __( 'Delete this note?', 'cem-mobile-app' ),
			],
		];

		if ( is_user_logged_in() && self::current_user_can_use() ) {
			$config['restUrl']   = rest_url( 'cma/v1/notes/' );
			$config['restNonce'] = wp_create_nonce( 'wp_rest' );
		}

		return $config;
	}

	public function render_auth_block(): string {
		$current_url = ( isset( $_SERVER['REQUEST_URI'] ) )
			? home_url( sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) )
			: home_url( '/' );
		$auth_error   = sanitize_text_field( wp_unslash( $_GET['sepa_auth_error'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$auth_success = sanitize_text_field( wp_unslash( $_GET['sepa_auth_success'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		ob_start();
		?>
		<div class="sepa-attendee-auth-card">
			<h3><?php esc_html_e( 'Attendee Notes Access', 'cem-mobile-app' ); ?></h3>
			<p><?php esc_html_e( 'Enter your email to view and save your private attendee notes. No password needed on this page.', 'cem-mobile-app' ); ?></p>
			<?php if ( '' !== $auth_error ) : ?>
				<div class="sepa-attendee-auth-message sepa-attendee-auth-message--error"><?php echo esc_html( $auth_error ); ?></div>
			<?php elseif ( 'registered' === $auth_success ) : ?>
				<div class="sepa-attendee-auth-message sepa-attendee-auth-message--success"><?php esc_html_e( 'You are signed in. Welcome!', 'cem-mobile-app' ); ?></div>
			<?php endif; ?>
			<form method="post" class="sepa-attendee-auth-form sepa-attendee-auth-form--email" id="sepa-attendee-email-access-form">
				<label for="sepa-attendee-email"><?php esc_html_e( 'Email', 'cem-mobile-app' ); ?></label>
				<input type="email" id="sepa-attendee-email" name="sepa_email" required autocomplete="email" />
				<input type="hidden" name="sepa_attendee_auth_action" value="email_access" />
				<input type="hidden" name="sepa_attendee_redirect_to" value="<?php echo esc_attr( $current_url ); ?>" />
				<?php wp_nonce_field( 'sepa_attendee_notes_email_access_action', 'sepa_attendee_auth_nonce' ); ?>
				<button type="submit" class="btn btn-primary btn-sm"><?php esc_html_e( 'Continue', 'cem-mobile-app' ); ?></button>
				<p class="sepa-attendee-auth-hint"><?php esc_html_e( 'New visitors are added automatically—no confirmation email is sent.', 'cem-mobile-app' ); ?></p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param string[] $keys
	 * @return string[]
	 */
	private function user_note_keys( int $user_id, array $keys ): array {
		$schedule_id = CMA_Settings::get_schedule_id();
		if ( $user_id <= 0 || $schedule_id <= 0 || empty( $keys ) ) {
			return [];
		}

		global $wpdb;
		$table_name   = self::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$query_args   = array_merge( [ $user_id, $schedule_id ], $keys );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic IN list.
		$rows = $wpdb->get_col( $wpdb->prepare( "SELECT attendee_key FROM {$table_name} WHERE user_id = %d AND schedule_id = %d AND attendee_key IN ({$placeholders}) AND note IS NOT NULL AND note <> ''", ...$query_args ) );

		return is_array( $rows ) ? array_map( 'strval', $rows ) : [];
	}

	public function handle_frontend_auth(): void {
		$request_method = isset( $_SERVER['REQUEST_METHOD'] )
			? strtoupper( sanitize_key( wp_unslash( (string) $_SERVER['REQUEST_METHOD'] ) ) )
			: '';
		if ( is_user_logged_in() || 'POST' !== $request_method ) {
			return;
		}

		$action = sanitize_text_field( wp_unslash( $_POST['sepa_attendee_auth_action'] ?? '' ) );
		if ( '' === $action ) {
			return;
		}

		$redirect_to = esc_url_raw( wp_unslash( $_POST['sepa_attendee_redirect_to'] ?? '' ) );
		if ( '' === $redirect_to ) {
			$redirect_to = home_url( '/' );
		}

		if ( 'email_access' === $action || 'login' === $action ) {
			$nonce_action = 'email_access' === $action ? 'sepa_attendee_notes_email_access_action' : 'sepa_attendee_notes_login_action';
			check_admin_referer( $nonce_action, 'sepa_attendee_auth_nonce' );
			$email = sanitize_email( wp_unslash( $_POST['sepa_email'] ?? '' ) );
			if ( '' === $email ) {
				$email = sanitize_email( wp_unslash( $_POST['sepa_login_identifier'] ?? '' ) );
			}

			$user_id = $this->get_or_create_user_by_email( $email );
			if ( is_wp_error( $user_id ) ) {
				wp_safe_redirect( add_query_arg( 'sepa_auth_error', rawurlencode( $user_id->get_error_message() ), $redirect_to ) );
				exit;
			}

			$user = $this->passwordless_sign_in( $user_id );
			if ( is_wp_error( $user ) ) {
				wp_safe_redirect( add_query_arg( 'sepa_auth_error', rawurlencode( $user->get_error_message() ), $redirect_to ) );
				exit;
			}

			wp_safe_redirect( remove_query_arg( [ 'sepa_auth_error', 'sepa_auth_success' ], $redirect_to ) );
			exit;
		}
	}

	public function ajax_login(): void {
		check_ajax_referer( 'sepa_attendee_notes_nonce', 'nonce' );

		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		if ( '' === $email ) {
			$email = sanitize_email( wp_unslash( $_POST['identifier'] ?? '' ) );
		}

		$redirect_to = esc_url_raw( wp_unslash( $_POST['redirect_to'] ?? '' ) );
		$user_id     = $this->get_or_create_user_by_email( $email );
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( [ 'message' => $user_id->get_error_message() ], 400 );
		}

		$user = $this->passwordless_sign_in( $user_id );
		if ( is_wp_error( $user ) ) {
			wp_send_json_error( [ 'message' => $user->get_error_message() ], 500 );
		}

		wp_send_json_success(
			[
				'html'        => $this->shortcode( [] ),
				'redirect_to' => remove_query_arg( [ 'sepa_auth_error', 'sepa_auth_success' ], $redirect_to ?: home_url( '/' ) ),
				'config'      => $this->script_config(),
			]
		);
	}

	public function ajax_register(): void {
		$this->ajax_login();
	}

	public function ajax_lost_password(): void {
		check_ajax_referer( 'sepa_attendee_notes_nonce', 'nonce' );
		$identifier = sanitize_text_field( wp_unslash( $_POST['identifier'] ?? '' ) );
		if ( '' === $identifier ) {
			wp_send_json_error( [ 'message' => __( 'Enter your email or username to reset your password.', 'cem-mobile-app' ) ], 400 );
		}
		$_POST['user_login'] = $identifier; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result              = retrieve_password();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}
		wp_send_json_success( [ 'message' => __( 'Password reset email sent. Check your inbox.', 'cem-mobile-app' ) ] );
	}

	public function ajax_get_html(): void {
		check_ajax_referer( 'sepa_attendee_notes_nonce', 'nonce' );
		if ( ! self::current_user_can_use() ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'cem-mobile-app' ) ], 403 );
		}
		wp_send_json_success(
			[
				'html'   => $this->shortcode( [] ),
				'config' => $this->script_config(),
			]
		);
	}

	public function ajax_is_logged_in(): void {
		check_ajax_referer( 'sepa_attendee_notes_nonce', 'nonce' );
		wp_send_json_success( [ 'logged_in' => is_user_logged_in() ] );
	}

	public function register_rest_routes(): void {
		register_rest_route(
			'cma/v1',
			'/notes/(?P<attendee_key>[^/]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'rest_get_note' ],
					'permission_callback' => [ $this, 'rest_permission' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'rest_save_note' ],
					'permission_callback' => [ $this, 'rest_permission' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'rest_delete_note' ],
					'permission_callback' => [ $this, 'rest_permission' ],
				],
			]
		);
	}

	public function rest_permission(): bool {
		return self::current_user_can_use();
	}

	public function rest_get_note( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$key    = $this->sanitize_attendee_key( (string) $request->get_param( 'attendee_key' ) );
		$result = $this->get_note_for_user( get_current_user_id(), $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( [ 'note' => $result ] );
	}

	public function rest_save_note( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$key    = $this->sanitize_attendee_key( (string) $request->get_param( 'attendee_key' ) );
		$result = $this->save_note_for_user( get_current_user_id(), $key, (string) $request->get_param( 'note' ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			[
				'message' => __( 'Note saved.', 'cem-mobile-app' ),
				'note'    => $result,
			]
		);
	}

	public function rest_delete_note( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$key    = $this->sanitize_attendee_key( (string) $request->get_param( 'attendee_key' ) );
		$result = $this->delete_note_for_user( get_current_user_id(), $key );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( [ 'message' => __( 'Note deleted.', 'cem-mobile-app' ) ] );
	}

	public function ajax_get_note(): void {
		check_ajax_referer( 'sepa_attendee_notes_nonce', 'nonce' );
		if ( ! self::current_user_can_use() ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'cem-mobile-app' ) ], 403 );
		}
		$key    = $this->request_attendee_key();
		$result = $this->get_note_for_user( get_current_user_id(), $key );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 400 );
		}
		wp_send_json_success( [ 'note' => $result ] );
	}

	public function ajax_save_note(): void {
		check_ajax_referer( 'sepa_attendee_notes_nonce', 'nonce' );
		if ( ! self::current_user_can_use() ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'cem-mobile-app' ) ], 403 );
		}
		$key    = $this->request_attendee_key();
		$note   = wp_unslash( $_POST['note'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$result = $this->save_note_for_user( get_current_user_id(), $key, (string) $note );
		if ( is_wp_error( $result ) ) {
			$code = in_array( $result->get_error_code(), [ 'invalid_entry', 'no_schedule' ], true ) ? 400 : 500;
			wp_send_json_error( [ 'message' => $result->get_error_message() ], $code );
		}
		wp_send_json_success(
			[
				'message' => __( 'Note saved.', 'cem-mobile-app' ),
				'note'    => $result,
			]
		);
	}

	public function ajax_delete_note(): void {
		check_ajax_referer( 'sepa_attendee_notes_nonce', 'nonce' );
		if ( ! self::current_user_can_use() ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'cem-mobile-app' ) ], 403 );
		}
		$key    = $this->request_attendee_key();
		$result = $this->delete_note_for_user( get_current_user_id(), $key );
		if ( is_wp_error( $result ) ) {
			$code = in_array( $result->get_error_code(), [ 'invalid_entry', 'no_schedule' ], true ) ? 400 : 500;
			wp_send_json_error( [ 'message' => $result->get_error_message() ], $code );
		}
		wp_send_json_success( [ 'message' => __( 'Note deleted.', 'cem-mobile-app' ) ] );
	}

	public function export_csv(): void {
		if ( ! self::current_user_can_use() ) {
			wp_die( esc_html__( 'Unauthorized.', 'cem-mobile-app' ) );
		}
		check_admin_referer( 'sepa_attendee_notes_export_csv' );

		$schedule_id = CMA_Settings::get_schedule_id();
		if ( $schedule_id <= 0 ) {
			wp_die( esc_html__( 'No event is selected for the app.', 'cem-mobile-app' ) );
		}

		$attendees = [];
		foreach ( CMA_Attendees::get_for_schedule( $schedule_id ) as $attendee ) {
			$attendees[ $attendee['key'] ] = $attendee;
		}

		global $wpdb;
		$table_name = self::table_name();
		$user_id    = get_current_user_id();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$notes = $wpdb->get_results(
			$wpdb->prepare( "SELECT attendee_key, note, updated_at FROM {$table_name} WHERE user_id = %d AND schedule_id = %d ORDER BY updated_at DESC", $user_id, $schedule_id ),
			ARRAY_A
		);

		$filename = 'sepa-attendee-notes-' . $schedule_id . '-' . gmdate( 'Ymd-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}

		fputcsv( $output, [ 'Attendee Key', 'First Name', 'Last Name', 'Company', 'Note', 'Updated At' ] );
		if ( ! empty( $notes ) ) {
			foreach ( $notes as $row ) {
				$key = (string) ( $row['attendee_key'] ?? '' );
				if ( '' === $key || ! isset( $attendees[ $key ] ) ) {
					continue;
				}
				$attendee = $attendees[ $key ];
				fputcsv(
					$output,
					[
						$key,
						$attendee['first_name'],
						$attendee['last_name'],
						$attendee['company'],
						isset( $row['note'] ) ? wp_strip_all_tags( (string) $row['note'] ) : '',
						(string) ( $row['updated_at'] ?? '' ),
					]
				);
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $output );
		exit;
	}

	public function get_note_for_user( int $user_id, string $attendee_key ): string|WP_Error {
		$user_id      = absint( $user_id );
		$attendee_key = $this->sanitize_attendee_key( $attendee_key );
		$schedule_id  = CMA_Settings::get_schedule_id();
		if ( $user_id <= 0 || '' === $attendee_key ) {
			return new WP_Error( 'invalid_entry', __( 'Invalid attendee.', 'cem-mobile-app' ) );
		}
		if ( $schedule_id <= 0 ) {
			return '';
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$note = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT note FROM `' . esc_sql( self::table_name() ) . '` WHERE user_id = %d AND schedule_id = %d AND attendee_key = %s LIMIT 1',
				$user_id,
				$schedule_id,
				$attendee_key
			)
		);

		return is_string( $note ) ? $note : '';
	}

	public function save_note_for_user( int $user_id, string $attendee_key, string $note ): string|WP_Error {
		$user_id      = absint( $user_id );
		$attendee_key = $this->sanitize_attendee_key( $attendee_key );
		$note         = wp_kses_post( $note );
		$parsed       = CMA_Attendees::parse_key( $attendee_key );
		$schedule_id  = CMA_Settings::get_schedule_id();

		if ( $schedule_id <= 0 ) {
			return new WP_Error( 'no_schedule', __( 'No event is selected for the app.', 'cem-mobile-app' ) );
		}

		if ( $user_id <= 0 || '' === $attendee_key || $parsed['order_id'] <= 0 || '' === $parsed['attendee_id'] ) {
			return new WP_Error( 'invalid_entry', __( 'Invalid attendee.', 'cem-mobile-app' ) );
		}

		global $wpdb;
		$table_name = self::table_name();
		$now        = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM `' . esc_sql( $table_name ) . '` WHERE user_id = %d AND schedule_id = %d AND attendee_key = %s LIMIT 1',
				$user_id,
				$schedule_id,
				$attendee_key
			)
		);

		if ( $existing_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table_name,
				[
					'note'       => $note,
					'updated_at' => $now,
				],
				[ 'id' => absint( $existing_id ) ],
				[ '%s', '%s' ],
				[ '%d' ]
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->insert(
				$table_name,
				[
					'user_id'      => $user_id,
					'schedule_id'  => $schedule_id,
					'attendee_key' => $attendee_key,
					'note'         => $note,
					'updated_at'   => $now,
				],
				[ '%d', '%d', '%s', '%s', '%s' ]
			);
		}

		if ( false === $result ) {
			return new WP_Error( 'save_failed', __( 'Failed to save note.', 'cem-mobile-app' ) );
		}

		return $note;
	}

	public function delete_note_for_user( int $user_id, string $attendee_key ): true|WP_Error {
		$user_id      = absint( $user_id );
		$attendee_key = $this->sanitize_attendee_key( $attendee_key );
		$schedule_id  = CMA_Settings::get_schedule_id();
		if ( $user_id <= 0 || '' === $attendee_key ) {
			return new WP_Error( 'invalid_entry', __( 'Invalid attendee.', 'cem-mobile-app' ) );
		}
		if ( $schedule_id <= 0 ) {
			return new WP_Error( 'no_schedule', __( 'No event is selected for the app.', 'cem-mobile-app' ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->delete(
			self::table_name(),
			[
				'user_id'      => $user_id,
				'schedule_id'  => $schedule_id,
				'attendee_key' => $attendee_key,
			],
			[ '%d', '%d', '%s' ]
		);

		if ( false === $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete note.', 'cem-mobile-app' ) );
		}

		return true;
	}

	private function request_attendee_key(): string {
		$raw = wp_unslash( $_POST['attendee_key'] ?? $_POST['entry_id'] ?? '' );

		return $this->sanitize_attendee_key( $raw );
	}

	private function sanitize_attendee_key( mixed $key ): string {
		$key = sanitize_text_field( (string) $key );

		return rawurldecode( $key );
	}

	private function get_or_create_user_by_email( string $email ): int|WP_Error {
		$email = sanitize_email( trim( $email ) );
		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'cem-mobile-app' ) );
		}

		$user = get_user_by( 'email', $email );
		if ( $user instanceof WP_User ) {
			if ( ! self::user_may_passwordless_access( $user ) ) {
				return new WP_Error( 'non_member_role', self::non_member_access_message() );
			}

			return (int) $user->ID;
		}

		$base_login = sanitize_user( current( explode( '@', $email ) ), true );
		if ( '' === $base_login ) {
			$base_login = 'attendee';
		}
		$user_login = $base_login;
		$counter    = 1;
		while ( username_exists( $user_login ) ) {
			$user_login = $base_login . $counter;
			++$counter;
		}

		$suppress = static fn() => false;
		add_filter( 'wp_send_new_user_notification_to_user', $suppress, 99 );
		add_filter( 'wp_send_new_user_notification_to_admin', $suppress, 99 );
		$user_id = wp_create_user( $user_login, wp_generate_password( 24, true, true ), $email );
		remove_filter( 'wp_send_new_user_notification_to_user', $suppress, 99 );
		remove_filter( 'wp_send_new_user_notification_to_admin', $suppress, 99 );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		$update_result = wp_update_user(
			[
				'ID'   => $user_id,
				'role' => 'subscriber',
			]
		);
		if ( is_wp_error( $update_result ) ) {
			return $update_result;
		}

		return (int) $user_id;
	}

	private function passwordless_sign_in( int $user_id ): WP_User|WP_Error {
		$user_id = absint( $user_id );
		$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;
		if ( ! $user instanceof WP_User ) {
			return new WP_Error( 'invalid_user', __( 'Could not sign you in. Please try again.', 'cem-mobile-app' ) );
		}
		if ( ! self::user_may_passwordless_access( $user ) ) {
			return new WP_Error( 'non_member_role', self::non_member_access_message() );
		}

		wp_set_current_user( $user->ID );

		$set_logged_in_cookie = static function ( string $cookie ): void {
			if ( defined( 'LOGGED_IN_COOKIE' ) ) {
				$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
			}
		};
		add_action( 'set_logged_in_cookie', $set_logged_in_cookie );
		wp_set_auth_cookie( $user->ID, true );
		remove_action( 'set_logged_in_cookie', $set_logged_in_cookie );

		do_action( 'sepa_attendee_notes_passwordless_login', $user );

		return $user;
	}
}
