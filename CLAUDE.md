# CLAUDE.md

Guidance for Claude Code sessions working in this repository.

## Project

**Pawfectly Pet Boarding Reservation**: a WordPress plugin (PHP 8.0+, WordPress 6.0+, vanilla JS) modeled on kohid/Sprint-Booking, with a Metronic 8-style look in Pawfectly's brand colors.
- Specs: `docs/superpowers/specs/2026-10-25-pet-boarding-reservation-design.md` (v0.1), `docs/superpowers/specs/2026-10-25-accounts-dashboard-design.md` (v0.2)
- Plans: `docs/superpowers/plans/2026-10-25-pet-boarding-reservation.md`, `docs/superpowers/plans/2026-10-25-accounts-dashboard-plan.md`

Conventions:
- Namespace `Pawfectly\`, with `includes/Foo.php` loaded automatically.
- Prefixes: `paw_` for options, transients, the table, and nonces; `paw-` for CSS and handles. Text domain `pawfectly-reservations`.
- Front-end CSS is scoped under `.paw-app` (form), `.paw-um` / `.paw-acct` (account pages), `.paw-dash` (dashboard); admin CSS under `.paw-admin`. Resets use `:where()`; component selectors carry the root prefix so theme rules can't override them.
- Staff data only over capability-checked REST routes (`StaffRest`); owner routes in `AccountRest`.
- Every field is defined once, in `includes/Fields.php`.

## Commands

- Unit tests: `bash bin/test.sh`
- Browser tests (set `PAW_SHOTS=dir` to save screenshots):
  - `php tests/e2e/build.php && php tests/e2e/build.php signed-in && node tests/e2e/form-e2e.js`
  - `php tests/e2e/build-account.php && node tests/e2e/account-e2e.js`
  - `php tests/e2e/build-dashboard.php && node tests/e2e/dashboard-e2e.js`
- Release zip: `bin/build-release.sh`

## Required skills: use them in every session

Every session in this repo works together with these four skills. Invoke them with the `Skill` tool at the stage where each applies. Don't skip one because a task looks small.

| Skill | When to use it |
|-------|----------------|
| `superpowers:brainstorming` | Before any creative or feature work. Clarify intent, propose approaches, get the design approved. |
| `feature-dev:feature-dev` | To drive feature work end to end: discovery, codebase exploration, clarifying questions, architecture options, implementation, quality review, summary. |
| `anthropic-skills:fullstack-developer` | For backend, API, database, and full-stack architecture decisions and code. |
| `frontend-design:frontend-design` | For any UI work: new screens, components, layout, typography, and visual direction. |

### Session workflow

1. **Brainstorm first.** Start with `superpowers:brainstorming`. Classify the task (spike, bounded, or architectural) and get approval before writing any code.
2. **Run the feature-dev phases.** Use `feature-dev:feature-dev` to structure the work. Ask every clarifying question before designing, and wait for approval before implementing.
3. **Build with the domain skills.**
   - Server, data, or API work: `anthropic-skills:fullstack-developer`.
   - Anything the user sees: `frontend-design:frontend-design`.
   - Features that span both: use both.
4. **Review before finishing.** Run the feature-dev quality-review phase, check that the work is verified, then summarize what changed.

## Documentation

- Write all design notes, specs, and plans as Markdown (`.md`) files in the repo.
- Specs: `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md`
- Plans: `docs/superpowers/plans/YYYY-MM-DD-<topic>-plan.md`
- Keep this file up to date as the stack, commands, and conventions are settled.
