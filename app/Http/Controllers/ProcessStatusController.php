<?php

namespace App\Http\Controllers;

use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetProcessStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcessStatusController extends Controller
{
    private const FAILURE_CACHE_PREFIX = 'master.dataset.failure.user.';
    private const PROCESS_UPLOAD_CACHE_PREFIX = 'process:upload:';
    private const EXCLUSION_UPLOAD_CACHE_PREFIX = 'process:exclusion:upload:';
    private const QUEUE_STATUS_CACHE_PREFIX = 'master:ingest:queue:';

    public function show(Request $request): JsonResponse|RedirectResponse
    {
        [$payload, $statusCode] = $this->buildStatusPayload($request);

        if (($payload['status'] ?? null) === 'waiting_confirmation' && ! $request->expectsJson()) {
            return redirect()->route('process.confirm.create');
        }

        return response()->json($payload, $statusCode);
    }

    public function stream(Request $request): StreamedResponse
    {
        // Release the session lock so other requests can proceed while streaming.
        $request->session()->save();
        if (function_exists('session_write_close')) {
            session_write_close();
        }

        $response = response()->stream(function () use ($request) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);

            $lastPayload = null;
            $start = time();
            $timeoutSeconds = 900;

            while ((time() - $start) < $timeoutSeconds) {
                [$payload, $statusCode] = $this->buildStatusPayload($request, false);

                $json = json_encode($payload);
                if ($json && $json !== $lastPayload) {
                    echo "data: {$json}\n\n";
                    $lastPayload = $json;
                } else {
                    echo ": heartbeat\n\n";
                }

                if (connection_aborted()) {
                    break;
                }

                $status = $payload['status'] ?? null;
                if (in_array($status, ['ready', 'failed', 'waiting_confirmation'], true)) {
                    break;
                }

                usleep(500000);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);

        return $response;
    }

    private function buildStatusPayload(Request $request, bool $allowSessionWrite = true): array
    {
        $sessionUserId = (int) data_get($request->session()->get('user'), 'id', 0);
        $failurePayload = $sessionUserId > 0
            ? Cache::get(self::FAILURE_CACHE_PREFIX . $sessionUserId)
            : null;

        // If a failure was cached for this user, always surface it first.
        // This prevents fallback to an older "ready" process after failed jobs are purged.
        if (is_array($failurePayload)) {
            $messages = array_merge(
                $failurePayload['master_errors'] ?? [],
                $failurePayload['exclusion_errors'] ?? [],
                $failurePayload['general_errors'] ?? []
            );

            $request->session()->forget('master.dataset.process_id');
            $request->session()->forget('master.dataset.staged_exclusions');

            return [[
                'status' => MasterDatasetProcessStatus::FAILED,
                'progress' => 100,
                'message' => (string) (count($messages) ? $messages[0] : 'Processing failed.'),
                'redirect_url' => route('master.upload.create'),
            ], 200];
        }

        $process = $this->resolveTrackedProcess($request);

        if (! $process) {
            Log::warning('STATUS CONTROLLER: No process_id in session');
            return [[
                'status' => null,
                'stages' => [],
                'active_stage' => null,
                'overall_progress' => 0,
                'message' => 'No active dataset process.',
            ], 404];
        }

        $processId = $process->id;

        $process->refresh();
        $freshStatus = $process->status;
        $queueState = $this->resolveQueueState($process->id);

        if ($freshStatus === MasterDatasetProcessStatus::WAITING_CONFIRMATION) {
            // Try to show last known row progress if available
            $token = (string) ($process->token ?? '');
            [$processedRows, $totalRows] = $this->resolveRowProgress($token);

            $message = 'Waiting for configuration confirmation...';
            if ($processedRows !== null && $totalRows !== null && $totalRows > 0) {
                $message = sprintf('Preparing confirmation... (%d/%d rows checked)', $processedRows, $totalRows);
            }

            return [[
                'status' => 'waiting_confirmation',
                'progress' => 50,
                'message' => $message,
                'redirect_url' => route('process.confirm.create'),
                'last_updated_at' => $process->updated_at?->toIso8601String(),
                'processed_rows' => $processedRows,
                'total_rows' => $totalRows,
            ], 200];
        }

        if (! $freshStatus) {
            Log::warning("STATUS CONTROLLER: Process {$processId} has no status");
            return [[
                'status' => null,
            ], 404];
        }

        if ($freshStatus === MasterDatasetProcessStatus::FAILED) {
            return [[
                'status' => $freshStatus,
                'progress' => 100,
                'message' => (string) ($process->failure_reason ?: MasterDatasetProcessStatus::getFriendlyName($freshStatus)),
                'redirect_url' => route('master.upload.create'),
                'last_updated_at' => $process->updated_at?->toIso8601String(),
            ], 200];
        }

        $percentage = MasterDatasetProcessStatus::getProgressPercentage($freshStatus);

        $progressKey = 'process.progress.' . $processId . '.last';
        $last = (int) $request->session()->get($progressKey, 0);

        if ($freshStatus === MasterDatasetProcessStatus::AWAITING_EXCLUSIONS) {
            $last = 0;
            $percentage = 0;
            if ($allowSessionWrite) {
                $request->session()->put($progressKey, 0);
            }
        } elseif ($percentage < $last) {
            $percentage = $last;
        } else {
            if ($allowSessionWrite) {
                $request->session()->put($progressKey, $percentage);
            }
        }

        Log::info("STATUS RESPONSE: Process {$processId} => {$freshStatus} => {$percentage}% (last={$last})");

        $message = MasterDatasetProcessStatus::getFriendlyName($freshStatus);
        if ($freshStatus === MasterDatasetProcessStatus::FAILED && ! empty($process->failure_reason)) {
            $message = (string) $process->failure_reason;
        }

        if ($freshStatus === MasterDatasetProcessStatus::VALIDATING) {
            $message = 'Validating master dataset…';
        }

        $queuePosition = null;
        $queueEtaSeconds = null;
        if (
            $queueState
            && in_array($freshStatus, [
                MasterDatasetProcessStatus::AWAITING_EXCLUSIONS,
                MasterDatasetProcessStatus::VALIDATING,
            ], true)
        ) {
            $queuePosition = is_numeric($queueState['position'] ?? null) ? (int) $queueState['position'] : null;
            $queueEtaSeconds = is_numeric($queueState['eta_seconds'] ?? null) ? (int) $queueState['eta_seconds'] : null;

            $queueMessage = 'Queued for processing';
            if ($queuePosition !== null) {
                $queueMessage .= ' (position ' . $queuePosition . ')';
            }

            if ($queueEtaSeconds !== null && $queueEtaSeconds > 0) {
                $queueMessage .= '. Estimated wait ' . $this->formatEta($queueEtaSeconds) . '.';
            }

            $message = $queueMessage;
        }

        $processedRows = null;
        $totalRows = null;
        $token = (string) ($process->token ?? '');

        if ($freshStatus === MasterDatasetProcessStatus::PYTHON_RUNNING) {
            $pythonState = $this->readPythonStatus($process);
            if (is_array($pythonState)) {
                $pythonMessage = $pythonState['message'] ?? null;
                if (is_string($pythonMessage) && trim($pythonMessage) !== '') {
                    $message = trim($pythonMessage);
                }

                $progress = $pythonState['progress'] ?? null;
                if (is_array($progress)) {
                    $p = $progress['processed_rows'] ?? null;
                    $t = $progress['total_rows'] ?? null;
                    if (is_numeric($p) && is_numeric($t)) {
                        $processedRows = (int) $p;
                        $totalRows = (int) $t;
                    }
                }
            }
        }

        // Use stage-aware token selection:
        // - Master pipeline phases read progress from master token
        // - Exclusion application phase reads progress from latest exclusion token
        $rowAwareStatuses = [
            MasterDatasetProcessStatus::VALIDATING,
            MasterDatasetProcessStatus::PYTHON_RUNNING,
            MasterDatasetProcessStatus::RECORDS_INSERTING,
            MasterDatasetProcessStatus::EXCLUSIONS_APPLYING,
        ];
        $debugInfo = [];
        if ($freshStatus === MasterDatasetProcessStatus::EXCLUSIONS_APPLYING && !empty($process->latest_exclusion_token)) {
            $token = (string) $process->latest_exclusion_token;
        }
        $debugInfo['used_token'] = $token;

        $cacheState = null;
        $startedAt = null;
        if ($token !== '') {
            $cacheState = Cache::get(self::PROCESS_UPLOAD_CACHE_PREFIX . $token);
            if (!is_array($cacheState)) {
                $cacheState = Cache::get(self::EXCLUSION_UPLOAD_CACHE_PREFIX . $token);
            }

            if (is_array($cacheState)) {
                $startedAt = $cacheState['started_at'] ?? null;
            }

            if (
                $freshStatus === MasterDatasetProcessStatus::VALIDATING
                && is_array($cacheState)
                && isset($cacheState['message'])
                && is_string($cacheState['message'])
                && trim($cacheState['message']) !== ''
            ) {
                $message = trim($cacheState['message']);
            }

            // For python_running we prefer python-status.json above; otherwise use cache.
            if (! ($freshStatus === MasterDatasetProcessStatus::PYTHON_RUNNING && $processedRows !== null && $totalRows !== null)) {
                [$processedRows, $totalRows] = $this->resolveRowProgress($token);
            }

            if ($processedRows !== null && $totalRows !== null && $totalRows > 0) {
                $currentMessage = $message;

                if (in_array($freshStatus, $rowAwareStatuses, true)) {
                    $suffix = 'rows checked...';
                    if ($freshStatus === MasterDatasetProcessStatus::PYTHON_RUNNING) {
                        $suffix = 'rows processed...';
                    }

                    $message = sprintf('%s (%d/%d %s)', $currentMessage, $processedRows, $totalRows, $suffix);
                }
            }
        }

        if (in_array($freshStatus, $rowAwareStatuses, true)) {
            $processedRows = $processedRows ?? 0;
            $totalRows = $totalRows ?? 0;
        }

        $debugInfo['cache_state'] = $cacheState;
        Log::info('ProcessStatusController DEBUG', $debugInfo);

        $redirectUrl = null;
        if ($freshStatus === MasterDatasetProcessStatus::READY) {
            $redirectUrl = route('process.assignments.index');
        }

        return [[
            'status' => $freshStatus,
            'message' => $message,
            'progress' => $percentage,
            'redirect_url' => $redirectUrl,
            'processed_rows' => $processedRows,
            'total_rows' => $totalRows,
            'queue_position' => $queuePosition,
            'queue_eta_seconds' => $queueEtaSeconds,
            'started_at' => $startedAt,
            'last_updated_at' => $process->updated_at?->toIso8601String(),
        ], 200];
    }

    private function resolveQueueState(int $processId): ?array
    {
        $state = Cache::get(self::QUEUE_STATUS_CACHE_PREFIX . $processId);

        return is_array($state) ? $state : null;
    }

    private function formatEta(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $minutes = (int) floor($seconds / 60);
        $remaining = $seconds % 60;

        if ($minutes < 60) {
            return sprintf('%dm %02ds', $minutes, $remaining);
        }

        $hours = (int) floor($minutes / 60);
        $minutes = $minutes % 60;

        return sprintf('%dh %02dm', $hours, $minutes);
    }

    private function readPythonStatus(MasterDatasetProcess $process): ?array
    {
        $path = (string) ($process->python_status_path ?? '');
        if ($path === '') {
            return null;
        }

        $diskName = (string) ($process->storage_disk ?: config('filesystems.default', 'local'));

        try {
            if (! Storage::disk($diskName)->exists($path)) {
                return null;
            }

            $raw = Storage::disk($diskName)->get($path);
            $decoded = json_decode((string) $raw, true);

            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resolve row progress from the master-upload cache first, then fall back
     * to exclusion-validation cache used during exclusion workbook validation.
     *
     * @return array{0:int|null,1:int|null}
     */
    private function resolveRowProgress(string $token): array
    {
        if ($token === '') {
            return [null, null];
        }

        $state = Cache::get(self::PROCESS_UPLOAD_CACHE_PREFIX . $token);
        if (! is_array($state)) {
            $state = Cache::get(self::EXCLUSION_UPLOAD_CACHE_PREFIX . $token);
        }

        if (! is_array($state)) {
            return [null, null];
        }

        $processedRows = isset($state['processed_rows']) ? (int) $state['processed_rows'] : null;
        $totalRows = isset($state['total_rows']) ? (int) $state['total_rows'] : null;

        return [$processedRows, $totalRows];
    }

    private function resolveTrackedProcess(Request $request): ?MasterDatasetProcess
    {
        $sessionProcessId = (int) $request->session()->get('master.dataset.process_id');
        $sessionProcess = $sessionProcessId > 0
            ? MasterDatasetProcess::find($sessionProcessId)
            : null;

        $sessionUserId = (int) data_get($request->session()->get('user'), 'id', 0);
        if ($sessionUserId <= 0) {
            return $sessionProcess;
        }

        $latest = MasterDatasetProcess::query()
            ->where('user_id', $sessionUserId)
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            return $sessionProcess;
        }

        if (! $sessionProcess || $latest->id > $sessionProcess->id) {
            $request->session()->put('master.dataset.process_id', $latest->id);
            return $latest;
        }

        return $sessionProcess;
    }
}
