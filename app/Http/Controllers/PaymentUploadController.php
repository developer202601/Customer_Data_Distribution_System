<?php

namespace App\Http\Controllers;

use App\Models\MasterDatasetRow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class PaymentUploadController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'upload' => 'required|file|mimes:xlsx|max:51200',
        ]);

        $file = $data['upload'];

        try {
            $rows = Excel::toArray(new \App\Imports\FileDataImport, $file);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'upload' => 'Unable to read the Excel file. Please ensure it is a valid .xlsx workbook.',
            ]);
        }

        if (empty($rows)) {
            throw ValidationException::withMessages([
                'upload' => 'The Excel file appears to be empty.',
            ]);
        }

        $headerRow = $rows[0];
        $dataRows = array_slice($rows, 1);

        if (empty($dataRows) || empty($headerRow)) {
            throw ValidationException::withMessages([
                'upload' => 'The Excel file contains no data rows.',
            ]);
        }

        $headers = array_map(function ($h) {
            return strtoupper(trim((string) $h));
        }, $headerRow[0] ?? []);

        $headerMap = [];
        foreach ($headers as $index => $header) {
            $headerMap[$header] = $index;
        }

        $accountNumIndex = $this->findHeaderIndex($headerMap, [
            'ACCOUNT_NUM',
        ]);
        $paymentValueIndex = $this->findHeaderIndex($headerMap, [
            'ACCOUNT_PAYMENT_MNY',
        ]);
        $paymentDateIndex = $this->findHeaderIndex($headerMap, [
            'PAYMENT_DATE',
        ]);

        if ($accountNumIndex === null) {
            throw ValidationException::withMessages([
                'upload' => 'The Excel file must contain an account number column (e.g., ACCOUNT_NUM).',
            ]);
        }

        $matched = 0;
        $updated = 0;
        $notFound = 0;

        DB::beginTransaction();
        try {
            foreach ($dataRows as $row) {
                if (empty($row[$accountNumIndex])) {
                    continue;
                }

                $accountNum = trim((string) $row[$accountNumIndex]);
                if ($accountNum === '') {
                    continue;
                }

                $matched++;

                $masterRow = MasterDatasetRow::where('account_num', $accountNum)->first();

                if (! $masterRow) {
                    $notFound++;
                    continue;
                }

                $updateData = [];

                if ($paymentValueIndex !== null && isset($row[$paymentValueIndex])) {
                    $value = trim((string) $row[$paymentValueIndex]);
                    if ($value !== '' && $value !== '-') {
                        $numeric = str_replace([',', ' '], '', $value);
                        if (is_numeric($numeric)) {
                            $updateData['payments_value'] = (float) $numeric;
                        }
                    }
                }

                if ($paymentDateIndex !== null && isset($row[$paymentDateIndex])) {
                    $dateValue = trim((string) $row[$paymentDateIndex]);
                    if ($dateValue !== '') {
                        $parsed = \Carbon\Carbon::createFromFormat('Y-m-d', $dateValue);
                        if ($parsed) {
                            $updateData['payment_date'] = $parsed;
                        }
                    }
                }

                if (!empty($updateData)) {
                    $masterRow->update($updateData);
                    $updated++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw ValidationException::withMessages([
                'upload' => 'An error occurred while processing payments: ' . $e->getMessage(),
            ]);
        }

        $request->session()->flash('payment_status', [
            'matched' => $matched,
            'updated' => $updated,
            'not_found' => $notFound,
            'message' => "Payment upload completed. Matched: {$matched}, Updated: {$updated}, Not found: {$notFound}",
        ]);

        return redirect()->route('payment.upload');
    }

    private function findHeaderIndex(array $headerMap, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $normalised = strtoupper(trim((string) $candidate));
            if (isset($headerMap[$normalised])) {
                return $headerMap[$normalised];
            }
        }

        foreach ($headerMap as $header => $index) {
            foreach ($candidates as $candidate) {
                $normalised = strtoupper(trim((string) $candidate));
                if (str_starts_with($header, $normalised)) {
                    return $index;
                }
            }
        }

        return null;
    }
}
