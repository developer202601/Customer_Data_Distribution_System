<?php

namespace App\Jobs;

use App\Models\MasterDatasetRow;
use App\Models\PaymentUpload;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessPaymentUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CHUNK_SIZE = 1000;
    private const CACHE_KEY_PREFIX = 'process:payment:upload:';
    private const USER_UPLOADS_PREFIX = 'user:payment:uploads:';

    public function __construct(
        private readonly string $token,
        private readonly string $storedPath,
        private readonly string $originalName,
        private readonly int $userId
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 300);

        $this->updateProgress([
            'status' => 'processing',
            'message' => 'Opening Excel workbook…',
            'progress' => 1,
            'processed_rows' => 0,
            'total_rows' => null,
            'started_at' => time(),
        ]);

        PaymentUpload::updateOrCreate(
            ['token' => $this->token],
            [
                'user_id' => $this->userId,
                'original_name' => $this->originalName,
                'status' => 'processing',
                'progress' => 1,
                'message' => 'Opening Excel workbook…',
                'processed_rows' => 0,
                'total_rows' => null,
                'started_at' => time(),
                'finished_at' => null,
            ]
        );

        $workbookPath = Storage::path($this->storedPath);

        if (! is_file($workbookPath) || ! is_readable($workbookPath)) {
            throw new RuntimeException('Uploaded workbook is not accessible.');
        }

        try {
            $this->updateProgress([
                'message' => 'Reading workbook with Python…',
                'progress' => 3,
            ]);

            PaymentUpload::where('token', $this->token)->update([
                'message' => 'Reading workbook with Python…',
                'progress' => 3,
            ]);

            $pythonScript = base_path('scripts/read_payment_excel.py');
            if (! is_file($pythonScript) || ! is_executable($pythonScript)) {
                $pythonScript = base_path('scripts/read_payment_excel.py');
            }

            $command = ['python', $pythonScript, $workbookPath];
            $output = shell_exec(implode(' ', array_map('escapeshellarg', $command)));
            
            if ($output === null || trim($output) === '') {
                throw new RuntimeException('Python script returned empty output.');
            }

            $result = json_decode(trim($output), true);
            if (! is_array($result) || ($result['status'] ?? '') !== 'ok') {
                $message = is_array($result) ? ($result['message'] ?? 'Unknown error') : 'Invalid response from Python script';
                throw new RuntimeException("Failed to read Excel file: {$message}");
            }

            $headerRow = $result['headers'] ?? [];
            $accountNumIndex = $result['account_num_index'] ?? null;
            $paymentValueIndex = $result['payment_mny_index'] ?? null;
            $dataRows = $result['data'] ?? [];
            $totalRows = (int) ($result['total_rows'] ?? 0);

            $this->updateProgress([
                'total_rows' => $totalRows,
                'message' => 'Processing payment rows…',
                'progress' => 10,
            ]);

            PaymentUpload::where('token', $this->token)->update([
                'total_rows' => $totalRows,
                'message' => 'Processing payment rows…',
                'progress' => 10,
            ]);

            $matched = 0;
            $updated = 0;
            $notFound = 0;
            $processed = 0;

            foreach (array_chunk($dataRows, self::CHUNK_SIZE) as $chunkIndex => $chunk) {
                [$m, $u, $n] = $this->processChunk($chunk, $accountNumIndex, $paymentValueIndex);
                $matched += $m;
                $updated += $u;
                $notFound += $n;
                $processed += count($chunk);

                $progress = $totalRows > 0 ? (int) round(10 + ($processed / $totalRows) * 85) : 95;
                $this->updateProgress([
                    'processed_rows' => $processed,
                    'progress' => min(95, $progress),
                    'message' => sprintf('Processed %d of %d rows…', $processed, $totalRows),
                ]);

                PaymentUpload::where('token', $this->token)->update([
                    'processed_rows' => $processed,
                    'progress' => min(95, $progress),
                    'message' => sprintf('Processed %d of %d rows…', $processed, $totalRows),
                ]);
            }

            $this->updateProgress([
                'status' => 'complete',
                'progress' => 100,
                'message' => 'Payment upload completed.',
                'processed_rows' => $processed,
                'total_rows' => $totalRows,
                'matched' => $matched,
                'updated' => $updated,
                'not_found' => $notFound,
            ]);

            PaymentUpload::where('token', $this->token)->update([
                'status' => 'complete',
                'progress' => 100,
                'message' => 'Payment upload completed.',
                'processed_rows' => $processed,
                'total_rows' => $totalRows,
                'matched' => $matched,
                'updated' => $updated,
                'not_found' => $notFound,
                'finished_at' => time(),
            ]);

            Storage::delete($this->storedPath);
        } catch (Throwable $e) {
            $this->updateProgress([
                'status' => 'failed',
                'progress' => 100,
                'message' => 'Payment processing failed.',
                'error' => $e->getMessage(),
            ]);

            PaymentUpload::where('token', $this->token)->update([
                'status' => 'failed',
                'progress' => 100,
                'message' => 'Payment processing failed.',
                'error' => $e->getMessage(),
                'finished_at' => time(),
            ]);

            if (Storage::exists($this->storedPath)) {
                Storage::delete($this->storedPath);
            }

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->updateProgress([
            'status' => 'failed',
            'progress' => 100,
            'message' => 'Payment processing failed.',
            'error' => $exception->getMessage(),
        ]);

        PaymentUpload::where('token', $this->token)->update([
            'status' => 'failed',
            'progress' => 100,
            'message' => 'Payment processing failed.',
            'error' => $exception->getMessage(),
            'finished_at' => time(),
        ]);

        if (Storage::exists($this->storedPath)) {
            Storage::delete($this->storedPath);
        }
    }

    private function processChunk(array $chunk, ?int $accountNumIndex, ?int $paymentValueIndex): array
    {
        $matched = 0;
        $updated = 0;
        $notFound = 0;

        $accountNumbers = [];
        foreach ($chunk as $cells) {
            if ($accountNumIndex === null || !isset($cells[$accountNumIndex])) {
                continue;
            }
            $accountNum = trim((string) ($cells[$accountNumIndex] ?? ''));
            if ($accountNum !== '') {
                $accountNumbers[] = $accountNum;
            }
        }

        if (empty($accountNumbers)) {
            return [$matched, $updated, $notFound];
        }

        $masterRows = MasterDatasetRow::whereIn('account_num', $accountNumbers)->get()->keyBy('account_num');

        foreach ($chunk as $cells) {
            if ($accountNumIndex === null || !isset($cells[$accountNumIndex])) {
                continue;
            }

            $accountNum = trim((string) ($cells[$accountNumIndex] ?? ''));
            if ($accountNum === '') {
                continue;
            }

            $matched++;

            if (! isset($masterRows[$accountNum])) {
                $notFound++;
                continue;
            }

            $row = $masterRows[$accountNum];
            $updateData = [];

            if ($paymentValueIndex !== null && isset($cells[$paymentValueIndex])) {
                $value = trim((string) ($cells[$paymentValueIndex] ?? ''));
                if ($value !== '' && $value !== '-') {
                    $numeric = str_replace([',', ' '], '', $value);
                    if (is_numeric($numeric)) {
                        $updateData['payments_value'] = (float) $numeric;
                    }
                }
            }

            if (!empty($updateData)) {
                $row->update($updateData);
                $updated++;
            }
        }

        return [$matched, $updated, $notFound];
    }

    private function updateProgress(array $overrides): void
    {
        $state = Cache::get($this->cacheKey(), []);
        $payload = array_merge($state, $overrides, [
            'last_updated_at' => now()->toIso8601String(),
        ]);
        Cache::put($this->cacheKey(), $payload, now()->addMinutes(60));

        $this->updateUserUploadIndex($payload);
    }

    private function updateUserUploadIndex(array $payload): void
    {
        $key = self::USER_UPLOADS_PREFIX . $this->userId;
        $uploads = Cache::get($key, []);

        $uploads[$this->token] = [
            'token' => $this->token,
            'original_name' => $this->originalName,
            'status' => $payload['status'] ?? 'processing',
            'progress' => $payload['progress'] ?? 0,
            'message' => $payload['message'] ?? 'Processing…',
            'processed_rows' => $payload['processed_rows'] ?? 0,
            'total_rows' => $payload['total_rows'] ?? null,
            'matched' => $payload['matched'] ?? null,
            'updated' => $payload['updated'] ?? null,
            'not_found' => $payload['not_found'] ?? null,
            'started_at' => $payload['started_at'] ?? time(),
            'last_updated_at' => $payload['last_updated_at'] ?? now()->toIso8601String(),
        ];

        if (count($uploads) > 50) {
            $uploads = array_slice($uploads, -50, null, true);
        }

        Cache::put($key, $uploads, now()->addDays(7));
    }

    private function cacheKey(): string
    {
        return self::CACHE_KEY_PREFIX . $this->token;
    }
}
