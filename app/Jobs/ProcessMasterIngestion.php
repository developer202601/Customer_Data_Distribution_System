<?php

namespace App\Jobs;

use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetImporter;
use App\Support\MasterDatasetProcessStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessMasterIngestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private int $processId,
        private ?array $userContext = null
    ) {
        $this->queue = config('queue.exports_queue', 'exports');
    }

    /**
     * Execute the job.
     */
    public function handle(MasterDatasetImporter $importer): void
    {
        ini_set('memory_limit', '1536M');
        ini_set('max_execution_time', 0);

        $process = MasterDatasetProcess::find($this->processId);
        if (! $process) {
            Log::warning("Process {$this->processId} no longer exists when running master ingestion job.");
            return;
        }

        try {
            // Run Phase 1: validate and ingest workbook (skip assignments for now)
            $importer->processStoredArchive($process, $this->userContext, true);

            // Once Phase 1 is done, set the status to awaiting_exclusions
            MasterDatasetProcessStatus::set($process, MasterDatasetProcessStatus::AWAITING_EXCLUSIONS);
        } catch (Throwable $e) {
            Log::error('Master dataset ingestion failed: ' . $e->getMessage(), [
                'process_id' => $this->processId,
                'exception' => $e,
            ]);

            $process->update([
                'status' => 'failed',
                'failure_reason' => substr($e->getMessage(), 0, 255),
            ]);

            throw $e;
        }
    }
}
