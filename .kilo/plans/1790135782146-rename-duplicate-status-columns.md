# Rename Duplicate "Status" Column Headers (UI Only)

## Scope
Rename duplicate "Status" column headers in 3 table views to be descriptive and unambiguous.

## Target Changes

### 1. `resources/views/callcenter/caller/dashboard.blade.php`
| Line | Current | New |
|------|---------|-----|
| 95 | `<th>Status</th>` (payment) | `<th>Payment Status</th>` |
| 97 | `<th>Status</th>` (assignment) | `<th>Assignment Status</th>` |

### 2. `resources/views/regionalbilling/caller/dashboard.blade.php`
| Line | Current | New |
|------|---------|-----|
| 95 | `<th>Status</th>` (payment) | `<th>Payment Status</th>` |
| 97 | `<th>Status</th>` (assignment) | `<th>Assignment Status</th>` |

### 3. `resources/views/regionalbilling/reports/_review_table.blade.php`
| Line | Current | New |
|------|---------|-----|
| 140 | `<th>Status</th>` (payment) | `<th>Payment Status</th>` |
| 144 | `<th>Status</th>` (visibility) | `<th>Visibility</th>` |

## Validation
- Open each affected page and verify column headers are unique and descriptive
- Verify no other "Status" headers are unintentionally changed
- Search for `<th>Status</th>` in views to confirm only intended ones remain (e.g., single-status tables)

## Notes
- No data/logic changes — only header text
- The corresponding `<td>` content remains unchanged (badges, assignment status, visibility badges)
- Single-"Status" tables in other views (e.g., `regionalbilling/users/index.blade.php`) are not affected