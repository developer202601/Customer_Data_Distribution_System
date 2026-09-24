# Add Thousand Separators to Amount Values in Card View Templates (UI Only)

## Scope
Fix JavaScript templates in assignment modal detail panels where `arrears`, `bill`, and `payment_value` are displayed without formatting.

## Root Cause
In the JS templates, `outstandingDisplay` uses `.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })` but `arrears`, `bill`, and `payment_value` are interpolated directly as raw values.

## Target Changes

### 1. `resources/views/callcenter/assignments/manage.blade.php`

**First card template (lines 565-593):**
- Line 568: Add `paymentValue` formatting (already done correctly)
- Line 578: `${data.arrears ?? '—'}` → format with `toLocaleString`
- Line 579: `${data.bill ?? '—'}` → format with `toLocaleString`
- Line 580: `${paymentValue}` — already formatted
- Line 581: `${outstandingDisplay}` — already formatted

**Second template - interaction history list (lines 763-780):**
- Line 767: `${r.arrears ?? '—'}` → format
- Line 767: `${r.bill ?? '—'}` → format
- Line 767: `${outstandingDisplay}` — already formatted
- Line 768: `${r.payment_value ?? '—'}` → format

### 2. `resources/views/regionalbilling/assignments/manage.blade.php`

**First card template (lines 778-806):**
- Line 790: `${data.arrears ?? '—'}` → format
- Line 791: `${data.bill ?? '—'}` → format
- Line 792: `${paymentValue}` — already formatted
- Line 793: `${outstandingDisplay}` — already formatted

**Second template - interaction history list (lines 970-987):**
- Line 974: `${r.arrears ?? '—'}` → format
- Line 974: `${r.bill ?? '—'}` → format
- Line 974: `${outstandingDisplay}` — already formatted
- Line 975: `${r.payment_value ?? '—'}` → format

### 3. `resources/views/callcenter/reports/index.blade.php`

**First card template (lines 620-643):**
- Line 630: `${data.arrears ?? '—'}` → format
- Line 631: `${data.bill ?? '—'}` → format
- Line 632: `${outstandingDisplay}` — already formatted
- Missing: `paymentValue` formatting (not currently present)

**Second template - interaction history list (lines 681):**
- Line 681: `${r.arrears ?? '—'}` → format
- Line 681: `${r.bill ?? '—'}` → format
- Line 681: `${outstandingDisplay}` — already formatted
- Missing: `r.payment_value` formatting

## Implementation Pattern

Create helper variables before each template using the same pattern:
```javascript
const arrearsDisplay = data.arrears !== null && data.arrears !== undefined 
    ? Number(data.arrears).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) 
    : '—';
const billDisplay = data.bill !== null && data.bill !== undefined 
    ? Number(data.bill).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) 
    : '—';
const paymentDisplay = data.payment_value !== null && data.payment_value !== undefined 
    ? Number(data.payment_value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) 
    : '—';
```

Then use `${arrearsDisplay}`, `${billDisplay}`, `${paymentDisplay}` in templates.

## Validation
- Open assignment modal, select a row with large values (e.g., 10000+)
- Verify all amount fields show thousand separators (e.g., "10,000.00")
- Check all 3 files and both template types (detail panel + interaction history list)