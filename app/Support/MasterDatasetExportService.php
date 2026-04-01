<?php

namespace App\Support;

use OpenSpout\Writer\XLSX\Writer as SpoutXlsxWriter;
use OpenSpout\Writer\CSV\Writer as SpoutCsvWriter;
use OpenSpout\Writer\WriterInterface as SpoutWriterInterface;
use OpenSpout\Common\Entity\Row as SpoutRow;
use OpenSpout\Common\Entity\Cell as SpoutCell;
use OpenSpout\Reader\CSV\Reader as SpoutCsvReader;
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
        /**
     * Store export to disk using Spout for large datasets (XLSX or CSV).
     *
     * @param MasterDatasetProcess $process
     * @param string $label
     * @param Builder $query
     * @param Filesystem $disk
     * @param string $path
     * @param string $format 'xlsx' or 'csv'
     */
    public function storeToDiskWithSpout(
        MasterDatasetProcess $process,
        string $label,
        Builder $query,
        Filesystem $disk,
        string $path,
        string $format = 'xlsx'
    ): void {
        $columns = $this->exportColumnsWithArrearsLabel($query);
        $headerRow = array_values($columns);
        $activeColumns = array_keys($columns);
        $selectColumns = array_filter($activeColumns, fn ($column) => $column !== 'dataset_month');
        if (!in_array('id', $selectColumns, true)) {
            $selectColumns[] = 'id';
        }
        $chunkSize = 2000;

        $tempFile = tempnam(sys_get_temp_dir(), 'export_');
        if ($tempFile === false) {
            throw new RuntimeException('Unable to allocate temporary storage while generating the export workbook.');
        }

        /** @var SpoutWriterInterface $writer */
        if ($format === 'csv') {
            $writer = new SpoutCsvWriter();
        } else {
            $writer = new SpoutXlsxWriter();
        }
        $writer->openToFile($tempFile);
        $writer->addRow(SpoutRow::fromValues($headerRow));

        $dataQuery = (clone $query);
        if (!empty($selectColumns)) {
            $dataQuery = $dataQuery->select($selectColumns);
        }

        $dataQuery->chunkById($chunkSize, function ($rows) use ($writer, $process, $activeColumns) {
            foreach ($rows as $row) {
                $rowData = [];
                foreach ($activeColumns as $attribute) {
                    $rowData[] = $this->formatValue($process, $row, $attribute);
                }
                $writer->addRow(SpoutRow::fromValues($rowData));
            }
        });

        $writer->close();

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
    /**
     * Stream export using Spout for large datasets (XLSX or CSV).
     *
     * @param MasterDatasetProcess $process
     * @param string $filename
     * @param Builder $query
     * @param string $format 'xlsx' or 'csv'
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function streamWithSpout(MasterDatasetProcess $process, string $filename, Builder $query, string $format = 'xlsx'): StreamedResponse
    {
        $columns = $this->exportColumnsWithArrearsLabel($query);
        $headerRow = array_values($columns);
        $activeColumns = array_keys($columns);
        $selectColumns = array_filter($activeColumns, fn ($column) => $column !== 'dataset_month');
        if (!in_array('id', $selectColumns, true)) {
            $selectColumns[] = 'id';
        }

        $chunkSize = 2000;

        return response()->streamDownload(function () use ($process, $query, $selectColumns, $headerRow, $activeColumns, $chunkSize, $format) {
            /** @var SpoutWriterInterface $writer */
            if ($format === 'csv') {
                $writer = new SpoutCsvWriter();
            } else {
                $writer = new SpoutXlsxWriter();
            }
            $writer->openToFile('php://output');
            $writer->addRow(SpoutRow::fromValues($headerRow));

            $dataQuery = (clone $query);
            if (!empty($selectColumns)) {
                $dataQuery = $dataQuery->select($selectColumns);
            }

            $dataQuery->chunkById($chunkSize, function ($rows) use ($writer, $process, $activeColumns) {
                foreach ($rows as $row) {
                    $rowData = [];
                    foreach ($activeColumns as $attribute) {
                        $rowData[] = $this->formatValue($process, $row, $attribute);
                    }
                    $writer->addRow(SpoutRow::fromValues($rowData));
                }
            });

            $writer->close();
        }, $filename, [
            'Content-Type' => $format === 'csv'
                ? 'text/csv'
                : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function streamCsvAsXlsx(
        Filesystem $disk,
        string $csvPath,
        string $downloadName
    ): StreamedResponse {
        [$localPath, $cleanup] = $this->resolveLocalCsvPath($disk, $csvPath);

        return response()->streamDownload(function () use ($localPath, $cleanup) {
            $reader = new SpoutCsvReader();
            $writer = new SpoutXlsxWriter();

            $reader->open($localPath);
            $writer->openToFile('php://output');

            try {
                foreach ($reader->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        $writer->addRow($row);
                    }
                }
            } finally {
                $writer->close();
                $reader->close();

                if ($cleanup && is_file($localPath)) {
                    @unlink($localPath);
                }
            }
        }, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function resolveLocalCsvPath(Filesystem $disk, string $csvPath): array
    {
        if (method_exists($disk, 'path')) {
            $candidate = $disk->path($csvPath);
            if (is_string($candidate) && is_file($candidate)) {
                return [$candidate, false];
            }
        }

        $stream = $disk->readStream($csvPath);
        if ($stream === false) {
            throw new RuntimeException('Unable to read the CSV export from storage.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'export_csv_');
        if ($tempFile === false) {
            fclose($stream);
            throw new RuntimeException('Unable to allocate a temporary CSV export file.');
        }

        $tempHandle = fopen($tempFile, 'wb');
        if ($tempHandle === false) {
            fclose($stream);
            @unlink($tempFile);
            throw new RuntimeException('Unable to prepare a temporary CSV export file.');
        }

        stream_copy_to_stream($stream, $tempHandle);
        fclose($stream);
        fclose($tempHandle);

        return [$tempFile, true];
    }
    
    private const EXPORT_COLUMNS = [
        'run_date_raw' => 'RUN_DATE',
        'dataset_month' => 'DATASET_MONTH',
        'region' => 'REGION',
        'rtom' => 'RTOM',
        'customer_ref' => 'CUSTOMER_REF',
        'account_num' => 'ACCOUNT_NUM',
        'installment' => 'INSTALLMENT',
        'account_status' => 'ACCOUNT_STATUS',
        'acct_effect_dtm' => 'ACCT_EFFECT_DTM',
        'bill_seq' => 'BILL_SEQ',
        'bill_month' => 'BILL_MONTH',
        'latest_bill_dtm' => 'LATEST_BILL_DTM',
        'latest_bill_mny' => 'LATEST_BILL_MNY',
        'next_bill_dtm' => 'Next Bill Date',
        'payment_due_dat' => 'PAYMENT_DUE_DAT',
        'invoicing_co_id' => 'INVOICING_CO_ID',
        'invoicing_co_name' => 'INVOICING_CO_NAME',
        'payments_value' => 'PAYMENTS_VALUE',
        'new_arrears_value' => 'ARREARS_VALUE',
        'new_arrears_secondary_value' => 'ARREARS_VALUE_2',
        'credit_score' => 'CREDIT_SCORE',
        'product_seq' => 'PRODUCT_SEQ',
        'product_label' => 'PRODUCT_LABEL',
        'product_id' => 'PRODUCT_ID',
        'product_name' => 'PRODUCT_NAME',
        'start_dat' => 'START_DAT',
        'end_dat' => 'END_DAT',
        'latest_product_status' => 'LATEST_PRODUCT_STATUS',
        'latest_effective_dtm' => 'LATEST_EFFECTIVE_DTM',
        'address_name' => 'ADDRESS_NAME',
        'full_address' => 'FULL_ADDRESS',
        'mobile_contact_tel' => 'MOBILE_CONTACT_TEL',
        'email_address' => 'EMAIL_ADDRESS',
        'billing_centre' => 'BILLING_CENTRE',
        'province' => 'PROVINCE',
        'credit_class_id' => 'CREDIT_CLASS_ID',
        'credit_class_name' => 'CREDIT_CLASS_NAME',
        'bill_handling_code' => 'BILL_HANDLING_CODE',
        'bill_handling_code_name' => 'BILL_HANDLING_CODE_NAME',
        'phone_number' => 'PHONE_NUMBER',
        'account_manager' => 'ACCOUNT_MANAGER',
        'slt_gl_sub_segment' => 'SLT_GL_SUB_SEGMENT',
        'slt_business_line_value' => 'SLT_BUSINESS_LINE_VALUE',
        'age_months' => 'AGE_MONTHS',
        'sales_channel' => 'SALES_CHANNEL',
        'sales_person' => 'SALES_PERSON',
        'medium' => 'MEDIUM',
        'customer_segment' => 'CUSTOMER_SEGMENT',
        'exclusion_reason' => 'Exclusion Reason',
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
        $columns = $this->exportColumnsWithArrearsLabel($query);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->truncateSheetTitle($label));

        $sheet->fromArray(array_values($columns), null, 'A1');
        $sheet->freezePane('A2');

        $rowPointer = 2;

        $chunkSize = 2000;
        $activeColumns = array_keys($columns);
        $selectColumns = array_filter($activeColumns, fn ($column) => $column !== 'dataset_month');

        // chunkById requires the id column to be present, so ensure it is always selected.
        if (! in_array('id', $selectColumns, true)) {
            $selectColumns[] = 'id';
        }

        $dataQuery = (clone $query);
        if (! empty($selectColumns)) {
            $dataQuery = $dataQuery->select($selectColumns);
        }

        $dataQuery->chunkById($chunkSize, function ($rows) use (&$rowPointer, $sheet, $process, $columns) {
            $batchData = [];

            /** @var MasterDatasetRow $row */
            foreach ($rows as $row) {
                $batchData[] = $this->mapRow($process, $row, $columns);
            }

            if (! empty($batchData)) {
                $sheet->fromArray($batchData, null, 'A' . $rowPointer);
                $rowPointer += count($batchData);
            }
        });

        if ($rowPointer === 2) {
            $sheet->fromArray([], null, 'A2');
        }

        $dimension = $sheet->calculateWorksheetDimension();
        $sheet->setAutoFilter($dimension);

        return $spreadsheet;
    }

    private function mapRow(MasterDatasetProcess $process, MasterDatasetRow $row, array $columns): array
    {
        $values = [];

        foreach ($columns as $attribute => $label) {
            $values[] = $this->formatValue($process, $row, $attribute);
        }

        return $values;
    }

    private function exportColumnsWithArrearsLabel(Builder $query): array
    {
        $columns = self::EXPORT_COLUMNS;
        $arrearsLabel = $this->resolveArrearsLabel($query);
        $paymentsLabel = $this->resolveColumnLabel($query, 'payments_column', 'PAYMENTS_VALUE');
        $arrearsSecondaryLabel = $this->resolveColumnLabel($query, 'new_arrears_secondary_column', 'ARREARS_VALUE_2');

        $columns['payments_value'] = $paymentsLabel;
        $columns['new_arrears_value'] = $arrearsLabel;
        $columns['new_arrears_secondary_value'] = $arrearsSecondaryLabel;

        return $columns;
    }

    private function resolveArrearsLabel(Builder $query): string
    {
        $label = (clone $query)->limit(1)->value('new_arrears_column');

        return $label && is_string($label) ? $label : 'ARREARS_VALUE';
    }

    private function resolveColumnLabel(Builder $query, string $columnName, string $fallback): string
    {
        $label = (clone $query)->limit(1)->value($columnName);

        return $label && is_string($label) ? $label : $fallback;
    }

    private function formatValue(MasterDatasetProcess $process, MasterDatasetRow $row, string $attribute): mixed
    {
        if ($attribute === 'dataset_month') {
            return $process->dataset_month;
        }

        $value = $row->{$attribute};

        return match ($attribute) {
            'run_date_raw' => $value,
            'acct_effect_dtm', 'next_bill_dtm', 'payment_due_dat', 'latest_bill_dtm', 'start_dat', 'end_dat', 'latest_effective_dtm' => $this->formatDate($value),
            'latest_bill_mny', 'payments_value', 'new_arrears_value', 'new_arrears_secondary_value' => $value !== null ? number_format((float) $value, 2, '.', '') : null,
            'credit_score' => $value !== null ? number_format((float) $value, 2, '.', '') : null,
                'exclusion_reason' => $row->excluded ? $value : null,
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
