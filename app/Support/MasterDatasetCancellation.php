<?php

namespace App\Support;

use App\Models\MasterDatasetProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class MasterDatasetCancellation
{
    private const PROCESS_ABORT_CACHE_PREFIX = 'master.dataset.process.abort.';
    private const ABORT_FLAG_FILENAME = 'abort.flag';

    public static function abortKey(int $processId): string
    {
        return self::PROCESS_ABORT_CACHE_PREFIX . $processId;
    }

    public static function signalAbort(MasterDatasetProcess $process): void
    {
        $ttl = now()->addHours(6);

        Cache::put(self::abortKey((int) $process->id), true, $ttl);

        $tokens = array_values(array_unique(array_filter([
            (string) ($process->token ?? ''),
            (string) ($process->latest_exclusion_token ?? ''),
        ], static fn($value) => is_string($value) && trim($value) !== '')));

        foreach ($tokens as $token) {
            Cache::put('process:upload:' . $token . ':abort', true, $ttl);
            Cache::put('process:exclusion:upload:' . $token . ':abort', true, $ttl);
            self::writeAbortFlag($process, $token);
        }
    }

    public static function writeAbortFlag(MasterDatasetProcess $process, string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        $diskName = (string) ($process->storage_disk ?: config('filesystems.default', 'local'));
        $disk = Storage::disk($diskName);

        $path = sprintf('exports/%s/source/%s', $token, self::ABORT_FLAG_FILENAME);

        try {
            $disk->put($path, now()->toIso8601String());
        } catch (\Throwable) {
            // ignore: cancellation should still proceed even if we cannot write the flag
        }
    }

    public static function isAborted(MasterDatasetProcess $process): bool
    {
        if (($process->status ?? null) === MasterDatasetProcessStatus::CANCELED) {
            return true;
        }

        if ((bool) Cache::get(self::abortKey((int) $process->id), false)) {
            return true;
        }

        $token = trim((string) ($process->token ?? ''));
        if ($token !== '' && (bool) Cache::get('process:upload:' . $token . ':abort', false)) {
            return true;
        }

        $token = trim((string) ($process->latest_exclusion_token ?? ''));
        if ($token !== '' && (bool) Cache::get('process:upload:' . $token . ':abort', false)) {
            return true;
        }

        return false;
    }
}
