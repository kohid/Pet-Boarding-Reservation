# CLAUDE.md

Guidance for Claude Code sessions working in this repository.

## Project

**Pawfectly Pet Boarding Reservation**: a WordPress plugin (PHP 8.0+, WordPress 6.0+, vanilla JS) modeled on kohid/Sprint-Booking, with a Metronic 8-style look in Pawfectly's brand colors.
- Specs: `docs/superpowers/specs/2026-10-25-pet-boarding-reservation-design.md` (v0.1), `docs/superpowers/specs/2026-10-25-accounts-dashboard-design.md` (v0.2), `docs/superpowers/specs/2026-10-25-login-services-design.md` (v0.3), `docs/superpowers/specs/2026-10-25-multi-pet-design.md` (v0.4), `docs/superpowers/specs/2026-10-29-events-calendar-design.md` (v0.5), `docs/superpowers/specs/2026-10-29-event-signups-design.md` (v0.6), `docs/superpowers/specs/2026-10-29-elementor-events-design.md` (v0.7), `docs/superpowers/specs/2026-10-29-saved-pets-design.md` (v0.10)
- Plans: `docs/superpowers/plans/2026-10-25-pet-boarding-reservation.md`, `docs/superpowers/plans/2026-10-25-accounts-dashboard-plan.md`, `docs/superpowers/plans/2026-10-25-login-services-plan.md`, `docs/superpowers/plans/2026-10-25-multi-pet-plan.md`, `docs/superpowers/plans/2026-10-29-events-calendar-plan.md`, `docs/superpowers/plans/2026-10-29-event-signups-plan.md`, `docs/superpowers/plans/2026-10-29-elementor-events-plan.md`

Conventions:
- Namespace `Pawfectly\`, with `includes/Foo.php` loaded automatically.
- Prefixes: `paw_` for options, transients, the table, and nonces; `paw-` for CSS and handles. Text domain `pawfectly-reservations`.
- Front-end CSS is scoped under `.paw-app` (form), `.paw-um` / `.paw-acct` (account pages), `.paw-dash` (dashboard); admin CSS under `.paw-admin`. Resets use `:where()`; component selectors carry the root prefix so theme rules can't override them.
- Staff data only over capability-checked REST routes (`StaffRest`); owner routes in `AccountRest`.
- Login and access: `[pawfectly_login]` (`Login`) is the sign-in page for everyone; `Access` limits wp-admin to the admin-email account and the `developer` role (escape: `PAW_WP_ADMIN_OPEN`).
- Prices live only in Settings and are read only through `Services` (catalog, prices by dog size, estimate).
- Every field is defined once, in `includes/Fields.php`.
- Events: `paw_event` posts (category taxonomy `paw_event_cat`), read and written only through `Events` (meta `_paw_ev_*`). Dates and labels come from `EventDates`. Public pages are `Calendar` (`[pawfectly_calendar]`), `EventsList` (`[pawfectly_events]`) and `EventPage` (`/event/<slug>/`). Staff routes are in `StaffEvents`. Public CSS is scoped under `.paw-cal`, `.paw-events` and `.paw-event`.
- Event sign-ups: `paw_ev_signup` posts, read and written only through `Signups` (meta `_paw_su_*`, references `EVT-XXXXXX`). The public route is `SignupRest` (`POST /events/<id>/signup`); visitors' forms fetch a fresh nonce from `/nonce` because event pages may be cached, so never print a nonce in a visitor's page; signed-in people get their own (`window.PAW_SIGNUP_NONCE`, from `EventPage::enqueue`), because a `/nonce` call without one is treated as logged out. Sign-ups point at their session by its place in the list, so `StaffEvents::save` refuses to remove, move or retime a session that has sign-ups; the spots check and insert run under `Signups::locked()`. Staff routes and the CSV (`admin-post` `paw_signups_csv`) are in `StaffEvents`; owner cancel is in `AccountRest`. An event's member coupon code never leaves the server: use `Events::public_view()` for anything public.
- Elementor: every Add Event field's value comes from `EventFields` (text, link, image, colour; never the coupon). `ElementorTags` registers the Elementor Pro Dynamic Tags (classes in `includes/elementor/tags.php`, loaded only when Elementor Pro is active, one named class per tag because Elementor re-creates tags by class name). `[pawfectly_event part="…"]` (`EventShortcode`) draws the description, section rows as plain lists (`.paw-ev-*`), the sign-up form and the Add to calendar menu; `EventShortcode::part()` is shared with the Section rows / Description (formatted) tags. Tags sit in groups that follow Add Event (`ElementorTags::GROUPS`; repeaters marked), and each repeater has Heading/Intro, All rows and a Row field tag (`EventFields::row_value()`, fields in `EventFields::ROW_FIELDS`); never rename a tag's `get_name()`, templates store it. In the Elementor editor the current post is the template, so `EventFields::current()` uses its Preview Settings event (else the latest published one). The event page shares those partials (`templates/partials/event-rows.php`, `event-addcal.php`, `event-closing.php`).
- Starter events (Settings switch): `StarterEvents` saves the 30 events in `includes/data/starter-events.php` through `Events::sanitize()` / `Events::save()` and remembers their IDs only in the option `paw_starter_events` (nothing on the events marks them). Off trashes them except those with active sign-ups. Route `POST /staff/starter-events` (settings access).
- Saved pets (My Profile): user meta `paw_pets` ([{uid, values}]) and `paw_owner_info`, read and written only through `SavedPets`, with the booking form's own field rules (pet/health/care steps minus files and `vaccinations_current`). Routes `POST /account/pets` and `/account/info`; `Rest::submit` remembers a signed-in owner's pets; `Accounts::prefill()` returns `pets` (those from the last booking) and `saved_pets` for the form's "Who's coming?".
- Links handed to JavaScript are plain URLs. Don't use `wp_nonce_url()` for them, because it returns `&amp;`.
- Several pets per booking: per-pet fields carry `per_pet` (form names `pets[N][id]`, files `vaccination_files_N[]`, error keys `pets.N.id`); read stored pets through `Reservations::pets()`. A step with no field shown for the booking type is skipped.

## Commands

- Unit tests: `bash bin/test.sh`
- Browser tests (set `PAW_SHOTS=dir` to save screenshots):
  - `php tests/e2e/build.php && php tests/e2e/build.php signed-in && node tests/e2e/form-e2e.js`
  - `php tests/e2e/build-account.php && node tests/e2e/account-e2e.js`
  - `php tests/e2e/build-dashboard.php && node tests/e2e/dashboard-e2e.js`
  - `php tests/e2e/build-dashboard.php && node tests/e2e/dashboard-events-e2e.js`
  - `php tests/e2e/build-events.php && node tests/e2e/events-e2e.js`
- Release zip: `bin/build-release.sh`

## Required skills: use them in every session

Every session in this repo works together with these four skills. Invoke them with the `Skill` tool at the stage where each applies. Don't skip one because a task looks small.

| Skill | When to use it |
|-------|----------------|
| `superpowers:brainstorming` | Before any creative or feature work. Clarify intent, propose approaches, get the design approved. |
| `feature-dev:feature-dev` | To drive feature work end to end: discovery, codebase exploration, clarifying questions, architecture options, implementation, quality review, summary. |
| `anthropic-skills:fullstack-developer` | For backend, API, database, and full-stack architecture decisions and code. |
| `frontend-design:frontend-design` | For any UI work: new screens, components, layout, typography, and visual direction. |

### Session workflow

1. **Brainstorm first.** Start with `superpowers:brainstorming`. Classify the task (spike, bounded, or architectural) and get approval before writing any code.
2. **Run the feature-dev phases.** Use `feature-dev:feature-dev` to structure the work. Ask every clarifying question before designing, and wait for approval before implementing.
3. **Build with the domain skills.**
   - Server, data, or API work: `anthropic-skills:fullstack-developer`.
   - Anything the user sees: `frontend-design:frontend-design`.
   - Features that span both: use both.
4. **Review before finishing.** Run the feature-dev quality-review phase, check that the work is verified, then summarize what changed.

## Documentation

- Write all design notes, specs, and plans as Markdown (`.md`) files in the repo.
- Specs: `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`
- Plans: `docs/superpowers/plans/YYYY-MM-DD-<topic>-plan.md`
- Keep this file up to date as the stack, commands, and conventions are settled.
