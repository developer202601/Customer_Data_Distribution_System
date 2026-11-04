<?php

namespace App\Jobs;

use App\Models\SmsReminderUpload;
use App\Support\SmsReminderIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessSmsReminderUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;
    public int $tries = 1;

    private const PROGRESS_CACHE_PREFIX = 'process:sms_reminder:upload:';

    public function __construct(
        private string $token,
        private string $storedPath,
        private string $originalName,
        private int $userId,
    ) {
        $this->queue = config('queue.exports_queue', 'exports');
    }

    public function handle(SmsReminderIngestionService $service): void
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 0);

        $upload = SmsReminderUpload::where('token', $this->token)->first();
        if (! $upload) {
            Log::warning("SMS reminder upload {$this->token} no longer exists when running job.");
            return;
        }

        try {
            $service->run($this->token, $this->storedPath, $this->originalName, $this->userId);
        } catch (Throwable $e) {
            Log::error('SMS reminder processing failed: ' . $e->getMessage(), [
                'token' => $this->token,
                'exception' => $e,
            ]);

            Cache::put(self::PROGRESS_CACHE_PREFIX . $this->token, [
                'status' => 'failed',
                'progress' => 100,
                'message' => 'SMS reminder processing failed.',
                'error' => $e->getMessage(),
                'finished_at' => time(),
                'original_filename' => $this->originalName,
            ], now()->addMinutes(60));

            SmsReminderUpload::where('token', $this->token)->update([
                'status' => 'failed',
                'progress' => 100,
                'message' => 'SMS reminder processing failed.',
                'error' => $e->getMessage(),
                'finished_at' => time(),
            ]);

            if (Storage::disk('local')->exists($this->storedPath)) {
                Storage::disk('local')->delete($this->storedPath);
            }

            throw $e;
        }
    }
}
