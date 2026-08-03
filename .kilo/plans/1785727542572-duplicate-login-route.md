# Plan: Duplicate login page at `/userLogin`

## Goal
Add an alternative login entry point at `/userLogin` that behaves identically to `/login`, backed by a new Blade view.

## Changes

### 1. `routes/web.php`
Add two routes after the existing `/login` routes (around line 245):
- `GET /userLogin` → `AuthController::showUserLogin` named `user.login`
- `POST /userLogin` → `AuthController::login` named `user.login.perform`

### 2. `app/Http/Controllers/AuthController.php`
Add a new method:
- `showUserLogin(): View` — returns `view('auth.user-login')`

Reuse the existing `login()` method for the POST handler. It is route-agnostic (validates input, looks up user, sets session, redirects).

### 3. `resources/views/auth/user-login.blade.php`
Create as a copy of `resources/views/auth/login.blade.php`.
Update the form action from `route('login.perform')` to `route('user.login.perform')`.

## Validation
- Visit `/userLogin` and confirm the page renders.
- Submit the form with a valid/invalid username and confirm the same behavior as `/login`.
- Run existing tests to ensure no regressions.

## Open questions
None.
