<?php

defined( 'ABSPATH' ) || exit;

$schedule_id    = CMA_Settings::get_schedule_id();
$schedules      = class_exists( 'CEM_Settings' ) ? CEM_Settings::get_schedule_options() : [];
$home_message   = (string) get_option( 'maps_home_screen_message', '' );
$home_images    = CMA_Settings::get_home_screen_image_ids();
$event_title    = (string) get_option( CMA_Settings::OPTION_EVENT_TITLE, '' );
$schedule_title = CMA_Settings::get_schedule_title();

?>
<div class="cma-upload-root"
	data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
	data-nonce="<?php echo esc_attr( wp_create_nonce( 'cma_upload_image' ) ); ?>">
<div class="card mb-4">
	<div class="card-header"><?php esc_html_e( 'Event Details', 'cem-mobile-app' ); ?></div>
	<div class="card-body">
		<p class="text-muted"><?php esc_html_e( 'Choose the CEM schedule the attendee app displays, then set the header title, between-events message, home copy, and map images.', 'cem-mobile-app' ); ?></p>

		<div class="form-group">
			<label for="cma_schedule_id"><?php esc_html_e( 'Event schedule', 'cem-mobile-app' ); ?></label>
			<select class="form-control" id="cma_schedule_id" name="cma_schedule_id">
				<option value=""><?php esc_html_e( '— Select a schedule —', 'cem-mobile-app' ); ?></option>
				<?php foreach ( $schedules as $schedule ) : ?>
					<option value="<?php echo esc_attr( (string) $schedule['id'] ); ?>" <?php selected( $schedule_id, (int) $schedule['id'] ); ?>><?php echo esc_html( $schedule['title'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<small class="text-muted"><?php esc_html_e( 'Used for the Event Itinerary tab, attendee notes, and app alerts. Notes and notifications stay with this schedule and do not carry over if you switch to another event.', 'cem-mobile-app' ); ?></small>
		</div>

		<div class="form-group<?php echo $schedule_id > 0 ? '' : ' d-none'; ?>" id="cma-event-title-wrap">
			<label for="cma_event_title"><?php esc_html_e( 'Event title', 'cem-mobile-app' ); ?></label>
			<input type="text" class="form-control" id="cma_event_title" name="cma_event_title" value="<?php echo esc_attr( $event_title ); ?>" placeholder="<?php echo esc_attr( $schedule_title ); ?>" />
			<small class="text-muted"><?php esc_html_e( 'Shown on the right side of the app header. Leave blank to use the schedule name, or enter a shorter label if that name is too long.', 'cem-mobile-app' ); ?></small>
		</div>

		<div class="form-group">
			<label for="cma_no_schedule_message"><?php esc_html_e( 'Message when no schedule is selected', 'cem-mobile-app' ); ?></label>
			<textarea class="form-control" id="cma_no_schedule_message" name="cma_no_schedule_message" rows="4"><?php echo esc_textarea( CMA_Settings::get_no_schedule_message() ); ?></textarea>
			<small class="text-muted"><?php esc_html_e( 'Shown on the app page instead of the event tabs when Event schedule is empty.', 'cem-mobile-app' ); ?></small>
		</div>

		<hr class="my-4">
		<h5 class="mb-3"><?php esc_html_e( 'App Home Screen Message', 'cem-mobile-app' ); ?></h5>
		<p class="text-muted"><?php esc_html_e( 'Optional content for the app home tab. Rendered by [app_home_screen_message]. Map images are shown with [app_home_screen_images].', 'cem-mobile-app' ); ?></p>
		<textarea id="cma_home_screen_message" name="cma_home_screen_message" class="form-control" rows="8"><?php echo esc_textarea( $home_message ); ?></textarea>

		<h6 class="mt-3"><?php esc_html_e( 'Map Images', 'cem-mobile-app' ); ?></h6>
		<p class="text-muted"><?php esc_html_e( 'Upload one or more images from your computer. Each file is saved as soon as it uploads, and a thumbnail appears immediately. In the file picker, select multiple files (Ctrl or Command + click), or click Upload again to add more.', 'cem-mobile-app' ); ?></p>
		<ul id="maps-home-screen-images-list" class="maps-home-images-admin-list">
			<?php foreach ( $home_images as $image_id ) : ?>
				<?php
				$file_url = CMA_Settings::get_app_image_url( $image_id );
				$thumb    = wp_get_attachment_image_src( $image_id, 'thumbnail' );
				$url      = ( $thumb && ! empty( $thumb[0] ) && (int) ( $thumb[1] ?? 0 ) > 1 ) ? $thumb[0] : $file_url;
				if ( ! $url ) {
					continue;
				}
				?>
				<li class="maps-home-image-item" data-id="<?php echo esc_attr( (string) $image_id ); ?>">
					<img src="<?php echo esc_url( $url ); ?>" alt="" width="50" height="50" />
					<button type="button" class="button-link maps-home-image-remove"><?php esc_html_e( 'Remove', 'cem-mobile-app' ); ?></button>
				</li>
			<?php endforeach; ?>
		</ul>
		<div id="maps-home-screen-images-inputs">
			<?php foreach ( $home_images as $image_id ) : ?>
				<input type="hidden" name="maps_home_screen_image_ids[]" value="<?php echo esc_attr( (string) $image_id ); ?>" />
			<?php endforeach; ?>
		</div>
		<p class="cma-upload-row">
			<label class="btn btn-outline-secondary btn-sm cma-upload-btn" id="maps-home-screen-images-add">
				<span class="cma-upload-btn-label"><?php esc_html_e( 'Upload images', 'cem-mobile-app' ); ?></span>
				<input type="file" id="cma-map-images-file" class="cma-upload-btn-input" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml,.svg" multiple />
			</label>
		</p>
		<p class="cma-upload-status small text-muted" id="cma-map-images-status" hidden></p>

		<button type="button" class="btn btn-success cma-save-content-tab" data-tab="cma-event-details"><?php esc_html_e( 'Save', 'cem-mobile-app' ); ?></button>
	</div>
</div>
</div>
