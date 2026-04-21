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
                'status' => 'starting',
                'message' => 'Starting Python ingestion…',
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

        $pythonBinary = $this->pythonBinary();
        $this->assertPythonDependencies($pythonBinary);

        $command = [
            $pythonBinary,
            base_path('scripts/ingest_master.py'),
            '--process=' . $process->id,
            '--manifest=' . $this->absolutePath($process, $process->python_manifest_path),
            '--status=' . $this->absolutePath($process, $process->python_status_path),
            '--abort-flag=' . $this->abortFlagAbsolutePath($process),
        ];

        $env = $this->buildPythonEnv();
        $python = new Process($command, base_path(), $env);
        $python->setTimeout(null);
        $python->run();

        $exitCode = $python->getExitCode() ?? 1;
        $process->python_ran_at = now();
        $process->python_exit_code = $exitCode;
        $process->save();

        Log::channel('single')->info('Python ingestion finished', [
            'process_id' => $process->id,
            'python_binary' => $pythonBinary,
            'exit_code' => $exitCode,
            'stdout' => $python->getOutput(),
            'stderr' => $python->getErrorOutput(),
        ]);

        if ($exitCode === 130) {
            // User cancellation signaled by abort flag.
            return $exitCode;
        }

        if ($exitCode !== 0) {
            throw new RuntimeException($this->formatPythonFailureMessage($process, $pythonBinary, $python));
        }

        return $exitCode;
    }

    private function assertPythonDependencies(string $pythonBinary): void
    {
        $check = new Process([
            $pythonBinary,
            '-c',
            'import polars; import pymysql',
        ], base_path());
        $check->setTimeout(60);
        $check->run();

        if (($check->getExitCode() ?? 1) === 0) {
            return;
        }

        $stderr = trim((string) $check->getErrorOutput());
        $stdout = trim((string) $check->getOutput());
        $details = $stderr !== '' ? $stderr : $stdout;

        if (preg_match("/No module named '([^']+)'/", $details, $match)) {
            $missing = $match[1];
            $quotedBinary = '"' . $pythonBinary . '"';
            throw new RuntimeException(
                "Python dependency missing: {$missing}. Install into the interpreter used by CDDS: {$quotedBinary} -m pip install -r scripts/requirements-ingest.txt"
            );
        }

        throw new RuntimeException(
            'Python dependency check failed for ' . $pythonBinary . '. ' . ($details !== '' ? $details : 'Unknown error.')
        );
    }

    private function formatPythonFailureMessage(MasterDatasetProcess $process, string $pythonBinary, Process $python): string
    {
        $stderr = trim((string) $python->getErrorOutput());
        $stdout = trim((string) $python->getOutput());

        $summary = '';
        $details = $stderr !== '' ? $stderr : $stdout;
        if ($details !== '') {
            $lines = preg_split("/\r\n|\r|\n/", $details);
            $lines = array_values(array_filter(array_map('trim', $lines), fn($line) => $line !== ''));
            $summary = implode(' ', array_slice($lines, 0, 6));
        }

        if (preg_match("/No module named '([^']+)'/", $details, $match)) {
            $missing = $match[1];
            $quotedBinary = '"' . $pythonBinary . '"';
            return "Python ingestion failed: missing Python module '{$missing}'. Install: {$quotedBinary} -m pip install -r scripts/requirements-ingest.txt";
        }

        // If Python wrote a status JSON with a message, prefer it.
        try {
            $statusAbsolute = $this->absolutePath($process, (string) $process->python_status_path);
            if (is_file($statusAbsolute)) {
                $raw = (string) file_get_contents($statusAbsolute);
                $payload = json_decode($raw, true);
                if (is_array($payload) && is_string($payload['message'] ?? null) && trim((string) $payload['message']) !== '') {
                    $message = trim((string) $payload['message']);
                    return "Python ingestion failed ({$pythonBinary}): {$message}";
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        return 'Python ingestion failed (' . $pythonBinary . ').' . ($summary !== '' ? ' ' . $summary : '');
    }

    /**
     * Provide DB connection info to the Python subprocess.
     * Avoid relying on OS-level env propagation of Laravel's .env.
     */
    private function buildPythonEnv(): array
    {
        $connectionName = (string) (config('database.default') ?: 'mysql');
        $connection = (array) config('database.connections.' . $connectionName, []);

        $host = (string) ($connection['host'] ?? env('DB_HOST', ''));
        $port = (string) ($connection['port'] ?? env('DB_PORT', '3306'));
        $database = (string) ($connection['database'] ?? env('DB_DATABASE', ''));
        $username = (string) ($connection['username'] ?? env('DB_USERNAME', ''));
        $password = (string) ($connection['password'] ?? env('DB_PASSWORD', ''));

        // Merge current process env so PATH, VIRTUAL_ENV, etc remain available.
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

    public function pythonBinary(): string
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

        return $resolved;
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

    private function abortFlagAbsolutePath(MasterDatasetProcess $process): string
    {
        $token = (string) ($process->token ?? '');
        $relative = 'exports/' . $token . '/source/abort.flag';

        return $this->disk($process)->path($relative);
    }

    private function disk(MasterDatasetProcess $process): Filesystem
    {
        $disk = $process->storage_disk ?: config('filesystems.default', 'local');

        return Storage::disk($disk);
    }
}
