# Mergelog, Feature 09: Testing and Polish

## Goal
Make the project reliable and presentable.

## Depends on
Features 01 to 08.

## Tasks
1. Backend tests (PHPUnit or Pest), at minimum:
   - Webhook: valid signature, bad signature, duplicate delivery, non-merged PR, ping event
   - AI job: success, invalid JSON, provider failure, `failed()` state
   - Publish: success, locked edits, GitHub failure handled
   - Ownership: user A cannot access user B's data
2. Use `Http::fake()` and `Queue::fake()`. Run with `php artisan test`.
3. Run Laravel Pint for formatting.
4. Seeder with a demo user and a few sample entries for quick demos.
5. README (root), titled "Mergelog":
   - What it is and a screenshot
   - Architecture diagram (from `architecture-context.md`)
   - Setup steps for backend and frontend
   - How to connect a GitHub webhook with ngrok
   - Environment variables table
   - Design decisions and trade-offs
   - Demo video link
6. Record a 2 to 3 minute demo video following the script in `project-overview.md`.
7. Frontend polish: consistent spacing, all loading/error/empty states, no console errors.
8. Optional: deploy (for example a small VPS or a free-tier host) and add the live link.

## Acceptance criteria
- [ ] All tests pass
- [ ] A new person can follow the README and run the project
- [ ] `.env.example` complete, no secrets in git history
- [ ] Demo video recorded
- [ ] Resume bullets match what was actually built

## Interview notes
Prepare short answers for every item in "Interview talking points" in `project-overview.md`, plus: what you would improve next (GitHub App install, Redis queues, team accounts) and why you did not do it in the MVP.
