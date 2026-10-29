<?php

namespace App\Services;

use App\Models\MasterDatasetRow;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OmniBillPaymentService
{
    private string $apiUrl;

    private string $clientKey;

    private int $timeout;

    private bool $verifySsl;

    /**
     * Seconds since last_api_fetched during which a page-view request may skip the API.
     */
    public const FRESHNESS_WINDOW_SECONDS = 25;

    public function __construct()
    {
        $this->apiUrl = config('omni.api_url', '');
        $this->clientKey = config('omni.client_key', '');
        $this->timeout = config('omni.timeout', 10);
        $this->verifySsl = config('omni.verify_ssl', true);
    }

    /**
     * Fetch bill payment data from OMNI API.
     *
     * @return array|null Decoded JSON response, or null on failure
     */
    public function fetchBillPayment(?string $telephoneNo, ?string $accountNo): ?array
    {
        if (empty($this->apiUrl) || empty($this->clientKey)) {
            \Log::error('OMNI API configuration missing', [
                'api_url' => $this->apiUrl,
                'has_client_key' => ! empty($this->clientKey),
            ]);

            return null;
        }

        // Ensure telephone number has a leading 0 for Sri Lankan numbers
        if ($telephoneNo !== null && $telephoneNo !== '') {
            $telephoneNo = (string) $telephoneNo;
            if (! str_starts_with($telephoneNo, '0') && ctype_digit($telephoneNo)) {
                $telephoneNo = '0'.$telephoneNo;
            }
        }

        $query = [
            'telephoneNo' => $telephoneNo ?? '',
            'accountNo' => $accountNo ?? '',
        ];

        try {
            $request = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'ClientKey' => $this->clientKey,
                ]);

            if (! $this->verifySsl) {
                $request = $request->withoutVerifying();
            }

            $response = $request->get($this->apiUrl, $query);

            if (! $response->successful()) {
                \Log::error('OMNI API non-200 response', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'telephoneNo' => $telephoneNo,
                    'accountNo' => $accountNo,
                ]);

                return null;
            }

            $data = $response->json();

            if (! is_array($data)) {
                \Log::error('OMNI API invalid JSON response', [
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $data;
        } catch (ConnectionException $e) {
            \Log::error('OMNI API connection error', [
                'message' => $e->getMessage(),
                'telephoneNo' => $telephoneNo,
                'accountNo' => $accountNo,
            ]);

            return null;
        } catch (RequestException $e) {
            \Log::error('OMNI API request error', [
                'message' => $e->getMessage(),
                'telephoneNo' => $telephoneNo,
                'accountNo' => $accountNo,
            ]);

            return null;
        } catch (\Exception $e) {
            \Log::error('OMNI API unexpected error', [
                'message' => $e->getMessage(),
                'telephoneNo' => $telephoneNo,
                'accountNo' => $accountNo,
            ]);

            return null;
        }
    }

    /**
     * Extract billing inquiry data from API response.
     *
     * @return array|null First item from listofbillingInquiryType, or null
     */
    public function extractBillingData(?array $apiResponse): ?array
    {
        if (! is_array($apiResponse)) {
            return null;
        }

        if (! empty($apiResponse['isSuccess']) && $apiResponse['isSuccess'] !== false) {
            // API reports success
        }

        $bundle = $apiResponse['dataBundle'] ?? null;
        if (! is_array($bundle)) {
            return null;
        }

        $list = $bundle['listofbillingInquiryType'] ?? null;
        if (! is_array($list) || empty($list)) {
            return null;
        }

        return $list[0] ?? null;
    }

    /**
     * Parse a date string from API format (YYYY/MM/DD) to Y-m-d.
     */
    public static function parseApiDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $dateStr = trim($dateStr);
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $dateStr, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr, $m)) {
            return $dateStr;
        }

        return null;
    }

    /**
     * Parse a decimal amount from API response.
     *
     * @param  string|float|int|null  $amount
     */
    public static function parseAmount($amount): ?float
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        if (is_numeric($amount)) {
            return (float) $amount;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $amount);
        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }

    /**
     * Refresh a single row's bill-payment data from the OMNI API.
     *
     * Mirrors the per-row logic embedded in the CallCenter and RegionalBilling
     * AssignmentController::userRows() methods so the nightly cron job and the
     * UI share identical behaviour.
     *
     * @param  bool  $skipFreshness  Bypass the last_api_fetched freshness skip (used by batch jobs).
     * @return string updated|skipped_duplicate|skipped_no_change|skipped_fresh|skipped_no_identifiers|api_error|skipped_no_response
     */
    public function refreshRow(MasterDatasetRow $row, bool $skipFreshness = false): string
    {
        $lastPaymentAmount = $row->last_payment_amount;
        $lastPaymentDate = $row->last_payment_date;
        $paymentsValue = $row->payments_value;
        $newArrearsValue = $row->new_arrears_value;

        $telephoneNo = $row->product_label ?? null;
        $accountNo = $row->account_num ?? $row->customer_ref ?? null;

        if (! $telephoneNo && ! $accountNo) {
            return 'skipped_no_identifiers';
        }

        if (! $skipFreshness) {
            $lastFetched = $row->last_api_fetched;
            if ($lastFetched) {
                try {
                    $fetchedAt = Carbon::parse($lastFetched);
                    $window = (int) config('omni.freshness_window_seconds', self::FRESHNESS_WINDOW_SECONDS);
                    if ($fetchedAt->gt(Carbon::now()->subSeconds($window))) {
                        return 'skipped_fresh';
                    }
                } catch (\Exception $e) {
                    // ignore parse error, proceed with API call
                }
            }
        }

        $apiResponse = $this->fetchBillPayment($telephoneNo, $accountNo);
        $billingData = $this->extractBillingData($apiResponse);

        if (! $billingData) {
            return $apiResponse === null ? 'api_error' : 'skipped_no_response';
        }

        $apiLastPaymentAmount = self::parseAmount($billingData['lastPaymentAmount'] ?? null);
        $apiLastPaymentDate = self::parseApiDate($billingData['lastPaymentDate'] ?? null);

        $storedAmount = $lastPaymentAmount !== null ? round((float) $lastPaymentAmount, 2) : null;
        $storedDate = $lastPaymentDate !== null ? Carbon::parse($lastPaymentDate)->toDateString() : null;

        $isDuplicate = ($apiLastPaymentAmount === $storedAmount && $apiLastPaymentDate === $storedDate);

        if (! $isDuplicate && $apiLastPaymentAmount !== null && $apiLastPaymentDate !== null && ($storedDate === null || $apiLastPaymentDate > $storedDate)) {
            $paymentsValue = ($paymentsValue ?? 0) + $apiLastPaymentAmount;
            $outstandingBalance = ($newArrearsValue ?? 0) - $paymentsValue;

            $row->update([
                'payments_value' => $paymentsValue,
                'last_payment_amount' => $apiLastPaymentAmount,
                'last_payment_date' => $apiLastPaymentDate,
                'outstanding_balance' => $outstandingBalance,
                'last_api_fetched' => now(),
            ]);

            return 'updated';
        }

        // Fetched successfully but no newer/unique payment to apply: record the fetch time.
        $row->update(['last_api_fetched' => now()]);

        return $isDuplicate ? 'skipped_duplicate' : 'skipped_no_change';
    }
}
