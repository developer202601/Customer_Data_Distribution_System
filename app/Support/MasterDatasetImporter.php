<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use App\Support\MasterDatasetAssignmentService;
use App\Support\MasterDatasetStagingPromoter;
use App\Support\PythonIngestionService;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;
use ZipArchive;

class MasterDatasetImporter
{
    use ProcessesExcelRows;

    private const MASTER_UPLOAD_DIRECTORY = 'master-datasets';
    private const REQUIRED_COLUMNS = [
        'RUN_DATE',
        'REGION',
        'RTOM',
        'CUSTOMER_REF',
        'ACCOUNT_NUM',
        'PRODUCT_LABEL',
        'MEDIUM',
        'CUSTOMER_SEGMENT',
        'FULL_ADDRESS',
        'LATEST_BILL_MNY',
        'LATEST_PRODUCT_STATUS',
        'SLT_BUSINESS_LINE_VALUE',
    ];

    private Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk(config('filesystems.default', 'local'));
    }

    private function buildManifestPayload(
        MasterDatasetProcess $process,
        array $headers,
        array $headerMap,
        string $arrearsLetter,
        string $arrearsColumnName,
        ?Carbon $arrearsDate,
        ?array $userContext
    ): array {
        return [
            'process_id' => $process->id,
            'token' => $process->token,
            'storage_disk' => $process->storage_disk,
            'master_archive_path' => $process->master_archive_path,
            'master_archive_full_path' => $this->disk->path($process->master_archive_path),
            'master_workbook_path' => $process->master_workbook_path,
            'master_workbook_full_path' => $this->disk->path($process->master_workbook_path),
            'headers' => $headers,
            'header_map' => $headerMap,
            'arrears_column_letter' => $arrearsLetter,
            'arrears_column_name' => $arrearsColumnName,
            'arrears_date' => $arrearsDate?->format('Y-m-d'),
            'required_columns' => self::REQUIRED_COLUMNS,
            'user_context' => $userContext,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function updateRunDateFromRows(MasterDatasetProcess $process): void
    {
        $firstRow = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->whereNotNull('run_date')
            ->orderBy('run_date')
            ->orderBy('id')
            ->first();

        if ($firstRow) {
            $process->run_date = $firstRow->run_date;
            $process->run_date_raw = $firstRow->run_date_raw;
            $process->save();
        }
    }

    /**
     * @throws ValidationException
     */
    public function import(UploadedFile $archive, ?array $userContext = null): MasterDatasetProcess
    {
        $token = (string) Str::uuid();
        $zipPath = $this->storeArchive($archive, $token);

        try {
            $workbookPath = $this->extractWorkbook($zipPath, $token);
            return $this->ingestWorkbook($token, $zipPath, $workbookPath, $archive, $userContext);
        } catch (Throwable $exception) {
            $this->cleanupFiles($token);
            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'upload' => $exception->getMessage(),
            ]);
        }
    }

    private function ingestWorkbook(string $token, string $zipPath, string $workbookPath, UploadedFile $archive, ?array $userContext): MasterDatasetProcess
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($this->disk->path($workbookPath));

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (count($rows) < 2) {
            throw ValidationException::withMessages([
                'upload' => 'The spreadsheet must include a header row and at least one data row.',
            ]);
        }

        [$headers, $dataRows] = $this->separateHeaderAndRows($rows);
        $headerMap = $this->buildHeaderMap($headers);
        $this->assertRequiredColumns($headers);
        $arrearsLetter = $this->findHeaderColumnByPrefix($headerMap, self::NEW_ARREARS_PREFIX);

        if ($arrearsLetter === null) {
            throw ValidationException::withMessages([
                'upload' => 'The spreadsheet must include a NEW_ARREARS_YYYYMMDD column.',
            ]);
        }

        $arrearsColumnName = $headers[$arrearsLetter]['normalised'] ?? 'NEW_ARREARS';
        $arrearsDate = $this->extractArrearsDate($arrearsColumnName);
        $datasetMonth = $arrearsDate ? $arrearsDate->format('Ym') : now()->format('Ym');

        $process = null;

        DB::beginTransaction();

        try {
            $process = MasterDatasetProcess::create([
                'token' => $token,
                'dataset_month' => $datasetMonth,
                'arrears_date' => $arrearsDate,
                'run_date_raw' => null,
                'master_archive_path' => $zipPath,
                'master_workbook_path' => $workbookPath,
                'storage_disk' => config('filesystems.default', 'local'),
                'master_filesize' => $archive->getSize(),
                'user_id' => $userContext['id'] ?? null,
                'user_name' => $userContext['name'] ?? null,
                'status' => 'pending_python',
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $process->refresh();

        $python = app(PythonIngestionService::class);
        $manifestPayload = $this->buildManifestPayload($process, $headers, $headerMap, $arrearsLetter, $arrearsColumnName, $arrearsDate, $userContext);
        $python->writeManifest($process, $manifestPayload);
        $python->ensureStatusFile($process);

        try {
            $python->run($process);
            $promoted = app(MasterDatasetStagingPromoter::class)->promote($process);

            if (($promoted['promoted'] ?? 0) === 0) {
                $statistics = $this->importRows($process, $dataRows, $headers, $headerMap, $arrearsLetter, $arrearsColumnName);
            } else {
                $statistics = $this->databaseStatistics($process);
            }

            $process->refresh();
            app(MasterDatasetAssignmentService::class)->assign($process);

            $process->update([
                'row_count' => $statistics['row_count'] ?? 0,
                'excluded_count' => $statistics['excluded_count'] ?? 0,
                'run_date' => $statistics['first_run_date'] ?? null,
                'run_date_raw' => $statistics['first_run_date_raw'] ?? null,
                'status' => 'ready',
                'failure_reason' => null,
            ]);

            return $process->fresh();
        } catch (Throwable $exception) {
            if ($process) {
                $process->update([
                    'status' => 'failed',
                    'failure_reason' => $exception->getMessage(),
                ]);
            }

            throw ValidationException::withMessages([
                'upload' => $exception->getMessage(),
            ]);
        }
    }

    private function importRows(MasterDatasetProcess $process, array $dataRows, array $headers, array $headerMap, string $arrearsLetter, string $arrearsColumnName): array
    {
        $statistics = [
            'row_count' => 0,
            'excluded_count' => 0,
            'first_run_date' => null,
            'first_run_date_raw' => null,
        ];

        foreach ($dataRows as $columns) {
            if (! $this->rowHasData($columns)) {
                continue;
            }

            $statistics['row_count']++;

            $parsed = $this->mapRow($columns, $headerMap, $arrearsLetter, $arrearsColumnName);

            if (! $statistics['first_run_date'] && $parsed['run_date'] instanceof Carbon) {
                $statistics['first_run_date'] = $parsed['run_date'];
                $statistics['first_run_date_raw'] = $parsed['run_date_raw'];
            }

            $autoExclusion = $this->evaluateAutoExclusion($parsed);
            if ($autoExclusion) {
                $parsed['excluded'] = true;
                $parsed['exclusion_reason'] = $autoExclusion;
                $parsed['exclusion_priority'] = max($parsed['exclusion_priority'] ?? 0, 5);
                $statistics['excluded_count']++;
            }

            $nonRetailExclusion = $this->nonRetailExclusion($parsed);
            if ($nonRetailExclusion && ! $parsed['excluded']) {
                $parsed['excluded'] = true;
                $parsed['exclusion_reason'] = $nonRetailExclusion;
                $parsed['exclusion_priority'] = max($parsed['exclusion_priority'] ?? 0, 6);
                $statistics['excluded_count']++;
            }

            if ($parsed['excluded']) {
                $parsed['assigned_to'] = null;
            }

            MasterDatasetRow::create(array_merge($parsed, [
                'process_id' => $process->id,
            ]));
        }

        return $statistics;
    }

    private function databaseStatistics(MasterDatasetProcess $process): array
    {
        $base = MasterDatasetRow::query()->where('process_id', $process->id);

        $first = (clone $base)
            ->whereNotNull('run_date')
            ->orderBy('run_date')
            ->orderBy('id')
            ->first();

        return [
            'row_count' => (clone $base)->count(),
            'excluded_count' => (clone $base)->where('excluded', true)->count(),
            'first_run_date' => $first?->run_date,
            'first_run_date_raw' => $first?->run_date_raw,
        ];
    }

    private function mapRow(array $columns, array $headerMap, string $arrearsLetter, string $arrearsColumnName): array
    {
        $runDateRaw = $this->getColumnValue($columns, $headerMap, 'RUN_DATE');
        $runDate = $this->parseRunDate($runDateRaw);

        return [
            'run_date' => $runDate,
            'run_date_raw' => $runDateRaw,
            'region' => $this->getColumnValue($columns, $headerMap, 'REGION'),
            'rtom' => $this->getColumnValue($columns, $headerMap, 'RTOM'),
            'customer_ref' => $this->getColumnValue($columns, $headerMap, 'CUSTOMER_REF'),
            'account_num' => $this->getColumnValue($columns, $headerMap, 'ACCOUNT_NUM'),
            'product_label' => $this->getColumnValue($columns, $headerMap, 'PRODUCT_LABEL'),
            'medium' => $this->getColumnValue($columns, $headerMap, 'MEDIUM'),
            'customer_segment' => $this->getColumnValue($columns, $headerMap, 'CUSTOMER_SEGMENT'),
            'address_name' => $this->getColumnValue($columns, $headerMap, 'ADDRESS_NAME'),
            'full_address' => $this->getColumnValue($columns, $headerMap, 'FULL_ADDRESS'),
            'latest_bill_mny' => $this->parseMoney($this->getColumnValue($columns, $headerMap, 'LATEST_BILL_MNY')),
            'new_arrears_value' => $this->parseMoney($columns[$arrearsLetter] ?? ''),
            'new_arrears_column' => $arrearsColumnName,
            'mobile_contact_tel' => $this->getColumnValue($columns, $headerMap, 'MOBILE_CONTACT_TEL'),
            'email_address' => $this->getColumnValue($columns, $headerMap, 'EMAIL_ADDRESS'),
            'credit_score' => $this->parseDecimal($this->getColumnValue($columns, $headerMap, 'CREDIT_SCORE')),
            'credit_class_name' => $this->getColumnValue($columns, $headerMap, 'CREDIT_CLASS_NAME'),
            'bill_handling_code_name' => $this->getColumnValue($columns, $headerMap, 'BILL_HANDLING_CODE_NAME'),
            'age_months' => $this->parseInteger($this->getColumnValue($columns, $headerMap, 'AGE_MONTHS')),
            'sales_person' => $this->getColumnValue($columns, $headerMap, 'SALES_PERSON'),
            'account_manager' => $this->getColumnValue($columns, $headerMap, 'ACCOUNT_MANAGER'),
            'slt_gl_sub_segment' => $this->getColumnValue($columns, $headerMap, 'SLT_GL_SUB_SEGMENT'),
            'billing_centre' => $this->getColumnValue($columns, $headerMap, 'BILLING_CENTRE'),
            'province' => $this->getColumnValue($columns, $headerMap, 'PROVINCE'),
            'next_bill_dtm' => $this->parseDate($this->getColumnValue($columns, $headerMap, 'NEXT_BILL_DTM')),
            'bill_month' => $this->getColumnValue($columns, $headerMap, 'BILL_MONTH'),
            'latest_bill_dtm' => $this->parseDate($this->getColumnValue($columns, $headerMap, 'LATEST_BILL_DTM')),
            'invoicing_co_id' => $this->getColumnValue($columns, $headerMap, 'INVOICING_CO_ID'),
            'invoicing_co_name' => $this->getColumnValue($columns, $headerMap, 'INVOICING_CO_NAME'),
            'product_seq' => $this->getColumnValue($columns, $headerMap, 'PRODUCT_SEQ'),
            'product_id' => $this->getColumnValue($columns, $headerMap, 'PRODUCT_ID'),
            'latest_product_status' => $this->getColumnValue($columns, $headerMap, 'LATEST_PRODUCT_STATUS'),
            'bill_handling_code' => $this->getColumnValue($columns, $headerMap, 'BILL_HANDLING_CODE'),
            'slt_business_line_value' => $this->getColumnValue($columns, $headerMap, 'SLT_BUSINESS_LINE_VALUE'),
            'sales_channel' => $this->getColumnValue($columns, $headerMap, 'SALES_CHANNEL'),
            'excluded' => false,
            'exclusion_reason' => null,
            'assigned_to' => null,
            'exclusion_priority' => 0,
        ];
    }

    private function evaluateAutoExclusion(array $row): ?string
    {
        $status = strtoupper(trim((string) $row['latest_product_status']));
        if ($status !== 'OK') {
            return 'AUTO: latest_product_status != OK';
        }

        $medium = strtoupper(trim((string) $row['medium']));
        if (! in_array($medium, ['COPPER', 'FTTH'], true)) {
            return 'AUTO: medium not in COPPER/FTTH';
        }

        if ((float) $row['new_arrears_value'] < 2400) {
            return 'AUTO: new_arrears below 2400';
        }

        return null;
    }

    private function nonRetailExclusion(array $row): ?string
    {
        $businessLine = trim((string) $row['slt_business_line_value']);
        $latestBill = (float) $row['latest_bill_mny'];

        $isRetail = in_array((string) $businessLine, ['11', '35'], true);

        if (! $isRetail && $latestBill < 5000) {
            return 'AUTO: non-retail latest_bill_mny < 5000';
        }

        return null;
    }

    private function parseRunDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $candidate = str_replace('.', ':', $value);
        foreach (['Y-m-d H:i', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $candidate, config('app.timezone'));
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['Y-m-d', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (Throwable) {
                continue;
            }
        }

        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestamp((int) $value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    private function parseMoney(?string $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        $numeric = str_replace([',', ' '], '', $value);
        return is_numeric($numeric) ? (float) $numeric : null;
    }

    private function parseDecimal(?string $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function parseInteger(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function extractArrearsDate(string $columnName): ?Carbon
    {
        $suffix = str_replace(self::NEW_ARREARS_PREFIX, '', $columnName);
        $suffix = preg_replace('/[^0-9]/', '', $suffix);

        if (strlen($suffix) < 6) {
            return null;
        }

        $candidate = substr($suffix, 0, 8);

        foreach (['Ymd', 'Ym'] as $format) {
            try {
                return Carbon::createFromFormat($format, $candidate);
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function assertRequiredColumns(array $headers): void
    {
        $missing = [];

        foreach (self::REQUIRED_COLUMNS as $column) {
            $present = false;
            foreach ($headers as $meta) {
                if (($meta['normalised'] ?? null) === $column) {
                    $present = true;
                    break;
                }
            }

            if (! $present) {
                $missing[] = $column;
            }
        }

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'upload' => 'Missing required columns: ' . implode(', ', $missing),
            ]);
        }
    }

    private function storeArchive(UploadedFile $archive, string $token): string
    {
        $path = self::MASTER_UPLOAD_DIRECTORY . '/' . $token . '.zip';
        $this->disk->putFileAs(self::MASTER_UPLOAD_DIRECTORY, $archive, $token . '.zip');
        return $path;
    }

    private function extractWorkbook(string $zipPath, string $token): string
    {
        $absoluteZip = $this->disk->path($zipPath);
        $zip = new ZipArchive();

        if ($zip->open($absoluteZip) !== true) {
            throw new RuntimeException('Unable to open the uploaded ZIP archive.');
        }

        try {
            $entry = $this->locateExcelEntry($zip);
            $targetPath = self::MASTER_UPLOAD_DIRECTORY . '/' . $token . '.xlsx';
            $absoluteTarget = $this->disk->path($targetPath);
            $directory = dirname($absoluteTarget);

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException('Unable to prepare storage for the extracted workbook.');
            }

            $stream = $zip->getStream($entry);
            if (! $stream) {
                throw new RuntimeException(sprintf('Unable to read "%s" in the uploaded archive.', $entry));
            }

            $targetHandle = fopen($absoluteTarget, 'wb');
            if (! $targetHandle) {
                fclose($stream);
                throw new RuntimeException('Unable to write the extracted workbook to storage.');
            }

            stream_copy_to_stream($stream, $targetHandle);
            fclose($stream);
            fclose($targetHandle);

            return $targetPath;
        } finally {
            $zip->close();
        }
    }

    private function locateExcelEntry(ZipArchive $zip): string
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = $stat['name'] ?? '';

            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            if (str_ends_with(strtolower($name), '.xlsx')) {
                $entries[] = $name;
            }
        }

        if (empty($entries)) {
            throw new RuntimeException('The ZIP file must contain exactly one Excel (.xlsx) workbook. None were found.');
        }

        if (count($entries) > 1) {
            throw new RuntimeException('The ZIP file must contain exactly one Excel (.xlsx) workbook.');
        }

        return $entries[0];
    }

    private function cleanupFiles(string $token): void
    {
        $this->disk->delete(self::MASTER_UPLOAD_DIRECTORY . '/' . $token . '.zip');
        $this->disk->delete(self::MASTER_UPLOAD_DIRECTORY . '/' . $token . '.xlsx');
    }
}
