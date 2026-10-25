# Pawfectly v0.3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the things v0.3 needs:
- one login page for all users
- wp-admin access only for the admin-email account and Developers, with an Admin Panel menu item for them
- password show/hide and confirm fields
- dashboard Settings, including pricing
- grooming services with an estimated total on the booking form

**Architecture:** Three new single-purpose classes:
- `Access`: who may use wp-admin.
- `Login`: the login page and redirects.
- `Services`: the catalog, prices and estimates.

Existing classes gain only what their role needs: `Fields`/`Validator` for the form rules, `Reservations`/`Stats` for storage, and `StaffRest`/`Dashboard` for the Settings view. Prices live only in `Settings`, and only `Services` reads them.

**Tech Stack:** PHP 8.0+, WordPress 6.0+, vanilla JS, plain PHP unit tests with WP stubs (`bash bin/test.sh`), Playwright e2e.

**Spec:** `docs/superpowers/specs/2026-10-25-login-services-design.md`

## Global Constraints

- **Naming:** namespace `Pawfectly\`, `paw_` options/nonces/meta, `paw-` CSS and handles, text domain `pawfectly-reservations`, REST namespace `pawfectly/v1`.
- **CSS scoping:** login page CSS under `.paw-acct` (it reuses `account.css`). Form additions under `.paw-app`, dashboard additions under `.paw-dash`. Inputs keep the `:not(#paw-0)` specificity guard.
- **Fields:** every field is defined once, in `includes/Fields.php`.
- **Versions:** `PAW_VERSION` `'0.3.0'`, plugin header `Version: 0.3.0`, `PAW_DB_VERSION` `'3'`.
- **Roles and access:**
  - Developer role slug `developer`, display name `Developer`.
  - Escape constant `PAW_WP_ADMIN_OPEN`.
  - Filter `paw_redirect_wp_login`.
  - Shortcode `pawfectly_login`.
- **Copy:**
  - Size labels: "Under 25 lbs" / "25 lbs & up".
  - Estimate label: "Estimate. Payment is taken at Pawfectly; staff confirm the final price."
  - Blank price: "Price on request".
- **Commits** end with the two attribution lines from the session. Run `bash bin/test.sh` green before every commit.

## Review Focus

1. **Admin-email account without the administrator role.** It must still reach wp-admin. `Access::is_admin_user` checks the email and nothing else (test in Task 1).
2. **Email case.** Comparing `Admin@Site.com` with `admin@site.com` must still match (test in Task 1).
3. **Hostile `redirect_to`.** An external URL must fall back to the role's home, never an open redirect (test in Task 2).
4. **Cat or other pet sent with `booking_type=grooming`.** The server must reject it even if the browser was bypassed (test in Task 6).
5. **Prices changed after booking.** The stored snapshot must not change (test in Task 7).

---

### Task 1: Access (Developer role, wp-admin gate, toolbar)

**Files:**
- Create: `includes/Access.php`
- Modify:
  - `includes/Activator.php` (`activate()` calls `Access::add_developer_role()`)
  - `includes/Plugin.php` (`Access::init()`)
  - `pawfectly-reservations.php` (`PAW_DB_VERSION` `'3'`)
- Test: `tests/access-test.php`. Extend `tests/user-stubs.php` with `get_role`/`add_role` and `$GLOBALS['pagenow']` if missing.

**Interfaces:**
- Produces:
  - `Access::open(): bool`: true when `PAW_WP_ADMIN_OPEN` is defined and truthy.
  - `Access::is_admin_user( \WP_User $u ): bool`: case-insensitive email equal to `get_option('admin_email')`, or `developer` in `$u->roles`. False for a user that doesn't exist.
  - `Access::can_manage_settings( \WP_User $u ): bool`: `user_can( $u, 'manage_options' ) || is_admin_user( $u )`.
  - `Access::gate_target( \WP_User $u, string $pagenow, bool $ajax, bool $cron ): ?string`: the redirect URL, or null to let the request through.
    - Null when `open()`, `$ajax`, `$cron`, `WP_CLI` is defined, `$pagenow` is one of `admin-post.php` or `async-upload.php`, or `is_admin_user`.
    - Otherwise `Login::home_for( $u )` from Task 2. Until Task 2 lands, use `home_url('/')`, and Task 2 replaces it.
  - `Access::gate(): void` (on `admin_init`): `wp_safe_redirect` + `exit` when `gate_target` is not null.
  - `Access::toolbar( bool $show ): bool` (filter `show_admin_bar`): `$show && ( open() || is_admin_user( current user ) )`.
  - `Access::add_developer_role(): void`: adds `developer` with the administrator's capabilities plus `Activator::CAPABILITY`, only when `get_role('developer')` is null.

- [ ] **Step 1: Write `tests/access-test.php`.**
  - Admin-email user with role `paw_staff` → `is_admin_user` true. `Owner@Example.com` vs option `owner@example.com` → true.
  - `developer` role → true. Plain administrator with a different email → false.
  - `gate_target`:
    - Administrator, other email, `index.php` → a URL.
    - Same user with `$ajax=true` → null. With `admin-post.php` → null.
    - Developer → null.
    - With `define('PAW_WP_ADMIN_OPEN', true)` in a separate run block at the end → null.
  - `toolbar(true)`: customer → false, developer → true.
  - `add_developer_role()`:
    - Creates the role once with `manage_options`.
    - A second call leaves a pre-existing `developer` role's capabilities untouched.
- [ ] **Step 2:** `php tests/access-test.php` → fails (class missing).
- [ ] **Step 3:** Implement `Access` as specified. Hook `admin_init` → `gate` and `show_admin_bar` → `toolbar` in `init()`.
- [ ] **Step 4:** `bash bin/test.sh` → all pass (update `activator-test.php` if it asserts the DB version).
- [ ] **Step 5:** Commit `feat: wp-admin only for the admin-email account and Developers`.

### Task 2: Login page, sign-in for every role, redirects

**Files:**
- Create: `includes/Login.php`, `templates/login.php`. Move the Sign in / Create account tabs out of `templates/profile.php` and drop the "Pawfectly staff" line.
- Modify:
  - `includes/Accounts.php`: `authenticate` no longer refuses staff and accepts an email or a username.
  - `includes/AccountRest.php::login`: uses `wp_signon`, takes `remember` and `redirect_to`, and returns `{ok, reload: true, redirect}`.
  - `includes/Profile.php` and `includes/MyBookings.php`: when signed out, render `Login::panel()` in place, or a link when the login page exists.
  - `includes/Dashboard.php`: the signed-out link becomes `Login::url_for( $here )`.
  - `includes/Pages.php`: add `pawfectly_login` to `TAGS` as `'Login'`, "Sign in and create account for everyone: owners, staff and admins.". Update the My Profile description.
  - `includes/Plugin.php`: `Login::init()`.
  - `assets/js/account.js`: after login, `location.assign(res.redirect)`.
- Test: `tests/login-test.php`; update `tests/accounts-test.php` and `tests/account-rest-test.php`. Add stubs `wp_signon`, `wp_validate_redirect`, `wp_safe_redirect` to `tests/user-stubs.php`.

**Interfaces:**
- Consumes: `Access::open()`, `Access::is_admin_user()`.
- Produces:
  - `Login::TAG = 'pawfectly_login'`.
  - `Login::url(): string`: `Pages::url( self::TAG )`, or `''`.
  - `Login::url_for( string $redirect_to ): string`: the login page with `redirect_to`. Falls back to `wp_login_url( $redirect_to )` when there's no page.
  - `Login::home_for( \WP_User $u ): string`, in order:
    1. Dashboard page, when the user has `Activator::CAPABILITY` and the page exists.
    2. Else the My Bookings page.
    3. Else `home_url('/')`.
  - `Login::target( string $redirect_to, \WP_User $u ): string`: `wp_validate_redirect( $redirect_to, '' )` if non-empty, else `home_for`.
  - `Login::wp_login_redirect( array $get, string $method ): ?string`:
    - Returns a URL only when all of these hold:
      - the method is GET
      - `$get['action'] ?? 'login'` is `login` or `register`
      - there's no `interim-login`
      - `! Access::open()`
      - `Login::url() !== ''`
      - `apply_filters( 'paw_redirect_wp_login', true )` is true
    - Carries `redirect_to`. `register` adds `tab=signup`.
  - `Login::login_url( string $url, string $redirect ): string` (filter `login_url`): the same conditions, minus the method and action.
  - `Login::panel( string $redirect_to = '' ): string`: the tabs markup from `templates/login.php`. `?tab=signup` opens Create account.
  - `Login::render( $atts ): string`: signed in → "You're signed in as {name}." with a "Continue" link to `home_for` and a Log Out link. Signed out → `panel()`.
  - `Accounts::authenticate( string $login, string $password, bool $remember = false )`: `\WP_User|\WP_Error`, via `wp_signon`. The error keeps code `paw_login` and the message "The email or password is not right.".

- [ ] **Step 1: Write `tests/login-test.php`.**
  - `target('https://evil.test/x', $customer)` → My Bookings URL.
  - `target('https://example.test/book/', …)` → that URL.
  - `home_for(staff)` → dashboard URL.
  - `wp_login_redirect`:
    - `(array(), 'GET')` → login page URL.
    - `(array('action'=>'lostpassword'),'GET')` → null.
    - `(array('action'=>'register'),'GET')` contains `tab=signup`.
    - `(array(), 'POST')` → null.
    - With the filter returning false → null.
  - `render()` signed out contains `data-paw-tab="signup"` and not `Pawfectly staff`.
- [ ] **Step 2: Update the account tests.** In `account-rest-test.php`, a staff login now succeeds and returns a `redirect`. In `accounts-test.php`, `authenticate` by username works.
- [ ] **Step 3:** Run both tests → fail.
- [ ] **Step 4: Implement** Login, the template move and the Accounts/AccountRest changes.
  - Hooks: `login_init` → redirect when `wp_login_redirect( $_GET, $_SERVER['REQUEST_METHOD'] )` is non-null; `add_filter( 'login_url', …, 10, 2 )`.
  - In `Access::gate_target`, replace the placeholder with `Login::home_for`.
- [ ] **Step 5:** `bash bin/test.sh` → all pass. `php tests/e2e/build-account.php && node tests/e2e/account-e2e.js` → update selectors for the moved tabs until green.
- [ ] **Step 6:** Commit `feat: [pawfectly_login] page for everyone; wp-login redirects to it`.

### Task 3: Show/hide passwords and confirm fields

**Files:**
- Create: `assets/js/password.js` (handle `paw-password`).
  - Every `input[type=password]` inside `.paw-app` or `.paw-acct` gets a wrapper `.paw-pw` and a button `.paw-pw__toggle`.
  - The button has `aria-pressed`, `aria-controls`, an eye icon, and the label "Show password" / "Hide password" from `window.PAW_PW`.
- Modify:
  - `templates/login.php`: signup `confirm` field "Confirm password", `autocomplete="new-password"`.
  - `templates/form.php`: a new `account_password_confirm` input under `account_password`, with its own error slot.
  - `includes/AccountRules.php::signup`: always compare `confirm`. Missing counts as `''`, so it mismatches.
  - `includes/Rest.php::account_request`: `AccountRules::password( $pw, $confirm )`, with the error on `account_password_confirm`.
  - `assets/js/form.js` and `assets/js/account.js`: client mismatch check, message "The two passwords don't match.".
  - `includes/Shortcode.php`, `Profile::enqueue`, `Login`: enqueue `paw-password` and localize `PAW_PW`.
  - CSS: `.paw-pw` styles in `form.css` (under `.paw-app`) and `account.css` (under `.paw-acct`).
- Test: update `tests/account-rules-test.php` and `tests/rest-test.php`; e2e in `form-e2e.js` and `account-e2e.js`.

- [ ] **Step 1: Unit tests.** `AccountRules::signup` without `confirm` → `errors['password']` is "The two passwords don't match.". A submit with `create_account=1` and a mismatched confirm → 400 with `fields.account_password_confirm`.
- [ ] **Step 2: E2E checks.**
  - Clicking the toggle flips `type` to `text` and `aria-pressed="true"`.
  - A mismatch on the form's create-account blocks Next and shows the message.
  - The login page signup shows a confirm box.
- [ ] **Step 3:** Run them → fail. Implement. Run `bash bin/test.sh` and all three e2e suites → green.
- [ ] **Step 4:** Commit `feat: show/hide on every password box; confirm on create account`.

### Task 4: Admin Panel menu item and menu targets

**Files:**
- Modify: `includes/UserMenu.php`, `tests/user-menu-test.php`.

**Interfaces:**
- Consumes: `Access::is_admin_user`, `Access::can_manage_settings`, `Login::url_for`.
- `items()` ctx gains `wp_admin: bool`. `admin` now means `Access::can_manage_settings`.
- URL changes:
  - `urls['settings']` → the dashboard page with `paw_view=settings`.
  - `urls['wp_admin']` → `admin_url()`.
  - `login` → `Login::url_for( here )`; `signup` → the same with `tab=signup`.
  - The dashboard no longer falls back to wp-admin. With no page, the item is hidden.
- Order after Settings: `wp_admin` "Admin Panel", then the separator, then Log Out.

- [ ] **Step 1: Tests.**
  - With `wp_admin=true`, the items contain key `wp_admin` labelled `Admin Panel`, placed directly after `settings` and before the `sep`.
  - With `wp_admin=false`, it's absent.
  - Signed out, the `login` URL contains the login page.
- [ ] **Step 2:** Fail → implement → `bash bin/test.sh` green.
- [ ] **Step 3:** Commit `feat: Admin Panel in the account menu for the admin-email account and Developers`.

### Task 5: Services catalog and pricing settings

**Files:**
- Create: `includes/Services.php`.
- Modify: `includes/Settings.php` (`defaults()` and `sanitize()` gain `currency`, `boarding_rates`, `services`).
- Test: `tests/services-test.php`, `tests/settings-test.php`.

**Interfaces:**
- Produces:
  - `Services::defaults(): array<string, array{enabled: bool, name: string, time: string, small: string, large: string, included: string}>`. Keys are in this order: `nail_trim`, `nail_membership`, `bath_membership`, `basic_bath`, `deluxe_bath`, `basic_haircut`, `premium_haircut`. Prices are strings (`''` = on request); `included` has one bullet per line.
  - `Services::MONTHLY = array( 'nail_membership', 'bath_membership' )`.
  - `Services::catalog(): array<string, array{id, name, time, monthly: bool, small: ?float, large: ?float, included: list<string>, enabled: bool}>`: defaults merged with `Settings::get()['services']`. Unknown ids are ignored.
  - `Services::enabled(): array`: the catalog filtered to `enabled`.
  - `Services::price( string $id, string $size ): ?float`: size `small` or `large`. Null for unknown, disabled or blank.
  - `Services::rate( string $pet_type, string $size ): ?float`: `dog` → `boarding_rates[$size]`, otherwise `boarding_rates['other']`.
  - `Services::money( float $amount ): string`: currency + amount, decimals only when they aren't whole. `15.0` → `$15`, `12.5` → `$12.50`.
  - `Services::clean_price( $raw ): ?string`: `''` stays `''`. A numeric value from 0 to 99999.99 → normalized, e.g. `'15'` → `'15.00'`. Anything else → null (invalid).
  - `Services::estimate( array $values ): array{lines: list<array{label: string, amount: ?float, monthly: bool}>, total: float, on_request: bool, currency: string}`:
    - Boarding line, for booking type `boarding` or `both`: label "Boarding: N nights". Amount = nights × `rate`, or null when the rate is blank.
    - Service line, for `grooming` or `both`: the service name, `price( service, dog_size )`, and `monthly`.
    - `total` sums the non-null amounts. `on_request` is true when any amount is null.
- Settings:
  - `currency`: `sanitize_text_field`, max 3 characters, default `$`.
  - `boarding_rates`: `{small, large, other}`, each from `clean_price`; invalid → previous value.
  - `services[id]`: `enabled` bool; `name` text, max 60, blank → default; `time` text, max 60; `small`/`large` from `clean_price`; `included` from `sanitize_textarea_field`.
  - `sanitize()` must be idempotent (the REST save in Task 10 may run it twice).

Default copy (fixed by the spec's screenshots):

| id | name | time | small | large | included (lines) |
|---|---|---|---|---|---|
| nail_trim | Nail Trimming | Usually under 15 minutes | 15.00 | 20.00 | Nail trimming to proper length / Nail filing for smooth edges / Paw pad inspection / Gentle handling for nervous dogs |
| nail_membership | Nail Membership | Come in as often as you like | 15.00 | 20.00 | Professional nail trimming / Nail filing for smooth edges / Come in as often as you like / Priority walk-in service / Calm, low-stress handling / No contract — cancel anytime |
| bath_membership | Bath Membership | Unlimited baths | 59.00 | 59.00 | Professional bathing for your dog / Unlimited visits / No sign-up fee / Cancel anytime |
| basic_bath | Basic Bath | | | | Thorough shampoo with premium Reliqpet all-natural products / Complete rinse to remove all residue / Towel dry (blow dry available as add-on) / Nail trimming & filing / Ear cleaning / Gland expression |
| deluxe_bath | Deluxe Bath | About 2 hours | | | Double-wash with premium Reliqpet all-natural shampoo / Deep conditioning treatment / Professional blow dry with brushing / Light brushing & de-shedding / Face trim, feet trim, sanitary trim / Nail trimming & filing / Ear cleaning / Gland expression / Finishing spray & cologne |
| basic_haircut | Basic Haircuts | 2–3 hours | | | Full bath with premium shampoo / Professional blow dry / Breed-standard or owner-requested haircut / Face, feet, and sanitary trimming / Nail trimming & filing / Ear cleaning / Gland expression |
| premium_haircut | Premium Haircuts | About 3 hours | | | Thorough pre-brush & de-matting / Luxurious bath with premium shampoo / Deep conditioning treatment / Professional blow dry with continuous brushing / Custom or breed-standard haircut / Face styling & scissor work / Paw pad trimming / Nail trim & filing / Ear cleaning / Gland expression / Sanitary trim / Finishing cologne & bandana |

- [ ] **Step 1: Tests.**
  - `price('nail_trim','large') === 20.0`. `price('basic_bath','small') === null`.
  - Disabling `nail_trim` in settings → `price` null, and it's absent from `enabled()`.
  - `money(15.0)==='$15'`, `money(12.5)==='$12.50'`.
  - `clean_price('abc')===null`, `clean_price('-1')===null`, `clean_price('15')==='15.00'`, `clean_price('')===''`.
  - Estimate for `{booking_type: both, pet_type: dog, dog_size: large, check_in: 2026-11-01, check_out: 2026-11-04, service: nail_trim}` with `boarding_rates.large='40'`: two lines, total `140.0`, `on_request` false.
  - Grooming with `basic_bath` → `on_request` true, total `0.0`.
  - Settings: `sanitize(sanitize(x)) === sanitize(x)` for a sample input with prices. An invalid price keeps the stored value.
- [ ] **Step 2:** Fail → implement → `bash bin/test.sh` green.
- [ ] **Step 3:** Commit `feat: services catalog and editable prices`.

### Task 6: Booking fields and rules

**Files:**
- Modify: `includes/Fields.php`, `includes/Validator.php`.
- Test: `tests/fields-test.php`, `tests/validator-test.php` (and `tests/fixtures.php` for a valid grooming sample).

**Interfaces:**
- Consumes: `Services::enabled()`.
- `Fields::f()` gains two keys:
  - `default` (string, `''`): preselects a choice.
  - `dog_only` (list of option keys, `array()`).
- `show_if` values may be a string or a list. Both `Validator::is_shown` and `form.js` (Task 8) accept a value that is in the list.
- New fields:
  - **Pet step, after `pet_type`:** `dog_size`, choice `{small: 'Under 25 lbs', large: '25 lbs & up'}`, `show_if {pet_type: dog}`.
  - **Stay step, first:** `booking_type`, choice `{boarding: 'Boarding', grooming: 'Grooming', both: 'Boarding + grooming'}`, `default 'boarding'`, `dog_only ['grooming','both']`, label "What would you like to book?".
  - `check_in`, `check_out`: add `show_if {booking_type: ['boarding','both']}`.
  - `appointment_date`: date, "Grooming day", `show_if {booking_type: ['grooming','both']}`.
  - `appointment_time`: choice `{morning: 'Morning (8–12)', afternoon: 'Afternoon (12–5)'}`, "Preferred drop-off time", `show_if {booking_type: grooming}`.
  - `service`: new type `service`, "Service", `show_if {booking_type: ['grooming','both']}`, `max 40`.
- `Fields::COLUMNS` gains `booking_type`, `appointment_date`, `service`.
- `Validator::check` case `service`: the value must be a key of `Services::enabled()`, else "Please choose one of the services.".
- `Validator::validate` rules:
  - `pet_type !== 'dog'` with `booking_type` other than `boarding` → error on `booking_type`: "Grooming is for dogs only.".
  - Grooming: `appointment_date` can't be in the past ("The grooming day can't be in the past.").
  - Grooming only: `check_in = check_out = appointment_date`.
  - Both: `check_in <= appointment_date <= check_out`, else "The grooming day must fall within the stay.".
  - The existing check-in/check-out rules run only when those fields are shown.
  - Columns for hidden fields are `''`.
  - `appointment_date` `''` becomes `null` in `build_row` (Task 7).

- [ ] **Step 1: Tests.**
  - The fields test sees the five new ids in their steps, with `booking_type` default `boarding`.
  - Validator, each expecting the stated outcome:
    - A grooming sample (dog, small, `basic_bath`, tomorrow, morning, no check-in/out) → valid, `columns.check_in === columns.check_out === tomorrow`.
    - Cat + grooming → `fields.booking_type`.
    - Both with the appointment after check-out → `fields.appointment_date`.
    - Disabled service → `fields.service`.
    - A boarding sample without `booking_type` → `fields.booking_type` required.
    - A dog without `dog_size` → `fields.dog_size`.
- [ ] **Step 2:** Fail → implement. Run `bash bin/test.sh`; existing fixtures may need `booking_type=boarding` and `dog_size` added → green.
- [ ] **Step 3:** Commit `feat: booking type, dog size, grooming day and service fields`.

### Task 7: Storage, snapshot and overview counts

**Files:**
- Modify:
  - `includes/Activator.php::schema_sql`: add after `check_out`:
    - `booking_type VARCHAR(12) NOT NULL DEFAULT 'boarding',`
    - `appointment_date DATE NULL,`
    - `service VARCHAR(40) NOT NULL DEFAULT '',`
  - `includes/Reservations.php`:
    - `build_row` turns `appointment_date` `''` into `null`.
    - `for_stats` also selects `booking_type, appointment_date`.
    - `values()` reads the new columns.
  - `includes/Rest.php::submit`: `$clean['details']['pricing'] = Services::estimate( values of clean )` next to `waiver_text`.
  - `includes/Stats.php::overview`:
    - Rows with `booking_type === 'grooming'` are skipped for check-ins, check-outs and nights.
    - New key `grooming`: rows with `booking_type` `grooming` or `both`, `appointment_date === $today`, and status in STAYING or pending.
- Test: `tests/activator-test.php`, `tests/reservations-test.php`, `tests/stats-test.php`, `tests/rest-test.php`.

- [ ] **Step 1: Tests.**
  - The schema contains the three columns.
  - `build_row` with an empty appointment → `appointment_date === null`.
  - Stats: a grooming row for today doesn't count in `check_ins` or `nights`, but counts in `grooming`. A `both` row counts in both.
  - REST: submitting the grooming sample stores `details.pricing.lines[0].label === 'Basic Bath'`. Changing the setting price afterwards and re-reading the stored row leaves the snapshot unchanged.
- [ ] **Step 2:** Fail → implement → `bash bin/test.sh` green.
- [ ] **Step 3:** Commit `feat: store booking type, service and price snapshot; grooming in overview`.

### Task 8: Booking form UI (service cards, estimate, booking type)

**Files:**
- Modify:
  - `templates/form.php`:
    - Render type `service` as a radiogroup of cards `.paw-svc`. Each card has a radio, the name, `time`, a price slot `[data-paw-price]`, a "Monthly" badge when monthly, and a `<details>` "What's included" list.
    - An estimate box `[data-paw-estimate]` after the service field. It shows in the done step too.
    - `dog_only` options carry `data-paw-dog-only`.
    - Choices with `default` render `checked`.
  - `includes/Shortcode.php::config()`:
    - `services`: id → `{small, large, monthly}` numbers or null.
    - `rates`: `{small, large, other}`.
    - `currency`.
    - i18n: `onRequest` "Price on request", `perMonth` "/ month", `estimate` (the Global Constraints copy), `boarding` "Boarding: %d nights", `total` "Estimated total".
  - `assets/js/form.js`:
    - `showIf` accepts arrays.
    - When the pet isn't a dog, hide dog-only options and reset to `boarding`.
    - Price slots update on `dog_size` change.
    - The estimate is recomputed on any relevant change, mirroring `Services::estimate`. Display only.
  - `assets/css/form.css`: `.paw-app .paw-svc*` cards in the Metronic style. Selected card: coral border `#FF7F50` and tint `#FFF1EB`. Monthly badge: `#30AFFF` text on a light tint.
- Test: `tests/form-render-test.php`, `tests/e2e/form-e2e.js`.

- [ ] **Step 1: Render tests.**
  - 7 `.paw-svc` cards in the catalog order (`nail_trim` before `premium_haircut`).
  - Monthly badge on 2 cards.
  - `booking_type` boarding checked.
  - Config `services.nail_trim.large === 20.0`, `services.basic_bath.small === null`.
- [ ] **Step 2: E2E checks.**
  - Pet = Cat → the Grooming option is hidden.
  - Dog + large + Grooming → Nail Trimming shows `$20`; Basic Bath shows "Price on request".
  - Choosing Boarding + grooming with rates in config → the estimate total updates.
  - Check-in fields are hidden for Grooming.
  - A full grooming submission reaches the done step.
- [ ] **Step 3:** Fail → implement. Run `bash bin/test.sh` and the form e2e (both builds) → green. Save phone and desktop screenshots with `PAW_SHOTS`.
- [ ] **Step 4:** Commit `feat: grooming services and live estimate on the booking form`.

### Task 9: Showing services to staff and owners

**Files:**
- Modify:
  - `includes/Admin.php::answer_text`: a `service` value shows the service name, from the snapshot or else the catalog. The answers partial shows the pricing lines and total under Reservation Details.
  - `includes/Admin.php` CSV: add `Estimated total` after the existing columns, from `details.pricing.total`, prefixed with `*` when `on_request`.
  - `includes/Mailer.php::reservation_received`:
    - Grooming → an "Appointment" row instead of check-in/out.
    - Service and estimate rows. The owner email shows the estimate copy.
  - `templates/my-bookings.php`: the service name under the dates; grooming rows show the appointment date.
  - `includes/StaffRest.php::list`: return `booking_type` and `service_name`.
  - `assets/js/dashboard.js`: "Grooming today" stat card, and the service shown in the list rows.
- Test: `tests/admin-test.php`, `tests/mailer-test.php`, `tests/my-bookings-test.php`, `tests/staff-rest-test.php`, `tests/e2e/dashboard-e2e.js`.

- [ ] **Step 1: Tests.**
  - Email for a grooming reservation contains "Appointment" and "Basic Bath", not "Check-out".
  - The CSV header ends with "Estimated total".
  - My Bookings shows the service name.
  - The staff list item carries `service_name`.
  - The dashboard e2e sees the "Grooming today" card.
- [ ] **Step 2:** Fail → implement → unit and dashboard e2e green.
- [ ] **Step 3:** Commit `feat: services in emails, CSV, My Bookings and the dashboard`.

### Task 10: Dashboard Settings view

**Files:**
- Modify:
  - `includes/StaffRest.php`: routes `GET /staff/settings` and `POST /staff/settings` with permission `Access::can_manage_settings( wp_get_current_user() )`.
    - GET returns `Settings::get()` plus `roles` (the role list), `logo_url`, and `lockout_risk`. `lockout_risk` is true when no user has the admin email and no user has the `developer` role.
    - POST runs `Settings::sanitize( array_merge( Settings::get(), json ) )`, then `update_option`, and returns the saved settings.
  - `includes/Dashboard.php`:
    - `VIEWS` gains `settings`.
    - `render` refuses `settings` to users without `can_manage_settings` (falls back to overview).
    - `config` `urls.settings` points to the dashboard view.
    - Calls `wp_enqueue_media()` for the logo when the user can `upload_files`.
  - `templates/dashboard.php`: a Settings side-menu item.
  - `assets/js/dashboard.js`: Settings view with two cards.
    - **General:** business name, logo (wp.media when present), staff emails, intro, waiver, default country, staff roles.
    - **Pricing:** currency, three boarding rates, then one row per service with enabled, name, time, small, large and included.
    - Save button, field errors from the response, and a success toast "Settings saved.".
    - `lockout_risk` shows a warning naming `PAW_WP_ADMIN_OPEN`.
  - `assets/css/dashboard.css`: `.paw-dash` form styles.
- Test: `tests/staff-rest-test.php`, `tests/dashboard-render-test.php`, `tests/e2e/dashboard-e2e.js` (+ `build-dashboard.php` mocks).

- [ ] **Step 1: Tests.**
  - `paw_staff` → GET settings is forbidden. Developer → allowed.
  - POST with `services.basic_bath.small='45'` stores `'45.00'`. POST with `'abc'` keeps the previous value.
  - The render test: `paw_view=settings` as staff falls back to overview.
  - E2E: an admin opens Settings, edits the Basic Bath small price, saves, and sees "Settings saved."; the mocked POST body carries the value.
- [ ] **Step 2:** Fail → implement → unit and dashboard e2e green.
- [ ] **Step 3:** Commit `feat: Settings and pricing in the dashboard`.

### Task 11: Docs, version, release prep

**Files:**
- Modify:
  - `pawfectly-reservations.php`: `Version: 0.3.0`, `PAW_VERSION '0.3.0'`.
  - `README.md`: login page, wp-admin access and the escape constant, services and pricing, the changelog.
  - `CLAUDE.md`: the spec and plan paths, the new shortcode.
  - `tests/e2e/README.md` if commands changed.

- [ ] **Step 1:** Run `bash bin/test.sh` and all e2e commands from `CLAUDE.md` → all green. Record the counts.
- [ ] **Step 2:** `bin/build-release.sh` → the zip builds and contains `includes/Access.php`, `includes/Login.php`, `includes/Services.php`, `templates/login.php`, `assets/js/password.js`.
- [ ] **Step 3:** Commit `chore: v0.3.0` and push to `claude/busy-volta-b9uwnb`.
- [ ] **Step 4:** Whole-branch review by a fresh reviewer, then fixes. Then a PR into `main`, merge, and the Release workflow with tag `v0.3.0`.
