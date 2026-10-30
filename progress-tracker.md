# Mergelog: Progress Tracker

Update this file after finishing each feature. Work in order. Do not start a feature until the one before it is done.

## Status key
`[ ]` not started, `[~]` in progress, `[x]` done

## Features
- [x] 01 Backend setup and auth (`context/feature-specs/01-backend-setup-and-auth.md`)
- [ ] 02 Repositories (`context/feature-specs/02-repositories.md`)
- [ ] 03 Webhook receiver (`context/feature-specs/03-webhook-receiver.md`)
- [ ] 04 AI summary job (`context/feature-specs/04-ai-summary-job.md`)
- [ ] 05 Frontend setup, login, repositories page (`context/feature-specs/05-frontend-setup-login-repos.md`)
- [ ] 06 Change entries page (`context/feature-specs/06-change-entries-page.md`)
- [ ] 07 Draft release editor (`context/feature-specs/07-draft-release-editor.md`)
- [ ] 08 Publish and public changelog (`context/feature-specs/08-publish-and-public-changelog.md`)
- [ ] 09 Testing and polish (`context/feature-specs/09-testing-and-polish.md`)

## Current focus
Feature: 01 complete
Next action: wait for direction before starting Feature 02 (Repositories).

## Notes per feature
- Feature 01: Laravel 13.39 API with Sanctum 4.3 token authentication, PostgreSQL, and the database queue driver.
- Feature 01: Added register, login, logout, and current-user endpoints; login is limited to five attempts per minute per IP and normalized email.
- Feature 01: Added a Postman collection. The local `.env` stays ignored and contains no committed credentials.
- Feature 01 verification: all migrations are applied; the full suite passed with 11 tests and 52 assertions.

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
