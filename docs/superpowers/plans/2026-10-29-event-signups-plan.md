# Event Sign-ups Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Owners sign up for an event (session, dogs, member code) on the event page. Staff manage the sign-ups in the dashboard, and owners see and cancel theirs in My Bookings.

**Architecture:**
- **Storage:** sign-ups are private `paw_ev_signup` posts, read and written only through `Signups`.
- **Event fields:** `Events` gains `spots` and `coupon`.
- **Public:** one public route (`SignupRest`) behind the booking form's protections.
- **Dashboard and My Bookings:** they reuse their existing patterns.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, vanilla JS, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-29-event-signups-design.md`

## Global Constraints

- Same conventions as v0.5 (CLAUDE.md):
  - `paw_` / `paw-` prefixes, the text domain, escaping on output, plain URLs to JS.
  - Staff routes behind `StaffRest::can`.
- Limits:

  | Field | Limit |
  |---|---|
  | Dogs | 1–5 |
  | Dog name / breed / weight | 60 / 60 / 20 characters |
  | Notes | 500 characters |
  | Coupon | 3–30 characters |
  | Spots | 1–500 |
  | Rate limit | 5 per 10 minutes |
- Reference: `EVT-` + 6 characters from `Reference::ALPHABET`.
- Version **0.6.0**.

## Review Focus

1. **Two people take the last spot at once:** the server must refuse the second. The check happens immediately before the insert. Test: Task 1, "a full session is refused".
2. **The coupon never leaks:** it must not appear in the page HTML, the calendar, list or REST payloads. Test: Task 3 page render and Task 1 `Events::get` public use.
3. **A cancelled sign-up frees its spots:** it must not block a duplicate email. Test: Task 1.
4. **Owners can only cancel their own sign-ups, and only before the event starts.** Test: Task 5.
5. **Changing an event's price later** leaves existing sign-up totals unchanged (they are snapshots). Test: Task 1.

---

### Task 1: Signups model, plus Events spots and coupon
- **Files:** `includes/Signups.php`, `includes/Reference.php` (a prefix argument), `includes/Events.php` (spots, coupon), `tests/signups-test.php`, `tests/events-test.php`.
- **Produces:**
  - `Signups::TYPE = 'paw_ev_signup'` and `Signups::MAX_DOGS = 5`.
  - `sanitize( array $event, array $in ): array|WP_Error`: errors keyed `first_name`, `email`, `session`, `dogs`, `dogs.N.name`, `coupon`, `agree`.
  - `create( array $event, array $clean, int $user_id, string $ip ): array` returns the stored sign-up.
  - `get( int $id ): ?array`.
  - `for_event( int $event, bool $active_only = false ): list<array>`.
  - `taken( int $event, int $session ): int` (dogs on active sign-ups).
  - `spots_left( array $event, int $session ): ?int` (null means no limit).
  - `set_status( int $id, string $status ): bool`.
  - `for_user( int $user_id ): list<array>`.
  - `can_cancel( array $su, array $event, ?DateTimeImmutable $now = null ): bool`.
  - `csv_rows( int $event ): list<list<string>>`.
  - Sign-up array keys: `id`, `event`, `session`, `session_label`, `status`, `reference`, `first_name`, `last_name`, `email`, `phone`, `user_id`, `dogs`, `dog_count`, `coupon`, `price_each`, `total`, `notes`, `created`.
- **Events:** `sanitize` accepts `spots` (blank or 1–500) and `coupon` (blank or 3–30 characters, kept as typed). `Events::get()` returns both. `public_view( array $ev ): array` strips the coupon for anything public.
- [ ] Write failing tests:
  - required fields
  - dogs 1–5, with blank dogs dropped
  - a session is required when the event has sessions, and must be a valid index
  - coupon: right, wrong, or a different case → free
  - the agreement is required
  - duplicate email refused; allowed again after a cancel
  - a full session is refused, and a cancel frees its spots
  - total = dogs × price; the price snapshot is kept after the event's price changes
  - reference matches `/^EVT-[A-HJ-NP-Z2-9]{6}$/`
  - `for_user`
  - `can_cancel` before and after the start
  - the CSV has one row per dog
- [ ] Run → FAIL. Implement. Run `bash bin/test.sh` → all pass. Commit.

### Task 2: Public sign-up route and emails
- **Files:** `includes/SignupRest.php`, `includes/Mailer.php`, `includes/Rest.php` (register), `tests/signup-rest-test.php`, `tests/mailer-test.php`.
- **Produces:** `POST /pawfectly/v1/events/(?P<id>\d+)/signup` (JSON body). It returns `201 {reference, summary:{event, when, session, dogs, total_text}, calendar:{google, outlook, ics}}`, or a `paw_invalid` 400 with `fields`. Other refusals:
  - 403 for a bad nonce
  - 429 for too many attempts
  - 404 for a draft, a past event, an event with a ticket link, or no such event
- Also `Mailer::signup_received( array $su, array $event ): void`.
- [ ] Failing tests for every refusal, the honeypot, the repeated submission id, success creating one sign-up, and both emails sent with the right details. Implement. All pass. Commit.

### Task 3: The form on the event page
- **Files:** `templates/partials/event-signup.php`, `templates/event.php`, `assets/js/signup.js`, `assets/css/calendar.css`, `includes/EventPage.php` (enqueue, config), `tests/event-page-test.php`, `tests/e2e/build-events.php`, `tests/e2e/events-router.php`, `tests/e2e/events-e2e.js`.
- **Shows:**
  - The form renders in `#paw-event-reserve` when there is no ticket link and the event is not past.
  - Session cards show the spots left and are disabled when full.
  - The coupon field is hidden for free events.
  - Prefill for signed-in owners.
  - The config holds the route, nonce, price, max dogs and strings — never the coupon.
- [ ] Failing tests:
  - Unit: form present or absent (ticket link, past); full session disabled; the coupon is never in the HTML.
  - Browser:
    - pick a session, add 2 dogs, remove one, live total
    - a wrong coupon shows its error; the right coupon gives "Free"
    - a missing name shows its error
    - success shows the thank-you with an `EVT-` reference
    - phone width
- [ ] Implement with `frontend-design:frontend-design`. All pass. Commit.

### Task 4: Dashboard sign-ups
- **Files:** `includes/StaffEvents.php`, `includes/Admin.php` (CSV handler) or `StaffEvents` (an `admin_post` handler), `templates/partials/dashboard-events.php`, `assets/js/events-admin.js`, `assets/css/dashboard.css`, `tests/staff-events-test.php`, `tests/e2e/dashboard-events-e2e.js`.
- **Produces:**
  - The Spots and Coupon fields in the editor.
  - The Sign-ups card.
  - `GET /staff/events/<id>/signups` returns `{sessions:[{label, taken, spots}], rows:[…], csv_url}`.
  - `POST /staff/signups/<id>/status`.
  - List rows gain `dogs`.
- [ ] Failing tests: permissions; the counts; a status change; the CSV through `admin-post` with nonce and capability; the browser card. Implement. All pass. Commit.

### Task 5: My Bookings
- **Files:** `templates/my-bookings.php`, `includes/AccountRest.php`, `assets/js/account.js`, `tests/my-bookings-test.php`, `tests/account-rest-test.php`, `tests/e2e/build-account.php`, `tests/e2e/account-e2e.js`.
- [ ] Failing tests:
  - the card lists the owner's sign-ups
  - cancel works for their own before the start, and is refused for another owner's or after the start
  - browser: Cancel updates the row
- [ ] Implement. All pass. Commit.

### Task 6: Docs, version, review, release
- [ ] README (Event sign-ups), CLAUDE.md conventions, version 0.6.0.
- [ ] All suites and `bin/build-release.sh`.
- [ ] One fresh whole-branch review; fix the Critical and Important findings test-first.
- [ ] PR, merge, confirm main, Release `v0.6.0`.
