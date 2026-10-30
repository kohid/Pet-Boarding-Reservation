# My Profile: saved pets, booking details and activity (v0.10)

## Goal

The user said: "On my-profile, users can add each pet's details and information, and see their transactions, so booking again isn't difficult to fill in." They asked not to be asked again, so every decision below was made and recorded here.

## What owners get

**My Profile → My Pets**
- **The list:** one card per pet, showing the name, type and breed, with Edit and Remove. **+ Add a pet** adds another; up to 10 pets.
- **The editor:** it has every field the booking form asks per pet, under the booking form's own headings. It opens on the card and uses the same labels and choices:
  - **Pet Information:** name, type (with dog size and "please specify"), breed, age, gender, weight, spayed/neutered, microchipped.
  - **Health:** medical conditions, medications and allergies, each with its details box. "Are all vaccinations up to date?" is asked fresh on every booking, so it is not saved.
  - **Care:** feeding schedule, food preference, dietary notes, behaviour notes, exercise needs.
- **Saving:** only the pet's name is required in the profile. Everything else is optional here; the booking form still checks every field when a booking is sent.
- **Not saved:**
  - Vaccination files are uploaded with each booking, because records expire and they are private files.
  - Grooming choices (service, groomer, last groom date, grooming notes) are made per booking.

**My Profile → Details for bookings**
- Saved once: home address, emergency contact (name, phone, relationship) and vet (name, clinic, phone, authorisation).

**My Profile → Recent activity**
- The last 5 reservations (reference, pets, stay or grooming day, status) and the last 5 event sign-ups (event, day, status), each with a link to **My Bookings**.

## The booking form

- **Filling in:** a signed-in owner's form fills in their name, email and phone, their saved details for bookings, and their saved pets.
- **Who's coming?:** with more than one saved pet, the Pet Information step starts with a "Who's coming?" row of pet chips.
  - **At first:** the pets from their last booking are ticked (all saved pets if none match). The form then shows one pet card for each ticked pet, filled in.
  - **Ticking and unticking** adds or removes that pet's card. Typing in a card never gets overwritten: a pet goes into the first card only if nothing in that card was touched. Unticking a pet whose card was changed, or has files uploaded, asks first.
- **Only empty fields are filled,** so a draft the owner already started wins, as before.
- **Staff:** staff filling in the form for a customer get no prefill, as before.

## Saving from bookings

- **Signed-in owners:** when they send a booking, its pets are saved to their profile, matched by name with any letter case. A new name adds a pet; a known name updates that pet's saved answers with the new non-empty ones. The booking's address, emergency contact and vet update the saved details.
- **"Create an account" on the form:** the new account starts with those pets and details.
- **Owners who haven't saved any pets yet:** My Profile shows the pets from their last booking. Nothing is saved until they save a pet or remove one. Either action saves the list as shown.

## Data

- **Pets:** user meta `paw_pets` holds a list of `{ uid, values }`. `uid` is 12 random hex characters, and `values` holds field id → string, for the fields above only.
- **Details for bookings:** user meta `paw_owner_info` holds field id → string, for the address, emergency and vet fields.
- **Rules:** sanitised with the booking form's own rules:
  - `Validator::read()`; `Validator::check()` on a copy of the field with `required` off, except the pet name.
  - Choices must be one of the field's options.
  - Answers hidden by `show_if` are dropped.
- **One place:** `SavedPets` reads and writes both; nothing else touches the meta.

## Routes (owner, signed in)

- `POST /account/pets` `{ pets: [{ uid?, values }] }`
  - Replaces the list.
  - Errors are keyed `pets.N.field_id` and returned with status 400; nothing is saved.
  - Returns `{ pets }`.
- `POST /account/info` `{ values }`
  - Errors are keyed by field id.
  - Returns `{ values }`.
- Both need sign-in (401 otherwise) and use the REST nonce like the other account routes.
- Staff accounts can use them too; the booking form ignores them for staff, as before.

## Tests

- **Unit, `saved-pets-test.php`:**
  - sanitising: name required, bad choice, hidden answers dropped, max length, the 10-pet limit, uid kept or made
  - seeding from the last booking
  - remembering from a booking: match by name, update, add
  - details for bookings
  - the prefill now uses saved pets and details
- **Unit, `account-rest-test.php`:** the two routes, including sign-in required and errors keyed per pet.
- **Browser, `account-e2e.js`:** add a pet, edit it, a conditional field appears, remove it, save the list, save the details, recent activity is listed, no horizontal scroll at phone width.
- **Browser, `form-e2e.js`:** the "Who's coming?" chips, ticking one adds a filled card, unticking removes it, and nothing is overwritten.

## Out of scope

- Saving vaccination files to the profile.
- Pet photos.
- Payment history. The plugin takes no payments; "transactions" means bookings and sign-ups.

## Review decisions

- **Merging a booking into a saved pet:** the merged answers are checked again as a whole. Follow-ups that the new answers hide are dropped, such as medical details after a "no", or a dog size once the pet is a cat.
- **Clearing an answer:** a booking cannot clear a saved answer, because only non-empty answers are taken. Owners clear answers in My Profile.


## v0.11: staff and the site owner booking for their own pets

The user said: "the website owner has pets too and can book; those bookings are made by the owner for their own pets, and I can still check them all."

- **The choice:** on the booking form, signed-in staff (the site owner included) see "Who is this booking for?":
  - **A customer** (the default, as before): nothing is filled in, and nothing is saved to their account.
  - **My own pets:** their name, email, phone, saved details and saved pets fill in, with the "Who's coming?" chips, exactly as for an owner (`account.selfPrefill`, from `Accounts::prefill()`).
- **Sending:** the form sends `booking_for=me`. `Rest::submit` then links the booking to the staffer (`user_id`) and remembers its pets and details in their My Profile, as for owners. Without it, a staff booking is linked to nobody, as before.
- **Switching back to A customer** takes out what was filled in from their account and not changed since: the details, and the pet cards that still match a saved pet.
- **Drafts** remember the choice. A reloaded draft picks "My own pets" again, so the booking stays theirs. "Start over" goes back to A customer.
- **Checking them all:** these bookings are ordinary reservations, so they show in the staff dashboard with every other booking, and in the staffer's own My Bookings.
