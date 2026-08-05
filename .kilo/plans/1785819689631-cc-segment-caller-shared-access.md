# Plan: CC Segment Admins Should Share Callers Within the Same Segment

## Problem

In the RB system, multiple RTOM admins in the same RTOM region **share callers** — each RTOM admin can see and manage all callers created by any RTOM admin in the same RTOM (via `getSharedRtomAdminUserIds()` in `RegionalBilling/UserController.php` and `RegionalBilling/AssignmentController.php`).

In the CC system, segment admins do **NOT** share callers — each segment admin only sees callers where `supervisor = their own ID`. Multiple segment admins in the same segment (e.g., both with `assignment='segment_cc'`) have isolated caller sets.

This is an asymmetry between the CC and RB systems that should be reconciled.

## Root Cause Analysis

### RB RTOM Model (Correct — Peer Sharing)

**Mechanism**: `getSharedRtomAdminUserIds()` in:
- `app/Http/Controllers/RegionalBilling/UserController.php` (lines 37-70)
- `app/Http/Controllers/RegionalBilling/AssignmentController.php` (lines 43-80)

**How it works**:
1. Gets current RTOM admin's `assignment` (e.g. `rtom_jhb`)
2. Derives the RTOM value (`jhb`) and looks up the region from `MasterDatasetRow`
3. Finds all RB users with `assignment = 'rtom_' + rtomValue` and same region
4. Calls them "shared supervisors" — callers with `supervisor` in this group are visible to all

**Applied in**:
- `UserController::index()` — lists callers where `supervisor IN (shared_ids)` (lines 132-133)
- `UserController::edit/update/disable/enable/destroy` — checks `isCallerSharedByRtomAdmin()` (lines 151, 167, 192, 211, 257)
- `AssignmentController::assign()` — distribution filters by `supervisor IN (shared_ids)` (lines 866-873)
- `RegionAdminController::rtomDashboard()` — dashboard shows callers from shared supervisors (lines 383-401)

### CC Segment Model (Broken — No Peer Sharing)

**Current isolation points** (all filter by `$segmentAdminId` only):
- `SegmentAdminController::callers()` — line 175: `->where('supervisor', $segmentAdminId)`
- `SegmentAdminController::storeCaller()` — line 224: sets `'supervisor' => $segmentAdminId`
- `SegmentAdminController::editCaller()` — line 238: `(int) $user->supervisor !== (int) $segmentAdminId`
- `SegmentAdminController::updateCaller()` — line 251: same guard
- `SegmentAdminController::destroyCaller()` — line 269: same guard
- `SegmentAdminController::enableCaller()` — line 295: same guard
- `SegmentAdminController::disableCaller()` — line 312: same guard
- `AssignmentController::distribute()` — lines 790-795: `->where('supervisor', $segmentAdminId)`
- `ReportController` (CC) — line 76: `->where('supervisor', $segmentAdminId)`

## Proposed Changes

The CC system already has a clear segment-to-bucket mapping: `segment_ccs`, `segment_cc`, `segment_s` map to `caller_ccs`, `caller_cc`, `caller_s`. We can use this as the "shared group" identifier, similar to how RB uses the RTOM value.

### 1. Add helper to SegmentAdminController

Add a method to fetch all segment admins in the same segment (shared group):

```php
protected function getSharedSegmentAdminIds(): array
{
    $sessionUser = session('user');
    $currentUserId = (int) ($sessionUser['id'] ?? 0);
    if ($currentUserId <= 0) {
        return [];
    }

    $assignment = $sessionUser['assignment'] ?? '';
    if (!str_starts_with($assignment, 'segment_')) {
        return [$currentUserId];
    }

    // All CC segment admins with the same assignment are in the same shared group
    $ids = User::where('system', 'cc')
        ->where('admin_prev', 1)
        ->where('assignment', $assignment)
        ->where('status', 1)
        ->pluck('id')
        ->map(fn($id) => (int) $id)
        ->toArray();

    return $ids;
}
```

### 2. Update `SegmentAdminController::callers()` (line 175)

**Before:**
```php
$query = User::where('system', 'cc')
    ->where('assignment', $callerAssign)
    ->where('supervisor', $segmentAdminId)
    ->withCount(['interactionsAsAgent', 'rowAssignments']);
```

**After:**
```php
$sharedIds = $this->getSharedSegmentAdminIds();
$query = User::where('system', 'cc')
    ->where('assignment', $callerAssign)
    ->whereIn('supervisor', $sharedIds)
    ->withCount(['interactionsAsAgent', 'rowAssignments']);
```

### 3. Update access guards in edit/update/destroy/enable/disable

Replace every instance of `(int) $user->supervisor !== (int) $segmentAdminId` with a shared-group check:

**Before:**
```php
if ($user->assignment !== $callerAssign || (int) $user->supervisor !== (int) $segmentAdminId) {
    abort(404);
}
```

**After:**
```php
$sharedIds = $this->getSharedSegmentAdminIds();
if ($user->assignment !== $callerAssign || !in_array((int) $user->supervisor, $sharedIds, true)) {
    abort(404);
}
```

### 4. Update `AssignmentController::distribute()` (lines 788-795)

**Before:**
```php
$userIds = User::where('system', 'cc')
    ->where('assignment', $callerAssignment)
    ->where('supervisor', $segmentAdminId)
    ->where('status', 1)
    ->pluck('id')
    ...
```

**After:**
```php
$sharedIds = /* reuse the same shared group logic */;
$userIds = User::where('system', 'cc')
    ->where('assignment', $callerAssignment)
    ->whereIn('supervisor', $sharedIds)
    ->where('status', 1)
    ->pluck('id')
    ...
```

### 5. Update CC `ReportController` caller listing (line 74-79)

**Before:**
```php
$ccUsers = User::where('system', 'cc')
    ->where('assignment', $callerAssignment)
    ->where('supervisor', $segmentAdminId)
    ->where('status', 1)
    ->orderBy('username')
    ->get();
```

**After:**
```php
$sharedIds = /* shared segment admin IDs */;
$ccUsers = User::where('system', 'cc')
    ->where('assignment', $callerAssignment)
    ->whereIn('supervisor', $sharedIds)
    ->where('status', 1)
    ->orderBy('username')
    ->get();
```

## Affected Code Boundaries

- `app/Http/Controllers/CallCenter/SegmentAdminController.php` — all methods (callers, editCaller, updateCaller, destroyCaller, enableCaller, disableCaller)
- `app/Http/Controllers/CallCenter/AssignmentController.php` — `distribute()` method only
- `app/Http/Controllers/CallCenter/ReportController.php` — caller listing in `index()` (around line 74)
- No database migrations needed
- No frontend/view changes needed
- New method `getSharedSegmentAdminIds()` needs to be accessible from all three controllers — consider extracting to a trait

## Shared Logic Extraction

Since `getSharedSegmentAdminIds()` is needed in 3 controllers, extract it to a trait:

**Create**: `app/Http/Controllers/CallCenter/Concerns/InteractsWithSharedSegmentAdmins.php`

```php
<?php
namespace App\Http\Controllers\CallCenter\Concerns;
use App\Models\User;
trait InteractsWithSharedSegmentAdmins
{
    protected function getSharedSegmentAdminIds(): array
    {
        $sessionUser = session('user');
        $currentUserId = (int) ($sessionUser['id'] ?? 0);
        if ($currentUserId <= 0) {
            return [];
        }
        $assignment = $sessionUser['assignment'] ?? '';
        if (!str_starts_with($assignment, 'segment_')) {
            return [$currentUserId];
        }
        return User::where('system', 'cc')
            ->where('admin_prev', 1)
            ->where('assignment', $assignment)
            ->where('status', 1)
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->toArray();
    }
}
```

Add `use InteractsWithSharedSegmentAdmins;` to each controller.

## Validation Steps

1. Create two CC Super Admins
2. Log in as Super Admin A; create two segment admins in `segment_cc`
3. Log in as Segment Admin A1; create a caller (caller_cc)
4. Log in as Segment Admin A2; verify the caller from A1 is visible in `/segment/callers`
5. Log in as Segment Admin A2; edit/call action on the caller created by A1 — verify it works
6. Log in as Segment Admin A2; distribute a report — verify callers from both A1 and A2 receive rows
7. Log in as Segment Admin B in `segment_ccs`; verify callers from `segment_cc` are NOT visible
8. Verify RTOM caller sharing remains unchanged (RB system untouched)
9. Verify non-segment-admin CC users cannot access `/segment/callers`
10. Run existing test suite

## Risks

- Low: Sharing expands visibility only within the same segment — no cross-segment leakage
- Caller attribution: `supervisor` still records the creating segment admin for audit. Multiple admins now share visibility without losing audit trail.
- Distribution: If two segment admins simultaneously distribute, rows may be assigned to callers from both admins — need to confirm this is desired behavior (matches RB RTOM model)

## Out of Scope

- Changing how `supervisor` is stored for callers (remains as creator ID)
- Any changes to RB system (already has peer sharing)
- Cross-segment sharing (segment_cc admins should NOT see segment_ccs callers)

## Implementation Notes

- The `DistributeCallCenterReport` job receives `$userIds` as a parameter and does NOT query for callers itself — updating the controller's caller query is sufficient (no job changes needed)
- The `Concerns` directory does not exist yet — needs to be created at `app/Http/Controllers/CallCenter/Concerns/`
- All three controllers (`SegmentAdminController`, `AssignmentController`, `ReportController`) already `use App\Models\User;`