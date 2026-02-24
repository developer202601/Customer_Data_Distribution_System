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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile as IlluminateUploadedFile;
use Throwable;

class ProcessExclusionUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
            throw $exception;
        } finally {
            $this->cleanup();
        }
    }

    private function cleanup(): void
    {
        foreach ($this->files as $file) {
            Storage::disk('local')->delete($file['path']);
        }
    }
}
