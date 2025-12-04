<?php

namespace App\Support;

use App\Jobs\BuildMasterDatasetExports;
use App\Models\DatasetExport;
use App\Models\MasterDatasetProcess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;
use App\Support\MasterDatasetProcessStatus;

class MasterDatasetExportCoordinator
{
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
    ) {
    }

    public function ensureFresh(MasterDatasetProcess $process, ?array $userContext = null): array
    {
        $diskName = $process->storage_disk ?: config('filesystems.default', 'local');
        $existing = DatasetExport::where('token', $process->token)->get()->keyBy('bucket');
        $context = $this->resolveUserContext($userContext);

        $shouldDispatch = false;
        $statuses = [];

        foreach (self::EXPORT_BUCKETS as $bucket => $meta) {
            $label = $this->viewService->bucketLabel($bucket);
            $filename = $this->viewService->bucketFilename($bucket);
            $path = $this->exportPath($process->token, $filename);

            $record = $existing->get($bucket);

            if (! $record) {
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

        $records = DatasetExport::where('token', $process->token)
            ->where('status', 'processing')
            ->get();

        foreach ($records as $record) {
            $bucket = $record->bucket;

            if (! array_key_exists($bucket, self::EXPORT_BUCKETS)) {
                $record->update(['status' => 'failed']);
                continue;
            }

            $label = $this->viewService->bucketLabel($bucket);
            $filename = $this->viewService->bucketFilename($bucket);
            $path = $this->exportPath($process->token, $filename);
            $query = $this->viewService->bucketQuery($freshProcess, $bucket);

            $record->fill([
                'label' => $label,
                'filename' => $filename,
                'file_path' => $path,
                'file_disk' => $diskName,
            ])->save();

            try {
                $this->exportService->storeToDisk($freshProcess, $label, $query, $disk, $path);

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
}
