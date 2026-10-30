# Mergelog, Feature 03: Webhook Receiver

## Goal
Safely receive GitHub "pull request merged" events and store them once.

## Depends on
Feature 02.

## Tasks
1. Migrations and models: `pull_requests`, `change_entries` (as in `architecture-context.md`). Add enums for category and entry status.
2. Route `POST /api/webhooks/github/{repository}`. No Sanctum middleware.
3. `GitHubWebhookController` (single action):
   1. Read the raw body with `$request->getContent()`.
   2. Compute `'sha256=' . hash_hmac('sha256', $raw, $repository->webhook_secret)`.
   3. Compare with `X-Hub-Signature-256` using `hash_equals`. If different, return 401.
   4. If `X-GitHub-Event` is `ping`, return 200.
   5. Ignore anything except `pull_request` with `action = closed` and `pull_request.merged = true` (return 200).
   6. If a PR with this `X-GitHub-Delivery` id exists, return 200 (duplicate).
   7. Save the PR. Create a `change_entries` row with status `pending_ai`.
   8. Dispatch `SummarizePullRequest` (feature 04). Until it exists, leave this line commented.
   9. Return 202.
4. Test locally with ngrok. Add the webhook in the GitHub repo settings: content type `application/json`, event "Pull requests".

## Acceptance criteria
- [ ] Valid signature and merged PR creates one PR row and one entry
- [ ] Bad or missing signature returns 401 and stores nothing
- [ ] Same delivery sent twice creates only one row
- [ ] Closed-but-not-merged PR and other events store nothing
- [ ] Response time is fast (no AI work in the request)
- [ ] Feature tests cover all four cases above

## Interview notes
- Why the HMAC uses the raw body, and why `hash_equals` (timing-safe).
- Why return 202 and queue the work.
- How the unique delivery id gives idempotency.

## Out of scope
Handling other event types, replaying old events.
