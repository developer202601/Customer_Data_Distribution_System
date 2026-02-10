<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PythonIngestionService
{
    public function writeManifest(MasterDatasetProcess $process, array $payload): string
    {
        $relative = $this->manifestRelativePath($process);
        $this->disk($process)->put($relative, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $process->python_manifest_path = $relative;
        $process->save();

        return $relative;
    }

    public function ensureStatusFile(MasterDatasetProcess $process): string
    {
        $relative = $this->statusRelativePath($process);
        if (! $this->disk($process)->exists($relative)) {
            $this->disk($process)->put($relative, json_encode([
                'status' => 'pending',
                'message' => 'Python ingestion not started.',
            ], JSON_PRETTY_PRINT));
        }

        $process->python_status_path = $relative;
        $process->save();

        return $relative;
    }

    public function run(MasterDatasetProcess $process): int
    {
        if (! $process->python_manifest_path || ! $process->python_status_path) {
            throw new RuntimeException('Python manifest/status paths are missing for this process.');
        }

        $command = [
            $this->pythonBinary(),
            base_path('scripts/ingest_master.py'),
            '--process=' . $process->id,
            '--manifest=' . $this->absolutePath($process, $process->python_manifest_path),
            '--status=' . $this->absolutePath($process, $process->python_status_path),
        ];

        $python = new Process($command, base_path());
        $python->setTimeout(null);
        $python->run();

        $exitCode = $python->getExitCode() ?? 1;
        $process->python_ran_at = now();
        $process->python_exit_code = $exitCode;
        $process->save();

        Log::channel('single')->info('Python ingestion finished', [
            'process_id' => $process->id,
            'exit_code' => $exitCode,
            'stdout' => $python->getOutput(),
            'stderr' => $python->getErrorOutput(),
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Python ingestion failed. Check logs for details.');
        }

        return $exitCode;
    }

    private function manifestRelativePath(MasterDatasetProcess $process): string
    {
        return 'exports/' . $process->token . '/source/python-manifest.json';
    }

    private function statusRelativePath(MasterDatasetProcess $process): string
    {
        return 'exports/' . $process->token . '/source/python-status.json';
    }

    private function absolutePath(MasterDatasetProcess $process, string $relative): string
    {
        return $this->disk($process)->path($relative);
    }

    private function disk(MasterDatasetProcess $process): Filesystem
    {
        $disk = $process->storage_disk ?: config('filesystems.default', 'local');

        return Storage::disk($disk);
    }

    private function pythonBinary(): string
    {
        $configured = config('services.master_ingest.python_binary') ?: env('PYTHON_BINARY');
        $binary = $configured ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');

        // Allow absolute paths (common on Windows) as well as PATH lookups.
        if (strpbrk($binary, '\\/') !== false) {
            if (! is_file($binary)) {
                throw new RuntimeException(
                    "Python executable not found at path: '{$binary}'. Fix PYTHON_BINARY or install Python."
                );
            }

            return $binary;
        }

        $finder = new ExecutableFinder;
        $resolved = $finder->find($binary);
        if (! $resolved) {
            throw new RuntimeException(
                "Python executable not found: '{$binary}'. Install Python and ensure it is on PATH, or set PYTHON_BINARY (e.g. 'python3')."
            );
        }

        return $binary;
    }
}
