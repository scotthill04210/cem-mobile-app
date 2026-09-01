<?php

defined( 'ABSPATH' ) || exit;

/**
 * WordPress dashboard updates from the public GitHub repository.
 */
class CMA_Updater {

	public const REPO_URL = 'https://github.com/scotthill04210/cem-mobile-app/';

	public static function init(): void {
		$library = CMA_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! is_readable( $library ) ) {
			return;
		}

		require_once $library;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			self::REPO_URL,
			CMA_FILE,
			'cem-mobile-app'
		);
		$checker->setBranch( 'main' );
	}
}
