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

class MasterDatasetExclusionService
{
    use ProcessesExcelRows;

    private const ASSIGNMENT_EXCLUDED = 'Excluded';
    private const MAX_ROW_ERRORS = 20;

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
                'exclusions' => 'Please upload at least one exclusion workbook.',
            ]);
        }

        $this->disk = Storage::disk($process->storage_disk ?: config('filesystems.default', 'local'));

        $token = $process->token;
        $archiveDirectory = $this->archiveDirectory($token);
        $savedArchives = [];
        $accountMap = [];
        $rowErrors = [];
        $rowErrorCount = 0;

        try {
            foreach ($uploads as $upload) {
                if (! $upload instanceof UploadedFile) {
                    continue;
                }

                $stored = $this->storeArchive($upload, $archiveDirectory);
                $savedArchives[] = $stored;
                $multipleEntries = count($stored['extracted_paths']) > 1;

                foreach ($stored['extracted_paths'] as $extracted) {
                    $fileLabel = $multipleEntries
                        ? sprintf('%s :: %s', $stored['original_name'], $extracted['entry_name'])
                        : $stored['original_name'];

                    $this->extractAccountsFromWorkbook(
                        $extracted['path'],
                        $fileLabel,
                        $accountMap,
                        $rowErrors,
                        $rowErrorCount
                    );
                }
            }

            if (! empty($rowErrors)) {
                if ($rowErrorCount > self::MAX_ROW_ERRORS) {
                    $rowErrors[] = sprintf('Showing first %d validation errors only.', self::MAX_ROW_ERRORS);
                }

                throw ValidationException::withMessages([
                    'exclusions' => $rowErrors,
                ]);
            }
        } catch (Throwable $exception) {
            $this->cleanupSavedArchives($savedArchives);
            throw $exception instanceof ValidationException ? $exception : ValidationException::withMessages([
                'exclusions' => $exception->getMessage() ?: 'Unable to read one of the exclusion workbooks.',
            ]);
        } finally {
            foreach ($savedArchives as $archive) {
                if (! empty($archive['extracted_paths'])) {
                    foreach ($archive['extracted_paths'] as $extracted) {
                        if (! empty($extracted['path']) && file_exists($extracted['path'])) {
                            @unlink($extracted['path']);
                        }
                    }
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
        $filename = $token . '.xlsx';
        $storedPath = $archiveDirectory . '/' . $filename;

        $this->disk->makeDirectory($archiveDirectory);
        $this->disk->putFileAs($archiveDirectory, $upload, $filename);

        $extractedPaths = [$this->copyWorkbookToTemp($storedPath)];

        return [
            'stored_path' => $storedPath,
            'extracted_paths' => $extractedPaths,
            'original_name' => $upload->getClientOriginalName(),
            'size' => $upload->getSize(),
        ];
    }

    private function copyWorkbookToTemp(string $storedPath): array
    {
        $source = $this->disk->path($storedPath);
        if (! is_file($source)) {
            throw new RuntimeException('Unable to read the uploaded exclusion workbook.');
        }

        $target = storage_path('app/tmp/exclusion_' . uniqid('', true) . '.xlsx');
        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to prepare a temporary directory for exclusions.');
        }

        if (! copy($source, $target)) {
            throw new RuntimeException('Unable to prepare the exclusion workbook for processing.');
        }

        return [
            'path' => $target,
            'entry_name' => basename($storedPath),
        ];
    }

    private function extractAccountsFromWorkbook(
        string $workbookPath,
        string $fileLabel,
        array &$accountMap,
        array &$rowErrors,
        int &$rowErrorCount
    ): void
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($workbookPath);

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetTitle = (string) $sheet->getTitle();
            $headers = [];
            $headerMap = [];
            $accountLetter = null;

            foreach ($sheet->getRowIterator() as $row) {
                $excelRow = (int) $row->getRowIndex();
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $columns = [];
                foreach ($cellIterator as $cell) {
                    $columns[$cell->getColumn()] = $cell->getValue();
                }

                if ($excelRow === 1) {
                    $headers = $this->prepareHeaders($columns);
                    $headerMap = $this->buildHeaderMap($headers);
                    $accountLetter = $headerMap['ACCOUNT_NUM'] ?? null;

                    if (! $accountLetter) {
                        throw ValidationException::withMessages([
                            'exclusions' => sprintf('File %s, worksheet %s: required column ACCOUNT_NUM is missing.', $fileLabel, $sheetTitle),
                        ]);
                    }

                    continue;
                }

                // Skip validation for rows that are completely blank (all columns empty)
                if (! $this->rowHasData($columns)) {
                    // All columns are empty, skip this row entirely
                    continue;
                }

                $account = trim((string) ($columns[$accountLetter] ?? ''));
                if ($account === '') {
                    // Only trigger error if at least one other column is non-empty (row is not fully blank)
                    $nonEmpty = false;
                    foreach ($columns as $col => $val) {
                        if ($col !== $accountLetter && !$this->isEmpty($val)) {
                            $nonEmpty = true;
                            break;
                        }
                    }
                    if ($nonEmpty) {
                        $rowErrorCount++;
                        if (count($rowErrors) < self::MAX_ROW_ERRORS) {
                            $rowErrors[] = sprintf(
                                'File %s, row %d, column ACCOUNT_NUM: value is required.',
                                $fileLabel,
                                $excelRow
                            );
                        }
                    }
                    // If row is only blank in ACCOUNT_NUM but has other data, error; if all blank, skip
                    continue;
                }

                $reason = $this->extractExclusionReason($columns, $headerMap);
                $accountMap[$account][] = [
                    'file' => $fileLabel,
                    'reason' => $reason,
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        gc_collect_cycles();
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

    private function archiveDirectory(string $token): string
    {
        return 'exports/' . $token . '/exclusions';
    }
}
