# Add Outstanding Column + Status Feature to Report Tables

## Goal

1. Add an "Outstanding" column (arrears - payments) to report tables across RB, CC, and caller dashboards.
2. Add a "Status" column derived from Outstanding: if Outstanding < configurable threshold → "paid" (green), otherwise → "unpaid" (red).
3. The threshold is configurable in admin config as a new feature.

## Data Availability

`MasterDatasetRow` has both `new_arrears_value` and `payments_value` in its `$fillable` array, so both fields are available on row objects in all templates below.

## Part A: Admin Config for Threshold

### A1. New Migration

Create migration `2026_07_31_000000_add_outstanding_threshold_to_configurations_table.php`:
- Adds a seed row for `outstanding_threshold` config with default value (e.g., 0)
- Uses `Configurations::updateOrCreate` pattern in a seeder or migration

Actually, since the `Configurations` model already supports arbitrary `config_name`/`value` rows, no migration is needed. The threshold will be stored as a new row in the existing `configurations` table when first saved via admin UI.

### A2. Admin Config UI — `admin/adminconfig.blade.php`

- Add a new button in the sidebar: "Outstanding Threshold" (data-config-target="outstanding-threshold")
- Add a new config block `<div data-config-block="outstanding-threshold">` with:
  - Input field: `<input type="number" name="outstanding_threshold" ...>` 
  - Save button posting to `route('configurations.outstanding_threshold')`
  - Display last edited timestamp and editor

### A3. New Route in `routes/web.php`

```
Route::post('/configurations/outstanding-threshold', [BillRangeController::class, 'saveOutstandingThreshold'])->name('configurations.outstanding_threshold');
```

### A4. New Controller Method in `BillRangeController.php`

`saveOutstandingThreshold(Request $request)`:
- Validate: `outstanding_threshold` required, integer, min:0
- Get previous value for audit
- `Configurations::updateOrCreate(['config_name' => 'outstanding_threshold'], ['value' => $incomingFields['outstanding_threshold'], 'changedby_id' => $userId])`
- Record `ConfigurationChange` entry
- Redirect back to admin config with success message

### A5. Update `AdminController::config()`

- Add `outstanding_threshold` to the `whereIn('config_name', [...])` query
- Pass it to the view as part of `$configs`

## Part B: Outstanding + Status Columns in Report Tables

### B1. RB Review Table — `regionalbilling/reports/_review_table.blade.php`

- Add `<th>Outstanding</th>` after `<th>Arrears</th>` (line 124)
- Add `<th>Status</th>` after `<th>Outstanding</th>`
- Add `<td>` for Outstanding after Arrears cell (line 146):
  `{{ $row->new_arrears_value !== null ? number_format((float) $row->new_arrears_value - (float) ($row->payments_value ?? 0), 2) : '—' }}`
- Add `<td>` for Status after Outstanding cell:
  - Compute `$outstanding = $row->new_arrears_value !== null ? (float) $row->new_arrears_value - (float) ($row->payments_value ?? 0) : null;`
  - If `$outstanding === null`: show `—`
  - Else if `$outstanding < $threshold`: `<span class="badge bg-success">paid</span>`
  - Else: `<span class="badge bg-danger">unpaid</span>`
- Update search placeholder (line 114) to include "outstanding"
- Get threshold from config: `$configs['outstanding_threshold']->value ?? 0` (passed from controller or via `config()` helper)

### B2. RB Caller Dashboard — `regionalbilling/caller/dashboard.blade.php`

- Add `<th>Outstanding</th>` after `<th>Arrears</th>` (line 93)
- Add `<th>Status</th>` after `<th>Outstanding</th>`
- Add `<td>` for Outstanding after Arrears cell (line 104)
- Add `<td>` for Status after Outstanding cell (same logic as B1)

### B3. CC Caller Dashboard — `callcenter/caller/dashboard.blade.php`

- Add `<th>Outstanding</th>` after `<th>Payment</th>` (line 94)
- Add `<th>Status</th>` after `<th>Outstanding</th>`
- Add `<td>` for Outstanding after Payment cell (line 106)
- Add `<td>` for Status after Outstanding cell (same logic as B1)

### B4. CC Region Review Table — `cc/region/_report_review_table.blade.php`

- Add `<th>Outstanding</th>` after `<th>Arrears</th>` (line ~52)
- Add `<th>Status</th>` after `<th>Outstanding</th>`
- Add `<td>` for Outstanding after Arrears cell (line 54)
- Add `<td>` for Status after Outstanding cell (same logic as B1)

### B5. Process Overview Results — `process/assignments/partials/overview-results.blade.php`

- Add `<th>Outstanding</th>` after `<th>New Arrears (Rs.)</th>` (line ~35)
- Add `<th>Status</th>` after `<th>Outstanding</th>`
- Add `<td>` for Outstanding after Arrears cell (line 36)
- Add `<td>` for Status after Outstanding cell (same logic as B1)

## Threshold Access in Views

The threshold value needs to be accessible in all 5 blade templates. Options:
1. Pass it from each controller as a view variable (requires controller changes)
2. Use a helper function or `config()` helper that reads from the `Configurations` model
3. Use a view composer that injects the config value into all views

Recommended: Option 3 — create a view composer in a service provider that shares `$outstandingThreshold` with all views, or use a simple helper that queries `Configurations::where('config_name', 'outstanding_threshold')->value('value')`.

## Verification

1. Confirm `payments_value` is accessible on `$row` objects in all five templates (it is in `MasterDatasetRow::$fillable`)
2. Verify the Outstanding calculation handles null `new_arrears_value` (shows "—") and null `payments_value` (treats as 0)
3. Verify the Status badge renders correctly: green for "paid", red for "unpaid"
4. Verify the admin config UI saves and retrieves the threshold correctly
5. Verify the threshold is accessible in all 5 blade templates
6. Check that column alignment and styling match existing columns