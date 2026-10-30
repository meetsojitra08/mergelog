# Mergelog: UI Context

Frontend: React + TypeScript + Vite + Tailwind CSS + React Router. Plain, clean, professional. No UI library.

## Design principles
- Simple layout, lots of white space, readable text.
- Status is always visible through colored badges.
- Every action gives feedback (loading state, success, error).
- Works on desktop first, usable on mobile.

## Visual style (Tailwind)
- Page background: `bg-gray-50`. Cards: `bg-white rounded-lg shadow-sm border border-gray-200 p-6`.
- Text: `text-gray-900` for headings, `text-gray-600` for secondary.
- Primary button: `bg-indigo-600 hover:bg-indigo-700 text-white rounded-md px-4 py-2`.
- Secondary button: `border border-gray-300 text-gray-700 hover:bg-gray-50`.
- Danger button: `bg-red-600 hover:bg-red-700 text-white`.
- Inputs: `border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-indigo-500`.
- Container: `max-w-5xl mx-auto px-4`.

## Badge colors
Entry status:
- `pending_ai`: yellow (`bg-yellow-100 text-yellow-800`)
- `drafted`: green (`bg-green-100 text-green-800`)
- `failed`: red (`bg-red-100 text-red-800`)

Category:
- `feature`: blue
- `fix`: orange
- `breaking`: red
- `other`: gray

Release status: `draft` gray, `published` green.

## Layout
- Top navbar: "Mergelog" on the left (also the browser tab title), "Repositories" link, user email and Logout on the right.
- Public changelog page has no navbar, just a centered document.
- Protected routes redirect to `/login` when there is no token.

## Pages

### 1. Login (`/login`)
Centered card with email and password, submit button, error message. Link to register.

### 2. Repositories (`/repos`)
- List of repo cards: full name, number of pending and drafted entries, "Open" button.
- "Add repository" form: `owner/repo`, optional GitHub token.
- After adding, show the webhook URL and secret with Copy buttons and 3 short setup instructions.

### 3. Change entries (`/repos/:id/entries`)
- Header with repo name and the webhook URL (copy).
- Filter tabs: All, Pending, Drafted, Failed.
- Table or list: PR number and title (link to GitHub), author, category badge, summary, status badge.
- Row actions: Edit (inline summary and category), Retry (only for failed).
- Checkbox per drafted entry and a "Create draft release" button with a version input.

### 4. Draft release editor (`/releases/:id`)
- Version and title fields.
- Entries grouped by category in this order: Breaking, Features, Fixes, Other.
- Each entry: editable summary, category dropdown, remove button.
- Preview panel with the changelog as it will look.
- Buttons: Save, Publish (with confirm dialog), checkbox "Also create GitHub Release".
- When published: fields are read-only and the public link is shown.

### 5. Public changelog (`/changelog/:slug`)
Clean document: repo name, version, date, grouped sections with bullet summaries and PR links. No login required.

## Shared components
`Navbar`, `ProtectedRoute`, `StatusBadge`, `CategoryBadge`, `CopyButton`, `Loading`, `ErrorMessage`, `EmptyState`.

## States every page needs
- Loading: simple "Loading..." text or spinner.
- Error: red message box with the API error text.
- Empty: friendly text and the next action (for example "No entries yet. Merge a PR to see it here.").

## Accessibility basics
Labels on all inputs, buttons are real `<button>` elements, visible focus rings, color is never the only status indicator (badges have text).
