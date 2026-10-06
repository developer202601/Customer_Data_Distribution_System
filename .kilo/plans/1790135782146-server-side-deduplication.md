# Server-Side OMNI API Deduplication (30-second window)

## Problem
Multiple page fetches within 30 seconds trigger multiple OMNI API calls to the same row, wasting API quota and potentially causing race conditions.

## Solution
Add a server-side time-based check before calling the OMNI API. If `last_api_fetched` is within the last 30 seconds, skip the API call entirely.

## Implementation

### 1. CallCenter AssignmentController.php (line 474-502)
Replace the OMNI API block with deduplication logic:

```php
if ($telephoneNo || $accountNo) {
    // Skip API call if fetched within last 30 seconds
    $lastFetched = $row->last_api_fetched;
    $shouldSkip = false;
    if ($lastFetched) {
        try {
            $fetchedAt = Carbon::parse($lastFetched);
            $shouldSkip = $fetchedAt->gt(Carbon::now()->subSeconds(30));
        } catch (\Exception $e) {
            // ignore parse error, proceed with API call
        }
    }

    if (! $shouldSkip) {
        $apiResponse = $omniService->fetchBillPayment($telephoneNo, $accountNo);
        $billingData = $omniService->extractBillingData($apiResponse);

        if ($billingData) {
            $apiLastPaymentAmount = OmniBillPaymentService::parseAmount($billingData['lastPaymentAmount'] ?? null);
            $apiLastPaymentDate = OmniBillPaymentService::parseApiDate($billingData['lastPaymentDate'] ?? null);

            // Deduplication: skip if both date and amount match stored values
            $isDuplicate = ($apiLastPaymentAmount === $lastPaymentAmount && $apiLastPaymentDate === $lastPaymentDate);

            if (! $isDuplicate && $apiLastPaymentAmount !== null) {
                $paymentsValue = ($paymentsValue ?? 0) + $apiLastPaymentAmount;
                $outstandingBalance = ($newArrearsValue ?? 0) - $paymentsValue;

                $row->update([
                    'payments_value' => $paymentsValue,
                    'last_payment_amount' => $apiLastPaymentAmount,
                    'last_payment_date' => $apiLastPaymentDate,
                    'outstanding_balance' => $outstandingBalance,
                    'last_api_fetched' => now(),
                ]);
            } else {
                // Even if duplicate, update last_api_fetched to track fetch time
                $row->update(['last_api_fetched' => now()]);
            }
        }
    }
}
```

### 2. RegionalBilling AssignmentController.php (line 547-575)
Apply the same deduplication logic.

## Files to Modify
1. `app/Http/Controllers/CallCenter/AssignmentController.php` — lines 474-502
2. `app/Http/Controllers/RegionalBilling/AssignmentController.php` — lines 547-575

## Validation
- Check that OMNI API is only called once per row per 30-second window
- Verify that `last_api_fetched` is updated after each successful API call
- Confirm that rows fetched within 30 seconds skip the API call