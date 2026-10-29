# Pawfectly v0.6: event sign-ups (design spec)

- Status: **approved to build**. The user said "build it all, I'm going to review the overall output", so every open question below was decided by Claude, with the recommended option. They are marked *(decided)* for the user's review.
- Date: 2026-10-29
- Builds on the v0.5 events spec (`2026-10-29-events-calendar-design.md`), whose §2-5 promised this release.

## 1. Goals

1. "Get Tickets" and "Reserve Your Spot" open Pawfectly's own sign-up form on the event page whenever the event has no outside ticket link.
2. An owner picks a session, adds their dogs, optionally enters the members' coupon code, and confirms their dogs meet the park rules. They get a reference and a confirmation email. Payment is taken at the event.
3. Staff see each event's sign-ups in the dashboard, with counts per session, cancel / attended, and a CSV.
4. Owners see and can cancel their event sign-ups in My Bookings.
5. Release as v0.6.0.

## 2. Decisions

| # | Decision |
|---|---|
| 1 | **No account needed** *(decided)*. Name, email and phone are enough. A signed-in owner gets them filled in, plus the names of the dogs from their last booking. Their sign-ups are tied to their account and show in My Bookings. There is no "create an account" box on this form (kept short; the booking form and login page already offer it). |
| 2 | **Where the form lives:** in the event page's closing block (`#paw-event-reserve`), so both buttons scroll to it. An event with a ticket link keeps sending people there, and shows no form. Past events show no form. |
| 3 | **Sessions:** when the event has sessions, the owner picks one (required). They are shown as cards with the time, name and "N spots left" when the event has a limit. A full session is shown but can't be picked. Without sessions, there is nothing to pick. |
| 4 | **Spots** *(decided)*: a new optional event field, **Spots per session** (dogs, 1–500). It applies to each session, or to the whole event when there are none. Blank means no limit. The server checks it when it saves, so two people can't take the last spot. |
| 5 | **Dogs:** 1 to 5 per sign-up. Each dog has a name (required), and a breed and weight (both optional). The total is dogs × the event's price. |
| 6 | **Coupon** *(decided)*: a new optional event field, **Member coupon code** (one code per event, not case-sensitive, 3–30 characters). A matching code makes the sign-up free ($0) and is recorded on it. A wrong code is refused on its field: "That code isn't valid for this event." Free events don't show the code field. |
| 7 | **Agreement** *(decided)*: one required checkbox: "My dogs are up to date on their vaccinations and are friendly with other dogs." The event's Good to Know list is just above it. No uploads (the example says photos can be texted). |
| 8 | **Duplicates:** one active sign-up per email per event. A second try is refused on the email field: "This email is already signed up for this event. Check your inbox for the confirmation." |
| 9 | **Status:** a sign-up is **Confirmed** straight away (the spots were checked). Staff can mark it **Attended** or **Cancelled**, and owners can cancel until the event starts. Cancelling frees its spots. |
| 10 | **Emails:** the owner gets a confirmation, with the reference, event, day and time, session, dogs, total ("Pay at the event", or "Free with member code") and Add to calendar links. Staff get an alert with the same details and a link to the event's sign-ups. |
| 11 | **Protection:** the same as the booking form, as shown below. |

**Protection (decision 11):**
- the REST nonce
- a rate limit (5 sign-ups per 10 minutes per visitor)
- a hidden honeypot field
- a submission id so a double click makes one sign-up

## 3. Data

- **Storage:** sign-ups are private posts of type `paw_ev_signup` (no public URL; `show_ui` false). They are read and written only through `Signups`.
- **Meta:** each sign-up keeps these fields as meta, prefixed `_paw_su_`:

  | Field | Holds |
  |---|---|
  | `event` | the event post id |
  | `session` | the session's index, or `-1` when the event has none |
  | `status` | `confirmed`, `attended` or `cancelled` |
  | `reference` | `EVT-` plus 6 characters from the reference alphabet |
  | `first_name`, `last_name`, `email`, `phone` | the owner's details |
  | `user_id` | the owner's account, or `0` |
  | `dogs` | a list of `{name, breed, weight}` |
  | `dog_count` | the number of dogs |
  | `coupon` | `1` when the member code was used |
  | `total` | a string such as `"30.00"`, or `"0.00"` |
  | `notes` | the owner's notes, up to 500 characters |
  | `created` | the date and time, in UTC |
  | `ip` | the visitor's address |
- **Snapshots:** the sign-up keeps the price and session labels it was made with (`price_each` and `session_label`), so later edits to the event don't change it.
- **New event fields:** `Events` gains `spots` (`int`, `0` = no limit) and `coupon` (`string`). The coupon is never exposed in public markup or routes.

## 4. Public form (event page)

- **Order, top to bottom:**
  1. **Pick your session:** cards.
  2. **Your details:** first name, last name, email, phone.
  3. **Your dogs:** a card per dog, with **+ Add another dog** (up to 5) and Remove on dogs 2–5.
  4. **Member code** (optional).
  5. **Notes** (optional).
  6. The agreement checkbox.
  7. The total line: "2 dogs × $15 = $30, paid at the event". It updates live, and reads "Free" with a code that's accepted when the form is sent.
  8. The **Reserve Your Spot** button.
- **Errors:** they come back keyed by field (`dogs.1.name`), are shown under each field, and the form scrolls to the first.
- **Thank-you:** the thank-you panel replaces the form. It shows the reference, a summary, and Add to calendar.
- **Route:** `POST /pawfectly/v1/events/(?P<id>\d+)/signup` (JSON).
- **Spots left:** they come from the page render. They are rechecked when the form is sent; a full session is refused with "Sorry, that session just filled up. Please pick another."

## 5. Dashboard

- **Events editor:**
  - **Price and tickets** gains **Spots per session** and **Member coupon code**.
  - A **Sign-ups** card for saved events, showing:
    - a count per session: "Session 1: 12 / 20 dogs"
    - a table: reference, name, contact, session, dogs, code, total, status
    - Mark attended / Cancel / Restore buttons
    - **Export CSV**
- **Events list rows** show "N dogs signed up".
- **Staff routes** (capability-checked):

  | Route | Does |
  |---|---|
  | `GET /staff/events/(?P<id>\d+)/signups` | The event's sign-ups. |
  | `POST /staff/signups/(?P<id>\d+)/status` | Changes a sign-up's status (`confirmed`, `attended` or `cancelled`). |
  | `admin-post.php?action=paw_signups_csv&event=ID&_wpnonce=` | The CSV: one row per dog. |

## 6. My Bookings

- **Event sign-ups** card, under reservations: event, day, session, dogs, total, status.
- **Cancel:** shown until the event starts (`POST /account/signups/(?P<id>\d+)/cancel`; the owner's own sign-ups only, by account).

## 7. Code layout

| Unit | Does |
|---|---|
| `Signups` | Registers the post type. Holds `sanitize( array $event, array $in ): array\|WP_Error`, `create()`, `get()`, `for_event()`, `taken( int $event, int $session )`, `spots_left()`, `set_status()`, `for_user()`, `can_cancel()`, `csv_rows()`. |
| `SignupRest` | The public route. |
| Staff and owner routes | In `StaffEvents` and `AccountRest`. |
| `Mailer` | `signup_received()`. |
| `templates/partials/event-signup.php` + `assets/js/signup.js` | The form. Its CSS goes in `calendar.css` under `.paw-event`. |
| Dashboard | Additions in `dashboard-events.php` and `events-admin.js`. |

## 8. Testing

- **Unit:**
  - sanitize: required fields, 1–5 dogs, session required and valid, coupon right/wrong/case, agreement, duplicates, totals
  - spots: full sessions refused, cancelled sign-ups free spots
  - create and get round trip
  - the reference format
  - the route: nonce, rate limit, honeypot, the same submission id returning the first reference
  - emails
  - staff and owner routes: permissions, own sign-ups only, cancel window
  - CSV
- **Browser:**
  - the form on the X-Mas Party page: pick a session, add and remove dogs, live total, coupon, errors, the thank-you panel
  - a full session can't be picked
  - an event with a ticket link shows no form
  - the dashboard Sign-ups card: counts, status, CSV link
  - My Bookings cancel
  - phone width with no sideways scroll
