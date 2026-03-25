<?php

namespace App\Jobs;

use App\Support\ProcessesExcelRows;
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
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ProcessesExcelRows;

    public function __construct(
        private readonly string $token,
        private readonly string $storedPath,
        private readonly string $originalName,
        private readonly array $headers,
        private readonly int $chunkCount
    ) {}

    public function handle(): void
    {
        $manifest = $this->buildManifest();

        $datasetPath = $this->datasetPath();
        Storage::put($datasetPath, json_encode(['manifest' => $manifest], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->updateProgressState([
            'status' => 'complete',
            'message' => 'Processing complete.',
            'progress' => 100,
            'dataset_path' => $datasetPath,
            'processed_rows' => $manifest['filtered_rows_total'],
        ]);
    }

    private function buildManifest(): array
    {
        $directory = sprintf('processed/%s/chunks', $this->token);

        $state = Cache::get($this->cacheKey(), []);

        if (! Storage::exists($directory)) {
            $sourceTotal = (int) ($state['total_rows'] ?? 0);

            return [
                'token' => $this->token,
                'stored_path' => $this->storedPath,
                'original_filename' => $this->originalName,
                'headers' => $this->headers,
                'source_total_rows' => $sourceTotal,
                'filtered_rows_total' => 0,
                'filtered_out_total' => 0,
                'vip_rows_total' => 0,
                'chunks' => [],
                'filtered_out_reason_counts' => [],
                'filtered_out_preview' => [],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];
        }

        $chunks = collect(Storage::files($directory))
            ->filter(fn($path) => str_contains($path, 'chunk_'))
            ->sort()
            ->values();

        $headerMap = $this->buildHeaderMap($this->headers);
        $totalFiltered = 0;
        $totalSkipped = 0;
        $vipTotal = 0;

        $reasonCounts = [];
        $filteredOutPreview = [];
        $previewLimit = 10;

        $chunkMeta = [];

        foreach ($chunks as $path) {
            $payload = json_decode(Storage::get($path), true);

            if (! is_array($payload)) {
                throw new RuntimeException('Unable to read chunk payload: ' . $path);
            }

            $rows = $payload['rows'] ?? [];
            $skipped = $payload['skipped'] ?? [];
            $meta = $payload['meta'] ?? [];

            $filteredCount = is_array($rows) ? count($rows) : 0;
            $skippedCount = is_array($skipped) ? count($skipped) : 0;

            $chunkVip = 0;

            if (! empty($rows)) {
                foreach ($rows as $columns) {
                    if (! is_array($columns)) {
                        continue;
                    }

                    if ($this->rowIsVip($columns, $headerMap)) {
                        $chunkVip++;
                    }
                }
            }

            if (! empty($skipped)) {
                foreach ($skipped as $rowIndex => $entry) {
                    if (! is_array($entry)) {
                        continue;
                    }

                    $reason = $entry['reason'] ?? 'Filtered out by eligibility rules.';
                    $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;

                    if (count($filteredOutPreview) < $previewLimit) {
                        $filteredOutPreview[$rowIndex] = $entry;
                    }
                }
            }

            $totalFiltered += $filteredCount;
            $totalSkipped += $skippedCount;
            $vipTotal += $chunkVip;

            $chunkMeta[] = [
                'index' => $meta['chunk_index'] ?? $this->extractChunkIndex($path),
                'path' => $path,
                'start_row' => $meta['start_row'] ?? null,
                'end_row' => $meta['end_row'] ?? null,
                'filtered_count' => $filteredCount,
                'skipped_count' => $skippedCount,
                'vip_count' => $chunkVip,
            ];
        }

        $sourceTotal = (int) ($state['total_rows'] ?? ($totalFiltered + $totalSkipped));

        arsort($reasonCounts);

        return [
            'token' => $this->token,
            'stored_path' => $this->storedPath,
            'original_filename' => $this->originalName,
            'headers' => $this->headers,
            'source_total_rows' => $sourceTotal,
            'filtered_rows_total' => $totalFiltered,
            'filtered_out_total' => $totalSkipped,
            'vip_rows_total' => $vipTotal,
            'chunks' => $chunkMeta,
            'filtered_out_reason_counts' => $reasonCounts,
            'filtered_out_preview' => $filteredOutPreview,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];
    }

    private function extractChunkIndex(string $path): int
    {
        $filename = basename($path);

        if (preg_match('/chunk_(\d+)/', $filename, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function rowIsVip(array $columns, array $headerMap): bool
    {
        $creditClass = $this->getColumnValue($columns, $headerMap, 'CREDIT_CLASS_NAME');

        if ($creditClass === '') {
            $creditClass = $this->getColumnValue($columns, $headerMap, 'CUSTOMER_SEGMENT');
        }

        if ($creditClass === '') {
            return false;
        }

        return $this->isVipCreditClass($creditClass);
    }

    private function updateProgressState(array $overrides): void
    {
        $state = Cache::get($this->cacheKey(), []);
        $payload = array_merge($state, $overrides, [
            'last_updated_at' => now()->toIso8601String(),
        ]);
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
