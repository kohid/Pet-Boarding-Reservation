# Pawfectly v0.2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (the user chose native execution for this project). Steps use checkbox (`- [ ]`) syntax.

**Goal:** Optional owner accounts (inline on the form), the account menu, My Profile, My Bookings, a front-end staff dashboard, and the requested form layout changes, released as v0.2.0.

**Architecture:** Same shape as v0.1, ported from Sprint-Booking's account and dashboard pieces:
- New final classes in `includes/`, with pure helpers (`AccountRules`, `Stats`, `UserMenu::items`) kept apart from WordPress glue so the plain-PHP tests can cover them.
- Staff data travels only over capability-checked REST routes.

**Tech Stack:** PHP 8.0+, WordPress 6.0+ (users, roles, REST, auth cookies), vanilla JS, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-25-accounts-dashboard-design.md` (v0.1 spec still applies).

## Global Constraints

- Version `0.2.0` in the header and `PAW_VERSION`; `PAW_DB_VERSION` `'2'`.
- Prefixes `paw_` and `paw-`; text domain `pawfectly-reservations`; REST namespace `pawfectly/v1`.
- Roles: `paw_customer` (read) and `paw_staff` (read plus `pawfectly_manage_reservations`).
- User meta: `paw_phone`, `paw_email_verified`, `paw_verify_hash`, `paw_verify_expires`.
- Shortcodes: `pawfectly_user_menu`, `pawfectly_my_profile`, `pawfectly_my_bookings`, `pawfectly_dashboard`.
- Passwords must be at least 8 characters. Verification links last 7 days and store only the hash of the token.
- Every staff REST route checks the capability. Every account write route requires a signed-in user except login and signup.
- Front-end component selectors start with `.paw-app` (form) or their own root (`.paw-um`, `.paw-acct`, `.paw-dash`).

## Review Focus

1. **The REST nonce after sign-in.** A nonce made for a visitor is invalid once they are signed in. Inline sign-in therefore reloads the page, and form submission after signing up in the same request must not depend on the old nonce. Pinned in Task 5 (e2e reload path) and Task 4 (login returns `reload: true`).
2. **Someone registering with another person's email.** They must not see that person's reservations: email matching applies only once the email is verified. Pinned in Task 2 (`for_owner`) and Task 7.
3. **Staff signing in on the customer page.** Staff and admins are refused there with a pointer to wp-login, so security plugins keep guarding staff logins. Pinned in Task 2.
4. **Theme CSS overriding form inputs** (the screenshot showed boxed inputs). Component selectors are `.paw-app`-prefixed, and the e2e checks the computed input border against an injected theme rule `input[type=text]{border:1px solid #000}`. Pinned in Task 1.
5. **Cancelling someone else's reservation, or one that already started.** It must be refused. Pinned in Task 7.

---

### Task 1: Form layout: transparent background, progress at the top, inline nav, theme-proof selectors

**Files:**
- Modify: `templates/form.php`, `assets/css/form.css`, `assets/js/form.js`
- Test: `tests/form-render-test.php`, `tests/e2e/form-e2e.js`, `tests/e2e/build.php` (inject the theme rule)

**Interfaces:**
- Produces:
  - The progress bar renders first inside `.paw-app`, as `.paw-progress[role=progressbar]`.
  - One `.paw-nav` follows the steps, containing `[data-paw-prev]` and `[data-paw-next]`.
  - `.paw-app` has `background: transparent` and no `min-height`.

- [ ] **Step 1:** Write the tests.
  - Render test: the progress bar comes before `data-step="0"`, and `.paw-nav` comes after the last `data-step`.
  - E2E:
    - `getComputedStyle(.paw-app).backgroundColor` is `rgba(0, 0, 0, 0)`
    - `.paw-nav` position is not `fixed`
    - the progress bar's top is at or above the step title's top
    - with the injected theme rule, `.paw-input` has `border-top-width: 0px`
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement.
  - Back and Next become labelled pill buttons ("Back" / "Next" plus arrows) in coral.
  - The progress track is a 6 px bar with the walking paw kept.
  - Prefix every component selector with `.paw-app`.
- [ ] **Step 4:** Run `bash bin/test.sh` and the e2e. Expected: PASS. Take screenshots and check them.
- [ ] **Step 5:** Commit `feat: transparent form, progress bar on top, inline back/next`.

### Task 2: Schema v2, roles, Accounts, AccountRules, owner visibility

**Files:**
- Create: `includes/Accounts.php`, `includes/AccountRules.php`
- Modify: `includes/Activator.php`, `includes/Reservations.php`, `pawfectly-reservations.php` (`PAW_DB_VERSION` '2')
- Test: `tests/accounts-test.php`, `tests/account-rules-test.php`, `tests/activator-test.php`, `tests/reservations-test.php`

**Interfaces:**
- Produces:
  - `Accounts::ROLE = 'paw_customer'`, `Accounts::STAFF_ROLE = 'paw_staff'`
  - `Accounts::add_roles(): void`
  - `Accounts::register( string $first, string $last, string $email, string $phone, string $password ): int|\WP_Error`
  - `Accounts::authenticate( string $email, string $password ): \WP_User|\WP_Error`. Error codes are `paw_login` (wrong details) and `paw_staff_login` (staff or admin).
  - `Accounts::start_session( int $id ): void`
  - `Accounts::new_token( int $id ): string`, which stores the hash and expiry and returns the raw token
  - `Accounts::verify( int $id, string $token ): bool`
  - `Accounts::is_verified( int $id ): bool`
  - `Accounts::verify_url( int $id, string $token ): string`
  - `Accounts::prefill( int $id ): array<string,string>`
  - `AccountRules::signup( array $in ): array{clean, errors}` (keys `first_name`, `last_name`, `email`, `phone`, `password`)
  - `AccountRules::details( array $in )` (same keys minus password)
  - `AccountRules::password( string $pw, ?string $confirm ): ?string`, which returns an error message or null
  - `Reservations::for_owner( int $user_id, string $email, bool $verified ): array`
  - `Reservations::last_for_owner( ... ): ?array`
  - `Activator::schema_sql` includes `user_id BIGINT(20) UNSIGNED NULL` and `KEY user_id (user_id)`.
- Prefill keys are every field id in steps `owner`, `pet`, `care` and `emergency`.

- [ ] **Step 1:** Write the tests, using stand-ins for the WordPress user functions (`wp_insert_user`, `get_user_by`, `wp_check_password`, `update_user_meta`, `get_user_meta`, roles).
  - **Rules:** short password; mismatched confirmation; bad email; first name required.
  - **Accounts:**
    - register sets the role, the phone meta, and unverified
    - register with an existing email returns `paw_email_exists`
    - authenticate with the wrong password returns `paw_login`
    - authenticate for an administrator or `paw_staff` user returns `paw_staff_login`
    - token: verify true once, false after reuse, false after expiry, false for a wrong token
    - prefill comes from the last reservation and excludes `check_in`, `vaccinations_current`, `signature`, `waiver_agree`; it falls back to the account's name, email and phone
  - **`for_owner`:**
    - the SQL contains `user_id = 5`
    - it contains the `owner_email` clause only when verified
  - **Activator:** the new column and key are in the schema.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement.
  - `Activator::activate` calls `Accounts::add_roles()`.
  - Passwords go through `wp_insert_user` (core hashing). Tokens are 32 random bytes in hex; the hash is sha256.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit `feat: owner accounts, roles and verification tokens`.

### Task 3: Page lookup and the Settings Shortcodes panel

**Files:**
- Create: `includes/Pages.php`
- Modify: `includes/Settings.php`
- Test: `tests/pages-test.php`

**Interfaces:**
- Produces:
  - `Pages::url( string $tag ): string`, which is '' when there is no page
  - `Pages::forget(): void`
  - `Pages::TAGS`, listing the 5 shortcode tags with label and description
  - `Settings` page section "Shortcodes"
  - `admin_post_paw_create_page` (nonce `paw_create_page`, `manage_options`), which creates a draft page titled with the label and containing the shortcode, then redirects to its editor

- [ ] **Step 1:** Write the tests, with a fake `$wpdb->get_col` and `get_post`/`get_permalink` stand-ins:
  - it finds `[pawfectly_my_profile]` but not `[pawfectly_my_profile_x]`
  - the result is cached (no second query)
  - it returns '' when nothing matches
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3:** Implement, porting Sprint-Booking's `Pages::url`.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit `feat: shortcode page lookup and Shortcodes settings panel`.

### Task 4: Account REST routes and the verification email

**Files:**
- Create: `includes/AccountRest.php`
- Modify: `includes/Mailer.php`, `includes/Plugin.php`
- Test: `tests/account-rest-test.php`, `tests/mailer-test.php`

**Interfaces:**
- Produces these routes:
  - `POST /account/login {email,password}` returns `{ok, reload:true}`
  - `POST /account/signup {first_name,last_name,email,phone,password,website}` returns `{ok, reload:true}` and sends verification
  - `POST /account/profile {first_name,last_name,email,phone}` returns `{ok,message,name,email}`; an email change clears verification and sends a link
  - `POST /account/password {current,password,confirm}`
  - `GET /account/verify?u=&t=`, which redirects to `Pages::url(my_bookings)` (or the profile page, or home) with `?paw_verified=1|0`
  - `POST /account/resend`
- It also produces `Mailer::verify_email( \WP_User $u, string $url ): void`.
- Errors are `WP_Error` with `fields`.

- [ ] **Step 1:** Write the tests.
  - Login: success; wrong password (400 `paw_login`); staff refused (`paw_staff_login`, message contains `wp-login.php`); 11th try in 10 minutes gives 429.
  - Signup: honeypot; duplicate email (409 field error); success sends one verification email whose link contains `account/verify`.
  - Profile: requires sign-in (401); an email change unverifies and sends mail.
  - Password: wrong current password; success.
  - Verify: good, bad and expired tokens produce the right redirect query.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit `feat: account sign-in, sign-up, profile and verification endpoints`.

### Task 5: Accounts on the reservation form

**Files:**
- Modify: `includes/Rest.php`, `includes/Shortcode.php`, `templates/form.php`, `assets/js/form.js`, `assets/css/form.css`
- Test: `tests/rest-test.php`, `tests/form-render-test.php`, `tests/e2e/form-e2e.js`, `tests/e2e/build.php` (variants: `signed-in`)

**Interfaces:**
- Consumes the `Accounts` and `AccountRules` functions from Task 2.
- Produces:
  - `config.account = {signedIn, name, prefill, loginRest, lostUrl, logoutUrl}`
  - the owner-step block `[data-paw-account]` with:
    - `[data-paw-signin-toggle]`
    - a sign-in box (`#paw-login-email`, `#paw-login-password`, `[data-paw-signin]`)
    - `input[name=create_account]`
    - `[data-field=account_password]` (`input[name=account_password]`)
- Field errors use keys `account_password` and `owner_email`.

- [ ] **Step 1:** Write the tests.
  - **Rest:**
    - signed in (stub `get_current_user_id` = 7) sets `user_id` 7
    - `create_account=1` with a short password gives 400 `account_password`
    - an existing email gives 400 `owner_email`
    - success creates the user, sets `user_id`, and sends verification and reservation emails
  - **Render:** signed out shows the sign-in toggle and checkbox; signed in shows "Signed in as" and no checkbox.
  - **E2E:**
    - ticking the checkbox reveals the password field
    - Next with a 5-character password shows the error
    - inline sign-in posts to the mocked `/account/login`, then reloads; the build serves a signed-in variant with prefill; owner fields are filled and the form is on step 1
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement.
  - Before reloading, the JS stores `sessionStorage.paw_resume = '1'`, then jumps to step 1 after the reload.
  - Prefill fills only fields that are empty after the draft is restored.
- [ ] **Step 4:** Run all tests. Expected: PASS.
- [ ] **Step 5:** Commit `feat: create an account or sign in from the owner step`.

### Task 6: Account menu

**Files:**
- Create: `includes/UserMenu.php`, `assets/css/account.css`, `assets/js/user-menu.js`
- Test: `tests/user-menu-test.php`, `tests/e2e/account-e2e.js`, `tests/e2e/build-account.php`

**Interfaces:**
- `UserMenu::items( array $ctx ): array` where `$ctx = {logged_in, staff, admin, urls{login,signup,profile,bookings,dashboard,settings,logout}}`.
- It returns a list of `{key,label,url}` or `{sep:true}`.
- `UserMenu::initials( string $name ): string`.

- [ ] **Step 1:** Write the tests.
  - **Unit:** guest gets login and signup; customer gets bookings, profile, sep, logout; staff adds dashboard first; admin adds settings; empty URLs are dropped; initials: "Jay Miralles" → "JM", "zoë" → "Z", "" → "?".
  - **E2E:** click opens; Esc closes and focuses the button; ArrowDown moves focus; outside click closes.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement, matching the screenshots: a 40 px round avatar with grey guest icon or coral initials, and a white panel with a header and list.
- [ ] **Step 4:** Run the tests. Expected: PASS.
- [ ] **Step 5:** Commit `feat: account menu shortcode`.

### Task 7: My Profile and My Bookings (with cancel)

**Files:**
- Create: `includes/Profile.php`, `includes/MyBookings.php`, `templates/profile.php`, `templates/my-bookings.php`, `assets/js/account.js`
- Modify: `includes/AccountRest.php` (cancel), `includes/Mailer.php` (`owner_cancelled`)
- Test: `tests/my-bookings-test.php`, `tests/account-rest-test.php`, `tests/e2e/account-e2e.js`

**Interfaces:**
- `POST /account/reservations/(?P<id>\d+)/cancel` returns `{ok,status:'cancelled'}`. A 404 means it isn't yours; a 409 means it isn't cancellable.
- `MyBookings::can_cancel( array $r, string $today ): bool`

- [ ] **Step 1:** Write the tests.
  - `can_cancel`: pending or confirmed with a future check-in is true; check-in today is false; `checked_in` is false.
  - Cancel: another user's reservation gives 404; email match while unverified gives 404; success appends to `status_log` with the owner's user id and emails staff.
  - Render: signed out shows tabs; `#signup` is supported; signed in shows the details form prefilled.
  - E2E: tabs switch; the signup form posts and reloads.
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the tests. Expected: PASS.
- [ ] **Step 5:** Commit `feat: my profile and my bookings with owner cancel`.

### Task 8: Staff REST, Stats and the shared answers partial

**Files:**
- Create: `includes/StaffRest.php`, `includes/Stats.php`, `templates/partials/answers.php`
- Modify: `templates/admin-detail.php`, `includes/Admin.php`
- Test: `tests/stats-test.php`, `tests/staff-rest-test.php`, `tests/admin-test.php` (must stay green)

**Interfaces:**
- `Stats::overview( array $rows, string $today ): array{check_ins:int, check_outs:int, boarding:int, pending:int, nights: list<array{date:string,count:int}>}`. Boarding and nights count `confirmed` and `checked_in` stays covering the night.
- `StaffRest::can(): bool`, used for every route.
- `GET /staff/reservations` takes the same filters as the list and returns `{rows:[{id,reference,pet_name,pet_type,owner,email,check_in,check_out,nights,status,status_label,created_at}], total, counts}`.
- `GET /staff/reservations/{id}` returns `{reservation…, answers_html, files:[{name,url,size}], signature_url, history:[…]}`.

- [ ] **Step 1:** Write the tests.
  - Stats fixtures cover a stay across today, a check-in today, a check-out today, cancelled (ignored), pending (counted in pending only), and the 14 nights.
  - Staff REST:
    - without the capability every route gives 403
    - the list shape
    - the detail includes `answers_html` with escaped notes
    - status change and notes
    - delete removes files
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement, extracting the partial from `admin-detail.php` without changing its output.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit `feat: staff REST endpoints and overview stats`.

### Task 9: Front-end staff dashboard

**Files:**
- Create: `includes/Dashboard.php`, `templates/dashboard.php`, `assets/js/dashboard.js`, `assets/css/dashboard.css`
- Test: `tests/dashboard-render-test.php`, `tests/e2e/dashboard-e2e.js`, `tests/e2e/build-dashboard.php`

**Interfaces:**
- `[pawfectly_dashboard view="overview|reservations" fullscreen="yes|no"]`
- `window.PAW_DASH = {rest, nonce, view, urls{overview,reservations,settings}, brand{name,logo}, statuses}`

- [ ] **Step 1:** Write the tests.
  - Render: a visitor sees a sign-in link; a user without the capability sees "No access"; staff see `[data-paw-dash]`.
  - E2E (mocked REST):
    - the Overview shows 4 tiles with mocked numbers and 14 bars
    - the Reservations list shows rows; a search field change re-queries
    - a row click opens the side panel with the answers; changing status calls the status route and updates the badge
    - Esc closes the panel
    - at 375 px there is no horizontal scroll, and the side menu collapses to a top bar
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run the tests. Expected: PASS. Take screenshots and check them.
- [ ] **Step 5:** Commit `feat: front-end staff dashboard`.

### Task 10: Version, docs, review, release

**Files:**
- Modify: `pawfectly-reservations.php` (0.2.0), `README.md`, `CLAUDE.md`

- [ ] **Step 1:** Bump the version and update the README (new shortcodes, accounts, dashboard, staff role) and `CLAUDE.md`.
- [ ] **Step 2:** Run everything: `bash bin/test.sh`, all e2e scripts, `bin/build-release.sh`. Expected: all PASS.
- [ ] **Step 3:** Run a final whole-branch review with a fresh reviewer, then one fix pass with TDD.
- [ ] **Step 4:** Push, open a pull request into `main`, merge it, and run the Release workflow with `v0.2.0`. Verify the release asset.
