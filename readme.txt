=== Members-Only Events for Paid Memberships Pro ===
Contributors: strangerstudios, paidmembershipspro
Tags: calendar, events, private event, tickets, paid memberships pro
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.2
Stable tag: 2.0

Create members-only events with built-in registration, or restrict events created with popular events calendar plugins.

== Description ==

### The best way to create private events and restrict event registration for members-only with WordPress.

Create a fully-featured membership community or association website with private events and registration for members-only.

= Create Events Without Another Plugin =

The built-in events module adds an Events post type with its own admin menu. Set the date, time, timezone, location or meeting URL, and capacity right from the block editor sidebar.

* Restrict an event with the same "Require Membership" panel you use on any page or post.
* Turn on registration and members claim their spot with one click. No checkout, no payment.
* Set a capacity to cap attendance, or leave it unlimited.
* Registered attendees get the meeting URL and Add-to-Calendar links for Google Calendar, Outlook, and .ics download.
* Add the My Events block or the [pmpro_events_my_events] shortcode to your Membership Account page to show members their upcoming events.
* Export the registrant list for any event to CSV.
* Rename "Event" to "Session", "Webinar", or whatever fits your site.

Selling tickets is not part of this release. You can restrict an event to a paid level, but that is access control rather than ticketing.

= Or Use Your Existing Events Plugin =

Already running an events plugin? This add-on detects it and restricts those events instead. Supported plugins:

* [Events Manager](https://wordpress.org/plugins/events-manager/)
* [The Events Calendar](https://wordpress.org/plugins/the-events-calendar/)
* [Sugar Calendar](https://wordpress.org/plugins/sugar-calendar-lite/)
* [Timely All-in-One Events Calendar](https://wordpress.org/plugins/all-in-one-event-calendar/)

Detected plugins are shown under Memberships > Events. When one is found on activation, the built-in module stays off so nothing about your existing setup changes.

= Hide Event Details From Non-Members =

Create or edit an event with your calendar plugin of choice. The event editor includes the "Require Membership" meta box bundled with Paid Memberships Pro.

This plugin filters the event information shown in your event lists, event categories, calendar views, and single event landing pages. You can control how much information is shown to public visitors in the plugin settings.

* Protect any event by checking the boxes for your desired membership levels.
* Private events do not allow non-members to view full event details or complete event registration.
* Supports recurring events for all modules. Membership requirements automatically copy over from the 'parent' event.

= Hide Private Events From The Public =

You can completely hide member-restricted events to non-members. This means that only logged in members can see any event details.

Hidden events are a secret—these events are not shown in your event lists, event categories, and calendar views.

Learn more about [creating and protecting events and event bookings for your membership](https://www.paidmembershipspro.com/add-ons/events-for-members-only/?utm_source=wordpress-org&utm_medium=readme&utm_campaign=pmpro-events) in our documentation site.

### About Paid Memberships Pro

[Paid Memberships Pro is a WordPress membership plugin](https://www.paidmembershipspro.com/?utm_source=wordpress-org&utm_medium=readme&utm_campaign=pmpro-events) that puts you in control. Create what you want and release in whatever format works best for your business.

* Courses & E-Learning
* Private Podcasts
* Premium Newsletters
* Private Communities
* Sell Physical & Digital Goods

Paid Memberships Pro allows anyone to build a membership site—for free. Restrict content, accept payment, and manage subscriptions right from your WordPress admin.

Paid Memberships Pro is built "the WordPress way" with a lean core plugin and over 75 Add Ons to enhance every aspect of your membership site. Each business is different and we encourage customization. For our members we have a library of 300+ recipes to personalize your membership site.

Paid Memberships Pro is the flagship product of Stranger Studios. We are a bootstrapped company which grows when membership sites like yours grow. That means we focus our entire company towards helping you succeed.

[Try Paid Memberships Pro entirely for free](https://paidmembershipspro.com) and see why 100,000+ sites trust us to help them #GetPaid.

### Read More

Want more information on protecting course content with Paid Memberships Pro, LearnDash or LifterLMS and WordPress membership sites? Have a look at:

* The [Paid Memberships Pro](https://www.paidmembershipspro.com/?utm_source=wordpress-org&utm_medium=readme&utm_campaign=pmpro-events) official homepage.
* The [Events for Members-Only documentation page](https://www.paidmembershipspro.com/add-ons/events-for-members-only/?utm_source=wordpress-org&utm_medium=readme&utm_campaign=pmpro-events).
* Also follow PMPro on [Twitter](https://twitter.com/pmproplugin), [YouTube](https://www.youtube.com/channel/UCFtMIeYJ4_YVidi1aq9kl5g) & [Facebook](https://www.facebook.com/PaidMembershipsPro/).

== Installation ==

Note: You must have [Paid Memberships Pro](https://paidmembershipspro.com) installed and activated on your site.

### Install PMPro Events from within WordPress

1. Visit the plugins page within your dashboard and select "Add New"
1. Search for "PMPro Events"
1. Locate this plugin and click "Install"
1. Activate "Paid Memberships Pro - Events" through the "Plugins" menu in WordPress
1. Go to "after activation" below.

### Install PMPro Events Manually

1. Upload the `pmpro-events` folder to the `/wp-content/plugins/` directory
1. Activate "Paid Memberships Pro - Events" through the "Plugins" menu in WordPress
1. Go to "after activation" below.

### After Activation: Create Members-Only Events

Once the plugin is activated, it automatically detects which events module to load for your site. The included modules are:

* [Events Manager](https://wordpress.org/plugins/events-manager/)
* [The Events Calendar](https://wordpress.org/plugins/the-events-calendar/)
* [Sugar Calendar](https://wordpress.org/plugins/sugar-calendar-lite/)
* [Timely All-in-One Events Calendar (< 3.0.0)](https://wordpress.org/plugins/all-in-one-event-calendar/)

If you are not already using an events plugin, you must select one from this list to use the features of this integration plugin.

1. Edit any event in your site to restrict access via the "Require Membership" meta box.
1. Events are displayed or hidden from calendar (archive) view according to your settings under Memberships > Settings > Advanced Settings.
1. Event excerpts are shown or hidden from non-members according to your settings under Memberships > Settings > Advanced Settings.

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
= 2.0 - 2026-07-31 =
* FEATURE: Added a built-in events module with a native Events post type, edited through block editor sidebar panels for dates, timezone, location, and capacity.
* FEATURE: Members can register for an event with one click. Registrations are stored in their own table with capacity limits, duplicate prevention, and cancellation.
* FEATURE: Registered attendees can add an event to Google Calendar, Outlook 365, Outlook.com, or download an .ics file.
* FEATURE: Added registration confirmation and cancellation emails for members, plus admin notification emails for both, all editable under Memberships > Email Templates.
* FEATURE: Added an Events panel to the Edit Member screen listing all of a member's registrations.
* FEATURE: Added an Upcoming Events block and [pmpro_events] shortcode with a list view and a monthly calendar view. Restricted events are hidden when Paid Memberships Pro's "Filter searches and archives?" setting is enabled.
* FEATURE: Added event categories for organizing events and scoping the Upcoming Events block or shortcode to a series.
* FEATURE: Added a My Events block and [pmpro_events_my_events] shortcode that list a member's upcoming events.
* FEATURE: Added a Registrations admin page with a per-event registrant list and CSV export.
* FEATURE: Added a settings page under Memberships > Events for enabling the built-in module and renaming "Event" throughout the plugin.
* ENHANCEMENT: Administrators can add, cancel, reactivate, and delete registrations from the Registrations page. Adding a registration searches members as you type, showing each match's email and membership level.
* ENHANCEMENT: The single event template now uses Paid Memberships Pro's card, button, and message styles, so events match the site's chosen style variation.
* ENHANCEMENT: Added a block template for single events so block themes don't render post meta that doesn't apply to an event.
* ENHANCEMENT: Added Start Date, Capacity, and Registrations columns to the Events admin list, with sorting by start date.
* ENHANCEMENT: The built-in module stays off on activation when a supported third-party events plugin is detected.

= 1.6.1 - 2025-11-25 =
* BUG FIX: Resolve a PHP warning for Events Manager that occurs when event content is retrieved via AJAX. #56 (@dwanjuki)

= 1.6 - 2024-10-17 =
* FEATURE: Now updating the plugin from paidmembershipspro.com.
* ENHANCEMENT: Updated translation files bundled with the plugin.

= 1.5 - 2024-07-18 =
* ENHANCEMENT: Updated the frontend UI for compatibility with PMPro v3.1. #53 (@dparker1005)
* ENHANCEMENT: Only load our module for All-In-One-Events Calendar for versions before 3.0. If you are using the latest version, please restrict the content by following this guide - https://www.paidmembershipspro.com/documentation/content-controls/
* REFACTOR: Now usigng `get_option()` instead of `pmpro_getOption()`. #51 (@JarrydLong)

= 1.4 - 2023-02-01 =
* ENHANCEMENT: Support recurring events for The Events Calendar. It inherits the 'main' event membership settings.
* BUG FIX: Resolve a database warning (unique alias warnings) for The Events Calendar in some instances when SQL queries run multiple times.

= 1.3.1 - 2022-01-26 =
* BUG FIX: Changed two hooks to the correct names in the integration for The Events Calendar.
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
