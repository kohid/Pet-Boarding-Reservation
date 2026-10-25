# Pawfectly Pet Boarding Reservation

A WordPress plugin for Pawfectly's boarding reservations. Owners fill in a 7-step form: owner details, pet, stay dates, health and vaccination records, care instructions, emergency contacts and vet, and a signed liability waiver. Staff review, confirm and export reservations in wp-admin.

Requires WordPress 6.0+ and PHP 8.0+. This is v0.1. See `docs/superpowers/specs/2026-10-25-pet-boarding-reservation-design.md` for what is and is not built.

## Install

1. Download `pawfectly-reservations.zip` from the latest GitHub release, then go to **Plugins → Add New → Upload Plugin**. Or copy this folder to `wp-content/plugins/pawfectly-reservations/`.
2. Activate it. Activation creates the `wp_paw_reservations` table, gives administrators the `pawfectly_manage_reservations` capability, and creates the private files folder.
3. Add the form to a page with the shortcode `[pawfectly_reservation_form]`. In Elementor, use a Shortcode widget.

## Settings (Pawfectly → Settings)

- **Business name** and **logo.** The logo shows in a circle on the welcome screen and in emails. Until you choose one, a paw placeholder is shown.
- **Send new reservations to.** One or more staff email addresses.
- **Welcome text** and **liability waiver.** Both default to the wording from the mock-ups.
- **Default country** for the address (United States).
- **Who can manage reservations.** Tick any role, for example a front-desk role. Administrators always can.
- **Private files.** Shows whether vaccination records and signatures are protected from direct web access (see below).

## Reservations (Pawfectly → Reservations)

- The list shows reference, pet, owner, stay and status, with status tabs, a check-in date range and search (reference, name, email, phone, pet name).
- **Bulk actions:** confirm, decline, cancel.
- **Export CSV** downloads the current filter, with every answer in its own column. Values that a spreadsheet could run as a formula are neutralised.
- **Open a reservation** to see every answer, download vaccination records, view the signature (with signing time and IP), change status (optionally emailing the owner), keep staff notes, and see the status history. **Print** gives a clean printout.

Statuses: Pending (new), Confirmed, Declined, Checked in, Completed, Cancelled.

## Emails

- **Owner:** "We've received your reservation request — PAW-XXXXXX", with dates and pet name.
- **Staff:** "New reservation request PAW-XXXXXX: Pet (dates)", with a link to the reservation.
- **Status change** (optional): "Your reservation PAW-XXXXXX is now Confirmed".

Health details and files are never emailed; staff read them in wp-admin. Sending uses `wp_mail`, so use an SMTP plugin if your host's mail is unreliable.

## Private files

Vaccination records and signatures are stored in `wp-content/uploads/pawfectly-private/`. Files are renamed so their original names never reach the disk, and they are only served to signed-in staff through wp-admin.

- **Apache:** the plugin writes an `.htaccess` that denies direct access.
- **Nginx** ignores `.htaccess`. Add this to your site's server block, then reload Nginx:

  ```nginx
  location ~* /wp-content/uploads/pawfectly-private/ { deny all; }
  ```

  Settings shows **Protected** once direct access is blocked.

## Upload size on your server

The form allows up to 3 vaccination files of 10 MB each, but never more than your server accepts. It reads PHP's limits and tells visitors the real numbers. To allow the full 3 × 10 MB, raise these (your host's control panel, `php.ini` or `.user.ini`):

```ini
upload_max_filesize = 10M
post_max_size = 32M
```

On Nginx, also set `client_max_body_size 32m;`. On Apache, check that `LimitRequestBody` is unset or above 32 MB. If the web server turns a submission away as too large, the form says so instead of failing silently.

## How the form protects itself

- Every answer is checked again on the server; the browser is never trusted.
- Files must really be PDF, JPG or PNG (checked from their contents, not their name), at most 3 files of 10 MB each.
- A hidden spam-trap field, 5 submissions per 10 minutes per visitor, and a double-click or retry never creates a second reservation.
- Drafts are saved in the visitor's own browser for 7 days (never files or the signature) and cleared on submit.

## Updates

The plugin updates itself from this repository's GitHub releases, on the normal Plugins screen. This works while the repository is public; a private repository would need a token for downloads.

To publish a release: set the same version in the plugin header and `PAW_VERSION`, commit, then push a tag like `v0.1.0`. You can also run **Actions → Release** with the tag. The workflow checks the version, runs the tests, and attaches `pawfectly-reservations.zip`.

## Development

```bash
bash bin/test.sh                                          # PHP unit tests (no WordPress needed)
php tests/e2e/build.php && node tests/e2e/form-e2e.js     # the whole form in Chromium
bin/build-release.sh                                      # dist/pawfectly-reservations.zip
```

Field definitions live in one place, `includes/Fields.php`. The form template, the browser checks and the server checks all read from it.
