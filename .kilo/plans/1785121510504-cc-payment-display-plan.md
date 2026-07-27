# Plan: Add Payment Display to CC Segments UI

## Context
The CC assignment manage view (`resources/views/callcenter/assignments/manage.blade.php`) omits the `payments_value` field from both the row list and the details modal summary. The RB equivalent (`resources/views/regionalbilling/assignments/manage.blade.php`) displays it. The backend controllers already return `payment_value` in both `userRows` and `assignmentDetails` endpoints — the gap is purely in the Blade templates.

## Changes

### 1. Add Payment row to assignment list (CC manage blade)
**File:** `resources/views/callcenter/assignments/manage.blade.php`

After line 736 (the Arrears/Bill line in the row template), add:
```
<div class="small text-danger">Payment: ${r.payment_value ?? '—'}</div>
```
This matches the RB pattern at line 957 of `regionalbilling/assignments/manage.blade.php`.

### 2. Add Payment to details modal summary (CC manage blade)
**File:** `resources/views/callcenter/assignments/manage.blade.php`

At line 576, change:
```
selAmt.textContent = `Arrears: ${data.arrears ?? '—'} — Bill: ${data.bill ?? '—'}` + ...
```
To:
```
const paymentValue = data.payment_value !== null && data.payment_value !== undefined ? Number(data.payment_value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
selAmt.textContent = `Arrears: ${data.arrears ?? '—'} — Bill: ${data.bill ?? '—'} — Payment: ${paymentValue}` + ...
```
This matches the RB pattern at lines 793-794 of `regionalbilling/assignments/manage.blade.php`.

## Verification
- Verify the CC manage view renders `Payment:` row in the assignment card list
- Verify the CC details modal summary line includes the payment amount
- Verify payment values align with what the `userRows` and `assignmentDetails` API endpoints return
- No controller changes needed — data is already returned