# Mergelog: Code Standards

## General
- Simple and readable beats clever.
- One responsibility per function, class, and component.
- Small commits with clear messages, for example `feat: add webhook signature check`.
- No dead code, no commented-out code, no stray `console.log` or `dd()`.

## Backend (Laravel / PHP)
- Follow PSR-12 and Laravel conventions. Format with Laravel Pint.
- Controllers stay thin: validate, call a model or service, return a resource.
- Validation lives in Form Request classes.
- Business logic (AI call, GitHub calls) lives in `app/Services`.
- Return JSON through API Resources. Never return a model directly with hidden secrets.
- Use PHP backed enums (or class constants) for `category`, entry `status`, and release `status`. Values must match `architecture-context.md` exactly.
- Use `encrypted` casts for `webhook_secret` and `github_token`.
- Scope every query to the logged-in user.
- Naming: models singular (`PullRequest`), tables plural snake_case (`pull_requests`), controllers `RepositoryController`, jobs as verbs (`SummarizePullRequest`).
- Migrations: one migration per table, with foreign keys and the unique indexes from the architecture doc.
- Jobs: set `$tries`, `backoff()`, and a `failed()` method. Jobs must be safe to run twice.
- HTTP status codes: 200/201 success, 202 accepted (webhook), 401 bad signature or not logged in, 403 not allowed, 404 not found, 422 validation error.
- Error responses are JSON in the Laravel default shape.

## Frontend (React / TypeScript)
- `strict: true` in tsconfig. Do not use `any`. Use `unknown` and narrow if needed.
- All API data types live in `src/types.ts`. Union types for category and status.
- All network calls live in `src/api.ts`. Components never call `fetch` directly.
- Function components and hooks only. State with `useState` and `useEffect`. No Redux.
- One component per file, PascalCase file names. Pages in `src/pages`, reusable parts in `src/components`.
- Styling with Tailwind utility classes only. No custom CSS unless unavoidable.
- Every page handles three states: loading, error, empty.
- Keep components short. If a component passes about 150 lines, split it.

## Testing
- Backend: PHPUnit or Pest feature tests for the webhook (valid signature, bad signature, duplicate delivery, non-merged event) and the AI job (success, bad JSON, failure).
- Fake external calls with `Http::fake()` and `Queue::fake()`.
- Frontend: manual testing is enough for the MVP. Add tests later only if time allows.

## Git
- `main` is always working. Use short-lived feature branches named like `feature/webhook-receiver`.
- `.env`, `node_modules`, and `vendor` are never committed.
