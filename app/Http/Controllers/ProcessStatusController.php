<?php

namespace App\Http\Controllers;

use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetProcessStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProcessStatusController extends Controller
{
    private const FAILURE_CACHE_PREFIX = 'master.dataset.failure.user.';

    public function show(Request $request): JsonResponse|RedirectResponse
    {
        $processId = $request->session()->get('master.dataset.process_id');

        if (! $processId) {
            \Log::warning("STATUS CONTROLLER: No process_id in session");
            return response()->json([
                'status' => null,
                'stages' => [],
                'active_stage' => null,
                'overall_progress' => 0,
                'message' => 'No active dataset process.',
            ], 404);
        }

        // Use the regular mysql connection with fresh() to ensure we get the latest data
        $process = MasterDatasetProcess::find($processId);
        
        if (! $process) {
            $userId = (int) data_get($request->session()->get('user'), 'id', 0);
            $failurePayload = $userId > 0 ? Cache::get(self::FAILURE_CACHE_PREFIX . $userId) : null;

            if (is_array($failurePayload)) {
                $messages = array_merge(
                    $failurePayload['master_errors'] ?? [],
                    $failurePayload['exclusion_errors'] ?? [],
                    $failurePayload['general_errors'] ?? []
                );

                $request->session()->forget('master.dataset.process_id');
                $request->session()->forget('master.dataset.staged_exclusions');

                return response()->json([
                    'status' => MasterDatasetProcessStatus::FAILED,
                    'progress' => 100,
                    'message' => (string) (count($messages) ? $messages[0] : 'Processing failed.'),
                    'redirect_url' => route('master.upload.create'),
                ]);
            }

            \Log::warning("STATUS CONTROLLER: Process {$processId} not found");
            return response()->json([
                'status' => null,
            ], 404);
        }

        // Force refresh from database to avoid stale model state
        $process->refresh();
        $freshStatus = $process->status;

        if ($freshStatus === MasterDatasetProcessStatus::WAITING_CONFIRMATION) {
            if (! $request->expectsJson()) {
                return redirect()->route('process.confirm.create');
            }

            return response()->json([
                'status' => 'waiting_confirmation',
                'progress' => 50,
                'message' => 'Waiting for configuration confirmation...',
                'redirect_url' => route('process.confirm.create'),
            ]);
        }

        if (! $freshStatus) {
            \Log::warning("STATUS CONTROLLER: Process {$processId} has no status");
            return response()->json([
                'status' => null,
            ], 404);
        }

        if ($freshStatus === MasterDatasetProcessStatus::FAILED) {
            return response()->json([
                'status' => $freshStatus,
                'progress' => 100,
                'message' => (string) ($process->failure_reason ?: MasterDatasetProcessStatus::getFriendlyName($freshStatus)),
            ]);
        }

        $percentage = MasterDatasetProcessStatus::getProgressPercentage($freshStatus);

        // Per-process monotonic guard (prevents a new process inheriting 100% from an older one)
        $progressKey = 'process.progress.' . $processId . '.last';
        $last = (int) $request->session()->get($progressKey, 0);

        // If we are at the initial status, force reset to 0
        if ($freshStatus === MasterDatasetProcessStatus::AWAITING_EXCLUSIONS) {
            $last = 0;
            $percentage = 0;
            $request->session()->put($progressKey, 0);
        } elseif ($percentage < $last) {
            $percentage = $last; // never regress
        } else {
            $request->session()->put($progressKey, $percentage);
        }

        \Log::info("STATUS RESPONSE: Process {$processId} => {$freshStatus} => {$percentage}% (last={$last})");

        $message = MasterDatasetProcessStatus::getFriendlyName($freshStatus);
        if ($freshStatus === MasterDatasetProcessStatus::FAILED && ! empty($process->failure_reason)) {
            $message = (string) $process->failure_reason;
        }

        // Return status with friendly name and progress percentage
        return response()->json([
            'status' => $freshStatus,
            'message' => $message,
            'progress' => $percentage,
        ]);
    }
}
