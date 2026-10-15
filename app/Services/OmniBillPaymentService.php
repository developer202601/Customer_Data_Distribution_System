<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Logger;

class OmniBillPaymentService
{
    private string $apiUrl;

    private string $secretCode;

    private int $timeout;

    private bool $verifySsl;

    public function __construct()
    {
        $this->apiUrl = config('omni.api_url', '');
        $this->secretCode = config('omni.secret_code', '');
        $this->timeout = config('omni.timeout', 10);
        $this->verifySsl = config('omni.verify_ssl', true);
    }

    /**
     * Fetch bill payment data from OMNI API.
     *
     * @param string|null $telephoneNo
     * @param string|null $accountNo
     * @return array|null Decoded JSON response, or null on failure
     */
    public function fetchBillPayment(?string $telephoneNo, ?string $accountNo): ?array
    {
        if (empty($this->apiUrl) || empty($this->secretCode)) {
            \Log::error('OMNI API configuration missing', [
                'api_url' => $this->apiUrl,
                'has_secret' => ! empty($this->secretCode),
            ]);
            return null;
        }

        // Ensure telephone number has a leading 0 for Sri Lankan numbers
        if ($telephoneNo !== null && $telephoneNo !== '') {
            $telephoneNo = (string) $telephoneNo;
            if (! str_starts_with($telephoneNo, '0') && ctype_digit($telephoneNo)) {
                $telephoneNo = '0' . $telephoneNo;
            }
        }

        try {
            $request = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]);

            if (! $this->verifySsl) {
                $request = $request->withoutVerifying();
            }

            $response = $request->send('GET', $this->apiUrl, [
                'body' => http_build_query([
                    'telephoneNo' => $telephoneNo ?? '',
                    'accountNo'   => $accountNo ?? '',
                    'secretCode'  => $this->secretCode,
                ]),
            ]);

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
     * @param array|null $apiResponse
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
     *
     * @param string|null $dateStr
     * @return string|null
     */
    public static function parseApiDate(?string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        $dateStr = trim($dateStr);
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $dateStr, $m)) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateStr, $m)) {
            return $dateStr;
        }

        return null;
    }

    /**
     * Parse a decimal amount from API response.
     *
     * @param string|float|int|null $amount
     * @return float|null
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
}