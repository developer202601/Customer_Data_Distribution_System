<?php

namespace App\Jobs;

use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetExportCoordinator;
use App\Support\MasterDatasetProcessStatus;
use App\Support\MasterDatasetWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile as IlluminateUploadedFile;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProcessExclusionUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const FAILURE_CACHE_TTL_SECONDS = 7200;
    public int $timeout = 3600;
    public int $tries = 1;

    /**
     * @param array<int, array{name: string, path: string, mime: string|null}> $files
     */
    public function __construct(
        private int $processId,
        private array $files,
        private array $userContext,
    ) {
    }

    public function handle(MasterDatasetWorkflowService $workflowService, MasterDatasetExportCoordinator $exportCoordinator): void
    {
        // Increase limits for large workbooks processed in this queued job.
        ini_set('memory_limit', '1536M');
        ini_set('max_execution_time', 0);
        $process = MasterDatasetProcess::find($this->processId);

        if (! $process) {
            Log::warning("Process {$this->processId} no longer exists when running exclusions job.");
            $this->cleanup();

            return;
        }

        $uploadedFiles = [];

        foreach ($this->files as $file) {
            $fullPath = Storage::disk('local')->path($file['path']);

            if (! is_readable($fullPath)) {
                Log::warning("Stored exclusion file not accessible: {$fullPath}");
                continue;
            }

            $uploadedFiles[] = new IlluminateUploadedFile(
                $fullPath,
                $file['name'],
                $file['mime'],
                null,
                true // test mode: treat as local file, not HTTP upload
            );
        }

        Log::info('Prepared exclusion uploads for job', [
            'process_id' => $this->processId,
            'count' => count($uploadedFiles),
            'files' => array_map(static fn ($file) => $file->getClientOriginalName(), $uploadedFiles),
        ]);

        try {
            if (! empty($uploadedFiles)) {
                $workflowService->ingestAndApplyExclusions($process, $uploadedFiles, $this->userContext);
            } else {
                Log::warning('No exclusion files remained by the time the job ran.');
            }
        } catch (Throwable $exception) {
            Log::error('Exclusion job failed: ' . $exception->getMessage(), [
                'process_id' => $this->processId,
                'exception' => $exception,
            ]);

            if ($process) {
                $failurePayload = $this->buildFailurePayload($process, $exception);
                $this->cacheFailurePayloadForUser($process, $failurePayload);
                $this->purgeFailedProcess($process);
            }

            throw $exception;
        } finally {
            $this->cleanup();
        }
    }

    private function buildFailurePayload(MasterDatasetProcess $process, Throwable $exception): array
    {
        $masterErrors = [];
        $exclusionErrors = [];
        $generalErrors = [];

        if ($exception instanceof ValidationException) {
            $validationErrors = $exception->errors();
            $masterErrors = collect($validationErrors['upload'] ?? [])->filter()->values()->all();
            $exclusionErrors = collect($validationErrors['exclusions'] ?? [])->filter()->values()->all();

            $remaining = collect($validationErrors)
                ->except(['upload', 'exclusions'])
                ->flatten()
                ->filter()
                ->values()
                ->all();

            $generalErrors = $remaining;
        }

        if (empty($masterErrors) && empty($exclusionErrors) && empty($generalErrors)) {
            $generalErrors = [trim((string) $exception->getMessage()) ?: 'Processing failed.'];
        }

        return [
            'master_file' => basename((string) ($process->master_archive_path ?: 'master archive')),
            'master_errors' => array_slice($masterErrors, 0, 20),
            'exclusion_errors' => array_slice($exclusionErrors, 0, 20),
            'general_errors' => array_slice($generalErrors, 0, 20),
            'created_at' => now()->toIso8601String(),
        ];
    }

    private function cacheFailurePayloadForUser(MasterDatasetProcess $process, array $payload): void
    {
        $userId = (int) ($process->user_id ?? ($this->userContext['id'] ?? 0));
        if ($userId <= 0) {
            return;
        }

        Cache::put(
            'master.dataset.failure.user.' . $userId,
            $payload,
            now()->addSeconds(self::FAILURE_CACHE_TTL_SECONDS)
        );
    }

    private function purgeFailedProcess(MasterDatasetProcess $process): void
    {
        $diskName = $process->storage_disk ?: config('filesystems.default', 'local');
        $disk = Storage::disk($diskName);
        $token = (string) ($process->token ?? '');

        if ($process->master_archive_path) {
            $disk->delete($process->master_archive_path);
        }

        if ($process->master_workbook_path) {
            $disk->delete($process->master_workbook_path);
        }

        if ($token !== '') {
            $disk->deleteDirectory('exports/' . $token);
        }

        $process->delete();
    }

    private function cleanup(): void
    {
        foreach ($this->files as $file) {
            Storage::disk('local')->delete($file['path']);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $process = MasterDatasetProcess::find($this->processId);
        if (! $process) {
            $this->cleanup();

            return;
        }

        $message = trim((string) ($exception?->getMessage() ?? 'Exclusion processing failed.'));
        $wrapped = ValidationException::withMessages([
            'exclusions' => [$message],
        ]);

        Log::error('Exclusion job failed in failed() handler', [
            'process_id' => $this->processId,
            'message' => $message,
            'exception' => $exception,
        ]);

        $failurePayload = $this->buildFailurePayload($process, $wrapped);
        $this->cacheFailurePayloadForUser($process, $failurePayload);

        MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::FAILED);
        $process->update([
            'failure_reason' => $message,
        ]);

        $this->cleanup();
    }
}
