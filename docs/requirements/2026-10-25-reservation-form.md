# Pawfectly — Pet Boarding Reservation: requirements (discovery)

Status: **discovery**. These are the requirements as given, before clarifying questions and design. They are not yet approved as a spec.

## What was asked

- A WordPress plugin, **Pet Boarding Reservation**, built the same way as [kohid/Sprint-Booking](https://github.com/kohid/Sprint-Booking).
- Components and visual design based on [Metronic 8 demo1](https://github.com/kohid/Metronic8/tree/main/html/theme/demo1/src).
- Business name: **Pawfectly**.
- Brand colors:
  - Primary `#FF7F50` (coral)
  - Secondary `#30AFFF` (sky blue), `#BC0202` (red), `#2C3947` (ink), `#3B7597` (steel blue), `#06D6A0` (mint), `#0F42C0` (royal blue)
- Form content comes from the screenshots the user supplied (summarized below).

## Form flow (from screenshots)

The form is one page with one section per screen. A bottom pill bar has a ← button, a progress bar, and a → button. The page background is a warm cream, sections sit in white cards, and fields use underlined inputs. Radio choices are option cards, yes/no questions are paired buttons, and the accent color is coral.

### 0. Welcome
- Logo in a circle (the screenshot shows a placeholder "Fet Daidy" logo; this will be replaced with Pawfectly's).
- Title: "Pet Boarding Reservation".
- Intro: "We're excited to care for your furry family member! Please provide the information below so we can ensure a safe, comfortable and happy stay."

### 1. Owner Information
| Field | Type | Required |
|---|---|---|
| First Name | text | yes |
| Last Name | text | yes |
| Email Address | email | yes |
| Phone Number | tel | yes |
| Home Address: Street | text | yes |
| Home Address: City/Suburb | text | no (no asterisk) |
| Home Address: State | text | yes |
| Home Address: Zip/Post Code | text | yes |
| Home Address: Country | select, default "United States" | yes |

### 2. Pet Information
| Field | Type | Required |
|---|---|---|
| Pet's Name | text | yes |
| Pet Type | radio: Dog / Cat / Other | yes |
| Breed | text | yes |
| Age | text (e.g. "3 years") | yes |
| Gender | radio: Male / Female | yes |
| Weight | text (e.g. "25 lbs") | yes |
| Is your pet spayed/neutered? | yes/no | yes |
| Is your pet microchipped? | yes/no | yes |

### 3. Reservation Details
| Field | Type | Required |
|---|---|---|
| Check-In Date | date (DD/MM/YYYY + calendar) | yes |
| Check-Out Date | date (DD/MM/YYYY + calendar) | yes |
| Special Requests or Notes | textarea ("Any specific preferences for your pet's stay?") | no |

### 4. Health & Vaccination Records
| Field | Type | Required |
|---|---|---|
| Are all vaccinations up to date? ("All pets must have current vaccinations to board with us") | yes/no | yes |
| Upload Vaccination Records (PDF or image files) | file | yes |
| Does your pet have any medical conditions? | yes/no | yes |
| Is your pet currently taking any medications? | yes/no | yes |
| Does your pet have any allergies? | yes/no | yes |

### 5. Special Care Instructions
| Field | Type | Required |
|---|---|---|
| Feeding Schedule ("Please describe feeding times and portion sizes") | textarea | yes |
| Food Preference | radio: "I will provide my pet's food" / "Please use facility food" | yes |
| Dietary Restrictions or Preferences (e.g. grain-free, no chicken) | textarea | no |
| Behavioral Notes (gets along with other pets, anxious tendencies, favorite toys…) | textarea | no |
| Exercise Needs ("How much exercise does your pet need daily?") | textarea | no |

### 6. Emergency Contacts & Veterinarian
| Field | Type | Required |
|---|---|---|
| Emergency Contact Name ("Someone we can reach if we cannot contact you") | text | yes |
| Emergency Contact Phone | tel | yes |
| Relationship to Owner (e.g. spouse, friend, family member) | text | yes |
| Veterinarian Name | text | yes |
| Veterinary Clinic Name | text | yes |
| Veterinarian Phone Number | tel | yes |
| Do you authorize us to seek veterinary care if needed? (explanatory note) | yes/no | yes |

### 7. Liability Waiver & Agreement
- Waiver text, shown as given:
  > I understand that boarding my pet involves inherent risks. I certify that my pet is in good health and has not been exposed to any contagious diseases within the last 30 days. I agree to hold harmless the boarding facility, its owners, employees, and agents from any and all liability for injury, illness, or death of my pet, or for damage to personal property, except in cases of proven negligence or intentional misconduct. I authorize the facility to seek emergency veterinary care if needed and agree to be responsible for all associated costs. I understand that every effort will be made to contact me before treatment, but in an emergency, care will be provided immediately. I certify that all information provided in this form is accurate and complete.

| Field | Type | Required |
|---|---|---|
| Agreement: "I have read and agree to the above terms and conditions" | checkbox | yes |
| Owner Signature ("Please sign below to confirm your agreement") | drawn signature pad | yes |
| Date | date, defaults to today | yes |

- A **Submit** button (labelled "Submit (Preview)" in the mock-up).

## Reference architecture (from Sprint-Booking)

- Main plugin file with a header, constants, and a PSR-4-style autoloader (`Namespace\Foo` → `includes/Foo.php`). Hooks are wired on `plugins_loaded`.
- `Activator` creates and upgrades a custom table with `dbDelta`, using a DB-version option.
- The form is rendered from a shortcode and a `templates/` file, with assets in `assets/css` and `assets/js` (vanilla JS, vendored flatpickr).
- Submissions go through a REST route with server-side validation that never trusts the browser.
- wp-admin has a menu, a bookings list, settings (Options API), roles and capabilities, and email notifications.
- CSS is scoped under an app class, with Metronic 8 tokens re-implemented as CSS variables. No Metronic files are bundled.
- Tests are plain `php tests/*.php` scripts with small WordPress stubs, plus Playwright e2e tests and a GitHub release workflow.
