# Fix: Redirect RB users from generic `/` dashboard

## Problem
RB users who navigate to the root URL `/` after login see the generic master dashboard (`views/dashboard.blade.php`). RB admin users (`is_admin === true`) see "View Reports" and "Configurations" cards, which belong to the RB system context, not the generic master landing page.

The login redirect in `AuthController::login()` correctly routes RB users to RB-specific dashboards, but `DashboardController::index` has no RB guard — only a CC redirect. Any RB user manually navigating to `/` falls through to the generic view.

## Root Cause
`app/Http/Controllers/DashboardController.php:15-22` only checks for `system === 'cc'` and redirects CC users. There is no equivalent check for `system === 'rb'`.

## Fix
Add an RB redirect block in `DashboardController::index`, immediately after the existing CC redirect block. Redirect RB users to `rb.dashboard` (`/rb/`), which already handles assignment-based sub-routing in `RegionalBilling\DashboardController::index`.

### File to edit
`app/Http/Controllers/DashboardController.php`

### Change
After the existing CC redirect block (line 22), add:

```php
if (!empty($sessionUser) && (($sessionUser['system'] ?? null) === 'rb')) {
    return redirect()->route('rb.dashboard');
}
```

This mirrors the CC pattern and ensures RB users are always routed to their system dashboard, never seeing the generic master landing page.

## Validation
- Log in as RB user (any assignment), navigate to `/` → should redirect to `/rb/` and then to the appropriate RB sub-dashboard based on assignment.
- Log in as CC user, navigate to `/` → should still redirect to CC dashboard (existing behavior, unchanged).
- Log in as master-system user, navigate to `/` → should still see the generic dashboard (existing behavior, unchanged).
