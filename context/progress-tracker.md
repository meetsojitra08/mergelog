# Mergelog: Progress Tracker

Update this file after finishing each feature. Work in order. Do not start a feature until the one before it is done.

## Status key
`[ ]` not started, `[~]` in progress, `[x]` done

## Features
- [x] 01 Backend setup and auth (`context/feature-specs/01-backend-setup-and-auth.md`)
- [x] 02 Repositories (`context/feature-specs/02-repositories.md`)
- [x] 03 Webhook receiver (`context/feature-specs/03-webhook-receiver.md`)
- [ ] 04 AI summary job (`context/feature-specs/04-ai-summary-job.md`)
- [ ] 05 Frontend setup, login, repositories page (`context/feature-specs/05-frontend-setup-login-repos.md`)
- [ ] 06 Change entries page (`context/feature-specs/06-change-entries-page.md`)
- [ ] 07 Draft release editor (`context/feature-specs/07-draft-release-editor.md`)
- [ ] 08 Publish and public changelog (`context/feature-specs/08-publish-and-public-changelog.md`)
- [ ] 09 Testing and polish (`context/feature-specs/09-testing-and-polish.md`)

## Current focus
Feature: 03 complete
Next action: wait for direction before starting Feature 04 (AI summary job).

## Notes per feature
- Feature 01: Laravel 13.39 API with Sanctum 4.3 token authentication, PostgreSQL, and the database queue driver.
- Feature 01: Added register, login, logout, and current-user endpoints; login is limited to five attempts per minute per IP and normalized email.
- Feature 01: Added a Postman collection. The local `.env` stays ignored and contains no committed credentials.
- Feature 01 verification: auth tests passed with 9 tests and 50 assertions; the full backend suite passed with 42 tests and 186 assertions. PostgreSQL migration status confirms the users, queue, and Sanctum token tables are applied; Feature 03's pull request and change entry migrations remain pending.
- Feature 02: Added repository CRUD endpoints, per-user uniqueness, owner-scoped lookups, and encrypted webhook secret/GitHub token storage.
- Feature 02: Repository list responses omit secrets and tokens; detail/create responses include the webhook URL and secret, never the GitHub token.
- Feature 02: Repository list counts report pending and drafted entries from their pull requests; failed entries are excluded from both counts.
- Feature 02 verification: focused tests passed with 11 tests and 72 assertions; full backend suite passed with 42 tests and 186 assertions. Pint and diff checks passed.
- Feature 03: Added an unauthenticated, repository-secret-authenticated GitHub webhook endpoint, pull request and change entry schema/models, backed enums, payload validation, and duplicate-safe persistence. AI dispatch remains out of scope until Feature 04.
- Feature 03 verification: 19 focused tests passed with 59 assertions, covering a signature over formatted raw JSON, valid and invalid signatures, duplicate deliveries, closed-but-not-merged PRs, ping, and no queued AI work. The full suite passed with 43 tests and 190 assertions; Pint and diff checks passed.

## Decisions log
| Date | Decision | Why |
|------|----------|-----|
| | Laravel API only, React frontend | Clean separation, same Laravel skills |
| | Sanctum tokens | Simpler than cookie SPA auth |
| | Database queue driver | No Redis needed |
| | TypeScript on frontend | Stronger resume signal, catches typos |
| 2026-10-31 | Login limit is five attempts per minute per IP and normalized email | Combines account and network limits for login abuse control |
| 2026-10-31 | Auth responses include only user id, name, and email | Keeps password and token storage details out of API user data |
| 2026-10-31 | CORS allows the configured frontend origin without credentials | The frontend authenticates with bearer tokens, not cookies |
| 2026-10-31 | Repository entry counts initially return zero until change entries exist | Feature 02 defines the list fields; Feature 03 will introduce the underlying records |
| 2026-11-01 | Repository list counts query pending and drafted entries through their pull requests | The API returns the current counts after Feature 03 introduced change entries |
| 2026-10-31 | GitHub webhook signatures are checked against the routed repository's decrypted secret before payload handling | Prevents a repository ID alone or another repository's secret from authenticating an event |
| 2026-10-31 | Pull request insertion and its pending change entry happen in one transaction, with unique delivery and repository/PR indexes | Retries and concurrent deliveries cannot create duplicate records or a PR without its entry |

## Known issues / ideas for later
- (empty)

## Final checklist before showing recruiters
- [ ] Full demo flow works end to end
- [ ] README with architecture diagram and setup steps
- [ ] Demo video recorded
- [ ] Tests pass
- [ ] `.env.example` is complete, no secrets committed
- [ ] Deployed (optional) or runs with one setup guide
- [ ] Resume bullets written from what was actually built
