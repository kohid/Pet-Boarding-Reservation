# Pawfectly Pet Boarding Reservation

A WordPress plugin for Pawfectly's boarding reservations. Owners fill in a 7-step form: owner details, pet, stay dates, health and vaccination records, care instructions, emergency contacts and vet, and a signed liability waiver. Staff review, confirm and export reservations in wp-admin.

Requires WordPress 6.0+ and PHP 8.0+. This is v0.2. See `docs/superpowers/specs/` for what is and is not built.

## Install

1. Download `pawfectly-reservations.zip` from the latest GitHub release, then go to **Plugins → Add New → Upload Plugin**. Or copy this folder to `wp-content/plugins/pawfectly-reservations/`.
2. Activate it. Activation creates the `wp_paw_reservations` table, gives administrators the `pawfectly_manage_reservations` capability, and creates the private files folder.
3. Add the form to a page with the shortcode `[pawfectly_reservation_form]`. In Elementor, use a Shortcode widget. The form has a transparent background, so set the page or section background in Elementor.

## Shortcodes

| Shortcode | For | Shows |
|---|---|---|
| `[pawfectly_reservation_form]` | everyone | The reservation form. Signed-out visitors can sign in or create an account on the owner step; signed-in owners get their details filled in. |
| `[pawfectly_user_menu]` | everyone | The avatar and dropdown for your site header (an Elementor Shortcode widget in the header template). Guests: Sign In / Sign Up. Signed in: Dashboard (staff), My Bookings, My Profile, Settings (admins), Log Out. `align="left"` opens it to the right. |
| `[pawfectly_my_profile]` | owners | Sign in / Create account (link to `…#signup` to open the second tab), then name, email, phone and password. |
| `[pawfectly_my_bookings]` | owners | Their reservations, with Cancel while a stay is Pending or Confirmed and check-in is still ahead. |
| `[pawfectly_dashboard]` | staff | The staff dashboard: Overview and Reservations. It fills the browser; `fullscreen="no"` keeps it in the page column, and `view="reservations"` opens that view first. |

**Settings → Shortcodes** lists them all with a Copy button, the page each one is on, and "Create page" (which makes a draft). The account menu finds the pages by itself.

## Owner accounts

- **Accounts are optional.** On the owner step, visitors can tick "Create an account with these details" and choose a password. The account is made once the reservation is saved, and they're signed in. Visitors who already have an account can click "Sign in" right there; the page reloads with their details filled in from their last reservation, never the dates, health answers, files or signature.
- **New accounts confirm their email with a link,** valid for 7 days. Until they do, My Bookings shows only reservations made while signed in. After confirming, it also shows earlier reservations made with that email.
- **Staff and administrators** can't sign in on the owner pages. They use the normal WordPress login, so any login protection you have still applies.
- **Forgotten passwords** use WordPress's own reset page.

## Staff dashboard

Put `[pawfectly_dashboard]` on a page, for example `/dashboard/`.
- **Overview:** today's check-ins and check-outs, pets boarding tonight, requests to review, and a 14-night chart of pets per night.
- **Reservations:** search, status tabs, a check-in date range, and a side panel with every answer, the vaccination files, the signature, status change (optionally emailing the owner), staff notes, history and delete.

The **Pawfectly staff** role can use the dashboard without any other wp-admin access. Give it to front-desk people under **Users**. You can also tick other roles in **Settings → Who can manage reservations**.

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
php tests/e2e/build.php && php tests/e2e/build.php signed-in && node tests/e2e/form-e2e.js   # the form
php tests/e2e/build-account.php && node tests/e2e/account-e2e.js                          # menu, profile, bookings
php tests/e2e/build-dashboard.php && node tests/e2e/dashboard-e2e.js                      # staff dashboard
bin/build-release.sh                                      # dist/pawfectly-reservations.zip
```

Field definitions live in one place, `includes/Fields.php`. The form template, the browser checks and the server checks all read from it.
