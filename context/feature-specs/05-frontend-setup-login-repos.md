# Mergelog, Feature 05: Frontend Setup, Login and Repositories Page

## Goal
A React + TypeScript app that logs in and lists/adds repositories. This proves the full round trip (CORS, token, API) early.

## Depends on
Features 01 and 02.

## Tasks
1. Create the app: `npm create vite@latest frontend -- --template react-ts`.
2. Install and configure Tailwind CSS. Install `react-router-dom`.
3. Create `src/types.ts` with `User`, `Repository`, `Category`, `EntryStatus`, `ChangeEntry`, `Release`.
4. Create `src/api.ts`: a small typed wrapper around `fetch`. It reads `VITE_API_URL`, adds the `Authorization: Bearer` token, parses JSON, and throws a readable error on non-2xx responses.
5. Auth: store the token in `localStorage`. Create a simple `AuthContext` (token, user, login, logout) and a `ProtectedRoute` that redirects to `/login`.
6. Build the shared components: `Navbar`, `Loading`, `ErrorMessage`, `EmptyState`, `CopyButton`.
7. Pages: `Login` (and a small Register), `Repos` (list, add form, show webhook URL and secret with copy buttons after creating).
8. Routes: `/login`, `/repos`.

## Acceptance criteria
- [ ] Login works against the Laravel API and the token persists after refresh
- [ ] Visiting `/repos` without a token redirects to `/login`
- [ ] Repos list loads, add works, delete works
- [ ] Webhook URL and secret are shown with working Copy buttons
- [ ] Loading, error and empty states exist
- [ ] No `any` in the code and `npm run build` passes

## Interview notes
- Why the token is in localStorage and the trade-off (simple, but readable by injected scripts, so no unsafe HTML rendering).
- Why all `fetch` calls live in one file.

## Out of scope
Redux, React Query, refresh tokens, dark mode.
