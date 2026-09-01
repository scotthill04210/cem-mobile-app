<?php
/**
 * Plugin Name:       CEM Mobile App
 * Description:       Mobile app page shell, PWA install, notifications, and attendee notes for Convention Event Manager.
 * Version:           1.2.8
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Requires Plugins:  convention-event-manager
 * Update URI:        https://github.com/scotthill04210/cem-mobile-app
 * Author:            Local Image & Scott Hill
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cem-mobile-app
 */

defined( 'ABSPATH' ) || exit;

define( 'CMA_VERSION', '1.2.8' );
define( 'CMA_FILE', __FILE__ );
define( 'CMA_PATH', plugin_dir_path( __FILE__ ) );
define( 'CMA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Whether Convention Event Manager is loaded.
 */
function cma_cem_is_active(): bool {
	return defined( 'CEM_VERSION' ) && class_exists( 'CEM_Loader' );
}

/**
 * Admin notice when CEM is missing.
 */
function cma_missing_cem_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'CEM Mobile App requires Convention Event Manager to be installed and active. The Mobile App plugin has been deactivated.', 'cem-mobile-app' );
	echo '</p></div>';
}

/**
 * Deactivate this plugin when CEM is not available.
 */
function cma_deactivate_self(): void {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	deactivate_plugins( plugin_basename( CMA_FILE ) );

	if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Clearing WP's activate flag after self-deactivation.
		unset( $_GET['activate'] );
	}
}

/**
 * Boot after CEM has had a chance to load.
 */
function cma_boot(): void {
	if ( ! cma_cem_is_active() ) {
		add_action( 'admin_notices', 'cma_missing_cem_notice' );
		add_action( 'admin_init', 'cma_deactivate_self' );
		return;
	}

	require_once CMA_PATH . 'includes/class-cma-settings.php';
	require_once CMA_PATH . 'includes/class-cma-app-page.php';
	require_once CMA_PATH . 'includes/class-cma-pwa.php';
	require_once CMA_PATH . 'includes/class-cma-notifications.php';
	require_once CMA_PATH . 'includes/class-cma-attendees.php';
	require_once CMA_PATH . 'includes/class-cma-attendee-notes.php';
	require_once CMA_PATH . 'includes/class-cma-shortcodes.php';
	require_once CMA_PATH . 'includes/class-cma-admin.php';
	require_once CMA_PATH . 'includes/class-cma-updater.php';
	require_once CMA_PATH . 'includes/class-cma-plugin.php';

	CMA_Updater::init();

	CMA_Plugin::instance();
}

add_action( 'plugins_loaded', 'cma_boot', 20 );

/**
 * Activation: require CEM, create tables, ensure /app page, flush rewrites.
 */
function cma_activate(): void {
	if ( ! cma_cem_is_active() ) {
		deactivate_plugins( plugin_basename( CMA_FILE ) );
		wp_die(
			esc_html__( 'CEM Mobile App requires Convention Event Manager to be installed and active.', 'cem-mobile-app' ),
			esc_html__( 'Plugin dependency missing', 'cem-mobile-app' ),
			[ 'back_link' => true ]
		);
	}

	require_once CMA_PATH . 'includes/class-cma-settings.php';
	require_once CMA_PATH . 'includes/class-cma-app-page.php';
	require_once CMA_PATH . 'includes/class-cma-pwa.php';
	require_once CMA_PATH . 'includes/class-cma-notifications.php';
	require_once CMA_PATH . 'includes/class-cma-attendee-notes.php';

	CMA_Notifications::create_table();
	CMA_Notifications::ensure_columns();
	CMA_Attendee_Notes::create_table();
	CMA_Attendee_Notes::ensure_columns();
	CMA_Attendee_Notes::register_caps();
	CMA_App_Page::ensure_page();
	CMA_PWA::register_rewrite_rules();
	flush_rewrite_rules();
	update_option( 'cma_plugin_version', CMA_VERSION );
}

register_activation_hook( CMA_FILE, 'cma_activate' );

register_deactivation_hook(
	CMA_FILE,
	static function (): void {
		flush_rewrite_rules();
	}
);
