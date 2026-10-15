# OMNI Bill Payment API Integration

## Overview
Integrate external OMNI API to refresh bill payment data on the assignments page for both Call Center and Regional Billing modules.

## Requirements
| Requirement | Decision |
|-------------|----------|
| Integration point | Assignments page (callcenter + regionalbilling) |
| Trigger | On page load |
| Input params | telephoneNo and accountNo derived from existing row data |
| Output handling | Update row data (arrears, outstanding, etc.) and refresh table |
| Security | secretCode stored in env vars |
| Module scope | Both callcenter and regionalbilling |

## API Response Format
```json
{
  "isSuccess": false,
  "errorMessege": "Erro 002",
  "exceptionDetail": null,
  "dataBundle": {
    "listofbillingInquiryType": [
      {
        "billAmount": "28779.89",
        "lastBillDate": "2026/09/25",
        "paymentDueDate": "2026/10/16",
        "lastPaymentDate": "2026/09/28",
        "lastPaymentAmount": "24570.00",
        "outstandingBalance": "28779.56"
      }
    ]
  },
  "errorShow": "Sorry! no data found",
  "errorCode": null
}
```

## Field Mapping
| API Field | DB Column | Action |
|-----------|-----------|--------|
| `billAmount` | `latest_bill_mny` | SKIP — already exists, do not update |
| `lastBillDate` | `latest_bill_dtm` | SKIP — already exists, do not update |
| `paymentDueDate` | `payment_due_dat` | SKIP — already exists, do not update |
| `lastPaymentAmount` | `last_payment_amount` | NEW column |
| `lastPaymentDate` | `last_payment_date` | NEW column |
| `outstandingBalance` | `outstanding_balance` | NEW column |

## Database Changes
### New Migration: `2026_10_06_000001_add_omni_columns_to_master_dataset_rows.php`
Add 4 new nullable columns to `master_dataset_rows`:
- `last_payment_amount` (decimal:12,2, nullable)
- `last_payment_date` (date, nullable)
- `outstanding_balance` (decimal:12,2, nullable)
- `last_api_fetched` (timestamp, nullable) — set to `now()` on each successful API call

### Model Updates
Update `app/Models/MasterDatasetRow.php`:
- Add `last_payment_amount`, `last_payment_date`, `outstanding_balance`, `last_api_fetched` to `$fillable`
- Add casts for `last_payment_date` (date), `last_payment_amount` (decimal:2), `outstanding_balance` (decimal:2), `last_api_fetched` (datetime)

## Calculation Logic
- `payments_value` = `payments_value + lastPaymentAmount` (accumulate, not replace)
- Deduplication: skip accumulation if `lastPaymentDate` AND `lastPaymentAmount` match stored values
- `outstanding_balance` = `new_arrears_value - payments_value` (calculated and stored in DB when payments_value changes)
- UI retrieves `outstanding_balance` directly from DB — no client-side calculation

## Files to Create
1. `config/omni.php` - API configuration
2. `app/Services/OmniBillPaymentService.php` - HTTP client
3. `database/migrations/2026_10_06_000001_add_omni_columns_to_master_dataset_rows.php`

## Files to Modify
1. `.env` - Add OMNI env vars
2. `app/Models/MasterDatasetRow.php` - Add fillable + casts
3. `app/Http/Controllers/CallCenter/AssignmentController.php` - Integrate API call in `rows()` method
4. `app/Http/Controllers/RegionalBilling/AssignmentController.php` - Integrate API call in `rows()` method
5. `resources/views/callcenter/assignments/manage.blade.php` - Trigger fetch on load
6. `resources/views/regionalbilling/assignments/manage.blade.php` - Trigger fetch on load
7. `resources/views/callcenter/reports/index.blade.php` - Trigger fetch on load

## Data Flow
1. Page loads -> JS calls `loadAssignments()`
2. Controller fetches assignment rows from DB
3. For each row, controller calls Omni API with `telephoneNo` (from `mobile_contact_tel`) + `accountNo` (from `account_num`)
4. Controller merges API response into row data:
   - `lastPaymentAmount` -> `last_payment_amount` (new column)
   - `lastPaymentDate` -> `last_payment_date` (new column)
   - `outstandingBalance` -> `outstanding_balance` (new column)
   - `last_api_fetched` -> `now()` (set on each successful API call)
   - Deduplication check: if `lastPaymentDate` AND `lastPaymentAmount` match stored values, skip accumulation
   - If not duplicate: `payments_value = payments_value + lastPaymentAmount` (accumulate)
   - `outstanding_balance = new_arrears_value - payments_value` (calculated and stored in DB)
   - SKIP: `billAmount`, `lastBillDate`, `paymentDueDate` (already exist, do not update)
5. Controller persists updated row data to DB
6. Controller returns updated rows as JSON (including `outstanding_balance`)
7. JS renders refreshed table, reading `outstanding_balance` directly from row data

## UI Changes
- Current Outstanding display uses `outstanding_balance` from DB directly (no client-side calculation)
- Display `last_payment_date` and `last_payment_amount` in the row detail panel
- All existing views that previously computed `new_arrears_value - payments_value` should switch to reading `outstanding_balance` from the row data

## Error Handling
- API timeout: 10 seconds, fall back to DB data
- API non-200: log error, fall back to DB data
- API invalid JSON: log error, fall back to DB data
- Missing telephoneNo/accountNo: skip API call for that row
- `isSuccess: false`: log error, fall back to DB data
- All API failures: table still renders with DB data

## Security
- `secretCode` stored in `.env` as `OMNI_API_SECRET_CODE`
- Never committed to codebase
- Add `.env` to gitignore if not already

## Validation
1. Open assignments page in callcenter module -> verify table loads with API data
2. Open assignments page in regionalbilling module -> verify table loads with API data
3. Open reports page -> verify table loads with API data
4. Check network tab -> verify OMNI API calls are made
5. Check console for errors -> verify graceful fallback works
6. Verify env vars are not committed to git