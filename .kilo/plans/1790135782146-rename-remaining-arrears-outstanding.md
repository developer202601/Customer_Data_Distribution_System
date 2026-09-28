# Rename Remaining Arrears/Outstanding Labels to Initial/Current Outstanding (UI Only)

## Scope
Rename display labels in Blade view files only. No backend, migration, or config changes.

## Target Changes

### Table Headers

| File | Line | Current | New |
|------|------|---------|-----|
| `resources/views/regionalbilling/reports/_review_table.blade.php` | 138 | `<th>Arrears</th>` | `<th>Initial Outstanding</th>` |
| `resources/views/regionalbilling/reports/_review_table.blade.php` | 139 | `<th>Outstanding</th>` | `<th>Current Outstanding</th>` |
| `resources/views/process/assignments/partials/overview-results.blade.php` | 25 | `<th scope="col" class="text-end">New Arrears (Rs.)</th>` | `<th scope="col" class="text-end">Initial Outstanding (Rs.)</th>` |
| `resources/views/process/assignments/partials/overview-results.blade.php` | 26 | `<th scope="col" class="text-end">Outstanding</th>` | `<th scope="col" class="text-end">Current Outstanding</th>` |

### Card Views (JS templates in assignment modal detail panels)

| File | Line | Current | New |
|------|------|---------|-----|
| `resources/views/callcenter/assignments/manage.blade.php` | 578 | `Arrears: ${data.arrears ?? '—'}` | `Initial Outstanding: ${data.arrears ?? '—'}` |
| `resources/views/callcenter/assignments/manage.blade.php` | 581 | `Outstanding: ${outstandingDisplay}` | `Current Outstanding: ${outstandingDisplay}` |
| `resources/views/callcenter/assignments/manage.blade.php` | 767 | `Arrears: ${r.arrears ?? '—'} — Bill: ${r.bill ?? '—'} — Outstanding: ${outstandingDisplay}` | `Initial Outstanding: ${r.arrears ?? '—'} — Bill: ${r.bill ?? '—'} — Current Outstanding: ${outstandingDisplay}` |
| `resources/views/regionalbilling/assignments/manage.blade.php` | 790 | `Arrears: ${data.arrears ?? '—'}` | `Initial Outstanding: ${data.arrears ?? '—'}` |
| `resources/views/regionalbilling/assignments/manage.blade.php` | 793 | `Outstanding: ${outstandingDisplay}` | `Current Outstanding: ${outstandingDisplay}` |
| `resources/views/regionalbilling/assignments/manage.blade.php` | 985 | `Arrears: ${r.arrears ?? '—'} — Bill: ${r.bill ?? '—'} — Outstanding: ${outstandingDisplay}` | `Initial Outstanding: ${r.arrears ?? '—'} — Bill: ${r.bill ?? '—'} — Current Outstanding: ${outstandingDisplay}` |
| `resources/views/callcenter/reports/index.blade.php` | 630 | `Arrears: ${data.arrears ?? '—'}` | `Initial Outstanding: ${data.arrears ?? '—'}` |
| `resources/views/callcenter/reports/index.blade.php` | 632 | `Outstanding: ${outstandingDisplay}` | `Current Outstanding: ${outstandingDisplay}` |
| `resources/views/callcenter/reports/index.blade.php` | 681 | `Arrears: ${r.arrears ?? '—'} — Bill: ${r.bill ?? '—'} — Outstanding: ${outstandingDisplay}` | `Initial Outstanding: ${r.arrears ?? '—'} — Bill: ${r.bill ?? '—'} — Current Outstanding: ${outstandingDisplay}` |

## Excluded (Do Not Change)
- `admin/adminconfig.blade.php` — "Outstanding Threshold" is a config setting name, not a column label
- `regionalbilling/reports/_review_table.blade.php:128` — search placeholder text, not a column label

## Notes
- Lines 767 (callcenter/assignments), 985 (regionalbilling/assignments), 681 (callcenter/reports) contain both labels in a single string — update both occurrences.
- All changes are string literal replacements in Blade templates.

## Validation
- Grep for `\bArrears\b` and `\bOutstanding\b` in `resources/views/` to confirm no unintended matches remain (excluding admin config and search placeholder).