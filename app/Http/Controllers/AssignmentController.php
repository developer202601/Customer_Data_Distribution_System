<?php

namespace App\Http\Controllers;

use App\Support\DatasetAssignmentManager;
use App\Support\ProcessesExcelRows;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    use ProcessesExcelRows;

    public function index(): View|RedirectResponse
    {
        $context = $this->resolveContext('Run the main upload and exclusions before reviewing assignments.');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $assignments = $context['assignments'];

        $groupTotals = [
            'group_a' => (int) ($assignments['group_a']['totals']['input'] ?? 0),
            'group_b' => (int) ($assignments['group_b']['totals']['eligible'] ?? 0),
        ];

        return view('process.assignments.overview', [
            'dataset' => $context['dataset'],
            'generatedAt' => $assignments['generated_at'] ?? null,
            'groupTotals' => $groupTotals,
            'latestExclusion' => $this->latestExclusionSummary($context['dataset']),
            'filteredOutSummary' => $this->filteredOutSummary($context['dataset']),
            'vipSummary' => $this->vipSummary($context['dataset']),
        ]);
    }

    public function groupA(): View|RedirectResponse
    {
        $context = $this->resolveContext('Run the main upload and exclusions before reviewing assignments.');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('process.assignments.group-a', [
            'dataset' => $context['dataset'],
            'assignments' => $context['assignments']['group_a'] ?? [],
            'generatedAt' => $context['assignments']['generated_at'] ?? null,
            'latestExclusion' => $this->latestExclusionSummary($context['dataset']),
        ]);
    }

    public function groupB(): View|RedirectResponse
    {
        $context = $this->resolveContext('Run the main upload and exclusions before reviewing assignments.');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('process.assignments.group-b', [
            'dataset' => $context['dataset'],
            'assignments' => $context['assignments']['group_b'] ?? [],
            'generatedAt' => $context['assignments']['generated_at'] ?? null,
            'latestExclusion' => $this->latestExclusionSummary($context['dataset']),
        ]);
    }

    public function exclusions(): View|RedirectResponse
    {
        $context = $this->resolveContext('Upload a dataset and exclusions before reviewing the exclusion summary.');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        return view('process.assignments.exclusions', [
            'dataset' => $context['dataset'],
            'latestExclusion' => $this->latestExclusionSummary($context['dataset']),
                'filteredOutSummary' => $this->filteredOutSummary($context['dataset']),
                'vipSummary' => $this->vipSummary($context['dataset']),
        ]);
    }

    public function filteredOut(Request $request): View|RedirectResponse
    {
        $context = $this->resolveContext('Upload a dataset before reviewing filtered-out records.');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $dataset = $context['dataset'];
        $headers = $dataset['headers'] ?? [];
        $filteredOut = $dataset['filtered_out'] ?? [];

        if (empty($filteredOut)) {
            return redirect()->route('process.assignments.exclusions')->withErrors([
                'assignments' => 'No filtered-out records are available yet. Upload a dataset and rerun the filters.',
            ]);
        }

        $headerMap = $this->buildHeaderMap($headers);
        $overallCount = count($filteredOut);

        $searchTerm = trim((string) $request->query('search', ''));
        $searchApplied = $searchTerm !== '';
        $filtered = $filteredOut;

        if ($searchApplied) {
            $term = $this->normaliseSearchTerm($searchTerm);
            $targets = array_filter([
                $headerMap['CUSTOMER_REF'] ?? null,
                $headerMap['ACCOUNT_NUM'] ?? null,
                $headerMap['PRODUCT_LABEL'] ?? null,
            ]);

            $filtered = array_filter($filteredOut, function ($entry) use ($term, $targets) {
                $reason = trim((string) ($entry['reason'] ?? ''));
                if ($reason !== '' && $this->valueContainsTerm($reason, $term)) {
                    return true;
                }

                $columns = $entry['columns'] ?? [];

                foreach ($targets as $letter) {
                    $value = isset($columns[$letter]) ? trim((string) $columns[$letter]) : '';

                    if ($value !== '' && $this->valueContainsTerm($value, $term)) {
                        return true;
                    }
                }

                return false;
            });
        }

        ksort($filtered, SORT_NUMERIC);

        $matchingCount = count($filtered);
        $limit = 50;
        $limited = $matchingCount > $limit;
        $displayRows = $limited ? array_slice($filtered, 0, $limit, true) : $filtered;

        return view('process.assignments.filtered-out', [
            'dataset' => $dataset,
            'headers' => $headers,
            'rows' => $displayRows,
            'overallCount' => $overallCount,
            'matchingCount' => $matchingCount,
            'displayCount' => count($displayRows),
            'limited' => $limited,
            'searchTerm' => $searchTerm,
            'searchApplied' => $searchApplied,
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $dataset = session('process.upload.data');

        if (! $dataset || empty($dataset['headers']) || empty($dataset['rows'])) {
            return redirect()->route('process.upload.create')->withErrors([
                'upload' => 'Processed data is unavailable. Upload the master file again.',
            ]);
        }

        $manager = app(DatasetAssignmentManager::class);
        session()->put('process.assignments', $manager->buildAssignments($dataset['headers'], $dataset['rows']));

        $redirectRoute = $request->input('redirect_to');
        $message = 'Assignments regenerated successfully.';

        if ($redirectRoute && Route::has($redirectRoute)) {
            return redirect()->route($redirectRoute)->with('status', $message);
        }

        return redirect()->route('process.assignments.index')->with('status', $message);
    }

    public function download(string $group, string $bucket): RedirectResponse|StreamedResponse
    {
        $context = $this->resolveContext('Run the main upload and exclusions before downloading assignments.');

        if ($context instanceof RedirectResponse) {
            return $context;
        }

        $assignments = $context['assignments'];
        $dataset = $context['dataset'];
        $headers = $assignments['headers'] ?? $dataset['headers'] ?? [];
        $dataRows = $dataset['rows'] ?? [];

        if (empty($headers)) {
            $fallbackRoute = $group === 'group-b'
                ? 'process.assignments.group-b'
                : 'process.assignments.group-a';

            return redirect()->route($fallbackRoute)->withErrors([
                'assignments' => 'Column headers are missing. Regenerate the assignments and try again.',
            ]);
        }

        if (in_array($bucket, ['latest-exclusions', 'filtered-out'], true)) {
            return $this->downloadLatestExclusions($dataset, $group, $bucket);
        }

        return match ($group) {
            'group-a' => $this->downloadGroupA($headers, $dataRows, $assignments['group_a'] ?? [], $bucket),
            'group-b' => $this->downloadGroupB($headers, $dataRows, $assignments['group_b'] ?? [], $bucket),
            default => redirect()->route('process.assignments.index')->withErrors([
                'assignments' => 'Unknown assignment group requested.',
            ]),
        };
    }

    private function downloadGroupA(array $headers, array $dataRows, array $group, string $bucket): RedirectResponse|StreamedResponse
    {
        if ($bucket === 'region-billing') {
            $rows = $group['region_billing']['rows'] ?? [];
            $label = $group['region_billing']['label'] ?? 'Region Billing Centre';
            $filename = Str::slug($label ?: 'region-billing-centre') . '.xlsx';

            return $this->streamWorkbook($headers, $dataRows, $rows, $filename, $label);
        }

        $quota = $group['quotas'][$bucket] ?? null;

        if (! $quota) {
            return redirect()->route('process.assignments.group-a')->withErrors([
                'assignments' => 'The requested Group A export could not be found.',
            ]);
        }

        $label = $quota['label'] ?? Str::title(str_replace('-', ' ', $bucket));
        $filename = Str::slug($label ?: $bucket) . '.xlsx';

        return $this->streamWorkbook($headers, $dataRows, $quota['rows'] ?? [], $filename, $label);
    }

    private function downloadGroupB(array $headers, array $dataRows, array $group, string $bucket): RedirectResponse|StreamedResponse
    {
        if ($bucket === 'enterprise-wholesale') {
            $bundle = $group['enterprise_wholesale'] ?? [];
            $categories = $bundle['categories'] ?? [];

            if (empty($categories)) {
                return redirect()->route('process.assignments.group-b')->withErrors([
                    'assignments' => 'No enterprise or wholesale records available for export.',
                ]);
            }

            $sheets = [];
            foreach ($categories as $category) {
                $rows = $category['rows'] ?? [];

                if (empty($rows)) {
                    continue;
                }

                $label = $category['label'] ?? Str::title($category['key'] ?? 'Category');
                $sheets[] = [
                    'name' => $label,
                    'rows' => $rows,
                ];
            }

            if (empty($sheets)) {
                return redirect()->route('process.assignments.group-b')->withErrors([
                    'assignments' => 'No enterprise or wholesale records available for export.',
                ]);
            }

            return $this->streamMultiSheetWorkbook($headers, $dataRows, $sheets, 'enterprise_wholesale.xlsx');
        }

        if ($bucket === 'ignored-latest-bill') {
            $ignored = $group['ignored'] ?? [];

            if (empty($ignored['rows'])) {
                return redirect()->route('process.assignments.group-b')->withErrors([
                    'assignments' => 'No records were ignored for falling below the LATEST_BILL_MNY threshold.',
                ]);
            }

            $label = $ignored['label'] ?? 'Ignored - LATEST_BILL_MNY < 5000';
            $filename = Str::slug($label ?: 'ignored-latest-bill-mny') . '.xlsx';

            return $this->streamWorkbook($headers, $dataRows, $ignored['rows'] ?? [], $filename, $label);
        }

        $segment = null;
        foreach ($group['segments'] ?? [] as $entry) {
            if (($entry['slug'] ?? '') === $bucket) {
                $segment = $entry;
                break;
            }
        }

        if (! $segment) {
            return redirect()->route('process.assignments.group-b')->withErrors([
                'assignments' => 'The requested Group B segment export could not be found.',
            ]);
        }

        $label = $segment['name'] ?? 'Segment';
        $filename = Str::slug($label ?: 'segment') . '.xlsx';

        return $this->streamWorkbook($headers, $dataRows, $segment['rows'] ?? [], $filename, $label);
    }

    private function streamWorkbook(array $headers, array $dataRows, array $records, string $filename, string $sheetTitle): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($this->truncateSheetTitle($sheetTitle));

        $headerLabels = $this->buildHeaderLabels($headers);
        $sheet->fromArray($headerLabels, null, 'A1');

        $rowPointer = 2;
        foreach ($records as $record) {
            $rowValues = $this->buildRowValues($headers, $dataRows, $record);
            $sheet->fromArray($rowValues, null, 'A' . $rowPointer);
            $rowPointer++;
        }

        if ($rowPointer === 2) {
            $sheet->fromArray([], null, 'A2');
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function streamMultiSheetWorkbook(array $headers, array $dataRows, array $segments, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($segments as $index => $segment) {
            $label = $segment['name'] ?? 'Segment ' . ($index + 1);
            $sheet = $spreadsheet->createSheet($index);
            $sheet->setTitle($this->truncateSheetTitle($label));

            $headerLabels = $this->buildHeaderLabels($headers);
            $sheet->fromArray($headerLabels, null, 'A1');

            $rowPointer = 2;
            foreach ($segment['rows'] ?? [] as $record) {
                $rowValues = $this->buildRowValues($headers, $dataRows, $record);
                $sheet->fromArray($rowValues, null, 'A' . $rowPointer);
                $rowPointer++;
            }

            if ($rowPointer === 2) {
                $sheet->fromArray([], null, 'A2');
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildHeaderLabels(array $headers): array
    {
        $labels = ['Excel Row'];

        foreach ($headers as $meta) {
            $labels[] = $meta['label'] ?? 'Column';
        }

        return $labels;
    }

    private function buildRowValues(array $headers, array $dataRows, int|array $record): array
    {
        if (is_array($record)) {
            $rowIndex = $record['row_index'] ?? null;
        } else {
            $rowIndex = $record;
        }

        $values = [$rowIndex];
        $columns = ($rowIndex !== null && isset($dataRows[$rowIndex])) ? $dataRows[$rowIndex] : [];

        foreach ($headers as $letter => $meta) {
            $values[] = $columns[$letter] ?? '';
        }

        return $values;
    }

    private function truncateSheetTitle(string $title): string
    {
        $safe = trim($title) !== '' ? trim($title) : 'Sheet';
        $length = 31;

        if (function_exists('mb_substr')) {
            return mb_substr($safe, 0, $length);
        }

        return substr($safe, 0, $length);
    }

    private function resolveContext(string $missingMessage): array|RedirectResponse
    {
        $assignments = session('process.assignments');
        $dataset = session('process.upload.data');

        if (! $assignments || ! $dataset) {
            return redirect()->route('process.upload.create')->withErrors([
                'upload' => $missingMessage,
            ]);
        }

        return [
            'assignments' => $assignments,
            'dataset' => $dataset,
        ];
    }

    private function latestExclusionSummary(array $dataset): ?array
    {
        $history = $dataset['exclusions_history'] ?? [];

        if (empty($history)) {
            return null;
        }

        $latest = $history[count($history) - 1];

        $files = array_values($latest['files'] ?? []);
        $records = array_values($latest['records'] ?? []);
        if (empty($records) && ! empty($dataset['exclusion_records'])) {
            $records = array_values($dataset['exclusion_records']);
        }
        $previewLimit = 5;

        return [
            'removed' => (int) ($latest['removed'] ?? 0),
            'files' => $files,
            'files_count' => count($files),
            'timestamp' => $latest['timestamp'] ?? null,
            'records' => $records,
            'records_preview' => array_slice($records, 0, $previewLimit),
            'records_total' => count($records),
            'preview_limit' => $previewLimit,
        ];
    }

    private function filteredOutSummary(array $dataset): ?array
    {
        $headers = $dataset['headers'] ?? [];
        $filteredOut = $dataset['filtered_out'] ?? [];

        if (empty($headers) || empty($filteredOut)) {
            return null;
        }

        $headerMap = $this->buildHeaderMap($headers);
        $previewLimit = 5;
        $reasonCounts = [];

        foreach ($filteredOut as $entry) {
            $reason = $entry['reason'] ?? 'Filtered out by eligibility rules.';
            $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
        }

        arsort($reasonCounts);
        $topReasons = array_slice($reasonCounts, 0, 3, true);

        $preview = [];

        foreach (array_slice($filteredOut, 0, $previewLimit, true) as $rowIndex => $entry) {
            $columns = $entry['columns'] ?? [];
            $preview[] = [
                'row_index' => $rowIndex,
                'reason' => $entry['reason'] ?? 'Filtered out by eligibility rules.',
                'account_num' => $this->getColumnValue($columns, $headerMap, 'ACCOUNT_NUM'),
                'customer_ref' => $this->getColumnValue($columns, $headerMap, 'CUSTOMER_REF'),
                'status' => $this->getColumnValue($columns, $headerMap, 'LATEST_PRODUCT_STATUS'),
                'medium' => $this->getColumnValue($columns, $headerMap, 'MEDIUM'),
            ];
        }

        return [
            'count' => count($filteredOut),
            'preview' => $preview,
            'preview_limit' => $previewLimit,
            'top_reasons' => $topReasons,
        ];
    }

    private function vipSummary(array $dataset): ?array
    {
        $headers = $dataset['headers'] ?? [];
        $rows = $dataset['rows'] ?? [];

        if (empty($headers) || empty($rows)) {
            return null;
        }

        $headerMap = $this->buildHeaderMap($headers);
        $vipRows = $this->filterVipRows($rows, $headerMap);
        $total = count($vipRows);

        $previewLimit = 5;
        $preview = [];

        if ($total > 0) {
            foreach (array_slice($vipRows, 0, $previewLimit, true) as $rowIndex => $columns) {
                $preview[] = [
                    'row_index' => $rowIndex,
                    'account_num' => $this->getColumnValue($columns, $headerMap, 'ACCOUNT_NUM'),
                    'customer_ref' => $this->getColumnValue($columns, $headerMap, 'CUSTOMER_REF'),
                    'credit_class' => $this->getColumnValue($columns, $headerMap, 'CREDIT_CLASS_NAME')
                        ?: $this->getColumnValue($columns, $headerMap, 'CUSTOMER_SEGMENT'),
                ];
            }
        }

        return [
            'count' => $total,
            'preview' => $preview,
            'preview_limit' => $previewLimit,
        ];
    }

    private function downloadLatestExclusions(array $dataset, string $group, string $bucket): RedirectResponse|StreamedResponse
    {
        $records = $dataset['exclusion_records'] ?? [];
        $filteredOut = $dataset['filtered_out'] ?? [];

        $fallbackRoute = $group === 'group-b'
            ? 'process.assignments.group-b'
            : 'process.assignments.group-a';

        if (empty($records) && empty($filteredOut)) {
            $history = $dataset['exclusions_history'] ?? [];
            if (! empty($history)) {
                $latest = $history[count($history) - 1];
                $records = $latest['records'] ?? [];
            }
        }

        if (empty($records) && empty($filteredOut)) {
            return redirect()->route($fallbackRoute)->withErrors([
                'assignments' => 'There are no exclusion or filtered-out records available to export yet.',
            ]);
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $sheetIndex = 0;

        if (! empty($records)) {
            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle($this->truncateSheetTitle('Latest Exclusions'));

            $headers = ['Excel Row', 'Account Number', 'Customer Reference', 'Source File'];
            $sheet->fromArray($headers, null, 'A1');

            $rowPointer = 2;
            foreach ($records as $record) {
                $sheet->fromArray([
                    $record['row_index'] ?? null,
                    $record['account_num'] ?? '',
                    $record['customer_ref'] ?? '',
                    $record['file'] ?? '',
                ], null, 'A' . $rowPointer);
                $rowPointer++;
            }

            if ($rowPointer === 2) {
                $sheet->fromArray([], null, 'A2');
            }
        }

        if (! empty($filteredOut)) {
            $sheet = $spreadsheet->createSheet($sheetIndex++);
            $sheet->setTitle($this->truncateSheetTitle('Filtered Out'));

            $headers = $dataset['headers'] ?? [];
            $headerLabels = array_merge(['Reason', 'Reason Code'], $this->buildHeaderLabels($headers));
            $sheet->fromArray($headerLabels, null, 'A1');

            $rowPointer = 2;
            foreach ($filteredOut as $entry) {
                $columns = $entry['columns'] ?? [];
                $rowIndex = $entry['row_index'] ?? null;

                $rowValues = [
                    $entry['reason'] ?? 'Filtered out by eligibility rules.',
                    $entry['reason_code'] ?? '',
                    $rowIndex,
                ];

                foreach ($headers as $letter => $meta) {
                    $rowValues[] = $columns[$letter] ?? '';
                }

                $sheet->fromArray($rowValues, null, 'A' . $rowPointer);
                $rowPointer++;
            }

            if ($rowPointer === 2) {
                $sheet->fromArray([], null, 'A2');
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = $bucket === 'filtered-out' ? 'filtered_out.xlsx' : 'latest_exclusions.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
