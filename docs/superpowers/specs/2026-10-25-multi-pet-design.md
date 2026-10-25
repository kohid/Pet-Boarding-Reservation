# Pawfectly v0.4: services up front, several pets per booking, preferred groomer (design spec)

- Status: **approved**.
  - The user approved recommendations 1–11 and chose groomer option (a).
  - §3.3 (a shorter form for grooming-only bookings) was approved later in conversation.
- Date: 2026-10-25
- Builds on:
  - `2026-10-25-pet-boarding-reservation-design.md` (v0.1)
  - `2026-10-25-accounts-dashboard-design.md` (v0.2)
  - `2026-10-25-login-services-design.md` (v0.3)
  - Their conventions still apply.

## 1. Goals

1. The first screen shows what Pawfectly offers. The owner chooses Boarding, Grooming, or Boarding + grooming there, with the service menu and prices in view.
2. One booking can hold up to 5 pets. An owner with two dogs fills in one form, gets one reference, and pays one estimate.
3. Each pet booked for grooming can name a preferred Pawfectly groomer. The business manages the list of groomers in Settings.
4. Each groomed pet can also give:
   - how often they want grooming
   - a preferred day and time for repeat visits
   - the last groom date
   - grooming notes
5. An optional checkbox records that the owner agrees to be contacted.
6. Release as v0.4.0.

## 2. Decisions (approved)

| # | Decision |
|---|---|
| 1 | The welcome screen asks "What would you like to book?" (`booking_type`: Boarding / Grooming / Boarding + grooming). It shows the service menu below: name, time, price range, monthly tag, "What's included". The menu is for looking only; each pet picks its own service later. `booking_type` moves off the Reservation Details step. |
| 2 | "+ Add another pet" allows up to **5 pets**. Pets after the first can be removed. |
| 3 | **Asked for each pet:** pet details, dog size, health and vaccination answers and files, feeding and care, and the grooming choices. **Asked once:** owner, stay dates, grooming day and drop-off time, emergency contact, vet and waiver. |
| 4 | One booking holds every pet: one row, one reference. Staff see "Biscuit + Luna" on one reservation. |
| 5 | The estimate adds up every pet: nights × that pet's rate (by dog size, or cat/other), plus each pet's service. |
| 6 | Settings gets a **Groomers** list, one name per line. Each groomed pet picks "Any / Flexible" (the default) or a name. |
| 7 | Each groomed pet also has these fields: |
|   | • **Weeks between grooms:** One time only / Every 2 weeks / Every 4 weeks / Every 6 weeks / Every 8 weeks. Required. |
|   | • **Preferred day:** Monday to Saturday. Shown and required only for a repeat choice. |
|   | • **Preferred time:** Morning / Afternoon. Shown and required only for a repeat choice. |
|   | • **Last groom date:** optional. Must not be in the future. |
|   | • **Grooming notes:** optional. |
|   | Staff see the requested schedule. No future bookings are created automatically. |
| 8 | An optional contact-consent checkbox on the waiver step: "I consent to be contacted by Pawfectly via phone, email, or text to confirm and remind me about these appointments. Message & data rates may apply." It is stored with the booking. |
| 9 | A signed-in owner's form is filled in from their last booking. That covers every pet's details and care answers, but never health answers, files, dates or grooming choices. |
| 10 | Older bookings still work. They are read as a booking with one pet. |
| 11 | Release v0.4.0 after review, through the PR, merge and Release workflow as before. |

## 3. Booking flow

| Step | Content |
|---|---|
| 0 Welcome | Logo, title, intro, then **What would you like to book?** (Boarding preselected). Below it, the **service menu** (§3.1). |
| 1 Owner | As today, plus the account block. |
| 2 Pets | One card per pet, labelled "Pet 1", "Pet 2" and so on, with a **Remove** link on cards 2–5. Each card holds the pet fields: name, type (and "please specify"), dog size, breed, age, gender, weight, spayed, microchipped. **+ Add another pet** goes below the cards and is hidden at 5 pets. |
| 3 Reservation Details | Booking-level fields: check-in/out (Boarding, Both), grooming day (Grooming, Both), drop-off time (Grooming). Then a **Grooming** card per pet, shown for Grooming and Both and titled with the pet's name. Each card has the pet's service choice and the §2-7 fields. Then special requests and the estimate. |
| 4 Health | One section per pet, titled with the pet's name: vaccinations current (with the existing warning), vaccination files (1–3 for each pet), and the medical, medication and allergy questions. |
| 5 Care | One section per pet: feeding, food preference, diet, behaviour, exercise. |
| 6 Emergency & vet | Once, as today. |
| 7 Waiver | Waiver, agreement, the new contact-consent checkbox, signature, date. |

### 3.1 Service menu on the welcome screen

- The menu is a list of the enabled services in catalog order. Each entry shows its name, time and price range ("$15–$20", "$59 / month", or "Price on request"), with a collapsible "What's included".
- Services are grouped under "Single visit" and "Monthly memberships".
- A line under the menu says: "Grooming is for dogs. You'll choose a service for each pet later."
- A Boarding entry is listed first, with nightly rates if they are set ("from $30 / night") and "Price on request" otherwise.

### 3.2 Grooming rules with several pets

- **Grooming:** every pet must be a dog, and every pet needs a service. If any pet is not a dog, the error says: "Grooming is for dogs only. Choose Boarding + grooming to board your other pets."
- **Boarding + grooming:** a dog's grooming card adds a first choice, **No grooming for this pet**. Cats and other pets show no grooming card; they board only. At least one pet must have a service, otherwise the error is "Choose a service for at least one pet, or book Boarding only."
- **Boarding:** there are no grooming cards.
- A turned-off service or an unknown groomer name is refused by the server.

### 3.3 The form follows the booking type (approved addendum)

| Step | Boarding | Grooming | Boarding + grooming |
|---|---|---|---|
| Health & Vaccination Records | yes | **yes** | yes |
| Special Care Instructions | yes | **skipped** | yes |
| Emergency Contacts & Veterinarian | yes | **vet only**: vet name, clinic, phone and authorization; no emergency contact | yes |
| Liability Waiver | boarding waiver | **grooming waiver** | both waivers |

- **Fields:**
  - Care fields and the three emergency-contact fields show only for Boarding and Boarding + grooming.
  - A step with no field shown is skipped. The progress bar counts only the steps shown.
  - For grooming bookings, the vet step's title reads "Veterinarian".
- **Settings:**
  - `grooming_waiver`: an editable text with a default draft covering:
    - matted coats and shave-downs
    - senior and health-compromised pets
    - nicks and clipper irritation
    - behaviour and the right to stop a groom
    - the same vet-care authorization as boarding
  - `grooming_needs_vaccines`: on by default. When off, vaccination files are optional for grooming-only bookings.
  - "Vaccinations up to date" is still required either way.
- **Stored waiver:** each booking keeps the waiver text(s) it was signed with: the grooming waiver for grooming, both for Boarding + grooming.

## 4. Data

- **One row per booking.** `PAW_DB_VERSION` becomes `'4'`.
- **Column changes:**
  - New `pet_count TINYINT UNSIGNED NOT NULL DEFAULT 1`.
  - `pet_name` holds every name joined with " + ", cut to 60 characters with "…".
  - `pet_type` holds the first pet's type.
  - `service` becomes `VARCHAR(255)` and holds the comma-separated service ids of every groomed pet, for example "nail_trim,basic_bath".
- **`details.pets`** is a list with one entry per pet, each holding every per-pet answer (pet, health, care and grooming fields) keyed by field id. Booking-level answers stay flat in `details`, as today.
- **`files`:** each stored file gains `pet` (its 0-based pet index). Download links and the answers view group files by pet.
- **Estimate snapshot.** `details.pricing` lines get labels such as "Biscuit: Boarding, 3 nights" and "Biscuit: Nail Trimming".
- **`Reservations::pets( array $r ): array`** returns `details.pets`. For an older row it returns one pet built from the flat answers and files.

## 5. Form mechanics

- **Fields.php**
  - Per-pet fields carry `'per_pet' => true`.
  - The form names them `pets[N][field_id]`, and files as `vaccination_files_N[]`.
  - `show_if` inside a pet refers to that pet's own values (`pet_type`, `grooming_frequency`, `service`), and to booking-level values such as `booking_type`.
- **New fields**
  - Per pet: `groomer`, `grooming_frequency`, `preferred_day`, `preferred_time`, `last_groom_date`, `grooming_notes`.
  - Booking level: `contact_consent`.
  - `service` becomes per pet.
  - `booking_type` moves to step 0.
- **form.js**
  - Pet cards are cloned from a `<template>` holding pet 1's markup. Clones renumber names, ids and labels.
  - Add, remove and renumber keep the draft, errors and the estimate in step.
  - The pet's name, as typed, appears in the Health, Care and Grooming section titles.
- **Validator**
  - It runs the field checks for each pet, then the cross-field rules.
  - Errors come back keyed `pets.N.field_id` (or `vaccination_files_N`), so the form can put each message under the right field and go to the right step.
- **Limits:** up to 3 files per pet, and the whole request stays within the existing total size limit. The size message names the total.

## 6. Where it shows

- **Staff side panel and wp-admin detail:** booking-level answers, then one section per pet with its answers and files, grouped by step.
- **CSV:** one row per pet. Booking columns repeat on each row, followed by "Pet #" and that pet's answers. The estimated total appears on the first row of each booking only.
- **Emails:**
  - The summary lists every pet with its service and groomer.
  - The estimate covers all pets.
  - The staff subject reads "PAW-XXXXXX: Biscuit + Luna (dates)".
- **My Bookings and the staff list:** all pet names, plus each pet's service where it has one.
- **Dashboard Overview:**
  - "Pets boarding tonight" and the 14-night chart count pets, not bookings, using `pet_count`.
  - "Grooming today" counts groomed pets.
- **Settings (dashboard and wp-admin):** a **Groomers** textarea, one name per line, kept in the order typed. Blank lines and duplicates are dropped, and each name is at most 60 characters.

## 7. Errors and edge cases

- More than 5 pets, or a per-pet value posted for a pet that doesn't exist, is refused with "Please check the highlighted fields."
- A groomer removed from Settings after the form loaded is refused on that pet's `groomer` field.
- A pet card removed in the browser takes its files with it, and the remaining pets keep their own files.
- Stored bookings never change when the groomer list or prices change. The snapshot keeps the names and prices.

## 8. Testing

- **Unit tests:**
  - Fields: per-pet flags, new fields, step order
  - Validator: up to 5 pets, rules from §3.2, the repeat-schedule fields, error keys
  - `Reservations::pets` for old and new rows
  - estimate for several pets
  - groomer settings sanitizing
  - REST: multi-pet submit with files for each pet
  - CSV: one row per pet
  - emails
  - Stats: counts by pet
  - prefill: every pet
- **Playwright:**
  - Welcome: the choice and the service menu.
  - Pets: add, remove and renumber; the 5-pet cap; the draft keeps every pet.
  - Grooming cards follow the booking type and pet type. A cat only boards. The repeat fields appear when a schedule is chosen.
  - Health and care sections per pet, with files for each pet.
  - Estimate for 2 pets. A full 2-pet booking reaches the thank-you screen.
  - Phone width with no horizontal scroll.
  - Dashboard: the side panel shows both pets; Settings saves the groomer list.
- **Manual check on real WordPress:** still owed.
