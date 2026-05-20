<?php

namespace App\Support;

use App\Jobs\BuildMasterDatasetExports;
use App\Models\CallCenterReport;
use App\Models\DatasetExport;
use App\Models\MasterDatasetProcess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use Throwable;
use App\Support\MasterDatasetProcessStatus;
use Illuminate\Database\Eloquent\Builder;

class MasterDatasetExportCoordinator
{
    private const STORAGE_FORMAT = 'csv';
    private const EXPORT_BUCKETS = [
        'call-center-staff' => ['group' => 'group-a'],
        'call-center' => ['group' => 'group-a'],
        'staff' => ['group' => 'group-a'],
        'region-billing' => ['group' => 'region'],
        'enterprise-wholesale' => ['group' => 'group-b'],
        'sme' => ['group' => 'group-b'],
        'vip' => ['group' => 'vip'],
        'excluded' => ['group' => 'exclusions'],
        'excluded-copper-retail-micro' => ['group' => 'exclusions'],
    ];

    public function __construct(
        private MasterDatasetViewService $viewService,
        private MasterDatasetExportService $exportService,
    ) {}

    public function ensureFresh(MasterDatasetProcess $process, ?array $userContext = null): array
    {
        $diskName = $process->storage_disk ?: config('filesystems.default', 'local');
        $existing = DatasetExport::where('token', $process->token)->get()->keyBy('bucket');
        $context = $this->resolveUserContext($userContext);

        $freshProcess = $process->fresh();
        if ($freshProcess->status === MasterDatasetProcessStatus::CANCELED || MasterDatasetCancellation::isAborted($freshProcess)) {
            $statuses = [];

            foreach (self::EXPORT_BUCKETS as $bucket => $_meta) {
                $record = $existing->get($bucket);
                $statuses[$bucket] = [
                    'status' => $record?->status,
                    'generated_at' => optional($record?->generated_at)->toIso8601String(),
                ];
            }

            return $statuses;
        }

        $shouldDispatch = false;
        $statuses = [];

        foreach (self::EXPORT_BUCKETS as $bucket => $meta) {
            $label = $this->viewService->bucketLabel($bucket);
            $filename = $this->viewService->bucketFilename($bucket, self::STORAGE_FORMAT);
            $path = $this->exportPath($process->token, $filename);

            $record = $existing->get($bucket);

            if (! $record) {
                try {
                    $record = DatasetExport::create([
                        'token' => $process->token,
                        'group' => $meta['group'],
                        'bucket' => $bucket,
                        'label' => $label,
                        'filename' => $filename,
                        'file_path' => $path,
                        'file_disk' => $diskName,
                        'status' => 'processing',
                        'user_id' => $context['id'],
                        'user_name' => $context['name'],
                        'meta' => [],
                    ]);
                } catch (QueryException $exception) {
                    // Another request may have created the row concurrently.
                    $record = DatasetExport::query()
                        ->where('token', $process->token)
                        ->where('bucket', $bucket)
                        ->orderByDesc('id')
                        ->first();

                    if (! $record) {
                        throw $exception;
                    }
                }
                $shouldDispatch = true;
            } else {
                $updates = [];

                if ($record->label !== $label) {
                    $updates['label'] = $label;
                }

                if ($record->filename !== $filename) {
                    $updates['filename'] = $filename;
                }

                if ($record->file_path !== $path) {
                    $updates['file_path'] = $path;
                }

                if ($record->file_disk !== $diskName) {
                    $updates['file_disk'] = $diskName;
                }

                if ($record->user_id !== $context['id']) {
                    $updates['user_id'] = $context['id'];
                }

                if ($record->user_name !== $context['name']) {
                    $updates['user_name'] = $context['name'];
                }

                $stale = $this->isStale($process, $record);
                $missing = $record->status === 'ready'
                    && $record->file_path
                    && ! Storage::disk($record->file_disk)->exists($record->file_path);

                if ($stale || $missing) {
                    if ($record->status !== 'processing') {
                        $updates['status'] = 'processing';
                        $shouldDispatch = true;
                    }
                }

                if (! empty($updates)) {
                    $record->fill($updates);
                    $record->save();
                    $record->refresh();
                }
            }

            $statuses[$bucket] = [
                'status' => $record->status,
                'generated_at' => optional($record->generated_at)->toIso8601String(),
            ];
        }

        if ($shouldDispatch) {
            MasterDatasetProcessStatus::set($process->fresh(), MasterDatasetProcessStatus::EXPORTS_PENDING);
            BuildMasterDatasetExports::dispatch($process->id)->onQueue($this->queueName());
        }

        return $statuses;
    }

    public function generateExports(MasterDatasetProcess $process): void
    {
        $diskName = $process->storage_disk ?: config('filesystems.default', 'local');
        $disk = Storage::disk($diskName);
        $freshProcess = $process->fresh();

        if ($freshProcess->status === MasterDatasetProcessStatus::CANCELED || MasterDatasetCancellation::isAborted($freshProcess)) {
            return;
        }

        $records = DatasetExport::where('token', $process->token)
            ->where('status', 'processing')
            ->get();

        foreach ($records as $record) {
            $freshProcess->refresh();
            if ($freshProcess->status === MasterDatasetProcessStatus::CANCELED || MasterDatasetCancellation::isAborted($freshProcess)) {
                return;
            }

            $bucket = $record->bucket;

            if (! array_key_exists($bucket, self::EXPORT_BUCKETS)) {
                $record->update(['status' => 'failed']);
                continue;
            }

            $label = $this->viewService->bucketLabel($bucket);
            $filename = $this->viewService->bucketFilename($bucket, self::STORAGE_FORMAT);
            $path = $this->exportPath($process->token, $filename);
            $query = $this->viewService->bucketQuery($freshProcess, $bucket);

            $record->fill([
                'label' => $label,
                'filename' => $filename,
                'file_path' => $path,
                'file_disk' => $diskName,
            ])->save();

            try {
                // Use Spout for memory-efficient export
                $this->exportService->storeToDiskWithSpout($freshProcess, $label, $query, $disk, $path, self::STORAGE_FORMAT);

                $size = $disk->size($path);
                $hash = null;

                if (method_exists($disk, 'path')) {
                    $absolute = $disk->path($path);
                    if (is_string($absolute) && is_file($absolute)) {
                        $hash = hash_file('sha256', $absolute);
                    }
                }

                $record->update([
                    'status' => 'ready',
                    'generated_at' => now(),
                    'file_size' => $size ?: null,
                    'file_hash' => $hash,
                ]);

                if ($bucket === 'call-center-staff') {
                    $this->recordReport($freshProcess, $query, CallCenterReport::REPORT_TYPE_CALL_CENTER);
                }

                if ($bucket === 'region-billing') {
                    $this->recordReport($freshProcess, $query, CallCenterReport::REPORT_TYPE_REGIONAL_BILLING);
                }
            } catch (Throwable $exception) {
                $meta = $record->meta ?? [];
                $meta['error'] = $exception->getMessage();
                $meta['failed_at'] = now()->toIso8601String();

                $record->update([
                    'status' => 'failed',
                    'meta' => $meta,
                ]);

                MasterDatasetProcessStatus::set($freshProcess->fresh(), MasterDatasetProcessStatus::FAILED);

                throw $exception;
            }
        }

        MasterDatasetProcessStatus::set($freshProcess->fresh(), MasterDatasetProcessStatus::READY);
    }

    private function resolveUserContext(?array $context): array
    {
        if (is_array($context)) {
            return [
                'id' => $context['id'] ?? null,
                'name' => $context['name'] ?? null,
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

    private function exportPath(string $token, string $filename): string
    {
        return sprintf('exports/%s/downloads/%s', $token, ltrim($filename, '/'));
    }

    private function queueName(): string
    {
        return config('queue.exports_queue', 'exports');
    }

    private function isStale(MasterDatasetProcess $process, DatasetExport $record): bool
    {
        if ($record->status !== 'ready') {
            return false;
        }

        if (! $record->generated_at) {
            return true;
        }

        // Treat any process that has advanced beyond the exports stage as already
        // refreshed so we do not requeue unnecessarily.
        if ($process->status === MasterDatasetProcessStatus::READY) {
            return false;
        }

        if ($process->status === MasterDatasetProcessStatus::FAILED) {
            return false;
        }

        return true;
    }

    public static function expectedBuckets(): array
    {
        return array_keys(self::EXPORT_BUCKETS);
    }

    private function recordReport(MasterDatasetProcess $process, Builder $query, string $reportType): void
    {
        $rowIds = (clone $query)->pluck('id')->map(function ($value) {
            return is_numeric($value) ? (int) $value : $value;
        })->toArray();

        $report = CallCenterReport::updateOrCreate([
            'master_dataset_process_id' => $process->id,
            'report_type' => $reportType,
        ], [
            'token' => $process->token,
            'dataset_month' => $process->dataset_month,
            'report_type' => $reportType,
            'row_count' => count($rowIds),
            'row_ids' => $rowIds,
        ]);

        if ($reportType === CallCenterReport::REPORT_TYPE_REGIONAL_BILLING) {
            $this->seedRegionalBillingAssignments($report, $rowIds);
        }
    }

    private function seedRegionalBillingAssignments(CallCenterReport $report, array $rowIds): void
    {
        $cleanRowIds = collect($rowIds)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($cleanRowIds)) {
            return;
        }

        $alreadySeeded = DB::table('call_center_row_assignments')
            ->where('call_center_report_id', $report->id)
            ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
            ->exists();

        if ($alreadySeeded) {
            return;
        }

        $now = now()->toDateTimeString();
        $batch = [];
        $batchSize = 1000;

        foreach ($cleanRowIds as $rowId) {
            $batch[] = [
                'call_center_report_id' => $report->id,
                'report_type' => CallCenterReport::REPORT_TYPE_REGIONAL_BILLING,
                'master_dataset_row_id' => $rowId,
                'assigned_user_id' => null,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $batchSize) {
                DB::table('call_center_row_assignments')->insertOrIgnore($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('call_center_row_assignments')->insertOrIgnore($batch);
        }
    }
}
