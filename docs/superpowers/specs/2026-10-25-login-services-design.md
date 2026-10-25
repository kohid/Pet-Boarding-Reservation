# Pawfectly v0.3: one login page, Admin Panel access, grooming services and pricing (design spec)

- Status: **approved** (the user approved the spec and the service list, and asked for the plan)
- Date: 2026-10-25
- Builds on:
  - `docs/superpowers/specs/2026-10-25-pet-boarding-reservation-design.md` (v0.1)
  - `docs/superpowers/specs/2026-10-25-accounts-dashboard-design.md` (v0.2)
  - Conventions there still apply.

## 1. Goals

1. Give every user one sign-in and sign-up page through a shortcode. Staff and admins use it too, so the "Pawfectly staff: use the staff sign-in" line goes away.
2. Create account gets a confirm-password field. Every password box gets a show/hide button, including on the booking form.
3. WordPress's own dashboard (wp-admin) is open only to:
   - the account whose email is the site's Administration Email Address
   - users with the Developer role

   Those users see an **Admin Panel** item in the existing account dropdown.
4. Settings move into the front-end dashboard. They include an editable price for every service.
5. The booking form offers boarding, grooming, or both, using Pawfectly's grooming services and memberships. It shows prices and an estimated total.
6. Release as v0.3.0.

## 2. Login and access (approved)

| # | Decision |
|---|---|
| 1 | New shortcode `[pawfectly_login]` with Sign in and Create account tabs. When signed out, My Profile, My Bookings, the dashboard and the menu's Sign In / Sign Up all lead to it. If no page uses the shortcode, those screens show the same panel in place. |
| 2 | After sign-in, users go to `redirect_to` if it is a same-site URL, checked with `wp_validate_redirect`. Otherwise users who can manage reservations go to the Dashboard, and everyone else goes to My Bookings. |
| 3 | Every role signs in through `wp_signon`, so login-limit and security plugins still apply. The v0.2 rule that refused staff at the customer sign-in is removed. |
| 4 | `wp-login.php` (plain login and register) redirects to the login page and carries `redirect_to` along. Logout, lost password, reset password, `postpass`, `confirmaction` and `interim-login` keep working on `wp-login.php`. There's no redirect when no login page exists or when the escape constant is set. The filter `paw_redirect_wp_login` can turn it off, for example for a two-factor plugin. |
| 5 | Show/hide toggles go on every password box: login, create account, the booking form's create-account and inline sign-in, and My Profile's password change. A confirm field goes on create account, the booking form's create-account, and My Profile's new password. Mismatches are caught in the browser and again on the server. |
| 6 | A **Developer** role (`developer`, "Developer") with every administrator capability. It is created on activation and on upgrade, but only if no `developer` role already exists. An existing role with that slug is respected as it is. |
| 7 | Only these users reach wp-admin: the user whose email equals `get_option( 'admin_email' )`, and anyone with the `developer` role. Everyone else is sent to the Dashboard (staff) or My Bookings, on `admin_init`. The gate does not apply to `admin-ajax.php`, `admin-post.php` (file downloads), `async-upload.php` (the logo picker), REST, WP-CLI or cron. |
| 8 | Emergency escape: `define( 'PAW_WP_ADMIN_OPEN', true );` in `wp-config.php` turns off both the gate and the `wp-login.php` redirect. |
| 9 | The WordPress toolbar is hidden for anyone without wp-admin access. |
| 10 | Every setting moves into a Settings view in the front-end dashboard. |
| 11 | The Settings view is shown to administrators (`manage_options`), Developers and the admin-email account. Saving goes through a capability-checked REST route that reuses `Settings::sanitize()`. |
| 12 | The wp-admin Settings screen stays as a fallback for the users who can still reach wp-admin. |
| 13 | Dropdown order: Dashboard, My Bookings, My Profile, Settings (dashboard view), **Admin Panel** (wp-admin access only), Log Out. |
| 14 | Release v0.3.0 after review. |

Consequence: staff who used the wp-admin Reservations screen now work in the front-end dashboard. It already has every staff feature.

## 3. Grooming services and pricing

### 3.1 The catalog (from the user's screenshots)

| id | Name (as the user lists them, in this order) | Kind | Time | Small dog (< 25 lbs) | Large dog (25 lbs +) |
|---|---|---|---|---|---|
| `nail_trim` | Nail Trimming | Single visit | Usually under 15 minutes | $15 | $20 |
| `nail_membership` | Nail Membership | Monthly | Come in as often as you like | $15 / month | $20 / month |
| `bath_membership` | Bath Membership | Monthly | Unlimited baths | $59 / month | $59 / month |
| `basic_bath` | Basic Bath | Single visit | — | price on request | price on request |
| `deluxe_bath` | Deluxe Bath | Single visit | About 2 hours | price on request | price on request |
| `basic_haircut` | Basic Haircuts | Single visit | 2–3 hours | price on request | price on request |
| `premium_haircut` | Premium Haircuts | Single visit | About 3 hours | price on request | price on request |

On the form, the services are listed in the order above, which is also the order of the user's list. The detailed descriptions come from the screenshots: "Premium Haircuts" uses the Premium Full-Service Grooming copy, and "Bath Membership" uses the Unlimited Dog Baths copy.

- Nail trim prices come from the membership card, which says its price is the same as a single trim. No other single-visit prices were given, so they start blank.
- A blank price shows as "Price on request" and is left out of the estimate.
- Each service's "What's included" list is copied from the screenshots as the default description.
- Staff can edit each service in Settings:
  - on/off switch
  - name
  - time
  - the two prices
  - description, one bullet per line

### 3.2 Booking flow

- **Pet step:** a new **Dog size** field (`dog_size`: "Under 25 lbs" / "25 lbs & up"). It appears and is required only when the pet type is Dog.
- **Reservation Details step** now opens with **What would you like to book?** (`booking_type`):
  - **Boarding** (default): check-in and check-out, as today.
  - **Grooming**: an appointment date (`appointment_date`) and a preferred drop-off time (`appointment_time`: Morning 8–12 / Afternoon 12–5). Check-in and check-out are hidden.
  - **Boarding + grooming**: check-in and check-out, plus a grooming day, which must fall within the stay.
  - Grooming is for dogs, matching the copy. For cats and other pets, only Boarding is offered, and the server rejects anything else.
- **Service** (`service`) appears for Grooming and for Boarding + grooming:
  - One choice, shown as cards in the catalog order. Each membership card carries a "Monthly" badge.
  - Each card shows the name, time, the price for the chosen dog size, and a collapsible "What's included".
  - Only one service is allowed, because the larger services already include the nail trim and bath, and the membership terms say they "cannot be combined with any other offer or service".
  - Turned-off services are not shown, and the server rejects them.
- **Estimated total**, shown under the service choice and in the confirmation:
  - Boarding: nights × the nightly rate for the pet (small dog, large dog, or cat/other).
  - Plus the service price. Memberships show "/ month".
  - Labelled "Estimate. Payment is taken at Pawfectly; staff confirm the final price."
  - Items priced on request are listed as "price on request".
- **Pricing settings:**
  - boarding nightly rates for small dog, large dog and cat/other (blank by default)
  - each service's prices
  - a currency symbol (default `$`)

### 3.3 Data

- `PAW_DB_VERSION` goes to `'3'`. New columns:
  - `booking_type` varchar(12), default `'boarding'`
  - `appointment_date` DATE NULL
  - `service` varchar(40), default `''`
- Grooming-only bookings store the appointment date in `check_in` and `check_out` too. Lists and sorting keep working, and `booking_type` keeps them out of the "boarding tonight" counts and the nights chart.
- The JSON `details` keeps a snapshot of what the owner saw: service name, dog size, unit prices, nights and estimate, plus the currency. Later price changes don't rewrite old bookings.
- Older rows have no `booking_type` and read as `boarding`.

### 3.4 Where it shows

- **Staff side panel, wp-admin detail, CSV and emails:** booking type, service, appointment and estimate.
- **Dashboard Overview:** a new card for "Grooming today" (appointments dated today).
- **My Bookings:** the service name next to the dates.

## 4. Account dropdown

The user is happy with the current dropdown, so its look stays as it is: avatar header, plain text items, separators.

The only change is a new **Admin Panel** item linking to `admin_url()`. It appears only to the admin-email account and to Developers, the same check that guards wp-admin. It goes after Settings and before the Log Out separator.

## 5. Components

| Unit | Responsibility |
|---|---|
| `Access` (new) | `can_use_wp_admin( WP_User )`, the `admin_init` gate, the toolbar filter, the Developer role. |
| `Login` (new) | The `[pawfectly_login]` shortcode, the shared login/signup panel, redirect targets, and the `wp-login.php` redirect. |
| `Services` (new) | The default catalog, a merge with saved settings, `price( id, size )`, and `estimate( values )`. It is the only place that knows prices. |
| `Fields` | New fields `dog_size`, `booking_type`, `appointment_date`, `appointment_time` and `service` (new field type `service`). `show_if` accepts a list of allowed values. |
| `Validator` | Date rules per booking type; the grooming day within the stay; dogs only; enabled service only. |
| `Reservations` | New columns and the snapshot. Boarding-only filters in `for_stats`. |
| `Settings` | New keys `currency`, `boarding_rates` and `services`, sanitized. |
| `StaffRest` | `GET/PUT /staff/settings` for settings managers. |
| `Dashboard` + `dashboard.js` | Settings view with General and Pricing sections. |
| `AccountRest` | Login for all roles, signup and password change with confirmation. |
| `UserMenu` | Admin Panel item; Settings points to the dashboard view. |
| `form.js`, `account.js` | Show/hide toggles, confirm checks, service cards, the live estimate. |

## 6. Errors and edge cases

- **Price input:** blank, or a number from 0 to 99,999.99 with two decimals. Anything else is rejected with the field named.
- **Service prices** are always recomputed on the server from Settings. The browser's estimate is display only.
- **Turned-off service:** if a service was turned off while someone was filling in the form, the server returns a field error on `service`.
- **Lockout:** if the admin email has no matching user and no Developer exists, nobody reaches wp-admin. The Settings view warns administrators about this, and the escape constant covers it.
- **Logged-out users** opening `/wp-admin` are sent by WordPress to `wp-login.php`, which redirects to the login page with `redirect_to` set. After sign-in, the gate decides.

## 7. Testing

- **Unit tests:**
  - `Access` (the matrix of admin email, developer, admin and staff)
  - `Login` redirects
  - `Services` prices and estimates
  - the new Validator rules
  - settings sanitizing
  - Reservations snapshot and stats exclusion
  - menu items
- **Playwright:**
  - form: booking type switching, dog size, service cards, the estimate, show/hide, confirm mismatch
  - account: the login page tabs, confirm, show/hide, no staff line
  - dashboard: the Settings view saving prices, the Admin Panel item shown or hidden
- **Manual check on real WordPress:** still owed from v0.1/v0.2, and it now matters more because of the wp-admin gate.
