# Pawfectly v0.4 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let one booking hold up to 5 pets, show the service menu and the booking-type choice on the welcome screen, and let each groomed pet name a preferred groomer and a repeat schedule.

**Architecture:**
- Per-pet fields stay defined once in `Fields`, each flagged `per_pet`. Every layer handles those fields as a list of pets:
  - the form, which renders a card per pet
  - the Validator, which checks each pet
  - storage, in `details.pets`
  - display, through `Reservations::pets()`
- Booking-level fields keep today's flat path.
- `Services::estimate()` sums over the pets.
- Older single-pet rows are read through `Reservations::pets()` as one pet.

**Tech Stack:**
- PHP 8.0+ and WordPress 6.0+, with vanilla JS.
- Plain PHP unit tests with WordPress stubs: `bash bin/test.sh`.
- Playwright browser tests.

**Spec:** `docs/superpowers/specs/2026-10-25-multi-pet-design.md`

## Global Constraints

- **Naming:** namespace `Pawfectly\`. Prefixes are `paw_` and `paw-`; the text domain is `pawfectly-reservations`. The form is scoped under `.paw-app`, and inputs keep the `:not(#paw-0)` guard.
- **Versions:** `PAW_VERSION` is `'0.4.0'` and the header reads `Version: 0.4.0`. `PAW_DB_VERSION` is `'4'`.
- **Limits:** `Fields::MAX_PETS = 5`, and at most 3 vaccination files per pet.
- **Request format:**
  - Per-pet inputs are named `pets[N][field_id]`, with N 0-based.
  - Per-pet files are named `vaccination_files_N[]`.
  - Errors for a pet are keyed `pets.N.field_id`; file errors are keyed `vaccination_files_N`.
- **Copy:**
  - "Add another pet" (with a "+"), "Remove", and "Pet %d".
  - "No grooming for this pet" and "Any / Flexible".
  - "Grooming is for dogs only. Choose Boarding + grooming to board your other pets."
  - "Choose a service for at least one pet, or book Boarding only."
  - "Grooming is for dogs. You'll choose a service for each pet later."
  - Consent: "I consent to be contacted by Pawfectly via phone, email, or text to confirm and remind me about these appointments. Message & data rates may apply."
- **Frequency options** (`grooming_frequency`): `once` "One time only", `2` "Every 2 weeks", `4` "Every 4 weeks", `6` "Every 6 weeks", `8` "Every 8 weeks".
- **Days** (`preferred_day`): `mon` to `sat`, labelled Monday to Saturday.
- **Times** (`preferred_time`): `morning` "Morning", `afternoon` "Afternoon".
- **Draft storage:** the browser draft key becomes `paw_draft_v2`. Old v1 drafts are ignored.
- **Commits:** every commit ends with the session's two attribution lines, and `bash bin/test.sh` must be green before each one.

## Review Focus

1. **Removing a middle pet** (pet 2 of 3): pet 3 becomes pet 2 and keeps its own answers and files. No stale `pets[2]` is sent. Test: Task 5 e2e.
2. **A 6th pet forged in the POST:** refused with "Please check the highlighted fields.", and nothing is stored. Test: Task 2.
3. **A cat in a Boarding + grooming booking with a dog:** valid. The cat boards only, and the cat's estimate line is boarding. Test: Task 2 and Task 1.
4. **Old single-pet rows:** the staff panel, CSV, emails and My Bookings show them exactly as before, with files listed. Test: Task 6 (old-row fixture).
5. **Files for pet 2 only failing validation:** the error lands on `vaccination_files_1`, and the form opens the Health step at pet 2. Test: Task 3 REST and Task 5 e2e.

---

### Task 1: Fields, groomers setting, multi-pet estimate

**Files:**
- Modify:
  - `includes/Fields.php`
  - `includes/Services.php`
  - `includes/Settings.php` (`defaults()`, `sanitize()`)
- Test:
  - `tests/fields-test.php`
  - `tests/services-test.php`
  - `tests/settings-test.php`

**Interfaces:**
- Produces:
  - `Fields::MAX_PETS = 5`.
  - `Fields::f()` gains a `per_pet` key (bool, default `false`).
  - `Fields::welcome(): array` returns the `booking_type` field, with `step` 0. `Fields::all()` includes it.
  - `Fields::pet_fields(): array<string, array>` returns the per-pet fields from `all()`, keyed by id, in step order.
- Step contents:
  - **Step `pet`:** every field is `per_pet`, including `dog_size`.
  - **Step `stay`:** the booking-level fields are `check_in`, `check_out`, `appointment_date` and `appointment_time`, then `special_requests`. The per-pet fields, in order:
    1. `service`, type `service`, shown when `booking_type` is grooming or both and `pet_type` is dog.
    2. `groomer`, type `choice`, options built at call time: `any` = "Any / Flexible" plus one option per name in `Services::groomers()`, keyed by the name. Default `any`.
    3. `grooming_frequency`, type `choice`.
    4. `preferred_day`, type `choice`, shown when `grooming_frequency` is 2, 4, 6 or 8.
    5. `preferred_time`, type `choice`, with the same `show_if`.
    6. `last_groom_date`, type `date`, not required.
    7. `grooming_notes`, type `textarea`, not required.

    Fields 2 to 7 are shown when `service` is one of the keys in `Services::enabled()`. That list is built at call time and excludes `none`.
  - **Steps `health` and `care`:** every field is `per_pet`. `vaccination_files` stays type `file`.
  - **Step `waiver`:** adds `contact_consent`, type `agree`, `required => false`, with options `array( '1' => <consent copy> )`.
- `Fields::COLUMNS` becomes: owner name, email and phone fields, `pet_name`, `pet_type`, `pet_count`, `booking_type`, `check_in`, `check_out`, `appointment_date`, `service`. The Validator derives `pet_name`, `pet_type`, `pet_count` and `service` (Task 2).
- `Services::groomers(): list<string>` reads `Settings::get()['groomers']`.
- `Services::estimate( array $v )` changes:
  - When `$v['pets']` is a list, it builds lines for each pet.
    - Boarding line, for boarding or both: label `"{pet_name}: Boarding, N night(s)"`, amount = rate(pet type, dog size) × nights.
    - Service line, when the pet's service is an enabled id: label `"{pet_name}: {service name}"`.
  - Without `pets`, `$v` is treated as a single pet, as today, so existing callers and stored snapshots keep working.
  - The return shape is unchanged.
- Settings:
  - New key `groomers` (`list<string>`), default `array()`.
  - The sanitizer accepts a string (one name per line) or an array. It trims each name, drops blanks and case-insensitive duplicates, caps each name at 60 characters, and keeps the order.
  - When `groomers` isn't sent, the saved list is kept.

- [ ] **Step 1: Tests.**
  - **fields-test:**
    - `Fields::welcome()[0]['id'] === 'booking_type'`, and `all()['booking_type']['step'] === 0`.
    - `pet_fields()` contains `pet_name`, `dog_size`, `service`, `groomer`, `vaccination_files` and `feeding_schedule`, but not `check_in` or `owner_email`.
    - `all()['groomer']['options']` starts with `any => 'Any / Flexible'`, then the Settings names in order.
    - `all()['preferred_day']['show_if'] === array( 'grooming_frequency' => array( '2', '4', '6', '8' ) )`.
    - `contact_consent` is not required.
    - `MAX_PETS === 5`.
  - **services-test:**
    - Estimate for `{booking_type: both, check_in: 2026-11-01, check_out: 2026-11-04, pets: [{pet_name: Rex, pet_type: dog, dog_size: large, service: nail_trim}, {pet_name: Tom, pet_type: cat}]}` with rates large `40` and other `25`:
      - 3 lines: `'Rex: Boarding, 3 nights'` 120.0, `'Rex: Nail Trimming'` 20.0, `'Tom: Boarding, 3 nights'` 75.0.
      - Total 215.0.
    - The old flat input still gives `'Boarding: 3 nights'`.
  - **settings-test:**
    - `sanitize( array( 'groomers' => " Ana \n\nBen\nana\n" . str_repeat( 'x', 70 ) ) )['groomers']` gives `array( 'Ana', 'Ben', str_repeat( 'x', 60 ) )`.
    - When `groomers` isn't sent, the stored list is kept.
- [ ] **Step 2:** `bash bin/test.sh`. Expected: the new tests fail.
- [ ] **Step 3:** Implement. Existing fixtures in `validator-test`, `rest-test` and `admin-test` will fail now; leave them for Task 2.
- [ ] **Step 4:** `php tests/fields-test.php && php tests/services-test.php && php tests/settings-test.php`. Expected: all pass.
- [ ] **Step 5:** Commit `feat: per-pet fields, groomers setting, estimate for several pets`.

### Task 2: Validator for several pets

**Files:**
- Modify: `includes/Validator.php`
- Test:
  - `tests/validator-test.php`
  - `tests/fixtures.php`: add `paw_valid_params(): array`, a valid one-pet boarding POST in the new shape, used by `validator-test`, `rest-test` and `admin-test`.

**Interfaces:**
- Consumes: `Fields::pet_fields()`, `Fields::MAX_PETS`, `Services::enabled()`, `Services::groomers()`.
- Produces: `Validator::validate( array $params, \DateTimeImmutable $today )` returns `array{columns, details}|WP_Error`.
  - `$params['pets']` is a list of one to five arrays of per-pet values.
  - `details['pets']` holds the cleaned per-pet values for each pet, with files excluded. Booking-level details stay flat.
  - Errors are keyed `pets.N.field_id`. A missing or empty `pets`, or more than 5 pets, gives the error `pets` = "Please check the highlighted fields."
  - Derived columns:
    - `pet_name`: names joined with `' + '`, cut to 60 characters ending in `'…'`.
    - `pet_type`: the first pet's type.
    - `pet_count`: the number of pets.
    - `service`: the comma-joined services of the groomed pets, skipping `none` and empty.
  - `Validator::is_shown( array $field, array $values )` keeps its behaviour. Per-pet fields are checked with `$values = $booking_values + $pet_values`, where the pet's values take priority.
- Rules (spec §3.2):
  - **Grooming:** a pet that isn't a dog gets the error `booking_type` with the dogs-only copy. A dog with no service gets the required error on `pets.N.service`.
  - **Both:** a dog's `service` may be `none`. If no pet has a real service, the error `booking_type` reads "Choose a service for at least one pet, or book Boarding only."
  - **`service`:** must be an enabled id, or `none` when the booking is Both.
  - **`groomer`:** `any` or a name in `Services::groomers()`, else "Please choose one of the options."
  - **`last_groom_date`:** must not be after today: "The last groom date can't be in the future."
  - The existing date rules (stay dates, grooming day) are unchanged and stay booking-level.

- [ ] **Step 1: Tests in validator-test.**
  - One pet valid. Two pets valid; `columns.pet_name === 'Rex + Tom'` and `pet_count === '2'`.
  - Six pets → `fields.pets`.
  - Grooming with a cat → `fields.booking_type` is the dogs-only copy.
  - Both, dog `none` and cat → `fields.booking_type` is the "at least one" copy.
  - Both, dog `nail_trim` and cat → valid; `columns.service === 'nail_trim'`.
  - Two dogs both groomed → `columns.service === 'nail_trim,basic_bath'`.
  - Error on the second pet → key `pets.1.pet_breed`.
  - Unknown groomer → `pets.0.groomer`.
  - Frequency `4` without a day → `pets.0.preferred_day` required. Frequency `once` without a day → valid.
  - Future `last_groom_date` → its error.
  - A long joined name → 60 characters ending in `…`.
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement. Switch the existing fixtures to `paw_valid_params()`.
- [ ] **Step 4:** `bash bin/test.sh`. Expected: everything except `rest-test` and `admin-test` passes. Those two are finished in Tasks 3 and 6. Record this as a ledger note.
- [ ] **Step 5:** Commit `feat: validate up to five pets per booking`.

### Task 3: Storage, files per pet, REST, stats, prefill

**Files:**
- Modify:
  - `includes/Activator.php`: schema adds `pet_count TINYINT UNSIGNED NOT NULL DEFAULT 1`; `service` becomes `VARCHAR(255) NOT NULL DEFAULT ''`.
  - `pawfectly-reservations.php`: `PAW_DB_VERSION` `'4'`.
  - `includes/Reservations.php`
  - `includes/Uploads.php`
  - `includes/Rest.php`
  - `includes/Stats.php`
  - `includes/Accounts.php` (`prefill`)
  - `includes/Shortcode.php` (`account()` prefill shape)
- Test:
  - `tests/activator-test.php`
  - `tests/reservations-test.php`
  - `tests/uploads-test.php`
  - `tests/rest-test.php`
  - `tests/stats-test.php`
  - `tests/accounts-test.php`
  - `tests/form-render-test.php` (prefill)

**Interfaces:**
- `Reservations::pets( array $r ): list<array<string,string|array>>`.
  - Returns `details['pets']`, with each pet's `files` taken from `$r['files']` where `pet === N`.
  - An older row (no `details.pets`) gives one pet built from the values of `Fields::pet_fields()` ids, plus every file.
- `Uploads::store( string $reference, array $checked, int $pet = 0 )`: each stored entry gains `'pet' => $pet`.
- `Rest::submit`:
  - Each pet's files go through `Uploads::check( $files['vaccination_files_N'] )`. Errors are re-keyed to `vaccination_files_N`.
  - Files are stored per pet with `pet` = N. On any failure, every stored file is deleted.
  - `details.pricing` is `Services::estimate( $clean['columns'] + $clean['details'] )`, which includes `pets`.
- `Reservations::for_stats()` also selects `pet_count` and `service`.
- `Stats::overview` changes:
  - Boarding counts and nights add up `(int) pet_count`; a missing value counts as 1.
  - `grooming` counts the groomed pets: the number of non-empty ids in `service`, or 1 for an older grooming row with an empty `service`.
  - `check_ins` and `check_outs` still count bookings.
- `Accounts::prefill( int $user_id ): array`:
  - Booking-level owner and emergency values stay flat.
  - Adds `'pets' => list<array>` with each pet's `pet` and `care` step values from `Reservations::pets( $last )`.
  - Never includes health, files or grooming choices.

- [ ] **Step 1: Tests.**
  - **activator:** the schema contains `pet_count TINYINT UNSIGNED NOT NULL DEFAULT 1` and `service VARCHAR(255) NOT NULL DEFAULT ''`.
  - **reservations:**
    - `pets()` of an old flat row → one pet with `pet_name` and its files.
    - `pets()` of a new row with files tagged `pet` 0 and 1 → each pet gets only its own.
  - **uploads:** a stored entry carries `pet`.
  - **rest:**
    - Submitting two pets with one file each → `pet_count '2'`, and files tagged 0 and 1.
    - Pet 2 missing its files → the error key is `vaccination_files_1`, and nothing is stored.
    - The snapshot has per-pet labels.
    - 6 pets → 400.
  - **stats:**
    - A confirmed 2-pet stay covering tonight → `boarding === 2`.
    - A grooming row with `service 'nail_trim,basic_bath'` today → `grooming === 2`.
  - **accounts:** prefill from a 2-pet booking → `pets` has 2 entries with `pet_name` and `feeding_schedule`, and no `vaccinations_current` or `service`.
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement.
- [ ] **Step 4:** `bash bin/test.sh`. Expected: everything except `admin-test` passes.
- [ ] **Step 5:** Commit `feat: store several pets, files per pet, stats by pet`.

### Task 4: Form template (welcome menu, pet cards, per-pet sections, consent)

**Files:**
- Create:
  - `templates/partials/field.php`: renders one field. Variables: `$f` (field), `$name` (input name), `$dom` (id prefix), `$settings`, `$countries`. It holds today's switch from `form.php` unchanged, apart from using `$name` and `$dom`.
  - `templates/partials/service-menu.php`: the welcome menu.
- Modify:
  - `templates/form.php`
  - `includes/Shortcode.php` (`config()`)
  - `assets/css/form.css`
- Test: `tests/form-render-test.php`

**Interfaces:**
- **Welcome step:** renders `Fields::welcome()` (the booking-type radios, boarding checked), then the service menu.
  - The menu has a Boarding entry: "from {money(min rate)} / night" when any rate is set, else "Price on request".
  - Services follow, grouped under "Single visit" and "Monthly memberships".
  - Each service shows its time, price range and "What's included".
  - The note "Grooming is for dogs. You'll choose a service for each pet later." closes the menu.
- **Per-pet steps** (`pet`, `stay`, `health`, `care`) render their booking-level fields as today.
  - Their per-pet fields go inside `<div class="paw-pets" data-paw-pets="{step_id}">`, which holds one `<fieldset class="paw-pet" data-paw-pet="0">`. Its legend is "Pet 1" on the pet step; elsewhere it's the pet's name, shown in `[data-paw-pet-name]`.
  - Each list is followed by `<template data-paw-pet-template="{step_id}">` with the same markup, using `__N__` for the index and `__NUM__` for the display number.
  - Input names are `pets[0][id]`, and ids `paw-p0-{id}`. The template uses `pets[__N__][id]` and `paw-p__N__-{id}`.
  - Files are named `vaccination_files_0[]`.
- **Pet step:** after the list, `<button type="button" class="paw-add-pet" data-paw-add-pet>` reading "+ Add another pet". Cards 2–5 get `<button type="button" class="paw-link" data-paw-remove-pet>` (template only).
- **Stay step:** the per-pet grooming cards sit inside `data-paw-pets="stay"`.
  - In Both bookings the service list gains an option `none` "No grooming for this pet", shown via `data-paw-both-only`.
  - The `groomer` select uses its options.
- **Waiver step:** renders `contact_consent` (the agree checkbox).
- **`Shortcode::config()`:**
  - Field rules gain `perPet`.
  - Adds `maxPets`, `groomers`, and i18n `petN` "Pet %d", `remove` "Remove", `addPet` "+ Add another pet", plus the two §3.2 messages.
  - The account prefill passes `pets`.
- **CSS:**
  - `.paw-app .paw-pet`: card, 1px line border, radius, padding.
  - `.paw-app .paw-add-pet`: dashed coral outline button, full width on phones.
  - `.paw-app .paw-menu` and `.paw-menu__*`: the welcome menu, matching the existing service cards but display-only, with no radio mark.

- [ ] **Step 1: Render tests.**
  - The welcome step contains `name="booking_type"` with boarding checked, and `data-paw-menu`. Seven service entries plus the Boarding entry appear, followed by the dogs note.
  - `step 2` contains `name="pets[0][pet_name]"`, `data-paw-add-pet`, and `<template data-paw-pet-template="pet">` containing `pets[__N__][pet_name]`.
  - `step 4` contains `name="vaccination_files_0[]"`.
  - The stay step contains `name="pets[0][groomer]"` with `<option value="any"` first, and a `value="none"` service option marked `data-paw-both-only`.
  - `contact_consent` is rendered.
  - Config: `maxPets === 5`, and `fields.pet_name.perPet === true`.
  - Existing render tests are updated to the new names.
- [ ] **Step 2:** Run. Expected: fail.
- [ ] **Step 3:** Implement. The form browser test is expected red until Task 5; record this as a ledger note.
- [ ] **Step 4:** `bash bin/test.sh`. Expected: everything except `admin-test` passes.
- [ ] **Step 5:** Commit `feat: welcome service menu, pet cards and per-pet sections in the form`.

### Task 5: form.js for several pets

**Files:**
- Modify:
  - `assets/js/form.js`
  - `tests/e2e/form-e2e.js`
  - `tests/e2e/build.php`: the config gains groomers `['Ana', 'Ben']`.

**Interfaces:**
- **Pet state:** `pets` is the number of cards. `files` becomes `files[n]`, an array per pet.
- **Helpers:**
  - `value(id, n)`, `setValue(id, v, n)`, `isShown(id, n)` and `problem(id, n)`. Per-pet ids use `n`; booking ids ignore it.
  - `isShown` merges booking values with that pet's values.
- **`addPet()`** clones every `data-paw-pet-template` into its list with N = the current count, then `refresh()`.
- **`removePet(n)`** drops card n in every list, renumbers the later cards (names, ids, `for`, legends, file arrays), then `refresh()`.
- **Pet names:** the name typed on the pet step fills `[data-paw-pet-name]` in the stay, health and care cards. With no name yet, they show "Pet %d".
- **Checks:** `checkStep(step)` checks booking fields once, then the per-pet fields of each card. It also checks the §3.2 rules on the stay step.
- **Server errors:** a key `pets.N.id` or `vaccination_files_N` goes to that field and opens its step.
- **Payload:** `FormData` from the form already carries `pets[N][...]`. Each pet's files are appended as `vaccination_files_N[]`.
- **Draft:** `paw_draft_v2` stores `{ v: flat, pets: [ {...} ] }`. Restoring re-creates the cards first.
- **Prefill:** `ACCOUNT.prefill.pets` creates the cards and fills them.
- **Booking type and grooming cards:**
  - Grooming cards show only for dogs in Grooming or Both bookings. A cat's grooming card is hidden.
  - In Grooming bookings with any pet that isn't a dog, the dogs-only message shows under the booking type, and Next is blocked on the stay step.
- **Estimate:** mirrors `Services::estimate` with per-pet lines.

- [ ] **Step 1: E2E scenario "two pets".**
  - The welcome step shows the menu with 8 entries. The owner picks Boarding + grooming.
  - Pet step: add a pet, so 2 cards. Fill Rex (dog, large) and Tom (cat).
  - Add a third pet, then remove pet 2. Tom's answers are gone and pet 3's are now pet 2's (check `name="pets[1][pet_name]"`).
  - Add pets until there are 5; the add button hides.
  - Reduce back to Rex and Tom, then reload: the draft brings back both cards with their names.
  - Stay step: Rex's grooming card shows and Tom's doesn't.
    - Rex gets `nail_trim`, groomer "Ana", every 4 weeks (the day and time appear), Saturday, morning.
    - The estimate lists "Rex: Boarding, 2 nights", "Rex: Nail Trimming" and "Tom: Boarding, 2 nights".
  - Health step: two sections titled "Rex" and "Tom". Leave Tom without files and press Next: the error shows under Tom's files. Add the files.
  - Care step: both sections.
  - Waiver: tick consent and submit.
  - The sent body has `pets[1][pet_name]` = Tom, `vaccination_files_0[]` and `vaccination_files_1[]`, `pets[0][groomer]` = Ana and `contact_consent` = 1.
  - At 390px: no horizontal scroll.
- [ ] **Step 2: Update existing scenarios to the new names.** Booking type moves to the welcome step; single-pet flows fill `pets[0][...]`.
- [ ] **Step 3:** Run the form e2e. Expected: fail. Implement, then run again. Expected: all pass, with phone and desktop screenshots saved via `PAW_SHOTS`.
- [ ] **Step 4:** `bash bin/test.sh`. Expected: everything except `admin-test` passes.
- [ ] **Step 5:** Commit `feat: add, remove and fill several pets in the booking form`.

### Task 6: Display for several pets, groomers in Settings

**Files:**
- Modify:
  - `templates/partials/answers.php`: booking-level steps, then one "Pet N: {name}" section per pet, grouped by step, each with its own files.
  - `includes/Admin.php`: `csv_rows( array $r ): list<array>` replaces `csv_row`. Columns are the booking keys, then `pet_number` "Pet #", then the per-pet keys. One row per pet; the estimate column is filled on the first row only. `handle_export` writes every row.
  - `includes/Mailer.php`: the summary gets one "Pet N" row per pet ("Rex: Nail Trimming, groomer Ana").
  - `templates/my-bookings.php`: services listed per pet.
  - `includes/Reservations.php`: `service_name()` becomes `service_names( array $r ): string`, the comma-joined names.
  - `includes/StaffRest.php`: the list row `service_name` uses `service_names`, and adds `pet_count`.
  - `assets/js/dashboard.js`
  - `templates/partials/dashboard-settings.php`: a Groomers textarea, one name per line, under Pricing.
  - `includes/Settings.php::page()`: the same textarea in wp-admin.
- Test:
  - `tests/admin-test.php`
  - `tests/mailer-test.php`
  - `tests/my-bookings-test.php`
  - `tests/staff-rest-test.php`
  - `tests/dashboard-render-test.php`
  - `tests/e2e/dashboard-e2e.js`

- [ ] **Step 1: Tests.**
  - **admin:**
    - A 2-pet row → `csv_rows` returns 2 rows with "Pet #" 1 and 2, and the estimate only in row 1.
    - The detail HTML has "Pet 1: Rex" and "Pet 2: Tom", each with its own file link.
    - An old flat row → 1 row, and the detail is as before.
  - **mailer:** a 2-pet booking email lists both pets, with Rex's service and groomer.
  - **my-bookings:** shows "Rex + Tom" and "Nail Trimming".
  - **staff-rest:** the list row has `pet_count` 2.
  - **dashboard-render:** the settings form has `name="groomers"`.
  - **dashboard e2e:** saving the groomers sends the text.
- [ ] **Step 2:** Run. Expected: fail. Implement.
- [ ] **Step 3:** `bash bin/test.sh` and all three e2e suites. Expected: all pass.
- [ ] **Step 4:** Commit `feat: show several pets to staff, owners and in emails; groomers in Settings`.

### Task 7: Docs, version, release prep

- [ ] **Step 1:**
  - `pawfectly-reservations.php`: version 0.4.0.
  - `README.md`: the welcome menu, several pets, groomers, consent, CSV with one row per pet.
  - `CLAUDE.md`: spec and plan paths, plus the per-pet convention ("per-pet fields carry `per_pet`; read pets through `Reservations::pets()`").
- [ ] **Step 2:** Run `bash bin/test.sh`, all three e2e commands and `bin/build-release.sh`. Expected: all green, and a zip with version 0.4.0.
- [ ] **Step 3:** Commit `chore: v0.4.0` and push.
- [ ] **Step 4:** Whole-branch review by a fresh reviewer and its fixes. Then the PR into `main`, the merge, and the Release workflow with tag `v0.4.0`.
