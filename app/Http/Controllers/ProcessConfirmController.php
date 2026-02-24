<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAssignmentJob;
use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use App\Support\MasterDatasetAssignmentConfiguration;
use App\Support\MasterDatasetProcessStatus;
use App\Support\SessionUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ProcessConfirmController extends Controller
{
    private function computeFtthCount(int $processId): int
    {
        return MasterDatasetRow::query()
            ->where('process_id', $processId)
            ->where('excluded', false)
            ->where(function ($query) {
                $query
                    ->whereRaw('LOWER(slt_gl_sub_segment) IN (?, ?, ?)', ['retail', 'micro business', 'microbusiness'])
                    ->orWhereIn('customer_segment', ['11', '35'])
                    ->orWhereRaw('LOWER(customer_segment) IN (?, ?, ?)', ['retail', 'micro business', 'microbusiness']);
            })
            ->whereRaw('LOWER(medium) IN (?, ?)', ['ftth', 'fiber'])
            ->count();
    }

    public function create(Request $request, MasterDatasetAssignmentConfiguration $configuration): View|RedirectResponse
    {
        $processId = $request->session()->get('master.dataset.process_id');
        $process = MasterDatasetProcess::find($processId);

        if (! $process) {
            return redirect()->route('master.upload.create');
        }

        // Refresh to ensure we have the very latest status from DB
        $process->refresh();

        if ($process->status === MasterDatasetProcessStatus::READY) {
            return redirect()->route('process.assignments.index');
        }

        if ($process->status === MasterDatasetProcessStatus::FAILED) {
            return redirect()->route('master.upload.create')->withErrors([
                'upload' => $process->failure_reason ?: 'Dataset processing failed. Please upload again.',
            ]);
        }

        // If the user already confirmed and processing resumed, show the running page instead of looping on wait.
        $postConfirmStatuses = [
            MasterDatasetProcessStatus::VIP_CHECKING,
            MasterDatasetProcessStatus::VIP_READY,
            MasterDatasetProcessStatus::RETAIL_MICRO_CHECKING,
            MasterDatasetProcessStatus::RETAIL_MICRO_READY,
            MasterDatasetProcessStatus::EXPORTS_PENDING,
        ];

        if (in_array($process->status, $postConfirmStatuses, true)) {
            return redirect()->route('process.running.show');
        }

        if ($process->status !== MasterDatasetProcessStatus::WAITING_CONFIRMATION) {
            // While the exclusions job is still running, show an HTML waiting page.
            return view('process.confirm-wait', [
                'process' => $process,
            ]);
        }

        $ftthCount = $this->computeFtthCount($process->id);

        return view('process.confirm', [
            'process' => $process,
            'ftthCount' => $ftthCount,
            'assignmentConfig' => $configuration->toArray(),
        ]);
    }

    public function store(
        Request $request,
        SessionUserResolver $resolver,
        MasterDatasetAssignmentConfiguration $configuration
    ): RedirectResponse
    {
        $processId = $request->session()->get('master.dataset.process_id');
        $process = MasterDatasetProcess::find($processId);

        if (! $process || $process->status !== MasterDatasetProcessStatus::WAITING_CONFIRMATION) {
            return redirect()->route('master.upload.create');
        }

        $validated = $request->validate([
            'upper_range' => 'required|integer|min:0',
            'lower_range' => 'required|integer|min:0|lte:upper_range',
            'call_center_staff_quota' => 'required|integer|min:0',
            'call_center_quota' => 'required|integer|min:0',
            'staff_quota' => 'required|integer|min:0',
        ]);
        
        $userContext = $resolver->resolve($request);

        $defaults = $configuration->toArray();
        $normalize = static function (array $values): array {
            return [
                'upper_range' => (int) ($values['upper_range'] ?? 0),
                'lower_range' => (int) ($values['lower_range'] ?? 0),
                'call_center_staff_quota' => (int) ($values['call_center_staff_quota'] ?? 0),
                'call_center_quota' => (int) ($values['call_center_quota'] ?? 0),
                'staff_quota' => (int) ($values['staff_quota'] ?? 0),
            ];
        };

        $validatedNormalized = $normalize($validated);
        $defaultsNormalized = $normalize($defaults);
        $source = $validatedNormalized === $defaultsNormalized ? 'default' : 'manual';

        $ftthCount = $this->computeFtthCount($process->id);

        $process->update([
            'assignment_config_source' => $source,
            'assignment_config_overrides' => $validatedNormalized,
            'assignment_config_default_snapshot' => $defaultsNormalized,
            'assignment_config_ftth_count' => $ftthCount,
            'assignment_config_set_by_user_id' => $userContext['id'] ?? null,
            'assignment_config_set_at' => now(),
        ]);

        Log::info('Process confirmation submitted; dispatching assignment job.', [
            'process_id' => $process->id,
            'overrides' => $validatedNormalized,
            'source' => $source,
            'user_id' => $userContext['id'] ?? null,
            'user_name' => $userContext['name'] ?? null,
        ]);

        ProcessAssignmentJob::dispatch($process->id, $validatedNormalized, $userContext)
            ->onQueue('exports');

        return redirect()->route('process.running.show');
    }
}
