<?php

namespace App\Jobs;

use App\Models\MasterDatasetRow;
use App\Services\OmniBillPaymentService;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateOmniPaymentsChunk implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 2;

    public function __construct(
        public array $rowIds,
    ) {
        $this->queue = config('queue.exports_queue', 'exports');
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        /** @var \App\Services\OmniBillPaymentService $omniService */
        $omniService = app(OmniBillPaymentService::class);

        $stats = [
            'total' => count($this->rowIds),
            'updated' => 0,
            'skipped_duplicate' => 0,
            'skipped_no_change' => 0,
            'skipped_fresh' => 0,
            'skipped_no_identifiers' => 0,
            'api_error' => 0,
            'skipped_no_response' => 0,
            'failed' => 0,
        ];

        // skipFreshness=true: the batch is the authoritative nightly refresh; the
        // duplicate + date-guard inside refreshRow still prevents double accumulation.
        MasterDatasetRow::query()
            ->whereIn('id', $this->rowIds)
            ->orderBy('id')
            ->chunkById((int) config('omni.chunk_size', 50), function ($rows) use ($omniService, &$stats) {
                foreach ($rows as $row) {
                    try {
                        $status = $omniService->refreshRow($row, true);
                        $stats[$status] = ($stats[$status] ?? 0) + 1;
                    } catch (Throwable $e) {
                        $stats['failed']++;
                        Log::warning('OMNI chunk row failed', [
                            'row_id' => $row->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $stats['updated_at'] = Carbon::now()->toDateTimeString();

        Log::info('omni chunk processed', $stats);
    }
}
