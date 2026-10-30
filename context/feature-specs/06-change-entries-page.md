# Mergelog, Feature 06: Change Entries Page

## Goal
See every merged PR and its AI result, fix mistakes, and retry failures.

## Depends on
Features 04 and 05.

## Tasks
Backend:
1. `GET /api/repositories/{id}/change-entries?status=` returns entries with their PR (number, title, author, url) ordered by newest first. Optional status filter.
2. `PATCH /api/change-entries/{id}` body `{summary?, category?}`. Validate values. Only the repo owner can edit. Reject edits to entries in a published release.

Frontend:
1. Page `/repos/:id/entries` with the filter tabs (All, Pending, Drafted, Failed).
2. List rows with PR link, author, `CategoryBadge`, summary, `StatusBadge`.
3. Inline edit for summary and category with Save and Cancel.
4. Retry button for failed entries (calls the retry endpoint).
5. A "Refresh" button to reload the list. (Polling is optional, keep it manual for the MVP.)
6. Show the webhook URL at the top with a copy button.

## Acceptance criteria
- [ ] Entries show with correct badges after merging a PR
- [ ] Filters work
- [ ] Editing a summary or category saves and updates the row
- [ ] Failed entries show the error message and a working Retry
- [ ] Another user's entries cannot be read or edited

## Interview notes
- Why humans can edit AI output.
- How ownership is enforced in the query.

## Out of scope
Websocket live updates, bulk edit, search.
