<?php

namespace App\Jobs;

use App\Models\CallCenterReport;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DistributeCallCenterReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $reportId;
    public array $userIds;
    public ?int $perUserCount;
    public ?string $cancelToken = null;

    public function __construct(int $reportId, array $userIds = [], ?int $perUserCount = null, ?string $cancelToken = null)
    {
        $this->reportId = $reportId;
        $this->userIds = array_values(array_filter($userIds, fn ($id) => is_numeric($id) && $id > 0));
        $this->perUserCount = $perUserCount !== null ? (int) $perUserCount : null;
        $this->cancelToken = $cancelToken;
    }

    public function handle(): void
    {
        $report = CallCenterReport::find($this->reportId);
        if (! $report) {
            return;
        }

        // If a cancel token was provided, only proceed if the pending cache key exists.
        if ($this->cancelToken !== null) {
            $cacheKey = 'cc:pending:distribute:'.$this->cancelToken;
            if (! Cache::has($cacheKey)) {
                return; // cancelled or expired
            }
            Cache::forget($cacheKey);
        }

        $rowIds = $report->row_ids ?? [];
        if (empty($rowIds)) {
            return;
        }

        $total = count($rowIds);
        $users = $this->userIds;
        if (empty($users)) {
            return;
        }

        // If perUserCount not provided, distribute as evenly as possible
        if (! $this->perUserCount) {
            $perUser = (int) floor($total / count($users));
        } else {
            $perUser = $this->perUserCount;
        }

        $now = Carbon::now()->toDateTimeString();

        // Bulk insert in batches
        $batch = [];
        $batchSize = 1000;
        $pos = 0;

        foreach ($users as $uid) {
            $take = $perUser;
            if ($uid === end($users)) {
                $take = $total - $pos;
            }

            for ($i = 0; $i < $take && $pos < $total; $i++, $pos++) {
                $batch[] = [
                    'call_center_report_id' => $report->id,
                    'master_dataset_row_id' => $rowIds[$pos],
                    'assigned_user_id' => $uid,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($batch) >= $batchSize) {
                    DB::table('call_center_row_assignments')->insert($batch);
                    $batch = [];
                }
            }
        }

        if (! empty($batch)) {
            DB::table('call_center_row_assignments')->insert($batch);
        }
    }
}
