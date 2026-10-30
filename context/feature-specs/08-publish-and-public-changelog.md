# Mergelog, Feature 08: Publish and Public Changelog

## Goal
Lock a release, give it a public page, and optionally create a GitHub Release.

## Depends on
Feature 07.

## Tasks
Backend:
1. `POST /api/releases/{id}/publish` body `{create_github_release: boolean}`:
   1. Only allowed when status is `draft` and the release has at least one entry.
   2. Inside a database transaction: set status `published`, set `published_at`, generate a unique `slug` (for example `repo-name-v1-2-0` plus a short random suffix).
   3. If `create_github_release` is true and the repo has a token, call the GitHub Releases API (`POST /repos/{owner}/{repo}/releases`) with `tag_name = version`, `name = title or version`, and a markdown body built from the grouped entries.
   4. If the GitHub call fails, keep the release published, save the message in `publish_error`, and return it in the response.
   5. Save `github_release_url` on success.
2. Build the markdown with a small `ChangelogFormatter` class (used for both the GitHub body and the public page data), so there is one place that groups and formats.
3. `GET /api/public/changelog/{slug}` (no auth) returns repo name, version, title, date, and grouped entries with PR links. Only published releases.

Frontend:
1. Publish button with a confirm dialog and the "Also create GitHub Release" checkbox.
2. After publishing: fields become read-only, show the public link with a copy button, and show the GitHub release link or the publish error.
3. Public page `/changelog/:slug` with no navbar, a clean centered layout, grouped sections, PR links.

## Acceptance criteria
- [ ] Publishing locks the release (edits return an error)
- [ ] Public page works without login and returns 404 for drafts
- [ ] GitHub Release is created on a real test repo
- [ ] GitHub failure does not break publishing and the error is shown
- [ ] Publishing twice is blocked

## Interview notes
- Why a transaction.
- Why one formatter class is used for both outputs.
- Why a GitHub failure does not roll back publishing.

## Out of scope
Custom domains, RSS feed, email subscribers, editing after publish.
