<?php

namespace App\Console\Commands;

use App\Jobs\UpdateOmniPaymentsChunk;
use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OmniUpdateAll extends Command
{
    protected $signature = 'omni:update-all
                            {--dry-run : Report eligible/due counts without dispatching jobs}
                            {--force : Ignore the staleness gate and refresh every eligible row}';

    protected $description = 'Nightly OMNI bill-payment refresh for all eligible rows in the latest master dataset process. Dispatches chunked jobs to the exports queue.';

    public function handle(): int
    {
        $clientKey = Config::get('omni.client_key');
        if (empty($clientKey)) {
            $this->error('OMNI_API_CLIENT_KEY is not configured. Set it in .env and re-run.');

            return self::FAILURE;
        }

        $intervalHours = (int) Config::get('omni.refresh_interval_hours', 24);
        $chunkSize = max(1, (int) Config::get('omni.chunk_size', 50));
        $queueName = Config::get('queue.exports_queue', 'exports');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $latestProcessId = MasterDatasetProcess::query()->max('id');
        if (! $latestProcessId) {
            $this->warn('No master dataset processes found. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Latest process id: {$latestProcessId}");
        $this->info('OMNI refresh interval: '.($force ? 'unconditional (full refresh)' : "{$intervalHours}h").($dryRun ? ' [DRY RUN]' : ''));

        $baseQuery = MasterDatasetRow::query()
            ->where('process_id', $latestProcessId)
            ->where('excluded', false)
            ->where(function ($q) {
                $q->whereNotNull('account_num')->where('account_num', '<>', '')
                    ->orWhereNotNull('customer_ref')->where('customer_ref', '<>', '')
                    ->orWhereNotNull('product_label')->where('product_label', '<>', '');
            });

        if ($dryRun) {
            $eligible = (int) (clone $baseQuery)->count();

            $dueQuery = clone $baseQuery;
            if (! $force) {
                $dueQuery->where(function ($q) use ($intervalHours) {
                    $q->whereNull('last_api_fetched')
                        ->orWhere('last_api_fetched', '<', now()->subHours($intervalHours));
                });
            }

            $due = (int) (clone $dueQuery)->count();

            $this->info("Eligible rows: {$eligible}");
            $this->info($force ? 'Full refresh selected; all eligible rows will be refreshed.' : "Due rows (last_api_fetched null or older than {$intervalHours}h): {$due}");

            return self::SUCCESS;
        }

        $query = clone $baseQuery;
        if (! $force) {
            $query->where(function ($q) use ($intervalHours) {
                $q->whereNull('last_api_fetched')
                    ->orWhere('last_api_fetched', '<', now()->subHours($intervalHours));
            });
        }

        $total = (int) (clone $query)->count();
        if ($total === 0) {
            $this->info('No due rows to refresh. Exiting.');

            return self::SUCCESS;
        }

        $this->info("Refreshing {$total} rows in chunks of {$chunkSize} onto the '{$queueName}' queue...");

        $lock = null;
        if (Schema::hasTable('job_batches')) {
            $running = DB::table('job_batches')
                ->where('name', 'omni:update-all')
                ->whereNull('finished_at')
                ->exists();

            if ($running) {
                $this->warn('A previous omni:update-all batch is still running (unfinished job_batches row). Exiting without dispatching.');

                return self::SUCCESS;
            }
        } else {
            $lock = Cache::lock('omni:update-all', 7200);
            if (! $lock->get()) {
                $this->warn('Another omni:update-all run is in progress. Exiting without dispatching.');

                return self::SUCCESS;
            }
        }

        try {
            if (! Schema::hasTable('job_batches')) {
                $this->warn('The `job_batches` table is missing; dispatching chunk jobs individually (no batch progress tracking).');

                $dispatched = 0;
                $query->orderBy('id')->chunkById($chunkSize, function (Collection $rows) use (&$dispatched, $queueName) {
                    UpdateOmniPaymentsChunk::dispatch($rows->pluck('id')->all())->onQueue($queueName);
                    $dispatched++;
                    if ($dispatched % 100 === 0) {
                        $this->line("  dispatched {$dispatched} chunks...");
                    }
                });

                $this->info("Done. Dispatched {$dispatched} chunk jobs to the '{$queueName}' queue.");

                return self::SUCCESS;
            }

            $batch = Bus::batch([])
                ->allowFailures()
                ->onQueue($queueName)
                ->name('omni:update-all')
                ->dispatch();

            $dispatched = 0;
            $query->orderBy('id')->chunkById($chunkSize, function (Collection $rows) use ($batch, &$dispatched) {
                $batch->add([new UpdateOmniPaymentsChunk($rows->pluck('id')->all())]);
                $dispatched++;
                if ($dispatched % 100 === 0) {
                    $this->line("  queued {$dispatched} chunks (batch {$batch->id})...");
                }
            });

            $this->info("Done. Dispatched {$dispatched} chunk jobs (batch {$batch->id}) to the '{$queueName}' queue.");
            $this->info("Track progress: php artisan queue:work --queue={$queueName}; or cancel: php artisan queue:batches:cancel {$batch->id}");

            return self::SUCCESS;
        } finally {
            $lock?->release();
        }
    }
}
