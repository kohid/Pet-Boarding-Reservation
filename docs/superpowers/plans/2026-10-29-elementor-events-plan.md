# Elementor event templates: plan (v0.7)

Spec: `docs/superpowers/specs/2026-10-29-elementor-events-design.md`. Built inline, test-first; each task ends with `bash bin/test.sh` green and a commit.

### Task 1: `EventFields`
- **Files:** `includes/EventFields.php`, `tests/event-fields-test.php`
- **Builds:** `current()`, `value()` (every text field, with formats, sections and session), `link()`, `image()`, `color()`.
- **Tests:** a draft is hidden from visitors; a non-event gives nothing; the coupon code is never returned.

### Task 2: Template split and `[pawfectly_event]`
- **Files:** `templates/partials/event-rows.php`, `templates/partials/event-addcal.php`, `templates/partials/event-closing.php`, `templates/event.php`, `includes/EventShortcode.php`, `assets/css/calendar.css` (plain-list reset, `.paw-event--part`), `tests/event-shortcode-test.php`
- **Builds:** each part, with empty output when there is nothing to show; `id=`; the shortcode enqueues its own assets off event pages.
- **Tests:** the existing event-page tests still pass.

### Task 3: `ElementorTags`
- **Files:** `includes/ElementorTags.php`, `includes/elementor/tags.php`, `tests/elementor-tags-test.php` (with Elementor stubs)
- **Builds:** the group and one named class per tag, in the Text, URL, Image and Colour categories. Loaded only when Elementor Pro is present.

### Task 4: Browser test
- **Files:** `tests/e2e/build-events.php` (an `event-parts.html` page built from the plain parts plus `signup`), `tests/e2e/events-e2e.js`
- **Checks:** the lists inherit the page font; the form works; the `#paw-event-reserve` anchor; 390px width.

### Task 5: Docs, review, release
- **Docs:** README section, CLAUDE.md, version 0.7.0.
- **Verify:** all suites plus `bin/build-release.sh`.
- **Review:** a fresh reviewer, then fixes written test-first.
- **Release:** PR → merge → Release `v0.7.0`.
