<?php

namespace App\Jobs;

use App\Support\ChunkReadFilter;
use App\Support\ProcessesExcelRows;
use App\Support\UploadProcessManager;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class ProcessExcelChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable, ProcessesExcelRows;

    private const EXPECTED_COLUMNS = [
        'RUN_DATE',
        'REGION',
        'RTOM',
        'CUSTOMER_REF',
        'ACCOUNT_NUM',
        'PRODUCT_LABEL',
        'MEDIUM',
        'CUSTOMER_SEGMENT',
        'ADDRESS_NAME',
        'FULL_ADDRESS',
        'LATEST_BILL_MNY',
        'MOBILE_CONTACT_TEL',
        'EMAIL_ADDRESS',
        'CREDIT_SCORE',
        'CREDIT_CLASS_NAME',
        'BILL_HANDLING_CODE_NAME',
        'AGE_MONTHS',
        'SALES_PERSON',
        'ACCOUNT_MANAGER',
        'SLT_GL_SUB_SEGMENT',
        'BILLING_CENTRE',
        'PROVINCE',
        'NEXT_BILL_DTM',
        'BILL_MONTH',
        'LATEST_BILL_DTM',
        'INVOICING_CO_ID',
        'INVOICING_CO_NAME',
        'PRODUCT_SEQ',
        'PRODUCT_ID',
        'LATEST_PRODUCT_STATUS',
        'BILL_HANDLING_CODE',
        'SLT_BUSINESS_LINE_VALUE',
        'SALES_CHANNEL',
    ];

    private const OPTIONAL_COLUMNS = [
        'ADDRESS_NAME',
        'EMAIL_ADDRESS',
        'CREDIT_SCORE',
        'SALES_PERSON',
        'SALES_CHANNEL',
    ];

    private const FILTER_MEDIUM_VALUES = ['COPPER', 'FTTH'];
    private const FILTER_STATUS_VALUE = 'OK';
    private const FILTER_MIN_ARREARS = 2400;

    public function __construct(
        private readonly string $token,
        private readonly string $storedPath,
        private readonly string $originalName,
        private readonly array $headers,
        private readonly int $chunkIndex,
        private readonly int $startRow,
        private readonly int $endRow
    ) {
    }

    public function handle(): void
    {
        try {
            $rows = $this->loadChunkRows();
            $dataRows = $this->stripHeader($rows);

            $errors = [];
            $filteredRows = [];
            $skippedRows = [];
            $processedRows = 0;

            foreach ($dataRows as $rowIndex => $columns) {
                if (! $this->rowHasData($columns)) {
                    continue;
                }

                $processedRows++;
                $passesValidation = true;

                foreach ($this->headers as $columnLetter => $headerMeta) {
                    $normalised = $headerMeta['normalised'];

                    if (! $this->isExpectedColumn($normalised)) {
                        continue;
                    }

                    $value = $columns[$columnLetter] ?? null;

                    if ($this->isEmpty($value) && ! in_array($normalised, self::OPTIONAL_COLUMNS, true)) {
                        $errors[] = sprintf('Row %d: "%s" cannot be empty.', $rowIndex, $headerMeta['label']);
                        $passesValidation = false;
                    }

                    if ($normalised === 'LATEST_BILL_MNY' && ! $this->isValidLatestBill($value)) {
                        $errors[] = sprintf('Row %d: "%s" must contain a numeric amount or "-".', $rowIndex, $headerMeta['label']);
                        $passesValidation = false;
                    }

                    // Validate dynamic NEW_ARREARS_YYYYMMDD columns contain only numeric amount characters
                    if (str_starts_with($normalised, 'NEW_ARREARS_') && ! $this->isValidArrears($value)) {
                        $errors[] = sprintf('Row %d: "%s" must contain only a numeric amount (no letters or currency symbols).', $rowIndex, $headerMeta['label']);
                        $passesValidation = false;
                    }
                }

                $filterResult = $this->evaluateFilterResult($columns, $this->headers);

                if ($passesValidation && $filterResult['passes']) {
                    $filteredRows[$rowIndex] = $columns;
                } elseif ($passesValidation) {
                    $skippedRows[$rowIndex] = [
                        'row_index' => $rowIndex,
                        'reason' => $filterResult['reason'] ?? 'Filtered out by eligibility rules.',
                        'columns' => $columns,
                        'reason_code' => $this->buildFilterReasonCode($filterResult),
                    ];
                }
            }

            if (! empty($errors)) {
                $sample = array_slice($errors, 0, 25);
                throw new RuntimeException('Validation failed: ' . implode(' ', $sample));
            }

            $this->storeChunkResults($filteredRows, $skippedRows);
            $this->updateProgress($processedRows);
        } catch (Throwable $exception) {
            $this->handleFailure($exception);
            throw $exception;
        }
    }

    private function loadChunkRows(): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $reader->setReadFilter(new ChunkReadFilter($this->startRow, $this->endRow));

        $spreadsheet = $reader->load(Storage::path($this->storedPath));
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if (empty($rows)) {
            throw new RuntimeException('Chunk ' . $this->chunkIndex . ' could not be read from the spreadsheet.');
        }

        return $rows;
    }

    private function stripHeader(array $rows): array
    {
        $rowKeys = array_keys($rows);
        $headerKey = $rowKeys[0] ?? null;

        if ($headerKey !== null) {
            unset($rows[$headerKey]);
        }

        return $rows;
    }

    private function storeChunkResults(array $filteredRows, array $skippedRows): void
    {
        $chunkPath = $this->chunkPath();
        Storage::makeDirectory(dirname($chunkPath));
        $payload = [
            'rows' => $filteredRows,
            'skipped' => $skippedRows,
        ];

        Storage::put($chunkPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private function updateProgress(int $processedRows): void
    {
        Cache::lock($this->cacheKey() . ':lock', 5)->block(10, function () use ($processedRows) {
            $state = Cache::get($this->cacheKey(), []);
            $currentProcessed = (int) ($state['processed_rows'] ?? 0);
            $currentChunks = (int) ($state['chunks_completed'] ?? 0);
            $totalRows = max((int) ($state['total_rows'] ?? 0), 1);

            $state['processed_rows'] = min($totalRows, $currentProcessed + $processedRows);
            $state['chunks_completed'] = $currentChunks + 1;

            $ratio = $totalRows > 0 ? $state['processed_rows'] / $totalRows : 0;
            $state['progress'] = round(min(95, 5 + ($ratio * 90)), 2);
            $state['message'] = sprintf(
                'Processing rows %d–%d (%d/%d chunks)…',
                $this->startRow,
                $this->endRow,
                $state['chunks_completed'],
                max((int) ($state['chunks_total'] ?? 0), 1)
            );

            Cache::put($this->cacheKey(), $state, now()->addMinutes(60));
        });
    }

    private function chunkPath(): string
    {
        return sprintf('processed/%s/chunks/chunk_%05d.json', $this->token, $this->chunkIndex);
    }

    private function cacheKey(): string
    {
        return 'process:upload:' . $this->token;
    }

    private function handleFailure(Throwable $exception): void
    {
        $batch = method_exists($this, 'batch') ? $this->batch() : null;
        $batchId = $batch ? $batch->id : null;
        UploadProcessManager::cancel($this->token, $batchId, $exception->getMessage(), 'failed');
    }
}
