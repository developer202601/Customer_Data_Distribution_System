<?php

namespace App\Jobs;

use App\Support\ProcessesExcelRows;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ValidateExclusionWorkbook implements ShouldQueue
{
    use Queueable, ProcessesExcelRows;

    private const CACHE_PREFIX = 'process:exclusion:upload:';
    private const REQUIRED_COLUMN = 'ACCOUNT_NUM';
    private const MAX_ERRORS = 20;

    public function __construct(
        private string $token,
        private string $filePath,
        private string $originalName
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        // Avoid poisoning long-lived queue worker with a finite global timeout.
        ini_set('max_execution_time', 0);

        $cacheKey = self::CACHE_PREFIX . $this->token;

        try {
            Cache::put($cacheKey, [
                'status' => 'processing',
                'progress' => 5,
                'message' => 'Validating exclusion workbook…',
                'processed_rows' => 0,
                'total_rows' => null,
                'stage' => 'validation',
                'started_at' => time(),
                'errors' => [],
                'file_name' => $this->originalName,
                'last_updated_at' => now()->toIso8601String(),
            ], now()->addMinutes(120));

            if (! str_ends_with(strtolower($this->filePath), '.xlsx')) {
                throw new \RuntimeException('Please upload a Microsoft Excel (.xlsx) workbook.');
            }

            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($this->filePath);
            $sheet = $spreadsheet->getActiveSheet();

            $headerRow = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, true, true);
            $headerRow = $headerRow[1] ?? [];
            $headers = $this->prepareHeaders($headerRow);
            $headerMap = $this->buildHeaderMap($headers);

            $requiredLetter = $headerMap[self::REQUIRED_COLUMN] ?? null;
            if (! $requiredLetter) {
                throw new \RuntimeException('Missing required column: ACCOUNT_NUM');
            }

            $totalRows = max(0, $sheet->getHighestRow() - 1);
            $processed = 0;
            $errors = [];
            $chunk = max(1, (int) ceil(max($totalRows, 1) / 100));

            foreach ($sheet->getRowIterator(2) as $row) {
                $rowIndex = $row->getRowIndex();
                $processed++;

                $value = $sheet->getCell($requiredLetter . $rowIndex)->getValue();
                if ($this->isEmpty($value)) {
                    if (count($errors) < self::MAX_ERRORS) {
                        $errors[] = "Row {$rowIndex}, column ACCOUNT_NUM: value is required.";
                    }
                }

                if ($processed % $chunk === 0 || $processed === $totalRows) {
                    $progress = 5 + (int) (($processed / max(1, $totalRows)) * 90);
                    Cache::put($cacheKey, [
                        'status' => 'processing',
                        'progress' => min(95, $progress),
                        'message' => "Validating row {$processed} / {$totalRows}",
                        'processed_rows' => $processed,
                        'total_rows' => $totalRows,
                        'stage' => 'validation',
                        'started_at' => time(),
                        'errors' => $errors,
                        'file_name' => $this->originalName,
                        'last_updated_at' => now()->toIso8601String(),
                    ], now()->addMinutes(120));
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if (! empty($errors)) {
                Cache::put($cacheKey, [
                    'status' => 'failed',
                    'progress' => 0,
                    'message' => 'Validation failed',
                    'processed_rows' => $processed,
                    'total_rows' => $totalRows,
                    'stage' => 'validation',
                    'errors' => $errors,
                    'file_name' => $this->originalName,
                    'last_updated_at' => now()->toIso8601String(),
                ], now()->addMinutes(120));

                return;
            }

            Cache::put($cacheKey, [
                'status' => 'ready',
                'progress' => 100,
                'message' => 'Exclusion workbook validated',
                'processed_rows' => $processed,
                'total_rows' => $totalRows,
                'stage' => 'validation',
                'errors' => [],
                'file_name' => $this->originalName,
                'last_updated_at' => now()->toIso8601String(),
            ], now()->addMinutes(120));
        } catch (Throwable $exception) {
            Cache::put($cacheKey, [
                'status' => 'failed',
                'progress' => 0,
                'message' => 'Validation failed: ' . $exception->getMessage(),
                'processed_rows' => 0,
                'total_rows' => 0,
                'stage' => 'validation',
                'error' => $exception->getMessage(),
                'errors' => [],
                'file_name' => $this->originalName,
                'last_updated_at' => now()->toIso8601String(),
            ], now()->addMinutes(120));
        }
    }
}
