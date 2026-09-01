<?php

defined( 'ABSPATH' ) || exit;

?>
<div class="cem-panel" id="panel-app-content">

	<h2 class="mb-4"><?php esc_html_e( 'App Content', 'cem-mobile-app' ); ?></h2>

	<div class="row">

		<div class="col-md-3 mb-4">
			<select class="form-control d-block d-md-none mb-3" id="cma-app-content-tab-select" aria-label="<?php esc_attr_e( 'App Content section', 'cem-mobile-app' ); ?>">
				<option value="cma-event-details"><?php esc_html_e( 'Event Details', 'cem-mobile-app' ); ?></option>
				<option value="cma-app-notifications"><?php esc_html_e( 'App Notifications', 'cem-mobile-app' ); ?></option>
			</select>

			<div class="list-group d-none d-md-block" id="cma-app-content-tab-nav" role="tablist">
				<a href="#" class="list-group-item list-group-item-action active" data-tab="cma-event-details"><?php esc_html_e( 'Event Details', 'cem-mobile-app' ); ?></a>
				<a href="#" class="list-group-item list-group-item-action" data-tab="cma-app-notifications"><?php esc_html_e( 'App Notifications', 'cem-mobile-app' ); ?></a>
			</div>
		</div>

		<div class="col-md-9">
			<div id="cma-app-content-tab-content"></div>
		</div>

	</div>

</div>
