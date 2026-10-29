# Pawfectly Pet Boarding Reservation

A WordPress plugin for Pawfectly's boarding and grooming reservations. Owners fill in a 7-step form: owner details, pet, what they're booking (boarding, grooming, or both) with an estimated price, health and vaccination records, care instructions, emergency contacts and vet, and a signed liability waiver. Staff review, confirm and export reservations in the front-end dashboard.

Requires WordPress 6.0+ and PHP 8.0+. This is v0.4. See `docs/superpowers/specs/` for what is and is not built.

## Install

1. Download `pawfectly-reservations.zip` from the latest GitHub release, then go to **Plugins → Add New → Upload Plugin**. Or copy this folder to `wp-content/plugins/pawfectly-reservations/`.
2. Activate it. Activation creates the `wp_paw_reservations` table, gives administrators the `pawfectly_manage_reservations` capability, and creates the private files folder.
3. Add the form to a page with the shortcode `[pawfectly_reservation_form]`. In Elementor, use a Shortcode widget. The form has a transparent background, so set the page or section background in Elementor.

## Shortcodes

| Shortcode | For | Shows |
|---|---|---|
| `[pawfectly_reservation_form]` | everyone | The reservation form. Signed-out visitors can sign in or create an account on the owner step; signed-in owners get their details filled in. |
| `[pawfectly_login]` | everyone | The one sign-in and create-account page for owners, staff and admins. `?tab=signup` opens Create account. WordPress's own login page sends people here (see below). |
| `[pawfectly_user_menu]` | everyone | The avatar and dropdown for your site header (an Elementor Shortcode widget in the header template). Guests: Sign In / Sign Up. Signed in: Dashboard (staff), My Bookings, My Profile, Settings (admins), Admin Panel (see below), Log Out. `align="left"` opens it to the right. |
| `[pawfectly_my_profile]` | everyone signed in | Name, email, phone and password. Signed out, it shows the login panel in place. |
| `[pawfectly_my_bookings]` | owners | Their reservations, with Cancel while a stay is Pending or Confirmed and check-in is still ahead. |
| `[pawfectly_dashboard]` | staff | The staff dashboard: Overview, Reservations and Events. It fills the browser; `fullscreen="no"` keeps it in the page column, and `view="reservations"` opens that view first. |
| `[pawfectly_calendar]` | everyone | The event calendar: Month, List and Day views, search, a category filter and Add to calendar. `view="list"` opens the list; `category="slug"` shows one category. |
| `[pawfectly_events]` | everyone | Event cards with Upcoming, Happening now, Past and All tabs, nine at a time with Load more. `show="past"` or `category="slug"` if you like. |

**Settings → Shortcodes** lists them all with a Copy button, the page each one is on, and "Create page" (which makes a draft). The account menu finds the pages by itself.

## Owner accounts

- **Accounts are optional.** On the owner step, visitors can tick "Create an account with these details" and choose a password. The account is made once the reservation is saved, and they're signed in. Visitors who already have an account can click "Sign in" right there; the page reloads with their details filled in from their last reservation, never the dates, health answers, files or signature.
- **New accounts confirm their email with a link,** valid for 7 days. Until they do, My Bookings shows only reservations made while signed in. After confirming, it also shows earlier reservations made with that email.
- **Everyone signs in on the login page,** owners, staff and admins alike, with their email or username. It goes through WordPress's own sign-in, so login-limit and security plugins still apply. After signing in, staff land on the Dashboard and owners on My Bookings (or wherever they were headed).
- **Create account** asks for the password twice, and every password box has a Show/Hide button, on the booking form too.
- **Changing email in My Profile:** owners switch at once and confirm the new address. Staff keep their current address until they click the link sent to the new one. The admin-email account changes its email in wp-admin.
- **Forgotten passwords** use WordPress's own reset page.

## Who can open the WordPress dashboard (wp-admin)

Only two kinds of account can open wp-admin, and only they see **Admin Panel** in the account menu:
- the account whose email is the site's **Administration Email Address** (Settings → General)
- users with the **Developer** role (created by the plugin with full administrator rights)

Everyone else, other administrators and staff included, is sent to the front-end Dashboard or My Bookings, and doesn't see the WordPress toolbar. File downloads, the logo picker and background requests keep working.

`/wp-login.php` sends people to the login page. Log out, lost password and reset password still work on wp-login.php. If a two-factor plugin needs wp-login.php, add `add_filter( 'paw_redirect_wp_login', '__return_false' );`.

The Administration Email Address is reserved: nobody can sign up with it or switch their account to it through Pawfectly's pages.

If no account uses the Administration Email Address and there is no Developer, administrators keep wp-admin access so the site is never locked, and the dashboard's Settings view shows a warning. To turn the restriction and the login redirect off completely, put `define( 'PAW_WP_ADMIN_OPEN', true );` in `wp-config.php`.

## Staff dashboard

Put `[pawfectly_dashboard]` on a page, for example `/dashboard/`.
- **Overview:** today's check-ins and check-outs, pets boarding tonight, grooming appointments today, requests to review, and a 14-night chart of pets per night.
- **Reservations:** search, status tabs, a check-in date range, and a side panel with every answer, the vaccination files, the signature, status change (optionally emailing the owner), staff notes, history and delete.
- **Events:** the event list and the Add / Edit editor (see Events below).

- **Export CSV** on the Reservations view downloads the current filter, one row per pet.
- **Settings** (administrators, Developers and the admin-email account): everything below, including prices.

## Events

- **Add Event:** in **Dashboard → Events**. Each event has:
  - an emoji (with a picker), a title and a tagline, a category, and an optional cover photo
  - start and end, venue and address, price per dog (or Free), spots per session, a member coupon code, an optional ticket link, and a highlight note
  - a description
- **Sections:** each one is optional, and its heading is yours to edit. Add, remove, drag or move rows; a section with no rows is hidden.
  - **Sessions:** times and who each one is for
  - **Ticket includes:** `{price}` in the heading shows "$15"
  - **Extra goodies**
  - **Good to know**
  - a closing **Reserve Your Spot** block
- **Duplicate** copies an event as a draft, which is handy for next year's party.
- **Drafts:** only staff see them. Use **Preview**.
- **Event page:** every event has its own page at `/event/<name>/`, laid out top to bottom: date line, emoji, title, tagline, facts, description, tickets, then the sections. It includes search-engine event data and an Add to calendar menu (Google, Outlook, .ics).
- **Ticket buttons:** "Get Tickets" and "Reserve Your Spot" go to the sign-up form at the bottom of the event page. If you sell tickets somewhere else, add a ticket link and both buttons go there instead (the form is then hidden).

## Designing event pages in Elementor

With **Elementor Pro**, build the event page yourself in **Templates → Theme Builder → Single** with the condition **Include → Events → All**. In the template's **Preview Settings**, pick an event so the editor shows real data.

**Dynamic Tags:** click the Dynamic Tags icon on any text, link, image or colour setting and open the **Pawfectly Event** group. Every Add Event field is there except the member coupon code, which never appears anywhere public.

- **Text:**
  - Event title, Tagline, Emoji, Category
  - Start date, Start time, End date, End time. Each has a **Format** setting, like `F j, Y` or `g:i A`.
  - Date line, Day, Time range, Summary line
  - Venue, Address, Venue + address
  - Price (number), Price text, Free badge, Ticket button text
  - Highlight note, Description (text)
  - Spots per session, Spots left (pick the session)
  - Section heading and Section intro (pick the section: Sessions, Ticket includes, Extra goodies, Good to know, Closing call to action). `{price}` is filled in.
- **Links:** Tickets / sign-up link (your ticket link, or the sign-up form on the page), Ticket link, Event page link, Google Calendar, Outlook, Apple / iCal (.ics), Map.
- **Image:** Cover photo.
- **Colour:** Category colour.

**Shortcode:** add a **Shortcode** widget for the parts Elementor can't build from single values:

| Shortcode | Shows |
|---|---|
| `[pawfectly_event part="description"]` | the description, with its paragraphs, bold, lists and links |
| `[pawfectly_event part="sessions"]` | the session rows |
| `[pawfectly_event part="includes"]` | the "ticket includes" rows |
| `[pawfectly_event part="goodies"]` | the goodies |
| `[pawfectly_event part="good_to_know"]` | the good-to-know rows |
| `[pawfectly_event part="signup"]` | the sign-up form. It says "This event has ended" once the event is over, and shows nothing when the event has a ticket link. |
| `[pawfectly_event part="addcal"]` | the Add to calendar menu |

- **Lists:** they are plain and take your page's fonts and colours. Style them with these classes:
  - `.paw-ev-list` (each list; `.paw-ev-list--sessions` etc. for one section)
  - `.paw-ev-row` (each row)
  - `.paw-ev-row__time`, `__name`, `__who` (sessions)
  - `.paw-ev-row__emoji`, `__title`, `__text` (the other sections)
- **Empty parts:** a part with nothing in it shows nothing, so hide its heading with Elementor's display conditions or leave the section out.
- **Linking to the form:** the sign-up form sits in `#paw-event-reserve`, so give your "Get Tickets" button the **Tickets / sign-up link** tag and it jumps to the form (from an events list card it opens the event page at the form). The **Cover photo** tag has its own Fallback image.
- **Other events:** add `id="123"` to show a different event's part.

The plugin's own event page still shows wherever the theme prints the content, for example in Elementor's **Post Content** widget. Use either that or your own design, not both.

## Event sign-ups

- **No account needed.** People pick a session, give their name, email and phone, and add 1–5 dogs (name required; breed and weight optional), then tick that their dogs are vaccinated and friendly. Signed-in owners get their name, email and phone filled in.
- **Paid at the event.** The form shows the total as it changes ("2 × $15 = $30, paid at the event"). Nothing is charged online.
- **Spots per session:** each dog takes one spot. A full session is greyed out and says "Full"; the others show how many spots are left. Leave Spots blank for no limit.
- **Member coupon code:** members who enter the event's code (any letter case) attend free. The code is never shown on the page, in emails or in the CSV.
- **One sign-up per email per event.** A cancelled sign-up frees its spots and its email.
- **After sending:** a thank-you with the reference (`EVT-XXXXXX`), the details and Add to calendar links, plus a confirmation email. Staff get an email with a "See sign-ups" button.
- **Staff:** open a saved event in **Dashboard → Events** to see its **Sign-ups** card: how full each session is, every sign-up with its dogs and notes, a status (Confirmed, Attended, Cancelled) and **Download CSV** (one row per dog). The events list shows how many dogs are signed up.
- **Owners:** **My Bookings** lists their event sign-ups. They can cancel until the event starts; staff get an email.
- **Spam protection:** the same as the booking form (a fresh security token, 5 tries per 10 minutes, a hidden trap field, and double-click protection).
- **Caching:** the page never holds a security token, so it is safe to cache. Spots left update when the cache is purged.
- **If an event page says "page can't be found":** the plugin rebuilds WordPress's link rules by itself when the `/event/` ones go missing (at most once an hour). To fix it right away, open **Settings → Permalinks** in wp-admin and click **Save Changes**, then purge your page cache.
- **Categories:** **Settings → Event categories** sets each one's name and colour. The calendar uses the colours.
- **Calendar page:** put `[pawfectly_calendar]` on a page such as `/event-calendar/`.
- **Events list:** put `[pawfectly_events]` on `/events/`. The event pages use `/event/`, so the two don't clash.

The **Pawfectly staff** role can use the dashboard. Give it to front-desk people under **Users**. You can also tick other roles in **Settings → Who can manage reservations**.

## Booking several pets

One booking holds up to 5 pets. The owner adds pets with "+ Add another pet" and can remove any after the first. Each pet has its own details, vaccination records, health and care answers, and, when groomed, its own service. The booking gets one reference ("Biscuit + Luna"), and the estimate adds up every pet. Staff see a section per pet, and the CSV export has one row per pet.

## The form follows the booking

The welcome screen asks what the owner wants to book (**Boarding**, **Grooming**, or **Boarding + grooming**) and shows the service menu with prices.

| Step | Boarding | Grooming | Boarding + grooming |
|---|---|---|---|
| Health & vaccination records | ✓ | ✓ | ✓ |
| Special care (feeding, exercise) | ✓ | skipped | ✓ |
| Emergency contact and vet | ✓ | vet only | ✓ |
| Waiver | boarding waiver | grooming waiver | both |

Vaccination records are required for grooming too, unless you turn that off in Settings.

## Grooming services, groomers and prices

Grooming is for dogs and cats. For each dog or cat, the owner picks one service, a preferred groomer ("Any / Flexible" or a name from your **Groomers** list in Settings), how often they'd like grooming, a preferred day and time for repeat visits, the last groom date and notes. In Boarding + grooming, a dog or cat can also choose "No grooming for this pet"; other pets (rabbits and so on) just board. Pick-up details: a grooming day, and for grooming-only bookings, a morning or afternoon drop-off. The services:

| Service | Starts at |
|---|---|
| Nail Trimming | $15 small / $20 large; cats: price on request until you set one |
| Nail Membership | $15 / $20 a month |
| Bath Membership | $59 a month |
| Basic Bath, Deluxe Bath, Basic Haircuts, Premium Haircuts | Price on request until you set a price |

In **Dashboard → Settings → Pricing** you can turn each service on or off and edit its name, time, small dog, large dog and cat prices, and "What's included" list. Cats pay the cat price; a blank one shows "Price on request". You can also set nightly boarding rates (small dog, large dog, cat or other) and the currency symbol. A blank price shows "Price on request".

The form shows an estimated total, labelled as an estimate: payment is taken at Pawfectly and staff confirm the final price. Each booking keeps the prices it was made with, so later price changes don't alter it.

## Settings (Dashboard → Settings)

- **Groomers**, one name per line.
- **Grooming waiver**, shown for grooming bookings, and **Require vaccination records for grooming-only bookings** (on by default).
- The waiver step also offers an optional "I consent to be contacted…" checkbox; the answer is saved with the booking.

The general settings (not the prices) are also on **Pawfectly → Settings** in wp-admin, for the accounts that can open it, along with the Shortcodes list and the private-files check.

- **Business name** and **logo.** The logo shows in a circle on the welcome screen and in emails. Until you choose one, a paw placeholder is shown.
- **Send new reservations to.** One or more staff email addresses.
- **Welcome text** and **liability waiver.** Both default to the wording from the mock-ups.
- **Default country** for the address (United States).
- **Who can manage reservations.** Tick any role, for example a front-desk role. Administrators always can.
- **Private files.** Shows whether vaccination records and signatures are protected from direct web access (see below).
- **Starter events.** A switch that fills the calendar with 30 ready-made events, September 2026 to January 2027.
  - **The events:** Pawfectly parties, puppy socials, training classes, adoption days, grooming pop-ups, a fundraiser and more. They are ordinary published events with sessions, prices, spots and member codes, and nothing on them says "demo". They use the Holiday party, Training, Social, Adoption, Grooming and Fundraiser categories, added beside yours.
  - **Turning it off** moves them to the trash, except any that people signed up for. Events you made yourself are never touched.
  - **Turning it on again** adds them back. Any kept for sign-ups are not added twice.

## Reservations (Pawfectly → Reservations)

- The list shows reference, pet, owner, stay and status, with status tabs, a check-in date range and search (reference, name, email, phone, pet name).
- **Bulk actions:** confirm, decline, cancel.
- **Export CSV** downloads the current filter, with every answer in its own column. Values that a spreadsheet could run as a formula are neutralised.
- **Open a reservation** to see every answer, download vaccination records, view the signature (with signing time and IP), change status (optionally emailing the owner), keep staff notes, and see the status history. **Print** gives a clean printout.

Statuses: Pending (new), Confirmed, Declined, Checked in, Completed, Cancelled.

## Emails

- **Owner:** "We've received your reservation request — PAW-XXXXXX", with dates (or the grooming day and time), pet name, service and estimated total.
- **Staff:** "New reservation request PAW-XXXXXX: Pet (dates)", with a link that opens the reservation in the dashboard.
- **Status change** (optional): "Your reservation PAW-XXXXXX is now Confirmed".
- **Event sign-up:** "You're signed up: X-Mas Party — EVT-XXXXXX" to the owner, "New sign-up for X-Mas Party: Name (2 dogs)" to staff, and "Sign-up cancelled for …" to staff when an owner cancels.

Health details and files are never emailed; staff read them in the dashboard. Sending uses `wp_mail`, so use an SMTP plugin if your host's mail is unreliable.

## Private files

Vaccination records and signatures are stored in `wp-content/uploads/pawfectly-private/`. Files are renamed so their original names never reach the disk, and they are only served to signed-in staff through wp-admin. In the dashboard's side panel, JPG and PNG records show as thumbnails (grouped by pet) that open in a lightbox; PDFs open in a new tab.

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

To publish a release: set the same version in the plugin header and `PAW_VERSION`, commit, then push a tag like `v0.3.0`. You can also run **Actions → Release** with the tag. The workflow checks the version, runs the tests, and attaches `pawfectly-reservations.zip`.

## Development

```bash
bash bin/test.sh                                          # PHP unit tests (no WordPress needed)
php tests/e2e/build.php && php tests/e2e/build.php signed-in && node tests/e2e/form-e2e.js   # the form
php tests/e2e/build-account.php && node tests/e2e/account-e2e.js                          # menu, login, profile, bookings
php tests/e2e/build-dashboard.php && node tests/e2e/dashboard-e2e.js                      # staff dashboard and settings
php tests/e2e/build-dashboard.php && node tests/e2e/dashboard-events-e2e.js               # dashboard Events editor
php tests/e2e/build-events.php && node tests/e2e/events-e2e.js                            # calendar, events list, event page, sign-up form, Elementor parts
bin/build-release.sh                                      # dist/pawfectly-reservations.zip
```

Field definitions live in one place, `includes/Fields.php`. The form template, the browser checks and the server checks all read from it.
