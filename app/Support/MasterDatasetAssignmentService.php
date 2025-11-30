<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MasterDatasetAssignmentService
{
    private const RETAIL_CODES = ['11', '35'];
    private const REGION_LABEL = 'region billing centre';
    private const CALL_CENTER_STAFF_LABEL = 'call center staff';
    private const CALL_CENTER_LABEL = 'call center';
    private const STAFF_LABEL = 'staff';
    private const VIP_LIKE = 'VIP%';

    private const CALL_CENTER_STAFF_QUOTA = 30000;
    private const CALL_CENTER_QUOTA = 5000;
    private const STAFF_QUOTA = 3000;
    private const MIN_ARREARS_THRESHOLD = 3000;

    public function assign(MasterDatasetProcess $process): array
    {
        return DB::transaction(function () use ($process) {
            // Reset assignments
            MasterDatasetRow::query()
                ->where('process_id', $process->id)
                ->update(['assigned_to' => null]);

            MasterDatasetRow::query()
                ->where('process_id', $process->id)
                ->where('excluded', false)
                ->where(function (Builder $query) {
                    $query
                        ->whereNull('credit_class_name')
                        ->orWhere('credit_class_name', 'not like', self::VIP_LIKE);
                })
                ->update(['assigned_to' => self::REGION_LABEL]);

            $selected = $this->selectRetailAssignments($process);

            $callCenterStaffIds = $selected['call_center_staff'];
            $callCenterIds = $selected['call_center'];
            $staffIds = $selected['staff'];

            if (! empty($callCenterStaffIds)) {
                MasterDatasetRow::query()
                    ->whereIn('id', $callCenterStaffIds)
                    ->update(['assigned_to' => self::CALL_CENTER_STAFF_LABEL]);
            }

            if (! empty($callCenterIds)) {
                MasterDatasetRow::query()
                    ->whereIn('id', $callCenterIds)
                    ->update(['assigned_to' => self::CALL_CENTER_LABEL]);
            }

            if (! empty($staffIds)) {
                MasterDatasetRow::query()
                    ->whereIn('id', $staffIds)
                    ->update(['assigned_to' => self::STAFF_LABEL]);
            }

            $statistics = $this->collectStatistics($process);

            $process->fill($statistics);
            $process->save();

            return [
                'statistics' => $statistics,
            ];
        });
    }

    private function selectRetailAssignments(MasterDatasetProcess $process): array
    {
        $desiredTotal = self::CALL_CENTER_STAFF_QUOTA + self::CALL_CENTER_QUOTA + self::STAFF_QUOTA;

        $candidates = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereIn('slt_business_line_value', self::RETAIL_CODES)
                ->where(function (Builder $query) {
                    $query
                        ->whereNull('credit_class_name')
                        ->orWhere('credit_class_name', 'not like', self::VIP_LIKE);
                })
            ->where('latest_bill_mny', '<', 5000)
            ->whereNotNull('new_arrears_value')
            ->where('new_arrears_value', '>', self::MIN_ARREARS_THRESHOLD)
            ->where('new_arrears_value', '<', 10000)
            ->orderBy('new_arrears_value')
            ->limit($desiredTotal)
            ->get(['id', 'new_arrears_value']);

        if ($candidates->isEmpty()) {
            return [
                'call_center_staff' => [],
                'call_center' => [],
                'staff' => [],
            ];
        }

        $selected = $this->expandRangeIfNeeded($process, $candidates, $desiredTotal);

        $callCenterStaffIds = $selected->slice(0, self::CALL_CENTER_STAFF_QUOTA)->pluck('id')->all();
        $offset = count($callCenterStaffIds);

        $callCenterIds = $selected->slice($offset, self::CALL_CENTER_QUOTA)->pluck('id')->all();
        $offset += count($callCenterIds);

        $staffIds = $selected->slice($offset, self::STAFF_QUOTA)->pluck('id')->all();

        return [
            'call_center_staff' => $callCenterStaffIds,
            'call_center' => $callCenterIds,
            'staff' => $staffIds,
        ];
    }

    private function expandRangeIfNeeded(MasterDatasetProcess $process, Collection $initial, int $desiredTotal): Collection
    {
        if ($initial->count() >= $desiredTotal) {
            return $initial;
        }

        $extended = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereIn('slt_business_line_value', self::RETAIL_CODES)
            ->where(function (Builder $query) {
                $query
                    ->whereNull('credit_class_name')
                    ->orWhere('credit_class_name', 'not like', self::VIP_LIKE);
            })
            ->where('latest_bill_mny', '<', 5000)
            ->whereNotNull('new_arrears_value')
            ->where('new_arrears_value', '>', self::MIN_ARREARS_THRESHOLD)
            ->orderBy('new_arrears_value')
            ->limit($desiredTotal)
            ->get(['id', 'new_arrears_value']);

        return $extended;
    }

    private function collectStatistics(MasterDatasetProcess $process): array
    {
        $baseQuery = MasterDatasetRow::query()->where('process_id', $process->id);

        return [
            'row_count' => (clone $baseQuery)->count(),
            'excluded_count' => (clone $baseQuery)->where('excluded', true)->count(),
            'call_center_staff_count' => (clone $baseQuery)->where('assigned_to', self::CALL_CENTER_STAFF_LABEL)->count(),
            'call_center_count' => (clone $baseQuery)->where('assigned_to', self::CALL_CENTER_LABEL)->count(),
            'staff_count' => (clone $baseQuery)->where('assigned_to', self::STAFF_LABEL)->count(),
            'region_billing_count' => (clone $baseQuery)->where('assigned_to', self::REGION_LABEL)->count(),
        ];
    }
}
