<?php

defined( 'ABSPATH' ) || exit;

$notifications  = CMA_Notifications::get_recent_rows( 20 );
$app_url        = CMA_Settings::get_app_url();
$schedule_id    = CMA_Settings::get_schedule_id();
$schedule_title = CMA_Settings::get_schedule_title();
$has_schedule   = $schedule_id > 0;

?>
<div class="card mb-4">
	<div class="card-header"><?php esc_html_e( 'App Notifications', 'cem-mobile-app' ); ?></div>
	<div class="card-body">
		<?php if ( $has_schedule ) : ?>
			<p class="text-muted">
				<?php
				printf(
					/* translators: %s: selected event schedule title */
					esc_html__( 'The Alerts history is stored only for %s. Device permission stays on the phone and does not need to be granted again for a new event.', 'cem-mobile-app' ),
					esc_html( $schedule_title )
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="cma_create_notification" />
			<?php wp_nonce_field( 'cma_create_notification' ); ?>
			<div class="form-group">
				<label for="maps_notification_title"><?php esc_html_e( 'Title', 'cem-mobile-app' ); ?></label>
				<input type="text" class="form-control" id="maps_notification_title" name="maps_notification_title" required />
			</div>
			<div class="form-group">
				<label for="maps_notification_body"><?php esc_html_e( 'Body', 'cem-mobile-app' ); ?></label>
				<textarea class="form-control" id="maps_notification_body" name="maps_notification_body" rows="4" required></textarea>
			</div>
			<div class="form-group">
				<label for="maps_notification_target_url"><?php esc_html_e( 'Target URL', 'cem-mobile-app' ); ?></label>
				<input type="url" class="form-control" id="maps_notification_target_url" name="maps_notification_target_url" value="<?php echo esc_attr( $app_url ); ?>" />
			</div>
			<div class="form-group">
				<label for="maps_notification_schedule_at"><?php esc_html_e( 'Schedule (optional)', 'cem-mobile-app' ); ?></label>
				<input type="datetime-local" class="form-control" id="maps_notification_schedule_at" name="maps_notification_schedule_at" />
			</div>
			<button type="submit" class="btn btn-primary"><?php esc_html_e( 'Send Notification', 'cem-mobile-app' ); ?></button>
		</form>
		<?php else : ?>
			<p class="alert alert-warning mb-0"><?php esc_html_e( 'Select an event schedule in Event Details before sending notifications. Alerts are stored per event and do not carry over when you switch schedules.', 'cem-mobile-app' ); ?></p>
		<?php endif; ?>

		<h5 class="mt-4"><?php esc_html_e( 'Recent Notifications', 'cem-mobile-app' ); ?></h5>
		<form id="cma-bulk-delete-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete selected notifications?', 'cem-mobile-app' ) ); ?>');">
			<input type="hidden" name="action" value="cma_bulk_delete_notifications" />
			<?php wp_nonce_field( 'cma_bulk_delete_notifications' ); ?>
			<p>
				<button type="submit" class="btn btn-outline-secondary btn-sm"><?php esc_html_e( 'Delete Selected', 'cem-mobile-app' ); ?></button>
			</p>
		</form>
		<table class="table table-striped table-sm">
			<thead>
				<tr>
					<th style="width:32px;"><input type="checkbox" id="cma-select-all-notifications" aria-label="<?php esc_attr_e( 'Select all notifications', 'cem-mobile-app' ); ?>" /></th>
					<th><?php esc_html_e( 'Title', 'cem-mobile-app' ); ?></th>
					<th><?php esc_html_e( 'Status', 'cem-mobile-app' ); ?></th>
					<th><?php esc_html_e( 'Created', 'cem-mobile-app' ); ?></th>
					<th><?php esc_html_e( 'Scheduled', 'cem-mobile-app' ); ?></th>
					<th><?php esc_html_e( 'Sent', 'cem-mobile-app' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'cem-mobile-app' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $notifications ) ) : ?>
					<tr><td colspan="7"><?php esc_html_e( 'No notifications yet.', 'cem-mobile-app' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $notifications as $item ) : ?>
						<tr>
							<td><input type="checkbox" class="cma-notification-row-checkbox" form="cma-bulk-delete-form" name="notification_ids[]" value="<?php echo esc_attr( (string) absint( $item->id ) ); ?>" /></td>
							<td><?php echo esc_html( $item->title ); ?></td>
							<td><?php echo esc_html( $item->status ); ?></td>
							<td><?php echo esc_html( $item->created_at ); ?></td>
							<td><?php echo esc_html( $item->scheduled_for ? $item->scheduled_for : '-' ); ?></td>
							<td><?php echo esc_html( $item->sent_at ? $item->sent_at : '-' ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this notification?', 'cem-mobile-app' ) ); ?>');">
									<input type="hidden" name="action" value="cma_delete_notification" />
									<input type="hidden" name="notification_id" value="<?php echo esc_attr( (string) absint( $item->id ) ); ?>" />
									<?php wp_nonce_field( 'cma_delete_notification' ); ?>
									<button type="submit" class="btn btn-sm btn-outline-danger"><?php esc_html_e( 'Delete', 'cem-mobile-app' ); ?></button>
								</form>
							</td>
						</tr>
						<?php if ( ! empty( $item->last_error ) ) : ?>
							<tr>
								<td colspan="7"><em><?php echo esc_html( $item->last_error ); ?></em></td>
							</tr>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
