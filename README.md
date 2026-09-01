# CEM Mobile App

WordPress plugin that gives Convention Event Manager attendees a phone-sized `/app` experience: schedule, notes, maps, PWA install, and push alerts.

Requires Convention Event Manager to be active. Do not run the legacy `mobile-app-page-shell` or `sepa-attendee-notes-for-events` plugins alongside this one.

## WordPress updates from this repository

The plugin checks this GitHub repo from the WordPress Plugins screen.

1. Bump `Version` in `cem-mobile-app.php` (and `CMA_VERSION`) **above** the installed version.
2. Commit and push to `main`.
3. GitHub Actions creates a release tagged `v{version}` if that tag does not already exist.
4. On the site, open Plugins (or Dashboard → Updates). WordPress will offer the new version.

Sites already running this plugin pick up later deploys the same way. No FTP copy is required after the first install.

## Install

Copy the `cem-mobile-app` folder into `wp-content/plugins/` and activate it after Convention Event Manager.
