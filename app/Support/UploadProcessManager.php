<?php

namespace App\Support;

use Illuminate\Bus\Batch;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class UploadProcessManager
{
    public static function cancel(string $token, ?string $batchId = null, ?string $reason = null, string $status = 'cancelled'): void
    {
        self::purgeQueuedJobs($token);
        self::cancelBatch($batchId);
        self::cleanupFiles($token);

        $payload = [
            'status' => $status,
            'progress' => $status === 'failed' ? 100 : 0,
            'message' => $reason ?? ($status === 'failed' ? 'Processing failed.' : 'Processing cancelled.'),
            'error' => $status === 'failed' ? ($reason ?? 'Processing failed.') : null,
        ];

        $state = Cache::get(self::cacheKey($token), []);
        Cache::put(self::cacheKey($token), array_merge($state, $payload), now()->addMinutes(5));
    }

    public static function purgeQueuedJobs(string $token): void
    {
        $queueName = config('queue.default');
        $queue = Queue::connection($queueName);

        if (! $queue instanceof DatabaseQueue) {
            return;
        }

        $queueConfig = config("queue.connections.{$queueName}", []);
        $connections = config('database.connections', []);

        $connectionName = $queueConfig['connection'] ?? $queue->getConnectionName() ?? config('database.default');

        if (! $connectionName || ! array_key_exists($connectionName, $connections)) {
            $connectionName = config('database.default');
        }

        $table = $queueConfig['table'] ?? 'jobs';

        DB::connection($connectionName)
            ->table($table)
            ->where('payload', 'like', '%' . $token . '%')
            ->delete();
    }

    public static function cancelBatch(?string $batchId): void
    {
        if (! $batchId) {
            return;
        }

        $batch = Bus::findBatch($batchId);

        if ($batch instanceof Batch) {
            $batch->cancel();
        }
    }

    public static function cleanupFiles(string $token): void
    {
        $disk = Storage::disk(config('filesystems.default', 'local'));
        $disk->deleteDirectory('processed/' . $token);
        $disk->delete('uploads/' . $token . '.xlsx');
    }

    private static function cacheKey(string $token): string
    {
        return 'process:upload:' . $token;
    }
}
