<?php

defined( 'ABSPATH' ) || exit;

$slug    = CMA_Settings::get_app_slug();
$app_url = CMA_Settings::get_app_url();
$logo_id = CMA_Settings::get_header_logo_id();

?>
<div id="cma-mobile-app-settings"
	class="cma-upload-root"
	data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'cma_upload_image' ) ); ?>">
<div class="card mb-4">
	<div class="card-header"><?php esc_html_e( 'Mobile App Settings', 'cem-mobile-app' ); ?></div>
	<div class="card-body">
		<p class="text-muted"><?php esc_html_e( 'Configure the attendee app page, header logo, and push credentials. Event copy, maps, and notifications live under App Content.', 'cem-mobile-app' ); ?></p>

		<div class="form-group">
			<label for="cma_app_page_slug"><?php esc_html_e( 'App page URL slug', 'cem-mobile-app' ); ?></label>
			<div class="input-group">
				<div class="input-group-prepend"><span class="input-group-text"><?php echo esc_html( untrailingslashit( home_url( '/' ) ) ); ?>/</span></div>
				<input type="text" class="form-control" id="cma_app_page_slug" name="cma_app_page_slug" value="<?php echo esc_attr( $slug ); ?>" />
			</div>
			<small class="text-muted">
				<?php
				printf(
					/* translators: %s: app URL */
					esc_html__( 'Defaults to /app. Current URL: %s', 'cem-mobile-app' ),
					'<code>' . esc_html( $app_url ) . '</code>'
				);
				?>
				<a href="<?php echo esc_url( $app_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View page', 'cem-mobile-app' ); ?></a>
			</small>
		</div>

		<div class="form-group">
			<label><?php esc_html_e( 'Header logo', 'cem-mobile-app' ); ?></label>
			<div id="cma-header-logo-preview" class="cma-header-logo-preview">
				<?php
				$logo_url = $logo_id ? CMA_Settings::get_app_image_url( $logo_id ) : '';
				if ( $logo_url ) {
					echo '<img src="' . esc_url( $logo_url ) . '" alt="" />';
				}
				?>
			</div>
			<input type="hidden" id="cma_header_logo_id" name="cma_header_logo_id" value="<?php echo esc_attr( (string) $logo_id ); ?>" />
			<p class="mb-1 cma-upload-row">
				<label class="btn btn-outline-secondary btn-sm cma-upload-btn" id="cma-header-logo-add">
					<span class="cma-upload-btn-label"><?php esc_html_e( 'Upload logo', 'cem-mobile-app' ); ?></span>
					<input type="file" id="cma-header-logo-file" class="cma-upload-btn-input" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml,.svg" />
				</label>
				<button type="button" class="btn btn-link btn-sm" id="cma-header-logo-remove"<?php echo $logo_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'cem-mobile-app' ); ?></button>
			</p>
			<p class="cma-upload-status small text-muted mb-1" id="cma-header-logo-status" hidden></p>
			<small class="text-muted"><?php esc_html_e( 'Upload a logo from your computer. The Media Library is not shown. PNG, JPG, GIF, WebP, or SVG.', 'cem-mobile-app' ); ?></small>
		</div>

		<?php $colors = CMA_Settings::get_app_colors(); ?>
		<div class="form-group">
			<span class="d-block mb-2"><?php esc_html_e( 'Colors', 'cem-mobile-app' ); ?></span>
			<div class="cma-color-fields">
				<div class="cma-color-field">
					<label for="cma_color_primary"><?php esc_html_e( 'Primary', 'cem-mobile-app' ); ?></label>
					<div class="cma-color-field__row">
						<input type="color" class="cma-color-picker" value="<?php echo esc_attr( $colors['primary'] ); ?>" data-target="cma_color_primary" aria-label="<?php esc_attr_e( 'Primary color', 'cem-mobile-app' ); ?>" />
						<input type="text" class="form-control form-control-sm cma-color-hex" id="cma_color_primary" name="cma_color_primary" value="<?php echo esc_attr( $colors['primary'] ); ?>" maxlength="7" spellcheck="false" />
					</div>
				</div>
				<div class="cma-color-field">
					<label for="cma_color_secondary"><?php esc_html_e( 'Secondary', 'cem-mobile-app' ); ?></label>
					<div class="cma-color-field__row">
						<input type="color" class="cma-color-picker" value="<?php echo esc_attr( $colors['secondary'] ); ?>" data-target="cma_color_secondary" aria-label="<?php esc_attr_e( 'Secondary color', 'cem-mobile-app' ); ?>" />
						<input type="text" class="form-control form-control-sm cma-color-hex" id="cma_color_secondary" name="cma_color_secondary" value="<?php echo esc_attr( $colors['secondary'] ); ?>" maxlength="7" spellcheck="false" />
					</div>
				</div>
				<div class="cma-color-field">
					<label for="cma_color_button_text"><?php esc_html_e( 'Button text', 'cem-mobile-app' ); ?></label>
					<div class="cma-color-field__row">
						<input type="color" class="cma-color-picker" value="<?php echo esc_attr( $colors['button_text'] ); ?>" data-target="cma_color_button_text" aria-label="<?php esc_attr_e( 'Button text color', 'cem-mobile-app' ); ?>" />
						<input type="text" class="form-control form-control-sm cma-color-hex" id="cma_color_button_text" name="cma_color_button_text" value="<?php echo esc_attr( $colors['button_text'] ); ?>" maxlength="7" spellcheck="false" />
					</div>
				</div>
			</div>
			<small class="text-muted"><?php esc_html_e( 'Primary is the header title and filled button backgrounds. Secondary is links and icons outside the bottom navigation. Button text is the label on filled buttons.', 'cem-mobile-app' ); ?></small>
		</div>

		<hr class="my-4">
		<h5 class="mb-3"><?php esc_html_e( 'OneSignal', 'cem-mobile-app' ); ?></h5>

		<div class="form-group">
			<label for="maps_onesignal_app_id"><?php esc_html_e( 'OneSignal App ID', 'cem-mobile-app' ); ?></label>
			<input type="text" class="form-control" id="maps_onesignal_app_id" name="maps_onesignal_app_id" value="<?php echo esc_attr( get_option( 'maps_onesignal_app_id', '' ) ); ?>" />
		</div>
		<div class="form-group">
			<label for="maps_onesignal_rest_api_key"><?php esc_html_e( 'OneSignal REST API Key', 'cem-mobile-app' ); ?></label>
			<input type="password" class="form-control" id="maps_onesignal_rest_api_key" name="maps_onesignal_rest_api_key" value="<?php echo esc_attr( get_option( 'maps_onesignal_rest_api_key', '' ) ); ?>" autocomplete="off" />
			<small class="text-muted"><?php esc_html_e( 'Leave blank when saving to keep the current key.', 'cem-mobile-app' ); ?></small>
		</div>
		<div class="form-group">
			<div class="form-check">
				<input type="checkbox" class="form-check-input" id="maps_onesignal_debug_mode" name="maps_onesignal_debug_mode" value="1" <?php checked( 1, absint( get_option( 'maps_onesignal_debug_mode', 0 ) ) ); ?> />
				<label class="form-check-label" for="maps_onesignal_debug_mode"><?php esc_html_e( 'Enable front-end debug messages for OneSignal init and permission flow.', 'cem-mobile-app' ); ?></label>
			</div>
		</div>
		<div class="form-group">
			<label for="maps_onesignal_test_subscription_id"><?php esc_html_e( 'Test Web Subscription ID', 'cem-mobile-app' ); ?></label>
			<input type="text" class="form-control" id="maps_onesignal_test_subscription_id" name="maps_onesignal_test_subscription_id" value="<?php echo esc_attr( get_option( 'maps_onesignal_test_subscription_id', '' ) ); ?>" />
			<small class="text-muted"><?php esc_html_e( 'Optional: Web Push Subscription ID for single-device testing.', 'cem-mobile-app' ); ?></small>
			<div class="form-check mt-2">
				<input type="checkbox" class="form-check-input" id="maps_onesignal_use_test_subscription_id" name="maps_onesignal_use_test_subscription_id" value="1" <?php checked( 1, absint( get_option( 'maps_onesignal_use_test_subscription_id', 0 ) ) ); ?> />
				<label class="form-check-label" for="maps_onesignal_use_test_subscription_id"><?php esc_html_e( 'Use Test Web Subscription ID for sends (targets only that single device).', 'cem-mobile-app' ); ?></label>
			</div>
		</div>

		<button type="button" class="btn btn-success cem-save-settings-tab" data-tab="mobile-app"><?php esc_html_e( 'Save', 'cem-mobile-app' ); ?></button>
	</div>
</div>

<div class="card mb-4">
	<div class="card-header"><?php esc_html_e( 'Cache Controls', 'cem-mobile-app' ); ?></div>
	<div class="card-body">
		<p class="text-muted"><?php esc_html_e( 'Use this if the app shell seems stale on devices. This invalidates the service worker cache name so fresh content is cached on next load.', 'cem-mobile-app' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cma_clear_app_cache" />
			<?php wp_nonce_field( 'cma_clear_app_cache' ); ?>
			<button type="submit" class="btn btn-outline-secondary"><?php esc_html_e( 'Clear App Cache', 'cem-mobile-app' ); ?></button>
		</form>
	</div>
</div>
</div>
