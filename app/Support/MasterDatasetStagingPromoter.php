<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MasterDatasetStagingPromoter
{
    private const DUPLICATE_ROW_CONSTRAINT = 'mdr_process_run_product_unique';

    /**
     * Promote rows from the staging table into the main dataset rows table.
     */
    public function promote(MasterDatasetProcess $process): array
    {
        $rowCount = DB::table('master_dataset_rows_staging')
            ->where('process_id', $process->id)
            ->count();

        if ($rowCount === 0) {
            return ['promoted' => 0];
        }

        $this->assertNoDuplicateCompositeRows($process);

        $columns = $this->columnList();

        DB::transaction(function () use ($process, $columns) {
            DB::table('master_dataset_rows_staging')
                ->where('process_id', $process->id)
                ->orderBy('id')
                ->chunkById(1000, function ($rows) use ($process, $columns) {
                    $payload = [];
                    $ids = [];
                    $now = now();

                    foreach ($rows as $row) {
                        $ids[] = $row->id;
                        $record = ['process_id' => $process->id];

                        foreach ($columns as $column) {
                            $record[$column] = $row->{$column} ?? null;
                        }

                        $record['created_at'] = $now;
                        $record['updated_at'] = $now;
                        $payload[] = $record;
                    }

                    if (! empty($payload)) {
                        try {
                            MasterDatasetRow::insert($payload);
                        } catch (QueryException $exception) {
                            if ($this->isDuplicateCompositeKeyViolation($exception)) {
                                throw ValidationException::withMessages([
                                    'upload' => [
                                        'Duplicate combination found for RUN_DATE/PRODUCT_LABEL/ACCOUNT_NUM. '
                                        . 'Please remove duplicates and re-upload the master file.',
                                    ],
                                ]);
                            }

                            throw $exception;
                        }
                    }

                    DB::table('master_dataset_rows_staging')->whereIn('id', $ids)->delete();
                });
        });

        return ['promoted' => $rowCount];
    }

    private function assertNoDuplicateCompositeRows(MasterDatasetProcess $process): void
    {
        $seen = [];
        $firstConflict = null;

        DB::table('master_dataset_rows_staging')
            ->where('process_id', $process->id)
            ->orderBy('id')
            ->select(['id', 'product_label', 'payload'])
            ->chunkById(1000, function ($rows) use (&$seen, &$firstConflict) {
                foreach ($rows as $row) {
                    $productLabel = trim((string) ($row->product_label ?? ''));

                    if ($productLabel === '') {
                        continue;
                    }

                    $key = strtolower($productLabel);
                    $rowNumber = $this->sourceRowNumber($row);

                    if (! isset($seen[$key])) {
                        $seen[$key] = [
                            'row_number' => $rowNumber,
                            'staging_id' => (int) $row->id,
                        ];
                        continue;
                    }

                    $firstConflict = [
                        'existing' => $seen[$key],
                        'duplicate' => [
                            'row_number' => $rowNumber,
                            'staging_id' => (int) $row->id,
                        ],
                    ];

                    return false;
                }

                return true;
            });

        if ($firstConflict !== null) {
            $existingLabel = $this->formatRowLabel($firstConflict['existing']);
            $duplicateLabel = $this->formatRowLabel($firstConflict['duplicate']);

            throw ValidationException::withMessages([
                'upload' => [
                    sprintf(
                        'Duplicate value found for PRODUCT_LABEL at %s; repeated at %s.',
                        $existingLabel,
                        $duplicateLabel
                    ),
                ],
            ]);
        }
    }

    private function sourceRowNumber(object $row): ?int
    {
        $payload = $row->payload ?? null;
        if (! is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            return null;
        }

        foreach (['excel_row', 'source_row', 'row_number', 'row'] as $key) {
            $value = $decoded[$key] ?? null;
            if (is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return null;
    }

    private function formatRowLabel(array $entry): string
    {
        $rowNumber = $entry['row_number'] ?? null;
        if (is_int($rowNumber) && $rowNumber > 0) {
            return 'row ' . $rowNumber;
        }

        return 'staging record #' . (int) ($entry['staging_id'] ?? 0);
    }

    private function isDuplicateCompositeKeyViolation(QueryException $exception): bool
    {
        $errorInfo = $exception->errorInfo;
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $message = strtolower((string) ($errorInfo[2] ?? $exception->getMessage()));

        if ($driverCode !== 1062) {
            return false;
        }

        return str_contains($message, strtolower(self::DUPLICATE_ROW_CONSTRAINT));
    }

    private function columnList(): array
    {
        return [
            'run_date',
            'run_date_raw',
            'region',
            'rtom',
            'customer_ref',
            'account_num',
            'installment',
            'account_status',
            'acct_effect_dtm',
            'bill_seq',
            'product_label',
            'medium',
            'customer_segment',
            'address_name',
            'full_address',
            'latest_bill_mny',
            'new_arrears_value',
            'new_arrears_column',
            'payments_value',
            'payments_column',
            'new_arrears_secondary_value',
            'new_arrears_secondary_column',
            'mobile_contact_tel',
            'email_address',
            'credit_score',
            'credit_class_id',
            'credit_class_name',
            'bill_handling_code_name',
            'age_months',
            'sales_person',
            'account_manager',
            'slt_gl_sub_segment',
            'billing_centre',
            'province',
            'next_bill_dtm',
            'payment_due_dat',
            'bill_month',
            'latest_bill_dtm',
            'invoicing_co_id',
            'invoicing_co_name',
            'product_seq',
            'product_id',
            'product_name',
            'start_dat',
            'end_dat',
            'latest_product_status',
            'latest_effective_dtm',
            'bill_handling_code',
            'phone_number',
            'slt_business_line_value',
            'sales_channel',
            'excluded',
            'exclusion_reason',
            'exclusion_priority',
            'assigned_to',
        ];
    }
}
