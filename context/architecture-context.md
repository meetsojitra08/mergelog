# Mergelog: Architecture Context

## Big picture
```
GitHub --webhook--> Laravel API --> PostgreSQL
                        |
                   queue job --> AI service (summarize)
                        |
React + TypeScript + Tailwind <--REST/JSON--> Laravel API
```
Monorepo with two folders: `backend/` (Laravel API only, no Blade) and `frontend/` (React app).

## Request flows

### 1. Webhook flow (the important one)
1. GitHub POSTs to `/api/webhooks/github/{repository}`.
2. Read the raw body. Compute `hash_hmac('sha256', rawBody, repo.webhook_secret)`.
3. Compare with the `X-Hub-Signature-256` header using `hash_equals`. Mismatch: return 401.
4. Check `X-GitHub-Event` is `pull_request`, `action` is `closed`, and `pull_request.merged` is true. Otherwise return 200 and do nothing.
5. Insert the PR using the `X-GitHub-Delivery` id (unique). If it already exists, return 200 and stop.
6. Create a `change_entries` row with status `pending_ai`, dispatch `SummarizePullRequest`.
7. Return 202 immediately. No AI work happens in the request.

### 2. AI job flow
1. Load the PR. Optionally fetch changed file names from GitHub (failure here is allowed, continue without).
2. Call `SummarizerService::summarize()`. It returns an array with `category` and `summary`.
3. Validate: category must be one of the allowed values, summary must be non-empty and short.
4. Save category, summary, raw response (jsonb), status `drafted`.
5. On error: retry (3 tries, backoff 10s, 60s, 300s). After the last try the `failed()` method sets status `failed` and stores the error message.

### 3. Review and publish flow
1. User selects `drafted` entries and creates a draft release with a version.
2. User edits summaries and categories.
3. Publish sets status `published`, sets `published_at`, creates a unique slug, locks edits.
4. Optional: call the GitHub Releases API. If it fails, the release stays published and the error is saved.

## Database (PostgreSQL)
- `users`: Laravel default
- `repositories`: id, user_id, full_name (owner/repo), webhook_secret (encrypted), github_token (encrypted, nullable), timestamps
- `pull_requests`: id, repository_id, github_pr_number, title, body, author, html_url, merged_at, github_delivery_id (unique), timestamps. Unique on (repository_id, github_pr_number).
- `change_entries`: id, pull_request_id, category, summary, status, ai_raw_response (jsonb, nullable), error_message (nullable), timestamps
- `releases`: id, repository_id, version, status, slug (unique, nullable until published), published_at, github_release_url (nullable), publish_error (nullable), timestamps
- `release_entries`: id, release_id, change_entry_id (unique, so an entry belongs to one release only)

Allowed values:
- category: `feature`, `fix`, `breaking`, `other`
- entry status: `pending_ai`, `drafted`, `failed`
- release status: `draft`, `published`

## API endpoints
Auth (public): `POST /api/register`, `POST /api/login`
Auth (token): `POST /api/logout`, `GET /api/me`
Repositories: `GET /api/repositories`, `POST /api/repositories`, `GET /api/repositories/{id}`, `DELETE /api/repositories/{id}`
Entries: `GET /api/repositories/{id}/change-entries?status=`, `PATCH /api/change-entries/{id}`, `POST /api/change-entries/{id}/retry`
Releases: `POST /api/repositories/{id}/releases`, `GET /api/releases/{id}`, `PATCH /api/releases/{id}`, `POST /api/releases/{id}/publish`
Public: `POST /api/webhooks/github/{repository}` (signature auth only), `GET /api/public/changelog/{slug}`

Rule: every authenticated query is scoped to the logged-in user, for example `$request->user()->repositories()->findOrFail($id)`.

## Environment variables
Backend: `APP_URL`, `FRONTEND_URL` (for CORS), `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `QUEUE_CONNECTION=database`, `AI_API_URL`, `AI_MODEL`, `AI_API_KEY`
Frontend: `VITE_API_URL`
Never commit `.env`. Commit `.env.example`.

## Folder structure
```
mergelog/
├── backend/
│   ├── app/Http/Controllers/
│   ├── app/Http/Requests/
│   ├── app/Http/Resources/
│   ├── app/Jobs/SummarizePullRequest.php
│   ├── app/Models/
│   ├── app/Services/SummarizerService.php
│   ├── app/Services/GitHubService.php
│   ├── database/migrations/
│   ├── routes/api.php
│   └── tests/
└── frontend/
    └── src/
        ├── api.ts
        ├── types.ts
        ├── pages/
        ├── components/
        └── App.tsx
```

## Key decisions and why (interview answers)
- **Laravel API only:** clean separation, same Laravel skills, React handles UI.
- **Sanctum tokens:** simpler than cookie SPA auth (no CSRF and cookie domain setup).
- **Database queue:** no Redis to install, enough for this project, easy to explain.
- **Unique delivery id:** GitHub retries webhooks, so duplicates must be safe.
- **Raw body for HMAC:** the signature is computed on exact bytes, so never re-encode the JSON.
- **AI behind one service class:** swap providers by changing one file.
- **Human approval:** AI output can be wrong, so nothing is published automatically.
- **jsonb for raw AI response:** helps debugging bad output.
- **Encrypted secrets:** use Laravel's `encrypted` cast for webhook_secret and github_token.

## Security checklist
- Signature check with `hash_equals` on the raw body
- Secrets encrypted in the database, never returned in list responses (secret shown only on the repo detail)
- Every query scoped to the owner
- Validation through Form Requests
- CORS limited to `FRONTEND_URL`
- Rate limit login route
