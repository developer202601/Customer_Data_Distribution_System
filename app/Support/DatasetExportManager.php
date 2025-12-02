<?php

namespace App\Support;

use App\Models\DatasetExport;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatasetExportManager
{
    public function store(
        ProcessedDataset $dataset,
        string $group,
        string $bucket,
        string $filename,
        callable $builder,
        array $meta = [],
        ?array $userContext = null
    ): ?DatasetExport {
        $spreadsheet = new Spreadsheet();
        $builder($spreadsheet);

        $tempFile = tempnam(sys_get_temp_dir(), 'export_');

        if ($tempFile === false) {
            $spreadsheet->disconnectWorksheets();
            return null;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);
        $spreadsheet->disconnectWorksheets();

        $disk = config('filesystems.default', 'local');
        $timestamp = now();
        $storagePath = $this->buildStoragePath($dataset, $group, $bucket, $filename, $timestamp);

        $stored = false;
        $stream = fopen($tempFile, 'rb');

        if ($stream !== false) {
            try {
                $stored = Storage::disk($disk)->put($storagePath, $stream);
            } finally {
                fclose($stream);
            }
        }

        $fileSize = $stored ? (filesize($tempFile) ?: null) : null;
        $fileHash = $stored ? (@hash_file('sha256', $tempFile) ?: null) : null;

        @unlink($tempFile);

        if (! $stored) {
            return null;
        }

        $context = $this->resolveUserContext($userContext);

        return DatasetExport::create([
            'token' => $dataset->token(),
            'group' => $group,
            'bucket' => $bucket,
            'label' => $meta['label'] ?? null,
            'filename' => $filename,
            'file_path' => $storagePath,
            'file_disk' => $disk,
            'file_size' => $fileSize,
            'file_hash' => $fileHash,
            'user_id' => $context['id'],
            'user_name' => $context['name'],
            'generated_at' => $timestamp,
            'status' => 'ready',
            'meta' => $this->filterMeta($meta),
        ]);
    }

    public function storeAndDownload(
        ProcessedDataset $dataset,
        string $group,
        string $bucket,
        string $downloadName,
        callable $builder,
        array $meta = [],
        ?array $userContext = null
    ): StreamedResponse {
        $record = $this->store($dataset, $group, $bucket, $downloadName, $builder, $meta, $userContext);

        if ($record) {
            return Storage::disk($record->file_disk)->download($record->file_path, $record->filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response()->streamDownload(function () use ($builder) {
            $sheetWorkbook = new Spreadsheet();
            $builder($sheetWorkbook);
            $writer = new Xlsx($sheetWorkbook);
            $writer->save('php://output');
            $sheetWorkbook->disconnectWorksheets();
        }, $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function singleSheetBuilder(
        ProcessedDataset $dataset,
        array $headers,
        array $records,
        string $sheetTitle
    ): callable {
        $headersCopy = $headers;
        $recordsCopy = $records;
        $title = $sheetTitle;

        return function (Spreadsheet $spreadsheet) use ($dataset, $headersCopy, $recordsCopy, $title) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle($this->truncateSheetTitle($title));

            $headerLabels = $this->buildHeaderLabels($headersCopy);
            $sheet->fromArray($headerLabels, null, 'A1');

            $rowPointer = 2;
            foreach ($recordsCopy as $record) {
                $rowValues = $this->buildRowValues($dataset, $headersCopy, $record);
                $sheet->fromArray($rowValues, null, 'A' . $rowPointer);
                $rowPointer++;
            }

            if ($rowPointer === 2) {
                $sheet->fromArray([], null, 'A2');
            }
        };
    }

    public function multiSheetBuilder(
        ProcessedDataset $dataset,
        array $headers,
        array $segments
    ): callable {
        $headersCopy = $headers;
        $segmentsCopy = $segments;

        return function (Spreadsheet $spreadsheet) use ($dataset, $headersCopy, $segmentsCopy) {
            $spreadsheet->removeSheetByIndex(0);

            foreach ($segmentsCopy as $index => $segment) {
                $sheetLabel = $segment['name'] ?? 'Segment ' . ($index + 1);
                $sheet = $spreadsheet->createSheet($index);
                $sheet->setTitle($this->truncateSheetTitle($sheetLabel));

                $headerLabels = $this->buildHeaderLabels($headersCopy);
                $sheet->fromArray($headerLabels, null, 'A1');

                $rowPointer = 2;
                foreach ($segment['rows'] ?? [] as $record) {
                    $rowValues = $this->buildRowValues($dataset, $headersCopy, $record);
                    $sheet->fromArray($rowValues, null, 'A' . $rowPointer);
                    $rowPointer++;
                }

                if ($rowPointer === 2) {
                    $sheet->fromArray([], null, 'A2');
                }
            }

            $spreadsheet->setActiveSheetIndex(0);
        };
    }

    private function buildStoragePath(ProcessedDataset $dataset, string $group, string $bucket, string $filename, $timestamp): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION) ?: 'xlsx';
        $groupSlug = Str::slug($group) ?: 'group';
        $bucketSlug = Str::slug($bucket) ?: 'export';
        $token = $dataset->token();
        $stamp = $timestamp->format('Ymd_His_u');

        return sprintf(
            'exports/%s/%s/%s_%s.%s',
            $token,
            $groupSlug,
            $bucketSlug,
            $stamp,
            $extension
        );
    }

    private function filterMeta(array $meta): array
    {
        $allowed = [
            'label',
            'row_count',
            'sheet_title',
            'sheet_count',
            'segment_names',
            'headers',
        ];

        return Arr::only($meta, $allowed);
    }

    private function resolveUserContext(?array $userContext): array
    {
        if (is_array($userContext)) {
            return [
                'id' => $userContext['id'] ?? null,
                'name' => $userContext['name'] ?? null,
            ];
        }

        $user = Auth::user();

        if (! $user) {
            return ['id' => null, 'name' => null];
        }

        $name = $user->username ?? $user->name ?? ($user->email ?? null);

        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $name,
        ];
    }

    private function buildHeaderLabels(array $headers): array
    {
        $labels = ['Excel Row'];

        foreach ($headers as $meta) {
            $labels[] = $meta['label'] ?? 'Column';
        }

        return $labels;
    }

    private function buildRowValues(ProcessedDataset $dataset, array $headers, int|array $record): array
    {
        $rowIndex = is_array($record) ? ($record['row_index'] ?? null) : $record;
        $rowIndex = is_numeric($rowIndex) ? (int) $rowIndex : null;

        $values = [$rowIndex];
        $columns = $rowIndex !== null ? ($dataset->getFilteredRow($rowIndex) ?? []) : [];

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
}
