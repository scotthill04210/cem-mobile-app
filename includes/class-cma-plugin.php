<?php

defined( 'ABSPATH' ) || exit;

class CMA_Plugin {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		new CMA_PWA();
		new CMA_Notifications();
		new CMA_Attendee_Notes();
		new CMA_Shortcodes();
		new CMA_Admin();
		new CMA_App_Page();

		add_action( 'init', [ $this, 'maybe_upgrade' ] );
	}

	public function maybe_upgrade(): void {
		$stored = (string) get_option( 'cma_plugin_version', '' );
		if ( CMA_VERSION === $stored ) {
			return;
		}

		CMA_Notifications::create_table();
		CMA_Notifications::ensure_columns();
		CMA_Attendee_Notes::create_table();
		CMA_Attendee_Notes::ensure_columns();
		CMA_Attendee_Notes::register_caps();
		update_option( 'cma_plugin_version', CMA_VERSION );
	}
}
