<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDatasetExports;
use App\Support\DatasetAssignmentManager;
use App\Support\ProcessedDataset;
use App\Support\ProcessesExcelRows;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;
use ZipArchive;

class ExclusionUploadController extends Controller
{
    use ProcessesExcelRows;

    private const MAX_FILES = 3;

    public function create(): View|RedirectResponse
    {
        $dataset = $this->resolveDatasetOrRedirect('Please complete the main upload before managing exclusions.');

        if ($dataset instanceof RedirectResponse) {
            return $dataset;
        }

        return view('process.exclusions', [
            'filename' => $dataset->originalFilename(),
            'totalRows' => $dataset->filteredRowCount(),
            'maxFiles' => self::MAX_FILES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $dataset = $this->resolveDatasetOrRedirect('Please complete the main upload before managing exclusions.');

        if ($dataset instanceof RedirectResponse) {
            return $dataset;
        }

        $request->validate([
            'exclusions' => 'required',
            'exclusions.*' => 'file|mimes:zip|max:20480',
        ]);

        /** @var UploadedFile[] $files */
        $files = $request->file('exclusions', []);

        if (! is_array($files)) {
            $files = array_filter([$files]);
        }

        if (count($files) === 0) {
            return back()
                ->withErrors(['exclusions' => 'Please add at least one exclusion file before submitting.'])
                ->withInput();
        }

        if (count($files) > self::MAX_FILES) {
            return back()
                ->withErrors(['exclusions' => sprintf('You can upload a maximum of %d exclusion files at once.', self::MAX_FILES)])
                ->withInput();
        }

        try {
            $exclusionKeys = $this->collectExclusionKeys($files);
        } catch (\RuntimeException $exception) {
            return back()
                ->withErrors(['exclusions' => $exception->getMessage()])
                ->withInput();
        }

        if ($exclusionKeys['rows'] === 0) {
            return back()
                ->withErrors(['exclusions' => 'The uploaded files did not contain any rows to match against.'])
                ->withInput();
        }

        $result = $dataset->removeAccounts($exclusionKeys['account_num'], $exclusionKeys['details']);
        $removedCount = (int) ($result['removed_count'] ?? 0);
        $removedEntries = $result['removed'] ?? [];

        if ($removedCount === 0) {
            return back()->with('status', 'No matching records were removed by the exclusion files.');
        }

        $removedDetails = array_map(static function (array $entry) {
            return [
                'row_index' => $entry['row_index'] ?? null,
                'account_num' => $entry['account_num'] ?? null,
                'customer_ref' => $entry['customer_ref'] ?? null,
                'reason' => $entry['reason'] ?? 'Excluded via exclusion file.',
                'file' => $entry['file'] ?? null,
            ];
        }, $removedEntries);

        $history = $dataset->exclusionHistory();
        $history[] = [
            'removed' => $removedCount,
            'files' => array_map(fn(UploadedFile $file) => $file->getClientOriginalName(), $files),
            'timestamp' => now()->toDateTimeString(),
            'records' => $removedDetails,
        ];
        $dataset->setExclusionHistory($history);
        $dataset->setExclusionRecords($removedDetails);

        $manager = app(DatasetAssignmentManager::class);
        session()->put('process.assignments', $manager->buildAssignmentsFromDataset($dataset));

        $user = $request->user();
        $userContext = [
            'id' => $user?->getAuthIdentifier(),
            'name' => $user?->username ?? $user?->name ?? ($user?->email ?? null),
        ];

        GenerateDatasetExports::dispatch(
            $dataset->token(),
            $dataset->manifestPath(),
            $userContext
        );

        return redirect()
            ->route('process.assignments.index')
            ->with('status', sprintf('%d records were excluded from the master list. Assignments have been prepared.', $removedCount));
    }

    private function resolveDatasetOrRedirect(string $message): ProcessedDataset|RedirectResponse
    {
        try {
            $dataset = ProcessedDataset::fromSession();
        } catch (Throwable $exception) {
            $dataset = null;
        }

        if (! $dataset) {
            return redirect()->route('process.upload.create')->withErrors([
                'upload' => $message,
            ]);
        }

        return $dataset;
    }

    /**
     * @param UploadedFile[] $files
     */
    private function collectExclusionKeys(array $files): array
    {
        $keys = [
            'account_num' => [],
            'rows' => 0,
            'details' => [],
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
            $fileName = $file->getClientOriginalName();

            foreach ($dataRows as $columns) {
                if (! $this->rowHasData($columns)) {
                    continue;
                }

                $account = $this->getColumnValue($columns, $headerMap, 'ACCOUNT_NUM');
                $reason = $this->extractExclusionReason($columns, $headerMap);

                if ($account === '') {
                    continue;
                }

                if ($account !== '') {
                    $keys['account_num'][$account] = true;
                    if (! isset($keys['details'][$account])) {
                        $keys['details'][$account] = [];
                    }
                    $keys['details'][$account][] = [
                        'file' => $fileName,
                        'reason' => $reason,
                    ];
                }

                $keys['rows']++;
            }
        }

        return $keys;
    }

    private function extractExclusionReason(array $columns, array $headerMap): string
    {
        foreach (['EXCLUSION_REASON', 'REASON', 'REMARKS', 'COMMENT'] as $column) {
            $value = $this->getColumnValue($columns, $headerMap, $column);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function loadWorksheetRows(UploadedFile $file): array
    {
        $workbookPath = $this->prepareWorkbookPath($file);

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);

            $spreadsheet = $reader->load($workbookPath);

            $allRows = [];
            $firstSheet = true;

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $rows = $sheet->toArray(null, true, true, true);

                if (empty($rows)) {
                    continue;
                }

                if ($firstSheet) {
                    // include header row from the first worksheet
                    foreach ($rows as $r) {
                        $allRows[] = $r;
                    }
                    $firstSheet = false;
                } else {
                    // subsequent worksheets: skip their header row (first row)
                    $rowKeys = array_keys($rows);
                    $headerKey = $rowKeys[0] ?? null;
                    if ($headerKey !== null) {
                        unset($rows[$headerKey]);
                    }

                    foreach ($rows as $r) {
                        $allRows[] = $r;
                    }
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return $allRows;
        } finally {
            if ($workbookPath !== $file->getRealPath() && file_exists($workbookPath)) {
                @unlink($workbookPath);
            }
        }
    }

    private function prepareWorkbookPath(UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension !== 'zip') {
            return $file->getRealPath();
        }

        return $this->extractWorkbookFromZip($file);
    }

    private function extractWorkbookFromZip(UploadedFile $file): string
    {
        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw new \RuntimeException(sprintf('Unable to open the ZIP archive "%s".', $file->getClientOriginalName()));
        }

        try {
            $entry = $this->locateExcelEntry($zip);
            $targetDirectory = storage_path('app/tmp');
            if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0755, true) && ! is_dir($targetDirectory)) {
                throw new \RuntimeException('Unable to prepare a temporary directory for exclusions.');
            }

            $targetPath = $targetDirectory . '/exclusion_' . uniqid('', true) . '.xlsx';

            $source = $zip->getStream($entry);
            if (! $source) {
                throw new \RuntimeException(sprintf('Unable to read "%s" inside the ZIP archive.', $entry));
            }

            $target = fopen($targetPath, 'wb');
            if (! $target) {
                fclose($source);
                throw new \RuntimeException('Unable to store the extracted workbook for processing.');
            }

            stream_copy_to_stream($source, $target);
            fclose($source);
            fclose($target);

            return $targetPath;
        } finally {
            $zip->close();
        }
    }

    private function locateExcelEntry(ZipArchive $zip): string
    {
        $entries = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $name = $stat['name'] ?? '';

            if ($name === '' || str_ends_with($name, '/')) {
                continue;
            }

            if (str_ends_with(strtolower($name), '.xlsx')) {
                $entries[] = $name;
            }
        }

        if (empty($entries)) {
            throw new \RuntimeException('Each ZIP must contain exactly one Excel (.xlsx) workbook, but none were found.');
        }

        if (count($entries) > 1) {
            throw new \RuntimeException('Each ZIP must contain exactly one Excel (.xlsx) workbook.');
        }

        return $entries[0];
    }
}
