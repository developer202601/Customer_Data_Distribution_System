# OMNI Bill Payment API Integration

## Overview
Integrates external OMNI API to refresh bill payment data on the assignments page for both Call Center and Regional Billing modules.

## Architecture

### 1. Database Changes
**4 new columns on `master_dataset_rows`:**

| Column | Type | Purpose |
|--------|------|---------|
| `last_payment_amount` | decimal(12,2) | Latest payment amount from OMNI API |
| `last_payment_date` | date | Latest payment date from OMNI API |
| `outstanding_balance` | decimal(12,2) | `new_arrears_value - payments_value` |
| `last_api_fetched` | timestamp | When OMNI API was last called for this row |

### 2. Service Layer
**`app/Services/OmniBillPaymentService.php`:**
- Sends **GET** request to OMNI API (matches original curl `--request GET`)
- Parameters sent as query string: `telephoneNo`, `accountNo`, `secretCode`
- Uses `telephoneNo` (from `product_label`) and `accountNo` (from `account_num`)
- Includes `secretCode` from config (env-based)
- 10-second timeout with graceful error handling
- Parses API date format (YYYY/MM/DD → Y-m-d)
- Parses decimal amounts

### 3. Controller Layer
**`userRows()` method in both CallCenter and RegionalBilling AssignmentControllers:**

For each row:
1. **Deduplication check** — If `last_api_fetched` is within 30 seconds, skip API call
2. **API call** — Fetch bill payment data from OMNI
3. **Data extraction** — Get `lastPaymentAmount` and `lastPaymentDate` from response
4. **Duplicate check** — Skip if both date and amount match stored values
5. **Accumulation** — `payments_value = payments_value + lastPaymentAmount`
6. **Calculation** — `outstanding_balance = new_arrears_value - payments_value`
7. **Persist** — Update row with all new values

### 4. View Layer
**3 blade templates updated:**
- `callcenter/assignments/manage.blade.php`
- `regionalbilling/assignments/manage.blade.php`
- `callcenter/reports/index.blade.php`

Each row shows:
- **Spinner** (`omni-pending` badge) while loading
- **Green checkmark** (`omni-done` badge) when OMNI data is updated
- `outstanding_balance` read directly from DB (no client-side calculation)

**Auto-polling:** Every 30 seconds, the table refreshes to pick up new OMNI data. Pauses when tab is hidden.

## Data Flow
```
Page Load → JS fetches rows → Controller calls OMNI API per row
    → Deduplication (30s window) → API response → Accumulate payments
    → Calculate outstanding_balance → Persist to DB → Return updated rows
    → JS renders table with spinner → Green checkmark when done
    → Auto-poll every 30s → Repeat
```

## Key Behaviors
- **One API call per row per 30 seconds** — server-side deduplication prevents duplicate calls
- **Accumulation, not replacement** — `payments_value` grows with each new payment
- **Graceful fallback** — If API fails, DB data is used
- **No page refresh needed** — Auto-polling updates the table dynamically

## Files Modified
- `app/Models/MasterDatasetRow.php` — Added fillable + casts for 4 new columns
- `app/Http/Controllers/CallCenter/AssignmentController.php` — OMNI API integration in `userRows()`
- `app/Http/Controllers/RegionalBilling/AssignmentController.php` — OMNI API integration in `userRows()`
- `resources/views/callcenter/assignments/manage.blade.php` — Uses `outstanding_balance` from DB
- `resources/views/regionalbilling/assignments/manage.blade.php` — Uses `outstanding_balance` from DB
- `resources/views/callcenter/reports/index.blade.php` — Uses `outstanding_balance` from DB
- `resources/css/app.css` — Added spinner/green check animations

## Files Created
- `config/omni.php` — API configuration
- `app/Services/OmniBillPaymentService.php` — HTTP client with timeout, error handling, deduplication
- `database/migrations/2026_10_06_000001_add_omni_columns_to_master_dataset_rows.php` — 4 new columns

## Environment Variables
Add to `.env`:
```
OMNI_API_URL=https://omnitest.slt.com.lk/api/AccountOMNI/BillPaymentRequestDirect
OMNI_API_SECRET_CODE=A7kP9xQ2mN5vR8tdfsdhghfgh5fhgsdfdfdallslfugmdnd25336fghdGDFGdsmfc5yC0riyiyiopoe55f5g5h5k5lwergnddsdDFNJNVSDlkfdgssg66d66kl66jgfghfghgh5dfDGFDGDFGXCVDFDDxP
```