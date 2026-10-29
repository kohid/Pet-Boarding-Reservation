# Events and Event Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Staff add events from the Pawfectly dashboard. Each event gets its own page (`/event/<slug>/`). Visitors find events through `[pawfectly_calendar]` (Month / List / Day) and `[pawfectly_events]`.

**Architecture:**
- Events are the post type `paw_event`. Their fields live in post meta, all read and written through one class, `Events`.
- Pure helpers (`EventDates`) format dates and build Add to calendar links.
- `Calendar`, `EventsList` and `EventPage` render the public views from `Events`.
- Staff edit through capability-checked routes in `StaffRest`, from a new dashboard view driven by `events-admin.js`.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, vanilla JS, Playwright for the browser tests. No new libraries. The emoji picker is ported from kohid/Community-Business-Directory.

**Spec:** `docs/superpowers/specs/2026-10-29-events-calendar-design.md`

## Global Constraints

- Namespace `Pawfectly\`; classes in `includes/<Class>.php`, which load automatically.
- Prefixes:
  - `paw_` for the post type `paw_event`, the taxonomy `paw_event_cat`, the meta `_paw_ev_*` and the term meta `_paw_color`.
  - `paw-` for CSS and script handles.
- Text domain `pawfectly-reservations`.
- CSS scoping:
  - Public CSS goes under `.paw-cal`, `.paw-events` and `.paw-event`, each guarded with `:not(#paw-0)` like the other front-end CSS.
  - Dashboard CSS goes under `.paw-dash`.
- Staff data only over capability-checked REST routes in `StaffRest` (`Activator::CAPABILITY`). Event categories need `Access::can_manage_settings()`.
- Links handed to JavaScript are plain URLs. Never use `wp_nonce_url()` for them (see v0.4.3).
- Times are local to the site (`wp_timezone()`), stored as `Y-m-d H:i:s`.
- Limits, from the spec:
  - Title: 120 characters. Tagline: 160. Venue: 120. Address: 200. Note: 200. Row text: 300.
  - Emoji: 64 bytes (raised from 32 in the final review: 🧑🏻‍❤️‍💋‍🧑🏼 is 35).
  - Rows: sessions 8, includes 20, goodies 12, good to know 20.
  - Calendar: 3 chips a day. `[pawfectly_events]` pages: 9 events.
- Version **0.5.0** (`PAW_VERSION` and the plugin header).

## Review Focus

1. **Site timezone not UTC:** an event at 11:30 PM local time must land on its own day in the grid, not the next one. The test goes in Task 3, with the `wp_timezone()` stub switched to `America/New_York`.
2. **An event crossing a month boundary** (Dec 31 → Jan 2) shows in both months' grids, with start and end chips on the right days. Test in Task 3.
3. **Rows removed in the editor stay removed after saving:** sections are replaced as a whole, never merged with the saved ones. Test in Task 1 (sanitize and save) and Task 7 (browser).
4. **Search text with `%` or `_`** matches literally, and never shows drafts. Test in Task 3.
5. **Joined emoji** (👨‍👩‍👧‍👦, 🐕‍🦺, skin tones) are kept intact. Test in Task 1.

---

### Task 1: Events model and the post test stubs

**Files:**
- Create: `includes/Events.php`
- Create: `tests/post-stubs.php`: an in-memory store for posts, post meta, terms and term meta. It covers `wp_insert_post`, `wp_update_post`, `get_post`, `get_post_meta`, `update_post_meta`, `delete_post_meta`, `wp_trash_post`, `get_posts`, `wp_set_object_terms`, `wp_get_object_terms`, `get_terms`, `wp_insert_term`, `wp_update_term`, `wp_delete_term`, `get_term_meta`, `update_term_meta`, `get_permalink`, `get_post_thumbnail_id`, `set_post_thumbnail`, `delete_post_thumbnail`, `wp_get_attachment_image_url`, `register_post_type`, `register_taxonomy` and `register_post_meta`.
  - `get_posts` supports `post_type`, `post_status` (a string or an array), `posts_per_page`, `offset`, `s` (title contains, literally), `tax_query` (one term slug), `meta_query`, `orderby` = `meta_value`, and `order`.
  - `meta_query` takes clauses of `key` / `value` / `compare` (`<=`, `>=`, `<`, `>`) joined by AND. Values are compared as strings.
- Create: `tests/events-test.php`
- Modify: `includes/Plugin.php`: call `Events::init()`.

**Interfaces:**
- **Produces:**
  - `Events::TYPE = 'paw_event'`, `Events::TAX = 'paw_event_cat'`
  - `Events::LIMITS`: the row limits keyed by section
  - `Events::SECTIONS = ['sessions','includes','goodies','good_to_know','closing']`
  - `Events::init(): void`: registers the post type (public, `show_ui` false, `show_in_rest` false, rewrite slug `event` with `with_front` false, supports title, editor, thumbnail), the taxonomy (`show_ui` false) and the meta.
  - `Events::defaults(): array`: an empty event, with the spec §3.1 headings and intros.
  - `Events::sanitize( array $in ): array|\WP_Error`. On success it returns a clean event. On failure it returns a `WP_Error` with code `paw_invalid` and `data.fields` keyed like `title`, `end`, `sections.sessions.1.from`.
  - `Events::save( array $clean, int $id = 0 ): int`. Inserts or updates; returns the post id.
  - `Events::get( int $id ): ?array`. Returns the event (the defaults merged with the saved values) plus `id`, `url`, `status`, `photo_id`, `photo_url`, `category` (slug), `category_name` and `color`. Returns null for anything that isn't a `paw_event`.
  - `Events::between( string $from, string $to, string $q = '', string $cat = '', array $statuses = ['publish'] ): array`. Events overlapping the window, as `get()` arrays sorted by start.
  - `Events::status( array $ev, ?\DateTimeImmutable $now = null ): string`. Returns `upcoming`, `current` or `past`.
  - `Events::duplicate( int $id ): int`. The copy is a draft with " (copy)" added to the title.
  - `Events::categories(): array`. A list of `{slug, name, color}`.
  - `Events::PALETTE`: 8 hex colours. The first is `#ff7f50`, which is also the colour for an event with no category.
- **Event array keys:** `title`, `tagline`, `emoji`, `category`, `start`, `end`, `venue`, `address`, `price` (a string in `clean_price` form, or `''`), `free` (bool), `note`, `description`, `ticket_url`, `status` (`publish` | `draft`), `photo_id`, `sections`.
  - `sections.sessions = {heading, intro, rows:[{from:'H:i', to:'H:i', name, who}]}`
  - `sections.includes = {heading, intro, rows:[{emoji, text}]}`
  - `sections.goodies = {heading, rows:[{emoji, title, text}]}`
  - `sections.good_to_know = {heading, rows:[{emoji, text}]}`
  - `sections.closing = {heading, intro}`

- [ ] **Step 1: Write the failing tests in `tests/events-test.php`** (they load `bootstrap.php` and `post-stubs.php`):
  - `sanitize: title, start, end and venue are required`: the error fields are exactly `title`, `start`, `end`, `venue`.
  - `sanitize: the end must be after the start`: only `end` is in error, with "The end must be after the start."
  - `sanitize: limits`: a title of 121 characters fails; a session row 9 fails on `sections.sessions`; a row text of 301 characters fails on `sections.includes.0.text`.
  - `sanitize: the price is cleaned like Settings prices`: `'15'` becomes `'15.00'`; `'abc'` fails on `price`.
  - `sanitize: a session needs from before to`: `from` 12:00 with `to` 10:00 fails on `sections.sessions.0.to`.
  - `sanitize: empty rows are dropped`: a row whose fields are all blank is removed, not refused.
  - `sanitize: joined emoji kept`: '👨‍👩‍👧‍👦', '🐕‍🦺' and '👋🏽' come back unchanged; 'abc' becomes ''; 33 bytes of emoji fail on `emoji`.
  - `sanitize: unknown keys and sections are dropped`; the ticket link goes through `esc_url_raw`; the description is filtered with `wp_kses` using the waiver tags plus `a[href]`.
  - `save and get round trip`: every key survives. `get()` on a non-event post returns null.
  - `saving fewer rows replaces the old ones`: save 3 includes, then 1; `get()` has 1.
  - `between: overlap, drafts left out, search, category`:
    - Three events: Dec 19, Dec 31 → Jan 2, and a draft on Dec 20.
    - `between('2026-12-01','2026-12-31')` returns 2.
    - A search for `%` returns none.
    - The category filter works.
  - `status`: upcoming, current and past around a fixed `$now`.
  - `duplicate`: a draft titled "X-Mas Party (copy)" with the same sections.
- [ ] **Step 2: Run** `php tests/events-test.php`. Expected: Fatal, "Class Pawfectly\Events not found".
- [ ] **Step 3: Write `tests/post-stubs.php`, then implement `includes/Events.php`.**
  - Emoji check: keep the value only if `preg_match('/^[\p{Extended_Pictographic}\x{1F3FB}-\x{1F3FF}\x{200D}\x{FE0F}\x{20E3}\x{1F1E6}-\x{1F1FF}]+$/u', $v)` matches; otherwise use `''`.
  - Prices use `Services::clean_price()`.
  - Times: dates come in as `Y-m-d` plus `H:i` pairs (`start_date`, `start_time`, `end_date`, `end_time`) and are stored as `start` / `end` in `Y-m-d H:i:s`.
- [ ] **Step 4: Run** `bash bin/test.sh`. Expected: every file passes, including `events-test.php`.
- [ ] **Step 5: Commit:** "feat(events): Events model, post type, sanitizing, in-memory post stubs for tests".

### Task 2: EventDates: date lines, summaries and Add to calendar

**Files:**
- Create: `includes/EventDates.php`
- Create: `tests/event-dates-test.php`

**Interfaces:**
- **Consumes:** the event array from Task 1.
- **Produces:**
  - `EventDates::date_line( array $ev ): string`:
    - one day: "One Day Only · Saturday, December 19th, 2026"
    - several days: "Friday, December 18th – Sunday, December 20th, 2026"
    - different years: both years shown
  - `EventDates::day( array $ev ): string`: "Saturday, December 19th, 2026".
  - `EventDates::time_range( array $ev ): string`: "10:00 AM – 2:00 PM".
  - `EventDates::summary( array $ev ): string`: "Saturday, December 19th, 2026, 10:00 AM – 2:00 PM — $15 per dog". For a free event: "— Free".
  - `EventDates::price_text( array $ev ): string`: "$15 per dog", "Free", or `''`. Built with `Services::money()`.
  - `EventDates::fill( string $text, array $ev ): string`: replaces `{price}` with "$15", or "Free".
  - `EventDates::links( array $ev ): array{google:string, outlook:string, ics:string}`. Google and Outlook use the Business Directory's `cal_links()` formats. `ics` is `get_permalink() . '?paw_ics=1'`.
  - `EventDates::ics( array $ev ): string`. A VCALENDAR / VEVENT with DTSTART and DTEND (`;TZID=` the site timezone), SUMMARY, LOCATION, URL and a UID of `<id>@<host>`. Lines end in CRLF; text is escaped for `,`, `;` and `\n`.
  - `EventDates::phone_links( string $escaped_html ): string`. Turns US-style numbers (`239-420-7418`, `(239) 420-7418`) into `<a href="tel:2394207418">`. It runs on already-escaped text.

- [ ] **Step 1: Write the failing tests:** each output above, for the X-Mas Party values (Dec 19 2026, 10:00–14:00, $15). Also:
  - a several-day event across a year boundary
  - a free event
  - `fill('Everything Your {price} Ticket Includes')` gives "Everything Your $15 Ticket Includes"
  - `ics` contains `DTSTART;TZID=UTC:20261219T100000` and ends with `END:VCALENDAR\r\n`
  - `phone_links` leaves "2026" alone and links "239-420-7418"
- [ ] **Step 2: Run** `php tests/event-dates-test.php`. Expected: Fatal, class not found.
- [ ] **Step 3: Implement.** Use `wp_date()` with the site timezone. The ordinal day comes from `jS`.
- [ ] **Step 4: Run** `bash bin/test.sh`. Expected: everything passes.
- [ ] **Step 5: Commit:** "feat(events): date lines, summaries and Add to calendar links".

### Task 3: Calendar builder, public route and `[pawfectly_calendar]`

**Files:**
- Create: `includes/Calendar.php`
- Create: `assets/js/calendar.js`
- Create: `assets/css/calendar.css`
- Create: `tests/calendar-test.php`
- Modify: `includes/Rest.php`: register the route.
- Modify: `includes/Shortcode.php` (or `Plugin`): register the shortcode and enqueue the assets only when it renders.

**Interfaces:**
- **Consumes:** `Events::between()`, `Events::categories()`, `EventDates::*`.
- **Produces:**
  - `Calendar::TAG = 'pawfectly_calendar'`, `Calendar::MAX_PER_DAY = 3`.
  - `Calendar::render( $atts ): string`. Attributes: `category`, `view` (`month` | `list` | `day`, default `month`). `?paw_cal=YYYY-MM` picks the month.
  - `Calendar::view_data( string $date, string $q, string $cat ): array{date, ym, monthTitle, dayTitle, grid, agenda, day, count}`. The HTML strings are escaped inside.
  - `Calendar::by_day( string $from, string $to, string $q, string $cat ): array<string,list<array>>`. Buckets each event onto every date it covers inside the window. Each entry adds `is_start`, `is_end`, `multiday` and `time` ("10:00 AM", or "All day" on full middle days).
  - Route: `GET /pawfectly/v1/events/calendar?date=&q=&cat=`. Public (`permission_callback` `__return_true`). Returns `view_data()`.
  - Markup hooks for the JS:
    - the root `.paw-cal[data-date][data-ym][data-cat][data-view]`
    - `[data-paw-cal-view=list|month|day]`, `[data-paw-cal-prev]`, `[data-paw-cal-next]`, `[data-paw-cal-today]`, `[data-paw-cal-title]`
    - the picker `[data-paw-cal-picker]`, the search `[data-paw-cal-q]`, the category select `[data-paw-cal-cat]`
    - each chip `[data-paw-cal-event="<id>"]`, which carries its popover data as `data-*`
    - each day's overflow `[data-paw-cal-more]`

- [ ] **Step 1: Write the failing tests:**
  - `grid: whole weeks, Monday first`: December 2026 starts Mon Nov 30 and ends Sun Jan 3, with 35 cells, and November and January days muted.
  - `an event crossing months shows in both`: Dec 31 → Jan 2 has `is_start` on Dec 31 in December's grid, and `is_end` on Jan 2 in January's.
  - `site timezone`: with the `wp_timezone()` stub on `America/New_York`, an event at 23:30 on Dec 19 sits in the Dec 19 cell.
  - `+N more`: 5 events on one day show 3 chips and "+2 more".
  - `search and category`: as in Task 1's `between` test, but through `view_data`.
  - `drafts never appear`.
  - `output is escaped`: a title of `<b>x</b>` appears as `&lt;b&gt;`.
  - `the route is public and returns view_data`.
- [ ] **Step 2: Run** `php tests/calendar-test.php`. Expected: Fatal, class not found.
- [ ] **Step 3: Implement `Calendar`**, following the Business Directory's `CalendarShortcode::build`, `fetch_events`, `grid_html`, `agenda_html` and `day_html` (kohid/Community-Business-Directory, `includes/Frontend/Shortcodes/CalendarShortcode.php`), with Pawfectly's names and escaping.
- [ ] **Step 4: Implement `calendar.js` and `calendar.css`:**
  - Switching view, the arrows, Today, the picker, search (on submit and Enter) and the category all fetch the route and swap the HTML.
  - Chip popover; "+N more" expands the day.
  - Under 640px the chips become dots, and tapping a day opens the Day view.
  - Category colours are set as `--paw-ev` inline on each chip.
  - Use `frontend-design:frontend-design` for the visual pass.
- [ ] **Step 5: Run** `bash bin/test.sh`. Expected: everything passes.
- [ ] **Step 6: Commit:** "feat(events): [pawfectly_calendar] with month, list and day views and a public route".

### Task 4: `[pawfectly_events]` list

**Files:**
- Create: `includes/EventsList.php`
- Create: `templates/partials/event-card.php`
- Create: `tests/events-list-test.php`
- Modify: `assets/js/calendar.js` (Load more), `assets/css/calendar.css` (cards).

**Interfaces:**
- **Consumes:** `Events::between()`, `Events::status()`, `EventDates::*`.
- **Produces:**
  - `EventsList::TAG = 'pawfectly_events'`, `EventsList::PER_PAGE = 9`.
  - `EventsList::render( $atts ): string`. Attributes: `category`, `limit`, `show`. `?paw_events=upcoming|current|past|all` picks the tab.
  - `EventsList::cards( string $tab, string $cat, int $offset ): array{html:string, more:bool}`.
  - Public route `GET /pawfectly/v1/events/list?tab=&cat=&offset=`, which returns `cards()`.
  - Order: upcoming and current soonest first; past most recent first.

- [ ] **Step 1: Write the failing tests:**
  - the tabs filter by `Events::status()`
  - the order per tab
  - 10 upcoming events give 9 cards with `more` true
  - a card shows the photo, or else the emoji
  - the date block reads "DEC" / "19"
  - the price text
  - the link goes to the event
- [ ] **Step 2: Run** `php tests/events-list-test.php`. Expected: Fatal, class not found.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `bash bin/test.sh`. Expected: everything passes.
- [ ] **Step 5: Commit:** "feat(events): [pawfectly_events] cards with tabs and Load more".

### Task 5: Event page

**Files:**
- Create: `includes/EventPage.php`
- Create: `templates/event.php`
- Create: `tests/event-page-test.php`
- Modify: `assets/css/calendar.css` (the `.paw-event` styles).

**Interfaces:**
- **Consumes:** `Events::get()`, `Events::status()`, `EventDates::*`.
- **Produces:**
  - `EventPage::init()`:
    - a `the_content` filter: for a `paw_event` in the main query, returns `EventPage::body( Events::get( id ) )`
    - `wp_head`: JSON-LD, plus Open Graph tags unless Yoast or Rank Math is active
    - `template_redirect`: sends the .ics when `?paw_ics=1`
  - `EventPage::body( array $ev ): string`: the spec §4.1 order.
  - Ticket buttons: `[data-paw-ticket]`. They go to `ticket_url` when it's set; otherwise `#paw-event-reserve`, and the closing block shows "Sign-ups open soon".
  - `EventPage::json_ld( array $ev ): array`.
  - Draft preview: `?paw_preview=1` on a draft's permalink (`Events::get()['url']` with `p=<id>&post_type=paw_event` for drafts) renders the body for users with `Activator::CAPABILITY`. Everyone else gets a 404. Preview links go through `EventPage::preview_url( int $id ): string`.
  - Test: `a draft is a 404 for visitors and renders for staff with paw_preview`.

- [ ] **Step 1: Write the failing tests** with the X-Mas Party example built in a fixture:
  - the date line, title, tagline and facts row in order
  - "Get Tickets — $15"
  - the highlight note with a `tel:2394207418` link
  - sessions read "10:00 AM – 12:00 PM" and "Session 1: Small dogs (under 30 lbs)"
  - the heading reads "Everything Your $15 Ticket Includes"
  - 5 include items, 2 goodies and 3 good-to-know items
  - the closing summary line
  - an empty section is not rendered
  - a past event shows "This event has ended" and no `[data-paw-ticket]`
  - `json_ld` has `@type` Event, `startDate` 2026-12-19T10:00:00+00:00, and `offers.price` "15.00"
  - all text is escaped
- [ ] **Step 2: Run** `php tests/event-page-test.php`. Expected: Fatal, class not found.
- [ ] **Step 3: Implement** the template and the `.paw-event` CSS. Use `frontend-design:frontend-design`.
- [ ] **Step 4: Run** `bash bin/test.sh`. Expected: everything passes.
- [ ] **Step 5: Commit:** "feat(events): event page with sections, JSON-LD and .ics".

### Task 6: Staff event routes and categories

**Files:**
- Modify: `includes/StaffRest.php`
- Create: `tests/staff-events-test.php`

**Interfaces:**
- **Consumes:** `Events::sanitize`, `save`, `get`, `duplicate`, `categories`; `Access::can_manage_settings()`.
- **Produces** (namespace `Rest::NS`):
  - `GET /staff/events?tab=upcoming|drafts|past|all&q=&page=` returns `{rows:[{id,title,emoji,when,category_name,color,venue,price_text,status,url}], pages}`, 20 per page.
  - `GET /staff/events/(?P<id>\d+)` returns the `Events::get()` array, with start and end split into `start_date`, `start_time`, `end_date` and `end_time` for the form.
  - `POST /staff/events` and `POST /staff/events/(?P<id>\d+)` return `{ok, id, event}`. On error: `paw_invalid` with `fields`, status 400.
  - `DELETE /staff/events/(?P<id>\d+)` moves the event to the trash.
  - `POST /staff/events/(?P<id>\d+)/duplicate` returns `{id}`.
  - `POST /staff/event-categories` takes `{categories:[{slug?, name, color}]}`; manage-settings only. A missing slug creates the category, and any category left out is deleted.

- [ ] **Step 1: Write the failing tests:**
  - owners and visitors are refused
  - staff can list, create, update, duplicate and delete
  - errors come back keyed by row (`sections.goodies.0.title`)
  - updating id 5 when 5 is a reservation page, not an event, gives a 404
  - staff without manage-settings get 403 on categories
  - saving categories creates, renames and deletes
- [ ] **Step 2: Run** `php tests/staff-events-test.php`. Expected: failures (routes missing).
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `bash bin/test.sh`. Expected: everything passes.
- [ ] **Step 5: Commit:** "feat(events): staff routes for events and categories".

### Task 7: Dashboard Events view, editor, repeaters and emoji picker

**Files:**
- Create: `templates/partials/dashboard-events.php` (the list and the editor shell)
- Create: `assets/js/events-admin.js`
- Create: `assets/js/emoji-picker.js` and `assets/css/emoji-picker.css`: ported from kohid/Community-Business-Directory `assets/js/emoji-picker.js` and `assets/css/emoji-picker.css`. Rename the storage keys to `paw-emoji-recents` / `paw-emoji-tone`, and scope the CSS under `.paw-dash`.
- Modify:
  - `templates/dashboard.php`: the nav item "Events" between Reservations and Settings, and a `data-paw-view="events"` section.
  - `includes/Dashboard.php`: the `events` view, the URLs, the config strings, and enqueueing `events-admin.js`, the emoji picker and `wp_enqueue_media()`.
  - `templates/partials/dashboard-settings.php`: the "Event categories" card (name, colour swatches from `Events::PALETTE`, Add, Remove), saved through `/staff/event-categories`.
  - `assets/css/dashboard.css`
- Test:
  - `tests/dashboard-render-test.php`: the Events nav and view render.
  - New `tests/e2e/dashboard-events-e2e.js`: uses `tests/e2e/build-dashboard.php` and mocks the routes from Task 6.

**Interfaces:**
- **Consumes:** the Task 6 routes; `window.PAW_DASH.rest` and its nonce.
- **Produces:**
  - Editor URL: `?paw_view=events&event=new|<id>`.
  - Markup hooks:
    - `[data-paw-ev-form]` and `[data-paw-ev-field="title"]`
    - each section `[data-paw-ev-section="sessions"]`, holding `[data-paw-ev-rows]`
    - each row `[data-paw-ev-row]`, with the handle `[data-paw-ev-drag]`, `[data-paw-ev-up]`, `[data-paw-ev-down]` and `[data-paw-ev-remove]`
    - `[data-paw-ev-add="sessions"]`
    - `[data-emoji-trigger][data-emoji-target]`, the picker's own contract
    - `[data-paw-ev-photo]`, `[data-paw-ev-save="draft|publish"]`, `[data-paw-ev-preview]`, `[data-paw-ev-delete]`
  - Rows come from a `<template data-paw-ev-row-template="sessions">`. The **Add** buttons hide at the section's limit.

- [ ] **Step 1: Write the failing tests:**
  - **render test:** the nav has Events between Reservations and Settings, and the events view and editor shell render for staff.
  - **e2e:**
    - the list tabs fetch with `tab=`
    - Add Event opens an empty editor with the default headings
    - adding 3 includes then removing the middle one sends 2 rows in order
    - Move up and drag both reorder rows
    - the emoji picker (with its CDN mocked by an image route) fills the event emoji with "🎄"
    - a 400 with `sections.sessions.0.to` marks that row field and scrolls to it
    - Publish posts `status: publish`
    - Duplicate and Delete (after confirming) call their routes
    - leaving with unsaved changes asks first
    - the Add button hides at 8 sessions
    - no sideways scroll at 390px
- [ ] **Step 2: Run** `php tests/dashboard-render-test.php && php tests/e2e/build-dashboard.php && node tests/e2e/dashboard-events-e2e.js`. Expected: failures.
- [ ] **Step 3: Implement.** Use `frontend-design:frontend-design` for the editor layout.
- [ ] **Step 4: Run** `bash bin/test.sh` and every e2e suite. Expected: all pass, with the existing dashboard suite unchanged.
- [ ] **Step 5: Commit:** "feat(events): dashboard Events view, editor with repeaters, emoji picker, categories".

### Task 8: Pages, public browser tests, docs and version

**Files:**
- Modify:
  - `includes/Pages.php` (`TAGS`):
    - `pawfectly_calendar`: "Event Calendar", "The event calendar: month, list and day views with search and categories."
    - `pawfectly_events`: "Events", "Event cards with Upcoming, Happening now and Past tabs."
  - `pawfectly-reservations.php`: 0.5.0.
  - `README.md`: an Events section.
  - `CLAUDE.md`: the spec and plan paths; the convention "Events: `paw_event` posts read and written only through `Events`".
  - `includes/Activator.php`: flush the rewrite rules on activation and on the version bump, so `/event/` works.
- Create: `tests/e2e/build-events.php` and `tests/e2e/events-e2e.js`.
  - The build renders the calendar, the events list and the X-Mas Party page into `tests/e2e/out/`, with the real CSS and JS.
  - It writes the route responses as JSON fixtures, which Playwright serves.

- [ ] **Step 1: Write the failing e2e (`events-e2e.js`):**
  - **Calendar:**
    - Month shows the X-Mas Party chip on Dec 19.
    - The next and previous arrows and Today load the right months; so does the picker.
    - List and Day views.
    - A search for "x-mas" finds the event, and one for "zzz" shows the empty message.
    - The category filter.
    - The chip popover links to the event.
    - "+N more" expands.
    - At 390px the dots open the Day view.
  - **Events list:** the tabs, and Load more.
  - **Event page:** the full example top to bottom; Get Tickets with no ticket link scrolls to `#paw-event-reserve`; the Google calendar link.
  - No sideways scroll at 390px on all three.
- [ ] **Step 2: Run** `php tests/e2e/build-events.php && node tests/e2e/events-e2e.js`. Expected: failures, until the build exists.
- [ ] **Step 3: Implement** the build, the `Pages` entries, the docs, the version and the rewrite flush. Add a `pages-test.php` check that both new tags are listed.
- [ ] **Step 4: Run** `bash bin/test.sh`, all five e2e suites and `bin/build-release.sh`. Expected: everything passes, and the zip contains `includes/Events.php` and `assets/js/calendar.js`.
- [ ] **Step 5: Commit:** "feat(events): pages, browser tests, docs, version 0.5.0".

### Final: review and release

- [ ] **One whole-branch review:** a fresh reviewer, focusing on the Review Focus list, escaping, and permissions on every route.
- [ ] **Fix and commit**, then open a PR into `main`.
- [ ] **Check the merge:** once merged, confirm `main` has every file, then run the Release workflow with `tag: v0.5.0`.
