# Pawfectly Pet Boarding Reservation v0.1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A WordPress plugin that serves Pawfectly's 8-step boarding reservation form. Submissions are validated on the server and stored with private files, and staff manage them in wp-admin.

**Architecture:** Mirrors kohid/Sprint-Booking: a main plugin file with an autoloader for `Pawfectly\` → `includes/`, and final classes with a static `init()`. One field list (`Fields`) drives the template, the client rules, and the server `Validator`. Reservations live in one table with indexed columns plus a JSON `details` column. Files and signatures go to a private uploads folder that only staff can read through `admin-post.php`.

**Tech Stack:** PHP 8.0+, WordPress 6.0+ (REST API, Options API, `WP_List_Table`, `wp_mail`), vanilla JS, vendored flatpickr 4.6.13 (MIT), Playwright (preinstalled) for e2e, plain `php tests/*.php` unit tests.

**Spec:** `docs/superpowers/specs/2026-10-25-pet-boarding-reservation-design.md`. Field inventory: `docs/requirements/2026-10-25-reservation-form.md`.

## Global Constraints

- Plugin header: Name "Pawfectly Pet Boarding Reservation", Version `0.1.0`, Requires at least `6.0`, Requires PHP `8.0`, License GPL v2 or later, Text Domain `pawfectly-reservations`, Update URI `https://github.com/kohid/Pet-Boarding-Reservation`.
- Main file `pawfectly-reservations.php` at the repo root. Constants `PAW_VERSION`, `PAW_DB_VERSION` (`'1'`), `PAW_FILE`, `PAW_DIR`, `PAW_URL`.
- Namespace `Pawfectly\`. Every file starts with `defined( 'ABSPATH' ) || exit;`.
- Prefixes: `paw_` for options, transients, the table, hooks, and nonces. `paw-` for CSS classes and handles. The CSS roots are `.paw-app` (front end) and `.paw-admin`.
- Capability `pawfectly_manage_reservations`. Settings requires `manage_options`.
- REST namespace `pawfectly/v1`.
- Brand tokens are exactly as in spec §6.1. No Metronic files are copied into the repo.
- Statuses are `pending`, `confirmed`, `declined`, `checked_in`, `completed`, `cancelled`.
- Dates are stored as ISO `Y-m-d` and shown as `m/d/Y`. Timestamps are stored in UTC.
- Files: 1–3, at most 10 MB each, `application/pdf` / `image/jpeg` / `image/png` only. Signature PNG at most 500 KB. Rate limit 5 per 10 min per IP. Longest stay 90 nights.
- Output is escaped at the point of echo. Input goes through `wp_unslash` and then a sanitizer.
- Lengths are counted with `mb_strlen`.
- Tests run with `bash bin/test.sh`, which runs every `tests/*-test.php` and exits non-zero on any `FAIL`.

## Review Focus

1. **Double-clicking Submit or a flaky network retry.** The visitor should get one reservation, not two. The client sends a `submission_id` (UUID v4). The server caches `submission_id → reference` for 10 minutes and returns the same reference on a repeat. Pinned in Task 6.
2. **Uploads larger than PHP's `post_max_size`.** PHP then empties `$_POST` and `$_FILES`. The visitor should see "Your files are too large to send…", not 40 "required" errors. Pinned in Task 6.
3. **"Today" near midnight or across timezones.** A visitor in another timezone picking today must not be rejected by the server. The client's `minDate` comes from the server-provided `today` (site timezone), not the browser clock. Pinned in Tasks 4 and 8.
4. **iPhone HEIC photos and renamed files.** A `.pdf` that is really PHP or HEIC must be rejected with a message naming the allowed types (PDF, JPG, PNG), and must never be stored. Pinned in Task 5.
5. **Names with accents and apostrophes** (e.g. `Zoë O'Brien`, 60-character names). They must be stored and shown exactly as typed: no `\'` slashes, and no rejection for character counts under the limit when the byte count is over. Pinned in Task 4.

---

## File map

| File | Responsibility | Task |
|---|---|---|
| `pawfectly-reservations.php` | header, constants, autoloader, activation/boot hooks | 1 |
| `includes/Plugin.php` | calls each class's `init()` | 1 (grows) |
| `includes/Activator.php` | table schema, upgrade, capability, private dir | 1 |
| `includes/Fields.php` | steps/fields/countries | 2 |
| `includes/Reference.php` | `PAW-XXXXXX` | 3 |
| `includes/Validator.php` | field rules → clean data or `WP_Error` | 4 |
| `includes/Uploads.php` | file checks, private storage, staff download | 5 |
| `includes/Signature.php` | data-URL decode/check/store | 5 |
| `includes/Reservations.php` | DB rows: build, insert, find, query, status, notes | 6 |
| `includes/RateLimit.php` | per-IP limiter (port of Sprint-Booking's) | 6 |
| `includes/Rest.php` | `POST /reservations`, `GET /nonce` | 6 |
| `includes/Settings.php` | `paw_settings` option + Settings screen | 7 |
| `includes/Shortcode.php` | shortcode, asset enqueue, JS config | 7 |
| `templates/form.php`, `assets/css/form.css`, `assets/img/paw.svg` | form markup + look | 7 |
| `assets/js/form.js`, `assets/js/signature-pad.js`, `assets/vendor/flatpickr/` | behavior | 8 |
| `includes/Mailer.php`, `includes/EmailTemplate.php`, `templates/email.php` | emails | 9 |
| `includes/Admin.php`, `includes/ListTable.php`, `templates/admin-detail.php`, `assets/css/admin.css`, `assets/js/admin.js` | wp-admin | 10 |
| `includes/Updater.php`, `bin/build-release.sh`, `.github/workflows/release.yml`, `README.md` | release + docs | 11 |
| `tests/bootstrap.php` | WordPress stand-ins + `t()` helper | 1 (grows) |

---

### Task 1: Plugin bootstrap, activator, test harness

**Files:**
- Create: `pawfectly-reservations.php`, `includes/Plugin.php`, `includes/Activator.php`, `tests/bootstrap.php`, `bin/test.sh`, `.gitignore`
- Test: `tests/activator-test.php`

**Interfaces:**
- Produces:
  - `Activator::table(): string` (`$wpdb->prefix . 'paw_reservations'`)
  - `Activator::schema_sql( string $table, string $charset ): string`
  - `Activator::activate(): void`
  - `Activator::maybe_upgrade(): void`
  - `Activator::CAPABILITY = 'pawfectly_manage_reservations'`
  - `Plugin::init(): void`
  - `tests/bootstrap.php`, which defines `ABSPATH` and the `PAW_*` constants; a `WP_Error` stand-in (`get_error_code()`, `get_error_message()`, `get_error_data()`); `is_wp_error`, `__`, `esc_html__`, `sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`, `is_email`, `wp_unslash`, `wp_timezone` (UTC), `get_option`/`update_option` backed by a `$GLOBALS['paw_options']` array, `get_transient`/`set_transient` backed by an array, and `wp_json_encode`; a PSR-4 autoloader for `includes/`; and `t( string $name, bool $ok ): void` plus `done(): never`, which exits 1 if any test failed.

- [ ] **Step 1:** Write `tests/activator-test.php`. Given `Activator::schema_sql( 'wp_paw_reservations', 'DEFAULT CHARSET=utf8mb4' )`, assert:
  - It contains each column from spec §4.1: `reference VARCHAR(12) NOT NULL`, `status VARCHAR(20) NOT NULL DEFAULT 'pending'`, `owner_email`, `check_in DATE NOT NULL`, `check_out DATE NOT NULL`, `details LONGTEXT NOT NULL`, `files LONGTEXT NOT NULL`, `signature_path`, `signed_at`, `signed_ip VARCHAR(45)`, `staff_notes TEXT NULL`, `status_log LONGTEXT NOT NULL`, `created_at`, `updated_at`.
  - It contains `PRIMARY KEY  (id)` (two spaces), `UNIQUE KEY reference (reference)`, `KEY status (status)`, `KEY check_in (check_in)`, `KEY owner_email (owner_email)`.
- [ ] **Step 2:** Run `php tests/activator-test.php`. Expected: fatal "Class Pawfectly\Activator not found".
- [ ] **Step 3:** Implement the main file (same shape as Sprint-Booking's `sprint-booking.php:15-45`, with `PAW_` names), `Plugin::init()` (empty list of `init()` calls for now), and `Activator`:
  - `activate()` runs `dbDelta( schema_sql(...) )`, adds the capability to `administrator`, creates the private folder via `Uploads::ensure_private_dir()` (guarded with `class_exists` until Task 5), and sets `paw_db_version`.
  - `maybe_upgrade()` re-runs `activate()`'s work when the option is not equal to `PAW_DB_VERSION`.
  - Write `bin/test.sh`, which loops over `tests/*-test.php` and exits non-zero on failure.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: all `ok`, exit 0. Run `php -l` on every PHP file. Expected: no syntax errors.
- [ ] **Step 5:** Commit: `feat: plugin bootstrap, activator and test harness`.

### Task 2: Field list

**Files:**
- Create: `includes/Fields.php`
- Test: `tests/fields-test.php`

**Interfaces:**
- Produces:
  - `Fields::steps(): array`, a list of `['id' => string, 'title' => string, 'intro' => string, 'fields' => array<field>]`. The field shape is spec §5. `type` is one of `text|email|tel|textarea|choice|yesno|date|country|file|signature|agree`.
  - `Fields::all(): array<string, field>`, flattened and keyed by id, with each field gaining `'step' => int` (1-based).
  - `Fields::countries(): array<string,string>`, ISO-3166 alpha-2 code → English name, including `US` → `United States`.
  - `Fields::COLUMNS`, the list of field ids stored in table columns: `owner_first_name, owner_last_name, owner_email, owner_phone, pet_name, pet_type, check_in, check_out`.

Step ids and order: `owner`, `pet`, `stay`, `health`, `care`, `emergency`, `waiver`. That is 7 steps. The welcome and thank-you screens are not steps in `Fields`.

Field ids, in order:
- **owner:** `owner_first_name`, `owner_last_name`, `owner_email`, `owner_phone`, `address_street`, `address_city` (not required), `address_state`, `address_postcode`, `address_country` (type `country`).
- **pet:** `pet_name`, `pet_type` (choice dog/cat/other), `pet_type_other` (`show_if` pet_type=other), `pet_breed`, `pet_age`, `pet_gender` (choice male/female), `pet_weight`, `pet_spayed` (yesno), `pet_microchipped` (yesno).
- **stay:** `check_in`, `check_out` (date), `special_requests` (textarea, optional).
- **health:** `vaccinations_current`, `vaccination_files` (file), `medical_conditions`, `medical_conditions_details`, `medications`, `medications_details`, `allergies`, `allergies_details`.
- **care:** `feeding_schedule` (textarea, required), `food_preference` (choice own/facility), `dietary_notes`, `behavior_notes`, `exercise_needs` (all optional textareas).
- **emergency:** `emergency_name`, `emergency_phone` (tel), `emergency_relationship`, `vet_name`, `vet_clinic`, `vet_phone` (tel), `vet_authorization` (yesno).
- **waiver:** `waiver_agree` (agree), `signature` (signature), `signed_date` (date, read-only).

Labels, help text, and placeholders are exactly as in the requirements doc. Max lengths: names and short text 60, email 100, phone 30, street 120, textareas 2000.

- [ ] **Step 1:** Write `tests/fields-test.php` asserting:
  - ids are unique
  - every type is in the allowed set
  - every `show_if` key is an earlier field in the same step
  - every `choice` has at least 2 options
  - every id in `COLUMNS` exists
  - the step count is 7
  - `Fields::all()['address_city']['required'] === false`
  - `Fields::countries()['US'] === 'United States'`
  - `Fields::all()['medications_details']['show_if'] === ['medications' => 'yes']`
- [ ] **Step 2:** Run it. Expected: FAIL (class missing).
- [ ] **Step 3:** Implement `Fields`. The countries come from a static array (an ISO list, about 250 entries).
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit: `feat: field list driving form and validation`.

### Task 3: Reference generator

**Files:**
- Create: `includes/Reference.php`
- Test: `tests/reference-test.php`

**Interfaces:**
- Produces:
  - `Reference::ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'`
  - `Reference::generate(): string`, using `random_int`
  - `Reference::is_valid( string $ref ): bool`, matching regex `/^PAW-[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{6}$/`

- [ ] **Step 1:** The test asserts that 1000 generated references are all valid, that at least 990 are unique, and that `is_valid` returns false for `'PAW-O0I1LL'`, `'paw-ABCDEF'`, and `'PAW-ABCDE'`.
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit: `feat: reservation reference generator`.

### Task 4: Validator (non-file fields)

**Files:**
- Create: `includes/Validator.php`
- Test: `tests/validator-test.php`

**Interfaces:**
- Consumes: `Fields::all()`, `Fields::countries()`.
- Produces:
  - `Validator::validate( array $params, \DateTimeImmutable $today ): array|\WP_Error`. On success it returns `['columns' => array<string,string>, 'details' => array<string,string>]`, where columns are the `Fields::COLUMNS` ids and details are everything else, excluding `file`/`signature`/`agree` types. `show_if` fields whose condition is false are dropped entirely.
  - On failure it returns `WP_Error( 'paw_invalid', 'Please check the highlighted fields.', ['status' => 400, 'fields' => array<string,string>] )`.
  - `Validator::MAX_NIGHTS = 90`.
- Error messages:
  - required: "This field is required."
  - email: "Please enter a valid email address."
  - phone: "Please enter a valid phone number."
  - choice: "Please choose one of the options."
  - too long: "Please keep this under {max} characters."
  - date: "Please enter a valid date."
  - past check-in: "Check-in can't be in the past."
  - check-out: "Check-out must be after check-in."
  - too long a stay: "Stays are limited to 90 nights."
  - vaccinations: "All pets must have current vaccinations to board with us."
  - agree: "Please agree to the terms to continue."

- [ ] **Step 1:** Write `tests/validator-test.php`, using a `$good` fixture with every required field filled and `$today = new DateTimeImmutable('2026-10-25')`. Assert:
  - `$good` passes, and `columns['pet_type'] === 'dog'`.
  - Each of these produces exactly that field's error:
    - empty `owner_first_name`
    - `owner_email = 'nope'`
    - `owner_phone = '12'`
    - `pet_type = 'lizard'`
    - `check_in = '2026-10-24'`
    - `check_out = check_in`
    - a stay of 91 nights
    - `vaccinations_current = 'no'`
    - missing `waiver_agree`
  - `check_in = '2026-10-25'` (today) passes. *(Review Focus 3)*
  - `medications = 'yes'` without details → error on `medications_details`. With `medications = 'no'` and details sent, `details` has no `medications_details` key.
  - `address_city` empty passes.
  - `owner_last_name = "O\\'Brien"` (slashed, as WordPress delivers it) is stored as `O'Brien`. A 60-character name with accents (`str_repeat('ë', 60)`) passes, and 61 characters fails. *(Review Focus 5)*
  - `address_country = 'XX'` fails.
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3:** Implement. Phone rule: strip `[\s+\-().]` and require 7–20 digits and nothing else. Dates are parsed with `DateTimeImmutable::createFromFormat('!Y-m-d', …)` and must round-trip exactly.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit: `feat: server-side validation of reservation fields`.

### Task 5: Uploads and signature

**Files:**
- Create: `includes/Uploads.php`, `includes/Signature.php`
- Test: `tests/uploads-test.php`, `tests/signature-test.php`. Fixtures are generated at runtime in a temp dir: a minimal PDF (`%PDF-1.4` with a trailer), a PNG and a JPEG from GD, `<?php echo 1;` named `x.pdf`, a HEIC header (`\0\0\0\x18ftypheic`) named `x.jpg`, and an 11 MB PDF made with `ftruncate`.

**Interfaces:**
- Produces:
  - `Uploads::check( array $file_field ): array|\WP_Error`. It takes the PHP `$_FILES['vaccination_files']` multi-array and returns a list of `['tmp' => string, 'name' => string, 'mime' => string, 'size' => int, 'ext' => string]`. Errors use `WP_Error('paw_invalid', …, ['status' => 400, 'fields' => ['vaccination_files' => msg]])`.
  - `Uploads::MIMES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png']`, `MAX_FILES = 3`, `MAX_BYTES = 10485760`.
  - `Uploads::ensure_private_dir(): string`, which creates `wp_upload_dir()['basedir'] . '/pawfectly-private'` with `index.php` and the `.htaccess` content from spec §4.2.
  - `Uploads::store( string $reference, array $checked ): array|\WP_Error`. It returns `[['name', 'path' (relative), 'mime', 'size']]` and uses `move_uploaded_file`, behind a static `$mover` callable that tests can swap to `rename`.
  - `Uploads::delete( array $stored ): void`.
  - `Uploads::resolve( string $relative ): ?string`, which returns an absolute path only if `realpath` is inside the private dir.
  - `Uploads::init()` registers `admin_post_paw_file`, which is handled by `Uploads::download()` (nonce `paw_file_{id}`, the capability, `nosniff`, and the attachment disposition, except inline for the signature).
  - `Signature::decode( string $data_url ): string|\WP_Error`, which returns PNG bytes. Errors are on field `signature`.
  - `Signature::store( string $reference, string $png ): string|\WP_Error`, which returns a relative path.
- Messages:
  - count: "Please upload between 1 and 3 files."
  - size: "Each file must be 10 MB or smaller."
  - type: "Only PDF, JPG or PNG files are accepted."
  - signature: "Please sign in the box above."

- [ ] **Step 1:** Write the tests. Uploads:
  - The PDF, PNG, and JPG pass, with mimes detected by `finfo`.
  - 0 files and 4 files → count error.
  - 11 MB → size error.
  - The PHP-as-pdf and HEIC-as-jpg files → type error. *(Review Focus 4)*
  - `UPLOAD_ERR_INI_SIZE` → size error.
  - `resolve('../../wp-config.php') === null`.
  - Signature:
    - a 300×150 PNG with a drawn line passes
    - a blank (single-color) PNG → error
    - a JPEG data URL → error
    - a 600 KB payload → error
    - garbage base64 → error
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement.
  - Type detection uses `finfo_file` and must match the extension's entry in `MIMES`. A `.jpeg` extension is normalized to `jpg`.
  - The blank-signature check uses GD: sample a 20×20 grid with `imagecolorat` and require at least 2 distinct alpha/color values.
  - Stored names are `{reference}-{bin2hex(random_bytes(8))}.{ext}` under `Y/m/`.
  - Add these stand-ins to the bootstrap: `wp_upload_dir`, `wp_mkdir_p`, `trailingslashit`.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit: `feat: private uploads and signature handling`.

### Task 6: Reservations store, rate limit, REST endpoint

**Files:**
- Create: `includes/Reservations.php`, `includes/RateLimit.php`, `includes/Rest.php`
- Modify: `includes/Plugin.php` (init `Rest`, `Uploads`)
- Test: `tests/rest-test.php`, `tests/reservations-test.php`

**Interfaces:**
- Consumes: `Validator::validate`, `Uploads::check/store/delete`, `Signature::decode/store`, `Reference::generate`, and `Mailer::reservation_received( array $reservation ): void` (Task 9; until then, guarded with `class_exists`).
- Produces:
  - `Reservations::STATUSES`, the status → translated label map (6 entries).
  - `Reservations::build_row( array $clean, string $reference, array $files, string $signature_path, string $ip, string $now_utc ): array`. Pure. It JSON-encodes `details` and `files`, and `status_log = [['status' => 'pending', 'user_id' => 0, 'at' => $now_utc]]`.
  - `Reservations::insert( array $row ): int|false`. It returns `false` on a duplicate reference so that the caller can retry.
  - `Reservations::find( int $id ): ?array`, with `details`, `files`, and `status_log` decoded to arrays.
  - `Reservations::query( array $args ): array{rows: array, total: int}`. The args are `status`, `search`, `from`, `to`, `orderby` (whitelist `check_in|created_at|reference`), `order`, `page`, and `per_page`. Each condition is built with `$wpdb->prepare`, and search uses `$wpdb->esc_like`.
  - `Reservations::counts(): array<string,int>`.
  - `Reservations::set_status( int $id, string $status, int $user_id ): bool`. It appends to `status_log` and rejects unknown statuses.
  - `Reservations::set_notes( int $id, string $notes ): bool`.
  - `RateLimit::allow( string $bucket, int $max, int $window ): bool`, ported verbatim from Sprint-Booking with the key prefix `paw_rl_`.
  - `Rest::submit( \WP_REST_Request $req ): \WP_REST_Response|\WP_Error`, which follows the 9-step order of spec §7, adding:
    - step 0, an empty-body check (Review Focus 2): if `$_SERVER['CONTENT_LENGTH'] > wp_max_upload_size()`-ish (use `ini_get('post_max_size')`, parsed), or if the params and files are both empty, return 413 `paw_too_large`, "Your files are too large to send. Please upload smaller files (10 MB each at most)."
    - an idempotency check (Review Focus 1): `submission_id` must match a UUID v4 regex. Transient `paw_sub_{md5(id)}` holds a reference from an earlier submission. If found, return 201 with that reference and do nothing else. It is set after a successful insert, for 600 s.
  - `Rest::nonce(): \WP_REST_Response`.

- [ ] **Step 1:** Write the tests. `reservations-test.php` tests `build_row` (column mapping, JSON round-trip of `O'Brien` and emoji, status log). `rest-test.php` uses stubbed `WP_REST_Request`/`WP_REST_Response`, `wp_verify_nonce`, and a fake `$wpdb` that records inserts. Assert:
  - a good submission → 201 and one insert, with a reference matching `Reference::is_valid`
  - a bad nonce → 403 `paw_nonce`
  - the 6th submission in a window → 429
  - honeypot `website = 'x'` → 201 and zero inserts
  - an invalid field → 400 with a `fields` map
  - the same `submission_id` twice → the same reference and one insert *(Review Focus 1)*
  - empty params and files with `CONTENT_LENGTH` above `post_max_size` → 413 `paw_too_large` *(Review Focus 2)*
  - insert failure → 500 and the stored files deleted (assert the temp dir is empty)
  - the first insert returning a duplicate → retried, and success
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement. Routes are registered on `rest_api_init`. `today` comes from `new DateTimeImmutable('today', wp_timezone())`. The IP comes from `REMOTE_ADDR` only.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit: `feat: reservation storage and submit endpoint`.

### Task 7: Settings, shortcode, form markup and styles

**Files:**
- Create: `includes/Settings.php`, `includes/Shortcode.php`, `templates/form.php`, `assets/css/form.css`, `assets/img/paw.svg`
- Modify: `includes/Plugin.php`
- Test: `tests/settings-test.php`, `tests/form-render-test.php`

**Interfaces:**
- Produces:
  - `Settings::OPTION = 'paw_settings'`
  - `Settings::defaults(): array` with keys `business_name` ('Pawfectly'), `logo_id` (0), `staff_emails` (admin email), `intro` (mock-up copy), `waiver` (mock-up waiver in `<p>`), `default_country` ('US'), `roles` ([]).
  - `Settings::get(): array` (merged with defaults)
  - `Settings::sanitize( array $input ): array`:
    - invalid emails are dropped
    - waiver goes through `wp_kses` with `p, strong, em, ul, ol, li, br`
    - unknown country falls back to 'US'
    - roles are limited to existing roles, and selected roles get the capability added while unselected roles have it removed (`administrator` is always kept)
  - The Settings screen is under the Pawfectly menu (the menu itself is created in Task 10; register the submenu on `admin_menu` priority 20).
  - `Shortcode::TAG = 'pawfectly_reservation_form'`
  - `Shortcode::render( $atts ): string`
  - `Shortcode::config(): array`, which is passed as `window.PAW_FORM` and contains `restUrl`, `nonceUrl`, `nonce`, `today` (site timezone `Y-m-d`), `fields` (`Fields::all()` minus labels), `maxFiles`, `maxBytes`, `mimes`, and `i18n` (client messages, the same strings as Task 4/5).
  - `templates/form.php` receives `$settings`, `$steps`, and `$countries`. It renders:
    - `<div class="paw-app" data-paw-form>`
    - a welcome section `data-step="0"`
    - one `<section data-step="N" hidden>` per step, with `<h2>` title and `.paw-card` fields
    - each field as `.paw-field[data-field="{id}"]`, with `data-show-if='{json}'` when it is conditional
    - a thank-you section `data-step="done"`
    - a honeypot (`.paw-hp`, `aria-hidden`, `tabindex=-1`, `autocomplete=off`)
    - the nav bar `.paw-nav` with `button[data-paw-prev]`, `.paw-progress > span`, and `button[data-paw-next]`
    - `<noscript>`
    - Choices and yes/no are radio inputs inside labels. The waiver is printed with `wp_kses_post`.
  - CSS: tokens and components per spec §6.1. All selectors start with `.paw-app`.

- [ ] **Step 1:** Write the tests. `settings-test.php` tests `sanitize` (the cases above). `form-render-test.php` includes the template with stand-ins (`esc_*` identity-escapers that really escape, `wp_kses_post`) and asserts:
  - there are 7 `data-step` sections plus welcome and done
  - every field id from `Fields::all()` appears as `data-field`
  - there are 3 `name="pet_type"` radios
  - `data-show-if` is on `medications_details`
  - a business name of `<script>` comes out escaped
  - the honeypot exists
- [ ] **Step 2:** Run them. Expected: FAIL.
- [ ] **Step 3:** Implement. Assets are enqueued only when the shortcode renders (`wp_enqueue_*` inside `render`).
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit: `feat: settings, shortcode and form markup`.

### Task 8: Form behavior and e2e

**Files:**
- Create: `assets/js/form.js`, `assets/js/signature-pad.js`, `assets/vendor/flatpickr/{flatpickr.min.js, flatpickr.min.css, LICENSE.md}` (copy from `/home/user/kohid/sprint-booking/assets/vendor/flatpickr/`), `tests/e2e/build.php`, `tests/e2e/form-e2e.js`, `tests/e2e/README.md`
- Modify: `includes/Shortcode.php` (enqueue the new scripts)

**Interfaces:**
- Consumes: `window.PAW_FORM` (Task 7), the markup hooks (Task 7), and the REST contract (Task 6: 201 `{reference}`, 400 `{data: {fields}}`, 403, 413, 429).
- Produces:
  - `SignaturePad` (global, in `signature-pad.js`): `new SignaturePad( canvas )` with `.isEmpty(): boolean`, `.strokes: number`, `.clear(): void`, `.toDataURL(): string`, and an `onchange` callback.
  - `form.js` behavior per spec §6.2, with these exact values:
    - draft key `paw_draft_v1`, 7-day expiry, 500 ms debounce
    - progress = `current / 7`
    - hash `#step-N`
    - flatpickr `dateFormat: 'Y-m-d'`, `altInput: true`, `altFormat: 'm/d/Y'`, `minDate: PAW_FORM.today` *(Review Focus 3)*, check-out `minDate` = check-in + 1 day
    - `submission_id` from `crypto.randomUUID()`, generated once per page load and kept across retries *(Review Focus 1)*
    - the submit button is disabled while in flight
    - on 403: GET `nonceUrl`, retry once
- `tests/e2e/build.php` renders `templates/form.php` with stand-ins into `tests/e2e/out/index.html`, inlining `PAW_FORM` (`restUrl: '/mock/reservations'`) and linking the real CSS/JS.

- [ ] **Step 1:** Write `tests/e2e/form-e2e.js`. It uses Playwright `chromium.launch()` and `page.route('**/mock/reservations')` to capture the request and reply 201 `{reference: 'PAW-7K3QX9'}`. It asserts:
  - Clicking → on the owner step while it is empty shows "This field is required." and stays on step 1.
  - Choosing `pet_type = other` reveals `pet_type_other`.
  - `vaccinations_current = no` shows the warning and disables →.
  - `medications = yes` reveals details.
  - After filling everything, uploading a generated PNG, drawing on the canvas with `page.mouse`, and ticking agree, submitting shows "PAW-7K3QX9".
  - The captured multipart body contains `owner_email`, `vaccination_files`, a `signature` starting with `data:image/png;base64,`, and a `submission_id` in UUID form.
  - Reloading mid-form (after step 1) restores the owner fields.
  - Double-clicking Submit sends exactly one request.
  - The flow runs at 375×812 with no horizontal scroll (`document.documentElement.scrollWidth <= 375`).
- [ ] **Step 2:** Run `php tests/e2e/build.php && node tests/e2e/form-e2e.js`. Expected: FAIL (no JS behavior).
- [ ] **Step 3:** Implement `signature-pad.js`, then `form.js`.
- [ ] **Step 4:** Run the same command. Expected: every assertion passes. Also run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Take screenshots of the welcome, pet, and waiver steps (desktop and 375 px) into the scratchpad and compare them against the mock-ups: cream background, white cards, coral nav pill, option cards. Commit: `feat: interactive multi-step form with signature and drafts`.

### Task 9: Emails

**Files:**
- Create: `includes/Mailer.php`, `includes/EmailTemplate.php`, `templates/email.php`
- Test: `tests/mailer-test.php`

**Interfaces:**
- Consumes: `Settings::get()` and `Reservations::find()` row shape (Task 6).
- Produces:
  - `EmailTemplate::render( string $heading, string $body_html, array $settings ): string`
  - `Mailer::reservation_received( array $reservation ): void`, which sends to the owner and to staff
  - `Mailer::status_changed( array $reservation ): void`
  - Subjects are exactly as in spec §9. Headers use `Content-Type: text/html; charset=UTF-8`. Failures go to `error_log` with the `[pawfectly]` prefix.

- [ ] **Step 1:** Test with a `wp_mail` stand-in that records calls. Assert:
  - two mails are sent: to `owner_email`, and to every staff email
  - the owner subject is `We've received your reservation request — PAW-7K3QX9`
  - the owner body contains the pet name and `10/30/2026` (the m/d/Y of `2026-10-30`), and does **not** contain `medications_details` text
  - the staff body contains the `admin_url` link to the detail view
  - a pet name of `<b>x</b>` is escaped
  - a `wp_mail` that returns false doesn't throw
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3:** Implement, and remove the `class_exists` guard in `Rest`.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit: `feat: owner confirmation and staff alert emails`.

### Task 10: wp-admin (list, detail, print, status, CSV)

**Files:**
- Create: `includes/Admin.php`, `includes/ListTable.php`, `templates/admin-detail.php`, `assets/css/admin.css`, `assets/js/admin.js`
- Modify: `includes/Plugin.php`
- Test: `tests/admin-test.php`

**Interfaces:**
- Consumes: `Reservations::query/find/counts/set_status/set_notes`, `Fields::steps()`, `Mailer::status_changed`, `Uploads` download URL (`admin-post.php?action=paw_file&id=&n=&_wpnonce=`, where `n` is the file index or `sig`).
- Produces:
  - Top menu `paw-reservations` (dashicons-pets, capability `pawfectly_manage_reservations`), with a pending-count bubble.
  - `ListTable extends \WP_List_Table`, with the columns, tabs, filters, search, and bulk actions from spec §8.
  - `Admin::detail( int $id ): void`, which renders `templates/admin-detail.php` (print mode with `&print=1`).
  - The `admin_post_paw_status` handler (nonce `paw_status_{id}`, capability, optional owner email), which redirects back with a notice.
  - The `admin_post_paw_notes` handler.
  - The `admin_post_paw_export` handler, which streams the CSV of the current filters.
  - `Admin::csv_cell( string $v ): string`, which prefixes `'` when the value starts with `=`, `+`, `-`, `@`, a tab, or a CR.
  - `Admin::csv_header(): array` and `Admin::csv_row( array $reservation ): array`, with columns: reference, status, created_at, then every `Fields::all()` id except file/signature/agree, then `files_count` and `signed_at`.
  - Badge colors per spec §8.

- [ ] **Step 1:** Write `tests/admin-test.php`. Assert:
  - `csv_cell('=1+1') === "'=1+1"`, `csv_cell('-5')` is prefixed, and `csv_cell('Zoë')` is unchanged
  - `csv_header()` length equals `csv_row($fixture)` length
  - in `csv_row`, a hidden conditional field is an empty string
  - the detail template, rendered with a fixture and stand-ins, shows every step title, contains a `paw_file` link with `n=sig`, and escapes a `<script>` in `behavior_notes`
- [ ] **Step 2:** Run it. Expected: FAIL.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 5:** Commit: `feat: wp-admin reservations list, detail, status and CSV`.

### Task 11: Updater, release, README, real-WordPress smoke test

**Files:**
- Create: `includes/Updater.php` (port of Sprint-Booking's `includes/Updater.php` with `REPO = 'kohid/Pet-Boarding-Reservation'`, `SLUG = 'pawfectly-reservations'`, and the `paw_` cache key), `bin/build-release.sh`, `.github/workflows/release.yml` (port of Sprint-Booking's, with the version check reading `pawfectly-reservations.php`, `bash bin/test.sh` run before packaging, and a zip root folder of `pawfectly-reservations/`), `README.md`
- Modify: `includes/Plugin.php`, `CLAUDE.md` (add "Commands: `bash bin/test.sh`, `php tests/e2e/build.php && node tests/e2e/form-e2e.js`")
- Test: `tests/updater-test.php` (port the relevant `parse_release`/`trusted_url` cases)

- [ ] **Step 1:** Port the updater test with the new repo and slug. Run it. Expected: FAIL.
- [ ] **Step 2:** Port `Updater`. Run `bash bin/test.sh`. Expected: PASS.
- [ ] **Step 3:** Write the README: install, shortcode, settings, the private-files note including the Nginx `location ~ /pawfectly-private/ { deny all; }` snippet, statuses, and the release process.
- [ ] **Step 4:** Smoke test on real WordPress. Use `wp-env`/Docker or a PHP built-in server with WordPress and SQLite if available. If it isn't available, record that in the summary and skip. Check:
  - activation creates the table
  - submitting through the page works
  - a direct URL to a stored file returns 403
  - the admin list, detail, print, status email, and CSV work
- [ ] **Step 5:** Run `bash bin/build-release.sh` and check that the zip contains no `tests/` or `docs/`. Commit: `chore: updater, release workflow and README`.

---

## Self-review notes

- **Spec coverage:** §3 → Tasks 1–11. §4.1 → T1/T6. §4.2 → T5/T10/T11. §5 → T2. §6.1 → T7. §6.2 → T8. §7 → T4–T6. §8 → T7 (Settings) and T10. §9 → T9. §10 → T6/T8. §11 → every task plus T8 e2e and T11 smoke.
- **Spec correction:** the spec said 8 steps, but the mock-ups have 7 field sections after the welcome screen. The spec was updated to 7 alongside this plan.
- **Additions beyond the spec:** `submission_id` idempotency and the 413 handling, both from Review Focus.
