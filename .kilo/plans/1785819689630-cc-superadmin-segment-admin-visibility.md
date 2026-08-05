# Fix: CC Super Admins should see ALL segment admins (not only those they created)

## Problem

When multiple CC Super Admins exist, each super admin can only see segment admins **they personally created**. This is because `SuperAdminController::indexSegments()` and `SuperAdminController::searchSegments()` filter by `supervisor = $sessionUser['id']`.

In contrast, the RB region admin views (`buildRbRegionQuery`) correctly do NOT filter by supervisor — all RB region admins are visible to any CC Super Admin.

### Root Cause

**File**: `app/Http/Controllers/CallCenter/SuperAdminController.php`

- **Line 82** (`indexSegments`): `->where('supervisor', $sessionUser['id'] ?? null)`
- **Line 110** (`searchSegments`): `->where('supervisor', $sessionUser['id'] ?? null)`

These lines restrict the query to only users whose `supervisor` equals the **currently logged-in** Super Admin's ID. Since `storeUser()` (line 58) sets `supervisor` to the creating admin's ID, only self-created segment admins are visible.

## Design Questions

1. **Should segment admins be associated with a creator at all?** Currently the `supervisor` field stores the creating Super Admin's ID. We should preserve this for audit purposes but not use it as a visibility filter.
2. **Any access-control concerns with removing the supervisor filter?** No — `ensureSuper()` already restricts both methods to users with `assignment === 'super'`. All CC Super Admins have equal visibility rights.

## Proposed Changes

### 1. Remove supervisor filter from `indexSegments()` (line 82)

**Before:**
```php
$query = User::where('system', 'cc')
    ->where('assignment', 'like', 'segment_%')
    ->where('supervisor', $sessionUser['id'] ?? null);
```

**After:**
```php
$query = User::where('system', 'cc')
    ->where('assignment', 'like', 'segment_%');
```

### 2. Remove supervisor filter from `searchSegments()` (line 110)

**Before:**
```php
$query = User::where('system', 'cc')
    ->where('assignment', 'like', 'segment_%')
    ->where('supervisor', $sessionUser['id'] ?? null);
```

**After:**
```php
$query = User::where('system', 'cc')
    ->where('assignment', 'like', 'segment_%');
```

### 3. Remove unused `$sessionUser` variable (if no longer needed in those methods)

In `indexSegments()`, `$sessionUser` is used on line 82 for the supervisor filter. If we remove that, check whether `$sessionUser` is used elsewhere in the method — it is not (lines 76, 82 only). We can remove the unused variable.

In `searchSegments()`, same situation — `$sessionUser` is only used for the supervisor filter (line 104, 110).

## Affected Code Boundaries

- `app/Http/Controllers/CallCenter/SuperAdminController.php` — methods `indexSegments()` and `searchSegments()`
- No database migration needed (the `supervisor` field is retained for audit; it's just not used as a filter)
- No frontend/view changes needed (`_segment_rows.blade.php` already displays all data passed to it)
- No route changes needed

## Data Flow

1. CC Super Admin navigates to `/cc/segments` (route: `cc.super.segments`)
2. `indexSegments()` runs `ensureSuper()` — confirms user has `assignment === 'super'`
3. Query retrieves **all** users with `system='cc'` and `assignment LIKE 'segment_%'`
4. Results passed to `cc.super.segments` view → renders `_segment_rows` partial
5. Real-time search via `searchSegments()` returns matching rows as HTML fragment

## Failure Modes / Risks

- **Low risk**: Removing the supervisor filter only expands visibility — no data loss, no deletion.
- **Audit consideration**: The creator (`supervisor`) is still recorded in the database for accountability. Optionally, could display "Created By" column using the `supervisorUser` relationship, but that is out of scope for this fix.
- **Performance**: Query returns more rows, but segment admins are limited to 3 segments, so volume is negligible.

## Validation Steps

1. Log in as CC Super Admin A; create a segment admin.
2. Log in as CC Super Admin B; verify the segment admin created by A is visible.
3. Verify search (`/cc/segments/search`) returns segment admins created by other super admins.
4. Verify RB region admins remain visible (existing behavior unchanged).
5. Verify a non-super-admin CC user cannot access these routes (still blocked by `session.cc_admin` middleware).

## Out of Scope

- Displaying "Created By" column for segment admins (could be a future enhancement)
- Changing how `supervisor` is stored or used elsewhere
- Any changes to RB region admin visibility (already working correctly)
- Caller sharing between peer segment admins (see separate plan: `cc-segment-caller-shared-access`)

## Implementation Tasks

1. ✅ Edit `SuperAdminController.php` — remove `->where('supervisor', $sessionUser['id'] ?? null)` from `indexSegments()` and `searchSegments()`
2. ✅ Remove unused `$sessionUser` variable in both methods
3. ✅ Run PHP lint / syntax check on the modified file
4. ✅ Run existing test suite