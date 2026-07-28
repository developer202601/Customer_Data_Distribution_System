# Plan: Parallel RTOM Admins Sharing Callers & Distribution

## Problem
Only one RTOM admin can exist per RTOM. Multiple logins for the same RTOM are not supported — callers are scoped to a single RTOM admin via the `supervisor` field, and distribution is locked to that admin's direct callers.

## Goal
Allow two (or more) RB login accounts for the same RTOM to share callers and do distribution within a single region. Both admins should see the same callers and can distribute rows independently.

## Critical Constraint
The same RTOM name (e.g., `rtom_alpha`) may exist in MULTIPLE regions. Sharing of callers must be scoped to a single region only — callers from region A's `rtom_alpha` must NOT be visible to region B's `rtom_alpha`.

## Approach
Change the caller lookup from `supervisor = current_user_id` to `supervisor IN (all rtom admin user IDs for the same rtom_<name> assignment WITHIN THE SAME REGION)`. No schema changes needed — reuses the existing `supervisor` column and callers table.

### Key Insight
Currently, callers are found by direct supervisor = current_user_id. For sharing, we find the RTOM assignment value (e.g., `rtom_alpha`), find ALL users with that same assignment who also share the same region (derived from the current user's supervisor chain), then find all callers whose `supervisor` is ANY of those users AND whose row data matches the region.

### Region Scoping Logic
1. Current RTOM admin user → get their region via supervisor chain (`getRtomAdminRegion()`)
2. Find all RTOM admin users with the same `rtom_<name>` assignment
3. Filter those to only users whose region matches the current user's region
4. Use those filtered user IDs as the `supervisor` IN clause for caller lookup

## Changes Required

### 1. New helper method in `RegionAdminController` (or shared base)
Add method `getSharedRtomAdminUsers(User $currentUser): \Illuminate\Support\Collection` that:
1. Gets the current user's region via `$this->getRtomAdminRegion($currentUser)`
2. Extracts the RTOM value from the current user's assignment
3. Finds all users with `assignment = 'rtom_' . $rtomValue` AND whose region matches (verified by checking their region via `getRtomAdminRegion()` for each candidate)
4. Returns the collection of matching RTOM admin users

```php
protected function getSharedRtomAdminUsers(User $currentUser): \Illuminate\Support\Collection
{
    $region = $this->getRtomAdminRegion($currentUser);
    if (!$region) {
        return collect();
    }
    $assignment = $currentUser->assignment ?? '';
    if (!str_starts_with($assignment, 'rtom_')) {
        return collect();
    }
    $rtomValue = substr($assignment, 5); // remove 'rtom_' prefix
    $rtomAssignment = 'rtom_' . $rtomValue;

    return User::where('system', 'rb')
        ->where('assignment', $rtomAssignment)
        ->where('status', 1)
        ->get()
        ->filter(fn (User $u) => $this->getRtomAdminRegion($u) === $region);
}
```

### 2. `app/Http/Controllers/RegionalBilling/AssignmentController.php` — `distribute()` line 826-835
**Before:**
```php
$callerIds = User::query()
    ->where('system', 'rb')
    ->where('status', 1)
    ->where('assignment', 'like', 'caller_%')
    ->where('supervisor', (int) ($sessionUser['id'] ?? 0))
    ->whereIn('id', $inputUserIds)
    ->pluck('id') ...
```
**After:** Use the shared RTOM admin approach. Extract rtom from current user's assignment, get all shared RTOM admin IDs, then filter callers by `whereIn('supervisor', $sharedRtomAdminIds)`.

### 3. `app/Http/Controllers/RegionalBilling/ReportController.php` — `rtomReportSummary()` line 376-382
**Before:**
```php
$callers = User::query()
    ->where('system', 'rb')
    ->where('status', 1)
    ->where('assignment', 'like', 'caller_%')
    ->where('supervisor', $sessionUserId)
    ->orderBy('username')
    ->get();
```
**After:** Same pattern — find callers under any shared RTOM admin for the same rtom group in the same region.

### 4. `app/Http/Controllers/RegionalBilling/RegionAdminController.php` — dashboard methods (lines 327, 340)
Both `supervisor_dashboard.blade.php` and `rtom_dashboard.blade.php` listing use `where('supervisor', ...)` to find callers. Update to share callers across RTOM admins within the same region only.

### 5. `app/Http/Controllers/RegionalBilling\UserController.php` — line 44
The user listing for supervisors also filters by `where('supervisor', ...)`. This is less critical (it's for management, not distribution), but should be consistent.

### 6. Existing `assignments.manage.blade.php` — CC side
The CC caller management page also uses `where('supervisor', ...)` pattern. If the same sharing model is desired for CC, it needs the same treatment. This is out of scope for this request.

## No Schema Changes Required
- The `supervisor` column structure stays unchanged.
- A single caller continues to have one `supervisor` (its primary RTOM admin).
- The sharing is computed at query time by expanding the supervisor filter to include all RTOM admins in the same region group.

## Verification Plan
1. Create two RTOM admin users for the same RTOM in the same region (e.g., region "west", both `rtom_alpha`)
2. Create callers under one RTOM admin (supervisor = user A)
3. Log in as RTOM admin B (same rtom_alpha, same region "west")
4. Verify the caller dashboard shows the shared callers
5. Verify `distribute()` shows shared callers in the dropdown
6. Verify distribution creates assignments correctly for both admins
7. Verify stop gate (regional review) still works — passed reports are per RTOM not per admin
8. **Negative test**: Create RTOM admin with same name `rtom_alpha` in a DIFFERENT region (e.g., "east") — callers should NOT be shared between regions

## Distribution Syncing Constraint
Distribution of the same record from two parallel RTOM accounts must NOT be allowed. The shared callers and rows must be synced so that once a row is distributed by one RTOM admin, it becomes unavailable to the other.

### Implementation for Distribution Syncing
1. **Row-level locking**: Track which rows are currently being distributed or already distributed. Use the existing `CallCenterAssignment` table — rows assigned to a caller under one RTOM admin should not be distributable by the other shared RTOM admin for the same report.
2. **Shared candidate rows**: When computing `$rowsToDistribute` in `distribute()`, subtract rows that are already assigned (via `CallCenterAssignment`) from the pool of available rows. This already partially exists at line 919 (`$rowsToDistribute = array_values(array_diff($rtomRowIds, $alreadyAssignedRowIds))`).
3. **Concurrency protection**: Add a database-level check using a unique constraint or application-level lock (DB transaction + row-level check) to prevent race conditions where two RTOM admins simultaneously pick the same row.

The existing `alreadyAssignedRowIds` check at AssignmentController line 909-919 handles part of this. The key change is ensuring that `$rtomRowIds` in `distribute()` includes shared callers' rows, and that the already-assigned subtraction works correctly across both RTOM admins.

### Verification for Sync
8. Log in as RTOM admin A and distribute row #42 to caller X
9. Log in as RTOM admin B (same rtom, same region) and verify row #42 is NOT available for distribution
10. Verify row #42 shows as already assigned in admin B's view