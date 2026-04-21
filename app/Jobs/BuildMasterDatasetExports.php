<?php

namespace App\Jobs;

use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetExportCoordinator;
use App\Support\MasterDatasetProcessStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuildMasterDatasetExports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;

    public function __construct(private readonly int $processId) {}

    public function handle(MasterDatasetExportCoordinator $coordinator): void
    {
        $process = MasterDatasetProcess::find($this->processId);

        if (! $process) {
            return;
        }

        if ($process->status === MasterDatasetProcessStatus::CANCELED) {
            return;
        }

        $coordinator->generateExports($process);
    }
}
