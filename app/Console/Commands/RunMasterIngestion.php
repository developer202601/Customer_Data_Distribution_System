<?php

namespace App\Console\Commands;

use App\Models\MasterDatasetProcess;
use App\Support\PythonIngestionService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class RunMasterIngestion extends Command
{
    protected $signature = 'master:ingest {process : The master_dataset_process id}';

    protected $description = 'Invoke the Python ingestion pipeline for a stored master dataset process.';

    public function handle(PythonIngestionService $python): int
    {
        $processId = (int) $this->argument('process');
        $process = MasterDatasetProcess::find($processId);

        if (! $process) {
            $this->error('Process not found.');
            return self::FAILURE;
        }

        if (! $process->python_manifest_path) {
            $this->error('Process does not have a prepared manifest. Re-run the upload.');
            return self::FAILURE;
        }

        try {
            $python->run($process);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Python ingestion failed: ' . $exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Python ingestion completed successfully.');
        return self::SUCCESS;
    }
}
