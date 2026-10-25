# Pawfectly Pet Boarding Reservation — design spec (v0.1)

- Status: **awaiting review**
- Date: 2026-10-25
- Inputs: `docs/requirements/2026-10-25-reservation-form.md` (field inventory from the mock-ups) and the decisions recorded below.
- Approach chosen: **B. Mirror Sprint-Booking**, with one shared field list and a lean table plus a JSON details column.

## 1. Goal

A WordPress plugin that lets Pawfectly's customers request a boarding stay through a branded, multi-step form. Staff review, confirm, and manage each request in wp-admin. Success for v0.1 means:

1. A visitor can complete all 8 steps on a phone or a desktop, upload vaccination records, sign the waiver, and get a reference number.
2. The server rejects anything the form would reject, even if the browser is bypassed.
3. Staff are emailed, can find the reservation in wp-admin, view every answer, open the files and signature, change the status, print the reservation, and export a CSV.
4. Uploaded records and signatures cannot be fetched by anyone who isn't signed in as staff.

## 2. Decisions (from clarifying questions; all at recommended defaults)

| # | Decision |
|---|---|
| 1 | v0.1 includes the form, wp-admin management, and emails. Not included: a front-end staff dashboard, customer accounts, payments. |
| 2 | No capacity or availability limits. Every request starts as **Pending** and staff confirm or decline it. |
| 3 | One pet per reservation. |
| 4 | No pricing. |
| 5 | Answering "Yes" to medical conditions, medications, or allergies reveals a required details textarea. "No" to vaccinations shows an inline warning and blocks moving past step 4. |
| 6 | Vaccination upload is required: 1–3 files, PDF/JPG/PNG, 10 MB each, stored privately. |
| 7 | Check-in is today or later. Check-out is after check-in. |
| 8 | Dates use a calendar picker and are shown as **MM/DD/YYYY**. They are stored as ISO `Y-m-d`. |
| 9 | The signature is stored as a PNG with the signing time (UTC) and the submitter's IP address. |
| 10 | Drafts are autosaved to the browser's `localStorage`, except files and the signature. Drafts are cleared on successful submit and expire after 7 days. |
| 11 | Submit is final and leads to a thank-you screen showing the reference. |
| 12 | Emails: a confirmation to the owner and an alert to the staff address in Settings. |
| 13 | A print-friendly detail view in wp-admin. No PDF generation. |
| 14 | The logo is set in Settings (Media Library). Until one is set, a paw placeholder is shown. |
| 15 | Intro text and waiver text are editable in Settings and default to the mock-up copy. |
| 16 | Shortcode `[pawfectly_reservation_form]`. Updates come from GitHub releases. |

## 3. Plugin layout

```
pawfectly-reservations/            (repo root = plugin folder)
├── pawfectly-reservations.php     header, constants, autoloader, hooks
├── includes/
│   ├── Plugin.php                 wires every class's init()
│   ├── Activator.php              dbDelta table, DB-version upgrade, capability, private dir
│   ├── Fields.php                 THE field list (single source of truth)
│   ├── Validator.php              validates a submission against Fields (pure PHP, testable)
│   ├── Reservations.php           insert / find / list / update status (wpdb)
│   ├── Reference.php              PAW-XXXXXX generator (unambiguous alphabet)
│   ├── Uploads.php                MIME check, private storage, staff-only download handler
│   ├── Signature.php              decode + validate PNG data URL, save privately
│   ├── Rest.php                   POST /pawfectly/v1/reservations
│   ├── RateLimit.php              per-IP transient limiter (as Sprint-Booking)
│   ├── Shortcode.php              [pawfectly_reservation_form], enqueues assets, passes config
│   ├── Settings.php               options read/write + Settings screen
│   ├── Admin.php                  menu, list table, detail/print view, status change, CSV
│   ├── Mailer.php                 owner confirmation + staff alert
│   ├── EmailTemplate.php          branded HTML wrapper
│   └── Updater.php                GitHub-release updates (ported from Sprint-Booking)
├── templates/
│   ├── form.php                   welcome + steps, rendered from Fields
│   ├── admin-detail.php           reservation detail / print view
│   └── email.php                  email layout
├── assets/
│   ├── css/form.css               scoped under .paw-app
│   ├── css/admin.css              scoped under .paw-admin
│   ├── js/form.js                 stepper, validation, conditional fields, draft, submit
│   ├── js/signature-pad.js        small canvas signature pad (no dependency)
│   ├── js/admin.js                status change, print
│   ├── img/paw.svg                placeholder logo
│   └── vendor/flatpickr/          vendored, MIT
├── tests/                         php tests/*.php (WordPress stand-ins) + tests/e2e (Playwright)
├── bin/build-release.sh
├── .github/workflows/release.yml
└── README.md
```

Conventions follow Sprint-Booking:
- PHP 8.0+ and WordPress 6.0+.
- Namespace `Pawfectly\`, with `Pawfectly\Foo` loaded from `includes/Foo.php`.
- Constants use the `PAW_` prefix.
- Text domain `pawfectly-reservations`.
- Options, transients, and the table use the `paw_` prefix.
- Classes are `final` with static `init()`.
- Everything is escaped on output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` for the waiver).

## 4. Data model

### 4.1 Table `{prefix}paw_reservations`

Only columns that staff filter, sort, or search by. Every other answer goes in `details`.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED PK AI | |
| reference | VARCHAR(12) UNIQUE | `PAW-` + 6 chars from `ABCDEFGHJKMNPQRSTUVWXYZ23456789` |
| status | VARCHAR(20) | `pending` (default), `confirmed`, `declined`, `checked_in`, `completed`, `cancelled` |
| owner_first_name / owner_last_name | VARCHAR(60) | |
| owner_email | VARCHAR(100), indexed | |
| owner_phone | VARCHAR(30) | |
| pet_name | VARCHAR(60) | |
| pet_type | VARCHAR(10) | `dog` / `cat` / `other` |
| check_in / check_out | DATE, check_in indexed | |
| details | LONGTEXT | JSON of every other field, keyed by field id |
| files | LONGTEXT | JSON list of `{name, path, mime, size}`. Paths are relative to the private dir. |
| signature_path | VARCHAR(190) | relative to the private dir |
| signed_at | DATETIME (UTC) | |
| signed_ip | VARCHAR(45) | |
| staff_notes | TEXT NULL | internal notes, editable in the detail view |
| status_log | LONGTEXT | JSON list of `{status, user_id, at}` |
| created_at / updated_at | DATETIME (UTC) | |

The schema version is stored in the `paw_db_version` option. `Activator::maybe_upgrade()` re-runs `dbDelta` when the stored version is older than `PAW_DB_VERSION`.

### 4.2 Private files

The private folder is `wp-content/uploads/pawfectly-private/{Y}/{m}/`.

- The folder holds an `index.php` and an `.htaccess` with `Require all denied` (and the Apache 2.2 `Deny from all` equivalent).
- Files are renamed to `{reference}-{random16}.{ext}`, so original names never reach the filesystem.
- Original names are kept in the `files` JSON, for display only.
- Staff download files through `admin-post.php?action=paw_file&id=…&n=…`, which requires a nonce and the `pawfectly_manage_reservations` capability. Files are streamed with `Content-Disposition: attachment` and `X-Content-Type-Options: nosniff`.
- The signature is shown inline in the detail view through the same handler, which serves `image/png` only.
- Nginx sites can't read `.htaccess`. The README documents the one-line `location` deny rule, and Settings shows a warning if a test request to a probe file in the folder succeeds.

## 5. The field list (`Fields.php`)

`Fields::steps()` returns an ordered array of steps, and each step has its fields. Each field has:

```php
[
  'id'       => 'pet_type',
  'label'    => __( 'Pet Type', 'pawfectly-reservations' ),
  'help'     => '',                     // small grey text under the label
  'type'     => 'choice',               // text | email | tel | textarea | choice | yesno | date | country | file | signature | agree
  'options'  => [ 'dog' => 'Dog', 'cat' => 'Cat', 'other' => 'Other' ],  // choice only
  'required' => true,
  'max'      => 60,                     // max length for text types
  'placeholder' => '',
  'show_if'  => [ 'medical_conditions' => 'yes' ],  // optional conditional
  'column'   => 'pet_type',             // optional: stored in a table column instead of details
  'half'     => false,                  // layout: two-up on wide screens (address block)
]
```

Three things read this list:
- `templates/form.php` renders the steps from it.
- `Shortcode` passes a JSON copy to `form.js`, which uses it for client-side rules (required, conditional, max length, email, and phone patterns).
- `Validator` uses it for the same rules on the server, plus the rules that only the server can check (§7).

### 5.1 Steps and conditional additions

The steps are exactly the inventory in the requirements doc. These fields are added:

| Step | Field id | Type | Rule |
|---|---|---|---|
| 4 Health | `medical_conditions_details` | textarea | required if `medical_conditions = yes` |
| 4 Health | `medications_details` | textarea | required if `medications = yes`; help text: "Name, dose and schedule" |
| 4 Health | `allergies_details` | textarea | required if `allergies = yes` |
| 2 Pet | `pet_type_other` | text | required if `pet_type = other` ("Please specify") |

`vaccinations_current = no` shows: "We're sorry, all pets must have current vaccinations to board with us. Please contact us once vaccinations are updated." The → button is disabled while that answer stands, and the server rejects a submission with that answer.

Country is a `<select>` of countries (WordPress has no built-in list, so it ships as a static array in `Fields::countries()`). It defaults to the Settings value, initially "United States".

## 6. Front end

### 6.1 Look

The look follows the mock-ups, using Metronic 8 tokens re-implemented in CSS. No Metronic files are bundled, because Metronic is commercially licensed.

```css
.paw-app {
  --paw-primary: #FF7F50;  --paw-primary-dark: #E8663A;  --paw-primary-tint: #FFF1EB;
  --paw-info: #30AFFF;     --paw-danger: #BC0202;        --paw-ink: #2C3947;
  --paw-steel: #3B7597;    --paw-success: #06D6A0;       --paw-royal: #0F42C0;
  --paw-cream: #FFF8F3;    --paw-card: #FFFFFF;          --paw-line: #E4E6EF;
  --paw-muted: #7E8299;    --paw-radius: .475rem;        --paw-pill: 2rem;
  --paw-shadow: 0 .1rem 1rem .25rem rgba(0,0,0,.05);
  --paw-ring: 0 0 0 3px rgba(255,127,80,.3);
  font-family: Inter, system-ui, -apple-system, "Segoe UI", sans-serif;
}
```

- Everything is scoped under `.paw-app` with `paw-` class prefixes. The block resets its own box-sizing and typography so the host theme can't break it.
- The page background is cream. Each step's heading is the section title, and its fields sit in one white card with `--paw-shadow`. Fields are separated by thin rules.
- Inputs use the mock-ups' underline style: a bottom border only, which turns coral on focus.
- **Option cards** (choice, agree) are full-width rows with a light grey fill, a soft shadow, and a radio circle on the right. When selected, the row gets a coral border, a `--paw-primary-tint` fill, and a filled circle. They are built as `<input type=radio>` with labels (Metronic's "btn-check" pattern), so the keyboard and screen readers work.
- **Yes/No** is two equal buttons side by side, built as radios. When selected, the button is coral with white text.
- **Nav bar:** a fixed bottom pill with a coral ← button, a progress track that fills coral as steps are completed, and a coral → button. On the final step, → is hidden and the full-width coral **Submit** button is shown.
- **File input:** a white pill button "Choose a file" with coral text. Chosen files are listed with their name, size, and a remove ×.
- **Signature:** a bordered canvas with a faint "SIGN HERE" watermark and a "Clear" link. It is drawn with pointer events, so mouse, touch, and pen all work, and it scales for high-DPI screens.
- Errors appear in red (`--paw-danger`) under the field, and the field is marked `aria-invalid`. On failed →, focus moves to the first invalid field.
- Layout is mobile-first. The address block is two-up from 640px. The content column is at most 760px wide.
- `prefers-reduced-motion` turns off the step slide animation.

### 6.2 Behavior (`form.js`, vanilla JS)

- **Steps:** the welcome screen (index 0) plus steps 1–8. → validates only the current step. ← never validates. The progress fill is `current / 8`.
- The URL hash (`#step-3`) is kept in sync so the browser Back button moves between steps. Jumping ahead past an invalid step is not allowed.
- **Conditionals:** `show_if` fields are hidden with the `hidden` attribute, and their values are ignored while hidden.
- **Dates:** flatpickr with display format `m/d/Y`, a hidden value in ISO format, and `minDate: today`. The check-out `minDate` is check-in + 1. The waiver date defaults to today and is read-only.
- **Draft:** non-file values are saved to `localStorage['paw_draft_v1']` with a timestamp, debounced at 500 ms. On load, a draft younger than 7 days is restored and a small "We restored your progress · Start over" bar is shown.
- **Submit:** builds a `multipart/form-data` request with all values, the files, and the signature as a PNG data URL. It is sent to the REST route with an `X-WP-Nonce` header.
  - While the request runs, the button shows a spinner and is disabled.
  - On success: the draft is cleared and the thank-you panel is shown: "Thank you! Your reservation request PAW-7K3QX9 has been received. We'll email you once it's confirmed."
  - On a 400 with `fields` errors: the form jumps to the step of the first error and shows the messages.
  - On 403 (stale nonce): the form fetches a fresh nonce from `GET /pawfectly/v1/nonce` and retries once.
  - On 429 or a network error: a message bar is shown and the form stays intact.
- **Honeypot:** a hidden `website` field. If it is filled, the server returns a fake success and saves nothing.

## 7. Server: `POST /wp-json/pawfectly/v1/reservations`

The route is public (`permission_callback` `__return_true`). The callback does the following, in order:

1. Checks the REST nonce (`wp_rest`). On failure it returns 403 `paw_nonce`.
2. Rate limit: at most 5 submissions per IP per 10 minutes (`RateLimit::allow( 'submit', 5, 600 )`). Over the limit it returns 429.
3. Honeypot: if filled, returns 200 with a fake reference and stops.
4. `Validator::validate( $params, $files )`. The result is either the clean data or a `WP_Error` with code 400 and `data.fields = { field_id: message }`. The checks are:
   - required fields, honoring `show_if`
   - max lengths
   - `is_email` for the email field
   - phone: 7–20 digits after stripping spaces, `+`, `-`, `(`, and `)`
   - choice values limited to their listed options
   - dates: valid ISO dates, check-in no earlier than today in the site's timezone, check-out after check-in, and a stay of at most 90 days (configurable later)
   - `vaccinations_current` must be `yes`
   - `agree` must be `1`
   - files: 1–3 files, each at most 10 MB, with no PHP upload errors, and an actual type of `application/pdf`, `image/jpeg`, or `image/png` (checked with `finfo` on the temp file and `wp_check_filetype_and_ext`, never the browser's declared type)
   - signature: a `data:image/png;base64,` prefix, a decoded size under 500 KB, PNG magic bytes, `getimagesizefromstring` dimensions within limits, and not blank (the browser sends a stroke count of at least 1, and the server checks that the decoded image has more than one distinct color)
   - every text value is passed through `sanitize_text_field` or `sanitize_textarea_field`
5. Generates the reference. On a unique-key collision it retries up to 5 times.
6. Moves the files and signature into the private folder (§4.2). If any move fails, the files written so far are deleted and the server returns 500 `paw_storage`.
7. Inserts the row. If the insert fails, the saved files are deleted and the server returns 500.
8. Sends the emails (§9). An email failure does not fail the request; it is logged with `error_log`.
9. Returns 201 `{ reference }`.

`GET /pawfectly/v1/nonce` returns `{ nonce }`. It exists because cached pages can serve an expired nonce.

## 8. wp-admin

- **Capability:** `pawfectly_manage_reservations`. It is added to administrators on activation. Settings lists the site's roles so others can be ticked, the same as Sprint-Booking. Settings itself requires `manage_options`.
- **Menu:** **Pawfectly** (paw dashicon) with **Reservations** and **Settings**. A count bubble shows the number of pending reservations.
- **Reservations list** (`WP_List_Table`):
  - Columns: Reference, Pet (name and type badge), Owner (name and email), Check-in → Check-out (and number of nights), Status badge, Submitted.
  - Status tabs with counts. A date-range filter on check-in. Search across reference, owner name, email, phone, and pet name.
  - Sorted by check-in by default. 20 per page.
  - Bulk actions: confirm, decline, cancel.
  - **Export CSV** exports the current filter with all fields flattened. Cell values that start with `=`, `+`, `-`, or `@` are prefixed with `'` to prevent formula injection.
- **Detail view** (`?page=paw-reservations&view=ID`):
  - Header with the reference, status badge, and a status `<select>` with Save. Changing the status is a nonce-protected POST, appended to `status_log`, with an optional "Email the owner about this change" checkbox.
  - One card per step showing every answer with its label from `Fields`. "Yes" answers on health questions are highlighted.
  - The files list with download links, and the signature image with "Signed {date} from {IP}".
  - A staff notes textarea, and the status history.
  - **Print** opens the same view with `&print=1`, a minimal print stylesheet that hides the wp-admin chrome, and calls `window.print()`.
- **Settings** (Options API, single option `paw_settings`, sanitized on save):
  - Business name (default "Pawfectly").
  - Logo (media picker).
  - Staff email(s), comma-separated, defaulting to the admin email.
  - Intro text.
  - Waiver text (allowed HTML: `p`, `strong`, `em`, `ul`, `ol`, `li`, `br`).
  - Default country.
  - Roles with access.
  - A "Private files protected" check with a status indicator.
  - A shortcode box with a Copy button.
- Styling uses `.paw-admin`, a scoped stylesheet in the Metronic card style, and brand-colored status badges: pending is info `#30AFFF`, confirmed is success `#06D6A0`, declined is danger `#BC0202`, checked in is royal `#0F42C0`, completed is steel `#3B7597`, and cancelled is muted grey.

## 9. Emails

Emails are HTML, sent with `wp_mail`, and wrapped in `EmailTemplate`: a logo header, a coral accent bar, the content, and the business name in the footer. Every value is escaped.

- **Owner confirmation:** subject "We've received your reservation request — {reference}". Contains the stay dates, pet name, and a note that it is pending confirmation. It includes no health data and no attachments.
- **Staff alert:** subject "New reservation request {reference}: {pet} ({check_in} → {check_out})". Contains a summary and a "View in wp-admin" link. No attachments are sent, because files stay private.
- **Status change** (optional, sent from the detail view): "Your reservation {reference} is now {Confirmed|Declined|…}".

## 10. Error handling summary

| Situation | Behavior |
|---|---|
| Field invalid (client) | Inline message; navigation blocked on that step |
| Field invalid (server) | 400 with a per-field map; the form jumps to the step of the first error |
| Stale nonce | 403; the form fetches a fresh nonce and retries once, then shows "Please refresh the page" |
| Too many submissions | 429; "Too many attempts, please try again in a few minutes." |
| File too large or wrong type | Rejected in the browser on selection, and again by the server |
| Storage or DB failure | 500; partial files removed; "Something went wrong, your details are still here — please try again." |
| Email failure | Reservation still saved; error logged |
| JS disabled | A `<noscript>` notice asking the visitor to enable JavaScript or call (the form needs the signature pad) |

## 11. Testing

- **Unit tests** are `php tests/*.php` scripts with small WordPress stand-ins, following Sprint-Booking's pattern. They are run by `bin/test.sh`.
  - `validator-test.php`: required fields, conditionals, email/phone, choices, date rules, vaccination block, agree, files (fixtures: real PDF/PNG/JPG, a PHP file renamed to `.pdf`, oversized files), and signature (valid, blank, non-PNG, too large).
  - `reference-test.php`: format, alphabet, and collision retry.
  - `fields-test.php`: unique ids, valid types, every `show_if` target exists, every `column` exists in the schema.
  - `csv-test.php`: formula-injection escaping.
- **E2E test** (`tests/e2e/form-e2e.js`, Playwright): a build script renders the form into static HTML with a mocked REST endpoint, the same way Sprint-Booking's `tests/e2e/build.php` does. The test fills all 8 steps, checks that → is blocked on an empty required field, that conditionals appear, and that the vaccination "No" block works, draws a signature, submits, and asserts the thank-you screen and the multipart request payload.
- **Manual pass** on a real WordPress install before tagging v0.1.0: activate, submit, check the files are denied by direct URL, check the admin list, detail, print, CSV, and status email.

## 12. Out of scope for v0.1 (possible later work)

Capacity limits and availability calendar, multiple pets per booking, pricing and deposits/payments, customer accounts and "my reservations", PDF export, a front-end staff dashboard, SMS/WhatsApp, kennel/room assignment, and retention rules for automatically deleting old records.
