=== Paid Memberships Pro - Events Add On ===
Contributors: strangerstudios
Tags: paid memberships pro, events, events calendar, events manager, bookings, calendar, registration, tribe
Requires at least: 3.5
Tested up to: 5.9
Requires PHP: 5.6
Stable tag: 1.3.1

Offer members-only events using popular events plugins and Paid Memberships Pro.

== Description ==
This Add On offers integration for popular events plugins, including:

* [Events Manager](https://wordpress.org/plugins/events-manager/)
* [The Events Calendar](https://wordpress.org/plugins/the-events-calendar/)
* [Sugar Calendar](https://wordpress.org/plugins/sugar-calendar-lite/)
* [All-in-One Event Calendar](https://wordpress.org/plugins/all-in-one-event-calendar/)

Events that are restricted by membership level will not allow non-members to view full event details or complete event registration. The list and single view of the event will show limited event details as defined by your Advanced Settings > "Show Excerpts to Non-Members" setting.

Additionally, you can completely hide member-restricted events to non-members via the Memberships > Advanced Settings > "Filter Searches and Archives" setting.

== Installation ==

1. Make sure you have the Paid Memberships Pro plugin installed and activated.
1. Upload the `pmpro-events` directory to the `/wp-content/plugins/` directory of your site.
1. Activate the plugin through the 'Plugins' menu in WordPress.

= Setup =

1. Once the plugin is active, it will automatically detect which events module to load for your site.
1. Edit any event in your site to restrict access via the "Require Membership" meta box.
1. Events will be displayed or hidden from calendar (archive) view according to your settings under Memberships > Settings > Advanced Settings.
1. Event excerpts will be shown or hidden from non-members according to your settings under Memberships > Settings > Advanced Settings.

== Frequently Asked Questions ==

= I found a bug in the plugin. =

Please post it in the GitHub issue tracker here: [https://github.com/strangerstudios/pmpro-events/issues](https://github.com/strangerstudios/pmpro-events/issues)

For immediate help, also post to our premium support site at [http://www.paidmembershipspro.com](https://www.paidmembershipspro.com) for more documentation and our support forums.

= I need help installing, configuring, or customizing the plugin. =

Please visit our premium support site at [https://www.paidmembershipspro.com](https://www.paidmembershipspro.com) for more documentation and our support forums.

== Screenshots ==

1. Set Membership Requirements when editing a single event.
2. Event information is hidden on the site.

== Changelog ==

= 1.3.1 - 2022-01-26 =
* BUG FIX: Changed two hooks to the correct anmes in the integration for The Events Calendar.
* Updated to show as compatible with WordPress 5.9 after testing.

= 1.3 - 2021-12-15 =
* ENHANCEMENT: New integration with The Events Calendar new "v2" calendar views for filtering protected events.
* Now requiring PHP 5.6+ to match minimum required version in Paid Memberships Pro

= 1.2 - 2021-06-21 =
* ENHANCEMENT: Improved integration for Sugar Calendar and the Event Ticketing Add On so that the Event Ticketing box is not displayed if the event is protected.
* BUG FIX: Fixed a problem where events for The Events Calendar would get the wrong excerpt when they were protected.

= 1.1 - 2021-03-01 =
* ENHANCEMENT: Added in a new filter for The Events Calendar, to adjust the event meta if the user doesn't have access to the event. `pmpro_events_tribe_post_single_html`.
* ENHANCEMENT: Added in a new filter for Sugar Calendar, to show/hide event meta to non-members. `pmpro_events_sc_hide_event_meta`.
* ENHANCEMENT: General improvements to localization and internationalization.
* ENHANCEMENT: Support recurring events for all modules, this will now automatically copy over membership requirements from the 'parent' event.
* BUG FIX: Fixed an issue in Sugar Calendar hiding events in the WordPress dashboard for non-members.
* BUG FIX: Fixed a warning for undefined variable for The Events Calendar for event excerpts.
* BUG FIX: Fixed an issue where event meta (event tickets and RSVP blocks) were showing on restricted events for The Events Calendar.

= 1.0 =
* Original version.
