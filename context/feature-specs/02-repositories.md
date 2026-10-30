# Mergelog, Feature 02: Repositories

## Goal
A logged-in user can add GitHub repos and get a webhook URL and secret for each.

## Depends on
Feature 01.

## Tasks
1. Migration and model `Repository`: `user_id`, `full_name`, `webhook_secret`, `github_token` (nullable). Add `encrypted` casts for the secret and token.
2. On create, generate a random secret: `Str::random(40)`.
3. `RepositoryController` with `index`, `store`, `show`, `destroy`. Always scope with `$request->user()->repositories()`.
4. `RepositoryResource`. The `index` response must NOT include the secret or token. The `show` response includes `webhook_url` and `webhook_secret`. Never return `github_token`.
5. Validate `full_name` with the pattern `owner/repo`. Unique per user.

## API contract
- `GET /api/repositories` returns a list of `{id, full_name, pending_count, drafted_count, created_at}`
- `POST /api/repositories` body `{full_name, github_token?}` returns the created repo with `webhook_url` and `webhook_secret` (201)
- `GET /api/repositories/{id}` returns `{id, full_name, webhook_url, webhook_secret}`
- `DELETE /api/repositories/{id}` returns 204

`webhook_url` format: `{APP_URL}/api/webhooks/github/{id}`

## Acceptance criteria
- [ ] User can create, list, view and delete their own repos
- [ ] User cannot see or delete another user's repo (404)
- [ ] Secret and token are stored encrypted (check in the database)
- [ ] Invalid `full_name` returns 422

## Interview notes
- Why secrets are encrypted at rest.
- Why list responses leave out secrets.

## Out of scope
Auto-registering the webhook on GitHub through the API. The user adds it manually in GitHub settings.
