# PMPro Events v2 — Plan

## Problem

PMPro users need to run events — workshops, conferences, meetups, webinars — and sell tickets through their membership site. Today they either use a third-party events plugin (EM, TEC, SC) with our gating add-on, or cobble together levels and pages manually. Neither path gives them a native PMPro experience where checkout, registration, and membership all work together seamlessly.

## Appetite

4 weeks for V1. Ship a complete single-event flow: create event, set price, sell tickets via PMPro checkout, track registrations. No recurrence, no calendar view, no timeslots in V1.

## Solution

Build PMPro Events v2 as a **first-party events system on top of PMPro**. Events are a custom post type. Each event auto-creates a PMPro level. Attendees register by checking out for that level. Registration data lives in a custom table. The entire flow uses PMPro checkout — no external payment, no third-party plugin required.

This is a full rewrite of `pmpro-events` on the `v2.0` branch. It replaces the old gating-layer approach with a complete events system. Backward compatibility with the old pmpro-events feature set (restricting EM/TEC/SC events) will be added in a later pass, similar to how PMPro Courses handles LMS compatibility alongside its native course system.

### Key Architectural Decisions

1. **PMPro checkout is the registration flow.** No custom booking forms. Buy the level = registered.
2. **Custom tables from day one.** `pmpro_events` + `pmpro_event_registrations`, both via `dbDelta()` on activation.
3. **All event meta prefixed `pmpro_`.** Post meta keys: `_pmpro_event_price`, `_pmpro_event_member_price`, `_pmpro_event_currency`.
4. **Always auto-create levels, never attach existing ones.** Each event gets its own auto-generated level(s). Existing membership levels (Plus, Pro, etc.) are never linked to events — that creates a rabbit hole where existing level members get auto-enrolled.
5. **Registration via Action Scheduler, not checkout hooks.** `pmpro_after_checkout` enqueues an AS job. The job handles registration insert, capacity check, confirmation email, and any future side effects (QR codes, webhooks) without blocking checkout.
6. **JS ticket picker for checkout embed.** The event page evaluates user state (logged in? what levels?), shows available ticket options, then renders the PMPro checkout form for the selected level. Discount codes work per-level — the picker selects the level before the form renders, so codes apply naturally.
7. **Registration data in custom table.** `pmpro_event_registrations` — not post meta, not user meta.
8. **Level group isolates event levels.** A `pmpro_events` level group keeps auto-created event levels out of the main membership level list.

---

## Data Architecture

### `pmpro_events` table

```sql
CREATE TABLE {$wpdb->prefix}pmpro_events (
  id             bigint unsigned  NOT NULL AUTO_INCREMENT,
  post_id        bigint unsigned  NOT NULL,
  start          datetime         NOT NULL DEFAULT '0000-00-00 00:00:00',
  start_utc      datetime         NOT NULL DEFAULT '0000-00-00 00:00:00',
  end            datetime         NOT NULL DEFAULT '0000-00-00 00:00:00',
  end_utc        datetime         NOT NULL DEFAULT '0000-00-00 00:00:00',
  timezone       varchar(155)     NOT NULL DEFAULT '',
  all_day        tinyint(1)       NOT NULL DEFAULT 0,
  duration_minutes int unsigned   NOT NULL DEFAULT 0,
  capacity       int              NOT NULL DEFAULT 0,
  venue_name     varchar(255)     NOT NULL DEFAULT '',
  venue_address  text             NOT NULL DEFAULT '',
  level_id       bigint unsigned           DEFAULT NULL,
  member_level_id bigint unsigned          DEFAULT NULL,
  status         varchar(20)      NOT NULL DEFAULT 'publish',
  uuid           varchar(100)     NOT NULL DEFAULT '',
  date_created   datetime         NOT NULL DEFAULT '0000-00-00 00:00:00',
  date_modified  datetime         NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (id),
  UNIQUE KEY post_id (post_id),
  KEY event_times (start, end),
  KEY event_level (level_id),
  KEY event_status (status)
);
```

**Notes:**
- `start_utc`/`end_utc` alongside local times: correct cross-timezone sorting without runtime conversion.
- `timezone` as varchar(155): IANA names can be long (e.g., `America/Indiana/Indianapolis`).
- `uuid` from day one: stable reference for email links, calendar feeds, API responses.
- `level_id` / `member_level_id`: auto-created PMPro level IDs stored directly. Avoids joining `wp_pmpro_memberships_pages` on every query.
- `duration_minutes`: computed on save from start/end. Enables queries like "show short events" or "sort by duration" without runtime math.
- `capacity = 0` means unlimited.

### `pmpro_event_registrations` table

```sql
CREATE TABLE {$wpdb->prefix}pmpro_event_registrations (
  id           bigint unsigned NOT NULL AUTO_INCREMENT,
  event_id     bigint unsigned NOT NULL,
  post_id      bigint unsigned NOT NULL,
  user_id      bigint unsigned NOT NULL,
  order_id     bigint unsigned NOT NULL,
  status       varchar(20)     NOT NULL DEFAULT 'active',
  registered_at datetime       NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (id),
  KEY event_id (event_id),
  KEY user_id (user_id),
  KEY order_id (order_id),
  UNIQUE KEY event_user (event_id, user_id)
);
```

**Notes:**
- `UNIQUE KEY event_user`: one registration per user per event in V1. Remove in V2 for multi-timeslot.
- `order_id` links to `wp_pmpro_membership_orders` — all payment data lives there.
- `status`: `active` | `cancelled` | `waitlisted` (V2).

### Post Meta (thin layer)

Only fields that benefit from WP meta API integration (Gutenberg sidebar, REST API):

| Key | Type | Purpose |
|-----|------|---------|
| `_pmpro_event_price` | decimal string | Non-member price |
| `_pmpro_event_member_price` | decimal string | Member price (empty = free for members) |
| `_pmpro_event_currency` | varchar(3) | ISO currency code |

Everything else lives in `pmpro_events` table.

---

## V1 Scope

### In

- **`pmpro_event` CPT** — registered under PMPro admin menu (`show_in_menu: 'pmpro-member'`), public, REST-enabled, supports title/editor/thumbnail/excerpt
- **Custom tables** — `pmpro_events` + `pmpro_event_registrations`, created via `dbDelta()` on activation
- **Admin metabox: Event Details** — start date/time, end date/time, timezone dropdown, all-day toggle, location (venue name + address as text fields), capacity
- **Admin metabox: Event Pricing** — price field, member price field (with "Free for members" checkbox), "How this works" explainer text
- **Admin metabox: Registrations** — inline table of registrations (user, date, order ID, status), CSV download, remaining capacity counter. Post-publish only.
- **Level auto-creation** — on event publish, create a PMPro level (name = event title, price = event price). Store `level_id` on `pmpro_events` record. If member price set, create companion level → `member_level_id`.
- **Level group** — create `pmpro_events` level group on activation. All event levels go here.
- **JS ticket picker** — event page evaluates user state, shows available ticket options (non-member price, member price), user picks, PMPro checkout form renders for that level
- **Registration via Action Scheduler** — `pmpro_after_checkout` enqueues AS job → job inserts `pmpro_event_registrations` row, checks capacity, fires confirmation
- **Single event template** — `templates/single-pmpro_event.php` with 5 states:
  1. Not registered, no membership → show ticket picker + checkout form
  2. Not registered, has member-price level → "Register Now" one-click button
  3. Already registered → "You're registered" + confirmation details + "Add to Calendar" dropdown (Google Calendar, Outlook 365, Outlook Live, iCal .ics download)
  4. Event full → "Sold Out"
  5. Event passed → "This event has passed"
- **"Free for members" cross-sell state** — when event is free for members, show messaging: "This event is included with [Level Name] membership" + link to buy that membership. Distinct template state.
- **PMPro account page** — "My Events" section showing upcoming registered events
- **Admin list columns** — Title, Start Date, Capacity (X remaining / Y total), Registrations count, Level
- **Discount codes** — work per-level via PMPro's native discount code system. The JS ticket picker selects the level before the checkout form renders, so codes apply to the correct level automatically.
- **Capacity enforcement** — pre-insert check in registration handler. `UNIQUE KEY event_user` prevents double registration at DB level.
- **Add to Calendar dropdown** — post-registration, show "Add to Calendar" with links for Google Calendar, Outlook 365, Outlook Live, and .ics file download. Google/Outlook links are computed URLs (no server round-trip). .ics download is a lightweight endpoint that generates a VCALENDAR file for the single event.
- **Configurable event terminology** — settings to rename "Event"/"Events" to "Session"/"Sessions", "Webinar"/"Webinars", etc. Labels propagate to CPT, menus, templates, account page.
- **`duration_minutes` computed column** — stored on save, enables duration-based queries without runtime math.

### Out (V1)

- Recurring events (V2 — schema will support adding recurrence columns later)
- Multiple time slots / timeslot grid (V2)
- Per-attendee custom fields (V2)
- Calendar view (V2)
- Subscribable ICS calendar feed (V2 — single-event .ics download is in V1)
- Waitlist
- Venue CPT (V1: free text fields; V2: taxonomy or CPT if needed)
- Multiple ticket types per event (V2)
- Guest checkout (V1 requires WP account)
- Backward compatibility with old pmpro-events EM/TEC/SC gating (separate pass)

---

## Registration Flow (Detail)

### Checkout → Registration

```
User visits event page
  → JS ticket picker evaluates user state
  → Shows available prices (member / non-member)
  → User selects ticket type
  → PMPro checkout form renders for that level_id
  → User completes checkout
  → pmpro_after_checkout fires
  → Action Scheduler job enqueued: pmpro_events_register_attendee
  → AS job runs:
    1. Check capacity (SELECT COUNT WHERE event_id = X AND status = 'active')
    2. If capacity available: INSERT into pmpro_event_registrations
    3. If full: set registration status = 'waitlisted' (or reject — V1 rejects)
    4. Fire confirmation action: do_action('pmpro_events_registration_complete', $registration)
```

### Level Auto-Creation

```
Admin publishes event (save_post_pmpro_event)
  → Check if level_id exists on pmpro_events record
  → If not: pmpro_add_level() with event title + price
  → Store level_id on pmpro_events record
  → Add level to pmpro_events level group
  → If member price set: create companion level → member_level_id
  → pmpro_set_membership_pages([$post_id], $level_id)
  → On event title change: update level name
  → On event price change: update level billing amount
  → On event trash: set level status = inactive (don't delete — orders reference it)
```

---

## Discount Code Design

Kim's feedback clarified the discount code use case: it's not about early bird pricing, it's about **per-level codes for custom event pricing**.

**Example scenario:**
- Event has two levels: Non-member ($30), Plus member ($20)
- Code `ABC123` gives $10 off, applicable to both levels
- Plus member uses code → pays $10 (normally $20)
- Non-member uses code → pays $20 (normally $30)

**How it works:** PMPro discount codes already support per-level targeting. The JS ticket picker selects which level the user checks out for. Once the level is selected, the checkout form accepts codes for that level. No custom discount logic needed — this is native PMPro behavior, we just need to make sure the ticket picker + checkout form flow preserves it.

---

## "Free for Members" Cross-Sell

When an event is free for members of a certain level (member_level_id exists, member price = 0), the event page needs a distinct template state:

```
[Event Details]
[Content]
[Registration Block — Cross-Sell State]
  "This event is included with [Level Name] membership."
  [Buy Level Name → link to level checkout page]  |  [Pay $30 for this event → non-member checkout]
```

This is NOT automatic enrollment. It's a cross-sell: "if you buy this membership, you get this event free." The user still has to check out for either the membership level or the event level. No auto-enrollment of existing level members into events they didn't register for.

---

## Admin UX

### Settings Page

Under PMPro > Settings > Events (or a dedicated tab):

**Event Terminology**
- Singular Name: `[text, default "Event"]`
- Plural Name: `[text, default "Events"]`
- These propagate to all labels: CPT name, admin menus, account page tab, frontend templates. Lets admins rename "Events" to "Sessions", "Webinars", "Workshops", etc.

### Event Edit Screen

Three metaboxes:

**1. Event Details** (normal, high priority)
- Start: `[date picker] at [time picker] [timezone dropdown]`
- End: `[date picker] at [time picker]`
- All Day: `[checkbox]` (hides time pickers when checked)
- Location: `[text: venue name]` + `[textarea: address]`
- Capacity: `[number input]` (0 = unlimited)
- Timezone warning: if WP is set to a UTC offset instead of a named timezone, show a red warning: "Your WordPress timezone is set to a UTC offset. Set a named timezone (e.g. America/New_York) in Settings > General for accurate event scheduling."

**2. Event Pricing** (normal, default priority)
- Price: `[currency symbol] [decimal input]`
- Member Price: `[checkbox: Free for members]` OR `[currency symbol] [decimal input]`
- Explainer: "Publishing creates a membership level for this event. Registrants check out for this level."

**3. Registrations** (normal, default priority, post-publish only)
- Table: User | Registered | Order | Status
- Download CSV
- Capacity: "X of Y spots filled" or "X registered (unlimited)"

### CPT List Table Columns

| Column | Content |
|--------|---------|
| Title | Event title (linked) |
| Start Date | Formatted start date/time |
| Capacity | "X / Y" or "X / unlimited" |
| Registrations | Count |
| Level | Auto-created level name (linked to level edit) |

---

## File Structure

```
pmpro-events/
├── pmpro-events.php              # Main plugin file (bootstrap, activation, dependency check)
├── PLAN.md                        # This file
├── includes/
│   ├── admin.php                  # Admin metaboxes, list columns, CSV export
│   ├── class-pmpro-event.php      # Event model — sync_schedule(), get_start/end, duration, calendar URLs
│   ├── cpt.php                    # CPT registration
│   ├── db.php                     # Table creation (dbDelta), upgrade routines
│   ├── ics.php                    # Single-event .ics download endpoint + ICS generation
│   ├── levels.php                 # Level auto-creation, group management
│   ├── registration.php           # Registration handler (AS job), capacity checks
│   ├── settings.php               # Settings page (terminology, future options)
│   └── template.php               # Template loading, single event output
├── js/
│   └── ticket-picker.js           # Frontend ticket selection + checkout form rendering
├── css/
│   └── pmpro-events.css           # Frontend + admin styles
├── templates/
│   ├── single-pmpro_event.php     # Default single event template
│   └── add-to-calendar.php        # "Add to Calendar" dropdown (Google, Outlook, iCal)
└── languages/
    └── pmpro-events.pot           # (existing, will be regenerated)
```

---

## Rabbit Holes

- **Attaching existing membership levels to events.** Don't. Always auto-create. Linking existing levels creates impossible questions (what happens to current members of that level? auto-enrolled?). Kim flagged this — it's a firm No Go.
- **Building a calendar view in V1.** Calendar is a display concern, not a core feature. Ship event list (CPT archive) first. Calendar can come in V2.
- **Custom booking forms / attendee fields in V1.** PMPro checkout collects everything we need for V1. Per-attendee custom fields (e.g., dietary restrictions, t-shirt size) are V2 scope.
- **Recurring events in V1.** Schema can accommodate recurrence columns later. Don't add the UI or logic now.
- **Guest checkout.** V1 requires a WP account. Guest event registration is a separate design challenge.

## No Gos

- No third-party events plugin dependency. This is standalone.
- No WooCommerce integration. PMPro checkout only.
- No auto-enrollment of existing level members into events. Registration is always explicit.
- No custom payment flow. PMPro handles all payment.
- No backward compatibility with pmpro-events v1.x in this pass. That's a separate future effort.

---

*Plan written: 2026-03-29. Awaiting Jason's review before coding begins.*
