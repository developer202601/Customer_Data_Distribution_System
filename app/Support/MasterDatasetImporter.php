<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use App\Support\MasterDatasetAssignmentService;
use App\Support\MasterDatasetProcessStatus;
use App\Support\MasterDatasetStagingPromoter;
use App\Support\PythonIngestionService;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
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

    private const MAX_ROW_ERRORS = 20;

    private const EXPORT_BASE_DIRECTORY = 'exports';
    private const MASTER_SOURCE_SUBDIRECTORY = 'source';
    private const TEMP_DIRECTORY = 'tmp/master';
    private const ASSIGNMENT_EXCLUDED = 'Excluded';
    private const ASSIGNMENT_VIP = 'VIP';
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
    private const REQUIRED_ROW_COLUMNS = [
        'RUN_DATE',
        'ACCOUNT_NUM',
        'PRODUCT_LABEL',
    ];
    private const DUPLICATE_ROW_CONSTRAINT = 'mdr_process_run_product_unique';
    private const PROCESS_UPLOAD_CACHE_PREFIX = 'process:upload:';
    private const VALIDATION_PROGRESS_UPDATE_EVERY = 200;

    private Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk(config('filesystems.default', 'local'));
    }

    private function formatFailureReason(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            $messages = collect($exception->errors())->flatten()->filter()->values()->all();
            if (! empty($messages)) {
                return implode(' | ', array_slice($messages, 0, self::MAX_ROW_ERRORS));
            }
        }

        if ($this->isDuplicateCompositeKeyViolation($exception)) {
            return 'Duplicate PRODUCT_LABEL found. Please remove duplicates and re-upload the master file.';
        }

        return $exception->getMessage();
    }

    private function uploadErrorMessage(Throwable $exception): string
    {
        if ($this->isDuplicateCompositeKeyViolation($exception)) {
            return 'Duplicate PRODUCT_LABEL found. Please remove duplicates and re-upload the master file.';
        }

        return $exception->getMessage();
    }

    private function isDuplicateCompositeKeyViolation(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return str_contains(strtolower($exception->getMessage()), strtolower(self::DUPLICATE_ROW_CONSTRAINT));
        }

        $errorInfo = $exception->errorInfo;
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $message = strtolower((string) ($errorInfo[2] ?? $exception->getMessage()));

        if ($driverCode !== 1062) {
            return false;
        }

        return str_contains($message, strtolower(self::DUPLICATE_ROW_CONSTRAINT));
    }

    private function buildManifestPayload(
        MasterDatasetProcess $process,
        array $headers,
        array $headerMap,
        string $arrearsLetter,
        string $arrearsColumnName,
        ?Carbon $arrearsDate,
        ?array $userContext,
        string $workbookAbsolutePath
    ): array {
        return [
            'process_id' => $process->id,
            'token' => $process->token,
            'storage_disk' => $process->storage_disk,
            'master_archive_path' => $process->master_archive_path,
            'master_archive_full_path' => $this->disk->path($process->master_archive_path),
            'master_workbook_path' => $this->workbookPlaceholderPath($process->token),
            'master_workbook_full_path' => $workbookAbsolutePath,
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

    private function masterSourceDirectory(string $token): string
    {
        return self::EXPORT_BASE_DIRECTORY . '/' . $token . '/' . self::MASTER_SOURCE_SUBDIRECTORY;
    }

    private function workbookPlaceholderPath(string $token): string
    {
        return $this->masterSourceDirectory($token) . '/master.xlsx';
    }

    private function temporaryWorkbookPath(string $token): string
    {
        $directory = storage_path('app/' . self::TEMP_DIRECTORY);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to prepare a temporary directory for master workbook extraction.');
        }

        return $directory . '/' . $token . '_' . Str::uuid()->toString() . '.xlsx';
    }

    private function isTemporaryWorkbookPath(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        $prefix = rtrim(storage_path('app/' . self::TEMP_DIRECTORY), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $resolved = realpath($path) ?: $path;

        return str_starts_with($resolved, $prefix);
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
     * Preserve compatibility with the legacy single-step import path.
     */
    public function import(UploadedFile $archive, ?array $userContext = null): MasterDatasetProcess
    {
        $process = $this->queue($archive, $userContext);

        return $this->processStoredArchive($process, $userContext);
    }

    /**
     * Persist the uploaded master archive and initialise a dataset process without
     * running validation yet. The resulting process can be resumed once exclusions arrive.
     */
    public function queue(UploadedFile $archive, ?array $userContext = null): MasterDatasetProcess
    {
        $token = (string) Str::uuid();
        $zipPath = $this->storeArchive($archive, $token);

        $disk = config('filesystems.default', 'local');

        return MasterDatasetProcess::create([
            'token' => $token,
            'dataset_month' => now()->format('Ym'),
            'arrears_date' => null,
            'run_date_raw' => null,
            'master_archive_path' => $zipPath,
            'master_workbook_path' => $this->workbookPlaceholderPath($token),
            'storage_disk' => $disk,
            'master_filesize' => $archive->getSize(),
            'user_id' => $userContext['id'] ?? null,
            'status' => 'awaiting_exclusions',
            'failure_reason' => null,
        ]);
    }

    /**
     * Resume processing for a dataset whose master archive has already been uploaded.
     */
    public function processStoredArchive(
        MasterDatasetProcess $process,
        ?array $userContext = null,
        bool $skipAssignment = false
    ): MasterDatasetProcess {
        if ($process->status === MasterDatasetProcessStatus::READY) {
            return $process->fresh();
        }

        $diskName = $process->storage_disk ?: config('filesystems.default', 'local');
        $this->disk = Storage::disk($diskName);

        $process = MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::VALIDATING);

        if (! $process->master_archive_path || ! $this->disk->exists($process->master_archive_path)) {
            throw ValidationException::withMessages([
                'upload' => 'The uploaded master archive could not be located. Please upload the file again.',
            ]);
        }

        $token = $process->token ?? (string) Str::uuid();
        if (! $process->token) {
            $process->token = $token;
            $process->save();
        }

        $workbookAbsolute = null;

        try {
            $workbookAbsolute = $this->extractWorkbook($process->master_archive_path, $token);

            $placeholder = $this->workbookPlaceholderPath($token);
            if ($process->master_workbook_path !== $placeholder) {
                $process->master_workbook_path = $placeholder;
                $process->save();
            }

            return $this->ingestWorkbook($process, $process->master_archive_path, $workbookAbsolute, $userContext, $skipAssignment);
        } catch (Throwable $exception) {
            $process->update([
                'status' => 'failed',
                'failure_reason' => $this->formatFailureReason($exception),
            ]);

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'upload' => $exception->getMessage(),
            ]);
        } finally {
            if ($this->isTemporaryWorkbookPath($workbookAbsolute) && file_exists((string) $workbookAbsolute)) {
                @unlink($workbookAbsolute);
            }
        }
    }

    private function ingestWorkbook(
        MasterDatasetProcess $process,
        string $zipPath,
        string $workbookAbsolutePath,
        ?array $userContext,
        bool $skipAssignment
    ): MasterDatasetProcess
    {
        // Use PhpSpreadsheet disk cache for large workbook processing to reduce peak memory.
        if (class_exists(\PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory::class)) {
            try {
                \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod(
                    \PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory::cache_to_discISAM,
                    ['dir' => storage_path('app/tmp')]
                );
            } catch (\Throwable $e) {
                // ignore; fallback in-memory
            }
        }

        $token = (string) ($process->token ?? '');
        $cacheKey = $token !== '' ? self::PROCESS_UPLOAD_CACHE_PREFIX . $token : null;
        if ($cacheKey) {
            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 6,
                'message' => 'Reading master workbook into memory (this may take a minute)...',
                'stage' => 'loading',
                'processed_rows' => 0,
                'total_rows' => 0,
                'started_at' => time(),
                'last_updated_at' => now()->toIso8601String(),
                'errors' => [],
            ], now()->addMinutes(120));
        }

        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($workbookAbsolutePath);

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

        // Run row integrity checks while status is still "validating" so
        // the UI can display live master-validation row progress.
        $this->assertRowIntegrity($process, $dataRows, $headerMap, $arrearsLetter);

        $process = MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::VALIDATED);

        DB::beginTransaction();

        try {
            $process->update([
                'dataset_month' => $datasetMonth,
                'arrears_date' => $arrearsDate,
                'run_date_raw' => null,
                'master_archive_path' => $zipPath,
                'master_workbook_path' => $this->workbookPlaceholderPath($process->token),
                'storage_disk' => $process->storage_disk ?: config('filesystems.default', 'local'),
                'master_filesize' => $process->master_filesize ?: $this->disk->size($zipPath),
                'user_id' => $userContext['id'] ?? $process->user_id,
                'failure_reason' => null,
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }

        $process->refresh();

        $python = app(PythonIngestionService::class);
        $manifestPayload = $this->buildManifestPayload(
            $process,
            $headers,
            $headerMap,
            $arrearsLetter,
            $arrearsColumnName,
            $arrearsDate,
            $userContext,
            $workbookAbsolutePath
        );
        $python->writeManifest($process, $manifestPayload);
        $python->ensureStatusFile($process);

        try {
            $process = MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::PYTHON_RUNNING);
            $python->run($process);
            $process = MasterDatasetProcessStatus::set($process->fresh(), MasterDatasetProcessStatus::PYTHON_COMPLETE);

            // Allow safe re-processing of the same process by clearing stale rows first.
            MasterDatasetRow::query()->where('process_id', $process->id)->delete();

            $process = MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::RECORDS_INSERTING);
            $promoted = app(MasterDatasetStagingPromoter::class)->promote($process);

            if (($promoted['promoted'] ?? 0) === 0) {
                $statistics = $this->importRows($process, $dataRows, $headers, $headerMap, $arrearsLetter, $arrearsColumnName);
            } else {
                $statistics = $this->databaseStatistics($process);
            }

            $process->refresh();

            if (! $skipAssignment) {
                app(MasterDatasetAssignmentService::class)->assign($process);

                // Audit: assignments were created using default configuration.
                if (! $process->assignment_config_source) {
                    $defaults = app(MasterDatasetAssignmentConfiguration::class)->toArray();
                    $normalized = [
                        'upper_range' => (int) ($defaults['upper_range'] ?? 0),
                        'lower_range' => (int) ($defaults['lower_range'] ?? 0),
                        'call_center_staff_quota' => (int) ($defaults['call_center_staff_quota'] ?? 0),
                        'call_center_quota' => (int) ($defaults['call_center_quota'] ?? 0),
                        'staff_quota' => (int) ($defaults['staff_quota'] ?? 0),
                    ];

                    $process->update([
                        'assignment_config_source' => 'default',
                        'assignment_config_overrides' => $normalized,
                        'assignment_config_default_snapshot' => $normalized,
                        'assignment_config_set_by_user_id' => null,
                        'assignment_config_set_at' => now(),
                    ]);
                }
            }

            $process->update([
                'row_count' => $statistics['row_count'] ?? 0,
                'excluded_count' => $statistics['excluded_count'] ?? 0,
                'run_date' => $statistics['first_run_date'] ?? null,
                'run_date_raw' => $statistics['first_run_date_raw'] ?? null,
                'failure_reason' => null,
            ]);

            $process = MasterDatasetProcessStatus::set($process->fresh(), MasterDatasetProcessStatus::RECORDS_INSERTED);

            return $process->fresh();
        } catch (Throwable $exception) {
            if ($process) {
                MasterDatasetProcessStatus::set($process, 'failed');
                $process->update([
                    'failure_reason' => $this->formatFailureReason($exception),
                ]);
            }

            if ($exception instanceof ValidationException) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'upload' => $this->uploadErrorMessage($exception),
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

        $paymentsLetter = $this->findHeaderColumnByPrefix($headerMap, 'PAYMENTS');
        $paymentsColumnName = $paymentsLetter ? ($headers[$paymentsLetter]['normalised'] ?? 'PAYMENTS_VALUE') : null;

        $secondaryArrearsLetter = null;
        foreach ($this->findHeaderColumnsByPrefix($headerMap, self::NEW_ARREARS_PREFIX) as $candidateLetter) {
            if ($candidateLetter !== $arrearsLetter) {
                $secondaryArrearsLetter = $candidateLetter;
                break;
            }
        }
        $secondaryArrearsColumnName = $secondaryArrearsLetter
            ? ($headers[$secondaryArrearsLetter]['normalised'] ?? 'NEW_ARREARS_SECONDARY')
            : null;

        $batch = [];
        $batchSize = 1000;
        $now = now();
        $processId = $process->id;

        foreach ($dataRows as $columns) {
            if (! $this->rowHasData($columns)) {
                continue;
            }

            $statistics['row_count']++;

            $parsed = $this->mapRow(
                $columns,
                $headerMap,
                $arrearsLetter,
                $arrearsColumnName,
                $paymentsLetter,
                $paymentsColumnName,
                $secondaryArrearsLetter,
                $secondaryArrearsColumnName
            );

            if (! $statistics['first_run_date'] && $parsed['run_date'] instanceof Carbon) {
                $statistics['first_run_date'] = $parsed['run_date'];
                $statistics['first_run_date_raw'] = $parsed['run_date_raw'];
            }

            $autoExclusion = $this->evaluateAutoExclusion($parsed);
            if ($autoExclusion) {
                $parsed['excluded'] = true;
                $parsed['exclusion_reason'] = $autoExclusion;
                $parsed['exclusion_priority'] = max($parsed['exclusion_priority'] ?? 0, 5);
                $parsed['assigned_to'] = self::ASSIGNMENT_EXCLUDED;
                $statistics['excluded_count']++;

                $parsed['process_id'] = $processId;
                $parsed['created_at'] = $now;
                $parsed['updated_at'] = $now;
                $batch[] = $parsed;
                
                if (count($batch) >= $batchSize) {
                    MasterDatasetRow::insert($batch);
                    $batch = [];
                }
                continue;
            }

            if ($this->shouldAssignVip($parsed)) {
                $parsed['assigned_to'] = self::ASSIGNMENT_VIP;
            }

            $parsed['process_id'] = $processId;
            $parsed['created_at'] = $now;
            $parsed['updated_at'] = $now;
            $batch[] = $parsed;

            if (count($batch) >= $batchSize) {
                MasterDatasetRow::insert($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            MasterDatasetRow::insert($batch);
        }

        return $statistics;
    }

    private function assertRowIntegrity(MasterDatasetProcess $process, array $dataRows, array $headerMap, string $arrearsLetter): void
    {
        $errors = [];
        $seenCompositeKey = [];
        $maxReached = false;

        $token = (string) ($process->token ?? '');
        $cacheKey = $token !== '' ? self::PROCESS_UPLOAD_CACHE_PREFIX . $token : null;
        $totalRows = 0;
        foreach ($dataRows as $columns) {
            if (is_array($columns) && $this->rowHasData($columns)) {
                $totalRows++;
            }
        }

        $startTime = time();
        if ($cacheKey) {
            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 6,
                'message' => 'Validating master dataset...',
                'stage' => 'validation',
                'processed_rows' => 0,
                'total_rows' => $totalRows,
                'started_at' => $startTime,
                'last_updated_at' => now()->toIso8601String(),
                'errors' => [],
            ], now()->addMinutes(120));
        }

        $processed = 0;
        $rowSleepMicros = $this->validationLoopSleepMicros();

        foreach ($dataRows as $excelRow => $columns) {
            if (! is_array($columns) || ! $this->rowHasData($columns)) {
                continue;
            }

            $processed++;
            if ($cacheKey && (($processed % self::VALIDATION_PROGRESS_UPDATE_EVERY === 0) || $processed === $totalRows)) {
                Cache::put($cacheKey, [
                    'status' => 'processing',
                    'progress' => 6,
                    'message' => 'Validating master dataset...',
                    'stage' => 'validation',
                    'processed_rows' => $processed,
                    'total_rows' => $totalRows,
                    'started_at' => $startTime,
                    'last_updated_at' => now()->toIso8601String(),
                    'errors' => [],
                ], now()->addMinutes(120));
            }

            // Optional local-only throttle for UI/demo validation progress visibility.
            if ($rowSleepMicros > 0) {
                usleep($rowSleepMicros);
            }

            foreach (self::REQUIRED_ROW_COLUMNS as $requiredColumn) {
                $value = trim($this->getColumnValue($columns, $headerMap, $requiredColumn));
                if ($value === '') {
                    $errors[] = sprintf('Row %d, column %s: value is required.', (int) $excelRow, $requiredColumn);
                    if (count($errors) >= self::MAX_ROW_ERRORS) {
                        $maxReached = true;
                        break 2;
                    }
                }
            }

            $arrearsRaw = trim((string) ($columns[$arrearsLetter] ?? ''));
            if ($arrearsRaw !== '' && $arrearsRaw !== '-') {
                $normalized = str_replace([',', ' '], '', $arrearsRaw);
                if (! is_numeric($normalized)) {
                    $errors[] = sprintf('Row %d, column %s: expected numeric value or "-".', (int) $excelRow, self::NEW_ARREARS_PREFIX . '*');
                    if (count($errors) >= self::MAX_ROW_ERRORS) {
                        $maxReached = true;
                        break;
                    }
                }
            }

            $productLabel = trim($this->getColumnValue($columns, $headerMap, 'PRODUCT_LABEL'));

            if ($productLabel !== '') {
                $key = strtolower($productLabel);
                if (isset($seenCompositeKey[$key])) {
                    $errors[] = sprintf(
                        'Row %d, column PRODUCT_LABEL: duplicate value already found at row %d.',
                        (int) $excelRow,
                        (int) $seenCompositeKey[$key]
                    );

                    if (count($errors) >= self::MAX_ROW_ERRORS) {
                        $maxReached = true;
                        break;
                    }
                } else {
                    $seenCompositeKey[$key] = (int) $excelRow;
                }
            }

            if ($maxReached) {
                break;
            }
        }

        if (! empty($errors)) {
            if ($cacheKey) {
                Cache::put($cacheKey, [
                    'status' => 'failed',
                    'progress' => 6,
                    'message' => 'Master dataset validation failed.',
                    'stage' => 'validation',
                    'processed_rows' => $processed,
                    'total_rows' => $totalRows,
                    'started_at' => time(),
                    'last_updated_at' => now()->toIso8601String(),
                    'errors' => $errors,
                ], now()->addMinutes(120));
            }

            if ($maxReached) {
                $errors[] = sprintf('Showing first %d validation errors only.', self::MAX_ROW_ERRORS);
            }

            throw ValidationException::withMessages([
                'upload' => $errors,
            ]);
        }
    }

    private function validationLoopSleepMicros(): int
    {
        if (! app()->environment('local')) {
            return 0;
        }

        $value = (int) env('MASTER_VALIDATION_ROW_SLEEP_US', 0);
        if ($value <= 0) {
            return 0;
        }

        return min($value, 1_000_000);
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

    private function mapRow(
        array $columns,
        array $headerMap,
        string $arrearsLetter,
        string $arrearsColumnName,
        ?string $paymentsLetter,
        ?string $paymentsColumnName,
        ?string $secondaryArrearsLetter,
        ?string $secondaryArrearsColumnName
    ): array
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
            'installment' => $this->getColumnValue($columns, $headerMap, 'INSTALLMENT'),
            'account_status' => $this->getColumnValue($columns, $headerMap, 'ACCOUNT_STATUS'),
            'acct_effect_dtm' => $this->parseDate($this->getColumnValue($columns, $headerMap, 'ACCT_EFFECT_DTM')),
            'bill_seq' => $this->getColumnValue($columns, $headerMap, 'BILL_SEQ'),
            'product_label' => $this->getColumnValue($columns, $headerMap, 'PRODUCT_LABEL'),
            'medium' => $this->getColumnValue($columns, $headerMap, 'MEDIUM'),
            'customer_segment' => $this->getColumnValue($columns, $headerMap, 'CUSTOMER_SEGMENT'),
            'address_name' => $this->getColumnValue($columns, $headerMap, 'ADDRESS_NAME'),
            'full_address' => $this->getColumnValue($columns, $headerMap, 'FULL_ADDRESS'),
            'latest_bill_mny' => $this->parseMoney($this->getColumnValue($columns, $headerMap, 'LATEST_BILL_MNY')),
            'new_arrears_value' => $this->parseMoney($columns[$arrearsLetter] ?? ''),
            'new_arrears_column' => $arrearsColumnName,
            'payments_value' => $paymentsLetter ? $this->parseMoney($columns[$paymentsLetter] ?? '') : null,
            'payments_column' => $paymentsColumnName,
            'new_arrears_secondary_value' => $secondaryArrearsLetter ? $this->parseMoney($columns[$secondaryArrearsLetter] ?? '') : null,
            'new_arrears_secondary_column' => $secondaryArrearsColumnName,
            'mobile_contact_tel' => $this->getColumnValue($columns, $headerMap, 'MOBILE_CONTACT_TEL'),
            'email_address' => $this->getColumnValue($columns, $headerMap, 'EMAIL_ADDRESS'),
            'credit_score' => $this->parseDecimal($this->getColumnValue($columns, $headerMap, 'CREDIT_SCORE')),
            'credit_class_id' => $this->getColumnValue($columns, $headerMap, 'CREDIT_CLASS_ID'),
            'credit_class_name' => $this->getColumnValue($columns, $headerMap, 'CREDIT_CLASS_NAME'),
            'bill_handling_code_name' => $this->getColumnValue($columns, $headerMap, 'BILL_HANDLING_CODE_NAME'),
            'age_months' => $this->parseInteger($this->getColumnValue($columns, $headerMap, 'AGE_MONTHS')),
            'sales_person' => $this->getColumnValue($columns, $headerMap, 'SALES_PERSON'),
            'account_manager' => $this->getColumnValue($columns, $headerMap, 'ACCOUNT_MANAGER'),
            'slt_gl_sub_segment' => $this->getColumnValue($columns, $headerMap, 'SLT_GL_SUB_SEGMENT'),
            'billing_centre' => $this->getColumnValue($columns, $headerMap, 'BILLING_CENTRE'),
            'province' => $this->getColumnValue($columns, $headerMap, 'PROVINCE'),
            'next_bill_dtm' => $this->parseDate($this->getColumnValue($columns, $headerMap, 'NEXT_BILL_DTM')),
            'payment_due_dat' => $this->parseDate($this->getColumnValue($columns, $headerMap, 'PAYMENT_DUE_DAT')),
            'bill_month' => $this->getColumnValue($columns, $headerMap, 'BILL_MONTH'),
            'latest_bill_dtm' => $this->parseDate($this->getColumnValue($columns, $headerMap, 'LATEST_BILL_DTM')),
            'invoicing_co_id' => $this->getColumnValue($columns, $headerMap, 'INVOICING_CO_ID'),
            'invoicing_co_name' => $this->getColumnValue($columns, $headerMap, 'INVOICING_CO_NAME'),
            'product_seq' => $this->getColumnValue($columns, $headerMap, 'PRODUCT_SEQ'),
            'product_id' => $this->getColumnValue($columns, $headerMap, 'PRODUCT_ID'),
            'product_name' => $this->getColumnValue($columns, $headerMap, 'PRODUCT_NAME'),
            'start_dat' => $this->parseDate($this->getColumnValue($columns, $headerMap, 'START_DAT')),
            'end_dat' => $this->parseDate($this->getColumnValue($columns, $headerMap, 'END_DAT')),
            'latest_product_status' => $this->getColumnValue($columns, $headerMap, 'LATEST_PRODUCT_STATUS'),
            'latest_effective_dtm' => $this->parseDate($this->getColumnValue($columns, $headerMap, 'LATEST_EFFECTIVE_DTM')),
            'bill_handling_code' => $this->getColumnValue($columns, $headerMap, 'BILL_HANDLING_CODE'),
            'phone_number' => $this->getColumnValue($columns, $headerMap, 'PHONE_NUMBER'),
            'slt_business_line_value' => $this->getColumnValue($columns, $headerMap, 'SLT_BUSINESS_LINE_VALUE'),
            'sales_channel' => $this->getColumnValue($columns, $headerMap, 'SALES_CHANNEL'),
            'excluded' => false,
            'exclusion_reason' => null,
            'assigned_to' => null,
            'exclusion_priority' => 0,
        ];
    }

    private function findHeaderColumnsByPrefix(array $headerMap, string $prefix): array
    {
        $letters = [];

        foreach ($headerMap as $normalised => $letter) {
            if (str_starts_with($normalised, $prefix)) {
                $letters[] = $letter;
            }
        }

        return $letters;
    }

    private function evaluateAutoExclusion(array $row): ?string
    {
        $medium = strtoupper(trim((string) $row['medium']));
        if ($medium === '' || ! in_array($medium, ['COPPER', 'FTTH'], true)) {
            $value = $medium === '' ? 'blank' : $medium;
            return sprintf('AUTO: MEDIUM is %s (requires COPPER or FTTH)', $value);
        }

        $status = strtoupper(trim((string) $row['latest_product_status']));
        if ($status !== 'OK') {
            $value = $status === '' ? 'blank' : $status;
            return sprintf('AUTO: LATEST_PRODUCT_STATUS is %s (requires OK)', $value);
        }

        $arrears = (float) ($row['new_arrears_value'] ?? 0);
        if ($arrears <= 2400) {
            $column = $row['new_arrears_column'] ?? 'NEW_ARREARS';
            return sprintf('AUTO: %s <= 2400', $column);
        }

        return null;
    }

    private function shouldAssignVip(array $row): bool
    {
        $creditClass = (string) ($row['credit_class_name'] ?? '');

        if ($creditClass === '') {
            $creditClass = (string) ($row['customer_segment'] ?? '');
        }

        if ($creditClass === '') {
            return false;
        }

        return $this->isVipCreditClass($creditClass);
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
                $missing[] = $column === 'RTOM' ? 'RTO' : $column;
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
        $directory = $this->masterSourceDirectory($token);
        $this->disk->makeDirectory($directory);

        $filename = 'master.xlsx';
        $stored = $this->disk->putFileAs($directory, $archive, $filename);

        if (! $stored) {
            throw ValidationException::withMessages([
                'upload' => 'Unable to store the uploaded master archive. Please try again.',
            ]);
        }

        return $directory . '/' . $filename;
    }

    private function extractWorkbook(string $zipPath, string $token): string
    {
        if (str_ends_with(strtolower($zipPath), '.xlsx')) {
            return $this->disk->path($zipPath);
        }

        $absoluteZip = $this->disk->path($zipPath);
        $zip = new ZipArchive();

        if ($zip->open($absoluteZip) !== true) {
            throw new RuntimeException('Unable to open the uploaded ZIP archive.');
        }

        try {
            $entry = $this->locateExcelEntry($zip);
            $targetPath = $this->temporaryWorkbookPath($token);

            $directory = dirname($targetPath);
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException('Unable to prepare storage for the extracted workbook.');
            }

            $stream = $zip->getStream($entry);
            if (! $stream) {
                throw new RuntimeException(sprintf('Unable to read "%s" in the uploaded archive.', $entry));
            }

            $targetHandle = fopen($targetPath, 'wb');
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
            $name = str_replace('\\', '/', $name);

            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            if (str_starts_with($name, '__MACOSX/')) {
                continue;
            }

            $base = basename($name);

            if ($base === ''
                || $base === '.DS_Store'
                || str_starts_with($base, '._')
                || str_starts_with($base, '~$')
                || str_starts_with($base, '.')
            ) {
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
            throw new RuntimeException(sprintf(
                'The ZIP file must contain exactly one Excel (.xlsx) workbook. Found %d: %s',
                count($entries),
                implode(', ', $entries)
            ));
        }

        return $entries[0];
    }

}
