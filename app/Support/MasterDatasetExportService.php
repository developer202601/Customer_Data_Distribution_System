<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterDatasetExportService
{
    private const EXPORT_COLUMNS = [
        'run_date_raw' => 'RUN_DATE',
        'dataset_month' => 'DATASET_MONTH',
        'region' => 'Region',
        'rtom' => 'RTOM',
        'customer_ref' => 'Customer Reference',
        'account_num' => 'Account Number',
        'product_label' => 'Product Label',
        'medium' => 'Medium',
        'customer_segment' => 'Customer Segment',
        'address_name' => 'Address Name',
        'full_address' => 'Full Address',
        'latest_bill_mny' => 'LATEST_BILL_MNY',
        'new_arrears_value' => 'ARREARS_VALUE',
        'new_arrears_column' => 'ARREARS_COLUMN',
        'mobile_contact_tel' => 'Mobile Contact',
        'email_address' => 'Email Address',
        'credit_score' => 'Credit Score',
        'credit_class_name' => 'Credit Class',
        'bill_handling_code_name' => 'Bill Handling Code Name',
        'age_months' => 'Age (Months)',
        'sales_person' => 'Sales Person',
        'account_manager' => 'Account Manager',
        'slt_gl_sub_segment' => 'GL Sub Segment',
        'billing_centre' => 'Billing Centre',
        'province' => 'Province',
        'next_bill_dtm' => 'Next Bill Date',
        'bill_month' => 'Bill Month',
        'latest_bill_dtm' => 'Latest Bill Date',
        'invoicing_co_id' => 'Invoicing Company ID',
        'invoicing_co_name' => 'Invoicing Company Name',
        'product_seq' => 'Product Sequence',
        'product_id' => 'Product ID',
        'latest_product_status' => 'Latest Product Status',
        'bill_handling_code' => 'Bill Handling Code',
        'slt_business_line_value' => 'Business Line Value',
        'sales_channel' => 'Sales Channel',
        'assigned_to' => 'Assigned To',
        'excluded' => 'Excluded',
        'exclusion_reason' => 'Exclusion Reason',
        'exclusion_priority' => 'Exclusion Priority',
    ];

    public function stream(
        MasterDatasetProcess $process,
        string $bucket,
        string $label,
        string $filename,
        Builder $query
    ): StreamedResponse {
        $spreadsheet = $this->buildSpreadsheet($process, $label, $query);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function storeToDisk(
        MasterDatasetProcess $process,
        string $label,
        Builder $query,
        Filesystem $disk,
        string $path
    ): void {
        $spreadsheet = $this->buildSpreadsheet($process, $label, $query);

        $tempFile = tempnam(sys_get_temp_dir(), 'export_');

        if ($tempFile === false) {
            $spreadsheet->disconnectWorksheets();
            throw new RuntimeException('Unable to allocate temporary storage while generating the export workbook.');
        }

        try {
            $writer = new Xlsx($spreadsheet);
            $writer->save($tempFile);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        $directory = trim(dirname($path), '/');
        if ($directory !== '' && $directory !== '.') {
            $disk->makeDirectory($directory);
        }

        $stream = fopen($tempFile, 'rb');

        if ($stream === false) {
            @unlink($tempFile);
            throw new RuntimeException('Unable to read the temporary export workbook.');
        }

        try {
            if (! $disk->put($path, $stream)) {
                throw new RuntimeException('Failed to persist the export workbook to storage.');
            }
        } finally {
            fclose($stream);
            @unlink($tempFile);
        }
    }

    private function buildSpreadsheet(MasterDatasetProcess $process, string $label, Builder $query): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->truncateSheetTitle($label));

        $sheet->fromArray(array_values(self::EXPORT_COLUMNS), null, 'A1');
        $sheet->freezePane('A2');

        $rowPointer = 2;

        (clone $query)->chunkById(500, function ($rows) use (&$rowPointer, $sheet, $process) {
            /** @var MasterDatasetRow $row */
            foreach ($rows as $row) {
                $sheet->fromArray($this->mapRow($process, $row), null, 'A' . $rowPointer);
                $rowPointer++;
            }
        });

        if ($rowPointer === 2) {
            $sheet->fromArray([], null, 'A2');
        }

        $dimension = $sheet->calculateWorksheetDimension();
        $sheet->setAutoFilter($dimension);

        return $spreadsheet;
    }

    private function mapRow(MasterDatasetProcess $process, MasterDatasetRow $row): array
    {
        $values = [];

        foreach (self::EXPORT_COLUMNS as $attribute => $label) {
            $values[] = $this->formatValue($process, $row, $attribute);
        }

        return $values;
    }

    private function formatValue(MasterDatasetProcess $process, MasterDatasetRow $row, string $attribute): mixed
    {
        if ($attribute === 'dataset_month') {
            return $process->dataset_month;
        }

        $value = $row->{$attribute};

        return match ($attribute) {
            'run_date_raw' => $value,
            'next_bill_dtm', 'latest_bill_dtm' => $this->formatDate($value),
            'latest_bill_mny', 'new_arrears_value' => $value !== null ? number_format((float) $value, 2, '.', '') : null,
            'credit_score' => $value !== null ? number_format((float) $value, 2, '.', '') : null,
            'excluded' => $row->excluded ? 'Yes' : 'No',
            default => $value,
        };
    }

    private function formatDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ? date('Y-m-d', $timestamp) : (string) $value;
    }

    private function truncateSheetTitle(string $title): string
    {
        $safe = trim($title) !== '' ? trim($title) : 'Sheet';
        $length = 31;

        if (function_exists('mb_substr')) {
            return mb_substr($safe, 0, $length);
        }

        return substr($safe, 0, $length);
    }
}
