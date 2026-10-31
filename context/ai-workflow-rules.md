# Mergelog: AI Workflow Rules

These rules apply to any AI coding assistant working on this project. Read this file first, every session.

## Before writing any code
1. Read `project-overview.md`, `architecture-context.md`, `code-standards.md`, and `ui-context.md`.
2. Open `progress-tracker.md` and find the current feature.
3. Read only the matching file in `feature-specs/`. Work on one feature spec at a time.
4. If something is unclear or missing in the specs, ask. Do not guess.

## Core principle
The owner must be able to understand and defend every line in an interview.
Always choose the simplest solution that works over more features or advanced technology.

## Rules
1. **Keep it simple.** No design patterns, packages, or abstractions unless the spec asks for them.
2. **Reuse what exists.** Use the technologies already in the stack. Do not rewrite working code in a new style or tool.
3. **Small steps.** Make changes in small pieces. Refactor incrementally, never rewrite large areas at once.
4. **Explain decisions.** For each important decision, give a short explanation in plain, simple language: what was chosen, and why.
5. **No unexplained large changes.** If a change touches many files, explain the plan first.
6. **No new dependencies** without asking and giving a one-line reason.
7. **Stay in scope.** Do not add features that are in "Out of scope" in `project-overview.md`.
8. **Follow the contracts.** API paths, JSON shapes, table names and enum values in `architecture-context.md` are the source of truth. If you must change one, update the doc in the same change.
9. **Never hardcode secrets.** Use `.env`, and keep `.env.example` updated.
10. **Test the risky parts.** Webhook signature, duplicate deliveries, and the AI job must have tests.

## After finishing a feature
1. Run the app and the tests.
2. Tick the checklist in `progress-tracker.md` and add short notes.
3. Add any decision made to the "Decisions log" in `progress-tracker.md`.
4. Suggest a small, clear commit message.
5. Give a short "how to explain this in an interview" summary (3 to 5 sentences).

## Response style
- Plain language, short sentences.
- Show the plan, then the code, then how to test it.
- Point out risks or trade-offs honestly. If something is a weak design, say so.
