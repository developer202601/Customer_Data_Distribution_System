<?php

namespace App\Support;

use App\Models\PaymentUpload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PaymentIngestionService
{
    public function run(string $token, string $storedPath, string $originalName, int $userId): void
    {
        $pythonBinary = $this->pythonBinary();
        $workbookPath = Storage::path($storedPath);

        if (! is_file($workbookPath) || ! is_readable($workbookPath)) {
            throw new RuntimeException('Uploaded workbook is not accessible.');
        }

        $this->updateProgress($token, [
            'status' => 'processing',
            'message' => 'Reading workbook with Python…',
            'progress' => 3,
            'user_id' => $userId,
            'original_name' => $originalName,
        ]);

        PaymentUpload::where('token', $token)->update([
            'status' => 'processing',
            'message' => 'Reading workbook with Python…',
            'progress' => 3,
        ]);

        $command = [
            $pythonBinary,
            base_path('scripts/read_payment_excel.py'),
            $workbookPath,
        ];

        $env = $this->buildPythonEnv();
        $process = new Process($command, base_path(), $env);
        $process->setTimeout(null);
        $process->run();

        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();
        $exitCode = $process->getExitCode() ?? 1;

        if ($exitCode !== 0 || ! $output) {
            $message = 'Python script failed.';
            if ($errorOutput) {
                $message = trim($errorOutput);
            } elseif ($output) {
                $message = trim($output);
            }

            $this->updateProgress($token, [
                'status' => 'failed',
                'progress' => 100,
                'message' => 'Payment processing failed.',
                'error' => $message,
                'finished_at' => time(),
            ]);

            PaymentUpload::where('token', $token)->update([
                'status' => 'failed',
                'progress' => 100,
                'message' => 'Payment processing failed.',
                'error' => $message,
                'finished_at' => time(),
            ]);

            if (Storage::exists($storedPath)) {
                Storage::delete($storedPath);
            }

            throw new RuntimeException($message);
        }

        $result = json_decode(trim($output), true);
        if (! is_array($result) || ($result['status'] ?? '') !== 'ok') {
            $message = is_array($result) ? ($result['message'] ?? 'Invalid response from Python script') : 'Invalid response from Python script';
            $this->updateProgress($token, [
                'status' => 'failed',
                'progress' => 100,
                'message' => 'Payment processing failed.',
                'error' => $message,
                'finished_at' => time(),
            ]);

            PaymentUpload::where('token', $token)->update([
                'status' => 'failed',
                'progress' => 100,
                'message' => 'Payment processing failed.',
                'error' => $message,
                'finished_at' => time(),
            ]);

            if (Storage::exists($storedPath)) {
                Storage::delete($storedPath);
            }

            throw new RuntimeException($message);
        }

        $accountNumIndex = $result['account_num_index'] ?? null;
        $paymentValueIndex = $result['payment_mny_index'] ?? null;
        $datasetMonthIndex = $result['dataset_month_index'] ?? null;
        $dataRows = $result['data'] ?? [];
        $totalRows = (int) ($result['total_rows'] ?? 0);

        $datasetMonth = null;
        if ($datasetMonthIndex !== null && !empty($dataRows)) {
            $firstMonthValue = trim((string) ($dataRows[0][$datasetMonthIndex] ?? ''));
            if ($firstMonthValue !== '') {
                $datasetMonth = $firstMonthValue;
            }
        }

        $processId = null;
        if ($datasetMonth !== null) {
            $process = \App\Models\MasterDatasetProcess::query()
                ->where('dataset_month', $datasetMonth)
                ->latest('id')
                ->first();

            if ($process) {
                $processId = $process->id;
            }
        }

        $this->updateProgress($token, [
            'total_rows' => $totalRows,
            'message' => $processId ? 'Processing payment rows…' : 'Processing payment rows (no dataset month match)…',
            'progress' => 10,
        ]);

        PaymentUpload::where('token', $token)->update([
            'total_rows' => $totalRows,
            'message' => $processId ? 'Processing payment rows…' : 'Processing payment rows (no dataset month match)…',
            'progress' => 10,
        ]);

        $matched = 0;
        $updated = 0;
        $notFound = 0;
        $processed = 0;
        $chunkSize = 1000;

        foreach (array_chunk($dataRows, $chunkSize) as $chunk) {
            [$m, $u, $n] = $this->processChunk($chunk, $accountNumIndex, $paymentValueIndex, $processId);
            $matched += $m;
            $updated += $u;
            $notFound += $n;
            $processed += count($chunk);

            $progress = $totalRows > 0 ? (int) round(10 + ($processed / $totalRows) * 85) : 95;
            $this->updateProgress($token, [
                'processed_rows' => $processed,
                'progress' => min(95, $progress),
                'message' => "Processed {$processed} of {$totalRows} rows…",
            ]);

            PaymentUpload::where('token', $token)->update([
                'processed_rows' => $processed,
                'progress' => min(95, $progress),
                'message' => "Processed {$processed} of {$totalRows} rows…",
            ]);
        }

        $this->updateProgress($token, [
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

        PaymentUpload::where('token', $token)->update([
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

        if (Storage::exists($storedPath)) {
            Storage::delete($storedPath);
        }
    }

    private function processChunk(array $chunk, ?int $accountNumIndex, ?int $paymentValueIndex, ?int $processId = null): array
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

        $query = \App\Models\MasterDatasetRow::whereIn('account_num', $accountNumbers);
        if ($processId !== null) {
            $query->where('process_id', $processId);
        }

        $masterRows = $query->get()->keyBy('account_num');

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

    private function updateProgress(string $token, array $overrides): void
    {
        $state = Cache::get('process:payment:upload:' . $token, []);
        $payload = array_merge($state, $overrides, [
            'last_updated_at' => now()->toIso8601String(),
        ]);
        Cache::put('process:payment:upload:' . $token, $payload, now()->addMinutes(60));
    }

    private function buildPythonEnv(): array
    {
        $connectionName = (string) (config('database.default') ?: 'mysql');
        $connection = (array) config('database.connections.' . $connectionName, []);

        $host = (string) ($connection['host'] ?? env('DB_HOST', ''));
        $port = (string) ($connection['port'] ?? env('DB_PORT', '3306'));
        $database = (string) ($connection['database'] ?? env('DB_DATABASE', ''));
        $username = (string) ($connection['username'] ?? env('DB_USERNAME', ''));
        $password = (string) ($connection['password'] ?? env('DB_PASSWORD', ''));

        $baseEnv = array_merge($_SERVER ?? [], $_ENV ?? []);

        return array_merge($baseEnv, [
            'CDDS_DB_CONNECTION' => $connectionName,
            'CDDS_DB_HOST' => $host,
            'CDDS_DB_PORT' => $port,
            'CDDS_DB_DATABASE' => $database,
            'CDDS_DB_USERNAME' => $username,
            'CDDS_DB_PASSWORD' => $password,
        ]);
    }

    private function pythonBinary(): string
    {
        $configured = config('services.master_ingest.python_binary') ?: env('PYTHON_BINARY');
        $binary = $configured ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');

        if (strpbrk($binary, '\\/') !== false) {
            if (! is_file($binary)) {
                throw new RuntimeException("Python executable not found at path: '{$binary}'. Fix PYTHON_BINARY or install Python.");
            }

            return $binary;
        }

        $finder = new ExecutableFinder;
        $resolved = $finder->find($binary);
        if (! $resolved) {
            throw new RuntimeException("Python executable not found: '{$binary}'. Install Python and ensure it is on PATH, or set PYTHON_BINARY (e.g. 'python3').");
        }

        return $resolved;
    }
}
