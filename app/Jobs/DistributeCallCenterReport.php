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

        // find the immediately previous report (by id) and mark all its assignments completed
        try {
            $prev = CallCenterReport::where('id', '<', $report->id)->orderBy('id', 'desc')->first();
            if ($prev) {
                DB::table('call_center_row_assignments')
                    ->where('call_center_report_id', $prev->id)
                    ->where('status', '<>', 'completed')
                    ->update([
                        'status' => 'completed',
                        'locked_at' => null,
                        'locked_by' => null,
                        'updated_at' => Carbon::now()->toDateTimeString(),
                    ]);
            }
        } catch (\Exception $e) {
            // ignore failures to avoid blocking distribution
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

        // Ensure we only attempt to assign master rows that actually exist
        try {
            $existing = DB::table('master_dataset_rows')->whereIn('id', $rowIds)->pluck('id')->toArray();
            if (empty($existing)) {
                return;
            }
            // preserve original order of $rowIds but filter out missing ids
            $rowIds = array_values(array_filter($rowIds, fn($id) => in_array($id, $existing, true)));
        } catch (\Exception $e) {
            // If the lookup fails for any reason, bail out to avoid FK errors
            return;
        }

        // Mark any previous assignments for these master rows as completed so they
        // no longer appear as active when we assign the new report's rows.
        try {
            DB::table('call_center_row_assignments')
                ->whereIn('master_dataset_row_id', $rowIds)
                ->where('call_center_report_id', '<>', $report->id)
                ->where('status', '<>', 'completed')
                ->update([
                    'status' => 'completed',
                    'locked_at' => null,
                    'locked_by' => null,
                    'updated_at' => $now ?? Carbon::now()->toDateTimeString(),
                ]);
        } catch (\Exception $e) {
            // ignore failures here to avoid blocking distribution; log if needed
        }

        $total = count($rowIds);
        $users = $this->userIds;
        if (empty($users)) {
            return;
        }

        // Mark selected users as fixed so distribution consumers know these
        // users should be treated as fixed recipients. Do not fail distribution
        // if this update errors.
        try {
            \App\Models\User::whereIn('id', $users)->update(['fixed' => 1]);
        } catch (\Exception $e) {
            // ignore errors to avoid blocking distribution
        }

        // If perUserCount not provided, distribute as evenly as possible
        $userCount = count($users);
        if (! $this->perUserCount) {
            $basePerUser = $userCount ? (int) floor($total / $userCount) : 0;
        } else {
            $basePerUser = $this->perUserCount;
        }
        $remainder = $userCount ? $total % $userCount : 0;

        $now = Carbon::now()->toDateTimeString();

        // Bulk insert in batches
        $batch = [];
        $batchSize = 1000;
        $pos = 0;

        foreach ($users as $index => $uid) {
            $take = $basePerUser + ($index < $remainder ? 1 : 0);

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
