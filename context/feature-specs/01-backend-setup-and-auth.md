# Mergelog, Feature 01: Backend Setup and Auth

## Goal
A running Laravel API connected to PostgreSQL, with token login.

## Depends on
Nothing. This is the first step.

## Tasks
1. Create the project: `composer create-project laravel/laravel backend`.
2. Create a PostgreSQL database and set the `DB_*` values in `.env`. Set `QUEUE_CONNECTION=database`.
3. Run `php artisan install:api` (installs Sanctum and the `routes/api.php` file).
4. Add the `HasApiTokens` trait to the `User` model.
5. Create the queue tables: `php artisan queue:table`, `php artisan migrate`.
6. Build `AuthController` with `register`, `login`, `logout`, `me`. Use Form Requests for validation.
7. Configure CORS to allow only `FRONTEND_URL`.
8. Add a login rate limit (for example 5 per minute).
9. Create `.env.example`.

## API contract
- `POST /api/register` body `{name, email, password, password_confirmation}` returns `{token, user}` (201)
- `POST /api/login` body `{email, password}` returns `{token, user}` (200), 422 on wrong credentials
- `POST /api/logout` (token) returns 204
- `GET /api/me` (token) returns `{id, name, email}`

## Acceptance criteria
- [ ] `php artisan migrate` works on PostgreSQL
- [ ] Register and login return a token
- [ ] `GET /api/me` works with the token and returns 401 without it
- [ ] Wrong password returns a validation error, not a server error
- [ ] Postman collection or saved requests created

## Interview notes
- Why Sanctum tokens instead of cookies for a separate React app.
- Why password hashing is automatic in Laravel.

## Out of scope
Email verification, password reset, social login.
