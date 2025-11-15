<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessExcelFinalize implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly string $token,
        private readonly string $storedPath,
        private readonly string $originalName,
        private readonly array $headers,
        private readonly int $chunkCount
    ) {
    }

    public function handle(): void
    {
        $rows = $this->mergeChunkRows();

        $processedData = [
            'headers' => $this->headers,
            'rows' => $rows,
            'total_rows' => (int) (Cache::get($this->cacheKey())['total_rows'] ?? count($rows)),
            'original_filename' => $this->originalName,
        ];

        $datasetPath = $this->datasetPath();
        Storage::put($datasetPath, json_encode(['data' => $processedData], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->updateProgressState([
            'status' => 'complete',
            'message' => 'Processing complete.',
            'progress' => 100,
            'dataset_path' => $datasetPath,
            'processed_rows' => $processedData['total_rows'],
        ]);

        $this->cleanupChunks();
    }

    private function mergeChunkRows(): array
    {
        $directory = sprintf('processed/%s/chunks', $this->token);

        if (! Storage::exists($directory)) {
            return [];
        }

        $files = collect(Storage::files($directory))
            ->filter(fn ($path) => str_contains($path, 'chunk_'))
            ->sort()
            ->values();

        $rows = [];

        foreach ($files as $path) {
            $payload = json_decode(Storage::get($path), true);

            if (! is_array($payload)) {
                throw new RuntimeException('Unable to read chunk payload: ' . $path);
            }

            $chunkRows = $payload['rows'] ?? [];

            foreach ($chunkRows as $rowIndex => $columns) {
                $rows[$rowIndex] = $columns;
            }
        }

        ksort($rows, SORT_NUMERIC);

        return $rows;
    }

    private function cleanupChunks(): void
    {
        $directory = sprintf('processed/%s/chunks', $this->token);
        Storage::deleteDirectory($directory);
    }

    private function updateProgressState(array $overrides): void
    {
        $state = Cache::get($this->cacheKey(), []);
        $payload = array_merge($state, $overrides);
        Cache::put($this->cacheKey(), $payload, now()->addMinutes(60));
    }

    private function datasetPath(): string
    {
        return 'processed/' . $this->token . '/final.json';
    }

    private function cacheKey(): string
    {
        return 'process:upload:' . $this->token;
    }
}
