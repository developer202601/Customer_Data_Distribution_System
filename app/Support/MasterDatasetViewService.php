<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MasterDatasetViewService
{
    private const REGION_LABEL = 'regional billing center';
    private const CALL_CENTER_STAFF_LABEL = 'call center staff';
    private const CALL_CENTER_LABEL = 'call center';
    private const STAFF_LABEL = 'staff';
    private const RETAIL_CODES = ['11', '35'];
    private const ENTERPRISE_CODES = ['41', '44', '47', '76'];
    private const SME_CODES = ['31'];
    private const VIP_PREFIX = 'VIP%';
    private const ENTERPRISE_LABEL = 'enterprise + wholesale';
    private const SME_LABEL = 'sme';
    private const VIP_LABEL = 'VIP';

    private const GROUP_A_QUOTAS = [
        'call-center-staff' => [
            'label' => 'Call Center Staff',
            'target' => 30000,
            'assignment_label' => self::CALL_CENTER_STAFF_LABEL,
        ],
        'call-center' => [
            'label' => 'Call Center',
            'target' => 5000,
            'assignment_label' => self::CALL_CENTER_LABEL,
        ],
        'staff' => [
            'label' => 'Staff',
            'target' => 3000,
            'assignment_label' => self::STAFF_LABEL,
        ],
    ];

    public function datasetSummary(MasterDatasetProcess $process): array
    {
        return [
            'token' => $process->token,
            'dataset_month' => $process->dataset_month,
            'row_count' => (int) $process->row_count,
            'excluded_count' => (int) $process->excluded_count,
            'call_center_staff_count' => (int) $process->call_center_staff_count,
            'call_center_count' => (int) $process->call_center_count,
            'staff_count' => (int) $process->staff_count,
            'region_billing_count' => (int) $process->region_billing_count,
        ];
    }

    public function groupASummary(MasterDatasetProcess $process): array
    {
        $groupAInput = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereIn('slt_business_line_value', self::RETAIL_CODES)
            ->count();

        $quotas = [];
        foreach (self::GROUP_A_QUOTAS as $key => $meta) {
            $count = MasterDatasetRow::query()
                ->where('process_id', $process->id)
                ->where('excluded', false)
                ->where('assigned_to', $meta['assignment_label'])
                ->count();

            $quotas[$key] = [
                'key' => $key,
                'label' => $meta['label'],
                'target' => $meta['target'],
                'actual' => $count,
            ];
        }

        $regionCount = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->where('assigned_to', self::REGION_LABEL)
            ->count();

        return [
            'input' => $groupAInput,
            'quotas' => $quotas,
            'region' => [
                'label' => 'Region Billing Centre',
                'actual' => $regionCount,
            ],
        ];
    }

    public function groupAQuery(MasterDatasetProcess $process): Builder
    {
        return MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereIn('assigned_to', [
                self::CALL_CENTER_STAFF_LABEL,
                self::CALL_CENTER_LABEL,
                self::STAFF_LABEL,
                self::REGION_LABEL,
            ]);
    }

    public function groupBSummary(MasterDatasetProcess $process): array
    {
        $enterpriseCount = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->where('assigned_to', self::ENTERPRISE_LABEL)
            ->count();

        $smeCount = MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->where('assigned_to', self::SME_LABEL)
            ->count();

        return [
            'enterprise_wholesale' => [
                'label' => 'Enterprise & Wholesale (47, 44, 41 & 76)',
                'count' => $enterpriseCount,
            ],
            'sme' => [
                'label' => 'SME (31)',
                'count' => $smeCount,
            ],
        ];
    }

    public function groupBQuery(MasterDatasetProcess $process): Builder
    {
        return MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->whereIn('assigned_to', [
                self::ENTERPRISE_LABEL,
                self::SME_LABEL,
            ]);
    }

    public function overviewQuery(MasterDatasetProcess $process): Builder
    {
        return MasterDatasetRow::query()
            ->where('process_id', $process->id);
    }

    public function exclusionSummary(MasterDatasetProcess $process): array
    {
        $archives = $process->exclusion_archives ?? [];
        if (! is_array($archives)) {
            $archives = [];
        }

        $latest = end($archives) ?: null;
        if ($latest) {
            $latest = array_merge($latest, [
                'uploaded_at' => $latest['uploaded_at'] ?? null,
            ]);
        }

        return [
            'total_excluded' => (int) MasterDatasetRow::query()
                ->where('process_id', $process->id)
                ->where('excluded', true)
                ->count(),
            'archives' => array_values($archives),
            'latest_archive' => $latest,
        ];
    }

    public function vipSummary(MasterDatasetProcess $process): array
    {
        $query = $this->vipQuery($process);

        $total = (clone $query)->count();

        $recent = (clone $query)
            ->orderByDesc('new_arrears_value')
            ->limit(5)
            ->get(['customer_ref', 'account_num', 'credit_class_name', 'new_arrears_value', 'assigned_to'])
            ->map(function (MasterDatasetRow $row) {
                return [
                    'customer_ref' => $row->customer_ref,
                    'account_num' => $row->account_num,
                    'credit_class_name' => $row->credit_class_name,
                    'new_arrears_value' => $row->new_arrears_value,
                    'assigned_to' => $row->assigned_to,
                ];
            })
            ->all();

        return [
            'count' => $total,
            'recent' => $recent,
        ];
    }

    public function vipQuery(MasterDatasetProcess $process): Builder
    {
        return MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->where('assigned_to', self::VIP_LABEL)
            ->whereNotNull('credit_class_name')
            ->where('credit_class_name', 'like', self::VIP_PREFIX);
    }

    public function regionSummary(MasterDatasetProcess $process): array
    {
        $query = $this->regionQuery($process);

        $count = (clone $query)->count();

        return [
            'count' => $count,
        ];
    }

    public function assignmentLabelMap(): array
    {
        return [
            self::CALL_CENTER_STAFF_LABEL => 'Call Center Staff',
            self::CALL_CENTER_LABEL => 'Call Center',
            self::STAFF_LABEL => 'Staff',
            self::REGION_LABEL => 'Region Billing Centre',
            self::ENTERPRISE_LABEL => 'Enterprise & Wholesale',
            self::SME_LABEL => 'SME',
            self::VIP_LABEL => 'VIP',
        ];
    }

    public function regionQuery(MasterDatasetProcess $process): Builder
    {
        return MasterDatasetRow::query()
            ->where('process_id', $process->id)
            ->where('excluded', false)
            ->where('assigned_to', self::REGION_LABEL);
    }

    public function bucketLabel(string $bucket): string
    {
        return $this->bucketConfig($bucket)['label'];
    }

    public function bucketFilename(string $bucket): string
    {
        return $this->bucketConfig($bucket)['filename'];
    }

    public function bucketQuery(MasterDatasetProcess $process, string $bucket): Builder
    {
        $config = $this->bucketConfig($bucket);
        $builder = MasterDatasetRow::query()->where('process_id', $process->id);

        foreach ($config['scopes'] as $scope) {
            $builder = $scope($builder);
        }

        return $builder->orderBy('id');
    }

    private function bucketConfig(string $bucket): array
    {
        $map = [
            'call-center-staff' => [
                'label' => 'Call Center Staff',
                'filename' => 'call_center_staff.xlsx',
                'scopes' => [
                    fn(Builder $query) => $query->where('excluded', false)->where('assigned_to', self::CALL_CENTER_STAFF_LABEL),
                ],
            ],
            'call-center' => [
                'label' => 'Call Center',
                'filename' => 'call_center.xlsx',
                'scopes' => [
                    fn(Builder $query) => $query->where('excluded', false)->where('assigned_to', self::CALL_CENTER_LABEL),
                ],
            ],
            'staff' => [
                'label' => 'Staff',
                'filename' => 'staff.xlsx',
                'scopes' => [
                    fn(Builder $query) => $query->where('excluded', false)->where('assigned_to', self::STAFF_LABEL),
                ],
            ],
            'region-billing' => [
                'label' => 'Region Billing Centre',
                'filename' => 'region_billing_centre.xlsx',
                'scopes' => [
                    fn(Builder $query) => $query->where('excluded', false)->where('assigned_to', self::REGION_LABEL),
                ],
            ],
            'enterprise-wholesale' => [
                'label' => 'Enterprise & Wholesale',
                'filename' => 'enterprise_wholesale.xlsx',
                'scopes' => [
                    fn(Builder $query) => $query->where('excluded', false)->where('assigned_to', self::ENTERPRISE_LABEL),
                ],
            ],
            'sme' => [
                'label' => 'SME',
                'filename' => 'sme.xlsx',
                'scopes' => [
                    fn(Builder $query) => $query->where('excluded', false)->where('assigned_to', self::SME_LABEL),
                ],
            ],
            'excluded' => [
                'label' => 'Excluded Records',
                'filename' => 'excluded.xlsx',
                'scopes' => [
                    fn(Builder $query) => $query->where('excluded', true),
                ],
            ],
            'vip' => [
                'label' => 'VIP Records',
                'filename' => 'vip_records.xlsx',
                'scopes' => [
                    fn(Builder $query) => $query
                        ->where('excluded', false)
                        ->where('assigned_to', self::VIP_LABEL)
                        ->whereNotNull('credit_class_name')
                        ->where('credit_class_name', 'like', self::VIP_PREFIX),
                ],
            ],
        ];

        return $map[$bucket] ?? $map['region-billing'];
    }
}
