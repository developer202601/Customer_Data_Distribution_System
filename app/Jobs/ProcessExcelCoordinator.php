<?php

namespace App\Jobs;

use App\Support\ChunkReadFilter;
use App\Support\ProcessesExcelRows;
use App\Support\UploadProcessManager;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class ProcessExcelCoordinator implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ProcessesExcelRows;

    private const CHUNK_SIZE = 30000;

    public function __construct(
        private readonly string $token,
        private readonly string $storedPath,
        private readonly string $originalName
    ) {
    }

    public function handle(): void
    {
        try {
            $this->process();
        } catch (Throwable $exception) {
            $this->failProgress($exception->getMessage());
            throw $exception;
        }
    }

    private function process(): void
    {
        $this->updateProgressState([
            'status' => 'processing',
            'message' => 'Opening Excel workbook…',
            'progress' => 5,
            'processed_rows' => 0,
            'total_rows' => null,
            'chunks_total' => 0,
            'chunks_completed' => 0,
        ]);

        $workbookPath = Storage::path($this->storedPath);
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);

        $worksheetInfo = $reader->listWorksheetInfo($workbookPath);
        $activeSheet = Arr::first($worksheetInfo);

        if (! $activeSheet) {
            throw new RuntimeException('Unable to read worksheet information from the upload.');
        }

        $totalRows = (int) ($activeSheet['totalRows'] ?? 0);

        if ($totalRows < 2) {
            throw new RuntimeException('The spreadsheet must include at least one header row and one data row.');
        }

        $headerRow = $this->loadHeaderRow($workbookPath);
        $headers = $this->prepareHeaders($headerRow);

        $dataRowCount = max($totalRows - 1, 0);

        $this->updateProgressState([
            'message' => 'Queuing row chunks…',
            'total_rows' => $dataRowCount,
            'progress' => 8,
            'headers' => $headers,
        ]);

        if ($dataRowCount === 0) {
            // No data rows; immediately finalise to keep behaviour consistent.
            ProcessExcelFinalize::dispatch($this->token, $this->storedPath, $this->originalName, $headers, 0);
            return;
        }

        $jobs = $this->buildChunkJobs($headers, $totalRows);

        $this->updateProgressState([
            'chunks_total' => count($jobs),
            'chunks_completed' => 0,
        ]);

        $token = $this->token;
        $storedPath = $this->storedPath;
        $originalName = $this->originalName;

        $pendingBatch = Bus::batch($jobs)
            ->name('process-upload:' . $token)
            ->then(static function (Batch $batch) use ($token, $storedPath, $originalName, $headers) {
                ProcessExcelFinalize::dispatch(
                    $token,
                    $storedPath,
                    $originalName,
                    $headers,
                    $batch->totalJobs
                );
            })
            ->catch(static function (Batch $batch, Throwable $exception) use ($token) {
                self::failProgressForToken($token, $exception->getMessage());
                UploadProcessManager::purgeQueuedJobs($token);
            })
            ->finally(static function (Batch $batch) use ($token) {
                // store batch id for debugging/reference
                self::updateProgressStateForToken($token, [
                    'batch_id' => $batch->id,
                ]);
            });

        $batch = $pendingBatch->dispatch();

        self::updateProgressStateForToken($token, [
            'batch_id' => $batch->id,
        ]);
    }

    private function buildChunkJobs(array $headers, int $totalRows): array
    {
        $jobs = [];
        $dataStart = 2;
        $chunkIndex = 0;

        while ($dataStart <= $totalRows) {
            $dataEnd = min($dataStart + self::CHUNK_SIZE - 1, $totalRows);

            $jobs[] = new ProcessExcelChunk(
                $this->token,
                $this->storedPath,
                $this->originalName,
                $headers,
                $chunkIndex,
                $dataStart,
                $dataEnd
            );

            $dataStart = $dataEnd + 1;
            $chunkIndex++;
        }

        return $jobs;
    }

    private function loadHeaderRow(string $workbookPath): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(false);
        $reader->setReadFilter(new ChunkReadFilter(1, 1));

        $spreadsheet = $reader->load($workbookPath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $rowKeys = array_keys($rows);
        $headerKey = $rowKeys[0] ?? null;

        if ($headerKey === null) {
            throw new RuntimeException('Unable to locate the header row in the spreadsheet.');
        }

        return $rows[$headerKey];
    }

    private function updateProgressState(array $overrides): void
    {
        $state = Cache::get($this->cacheKey(), []);
        $payload = array_merge($state, $overrides);
        Cache::put($this->cacheKey(), $payload, now()->addMinutes(60));
    }

    private function failProgress(string $message): void
    {
        self::failProgressForToken($this->token, $message);
        UploadProcessManager::purgeQueuedJobs($this->token);
    }

    private function cacheKey(): string
    {
        return self::cacheKeyForToken($this->token);
    }

    private static function failProgressForToken(string $token, string $message): void
    {
        self::updateProgressStateForToken($token, [
            'status' => 'failed',
            'progress' => 100,
            'message' => 'Processing failed.',
            'error' => $message,
        ]);
    }

    private static function updateProgressStateForToken(string $token, array $overrides): void
    {
        $state = Cache::get(self::cacheKeyForToken($token), []);
        $payload = array_merge($state, $overrides);
        Cache::put(self::cacheKeyForToken($token), $payload, now()->addMinutes(60));
    }

    private static function cacheKeyForToken(string $token): string
    {
        return 'process:upload:' . $token;
    }
}
