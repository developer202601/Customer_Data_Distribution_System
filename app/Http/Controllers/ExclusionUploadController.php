<?php

namespace App\Http\Controllers;

use App\Support\ProcessesExcelRows;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ExclusionUploadController extends Controller
{
    use ProcessesExcelRows;

    private const MAX_FILES = 3;

    public function create(): View|RedirectResponse
    {
        $dataset = session('process.upload.data');

        if (! $dataset) {
            return redirect()->route('process.upload.create')->withErrors([
                'upload' => 'Please complete the main upload before managing exclusions.',
            ]);
        }

        return view('process.exclusions', [
            'filename' => $dataset['original_filename'] ?? null,
            'totalRows' => count($dataset['rows'] ?? []),
            'maxFiles' => self::MAX_FILES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dataset = session('process.upload.data');

        if (! $dataset) {
            return redirect()->route('process.upload.create')->withErrors([
                'upload' => 'Please complete the main upload before managing exclusions.',
            ]);
        }

        $validated = $request->validate([
            'exclusions' => 'required|array|min:1|max:' . self::MAX_FILES,
            'exclusions.*' => 'file|mimes:xlsx|max:10240',
        ]);

        /** @var UploadedFile[] $files */
        $files = $request->file('exclusions', []);

        $exclusionKeys = $this->collectExclusionKeys($files);

        if ($exclusionKeys['rows'] === 0) {
            return back()
                ->withErrors(['exclusions' => 'The uploaded files did not contain any rows to match against.'])
                ->withInput();
        }

        $headers = $dataset['headers'] ?? [];
        $rows = $dataset['rows'] ?? [];

        if (empty($headers) || empty($rows)) {
            return redirect()->route('process.upload.create')->withErrors([
                'upload' => 'Processed data is no longer available. Please upload the master file again.',
            ]);
        }

        [$filteredRows, $removedCount] = $this->applyExclusions($rows, $headers, $exclusionKeys);

        if ($removedCount === 0) {
            return back()->with('status', 'No matching records were removed by the exclusion files.');
        }

        $dataset['rows'] = $filteredRows;
        $dataset['total_rows'] = count($filteredRows);

        $history = $dataset['exclusions_history'] ?? [];
        $history[] = [
            'removed' => $removedCount,
            'files' => array_map(fn (UploadedFile $file) => $file->getClientOriginalName(), $files),
            'timestamp' => now()->toDateTimeString(),
        ];
        $dataset['exclusions_history'] = $history;

        session()->put('process.upload.data', $dataset);

        return redirect()
            ->route('process.upload.preview')
            ->with('status', sprintf('%d records were excluded from the master list.', $removedCount));
    }

    /**
     * @param UploadedFile[] $files
     */
    private function collectExclusionKeys(array $files): array
    {
        $keys = [
            'customer_ref' => [],
            'account_num' => [],
            'rows' => 0,
        ];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $worksheetRows = $this->loadWorksheetRows($file);

            if (empty($worksheetRows)) {
                continue;
            }

            [$headers, $dataRows] = $this->separateHeaderAndRows($worksheetRows);
            $headerMap = $this->buildHeaderMap($headers);

            foreach ($dataRows as $columns) {
                if (! $this->rowHasData($columns)) {
                    continue;
                }

                $customer = $this->getColumnValue($columns, $headerMap, 'CUSTOMER_REF');
                $account = $this->getColumnValue($columns, $headerMap, 'ACCOUNT_NUM');

                if ($customer === '' && $account === '') {
                    continue;
                }

                if ($customer !== '') {
                    $keys['customer_ref'][$customer] = true;
                }

                if ($account !== '') {
                    $keys['account_num'][$account] = true;
                }

                $keys['rows']++;
            }
        }

        return $keys;
    }

    private function applyExclusions(array $rows, array $headers, array $keys): array
    {
        $headerMap = $this->buildHeaderMap($headers);
        $filtered = [];
        $removed = 0;

        foreach ($rows as $rowIndex => $columns) {
            $customer = $this->getColumnValue($columns, $headerMap, 'CUSTOMER_REF');
            $account = $this->getColumnValue($columns, $headerMap, 'ACCOUNT_NUM');

            $matchesCustomer = $customer !== '' && isset($keys['customer_ref'][$customer]);
            $matchesAccount = $account !== '' && isset($keys['account_num'][$account]);

            if ($matchesCustomer || $matchesAccount) {
                $removed++;
                continue;
            }

            $filtered[$rowIndex] = $columns;
        }

        return [$filtered, $removed];
    }

    private function loadWorksheetRows(UploadedFile $file): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($file->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }
}
