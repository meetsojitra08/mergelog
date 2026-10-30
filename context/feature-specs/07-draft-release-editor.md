# Mergelog, Feature 07: Draft Release Editor

## Goal
Group drafted entries into a release, edit them, and preview the changelog.

## Depends on
Feature 06.

## Tasks
Backend:
1. Migrations and models: `releases`, `release_entries` (unique `change_entry_id`). Enum for release status.
2. `POST /api/repositories/{id}/releases` body `{version, title?, entry_ids[]}`. Only `drafted` entries of this repo that are not already in a release. Create the release with status `draft`.
3. `GET /api/releases/{id}` returns the release with its entries and PR info.
4. `PATCH /api/releases/{id}` updates version and title, and can add or remove entries. Block all edits when the release is published.
5. Validate the version string (for example `v1.2.0`), unique per repo.

Frontend:
1. On the entries page: checkboxes on drafted entries, a version input, and a "Create draft release" button. After creation, go to `/releases/:id`.
2. Page `/releases/:id`: version and title fields, entries grouped in this order: Breaking, Features, Fixes, Other. Each entry has an editable summary, a category dropdown, and a remove button.
3. A live preview panel that renders the grouped changelog from the current state.
4. Save button.

## Acceptance criteria
- [ ] Can create a draft from selected entries
- [ ] An entry cannot be in two releases
- [ ] Edits and removals save correctly
- [ ] Preview matches the data
- [ ] Duplicate version for the same repo returns a validation error

## Interview notes
- Why `release_entries` has a unique `change_entry_id`.
- How grouping works (done in the frontend from a flat list).

## Out of scope
Drag and drop ordering, release templates, markdown editor.
