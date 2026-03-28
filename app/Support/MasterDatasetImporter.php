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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use ZipArchive;
use Symfony\Component\Process\Process;

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
    private const VALIDATION_REPORT_BASE_DIRECTORY = 'validation-reports/master';

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
        ?array $userContext,
        string $workbookAbsolutePath
    ): array {
        $token = (string) ($process->token ?? '');
        $diskName = (string) ($process->storage_disk ?: config('filesystems.default', 'local'));
        $disk = Storage::disk($diskName);

        $reportRelative = $token !== '' ? $this->validationReportRelativePath($token) : null;
        $reportAbsolute = $reportRelative ? $disk->path($reportRelative) : null;

        return [
            'process_id' => $process->id,
            'token' => $process->token,
            'storage_disk' => $process->storage_disk,
            'master_archive_path' => $process->master_archive_path,
            'master_archive_full_path' => $process->master_archive_path ? $this->disk->path($process->master_archive_path) : null,
            'master_workbook_path' => $this->workbookPlaceholderPath($process->token),
            'master_workbook_full_path' => $workbookAbsolutePath,
            'required_columns' => self::REQUIRED_COLUMNS,
            'required_row_columns' => self::REQUIRED_ROW_COLUMNS,
            'dedupe_column' => 'PRODUCT_LABEL',
            'arrears_prefix' => self::NEW_ARREARS_PREFIX,
            'validation_report_relative_path' => $reportRelative,
            'validation_report_full_path' => $reportAbsolute,
            'max_ui_errors' => self::MAX_ROW_ERRORS,
            'max_report_rows' => 50000,
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
        $token = (string) ($process->token ?? '');
        $cacheKey = $token !== '' ? self::PROCESS_UPLOAD_CACHE_PREFIX . $token : null;
        if ($cacheKey) {
            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 6,
                'message' => 'Preparing master dataset ingestion…',
                'stage' => 'loading',
                'processed_rows' => 0,
                'total_rows' => 0,
                'started_at' => time(),
                'last_updated_at' => now()->toIso8601String(),
                'errors' => [],
            ], now()->addMinutes(120));
        }

        // Validation now runs inside the Python ingestion script (single XLSX read).
        // We still keep the abort/cancel check here so users can stop quickly.
        if ($cacheKey && (bool) Cache::get($cacheKey . ':abort', false)) {
            Cache::put($cacheKey, [
                'status' => 'canceled',
                'progress' => 0,
                'message' => 'Validation canceled by user.',
                'stage' => 'validation',
                'processed_rows' => 0,
                'total_rows' => 0,
                'started_at' => time(),
                'last_updated_at' => now()->toIso8601String(),
                'errors' => [],
            ], now()->addMinutes(120));

            throw ValidationException::withMessages([
                'upload' => ['Validation canceled by user.'],
            ]);
        }

        $process = MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::VALIDATED);
        $process->update([
            'run_date_raw' => null,
            'master_archive_path' => $zipPath,
            'master_workbook_path' => $this->workbookPlaceholderPath($process->token),
            'storage_disk' => $process->storage_disk ?: config('filesystems.default', 'local'),
            'master_filesize' => $process->master_filesize ?: $this->disk->size($zipPath),
            'user_id' => $userContext['id'] ?? $process->user_id,
            'failure_reason' => null,
        ]);

        $process->refresh();

        $python = app(PythonIngestionService::class);
        $manifestPayload = $this->buildManifestPayload($process, $userContext, $workbookAbsolutePath);
        $python->writeManifest($process, $manifestPayload);
        $python->ensureStatusFile($process);

        try {
            $process = MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::PYTHON_RUNNING);
            $python->run($process);

            $this->assertPythonValidationPassed($process);

            $process = MasterDatasetProcessStatus::set($process->fresh(), MasterDatasetProcessStatus::PYTHON_COMPLETE);

            $this->applyPythonWorkbookMetadata($process);

            // Allow safe re-processing of the same process by clearing stale rows first.
            MasterDatasetRow::query()->where('process_id', $process->id)->delete();

            $process = MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::RECORDS_INSERTING);
            $promoted = app(MasterDatasetStagingPromoter::class)->promote($process);

            $statistics = $this->databaseStatistics($process);

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

    private function assertPythonValidationPassed(MasterDatasetProcess $process): void
    {
        $diskName = (string) ($process->storage_disk ?: config('filesystems.default', 'local'));
        $disk = Storage::disk($diskName);
        $statusPath = (string) ($process->python_status_path ?? '');
        if ($statusPath === '' || ! $disk->exists($statusPath)) {
            return;
        }

        try {
            $raw = (string) $disk->get($statusPath);
        } catch (Throwable) {
            return;
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return;
        }

        $status = (string) ($payload['status'] ?? '');
        if (! in_array($status, ['failed_validation', 'validation_failed', 'failed'], true)) {
            return;
        }

        $errors = $payload['errors'] ?? [];
        $errors = is_array($errors) ? array_values(array_filter($errors, 'is_string')) : [];

        $token = (string) ($process->token ?? '');
        if ($token !== '') {
            $cacheKey = self::PROCESS_UPLOAD_CACHE_PREFIX . $token;
            $reportRelative = $this->validationReportRelativePath($token);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 6,
                'message' => 'Master dataset validation failed.',
                'stage' => 'validation',
                'processed_rows' => (int) ($payload['row_count'] ?? 0),
                'total_rows' => (int) ($payload['row_count'] ?? 0),
                'started_at' => time(),
                'last_updated_at' => now()->toIso8601String(),
                'errors' => $errors,
                'validation_report_path' => $reportRelative,
                'validation_report_disk' => $diskName,
            ], now()->addMinutes(120));
        }

        throw ValidationException::withMessages([
            'upload' => ! empty($errors) ? $errors : ['Master dataset validation failed.'],
        ]);
    }

    private function validationReportRelativePath(string $token): string
    {
        return self::VALIDATION_REPORT_BASE_DIRECTORY . '/' . $token . '/master-validation-errors.csv';
    }

    private function applyPythonWorkbookMetadata(MasterDatasetProcess $process): void
    {
        $diskName = (string) ($process->storage_disk ?: config('filesystems.default', 'local'));
        $disk = Storage::disk($diskName);
        $statusPath = (string) ($process->python_status_path ?? '');
        if ($statusPath === '' || ! $disk->exists($statusPath)) {
            return;
        }

        try {
            $raw = (string) $disk->get($statusPath);
        } catch (Throwable) {
            return;
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return;
        }

        $workbook = $payload['workbook'] ?? null;
        if (! is_array($workbook)) {
            return;
        }

        $updates = [];

        $datasetMonth = trim((string) ($workbook['dataset_month'] ?? ''));
        if ($datasetMonth !== '' && preg_match('/^\d{6}$/', $datasetMonth)) {
            $updates['dataset_month'] = $datasetMonth;
        }

        $arrearsDateRaw = trim((string) ($workbook['arrears_date'] ?? ''));
        if ($arrearsDateRaw !== '') {
            try {
                $updates['arrears_date'] = Carbon::createFromFormat('Y-m-d', $arrearsDateRaw)->toDateString();
            } catch (Throwable) {
                // ignore
            }
        }

        if (! empty($updates)) {
            $process->update($updates);
        }
    }

    /**
     * @deprecated Legacy standalone validator.
     * Validation is now performed inside scripts/ingest_master.py to avoid reading the XLSX twice.
     */
    private function validateWithPolars(MasterDatasetProcess $process, string $workbookAbsolutePath): void
    {
        $token = (string) ($process->token ?? '');
        if ($token === '') {
            return;
        }

        $cacheKey = self::PROCESS_UPLOAD_CACHE_PREFIX . $token;
        $abortKey = $cacheKey . ':abort';
        $diskName = $process->storage_disk ?: config('filesystems.default', 'local');
        $disk = Storage::disk($diskName);

        $reportRelative = $this->validationReportRelativePath($token);
        $disk->makeDirectory(dirname($reportRelative));
        $reportAbsolute = $disk->path($reportRelative);

        Cache::put($cacheKey, [
            'status' => 'processing',
            'progress' => 6,
            'message' => 'Fast-validating master dataset (required fields + duplicates + arrears format)…',
            'stage' => 'validation',
            'processed_rows' => 0,
            'total_rows' => 0,
            'started_at' => time(),
            'last_updated_at' => now()->toIso8601String(),
            'errors' => [],
        ], now()->addMinutes(120));

        if ((bool) Cache::get($abortKey, false)) {
            Cache::put($cacheKey, [
                'status' => 'canceled',
                'progress' => 0,
                'message' => 'Validation canceled by user.',
                'stage' => 'validation',
                'processed_rows' => 0,
                'total_rows' => 0,
                'started_at' => time(),
                'last_updated_at' => now()->toIso8601String(),
                'errors' => [],
            ], now()->addMinutes(120));

            throw ValidationException::withMessages([
                'upload' => ['Validation canceled by user.'],
            ]);
        }

        $python = app(PythonIngestionService::class);
        $command = [
            $python->pythonBinary(),
            base_path('scripts/validate_master.py'),
            '--input',
            $workbookAbsolutePath,
            '--report-out',
            $reportAbsolute,
            '--required',
            implode(',', self::REQUIRED_ROW_COLUMNS),
            '--required-columns',
            implode(',', self::REQUIRED_COLUMNS),
            '--dedupe',
            'PRODUCT_LABEL',
            '--arrears-prefix',
            'NEW_ARREARS_',
            '--max-ui-errors',
            (string) self::MAX_ROW_ERRORS,
        ];

        $proc = new Process($command, base_path());
        $proc->setTimeout(null);
        $proc->run();

        $stdout = trim($proc->getOutput());
        $stderr = trim($proc->getErrorOutput());

        $payload = json_decode($stdout, true);
        if (! is_array($payload)) {
            $message = $stderr !== '' ? $stderr : ($stdout !== '' ? $stdout : 'Python validator produced no output.');
            throw new RuntimeException('Python validator failed: ' . $message);
        }

        $status = (string) ($payload['status'] ?? 'error');
        $rowCount = (int) ($payload['row_count'] ?? 0);
        $errors = $payload['errors'] ?? [];
        $errors = is_array($errors) ? array_values(array_filter($errors, 'is_string')) : [];

        if ($status === 'pass') {
            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 6,
                'message' => 'Master dataset fast-validation passed.',
                'stage' => 'validation',
                'processed_rows' => $rowCount,
                'total_rows' => $rowCount,
                'started_at' => time(),
                'last_updated_at' => now()->toIso8601String(),
                'errors' => [],
            ], now()->addMinutes(120));

            return;
        }

        if ($status === 'fail') {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 6,
                'message' => 'Master dataset validation failed.',
                'stage' => 'validation',
                'processed_rows' => $rowCount,
                'total_rows' => $rowCount,
                'started_at' => time(),
                'last_updated_at' => now()->toIso8601String(),
                'errors' => $errors,
                'validation_report_path' => $reportRelative,
                'validation_report_disk' => $diskName,
            ], now()->addMinutes(120));

            throw ValidationException::withMessages([
                'upload' => ! empty($errors) ? $errors : ['Master dataset validation failed.'],
            ]);
        }

        $message = (string) ($payload['message'] ?? 'Python validator error.');
        if ($stderr !== '') {
            $message .= ' ' . $stderr;
        }

        throw new RuntimeException('Python validator error: ' . trim($message));
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

    private function assertArrearsColumnIntegrity(MasterDatasetProcess $process, array $dataRows, string $arrearsLetter): void
    {
        $errors = [];
        $maxReached = false;

        $reportRows = [];
        $maxReportRows = 50000;

        $token = (string) ($process->token ?? '');
        $cacheKey = $token !== '' ? self::PROCESS_UPLOAD_CACHE_PREFIX . $token : null;

        $diskName = (string) ($process->storage_disk ?: config('filesystems.default', 'local'));
        $disk = Storage::disk($diskName);
        $reportRelativePath = $token !== '' ? $this->validationReportRelativePath($token) : null;

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
                'message' => 'Validating arrears column format…',
                'stage' => 'validation',
                'processed_rows' => 0,
                'total_rows' => $totalRows,
                'started_at' => $startTime,
                'last_updated_at' => now()->toIso8601String(),
                'errors' => [],
            ], now()->addMinutes(120));
        }

        $processed = 0;
        foreach ($dataRows as $excelRow => $columns) {
            if (! is_array($columns) || ! $this->rowHasData($columns)) {
                continue;
            }

            $processed++;

            if ($cacheKey && (($processed % self::VALIDATION_PROGRESS_UPDATE_EVERY === 0) || $processed === $totalRows)) {
                Cache::put($cacheKey, [
                    'status' => 'processing',
                    'progress' => 6,
                    'message' => 'Validating arrears column format…',
                    'stage' => 'validation',
                    'processed_rows' => $processed,
                    'total_rows' => $totalRows,
                    'started_at' => $startTime,
                    'last_updated_at' => now()->toIso8601String(),
                    'errors' => [],
                ], now()->addMinutes(120));
            }

            $arrearsRaw = trim((string) ($columns[$arrearsLetter] ?? ''));
            if ($arrearsRaw !== '' && $arrearsRaw !== '-') {
                $normalized = str_replace([',', ' '], '', $arrearsRaw);
                if (! is_numeric($normalized)) {
                    if (count($errors) < self::MAX_ROW_ERRORS) {
                        $errors[] = sprintf(
                            'Row %d, column %s: expected numeric value or "-".',
                            (int) $excelRow,
                            self::NEW_ARREARS_PREFIX . '*'
                        );
                    }

                    if (count($reportRows) < $maxReportRows) {
                        $reportRows[] = [
                            (int) $excelRow,
                            self::NEW_ARREARS_PREFIX . '*',
                            'INVALID_NUMBER',
                            $arrearsRaw,
                            '',
                        ];
                    }

                    if (count($errors) >= self::MAX_ROW_ERRORS) {
                        $maxReached = true;
                        if (count($reportRows) >= $maxReportRows) {
                            break;
                        }
                    }
                }
            }
        }

        if (! empty($errors)) {
            if ($reportRelativePath) {
                try {
                    $disk->makeDirectory(dirname($reportRelativePath));

                    $fh = fopen('php://temp', 'w+');
                    if ($fh !== false) {
                        fputcsv($fh, ['excel_row', 'column', 'error_code', 'value', 'first_seen_row']);
                        foreach ($reportRows as $row) {
                            fputcsv($fh, $row);
                        }
                        rewind($fh);
                        $csv = stream_get_contents($fh);
                        fclose($fh);

                        if (is_string($csv)) {
                            $disk->put($reportRelativePath, $csv);
                        }
                    }
                } catch (Throwable) {
                    // ignore report-writing failures; validation errors still surface
                }
            }

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
                    'validation_report_path' => $reportRelativePath,
                    'validation_report_disk' => $diskName,
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
