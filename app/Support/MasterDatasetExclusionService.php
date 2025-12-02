<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use App\Support\MasterDatasetAssignmentService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;
use ZipArchive;

class MasterDatasetExclusionService
{
    use ProcessesExcelRows;

    private const ASSIGNMENT_EXCLUDED = 'Excluded';

    private Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk(config('filesystems.default', 'local'));
    }

    /**
     * @param UploadedFile[] $uploads
     *
     * @throws ValidationException
     */
    public function apply(MasterDatasetProcess $process, array $uploads): array
    {
        if (empty($uploads)) {
            throw ValidationException::withMessages([
                'exclusions' => 'Please upload at least one exclusion archive.',
            ]);
        }

        $this->disk = Storage::disk($process->storage_disk ?: config('filesystems.default', 'local'));

        $token = $process->token;
        $archiveDirectory = $this->archiveDirectory($token);
        $savedArchives = [];
        $accountMap = [];

        try {
            foreach ($uploads as $upload) {
                if (! $upload instanceof UploadedFile) {
                    continue;
                }

                $stored = $this->storeArchive($upload, $archiveDirectory);
                $savedArchives[] = $stored;

                $worksheetRows = $this->loadWorksheetRows($stored['extracted_path']);
                if (empty($worksheetRows)) {
                    continue;
                }

                [$headers, $dataRows] = $this->separateHeaderAndRows($worksheetRows);
                $headerMap = $this->buildHeaderMap($headers);

                foreach ($dataRows as $columns) {
                    if (! $this->rowHasData($columns)) {
                        continue;
                    }

                    $account = $this->getColumnValue($columns, $headerMap, 'ACCOUNT_NUM');
                    if ($account === '') {
                        continue;
                    }

                    $reason = $this->extractExclusionReason($columns, $headerMap);
                    $accountMap[$account][] = [
                        'file' => $stored['original_name'],
                        'reason' => $reason,
                    ];
                }
            }
        } catch (Throwable $exception) {
            $this->cleanupSavedArchives($savedArchives);
            throw $exception instanceof ValidationException ? $exception : ValidationException::withMessages([
                'exclusions' => $exception->getMessage() ?: 'Unable to read one of the exclusion archives.',
            ]);
        } finally {
            foreach ($savedArchives as $archive) {
                if (! empty($archive['extracted_path']) && file_exists($archive['extracted_path'])) {
                    @unlink($archive['extracted_path']);
                }
            }
        }

        if (empty($accountMap)) {
            $this->cleanupSavedArchives($savedArchives);
            throw ValidationException::withMessages([
                'exclusions' => 'The uploaded files did not contain any ACCOUNT_NUM values.',
            ]);
        }

        return $this->applyAccounts($process, $savedArchives, $accountMap);
    }

    private function applyAccounts(MasterDatasetProcess $process, array $savedArchives, array $accountMap): array
    {
        $matched = 0;
        $alreadyExcluded = 0;
        DB::beginTransaction();

        try {
            $rows = MasterDatasetRow::query()
                ->where('process_id', $process->id)
                ->where('excluded', false)
                ->whereIn('account_num', array_keys($accountMap))
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $details = $accountMap[$row->account_num] ?? [];
                $reasonText = $this->buildReasonText($details);

                $matched++;

                $row->excluded = true;
                $row->exclusion_reason = $reasonText;
                $row->assigned_to = self::ASSIGNMENT_EXCLUDED;
                $row->exclusion_priority = 10;
                $row->save();
            }

            $archivesPayload = $this->mergeArchiveHistory($process, $savedArchives);
            $process->update([
                'exclusion_archives' => $archivesPayload,
            ]);

            DB::commit();
        } catch (Throwable $exception) {
            DB::rollBack();
            $this->cleanupSavedArchives($savedArchives);
            throw $exception instanceof ValidationException ? $exception : ValidationException::withMessages([
                'exclusions' => $exception->getMessage() ?: 'Unable to update the master dataset with exclusions.',
            ]);
        }

        $process = $process->fresh();
        $assignmentSummary = app(MasterDatasetAssignmentService::class)->assign($process);

        return [
            'matched' => $matched,
            'already_excluded' => $alreadyExcluded,
            'archives' => $savedArchives,
            'statistics' => $assignmentSummary['statistics'] ?? [],
        ];
    }

    private function storeArchive(UploadedFile $upload, string $archiveDirectory): array
    {
        $token = Str::uuid()->toString();
        $filename = $token . '.zip';
        $storedPath = $archiveDirectory . '/' . $filename;

        $this->disk->makeDirectory($archiveDirectory);
        $this->disk->putFileAs($archiveDirectory, $upload, $filename);

        $extractedPath = $this->extractWorkbook($storedPath);

        return [
            'stored_path' => $storedPath,
            'extracted_path' => $extractedPath,
            'original_name' => $upload->getClientOriginalName(),
            'size' => $upload->getSize(),
        ];
    }

    private function extractWorkbook(string $storedPath): string
    {
        $absoluteZip = $this->disk->path($storedPath);
        $zip = new ZipArchive();

        if ($zip->open($absoluteZip) !== true) {
            throw new RuntimeException('Unable to open one of the exclusion ZIP archives.');
        }

        try {
            $entry = $this->locateExcelEntry($zip);
            $target = storage_path('app/tmp/exclusion_' . uniqid('', true) . '.xlsx');
            $directory = dirname($target);

            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException('Unable to prepare a temporary directory for exclusions.');
            }

            $stream = $zip->getStream($entry);
            if (! $stream) {
                throw new RuntimeException(sprintf('Unable to read "%s" inside the ZIP archive.', $entry));
            }

            $handle = fopen($target, 'wb');
            if (! $handle) {
                fclose($stream);
                throw new RuntimeException('Unable to extract exclusion workbook.');
            }

            stream_copy_to_stream($stream, $handle);
            fclose($stream);
            fclose($handle);

            return $target;
        } finally {
            $zip->close();
        }
    }

    private function loadWorksheetRows(string $workbookPath): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($workbookPath);

        $rows = [];
        foreach ($spreadsheet->getAllSheets() as $index => $sheet) {
            $sheetRows = $sheet->toArray(null, true, true, true);
            if ($index === 0) {
                $rows = array_merge($rows, $sheetRows);
            } else {
                $rowKeys = array_keys($sheetRows);
                $headerKey = $rowKeys[0] ?? null;
                if ($headerKey !== null) {
                    unset($sheetRows[$headerKey]);
                }
                $rows = array_merge($rows, $sheetRows);
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
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

    private function buildReasonText(array $details): string
    {
        if (empty($details)) {
            return 'Excluded via uploaded file.';
        }

        $first = Arr::first($details);
        $file = $first['file'] ?? 'exclusion file';
        $reason = trim((string) ($first['reason'] ?? ''));

        if ($reason !== '') {
            return sprintf('Excluded via %s (%s)', $file, $reason);
        }

        return sprintf('Excluded via %s', $file);
    }

    private function mergeArchiveHistory(MasterDatasetProcess $process, array $savedArchives): array
    {
        $existing = $process->exclusion_archives ?? [];
        if (! is_array($existing)) {
            $existing = [];
        }

        foreach ($savedArchives as $archive) {
            $existing[] = [
                'path' => $archive['stored_path'],
                'original_name' => $archive['original_name'],
                'size' => $archive['size'],
                'uploaded_at' => now()->toIso8601String(),
            ];
        }

        return $existing;
    }

    private function cleanupSavedArchives(array $savedArchives): void
    {
        foreach ($savedArchives as $archive) {
            if (! empty($archive['stored_path'])) {
                $this->disk->delete($archive['stored_path']);
            }
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
            throw new RuntimeException('Each exclusion ZIP must contain exactly one Excel (.xlsx) workbook.');
        }

        if (count($entries) > 1) {
            throw new RuntimeException('Each exclusion ZIP must contain exactly one Excel (.xlsx) workbook.');
        }

        return $entries[0];
    }

    private function archiveDirectory(string $token): string
    {
        return 'exports/' . $token . '/exclusions';
    }
}
