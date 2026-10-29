# Pawfectly v0.5: events and an event calendar (design spec)

- Status: **draft, for review**.
- Date: 2026-10-29
- Builds on the v0.1–v0.4 specs. Their conventions still apply.
- Modelled on the events and `[cbd_calendar]` of kohid/Community-Business-Directory, in Pawfectly's look.

## 1. Goals

1. Staff add and edit events from the Pawfectly dashboard, with repeatable sections that match the "X-Mas Party" page.
2. Each event has its own page, laid out like that example.
3. A public event calendar (Month, List, Day views) and an events list, like the Business Directory's.
4. Release as v0.5.0.

**Out of scope here.** The sign-up form behind "Get Tickets" / "Reserve Your Spot" is the next release (its own spec):
- choosing a session
- adding your dogs
- a members' coupon code
- staff seeing sign-ups
- paying at the event

## 2. Decisions (approved in conversation)

| # | Decision |
|---|---|
| 1 | **Storage, approach A.** Each event is a WordPress post of type `paw_event`, with its fields in post meta. Categories use the taxonomy `paw_event_cat`. There is no extra table. |
| 2 | **Where staff work.** A new **Events** view in the Pawfectly dashboard, open to everyone with the staff capability. wp-admin shows no event screens: the post type has `show_ui` false. |
| 3 | **Calendar.** A full copy of the Business Directory calendar: Month / List / Day, search, Today and arrows that load without a page reload, a month picker, category colours and a category filter, and "Add to calendar" links. |
| 4 | **Emoji.** The Business Directory's emoji picker is ported for every emoji field. It shows Fluent Emoji images from the kohid/icons collection on jsDelivr, in the dashboard only. The saved value is the plain emoji character. |
| 5 | **Ticket buttons until sign-ups ship.** "Get Tickets" and "Reserve Your Spot" go to the event's **ticket link** when one is set. Otherwise they scroll to the closing section, which reads "Sign-ups open soon". |
| 6 | **Cover photo.** Optional. When set, it sits behind the page heading and is the picture on cards and social previews. The emoji is shown either way. |

## 3. What an event holds

| Field | Rules |
|---|---|
| Title | Required, at most 120 characters. This is the post title. |
| Tagline | Optional, at most 160 characters ("A jingle-bark holiday party for dogs of every breed"). |
| Emoji | Optional, one emoji (at most 16 bytes; the picker fills it). |
| Cover photo | Optional, from the media library. This is the featured image. |
| Category | Optional, one of the event categories. |
| Starts / ends | Required: a date and time for each. The end must be after the start. Events can run over several days. Times are in the site's timezone. |
| Venue | Name (required, at most 120 characters) and address (optional, at most 200 characters). |
| Price per dog | Optional amount, cleaned like the Settings prices. "Free event" is a checkbox. Leave both empty for no price line. |
| Highlight note | Optional, one line, at most 200 characters ("🐾 FREE for Dog Park Members — call or text 239-420-7418 for your coupon code"). Phone numbers become tap-to-call links. |
| Description | Optional. Paragraphs, bold, italic, links and lists (the same tags as the waiver, plus links). |
| Ticket link | Optional URL (see §2-5). |
| Status | Published or Draft. Drafts appear only in the dashboard. |

### 3.1 Sections

Each section is optional. A section with no rows is not shown. Headings and intros are editable and start from the defaults below. `{price}` in a heading or intro becomes "$15", or "Free".

| Section | Heading and intro | Each row | At most |
|---|---|---|---|
| **Sessions** | "Pick Your Pup's Session" / "Two sessions keep playtime safe and fun for every size — choose yours when you register." | From time, to time, name ("Session 1"), who it's for ("Small dogs (under 30 lbs)") | 8 rows |
| **Ticket includes** | "Everything Your {price} Ticket Includes" / "One ticket, a whole morning of perks for you and your pup." | Emoji, text | 20 rows |
| **Extra goodies** | "Extra Goodies You'll Love" | Emoji, title, text | 12 rows |
| **Good to know** | "Good to Know" | Emoji (🐾 by default), text | 20 rows |
| **Closing** | "Reserve Your Spot Today!" / "Register in seconds — we can't wait to meet your pup!" | none (one block) | |

- Row text is plain, at most 300 characters.
- Phone numbers in row text become tap-to-call links on the page.
- Rows keep the order staff give them.

### 3.2 Stored as

- **Post meta, prefixed `_paw_ev_`:**
  - `start` and `end`: "Y-m-d H:i:s", local time, so date ranges are easy to query.
  - `tagline`, `emoji`, `venue`, `address`, `price`, `free`, `note`, `ticket_url`
  - `sections`: one array holding the five sections, each with its heading, intro and rows.
- **The description** is `post_content`. **The category** is a `paw_event_cat` term.
- **Category colours:** term meta `_paw_color`, a hex value from Pawfectly's palette. Settings → Event categories edits the names and colours.

## 4. Public pages

### 4.1 Event page (`/event/<slug>/`)

The post type is public with the rewrite slug `event`: a singular slug, so a site page at `/events/` keeps working, as in the Business Directory. The plugin supplies the page body through `the_content` for `paw_event`, so the theme's header and footer stay. From top to bottom:

1. **Date line:**
   - "One Day Only · Saturday, December 19th, 2026" for a single-day event.
   - "Friday, December 18th – Sunday, December 20th, 2026" for several days.
2. **Heading:** emoji, title and tagline, on the cover photo when there is one.
3. **Facts row:**
   - 📅 the date
   - 🕘 the time range
   - 📍 the venue and address, linked to Google Maps
   - 🎟️ "$15 per dog", or "Free"
4. **Description.**
5. **Ticket area:**
   - the **Get Tickets — $15** button (just "Get Tickets" when free)
   - the highlight note
   - **Add to calendar**: Google, Outlook and an .ics file
6. **The sections, in the §3.1 order:**
   - **Sessions:** a card per session, with the time on top and "Session 1: Small dogs (under 30 lbs)" below.
   - **Ticket includes:** a list, each item with its emoji.
   - **Extra goodies:** cards, each with an emoji, title and text.
   - **Good to know:** a list, each item with its emoji.
7. **Closing block:**
   - the heading
   - one summary line: "Saturday, December 19th, 2026, 10:00 AM – 2:00 PM — $15 per dog"
   - the intro
   - the **Reserve Your Spot** button
8. **Past events:** a ribbon reads "This event has ended", and the ticket buttons are hidden.

The page also gives search engines and shares the details they use:
- `Event` structured data (JSON-LD): name, start, end, location, offers and image.
- Social preview tags, unless an SEO plugin already prints them.

### 4.2 `[pawfectly_calendar]`

The Business Directory's calendar, in Pawfectly's look (CSS under `.paw-cal`).

- **Toolbar:**
  - Search ("Search for events" plus a **Find Events** button).
  - The category filter (All + each category, with its colour dot).
  - List / Month / Day switch.
- **Navigation:**
  - ‹ › arrows and **Today**.
  - A title button ("December 2026 ▾") that opens a month-and-year picker.
- **Month view:**
  - Monday-first, whole weeks; days from the next and previous months are muted, and today is marked.
  - Each event is a chip showing the start time and title, in its category colour.
  - An event over several days shows as one connected bar across those days.
  - After 3 chips a day: a "+N more" button.
  - A chip opens a small card with:
    - the photo or emoji
    - the time and venue
    - a two-line summary
    - **View event** and **Add to calendar**
- **List view:** the shown month's events grouped by day, with the date block, time, title, venue and summary.
- **Day view:** the focused day's events on a timeline.
- **Loading:** changing month, day, view, search or category asks `GET /pawfectly/v1/events/calendar?date=YYYY-MM-DD&q=&cat=`. That's a public route that returns the rendered views; the shortcode's first render uses the same builder.
- **Links:**
  - `?paw_cal=YYYY-MM` opens a given month.
  - Shortcode attributes: `category="slug"` and `view="month|list|day"`.
- **Phone width:** Month shows dots instead of chips, and tapping a day opens its list.

### 4.3 `[pawfectly_events]`

- **Tabs:** Upcoming (default) / Happening now / Past / All.
- **Cards:**
  - the photo, or the emoji on a tinted panel
  - a date block (DEC / 19)
  - the title, time, venue, price and category
- **More events:** a **Load more** button, 9 events at a time.
- **Shortcode attributes:** `category`, `limit`, and `show="upcoming|past|all"`.

### 4.4 Pages

`Pages::TAGS` gains **Event Calendar** (`pawfectly_calendar`) and **Events** (`pawfectly_events`). They get the usual Settings rows with Copy and **Create page**.

## 5. Dashboard: Events

- **Menu:** "Events" sits between Reservations and Settings (`?paw_view=events`).
- **List:**
  - Tabs: Upcoming / Drafts / Past / All, plus search.
  - Columns: the emoji, title, date and time, category, venue, price and status.
  - Rows open the editor.
  - Each row has **View** (opens the public page) and **Duplicate** (copies the event as a draft with " (copy)" added to the title, so next year's party starts from this one).
- **Add Event / editor:** a full page in the dashboard (`?paw_view=events&event=new|<id>`), in cards:
  1. **Basics:** emoji (picker button), title, tagline, category, cover photo (media library; Remove), status.
  2. **When and where:** start date and time, end date and time, venue, address.
  3. **Price and tickets:** price per dog, Free event, ticket link, highlight note.
  4. **Description:** a textarea that accepts the waiver's tags plus links, with a short help line.
  5. **Sections:** one card per section, holding:
     - the heading and intro fields
     - the rows; each row has a drag handle, its fields, **Move up** / **Move down** buttons (for the keyboard) and **Remove**
     - **+ Add session** / **+ Add item** / **+ Add goodie** under the rows
     - a hint when a section has no rows: "Leave empty to hide this section".
  6. **Actions:**
     - **Save draft** and **Publish** (or **Update** once published)
     - **Preview**, which opens the page in a new tab; for a draft, the preview works only for signed-in staff
     - **Delete**, behind a confirmation
- **Saving and errors:**
  - Saving posts JSON. Errors come back keyed by field, including row fields such as `sections.sessions.1.from`.
  - The editor marks each field and scrolls to the first one with an error.
  - Leaving with unsaved changes asks for confirmation.
- **Settings → Event categories** (staff who can manage settings): add, rename and delete categories; pick each colour from the palette.

### 5.1 Staff routes (`StaffRest`, staff capability)

| Route | Does |
|---|---|
| `GET /staff/events?tab=&q=&page=` | The list. |
| `GET /staff/events/<id>` | One event, for the editor. |
| `POST /staff/events` | Create. |
| `POST /staff/events/<id>` | Update. |
| `DELETE /staff/events/<id>` | Delete. Moves the event to the WordPress trash. |
| `POST /staff/events/<id>/duplicate` | Copy as a draft. |
| `POST /staff/event-categories` | Save the category list (manage-settings only). |

The media picker runs as the staff member, and staff already have `upload_files`.

## 6. Code layout

| Unit | Does |
|---|---|
| `Events` | Registers the post type, the taxonomy and the meta. Holds `defaults()`, `sanitize( array $in ): array\|WP_Error` (the field and section rules of §3), `get( int $id ): ?array` and `save()`, and `status( array $ev ): upcoming\|current\|past`. The only place that reads and writes event meta. |
| `EventDates` | Formats the date line, the time range, the summary line and the Add to calendar links (Google, Outlook, .ics). These are pure functions, so they're easy to test. |
| `Calendar` | `view_data( date, q, cat )` returns the month grid, list and day views, as in the Business Directory: events bucketed onto every day they cover. It also serves the `[pawfectly_calendar]` shortcode and the public calendar route. |
| `EventsList` | The `[pawfectly_events]` shortcode, its cards and Load more. |
| `EventPage` | The single event page body, JSON-LD, social tags and the .ics download (`?paw_ics=1`). |
| `StaffRest` | The routes of §5.1, which hand off to `Events`. |
| Templates | `templates/event.php`, `partials/event-card.php`, `partials/dashboard-events.php` and `partials/dashboard-event-edit.php`. |
| Assets | `calendar.js` / `calendar.css` (public), `events-admin.js` (the editor and repeaters), `emoji-picker.js` / `emoji-picker.css` (ported). Each loads only on pages that use it. |

## 7. Edge cases

- **Times:** stored and shown in the site's timezone. "Happening now" compares against the site's current time.
- **End before start:** refused, with the error on **Ends**.
- **An event lasting past midnight:** it shows on both days. Its chip reads "All day" on full middle days, and gives the start or end time on the first and last day.
- **A deleted category:** its events keep working with no category, and show in the default coral.
- **Text from staff:** escaped on output. The description is filtered to the allowed tags. Emoji fields keep only the emoji characters.
- **Very full days:** they collapse behind "+N more", and the List and Day views show everything.
- **Drafts:** never appear in the public calendar, list or route. Visitors get a 404 on a draft's page.

## 8. Testing

- **Unit tests:**
  - `Events::sanitize`: required fields, end after start, price and Free, URL, emoji, every section's row limits and row fields, text lengths, unknown keys dropped.
  - Save and get round trip; duplicate.
  - `EventDates`: date line for one and several days, time range, summary, Google and Outlook links, .ics content.
  - `Calendar`: whole-week grid, an event over several days bucketed onto each day, search, category filter, drafts left out, "+N more".
  - `EventsList` tabs.
  - Staff routes: permissions, errors keyed by row, the category save limited to manage-settings.
  - Event page: section order, empty sections hidden, `{price}`, past-event ribbon, JSON-LD.
- **Playwright:**
  - Dashboard:
    - Add Event with every section; add, remove and reorder rows (drag and buttons).
    - The emoji picker fills a field.
    - Errors are marked on their row field.
    - Save a draft, publish, duplicate, delete.
  - Calendar:
    - Month, List and Day.
    - The arrows, Today and the month picker.
    - Search and the category filter.
    - An event over several days; "+N more".
    - The chip card and its link.
    - Phone layout.
  - Event page: the X-Mas Party example reproduced. Checked at phone width with no sideways scroll.
- **Manual check on real WordPress:**
  - permalinks
  - the theme around the event page
  - an SEO plugin present
  - the media picker
