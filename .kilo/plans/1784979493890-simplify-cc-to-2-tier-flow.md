# Plan: Replace Call Center 5-Tier Hierarchy with 2-Tier Flow (Region + Caller)

**Goal:** Replace the existing Call Center (`/cc`) 5-tier hierarchy (Super Admin → Region Admin → RTOM Admin → Supervisor → Caller) with a simplified 2-tier flow that follows the Regional Billing feature patterns: Super Admin → Region Admin → Caller.

**Confirmed mode:** Replace the existing hierarchy entirely. Existing CC users will be migrated to the new model.

---

## 1. New Hierarchy & Assignment Pattern

```
Super Admin   (system=cc, assignment=super,        admin_prev=1)
  └── Region Admin (system=cc, assignment=REGION_NAME, admin_prev=1, supervisor=SuperAdminID)
        └── Caller   (system=cc, assignment=caller_REGION_NAME, admin_prev=0, supervisor=RegionAdminID)
```

- **Caller assignment naming:** `caller_` + normalized region name (mirrors RB's `caller_<scope>` pattern but uses region instead of RTOM).
- **Caller supervisor FK:** Points to the Region Admin. This preserves hierarchy for reporting without a Supervisor or RTOM layer.
- **Region Admin assignment:** Unchanged — remains the plain region name (e.g. `WESTERN CAPE`).

---

## 2. User Migration (One-time)

All existing CC users who are not Super or plain Region Admin must be flattened into Callers.

| Current `assignment` pattern | Action | New `assignment` | New `supervisor` |
|---|---|---|---|
| `super` | Keep | `super` | unchanged |
| Plain region name (e.g. `WESTERN CAPE`) | Keep | unchanged | unchanged |
| `rtom_*` | Convert to Caller | `caller_REGION_NAME` | Region Admin ID (traverse `supervisor` chain) |
| `supervisor_*` | Convert to Caller | `caller_REGION_NAME` | Region Admin ID (traverse `supervisor` chain) |
| `caller_rtom_*` | Convert to Caller | `caller_REGION_NAME` | Region Admin ID (traverse `supervisor` chain) |

**Migration approach:**
- Run as an Artisan command (`php artisan cc:migrate-to-two-tier`) or ad-hoc DB script.
- For each CC user not in `{super, REGION_NAME}`:
  1. Traverse `supervisor` chain upward until a user whose `assignment` is not `rtom_*`, `supervisor_*`, or `caller_*` is found. That user is the Region Admin.
  2. If found, derive the region name from the Region Admin's `assignment`.
  3. Update: `assignment = 'caller_' . normalize(region_name)`, `supervisor = RegionAdminID`, `admin_prev = 0`.
- **Preserve all user IDs** so existing `CallCenterAssignment.assigned_user_id` and `CallCenterInteraction.agent_id` references remain valid.
- Preserve `fixed` flag and `status`.

---

## 3. Controller Changes

### 3.1 `CallCenter\RegionAdminController` — Major simplification
Remove these methods entirely:
- `ensureRtomAdmin()`, `ensureSupervisor()`
- `createAdminForm()`, `storeAdmin()`, `editAdminForm()`, `updateAdmin()`, `destroyAdmin()`
- `createSupervisorForm()`, `storeSupervisor()`, `editSupervisorForm()`, `updateSupervisor()`, `destroySupervisor()`, `enableSupervisor()`, `disableSupervisor()`
- `supervisorDashboard()`, `rtomDashboard()`
- `showAssignForm()`, `storeAssignment()`, `indexAssign()` → replace with caller management methods

Add / replace with:
- **`callers()`** — List all callers for this region (replaces `index()` which currently lists RTOM admins).
- **`createCallerForm()`** — Show form to create a new Caller.
- **`storeCaller()`** — Create Caller: `assignment = 'caller_' . normalize(region)`, `supervisor = session user ID`, `system = 'cc'`, `admin_prev = 0`.
- **`editCaller(User $user)`** — Edit caller name.
- **`updateCaller(Request $request, User $user)`** — Update caller name.
- **`destroyCaller(User $user)`** — Delete caller. Check `fixed` and existing assignments/interactions (same protection as CC `UserController::destroy`).
- **`enableCaller(User $user)` / `disableCaller(User $user)`** — Toggle caller status.

Simplify existing methods:
- **`dashboard()`** — Remove RTOM breakdown. Replace with caller-level breakdown (caller name, assigned rows, paid count, paid amount). Keep latest-report and all-time aggregates.
- **`reviewReport()`** — Keep region-level hide/unhide and pass flow. Remove any RTOM-granular UI references. `passReport()` commits hidden rows at the region level (already works this way in CC).
- **`hideRows()`** — Keep as-is (already region-scoped).
- **`passReport()`** — Keep as-is (already region-level). Remove `CallCenterReportRtomPass` usage; rely solely on `CallCenterReportRegionReview`.

### 3.2 `CallCenter\ReportController` — Simplify
- **Remove `distributeSupervisor()`.** The regular `distribute()` will be the only distribution endpoint and will be unlocked for Region Admin use.
- **`distribute()`** — Remove RTOM-scoping. When `user_ids` is empty, default to all active callers in the Region Admin's region. `pendingRegionalReviews()` stays as-is (already region-scoped).
- **`index()`** — Remove supervisor-specific RTOM scoping of rejected/accepted assignments.
- **`history()`** — Remove supervisor-specific RTOM scoping.
- **`summary()`** — Remove RTOM references in call calendar and agent labels.
- **`getAgentDetails()`** — Simplify: region is the agent's supervisor's assignment (direct chain, no RTOM parsing).

### 3.3 `CallCenter\AssignmentController` — Simplify
- **`distribute()`** — Support Region Admin direct distribution. When no explicit `user_ids`, auto-populate with all active callers in the region. Remove RTOM checks.
- **Remove `reopenOriginalRejectedAssignment()` rejection metadata preservation oddity?** No, keep it. The reopen logic is independent of hierarchy.

### 3.4 `CallCenter\UserController` — Convert to Caller Controller
The current CC `UserController` manages a mix of CC users. In the 2-tier model, only Region Admin creates/manages Callers. Move caller CRUD into `RegionAdminController` (as listed in 3.1) and remove `CallCenter\UserController` or repurpose it. If kept, scope it strictly to callers.

Remove:
- `setName()` — not essential for the 2-tier model; callers can use a standard edit form.
- Any mixed-role listing logic.

### 3.5 `CallCenter\SuperAdminController` — Minor update
- Keep Region Admin creation (already working).
- Remove any CC-specific user creation that's already commented out.

### 3.6 `CallCenter\DashboardController` — Minor update
- Add `callerDashboard()` ported from `RegionalBilling\DashboardController` for caller productivity metrics (completed calls, payments, daily performance).
- Keep `index()` for Super Admin.
- Remove `callerCalls7()` and `paymentList()` if they duplicate caller dashboard functionality, or keep as AJAX endpoints consumed by the caller dashboard.

---

## 4. Route Changes (`routes/web.php`)

### 4.1 Remove CC routes
```php
// RTOM / Supervisor management
Route::get('/rtoms/dashboard', ...)->name('supervisor.dashboard');   // RTOM dashboard
Route::get('/rtom/dashboard', ...)->name('rtom.dashboard');          // Supervisor dashboard

// RTOM admin CRUD
Route::get('/rtoms', ...)->name('region.index');
Route::get('/rtoms/search', ...)->name('region.search');
Route::get('/rtoms/create-admin', ...)->name('region.create_admin');
Route::get('/rtoms/create-supervisor', ...)->name('region.create_supervisor');
Route::post('/rtoms/admins', ...)->name('region.store_admin');
Route::post('/rtoms/supervisors', ...)->name('region.store_supervisor');
Route::get('/rtoms/admins/{user}/edit', ...)->name('region.edit_admin');
Route::put('/rtoms/admins/{user}', ...)->name('region.update_admin');
Route::delete('/rtoms/admins/{user}', ...)->name('region.destroy_admin');
Route::get('/rtoms/supervisors/{user}/edit', ...)->name('region.edit_supervisor');
Route::put('/rtoms/supervisors/{user}', ...)->name('region.update_supervisor');
Route::put('/rtoms/supervisors/{user}/disable', ...)->name('region.disable_supervisor');
Route::put('/rtoms/supervisors/{user}/enable', ...)->name('region.enable_supervisor');
Route::delete('/rtoms/supervisors/{user}', ...)->name('region.destroy_supervisor');

// RTOM assignment
Route::get('/rtoms/assign', ...)->name('region.assign.index');
Route::get('/rtoms/{user}/assign', ...)->name('region.assign');
Route::post('/rtoms/{user}/assign', ...)->name('region.assign.store');

// Supervisor distribution (superseded by unified distribute)
Route::post('/reports/{report}/distribute-supervisor', ...)->name('reports.distribute_supervisor');
```

### 4.2 Add CC routes
```php
// Caller management (replaces RTOM/Supervisor management)
Route::get('/region/callers', [RegionAdminController::class, 'callers'])->name('region.callers');
Route::get('/region/callers/create', [RegionAdminController::class, 'createCallerForm'])->name('region.callers.create');
Route::post('/region/callers', [RegionAdminController::class, 'storeCaller'])->name('region.callers.store');
Route::get('/region/callers/{user}/edit', [RegionAdminController::class, 'editCaller'])->name('region.callers.edit');
Route::put('/region/callers/{user}', [RegionAdminController::class, 'updateCaller'])->name('region.callers.update');
Route::delete('/region/callers/{user}', [RegionAdminController::class, 'destroyCaller'])->name('region.callers.destroy');
Route::put('/region/callers/{user}/enable', [RegionAdminController::class, 'enableCaller'])->name('region.callers.enable');
Route::put('/region/callers/{user}/disable', [RegionAdminController::class, 'disableCaller'])->name('region.callers.disable');

// Caller dashboard (ported from RB)
Route::get('/caller/dashboard', [DashboardController::class, 'callerDashboard'])->name('caller.dashboard');

// Review features ported from RB
Route::post('/reports/{report}/exclude-file', [ReportController::class, 'submitExcludeFile'])->name('reports.exclude_file');
Route::post('/reports/{report}/include-file', [ReportController::class, 'submitIncludeFile'])->name('reports.include_file');
Route::post('/reports/{report}/unlock', [ReportController::class, 'unlockReview'])->name('reports.unlock');
```

### 4.3 Keep (with simplified handlers)
```php
Route::get('/region/dashboard', [RegionAdminController::class, 'dashboard'])->name('region.dashboard');
Route::get('/region/review', [RegionAdminController::class, 'reviewReport'])->name('region.review');
Route::post('/region/review/hide-rows', [RegionAdminController::class, 'hideRows'])->name('region.review.hide_rows');
Route::post('/region/review/pass', [RegionAdminController::class, 'passReport'])->name('region.review.pass');
Route::post('/region/review-preference', [RegionAdminController::class, 'updateReviewPreference'])->name('region.review.preference');
Route::get('/assignments', [AssignmentController::class, 'index'])->name('assignments.list');
Route::get('/assignments/manage', [AssignmentController::class, 'manage'])->name('assignments.manage');
Route::post('/reports/{report}/distribute', [AssignmentController::class, 'distribute'])->name('reports.distribute');
// ... other assignment routes
```

---

## 5. View Changes

### 5.1 Delete views
- `resources/views/cc/region/create_admin.blade.php`
- `resources/views/cc/region/edit_admin.blade.php`
- `resources/views/cc/region/create_supervisor.blade.php`
- `resources/views/cc/region/edit_supervisor.blade.php`
- `resources/views/cc/region/assign.blade.php`
- `resources/views/cc/region/assign_index.blade.php`
- `resources/views/cc/supervisor/dashboard.blade.php`
- `resources/views/cc/rtom/dashboard.blade.php`

### 5.2 Create views
- `resources/views/cc/region/callers.blade.php` — Caller list table (mirrors `callcenter/users/index` but region-scoped).
- `resources/views/cc/region/create_caller.blade.php` — Simple form: username (6 digits), name, optional.
- `resources/views/cc/region/edit_caller.blade.php` — Edit caller name.
- `resources/views/cc/caller/dashboard.blade.php` — Port from `resources/views/regionalbilling/caller/dashboard.blade.php`.

### 5.3 Modify views
- `resources/views/cc/region/dashboard.blade.php` — Remove RTOM breakdown sections. Add caller breakdown (caller name, total, assigned, paid, paid amount).
- `resources/views/cc/region/index.blade.php` — Replace RTOM list with caller list.
- `resources/views/cc/region/report_review.blade.php` — Remove RTOM pass/unlock UI. Pass button locks the entire region review.
- `resources/views/callcenter/assignments/manage.blade.php` — Keep mostly intact. Remove supervisor-specific header/filter logic if present.
- `resources/views/callcenter/reports/index.blade.php` — Remove supervisor-specific `allowedRowIds` RTOM scoping. All region-admin distributions cover all region rows.

---

## 6. Features Ported from RB Pattern

| Feature | Current CC | RB | Action |
|---|---|---|---|
| Region Admin role enforcement | Loose (allows RTOM, supervisor, caller through) | Strict (`isRegionAdmin` blocks lower roles) | Tighten CC `ensureRegionAdmin()` to match RB's `isRegionAdmin()` |
| Caller dashboard | Missing | Present (`callerDashboard`) | Add to CC `CallCenter\DashboardController` |
| Exclude file upload | Missing | Present (`submitExcludeFile`) | Port to CC `ReportController` |
| Include file upload | Missing | Present (`submitIncludeFile`) | Port to CC `ReportController` |
| Review unlock | Missing | Present (`unlockReview`) | Port to CC `ReportController` |
| RTOM pass/unlock | Region-level only | Per-RTOM pass/unlock | Keep region-level only (simplify, no RTOM granularity) |
| Caller `assignment` naming | `caller_rtom_RTOM_NAME` | `caller_RTOM_NAME` | Change to `caller_REGION_NAME` for 2-tier model |
| Caller `supervisor` chain | Caller → Supervisor → RTOM → Region | Caller → (Supervisor) → RTOM → Region | Flatten to Caller → Region Admin |
| `CallCenterUser` model | Dedicated model with global scope | Uses plain `User` | Keep for backward compatibility but simplify usage |

---

## 7. Middleware & Authorization Updates

No new middleware needed. Update existing logic:

- **`EnsureCallCenterUser`** — Stays unchanged.
- **`EnsureCallCenterAdmin`** — Stays unchanged.
- **`CallCenter\RegionAdminController::ensureRegionAdmin()`** — Tighten to match RB:
  ```php
  protected function ensureRegionAdmin()
  {
      $sessionUser = session('user');
      if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
          abort(403);
      }
      $assignment = $sessionUser['assignment'] ?? null;
      if (! $assignment || $assignment === 'super'
          || str_starts_with($assignment, 'caller_')
          || str_starts_with($assignment, 'supervisor_')
          || str_starts_with($assignment, 'rtom_')) {
          abort(403);
      }
      return $assignment; // region name
  }
  ```
  This blocks callers, supervisors, and RTOMs from accessing region admin pages.

- **`CallCenter\ReportController::getAgentDetails()`** — Simplify region lookup:
  ```php
  $supervisorUser = $agent->supervisor ? User::find($agent->supervisor) : null;
  $supervisor = $supervisorUser ? $this->formatAgentLabel($supervisorUser) : 'N/A';
  $region = $supervisorUser ? $supervisorUser->assignment : 'N/A';
  ```
  (In 2-tier model, the agent's supervisor IS the Region Admin, so the region is their assignment directly.)

---

## 8. Distribution Flow (Simplified)

```
Region Admin selects report
        │
        ▼
System auto-selects all active callers in region
        │
        ▼
DistributeCallCenterReport job assigns rows round-robin
        │
        ▼
Callers see assignments on /cc/assignments/manage
```

- No RTOM scoping in the job.
- `pendingRegionalReviews` still gates distribution until the Region Admin passes the report.
- Callers accept/reject interactions as before.

---

## 9. Regional Review Flow (Simplified)

```
Region Admin opts into Regional Review Gate
        │
        ▼
Review page shows report rows for the region
        │
        ▼
Hide / Unhide rows (draft)
        │
        ▼
Exclude file upload  ← NEW (ported from RB)
Include file upload  ← NEW (ported from RB)
        │
        ▼
Pass → commits hidden rows + locks review
Unlock → reverts to draft  ← NEW (ported from RB)
```

- Removed per-RTOM pass/unlock.
- `CallCenterReportRegionReview` is the sole review lock mechanism.
- `CallCenterReportRtomPass` table remains in DB but is unused by CC.

---

## 10. Caller Flow (Unchanged + Dashboard)

Caller experience remains functionally identical:
- `/cc/assignments/manage` — accept/reject, view details, log interactions.
- `/cc/assignments/{id}/accept|reject|claim|complete|interactions` — same.

**Add:** `/cc/caller/dashboard` — caller productivity metrics:
- Today's calls, today's payments
- Total assignments, accepted, paid
- Daily performance calendar (port from RB)

---

## 11. Validation Steps

1. **Migration dry-run:** Run the migration script with `--dry-run` flag. Log all user transformations. Verify no orphaned `supervisor` references remain.
2. **Role enforcement test:** Log in as a Caller, verify `403` on `/cc/region/dashboard`, `/cc/region/review`, etc.
3. **Distribution test:** Region Admin distributes a report. Verify all callers in the region receive assignments. Verify `supervisor` chain is intact for calls/interactions.
4. **Regional review test:** Region Admin hides rows, passes report. Verify distribution is unblocked. Verify `CallCenterReportHiddenRow` records are committed correctly.
5. **Exclude/include file test:** Upload exclusion and inclusion Excel files. Verify rows are hidden/visible correctly.
6. **Caller dashboard test:** Verify metrics match assignments and interactions for the logged-in caller.
7. **Backward compatibility:** Verify existing reports, assignments, and interactions still appear for Region Admin and Callers after migration.
8. **Super Admin regression test:** Verify Super Admin can still create Region Admins and view reports.

---

## 12. Open Questions / Out of Scope

- **`CallCenterUser` model future:** Kept for backward compatibility. A future pass could migrate all CC code to plain `User` with `system='cc'` scoping, matching RB exactly. Noted but out of scope for this plan.
- **Caller `setName` endpoint:** Removed from simplified flow. If needed, can be added to the caller edit form.
- **`adminconfig` and master-system routes:** Unchanged. These serve the shared data pipeline and are not part of the call-center hierarchy.
- **Payment upload (`/payments/*`):** Unchanged. Shared feature.

---

## 13. Execution Order

1. **Database migration / Artisan command** for user flattening.
2. **Controllers:** `RegionAdminController`, `ReportController`, `AssignmentController`, `UserController`, `SuperAdminController`, `DashboardController`.
3. **Routes (`routes/web.php`).**
4. **Views:** Delete → Create → Modify.
5. **Run validation steps.**
