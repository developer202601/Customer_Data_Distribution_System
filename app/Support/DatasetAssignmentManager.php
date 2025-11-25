<?php

namespace App\Support;

use Illuminate\Support\Str;

class DatasetAssignmentManager
{
    use ProcessesExcelRows;

    private const GROUP_A_SEGMENTS = ['RETAIL', 'MICRO BUSINESS'];

    private const GROUP_A_QUOTAS = [
        'call_center_staff' => ['label' => 'Call Center Staff', 'target' => 30000],
        'call_center' => ['label' => 'Call Center', 'target' => 5000],
        'staff' => ['label' => 'Staff', 'target' => 3000],
    ];

    private const ENTERPRISE_PREFIX = 'ENTERPRISE';
    private const WHOLESALE_PREFIX = 'WHOLE';

    public function buildAssignments(array $headers, array $rows): array
    {
        $headerMap = $this->buildHeaderMap($headers);
        $groupA = [];
        $groupB = [];

        foreach ($rows as $rowIndex => $columns) {
            if (! $this->rowHasData($columns)) {
                continue;
            }

            $segment = $this->getColumnValue($columns, $headerMap, 'SLT_GL_SUB_SEGMENT');
            $segmentNormalised = strtoupper($segment);

            $latestBill = $this->parseNumeric($this->getColumnValue($columns, $headerMap, 'LATEST_BILL_MNY'));
            $totalOutstanding = $this->parseNumeric($this->getColumnValue($columns, $headerMap, 'NEW_ARREARS_20251122'));

            $record = [
                'row_index' => $rowIndex,
                'segment' => $segment,
                'segment_normalised' => $segmentNormalised,
                'latest_bill' => $latestBill,
                'total_outstanding' => $totalOutstanding,
            ];

            if (in_array($segmentNormalised, self::GROUP_A_SEGMENTS, true)) {
                $groupA[] = $record;
            } else {
                $groupB[] = $record;
            }
        }

        $groupAAssignments = $this->assignGroupA($groupA);
        $groupBAssignments = $this->assignGroupB($groupB);

        return [
            'headers' => $headers,
            'generated_at' => now()->toDateTimeString(),
            'group_a' => $groupAAssignments,
            'group_b' => $groupBAssignments,
        ];
    }

    private const GROUP_A_LATEST_BILL_MAX = 5000;
    private const GROUP_A_ARREARS_MIN = 3000;
    private const GROUP_A_ARREARS_MAX = 10000;

    private function assignGroupA(array $records): array
    {
        $midRangePool = array_values(array_filter($records, static function ($record) {
            return $record['latest_bill'] >= 0
                && $record['latest_bill'] < self::GROUP_A_LATEST_BILL_MAX
                && $record['total_outstanding'] > self::GROUP_A_ARREARS_MIN
                && $record['total_outstanding'] < self::GROUP_A_ARREARS_MAX;
        }));
        usort($midRangePool, static fn ($a, $b) => $a['total_outstanding'] <=> $b['total_outstanding']);

        $assignments = [];
        $used = [];

        $pool = $midRangePool;

        foreach (self::GROUP_A_QUOTAS as $key => $meta) {
            $target = $meta['target'];
            $take = min($target, count($pool));

            if ($take <= 0) {
                $assignments[$key] = [];
                continue;
            }

            // Pull each tranche from the middle of the current ascending pool so that lower and higher arrears remain for the other quotas.
            $start = (int) max(0, floor((count($pool) - $take) / 2));
            $selected = array_splice($pool, $start, $take);

            foreach ($selected as $item) {
                $used[$item['row_index']] = true;
            }

            $assignments[$key] = $selected;
        }

        $fallbackCandidates = array_values(array_filter($records, static function ($record) {
            return $record['latest_bill'] >= 0
                && $record['latest_bill'] < self::GROUP_A_LATEST_BILL_MAX
                && $record['total_outstanding'] >= self::GROUP_A_ARREARS_MAX;
        }));
        usort($fallbackCandidates, static fn ($a, $b) => $b['total_outstanding'] <=> $a['total_outstanding']);

        $fallbackIndex = 0;
        $fallbackCount = count($fallbackCandidates);

        foreach (self::GROUP_A_QUOTAS as $key => $meta) {
            $target = $meta['target'];
            $current = count($assignments[$key]);

            if ($current >= $target) {
                continue;
            }

            $needed = $target - $current;
            $topUps = [];

            while ($needed > 0 && $fallbackIndex < $fallbackCount) {
                $candidate = $fallbackCandidates[$fallbackIndex];
                $fallbackIndex++;

                if (isset($used[$candidate['row_index']])) {
                    continue;
                }

                $used[$candidate['row_index']] = true;
                $topUps[] = $candidate;
                $needed--;
            }

            if (! empty($topUps)) {
                $assignments[$key] = array_merge($assignments[$key], $topUps);
            }
        }

        $quotaSummaries = [];
        foreach (self::GROUP_A_QUOTAS as $key => $meta) {
            $quotaSummaries[$key] = [
                'label' => $meta['label'],
                'target' => $meta['target'],
                'rows' => array_map(static fn ($entry) => $entry['row_index'], $assignments[$key]),
                'actual' => count($assignments[$key]),
            ];
        }

        $regionRows = [];
        foreach ($records as $record) {
            if (! isset($used[$record['row_index']])) {
                $regionRows[] = $record['row_index'];
            }
        }

        return [
            'quotas' => $quotaSummaries,
            'region_billing' => [
                'label' => 'Region Billing Centre',
                'rows' => $regionRows,
                'actual' => count($regionRows),
            ],
            'totals' => [
                'input' => count($records),
                'assigned' => array_sum(array_map(static fn ($entry) => $entry['actual'], $quotaSummaries)),
            ],
        ];
    }

    private function assignGroupB(array $records): array
    {
        $enterpriseSegments = [];
        $wholesaleSegments = [];
        $enterpriseRows = [];
        $wholesaleRows = [];
        $otherSegments = [];
        $ignoredRows = [];

        foreach ($records as $record) {
            if ($record['latest_bill'] < 5000) {
                $ignoredRows[] = $record['row_index'];
                continue;
            }

            $segment = $record['segment'] !== '' ? $record['segment'] : 'Unknown Segment';
            $normalised = $record['segment_normalised'];

            if (Str::startsWith($normalised, self::ENTERPRISE_PREFIX)) {
                $enterpriseSegments[$segment][] = $record['row_index'];
                $enterpriseRows[] = $record['row_index'];
            } elseif (Str::startsWith($normalised, self::WHOLESALE_PREFIX)) {
                $wholesaleSegments[$segment][] = $record['row_index'];
                $wholesaleRows[] = $record['row_index'];
            } else {
                $otherSegments[$segment][] = $record['row_index'];
            }
        }

        ksort($enterpriseSegments, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($wholesaleSegments, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($otherSegments, SORT_NATURAL | SORT_FLAG_CASE);

        $enterpriseCount = count($enterpriseRows);
        $wholesaleCount = count($wholesaleRows);
        $otherCount = array_sum(array_map(static fn ($rows) => count($rows), $otherSegments));
        $ignoredCount = count($ignoredRows);

        $enterpriseCategory = $this->buildEnterpriseCategory('enterprise', 'Enterprise', $enterpriseSegments, $enterpriseRows);
        $wholesaleCategory = $this->buildEnterpriseCategory('wholesale', 'Wholesale', $wholesaleSegments, $wholesaleRows);

        $enterpriseWholesaleCategories = array_values(array_filter([
            $enterpriseCategory,
            $wholesaleCategory,
        ]));

        return [
            'enterprise_wholesale' => [
                'label' => 'Enterprise & Wholesale',
                'categories' => $enterpriseWholesaleCategories,
                'count' => $enterpriseCount + $wholesaleCount,
            ],
            'segments' => $this->mapSegmentCollection($otherSegments),
            'ignored' => [
                'label' => 'Ignored - LATEST_BILL_MNY < 5000',
                'rows' => $ignoredRows,
                'count' => $ignoredCount,
            ],
            'totals' => [
                'eligible' => $enterpriseCount + $wholesaleCount + $otherCount,
                'enterprise_wholesale' => $enterpriseCount + $wholesaleCount,
                'ignored' => $ignoredCount,
            ],
        ];
    }

    private function buildEnterpriseCategory(string $key, string $label, array $segments, array $rows): ?array
    {
        if (empty($rows)) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'rows' => array_values($rows),
            'count' => count($rows),
            'segments' => $this->mapSegmentCollection($segments),
        ];
    }

    private function mapSegmentCollection(array $segments): array
    {
        $results = [];

        foreach ($segments as $name => $rows) {
            $results[] = [
                'name' => $name,
                'slug' => Str::slug($name) ?: 'segment',
                'rows' => $rows,
                'count' => count($rows),
            ];
        }

        return $results;
    }
}
