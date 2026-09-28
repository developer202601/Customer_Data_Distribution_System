# Fix Modal Z-Index Below Navbar

## Problem
The header bar (`.main-header.navbar`) has `z-index: 1200 !important` but Bootstrap modal defaults are:
- `.modal-backdrop`: 1050
- `.modal`: 1060

Result: Navbar renders over modal when they overlap.

## Root Cause
No modal z-index override in `resources/css/app.css`. Navbar was raised to 1200 to sit above offcanvas (1150), but modal wasn't adjusted accordingly.

## Solution
Add modal z-index overrides in `resources/css/app.css` to be above navbar:

```css
.modal-backdrop {
    z-index: 1205 !important; /* above navbar (1200) */
}

.modal {
    z-index: 1210 !important; /* above backdrop */
}
```

## Files to Modify
- `resources/css/app.css` — add modal z-index rules (after navbar/offcanvas section around line 308)

## Validation
1. Open any assignment modal
2. Scroll so modal overlaps header bar
3. Verify modal content is above header, not behind it
4. Verify backdrop also covers header
5. Test on both Call Center and Regional Billing assignment pages

## Risk
- Low: Only affects modal stacking context
- No JavaScript changes needed
- Offcanvas (1150) still below navbar (1200) — correct behavior preserved