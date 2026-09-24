# Add "Rs." Prefix to All Amount Displays in UI

## Scope
Add "Rs." currency prefix to all amount values displayed in the UI across all views. UI-only changes.

## Current State Analysis

### 1. Table Headers (already partially done)
| File | Line | Current | Target |
|------|------|---------|--------|
| `process/assignments/partials/overview-results.blade.php` | 25 | `Initial Outstanding (Rs.)` | Keep as-is ✓ |
| `process/assignments/partials/overview-results.blade.php` | 26 | `Current Outstanding` | Add "(Rs.)" |
| `regionalbilling/reports/_review_table.blade.php` | 138 | `Initial Outstanding` | Add "(Rs.)" |
| `regionalbilling/reports/_review_table.blade.php` | 139 | `Current Outstanding` | Add "(Rs.)" |
| `callcenter/caller/dashboard.blade.php` | 93-94 | `Initial Outstanding` / `Current Outstanding` | Add "(Rs.)" |
| `regionalbilling/caller/dashboard.blade.php` | 93-94 | `Initial Outstanding` / `Current Outstanding` | Add "(Rs.)" |

### 2. Table Cells (Blade templates with `number_format()`)
| File | Lines | Fields |
|------|-------|--------|
| `callcenter/caller/dashboard.blade.php` | 107, 108, 121 | new_arrears_value, outstanding, payments_value |
| `regionalbilling/caller/dashboard.blade.php` | 107, 108, 121 | new_arrears_value, outstanding, payments_value |
| `regionalbilling/reports/_review_table.blade.php` | 163, 164, 177 | new_arrears_value, outstanding, payments_value |
| `process/assignments/partials/overview-results.blade.php` | 38, 39 | new_arrears_value, outstanding |
| `cc/supervisor/dashboard.blade.php` | 122, 155 | paid_amount |
| `cc/rtom/dashboard.blade.php` | 123, 154 | paid_amount |
| `cc/segment/dashboard.blade.php` | 90, 152 | paid_amount |

### 3. Card View Templates (JS in assignment modals)
| File | Templates | Lines |
|------|-----------|-------|
| `callcenter/assignments/manage.blade.php` | Detail panel + History list | 580-583, 772-773 |
| `regionalbilling/assignments/manage.blade.php` | Detail panel + History list | 792-795, 979-980 |
| `callcenter/reports/index.blade.php` | Detail panel + History list | 633-636, 688 |

### 4. Interaction History (JS variables)
| File | Lines |
|------|-------|
| `callcenter/assignments/manage.blade.php` | 470, 547 (interaction.paid_amount, pay.paid_amount) |
| `regionalbilling/assignments/manage.blade.php` | 684, 759 (interaction.paid_amount, pay.paid_amount) |

### 5. Form Input (paid_amount)
| File | Line |
|------|------|
| `callcenter/assignments/manage.blade.php` | 316 |
| `regionalbilling/assignments/manage.blade.php` | (equivalent) |

## Target Format
- **Table headers**: Add "(Rs.)" suffix to label
- **Table cells / displayed values**: Prefix with "Rs. " (e.g., "Rs. 1,234.56")
- **Card view labels**: "Initial Outstanding: Rs. 1,234.56"
- **Form inputs**: Placeholder/label with "Rs."

## Implementation Approach
1. **Blade templates**: Wrap `number_format()` output with `"Rs. " . number_format(...)`
2. **JS templates**: Prepend "Rs. " to formatted display variables
3. **Headers**: Add "(Rs.)" to `<th>` text content
4. **Form labels**: Update label text

## Files to Modify (12 files)
1. `resources/views/process/assignments/partials/overview-results.blade.php` - 2 headers, 2 cells
2. `resources/views/regionalbilling/reports/_review_table.blade.php` - 2 headers, 3 cells
3. `resources/views/callcenter/caller/dashboard.blade.php` - 2 headers, 3 cells
4. `resources/views/regionalbilling/caller/dashboard.blade.php` - 2 headers, 3 cells
5. `resources/views/callcenter/assignments/manage.blade.php` - 2 JS templates + 2 history vars + 1 form
6. `resources/views/regionalbilling/assignments/manage.blade.php` - 2 JS templates + 2 history vars + 1 form
7. `resources/views/callcenter/reports/index.blade.php` - 2 JS templates
8. `resources/views/cc/supervisor/dashboard.blade.php` - 2 cells
9. `resources/views/cc/rtom/dashboard.blade.php` - 2 cells
10. `resources/views/cc/segment/dashboard.blade.php` - 2 cells

## Validation
- Check all amount displays show "Rs." prefix
- Verify thousand separators still work
- No duplicate "Rs." prefixes