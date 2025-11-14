<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProcessFileController extends Controller
{
    private const EXPECTED_COLUMNS = [
        'RUN_DATE',
        'REGION',
        'RTOM',
        'CUSTOMER_REF',
        'ACCOUNT_NUM',
        'PRODUCT_LABEL',
        'MEDIUM',
        'CUSTOMER_SEGMENT',
        'ADDRESS_NAME',
        'FULL_ADDRESS',
        'LATEST_BILL_MNY',
        'NEW_ARREARS_20251022',
        'MOBILE_CONTACT_TEL',
        'EMAIL_ADDRESS',
        'CREDIT_SCORE',
        'CREDIT_CLASS_NAME',
        'BILL_HANDLING_CODE_NAME',
        'AGE_MONTHS',
        'SALES_PERSON',
        'ACCOUNT_MANAGER',
        'SLT_GL_SUB_SEGMENT',
        'BILLING_CENTRE',
        'PROVINCE',
        'NEXT_BILL_DTM',
        'BILL_MONTH',
        'LATEST_BILL_DTM',
        'INVOICING_CO_ID',
        'INVOICING_CO_NAME',
        'PRODUCT_SEQ',
        'PRODUCT_ID',
        'LATEST_PRODUCT_STATUS',
        'BILL_HANDLING_CODE',
        'SLT_BUSINESS_LINE_VALUE',
        'SALES_CHANNEL',
    ];

    private const OPTIONAL_COLUMNS = [
        'ADDRESS_NAME',
        'EMAIL_ADDRESS',
        'CREDIT_SCORE',
        'SALES_PERSON',
        'SALES_CHANNEL',
    ];

    private const FILTER_MEDIUM_VALUES = ['COPPER', 'FTTH'];
    private const FILTER_STATUS_VALUE = 'OK';
    private const FILTER_MIN_ARREARS = 2400;
    private const PREVIEW_ROW_LIMIT = 10;

    public function create(): View
    {
        return view('process.upload');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'upload' => 'required|file|mimes:xlsx',
        ]);

        $file = $request->file('upload');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (Throwable $exception) {
            return back()->withErrors([
                'upload' => 'Unable to read the Excel file. Please verify the file is not corrupted and try again.',
            ]);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return back()->withErrors([
                'upload' => 'The spreadsheet must include a header row and at least one data row.',
            ]);
        }

        [$headers, $dataRows] = $this->separateHeaderAndRows($rows);

        if (empty($headers)) {
            return back()->withErrors([
                'upload' => 'Unable to locate the header row in the spreadsheet.',
            ]);
        }

        $availableColumns = array_map(static fn(array $header) => $header['normalised'], $headers);
        $missingColumns = array_diff(self::EXPECTED_COLUMNS, $availableColumns);

        if (! empty($missingColumns)) {
            return back()->withErrors([
                'upload' => 'Missing required columns: ' . implode(', ', $missingColumns) . '.',
            ]);
        }

        $errors = [];

        foreach ($dataRows as $rowIndex => $columns) {
            if (! $this->rowHasData($columns)) {
                continue;
            }

            foreach ($headers as $columnLetter => $headerMeta) {
                $normalised = $headerMeta['normalised'];

                if (! in_array($normalised, self::EXPECTED_COLUMNS, true)) {
                    continue;
                }

                $value = $columns[$columnLetter] ?? null;
                $isOptional = in_array($normalised, self::OPTIONAL_COLUMNS, true);

                if ($this->isEmpty($value)) {
                    if ($isOptional) {
                        continue;
                    }

                    $errors[] = sprintf('Row %d: "%s" cannot be empty.', $rowIndex, $headerMeta['label']);
                    continue;
                }

                if ($normalised === 'LATEST_BILL_MNY' && ! $this->isValidLatestBill($value)) {
                    $errors[] = sprintf('Row %d: "%s" must contain a numeric amount or "-".', $rowIndex, $headerMeta['label']);
                }
            }
        }

        if (! empty($errors)) {
            return back()->withErrors($errors);
        }

        $storedPath = $file->store('uploads');

        session()->put('process.upload.path', $storedPath);
        session()->put('process.upload.filename', $file->getClientOriginalName());

        return redirect()
            ->route('process.upload.preview')
            ->with('status', 'File uploaded and validated successfully.');
    }

    public function preview(Request $request): View|RedirectResponse
    {
        return $this->renderFilteredView($request, false);
    }

    public function vip(Request $request): View|RedirectResponse
    {
        return $this->renderFilteredView($request, true);
    }

    public function exportVip(Request $request): StreamedResponse|RedirectResponse
    {
        $dataset = $this->prepareVipExportRows($request);

        if ($dataset instanceof RedirectResponse) {
            return $dataset;
        }

        $headers = $dataset['headers'];
        $filteredRows = $dataset['rows'];

        $exportSpreadsheet = new Spreadsheet();
        $sheet = $exportSpreadsheet->getActiveSheet();

        $headerLabels = ['Excel Row'];
        foreach ($headers as $meta) {
            $headerLabels[] = $meta['label'];
        }

        $sheet->fromArray($headerLabels, null, 'A1');

        $rowPointer = 2;
        foreach ($filteredRows as $rowIndex => $row) {
            $rowValues = [$rowIndex];
            foreach ($headers as $letter => $meta) {
                $rowValues[] = $row[$letter] ?? '';
            }

            $sheet->fromArray($rowValues, null, 'A' . $rowPointer);
            $rowPointer++;
        }

        if ($rowPointer === 2) {
            $sheet->fromArray([], null, 'A2');
        }

        $downloadName = $this->buildVipFilename('xlsx');

        return response()->streamDownload(function () use ($exportSpreadsheet) {
            $writer = new Xlsx($exportSpreadsheet);
            $writer->save('php://output');
        }, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }


    private function renderFilteredView(Request $request, bool $forceVip): View|RedirectResponse
    {
        $dataset = $this->loadSpreadsheetOrRedirect(
            $forceVip ? 'Upload a file to review VIP records.' : 'Upload a file to see the filtered results.',
            'The stored spreadsheet does not contain enough data to display.'
        );

        if ($dataset instanceof RedirectResponse) {
            return $dataset;
        }

        [$headers, $dataRows] = $dataset;

        $headerMap = $this->buildHeaderMap($headers);
        $filteredRows = $this->filterRows($dataRows, $headers);

        $vipApplied = $forceVip || $request->boolean('vip');

        if ($vipApplied) {
            $filteredRows = $this->filterVipRows($filteredRows, $headerMap);
        }

        $filteredCount = count($filteredRows);

        $searchTerm = trim((string) $request->query('search', ''));
        $searchApplied = $searchTerm !== '';

        if ($searchApplied) {
            $displayRows = $this->searchRows($filteredRows, $headerMap, $searchTerm);
            $limited = false;
        } else {
            $limit = $vipApplied ? null : self::PREVIEW_ROW_LIMIT;
            if ($limit !== null && count($filteredRows) > $limit) {
                $displayRows = array_slice($filteredRows, 0, $limit, true);
                $limited = true;
            } else {
                $displayRows = $filteredRows;
                $limited = false;
            }
        }

        $dataRowCount = 0;

        foreach ($dataRows as $columns) {
            if ($this->rowHasData($columns)) {
                $dataRowCount++;
            }
        }

        $summary = [
            'total_rows' => $dataRowCount,
            'filtered_rows' => $filteredCount,
            'skipped_rows' => max($dataRowCount - $filteredCount, 0),
        ];

        return view('process.filtered', [
            'headers' => $headers,
            'filteredRows' => $displayRows,
            'summary' => $summary,
            'filename' => session('process.upload.filename'),
            'searchTerm' => $searchTerm,
            'searchApplied' => $searchApplied,
            'filteredCount' => $filteredCount,
            'limited' => $limited,
            'vipApplied' => $vipApplied,
            'displayCount' => count($displayRows),
        ]);
    }

    private function loadSpreadsheetOrRedirect(string $missingUploadStatus, string $insufficientDataMessage): array|RedirectResponse
    {
        $path = session('process.upload.path');

        if (! $path) {
            return redirect()
                ->route('process.upload.create')
                ->with('status', $missingUploadStatus);
        }

        if (! Storage::exists($path)) {
            session()->forget('process.upload.path');
            session()->forget('process.upload.filename');

            return redirect()
                ->route('process.upload.create')
                ->withErrors(['upload' => 'The uploaded file is no longer available. Please upload it again.']);
        }

        try {
            $spreadsheet = IOFactory::load(Storage::path($path));
        } catch (Throwable $exception) {
            session()->forget('process.upload.path');
            session()->forget('process.upload.filename');

            return redirect()
                ->route('process.upload.create')
                ->withErrors(['upload' => 'Unable to read the stored Excel file. Please upload it again.']);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return redirect()
                ->route('process.upload.create')
                ->withErrors(['upload' => $insufficientDataMessage]);
        }

        [$headers, $dataRows] = $this->separateHeaderAndRows($rows);

        if (empty($headers)) {
            return redirect()
                ->route('process.upload.create')
                ->withErrors(['upload' => 'Unable to locate the header row in the stored spreadsheet.']);
        }

        return [$headers, $dataRows];
    }

    private function prepareVipExportRows(Request $request): array|RedirectResponse
    {
        $dataset = $this->loadSpreadsheetOrRedirect(
            'Upload a file to export VIP records.',
            'The stored spreadsheet does not contain enough data to export.'
        );

        if ($dataset instanceof RedirectResponse) {
            return $dataset;
        }

        [$headers, $dataRows] = $dataset;

        $headerMap = $this->buildHeaderMap($headers);
        $filteredRows = $this->filterRows($dataRows, $headers);
        $filteredRows = $this->filterVipRows($filteredRows, $headerMap);

        $searchTerm = trim((string) $request->query('search', ''));

        if ($searchTerm !== '') {
            $filteredRows = $this->searchRows($filteredRows, $headerMap, $searchTerm);
        }

        return [
            'headers' => $headers,
            'rows' => $filteredRows,
        ];
    }

    private function buildVipFilename(string $extension): string
    {
        $filename = pathinfo((string) session('process.upload.filename'), PATHINFO_FILENAME);
        $downloadName = $filename !== '' ? $filename . '_vip' : 'vip-records';

        return $downloadName . '.' . $extension;
    }

    private function separateHeaderAndRows(array $rows): array
    {
        if (empty($rows)) {
            return [[], []];
        }

        $rowKeys = array_keys($rows);
        $headerKey = $rowKeys[0];
        $headerRow = $rows[$headerKey];
        unset($rows[$headerKey]);

        $headers = $this->prepareHeaders($headerRow);

        return [$headers, $rows];
    }

    private function prepareHeaders(array $headerRow): array
    {
        $headers = [];

        foreach ($headerRow as $letter => $value) {
            $label = trim((string) $value);
            $normalised = $this->normaliseHeaderName($label);

            if ($normalised === '') {
                $normalised = sprintf('COLUMN_%s', $letter);
            }

            $headers[$letter] = [
                'label' => $label !== '' ? $label : sprintf('Column %s', $letter),
                'normalised' => $normalised,
            ];
        }

        return $headers;
    }

    private function normaliseHeaderName(string $header): string
    {
        if ($header === '') {
            return '';
        }

        $normalised = strtoupper(trim($header));
        $normalised = preg_replace('/\s+/', '_', $normalised);
        $normalised = preg_replace('/[^A-Z0-9_]/', '_', $normalised);

        return preg_replace('/_+/', '_', $normalised);
    }

    private function rowHasData(array $columns): bool
    {
        foreach ($columns as $value) {
            if (! $this->isEmpty($value)) {
                return true;
            }
        }

        return false;
    }

    private function isEmpty($value): bool
    {
        if ($value === null) {
            return true;
        }

        return trim((string) $value) === '';
    }

    private function isValidLatestBill($value): bool
    {
        $value = trim((string) $value);

        if ($value === '') {
            return false;
        }

        if ($value === '-') {
            return true;
        }

        $numeric = str_replace([',', ' '], '', $value);

        return is_numeric($numeric);
    }

    private function buildHeaderMap(array $headers): array
    {
        $map = [];

        foreach ($headers as $letter => $meta) {
            $map[$meta['normalised']] = $letter;
        }

        return $map;
    }

    private function filterRows(array $rows, array $headers): array
    {
        $headerMap = $this->buildHeaderMap($headers);
        $results = [];

        foreach ($rows as $rowIndex => $columns) {
            if (! $this->rowHasData($columns)) {
                continue;
            }

            $medium = strtoupper($this->getColumnValue($columns, $headerMap, 'MEDIUM'));
            if (! in_array($medium, self::FILTER_MEDIUM_VALUES, true)) {
                continue;
            }

            $status = strtoupper($this->getColumnValue($columns, $headerMap, 'LATEST_PRODUCT_STATUS'));
            if ($status !== self::FILTER_STATUS_VALUE) {
                continue;
            }

            $arrears = $this->parseNumeric($this->getColumnValue($columns, $headerMap, 'NEW_ARREARS_20251022'));
            if ($arrears <= self::FILTER_MIN_ARREARS) {
                continue;
            }

            $results[$rowIndex] = $columns;
        }

        return $results;
    }

    private function filterVipRows(array $rows, array $headerMap): array
    {
        $results = [];

        foreach ($rows as $rowIndex => $columns) {
            $creditClass = $this->getColumnValue($columns, $headerMap, 'CREDIT_CLASS_NAME');

            if ($creditClass === '') {
                $creditClass = $this->getColumnValue($columns, $headerMap, 'CUSTOMER_SEGMENT');
            }

            if ($creditClass === '') {
                continue;
            }

            if ($this->isVipCreditClass($creditClass)) {
                $results[$rowIndex] = $columns;
            }
        }

        return $results;
    }

    private function isVipCreditClass(string $value): bool
    {
        $candidate = trim($value);

        if ($candidate === '') {
            return false;
        }

        return (bool) preg_match('/^VIP(\s*-\s*.+)?$/i', $candidate);
    }

    private function searchRows(array $rows, array $headerMap, string $term): array
    {
        $term = $this->normaliseSearchTerm($term);

        if ($term === '') {
            return $rows;
        }

        $targets = array_filter([
            $headerMap['CUSTOMER_REF'] ?? null,
            $headerMap['ACCOUNT_NUM'] ?? null,
            $headerMap['PRODUCT_LABEL'] ?? null,
        ]);

        if (empty($targets)) {
            return [];
        }

        $results = [];

        foreach ($rows as $rowIndex => $columns) {
            foreach ($targets as $columnLetter) {
                $value = isset($columns[$columnLetter]) ? trim((string) $columns[$columnLetter]) : '';

                if ($value === '') {
                    continue;
                }

                if ($this->valueContainsTerm($value, $term)) {
                    $results[$rowIndex] = $columns;
                    break;
                }
            }
        }

        return $results;
    }

    private function normaliseSearchTerm(string $term): string
    {
        $term = trim($term);

        if ($term === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($term);
        }

        return strtolower($term);
    }

    private function valueContainsTerm(string $value, string $term): bool
    {
        if ($term === '') {
            return true;
        }

        if (function_exists('mb_stripos')) {
            return mb_stripos($value, $term) !== false;
        }

        return stripos($value, $term) !== false;
    }

    private function getColumnValue(array $row, array $headerMap, string $column): string
    {
        if (! isset($headerMap[$column])) {
            return '';
        }

        $value = $row[$headerMap[$column]] ?? '';

        return trim((string) $value);
    }

    private function parseNumeric(string $value): float
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return 0.0;
        }

        $numeric = str_replace([',', ' '], '', $value);

        return is_numeric($numeric) ? (float) $numeric : 0.0;
    }
}
