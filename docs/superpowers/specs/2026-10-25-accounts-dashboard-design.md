# Pawfectly v0.2: owner accounts, account menu, staff dashboard and form layout (design spec)

- Status: **approved in conversation** (the user approved recommendations 1–15 plus the form requests below and asked for a release when done)
- Date: 2026-10-25
- Builds on: `docs/superpowers/specs/2026-10-25-pet-boarding-reservation-design.md` (v0.1). Conventions there still apply.
- Reference: kohid/Sprint-Booking `UserMenu`, `Profile`, `Accounts`, `MyBookings`, `Dashboard`, `AdminRest`, `Pages`.

## 1. Goals

1. Owners can have an optional account. While filling the owner step they are invited to create one, or to sign in and have their details filled in. Booking as a guest still works.
2. A header account menu (`[pawfectly_user_menu]`) works the same way as Sprint-Booking's.
3. Owners see and cancel their own reservations (`[pawfectly_my_bookings]`) and manage their details (`[pawfectly_my_profile]`).
4. Staff get a full-screen front-end dashboard (`[pawfectly_dashboard]`) with Overview and Reservations.
5. Form layout changes:
   - progress bar at the top
   - Back/Next as ordinary buttons below the step, not fixed to the screen
   - a transparent background, so it can be designed in Elementor
   - styles that hold up against theme input styles
6. Released as v0.2.0, updating through the Plugins screen.

## 2. Decisions

| # | Decision |
|---|---|
| 1 | Accounts are optional; guests can still book. |
| 2 | New accounts verify their email with a link. Unverified accounts can sign in. |
| 3 | My Bookings shows reservations made while signed in, plus reservations with the same email once that email is verified. |
| 4 | Signed-in owners get the form filled in from their last reservation: the owner, pet, care and emergency/vet steps. Never the stay dates, health answers, files, signature or agreement. With no previous reservation, it uses the account's name, email and phone. |
| 5 | Owners can cancel a Pending or Confirmed reservation whose check-in has not passed. Staff are emailed. |
| 6 | My Profile: name, email (changing it clears verification and sends a new link), phone, and password (the current password is required). Forgotten passwords use WordPress's lost-password page. |
| 7 | Dashboard pages: Overview and Reservations, full-screen with a dark side menu. "Settings" links to wp-admin Settings for administrators. |
| 8 | Overview shows today's check-ins, today's check-outs, pets boarding tonight, pending requests, and a 14-day bar chart of pets per night. |
| 9 | Reservations: search, status tabs, check-in date range, paging, and a side panel. The side panel shows every answer (the same partial as wp-admin), files, signature, status change (optional owner email), notes and delete. |
| 10 | Access uses the `pawfectly_manage_reservations` capability. A new role, `paw_staff` ("Pawfectly staff"), has `read` plus that capability. |
| 11 | Staff sign in at `wp-login.php`. The customer sign-in rejects staff accounts and points them there. |
| 12 | Menu items. Guest: Sign In, Sign Up. Signed in: Dashboard (staff), My Bookings, My Profile, Settings (admins), Log Out. |
| 13 | Settings gets a Shortcodes panel listing each shortcode with a Copy button, the pages using it, and "Create page" (a draft). |
| 14 | Look: Metronic-style. Side menu ink `#2C3947`, active item coral `#FF7F50`, avatar with coral initials on `#FFF1EB`. |
| 15 | Release v0.2.0. `PAW_DB_VERSION` goes to `'2'`. |
| 16 | Owner step, signed out: an "Already have an account? Sign in" link opens an inline email/password box. Signing in reloads the page; the draft is kept and the form returns to the owner step, filled in. |
| 17 | Owner step, signed out: a "Create an account with these details" checkbox reveals a password field (at least 8 characters). The account is created when the reservation is submitted. |
| 18 | Owner step, signed in: a "Signed in as {name} · Not you? Log out" line replaces the account prompts. |
| 19 | The form's background is transparent. The progress bar sits at the top of the form, sticky to the top of the form area only (not the screen). Back/Next sit inline under each step. |
| 20 | Component selectors are prefixed with `.paw-app`, so theme rules like `input[type=text]` cannot override them. |

## 3. Data

- **Table** `paw_reservations` gains `user_id BIGINT(20) UNSIGNED NULL` and `KEY user_id (user_id)`, via `dbDelta` on the DB version bump. Existing rows get `NULL`.
- **Role** `paw_customer` ("Pawfectly customer") with `read` only. **Role** `paw_staff` with `read` plus the capability. Both are added on activation and upgrade.
- **User meta:**
  - `paw_phone`
  - `paw_email_verified` (`'1'`)
  - `paw_verify_hash` (sha256 of the token)
  - `paw_verify_expires` (unix time, 7 days)
- **Visibility:** `Reservations::for_owner( int $user_id, string $email, bool $verified )` returns rows where `user_id = id`, or (when verified) `owner_email = email`, newest check-in first, at most 50.

## 4. Server components (`includes/`)

| Class | Responsibility |
|---|---|
| `Accounts` | Roles, `register(first,last,email,phone,password)`, `authenticate(email,password)` (rejects staff and admins with a pointer to wp-login), `start_session`, verification (`send_verification`, `verify(user,token)`), `is_verified`, `prefill(user_id)`. |
| `AccountRules` | Pure validation: signup, details, password (≥ 8 characters; confirmation must match). |
| `AccountRest` | `POST /account/login`, `POST /account/signup`, `POST /account/profile`, `POST /account/password`, `GET /account/verify`, `POST /account/reservations/{id}/cancel`. Rate limits: login 10/10 min, signup 5/30 min, profile 20/10 min. |
| `UserMenu` | `[pawfectly_user_menu]`; `UserMenu::items(array $ctx)` is pure and testable. |
| `Profile` | `[pawfectly_my_profile]`: signed out, Sign in / Create account tabs (`#signup` opens the second); signed in, details and password forms. |
| `MyBookings` | `[pawfectly_my_bookings]`: table plus a Cancel button where allowed. |
| `Dashboard` | `[pawfectly_dashboard view="overview\|reservations"]`: mounts the app. Visitors see a sign-in link; users without the capability see "No access". |
| `StaffRest` | Every route checks the capability. `GET /staff/stats`, `GET /staff/reservations`, `GET /staff/reservations/{id}` (JSON plus the answers HTML), `POST …/{id}/status`, `POST …/{id}/notes`, `DELETE …/{id}`. |
| `Stats` | Pure: `Stats::overview(array $rows, string $today): array` with `check_ins`, `check_outs`, `boarding`, `pending`, `nights` (14 × {date, count}). |
| `Pages` | `Pages::url(tag)`: the published page containing a shortcode (post content or Elementor data), cached for 5 minutes and cleared on page save. |
| `Shortcodes` (Settings panel) | Lists the 5 shortcodes; "Create page" makes a draft through an admin-post handler with nonce and `manage_options`. |

Changes to v0.1 classes:
- **`Rest::submit`:**
  - Signed in: sets `user_id`.
  - `create_account=1`: validates `account_password` with `AccountRules`; if the email is already registered, returns a 400 on `owner_email` ("An account already uses this email. Sign in above, or untick 'create an account'."). After a successful insert it registers the account, links `user_id`, starts the session and sends verification.
- **`Shortcode::config()`:**
  - adds `account` (`signedIn`, `name`, `prefill`, `loginUrl`, `logoutUrl`, `lostUrl`, `accountRest`)
  - prefill is built only for signed-in users
- **`Mailer`:** `owner_cancelled(reservation)` goes to staff; `verify(user, link)` goes to the owner.
- **`Admin`:** the answers markup moves to `templates/partials/answers.php`, shared with the dashboard.

## 5. Front end

- **Form (`form.js`, `form.css`, `templates/form.php`):**
  - Remove the cream background (`--paw-cream` is not applied to `.paw-app`) and the `min-height`.
  - Progress bar at the top inside the form (`.paw-progress` stays, sticky `top: 0` within `.paw-app`).
  - Inline nav `.paw-nav` after each step's card: ← Back (hidden on welcome), Next → (hidden on the last step). No fixed positioning.
  - Owner step account block (`.paw-account`): sign-in box, create-account checkbox plus password field, or the signed-in line.
- **Account menu:** avatar button plus dropdown. Keyboard: Enter/Space/↓ open, arrows move, Esc closes and returns focus, Tab leaves. Click outside closes. Right/left alignment attribute.
- **Profile and My Bookings:** server-rendered forms posting JSON to the REST routes with the REST nonce; inline errors; Metronic cards.
- **Dashboard (`dashboard.js`, `dashboard.css`):**
  - fills the viewport (`fullscreen="no"` keeps it in the page column)
  - dark side menu with the brand logo and name; Overview / Reservations / Settings
  - Overview: four stat tiles and a CSS bar chart
  - Reservations: table, side panel (`<aside role="dialog">`, Esc closes)
  - all data over `StaffRest`

## 6. Errors

| Situation | Behaviour |
|---|---|
| Wrong email or password | "The email or password is not right." |
| Staff tries the customer sign-in | "Staff sign in on the WordPress login page." with a link |
| Signup with an existing email | Field error on email |
| Verify link expired or wrong | Redirect to My Profile with an error notice and a resend button for signed-in users |
| Cancel not allowed (past check-in or wrong status) | 409 "This reservation can no longer be cancelled online — please call us." |
| Staff route without the capability | 403 |

## 7. Testing

- **Unit tests** (`php tests/*.php`):
  - `account-rules-test`
  - `accounts-test`: register, authenticate, staff rejection, verify token and expiry, prefill selection
  - `account-rest-test`: login, signup, profile, password, cancel rules, rate limits
  - `user-menu-test`: items per context
  - `stats-test`
  - `staff-rest-test`: permission and payload shapes
  - `pages-test`
  - additions to `rest-test` (create-account path, user link) and `activator-test` (`user_id` column)
- **E2E:**
  - `form-e2e.js` updated: progress bar at the top, nav not fixed, transparent background, create-account reveal, inline sign-in, prefill after reload
  - new `dashboard-e2e.js` against mocked staff REST: tiles, chart, list, side panel, status change
  - new `account-e2e.js`: menu keyboard and outside click; profile tabs

## 8. Out of scope

Social login, multiple pets per account (prefill uses the last reservation only), editing a reservation from My Bookings, the dashboard Settings screen (it links to wp-admin), and owner-facing status emails beyond v0.1's.
