<?php

namespace App\Jobs;

use App\Support\MasterDatasetImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\UploadedFile;
use Throwable;

class ValidateMasterFile implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private string $token,
        private string $filePath,
        private string $originalName,
        private string $mimeType,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 1200);

        // Use PhpSpreadsheet disk-based caching to avoid in-memory blowup on large sheets.
        if (class_exists(\PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory::class)) {
            try {
                \PhpOffice\PhpSpreadsheet\Settings::setCacheStorageMethod(
                    \PhpOffice\PhpSpreadsheet\CachedObjectStorageFactory::cache_to_discISAM,
                    ['dir' => storage_path('app/tmp')]
                );
            } catch (\Throwable $e) {
                // fall back to in-memory if disk caching is unavailable
            }
        }

        $cacheKey = 'process:upload:' . $this->token;
        $abortKey = $cacheKey . ':abort';

        Cache::forget($abortKey);

        try {
            Cache::put($cacheKey, [
                'status' => 'queued',
                'progress' => 50,
                'message' => 'Upload complete; queued for validation...',
                'processed_rows' => 0,
                'total_rows' => null,
                'started_at' => time(),
            ], now()->addMinutes(120));

            $uploadedFile = new UploadedFile(
                $this->filePath,
                $this->originalName,
                $this->mimeType,
                null,
                true
            );

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 50,
                'message' => 'Validation started...',
                'processed_rows' => 0,
                'total_rows' => 0,
                'started_at' => time(),
            ], now()->addMinutes(120));

            // Setup importer and extract workbook from ZIP archive (DIY extraction because extractWorkbook() is private)
            $zip = new \ZipArchive();
            if ($zip->open($this->filePath) !== true) {
                throw new \RuntimeException('Unable to open the uploaded ZIP archive for validation.');
            }

            try {
                Cache::put($cacheKey, [
                    'status' => 'processing',
                    'progress' => 52,
                    'message' => 'Extracting workbook from ZIP...',
                    'stage' => 'extraction',
                    'processed_rows' => 0,
                    'total_rows' => null,
                    'started_at' => time(),
                    'errors' => [],
                    'file_name' => $this->originalName,
                ], now()->addMinutes(120));

                $entries = [];
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $name = $stat['name'] ?? '';
                    $name = str_replace('\\', '/', $name);

                    if ($name === '' || str_ends_with($name, '/')) {
                        continue;
                    }

                    $base = basename($name);
                    if ($base === '' || $base === '.DS_Store' || str_starts_with($base, '._') || str_starts_with($base, '~$') || str_starts_with($base, '.')) {
                        continue;
                    }

                    if (str_ends_with(strtolower($name), '.xlsx')) {
                        $entries[] = $name;
                    }
                }

                if (empty($entries)) {
                    throw new \RuntimeException('The ZIP file must contain exactly one Excel (.xlsx) workbook. None were found.');
                }

                if (count($entries) > 1) {
                    throw new \RuntimeException('The ZIP file must contain exactly one Excel (.xlsx) workbook.');
                }

                $entryName = $entries[0];
                $rawStream = $zip->getStream($entryName);
                if (! $rawStream) {
                    throw new \RuntimeException('Unable to read the Excel workbook inside the uploaded ZIP file.');
                }

                $workbookPath = storage_path('app/tmp/validated_' . $this->token . '.xlsx');
                $workbookDir = dirname($workbookPath);
                if (! is_dir($workbookDir)) {
                    mkdir($workbookDir, 0755, true);
                }

                $targetHandle = fopen($workbookPath, 'wb');
                if (! $targetHandle) {
                    fclose($rawStream);
                    throw new \RuntimeException('Unable to create temporary workbook file.');
                }

                stream_copy_to_stream($rawStream, $targetHandle);
                fclose($rawStream);
                fclose($targetHandle);
            } finally {
                $zip->close();
            }

            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($workbookPath);
            $sheet = $spreadsheet->getActiveSheet();

            // Get headers from first row
            $headers = [];
            foreach ($sheet->getRowIterator(1, 1) as $headerRow) {
                foreach ($headerRow->getCellIterator() as $cell) {
                    $column = $cell->getColumn();
                    $value = strtoupper(trim($cell->getValue() ?? ''));
                    if ($value !== '') {
                        $headers[$column] = $value;
                    }
                }
            }
            $headerMap = array_flip($headers);

            $totalRows = max(0, $sheet->getHighestRow() - 1); // exclude header

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 57,
                'message' => "Workbook loaded: {$totalRows} data rows detected; starting row validation...",
                'stage' => 'validation',
                'processed_rows' => 0,
                'total_rows' => $totalRows,
                'started_at' => time(),
                'errors' => [],
                'file_name' => $this->originalName,
            ], now()->addMinutes(120));

            if ($totalRows <= 0) {
                Cache::put($cacheKey, [
                    'status' => 'failed',
                    'progress' => 0,
                    'message' => 'Validation failed: workbook contains no data rows.',
                    'processed_rows' => 0,
                    'total_rows' => 0,
                    'started_at' => time(),
                    'errors' => ['Workbook contains no data rows.'],
                    'file_name' => $this->originalName,
                ], now()->addMinutes(120));
                if (file_exists($workbookPath)) {
                    unlink($workbookPath);
                }
                return;
            }

            // Validation scope requested by user:
            // - PRODUCT_LABEL: required + duplicates
            // - RUN_DATE: required only
            // - ACCOUNT_NUM: required only
            $requiredColumns = [
                'RUN_DATE',
                'ACCOUNT_NUM',
                'PRODUCT_LABEL',
            ];

            $errors = [];
            $maxErrors = 20;
            $seenProductComposite = [];

            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 55,
                'message' => 'Loaded spreadsheet; validating rows...',
                'processed_rows' => 0,
                'total_rows' => $totalRows,
                'started_at' => time(),
                'errors' => [],
                'file_name' => $this->originalName,
            ], now()->addMinutes(120));

            $chunk = max(1, (int) ceil($totalRows / 100));
            $processed = 0;

            $rowIterator = $sheet->getRowIterator(2); // start from data rows
            foreach ($rowIterator as $row) {
                if ((bool) Cache::get($abortKey, false)) {
                    Cache::put($cacheKey, [
                        'status' => 'canceled',
                        'progress' => 0,
                        'message' => 'Validation canceled by user.',
                        'stage' => 'validation',
                        'processed_rows' => $processed,
                        'total_rows' => $totalRows,
                        'started_at' => time(),
                        'errors' => $errors,
                        'file_name' => $this->originalName,
                    ], now()->addMinutes(120));

                    if (isset($spreadsheet)) {
                        $spreadsheet->disconnectWorksheets();
                        unset($spreadsheet);
                    }
                    if (!empty($workbookPath) && file_exists($workbookPath)) {
                        @unlink($workbookPath);
                    }

                    return;
                }

                $rowIndex = $row->getRowIndex();
                $processed++;

                // Validate required columns
                $rowProblems = false;
                foreach ($requiredColumns as $col) {
                    $upperCol = strtoupper($col);
                    if (!isset($headerMap[$upperCol])) {
                        if (count($errors) < $maxErrors) {
                            $errors[] = "Missing required column: {$col}";
                        }
                        $rowProblems = true;
                        break; // skip duplicate checks for this row
                    }
                    $colLetter = $headerMap[$upperCol];
                    $cell = $sheet->getCell($colLetter . $rowIndex);
                    $value = trim((string) ($cell->getValue() ?? ''));
                    if ($value === '') {
                        if (count($errors) < $maxErrors) {
                            $errors[] = "Row {$rowIndex}, column {$col}: value is required.";
                        }
                        $rowProblems = true;
                    }
                }

                // Check duplicates by PRODUCT_LABEL + PRODUCT_SEQ
                if (! $rowProblems && isset($headerMap['PRODUCT_LABEL']) && isset($headerMap['PRODUCT_SEQ'])) {
                    $productLabelCell = $sheet->getCell($headerMap['PRODUCT_LABEL'] . $rowIndex);
                    $productLabel = trim((string) ($productLabelCell->getValue() ?? ''));
                    $productSeqCell = $sheet->getCell($headerMap['PRODUCT_SEQ'] . $rowIndex);
                    $productSeq = trim((string) ($productSeqCell->getValue() ?? ''));

                    if ($productLabel !== '' && $productSeq !== '') {
                        $key = mb_strtolower($productLabel) . '|' . mb_strtolower($productSeq);
                        if (isset($seenProductComposite[$key])) {
                            if (count($errors) < $maxErrors) {
                                $errors[] = "Row {$rowIndex}, columns PRODUCT_LABEL/PRODUCT_SEQ: duplicate value already found at row {$seenProductComposite[$key]}.";
                            }
                        } else {
                            $seenProductComposite[$key] = $rowIndex;
                        }
                    }
                }

                if (count($errors) >= $maxErrors) {
                    // stop collecting once max reached but keep running through rows to update progress
                    if ($processed % $chunk === 0 || $processed === $totalRows) {
                        $progress = 55 + (int)(($processed / max(1, $totalRows)) * 30);
                        Cache::put($cacheKey, [
                            'status' => 'processing',
                            'progress' => min(90, $progress),
                            'message' => "Validating row {$processed} / {$totalRows}",
                            'stage' => 'validation',
                            'processed_rows' => $processed,
                            'total_rows' => $totalRows,
                            'started_at' => time(),
                            'errors' => $errors,
                            'file_name' => $this->originalName,
                        ], now()->addMinutes(120));
                    }
                    continue;
                }

                if ($processed % $chunk === 0 || $processed === $totalRows) {
                    $progress = 55 + (int)(($processed / max(1, $totalRows)) * 30); // map validation to 55-85

                    Cache::put($cacheKey, [
                        'status' => 'processing',
                        'progress' => min(90, $progress),
                        'message' => "Validating row {$processed} / {$totalRows}",
                        'stage' => 'validation',
                        'processed_rows' => $processed,
                        'total_rows' => $totalRows,
                        'started_at' => time(),
                        'errors' => $errors,
                        'file_name' => $this->originalName,
                    ], now()->addMinutes(120));
                }
            }

            if (!empty($errors)) {
                Cache::put($cacheKey, [
                    'status' => 'failed',
                    'progress' => 0,
                    'message' => 'Validation failed',
                    'stage' => 'validation',
                    'processed_rows' => $processed,
                    'total_rows' => $totalRows,
                    'started_at' => time(),
                    'errors' => $errors,
                    'file_name' => $this->originalName,
                ], now()->addMinutes(120));

                if (file_exists($workbookPath)) {
                    unlink($workbookPath);
                }
                if (isset($spreadsheet)) {
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                }

                return;
            }

            if ((bool) Cache::get($abortKey, false)) {
                Cache::put($cacheKey, [
                    'status' => 'canceled',
                    'progress' => 0,
                    'message' => 'Validation canceled by user.',
                    'stage' => 'validation',
                    'processed_rows' => $processed,
                    'total_rows' => $totalRows,
                    'started_at' => time(),
                    'errors' => $errors,
                    'file_name' => $this->originalName,
                ], now()->addMinutes(120));

                if (isset($spreadsheet)) {
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                }
                if (!empty($workbookPath) && file_exists($workbookPath)) {
                    @unlink($workbookPath);
                }

                return;
            }

            // Row-level validation done, now import step
            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 90,
                'message' => 'Row validation complete; importing processed data...',
                'stage' => 'import',
                'processed_rows' => $processed,
                'total_rows' => $totalRows,
                'started_at' => time(),
                'errors' => $errors,
                'file_name' => $this->originalName,
            ], now()->addMinutes(120));
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }

            // If validation passes, proceed to import
            app(MasterDatasetImporter::class)->import($uploadedFile, null);

            // Remove temporary workbook copy after import
            if (!empty($workbookPath) && file_exists($workbookPath)) {
                @unlink($workbookPath);
            }

            Cache::put($cacheKey, [
                'status' => 'ready',
                'progress' => 100,
                'message' => 'Validation complete',
                'processed_rows' => $totalRows,
                'total_rows' => $totalRows,
                'started_at' => time(),
                'errors' => [],
                'file_name' => $this->originalName,
            ], now()->addMinutes(120));

        } catch (Throwable $e) {
            // Ensure temp workbook cleanup on failure as well
            if (!empty($workbookPath) && file_exists($workbookPath)) {
                @unlink($workbookPath);
            }

            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 0,
                'message' => 'Validation failed: ' . $e->getMessage(),
                'processed_rows' => $processed ?? 0,
                'total_rows' => $totalRows ?? 0,
                'started_at' => time(),
                'error' => $e->getMessage(),
                'errors' => $errors ?? [],
                'file_name' => $this->originalName,
            ], now()->addMinutes(120));
        }
    }
}
