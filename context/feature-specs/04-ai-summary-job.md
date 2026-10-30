# Mergelog, Feature 04: AI Summary Job

## Goal
Turn each stored PR into a category and a short summary, in the background.

## Depends on
Feature 03.

## Tasks
1. `app/Services/SummarizerService.php` with one public method `summarize(PullRequest $pr, array $files = []): array` returning `['category' => ..., 'summary' => ...]`. All provider details (URL, model, headers, request format) stay inside this class. Read config from `config/services.php` (`AI_API_URL`, `AI_MODEL`, `AI_API_KEY`).
2. Prompt rules:
   - Input: PR title, description, changed file names.
   - Output: JSON only: `{"category": "feature|fix|breaking|other", "summary": "<one sentence, max 160 chars>"}`.
   - Tell the model to mark `breaking` only if the PR clearly says so.
3. Parse the response. Strip code fences if present. Validate with Laravel's `Validator` (category in allowed list, summary required and max 255). If invalid, throw an exception so the job retries.
4. `app/Services/GitHubService.php` with `getPullRequestFiles($repo, $number)`. Use the repo token if present. If it fails, the job continues with an empty file list.
5. `SummarizePullRequest` job (`ShouldQueue`): `$tries = 3`, `backoff()` returns `[10, 60, 300]`, `failed()` sets status `failed` and saves the error message. On success: save category, summary, raw response (jsonb), status `drafted`.
6. Turn on the dispatch line in the webhook controller.
7. Run the worker: `php artisan queue:work`.
8. Add `POST /api/change-entries/{id}/retry`: only for `failed` entries, resets to `pending_ai` and dispatches again.

## Acceptance criteria
- [ ] A merged PR becomes `drafted` with a category and summary
- [ ] Bad JSON from the AI causes retries, then `failed` with an error message
- [ ] AI down (timeout or 500) behaves the same way
- [ ] Retry endpoint works only for failed entries
- [ ] Tests use `Http::fake()` for success, bad JSON and failure
- [ ] Switching AI provider only requires editing `SummarizerService` and `.env`

## Interview notes
- Why a queue: AI calls are slow and unreliable.
- Retries with backoff, and what `failed()` does.
- Why validate AI output before trusting it.
- Why the job is safe to run twice.

## Out of scope
Multiple providers, streaming, fine-tuning, token cost tracking.
