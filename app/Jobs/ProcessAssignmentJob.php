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
use Throwable;

class ProcessAssignmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $processId,
        private array $configOverrides,
        private array $userContext = [],
    ) {
    }

    public function handle(
        MasterDatasetWorkflowService $workflowService,
        MasterDatasetExportCoordinator $exportCoordinator
    ): void {
        $process = MasterDatasetProcess::find($this->processId);

        if (! $process) {
            Log::warning("Process {$this->processId} no longer exists when running assignment job.");
            return;
        }

        Log::info('ProcessAssignmentJob started.', [
            'process_id' => $this->processId,
            'overrides' => $this->configOverrides,
            'queue' => $this->queue,
        ]);

        try {
            // Apply assignment
            $workflowService->finalizeAssignment($process, $this->configOverrides);

            Log::info('ProcessAssignmentJob assignments applied.', [
                'process_id' => $this->processId,
            ]);

            // After assignments
            sleep(2);
            
            $process = $process->fresh();
            $process = MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::EXPORTS_PENDING);
            
            sleep(1);
            
            $exportCoordinator->ensureFresh($process, $this->userContext);

            Log::info('ProcessAssignmentJob exports triggered.', [
                'process_id' => $this->processId,
            ]);

        } catch (Throwable $exception) {
            Log::error('Assignment job failed: ' . $exception->getMessage(), [
                'process_id' => $this->processId,
                'exception' => $exception,
            ]);

            MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::FAILED);
            $process->update([
                'failure_reason' => $this->limitFailureReason($exception->getMessage()),
            ]);
            
            throw $exception;
        }
    }

    private function limitFailureReason(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return 'Assignment job failed.';
        }

        return substr($message, 0, 255);
    }
}
