<?php

namespace App\Jobs;

use App\Support\DatasetAssignmentManager;
use App\Support\DatasetExportManager;
use App\Support\ProcessedDataset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class GenerateDatasetExports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;

    private string $token;
    private string $manifestPath;
    private ?array $userContext;

    public function __construct(string $token, string $manifestPath, ?array $userContext = null)
    {
        $this->token = $token;
        $this->manifestPath = $manifestPath;
        $this->userContext = $userContext;
    }

    public function handle(DatasetAssignmentManager $assignmentManager, DatasetExportManager $exportManager): void
    {
        ini_set('memory_limit', '2048M');
        ini_set('max_execution_time', 600);

        $dataset = new ProcessedDataset($this->token, $this->manifestPath);
        $assignments = $assignmentManager->buildAssignmentsFromDataset($dataset);

        $headers = $assignments['headers'] ?? $dataset->headers();

        if (empty($headers)) {
            return;
        }

        $this->generateGroupAExports($dataset, $headers, $assignments, $exportManager);
        $this->generateGroupBExports($dataset, $headers, $assignments, $exportManager);
        $this->generateVipExport($dataset, $headers, $exportManager);
        $this->generateExclusionExport($dataset, $exportManager);
    }

    private function generateVipExport(ProcessedDataset $dataset, array $headers, DatasetExportManager $exportManager): void
    {
        $rows = [];
        foreach ($dataset->filteredRowsMatching(true) as $rowIndex => $columns) {
            $rows[] = $rowIndex;
        }

        if (empty($rows)) {
            return;
        }

        $label = 'VIP Records';
        $filename = 'vip-records.xlsx';

        $builder = $exportManager->singleSheetBuilder($dataset, $headers, $rows, $label);
        $meta = [
            'label' => $label,
            'row_count' => count($rows),
            'sheet_title' => $label,
            'headers' => $headers,
        ];

        $exportManager->store($dataset, 'vip', 'vip-records', $filename, $builder, $meta, $this->userContext);
    }

    private function generateExclusionExport(ProcessedDataset $dataset, DatasetExportManager $exportManager): void
    {
        $records = $dataset->exclusionRecords();

        if (empty($records)) {
            return;
        }

        $label = 'Excluded Accounts';
        $filename = 'excluded-accounts.xlsx';

        $builder = function (\PhpOffice\PhpSpreadsheet\Spreadsheet $spreadsheet) use ($records, $label) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle(substr($label, 0, 31));

            $headers = ['Account Num', 'Customer Ref', 'Reason', 'Source File'];
            $sheet->fromArray($headers, null, 'A1');

            $rowPointer = 2;
            foreach ($records as $record) {
                $rowValues = [
                    $record['account_num'] ?? '',
                    $record['customer_ref'] ?? '',
                    $record['reason'] ?? '',
                    $record['file'] ?? '',
                ];
                $sheet->fromArray($rowValues, null, 'A' . $rowPointer);
                $rowPointer++;
            }
        };

        $meta = [
            'label' => $label,
            'row_count' => count($records),
            'sheet_title' => $label,
        ];

        $exportManager->store($dataset, 'exclusions', 'excluded-accounts', $filename, $builder, $meta, $this->userContext);
    }

    private function generateGroupAExports(ProcessedDataset $dataset, array $headers, array $assignments, DatasetExportManager $exportManager): void
    {
        $group = $assignments['group_a'] ?? [];

        foreach ($group['quotas'] ?? [] as $key => $quota) {
            $rows = $quota['rows'] ?? [];

            if (empty($rows)) {
                continue;
            }

            $label = $quota['label'] ?? Str::title(str_replace('-', ' ', $key));
            $filename = Str::slug($label ?: $key) . '.xlsx';

            $builder = $exportManager->singleSheetBuilder($dataset, $headers, $rows, $label);
            $meta = [
                'label' => $label,
                'row_count' => count($rows),
                'sheet_title' => $label,
                'headers' => $headers,
            ];

            $exportManager->store($dataset, 'group-a', $key, $filename, $builder, $meta, $this->userContext);
        }

        $region = $group['region_billing'] ?? [];
        $rows = $region['rows'] ?? [];

        if (empty($rows)) {
            return;
        }

        $label = $region['label'] ?? 'Region Billing Centre';
        $filename = Str::slug($label ?: 'region-billing-centre') . '.xlsx';

        $builder = $exportManager->singleSheetBuilder($dataset, $headers, $rows, $label);
        $meta = [
            'label' => $label,
            'row_count' => count($rows),
            'sheet_title' => $label,
            'headers' => $headers,
        ];

        $exportManager->store($dataset, 'group-a', 'region-billing', $filename, $builder, $meta, $this->userContext);
    }

    private function generateGroupBExports(ProcessedDataset $dataset, array $headers, array $assignments, DatasetExportManager $exportManager): void
    {
        $group = $assignments['group_b'] ?? [];

        $bundle = $group['enterprise_wholesale'] ?? [];
        $categories = $bundle['categories'] ?? [];

        if (! empty($categories)) {
            $sheets = [];

            foreach ($categories as $category) {
                $rows = $category['rows'] ?? [];

                if (empty($rows)) {
                    continue;
                }

                $sheets[] = [
                    'name' => $category['label'] ?? Str::title($category['key'] ?? 'Category'),
                    'rows' => $rows,
                ];
            }

            if (! empty($sheets)) {
                $builder = $exportManager->multiSheetBuilder($dataset, $headers, $sheets);
                $meta = [
                    'label' => $bundle['label'] ?? 'Enterprise & Wholesale bundle',
                    'sheet_count' => count($sheets),
                    'headers' => $headers,
                    'segment_names' => array_map(static fn ($sheet) => $sheet['name'], $sheets),
                ];

                $exportManager->store(
                    $dataset,
                    'group-b',
                    'enterprise-wholesale',
                    'enterprise_wholesale.xlsx',
                    $builder,
                    $meta,
                    $this->userContext
                );
            }
        }

        foreach ($group['segments'] ?? [] as $segment) {
            $rows = $segment['rows'] ?? [];

            if (empty($rows)) {
                continue;
            }

            $slug = $segment['slug'] ?? Str::slug($segment['name'] ?? 'segment');
            $label = $segment['name'] ?? 'Segment';
            $filename = Str::slug($label ?: $slug) . '.xlsx';

            $builder = $exportManager->singleSheetBuilder($dataset, $headers, $rows, $label);
            $meta = [
                'label' => $label,
                'row_count' => count($rows),
                'sheet_title' => $label,
                'headers' => $headers,
            ];

            $exportManager->store($dataset, 'group-b', $slug, $filename, $builder, $meta, $this->userContext);
        }

        $ignored = $group['ignored'] ?? [];
        $ignoredRows = $ignored['rows'] ?? [];

        if (! empty($ignoredRows)) {
            $label = $ignored['label'] ?? 'Ignored - LATEST_BILL_MNY < 5000';
            $filename = Str::slug($label ?: 'ignored-latest-bill-mny') . '.xlsx';

            $builder = $exportManager->singleSheetBuilder($dataset, $headers, $ignoredRows, $label);
            $meta = [
                'label' => $label,
                'row_count' => count($ignoredRows),
                'sheet_title' => $label,
                'headers' => $headers,
            ];

            $exportManager->store($dataset, 'group-b', 'ignored-latest-bill', $filename, $builder, $meta, $this->userContext);
        }
    }
}
