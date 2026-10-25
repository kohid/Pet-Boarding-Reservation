# CLAUDE.md

Guidance for Claude Code sessions working in this repository.

## Project

**Pet Boarding Reservation**: an app for booking pet boarding stays. Scope, users, and tech stack are still being decided during brainstorming. Record decisions here (or in `docs/superpowers/specs/`) once they're agreed.

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
