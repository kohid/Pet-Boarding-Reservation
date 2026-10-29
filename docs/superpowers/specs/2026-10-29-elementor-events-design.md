# Elementor event templates: Dynamic Tags and `[pawfectly_event]` (v0.7)

## Goal

Let staff design the single event page in **Elementor Pro → Theme Builder → Single** (condition: Include → Events → All) with Elementor's own widgets. Every field from **Add Event** is available as a Dynamic Tag. A shortcode draws the parts Elementor can't build from single values: the repeatable section rows, the formatted description, the sign-up form and the Add to calendar menu.

The user's choices (2026-10-29):
- **B, full redesign:** the page is built with Elementor widgets; the plugin supplies data, not layout.
- **Plain lists:** shortcode lists carry almost no styling, so they inherit the theme and Elementor fonts and colours. Every element has a class name to style.
- **Everything from Add Event:** every editor field has a tag. The one exception is the member coupon code, which never leaves the server.

## What stays the same

- The plugin-built page (the `the_content` filter) still works. It shows when the theme or Elementor's **Post Content** widget prints the content, and looks as before.
- JSON-LD, share tags, `.ics`, previews and the sign-up route are unchanged.
- Without Elementor Pro, nothing Elementor-related loads. The shortcode works with any builder.

## Dynamic Tags

All tags sit in one group, **Pawfectly Event**. They read the event being shown: `get_the_ID()`, which is the queried event on its page, or the current item in an Elementor Loop Grid. In the Elementor editor this is the preview post chosen in the template's Preview Settings. When that post isn't an event, or is a draft the viewer can't preview, every tag is empty, so Elementor's own Fallback setting applies.

### Text tags

| Tag | Value | Controls |
|---|---|---|
| Title | `title` | |
| Tagline | `tagline` | |
| Emoji | `emoji` | |
| Category | `category_name` | |
| Start date | start, formatted | Format (PHP date format; default `l, F jS, Y`) |
| Start time | start, formatted | Format (default `g:i A`) |
| End date | end, formatted | Format (default `l, F jS, Y`) |
| End time | end, formatted | Format (default `g:i A`) |
| Date line | `EventDates::date_line()` ("One Day Only · Saturday, December 19th, 2026") | |
| Day | `EventDates::day()` | |
| Time range | `EventDates::time_range()` | |
| Summary line | `EventDates::summary()` | |
| Venue | `venue` | |
| Address | `address` | |
| Venue + address | `EventDates::where()` | |
| Price | the number, like `15` (blank when free or not set) | |
| Price text | `EventDates::price_text()` ("$15 per dog", "Free") | |
| Free | "Free" when the event is free, else empty | Text (default "Free") |
| Ticket button text | the page's button label ("Get Tickets — $15" / "Get Tickets") | |
| Highlight note | `note` | |
| Description | the description as plain text (tags stripped) | |
| Spots per session | `spots`, empty when there's no limit | |
| Spots left | "3 spots left" / "Full" / empty when there's no limit | Session (1–8, or "no sessions") |
| Section heading | that section's heading, with `{price}` filled in | Section: Sessions, Ticket includes, Extra goodies, Good to know, Closing |
| Section intro | that section's intro, with `{price}` filled in | Section (same list) |

### Link tags

| Tag | URL |
|---|---|
| Tickets / sign-up | `ticket_url` when set, else `#paw-event-reserve` (the sign-up part) |
| Ticket link | `ticket_url` (empty when not set) |
| Event page | the event's permalink (for Loop Grid cards) |
| Google Calendar / Outlook / Apple iCal | `EventDates::links()` |
| Map | Google Maps search for venue + address (the same link the page uses) |

### Image and colour tags

| Tag | Value |
|---|---|
| Cover photo | `{ id, url }` of the cover photo; empty when there is none |
| Category colour | the category's colour (`#rrggbb`) |

## `[pawfectly_event]`

Attributes:
- `part`, one of `description`, `sessions`, `includes`, `goodies`, `good_to_know`, `signup`, `addcal`.
- `id`, optional. By default the shortcode uses the event being shown, like the tags.

It outputs nothing when the part is empty, the post isn't an event, the event is a draft the viewer can't preview, or `part` is unknown.

| part | Output |
|---|---|
| `description` | the description's HTML, filtered as on the page (paragraphs, bold, italic, lists, links) |
| `sessions` | `<ul class="paw-ev-list paw-ev-list--sessions">`; each `li.paw-ev-row` has `span.paw-ev-row__time`, `span.paw-ev-row__name` ("Session 1"), `span.paw-ev-row__who` |
| `includes` | `ul.paw-ev-list--includes` > `li.paw-ev-row` > `span.paw-ev-row__emoji` + `span.paw-ev-row__text` |
| `goodies` | `ul.paw-ev-list--goodies` > `li.paw-ev-row` > `span.paw-ev-row__emoji` + `strong.paw-ev-row__title` + `span.paw-ev-row__text` |
| `good_to_know` | `ul.paw-ev-list--good_to_know`, the same markup as `includes` |
| `signup` | in `<div class="paw-event paw-event--part" id="paw-event-reserve">`: "This event has ended" when past; nothing when the event has a ticket link; the sign-up form when published; nothing for a draft |
| `addcal` | the Add to calendar menu (`details.paw-event__addcal`) inside `div.paw-event.paw-event--part` |

Plain lists:
- Phone numbers in row text are tap-to-call, as on the page.
- Rows with an empty emoji get no emoji span; the plugin-styled page keeps its 🐾 default.
- The only CSS shipped for the lists is a tiny reset (`list-style: none; padding: 0`) and a small gap between the emoji and the text. Fonts and colours come from the theme and Elementor.
- The `signup` and `addcal` parts keep the plugin's form styling, because they are working controls with errors and the thank-you panel. They take the page's font (`font-family: inherit`), and their accent follows the category colour (`--paw-ev`).
- On event pages, `calendar.css` and `signup.js` are already enqueued by `EventPage::enqueue`. When the shortcode renders `signup` or `addcal` elsewhere, it enqueues them itself.

## Code

- **`includes/EventFields.php`** (new): `value( array $ev, string $field, array $opts = array() ): string`, plus `link()`, `image()`, `color()`. This is the one place each tag's value is worked out, and it is unit-tested without Elementor. `EventFields::current(): ?array` returns the event being shown, or null (wrong post type, a draft the viewer can't preview). The coupon code has no field.
- **`includes/EventShortcode.php`** (new): registers `[pawfectly_event]` and renders a part through `templates/partials/event-*.php`.
- **Template split.** `templates/event.php` keeps its layout, but its section lists come from new partials, with a `$paw_plain` flag that picks plain classes over the page's `paw-event__*` classes:
  - `templates/partials/event-rows.php` (sessions, includes, goodies, good to know)
  - `templates/partials/event-addcal.php`
  - `templates/partials/event-closing.php` (ended / ticket link / form)

  The page looks and behaves as before, and the existing event-page tests guard this.
- **`includes/ElementorTags.php`** (new): on `elementor/dynamic_tags/register`, if `\ElementorPro\Plugin` exists:
  - It registers the group and requires `includes/elementor/tags.php`.
  - That file holds one small named class per tag, extending `Tag` (text) or `Data_Tag` (link, image, colour). Each declares its field and controls and calls `EventFields`.
  - Named classes are needed because Elementor re-creates tags by class name.
  - The file is loaded only inside the hook, so the Elementor base classes exist.
- `Plugin.php` gains `EventShortcode::init()` and `ElementorTags::init()`.

## Tests

- **`tests/event-fields-test.php`:**
  - every field on the X-Mas Party fixture, date and time formats, the free event, an event with no price
  - spots left (limit, full, no limit)
  - section heading with `{price}`
  - links, image and colour
  - a draft for visitors (empty) and for staff (shown)
  - a post that isn't an event
  - no field ever returns the coupon code
- **`tests/event-shortcode-test.php`:**
  - each part's markup and classes
  - empty parts produce empty output
  - unknown part, no event, draft
  - signup ended / ticket link / form
  - `id=` works
  - the description is filtered
  - phone links
  - the coupon never appears
- **`tests/elementor-tags-test.php`:** with small stubs of the Elementor base classes and manager:
  - the group and every tag register, with unique names and the right categories
  - a text tag renders its value, link/image/colour tags return theirs
  - the format / section / session controls are passed through
  - nothing registers without Elementor Pro
- **`tests/event-page-test.php`:** unchanged assertions still pass after the template split.
- **Browser** (`tests/e2e/events-e2e.js`): a page built from the plain parts plus the `signup` part. It checks:
  - the lists inherit the page font
  - the sign-up form works inside `#paw-event-reserve`
  - a "Get Tickets" link to `#paw-event-reserve` scrolls to it
  - no horizontal scroll at 390px

  Elementor itself isn't run here. The tags are covered by the unit tests above.

## Docs

- README: a new "Designing event pages in Elementor" section with the tag list, the shortcode parts, and a short recipe for the X-Mas Party layout.
- CLAUDE.md conventions: `EventFields`, `EventShortcode`, `ElementorTags`; the coupon never gets a field.
- Version 0.7.0.

## Out of scope

- Elementor widgets of our own. Dynamic Tags plus the Shortcode widget cover the page.
- Loop Grid query sources for events. Elementor Pro's Posts query with post type Events already works.
- Dynamic tags for individual section rows. Rows are lists, which Elementor tags can't repeat.
