<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use App\Support\MasterDatasetAssignmentConfiguration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MasterDatasetAssignmentService
{
    private const RETAIL_SEGMENTS = ['retail', 'micro business', 'microbusiness'];
    private const REGION_LABEL = 'regional billing center';
    private const CALL_CENTER_STAFF_LABEL = 'call center staff';
    private const CALL_CENTER_LABEL = 'call center';
    private const STAFF_LABEL = 'staff';
    private const ENTERPRISE_LABEL = 'enterprise + wholesale';
    private const SME_LABEL = 'sme';
    private const VIP_LABEL = 'VIP';
    private const EXCLUDED_LABEL = 'Excluded';
    private const ARREARS_EXPANSION_STEP = 1000;

    private MasterDatasetAssignmentConfiguration $configuration;

    public function __construct(MasterDatasetAssignmentConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function assign(MasterDatasetProcess $process): array
    {
        return DB::transaction(function () use ($process) {
            $this->resetAssignableRows($process);
            $this->excludeRetailMicroCopper($process);
            $this->assignRetailHighBillToRegion($process);
            $this->assignRetailArrearsBands($process);
            $this->assignNonRetailHighBill($process);
            $this->assignRemainderToRegion($process);

            $statistics = $this->collectStatistics($process);

            $process->fill($statistics);
            $process->save();

            return [
                'statistics' => $statistics,
            ];
        });
    }

    private function excludeRetailMicroCopper(MasterDatasetProcess $process): void
    {
        $query = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereNull('assigned_to');

        $query = $this->applyRetailOrMicroFilter($query)
            ->whereRaw('LOWER(COALESCE(medium, "")) = ?', ['copper']);

        $query->update([
            'excluded' => true,
            'assigned_to' => self::EXCLUDED_LABEL,
            'exclusion_reason' => 'Medium is Copper (Retail/Micro)',
            'exclusion_priority' => 5,
        ]);
    }

    private function resetAssignableRows(MasterDatasetProcess $process): void
    {
        MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereNotIn('assigned_to', [self::VIP_LABEL, self::EXCLUDED_LABEL])
            ->update(['assigned_to' => null]);
    }

    private function assignRetailHighBillToRegion(MasterDatasetProcess $process): void
    {
        $query = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereNull('assigned_to')
            ->where('latest_bill_mny', '>=', 5000);

        $query = $this->applyRetailOrMicroFilter($query);

        $query->update(['assigned_to' => self::REGION_LABEL]);
    }

    private function assignRetailArrearsBands(MasterDatasetProcess $process): void
    {
        $desiredTotal = $this->configuration->getTotalRetailSelectionGoal();

        $retailMin = $this->configuration->getRetailLowerBound();
        $retailMax = $this->configuration->getRetailUpperBound();

        $candidates = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereNull('assigned_to')
            ->where('latest_bill_mny', '<', 5000)
            ->whereNotNull('new_arrears_value')
            ->where('new_arrears_value', '>', $retailMin)
            ->orderBy('new_arrears_value')
            ->get(['id', 'new_arrears_value', 'slt_gl_sub_segment']);

        $candidates = $this->filterRetailOrMicroCollection($candidates);

        if ($candidates->isEmpty()) {
            return;
        }

        $upperBound = $retailMax;
        $maxArrears = (float) $candidates->max('new_arrears_value');

        while ($upperBound < $maxArrears && $this->countBelowThreshold($candidates, $upperBound) < $desiredTotal) {
            $upperBound += self::ARREARS_EXPANSION_STEP;
        }

        $filtered = $candidates->filter(function ($row) use ($upperBound, $retailMin) {
            $value = (float) $row->new_arrears_value;
            return $value > $retailMin && $value < $upperBound;
        })->values();

        if ($filtered->isEmpty()) {
            return;
        }

        $sliceSize = min($desiredTotal, $filtered->count());
        $start = max(0, intdiv($filtered->count() - $sliceSize, 2));
        $selection = $filtered->slice($start, $sliceSize)->values();

        $callCenterStaffIds = $selection->slice(0, $this->configuration->getCallCenterStaffQuota())->pluck('id')->all();
        $offset = count($callCenterStaffIds);

        $callCenterIds = $selection->slice($offset, $this->configuration->getCallCenterQuota())->pluck('id')->all();
        $offset += count($callCenterIds);

        $staffIds = $selection->slice($offset, $this->configuration->getStaffQuota())->pluck('id')->all();

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
    }

    private function assignNonRetailHighBill(MasterDatasetProcess $process): void
    {
        $rows = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereNull('assigned_to')
            ->where('latest_bill_mny', '>=', 5000)
            ->get(['id', 'slt_gl_sub_segment']);

        if ($rows->isEmpty()) {
            return;
        }

        $enterpriseIds = [];
        $smeIds = [];
        $regionIds = [];

        foreach ($rows as $row) {
            $segment = strtolower((string) $row->slt_gl_sub_segment);

            if (str_contains($segment, 'enterprise') || str_contains($segment, 'wholesale')) {
                $enterpriseIds[] = $row->id;
            } elseif (str_contains($segment, 'sme')) {
                $smeIds[] = $row->id;
            } elseif (! $this->isRetailOrMicroSegment($segment)) {
                $regionIds[] = $row->id;
            }
        }

        if (! empty($enterpriseIds)) {
            MasterDatasetRow::query()
                ->whereIn('id', $enterpriseIds)
                ->update(['assigned_to' => self::ENTERPRISE_LABEL]);
        }

        if (! empty($smeIds)) {
            MasterDatasetRow::query()
                ->whereIn('id', $smeIds)
                ->update(['assigned_to' => self::SME_LABEL]);
        }

        if (! empty($regionIds)) {
            MasterDatasetRow::query()
                ->whereIn('id', $regionIds)
                ->update(['assigned_to' => self::REGION_LABEL]);
        }
    }

    private function assignRemainderToRegion(MasterDatasetProcess $process): void
    {
        MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereNull('assigned_to')
            ->update(['assigned_to' => self::REGION_LABEL]);
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
            'vip_count' => (clone $baseQuery)->where('assigned_to', self::VIP_LABEL)->count(),
        ];
    }

    private function applyRetailOrMicroFilter(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery) {
            $first = true;
            foreach (self::RETAIL_SEGMENTS as $segment) {
                if ($first) {
                    $subQuery->whereRaw('LOWER(COALESCE(slt_gl_sub_segment, "")) = ?', [$segment]);
                    $first = false;
                } else {
                    $subQuery->orWhereRaw('LOWER(COALESCE(slt_gl_sub_segment, "")) = ?', [$segment]);
                }
            }
        });
    }

    private function filterRetailOrMicroCollection(Collection $rows): Collection
    {
        return $rows->filter(function ($row) {
            $segment = strtolower((string) $row->slt_gl_sub_segment);
            return $this->isRetailOrMicroSegment($segment);
        })->values();
    }

    private function isRetailOrMicroSegment(string $segment): bool
    {
        return in_array($segment, self::RETAIL_SEGMENTS, true);
    }

    private function countBelowThreshold(Collection $rows, float $threshold): int
    {
        return $rows->where('new_arrears_value', '<', $threshold)->count();
    }
}
