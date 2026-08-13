# Plan: RB Region Admins Should Share RTOM Admins Within the Same Region

## Problem

Currently, when multiple RB region admins exist for the same region, each region admin can only see RTOM admins **they personally created**. This is because `RegionAdminController::index()` and `RegionAdminController::search()` filter RTOM admins by `supervisor = $currentSupervisor` (the logged-in region admin's ID).

This mirrors the original CC segment admin → caller isolation bug that was just fixed. The RB system already had peer-sharing for RTOM admin → caller (via `getSharedRtomAdminUserIds()`), but **lacks** the same sharing for region admin → RTOM admin.

## Current Behavior (Region Admin Views RTOM Admins)

### File: `app/Http/Controllers/RegionalBilling/RegionAdminController.php`

**Isolated by supervisor (no sharing):**

| Method | Line | Filter |
|--------|------|--------|
| `index()` | 147 | `->where('supervisor', $currentSupervisor)` |
| `search()` | 204 | `->where('supervisor', $currentSupervisor)` |
| `dashboard()` | 176 | `->where('supervisor', session('user')['id'])` |
| `editAdminForm()` | 304 | `$user->supervisor !== session('user')['id']` |
| `updateAdmin()` | 316 | `$user->supervisor !== session('user')['id']` |

**Existing sharing (for reference — RTOM admin → callers):**

| Method | Line | Shared Logic |
|--------|------|--------------|
| `UserController::index()` | 132-133 | `whereIn('supervisor', getSharedRtomAdminUserIds())` |
| `UserController::isCallerSharedByRtomAdmin()` | 114-122 | Checks caller's supervisor is in shared RTOM admin group |
| `AssignmentController::assign()` | 866-873 | `whereIn('supervisor', sharedIds + currentUserId)` |
| `rtomDashboard()` | 383-401 | Builds supervisorIds from all same-RTOM admins |

## Comparison: CC Segment Admin Pattern (Already Fixed)

The CC system already solved this exact problem for segment admins → callers. The pattern used there is:

- **Trait**: `app/Http/Controllers/CallCenter/Concerns/InteractsWithSharedSegmentAdmins.php`
- **Method**: `getSharedSegmentAdminIds()` — returns all segment admin IDs with the same `segment_xxx` assignment value
- **Applied to**: 3 controllers (`SegmentAdminController`, `AssignmentController`, `ReportController`)
- **Replaces**: Every `->where('supervisor', $ownId)` with `->whereIn('supervisor', $sharedIds)`

## Proposed Changes

### 1. Add helper to RegionAdminController

Add a `getSharedRegionAdminIds()` method to find all region admins with the same region assignment:

```php
protected function getSharedRegionAdminIds(): array
{
    $sessionUser = session('user');
    $currentUserId = (int) ($sessionUser['id'] ?? 0);
    if ($currentUserId <= 0) {
        return [];
    }

    $assignment = $sessionUser['assignment'] ?? '';
    // Region admins have a plain region name (not super/rtom_/caller_/supervisor_)
    if ($assignment === 'super' || str_starts_with($assignment, ['rtom_', 'caller_', 'supervisor_'])) {
        return [$currentUserId];
    }

    return User::where('system', 'rb')
        ->where('admin_prev', 1)
        ->where('assignment', $assignment)
        ->where('status', 1)
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->toArray();
}
```

**Rationale**: Unlike the RTOM sharing (which needs RTOM value + region cross-referencing), region admin sharing is simpler — all region admins with the exact same `assignment` (region name) form the shared group. No database lookup to `MasterDatasetRow` needed.

### 2. Create trait for reuse

Create `app/Http/Controllers/RegionalBilling/Concerns/InteractsWithSharedRegionAdmins.php`:

```php
<?php
namespace App\Http\Controllers\RegionalBilling\Concerns;
use App\Models\User;
trait InteractsWithSharedRegionAdmins
{
    protected function getSharedRegionAdminIds(): array
    {
        $sessionUser = session('user');
        $currentUserId = (int) ($sessionUser['id'] ?? 0);
        if ($currentUserId <= 0) {
            return [];
        }
        $assignment = $sessionUser['assignment'] ?? '';
        if ($assignment === 'super' || str_starts_with($assignment, ['rtom_', 'caller_', 'supervisor_'])) {
            return [$currentUserId];
        }
        return User::where('system', 'rb')
            ->where('admin_prev', 1)
            ->where('assignment', $assignment)
            ->where('status', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
```

### 3. Update `RegionAdminController::index()` (line 144-147)

**Before:**
```php
$query = User::where('system', 'rb')
    ->where('admin_prev', 1)
    ->where('assignment', 'like', 'rtom_%')
    ->where('supervisor', $currentSupervisor);
```

**After:**
```php
$sharedIds = $this->getSharedRegionAdminIds();
$query = User::where('system', 'rb')
    ->where('admin_prev', 1)
    ->where('assignment', 'like', 'rtom_%')
    ->whereIn('supervisor', $sharedIds);
```

### 4. Update `RegionAdminController::search()` (line 201-204)

Same pattern — replace `->where('supervisor', $currentSupervisor)` with `->whereIn('supervisor', $sharedIds)`.

### 5. Update `RegionAdminController::dashboard()` (line 173-177)

**Before:**
```php
$rtomCount = User::where('system', 'rb')
    ->where('admin_prev', 1)
    ->where('assignment', 'like', 'rtom_%')
    ->where('supervisor', session('user')['id'] ?? null)
    ->count();
```

**After:**
```php
$sharedIds = $this->getSharedRegionAdminIds();
$rtomCount = User::where('system', 'rb')
    ->where('admin_prev', 1)
    ->where('assignment', 'like', 'rtom_%')
    ->whereIn('supervisor', $sharedIds)
    ->count();
```

### 6. Update `RegionAdminController::editAdminForm()` (line 304)

**Before:**
```php
if (! $user->assignment || ! str_starts_with($user->assignment, 'rtom_') || $user->supervisor !== (session('user')['id'] ?? null)) {
    abort(404);
}
```

**After:**
```php
if (! $user->assignment || ! str_starts_with($user->assignment, 'rtom_') || !in_array((int) $user->supervisor, $this->getSharedRegionAdminIds(), true)) {
    abort(404);
}
```

### 7. Update `RegionAdminController::updateAdmin()` (line 316)

Same guard pattern as `editAdminForm()`.

## Affected Code Boundaries

- **New file**: `app/Http/Controllers/RegionalBilling/Concerns/InteractsWithSharedRegionAdmins.php`
- **Modified**: `app/Http/Controllers/RegionalBilling/RegionAdminController.php` — `index()`, `search()`, `dashboard()`, `editAdminForm()`, `updateAdmin()`
- **NO migration needed** — the `supervisor` field is retained for audit
- **NO view changes needed** — templates already display whatever data is passed
- **NO route changes needed**
- **NO changes to RB caller sharing** (already implemented via `getSharedRtomAdminUserIds()`)
- **NO changes to CC system** (already implemented)

## Key Design Difference from RTOM Sharing

The RTOM → caller sharing uses a **region-derived** grouping (`getRtomAdminRegion()` walks the supervisor chain + looks up `MasterDatasetRow`). The region admin → RTOM admin sharing is simpler:

- RTOM admins have `assignment = 'rtom_<value>'` and their `supervisor` = region admin ID
- Multiple region admins share the same `assignment` (region name)
- Therefore: "shared RTOM admins" = RTOM admins where `supervisor IN (all region admins with same assignment)`

This means a region admin who created RTOM admins can see them, **and** so can any other region admin in the same region — even if that other admin didn't create them.

## Validation Steps

1. Create two RB region admins in the same region (e.g., both with `assignment='WESTERN CAPE'`)
2. Log in as Region Admin A; create an RTOM admin (assignment starts with `rtom_`)
3. Log in as Region Admin B; navigate to `/rb/region/index` — verify the RTOM admin from A is visible
4. Log in as Region Admin B; edit the RTOM admin created by A — verify it works (line 304 guard passes)
5. Log in as Region Admin B; verify RTOM dashboard count includes RTOM admins from both A and B
6. Search functionality (`/rb/region/search`) returns RTOM admins from both region admins
7. Log in as Region Admin C in a different region — verify RTOM admins from A are NOT visible
8. Verify RTOM → caller sharing remains unchanged (existing `getSharedRtomAdminUserIds()` logic)
9. Verify non-region-admin RB users cannot access `/rb/region/index` (still blocked by `ensureRegionAdmin()`)
10. Run existing test suite

## Risks

- **Low**: Sharing is scoped to region admins with identical `assignment` (region name) — no cross-region leakage
- **Audit preservation**: `supervisor` still records the creating region admin. Multiple admins now share visibility without losing audit trail.
- **Consistency**: This change makes CC segment admin → caller and RB region admin → RTOM admin behave identically

## Out of Scope

- Any changes to RTOM admin → caller sharing (already working)
- Changes to RB region admin creation/management by Super Admin
- UI modifications (views already display passed data)
- Database schema changes