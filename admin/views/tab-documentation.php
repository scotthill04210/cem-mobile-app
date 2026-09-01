<?php

defined( 'ABSPATH' ) || exit;

$app_url = CMA_Settings::get_app_url();

?>
<h3><?php esc_html_e( 'What is the Mobile App?', 'cem-mobile-app' ); ?></h3>
<p><?php esc_html_e( 'CEM Mobile App is an add-on that gives attendees a phone-sized event app. It shows the schedule, notes, maps, and push alerts for the CEM schedule you pick. It only appears in this menu when the Mobile App plugin is active.', 'cem-mobile-app' ); ?></p>

<h3><?php esc_html_e( 'App Content', 'cem-mobile-app' ); ?></h3>
<p><?php esc_html_e( 'Open App Content from the left menu (just under Dashboard). That is where you set what attendees see.', 'cem-mobile-app' ); ?></p>
<ul>
	<li><?php esc_html_e( 'Event Details: choose the CEM schedule the app uses, the header event title, the message shown when no schedule is selected, the home-screen message, and map images. Attendee notes and app alerts are stored only for this schedule.', 'cem-mobile-app' ); ?></li>
	<li><?php esc_html_e( 'App Notifications: write and send (or schedule) push notifications for the selected event. The Alerts history on this tab and in the app is for that event only. Device permission (Enable Notifications) is granted once per phone and carries over when you switch schedules.', 'cem-mobile-app' ); ?></li>
</ul>
<p><?php esc_html_e( 'The Map tab on the attendee app only appears when at least one map image is uploaded. When maps exist, Home also shows an Event Maps row under Event App Features.', 'cem-mobile-app' ); ?></p>

<h3><?php esc_html_e( 'Mobile App Settings', 'cem-mobile-app' ); ?></h3>
<p><?php esc_html_e( 'Open Settings, then Mobile App Settings. This is for the app page itself, branding, and push credentials — not day-to-day event copy.', 'cem-mobile-app' ); ?></p>
<ul>
	<li><?php esc_html_e( 'App page URL slug: defaults to /app. The plugin creates a page with the [cem_mobile_app] shortcode if one does not already exist.', 'cem-mobile-app' ); ?></li>
	<li><?php esc_html_e( 'Header logo: upload an image (including SVG). It shows on the left of the app header.', 'cem-mobile-app' ); ?></li>
	<li><?php esc_html_e( 'Colors: Primary is the header title and filled button backgrounds. Secondary is links and icons outside the bottom navigation. Button text is the label on those filled buttons.', 'cem-mobile-app' ); ?></li>
	<li><?php esc_html_e( 'OneSignal: App ID and REST API key for push notifications. Optional debug and test-device fields are here too.', 'cem-mobile-app' ); ?></li>
	<li><?php esc_html_e( 'Cache Controls: use Clear App Cache if phones still show an old version of the app after you change content.', 'cem-mobile-app' ); ?></li>
	<li><?php esc_html_e( 'Updates: WordPress checks the GitHub repository for new plugin versions. After you bump the version and push to main, sites can update from the Plugins screen.', 'cem-mobile-app' ); ?></li>
</ul>

<h3><?php esc_html_e( 'The attendee app page', 'cem-mobile-app' ); ?></h3>
<p>
	<?php
	printf(
		/* translators: %s: app URL */
		esc_html__( 'Attendees open %s. That page uses a blank layout (no theme header or footer) and the tabbed app shell.', 'cem-mobile-app' ),
		'<code>' . esc_html( $app_url ) . '</code>'
	);
	?>
</p>
<p><?php esc_html_e( 'If a WordPress page with that slug already exists, the plugin uses it instead of creating a new one. Put [cem_mobile_app] in that page’s content so the app shell appears.', 'cem-mobile-app' ); ?></p>
<p><?php esc_html_e( 'On a phone, attendees can add the page to the home screen (Install tab, or Save to Home Screen on Home). Notifications work after the app is installed on the device.', 'cem-mobile-app' ); ?></p>

<div class="cem-doc-tip">
	<p class="mb-0"><?php esc_html_e( 'Tip: Pick the event schedule in App Content → Event Details before you share the app URL. Until a schedule is selected, attendees only see the between-events message.', 'cem-mobile-app' ); ?></p>
</div>
