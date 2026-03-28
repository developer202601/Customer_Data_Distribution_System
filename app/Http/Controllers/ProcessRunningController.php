<?php

namespace App\Http\Controllers;

use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetProcessStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProcessRunningController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $processId = $request->session()->get('master.dataset.process_id');

        if (! $processId) {
            return redirect()->route('master.upload.create');
        }

        $process = MasterDatasetProcess::find($processId);

        if (! $process) {
            $request->session()->forget('master.dataset.process_id');
            return redirect()->route('master.upload.create');
        }

        $process->refresh();
        if ($process->status === MasterDatasetProcessStatus::READY) {
            return redirect()->route('process.assignments.index');
        }

        if ($process->status === MasterDatasetProcessStatus::EXPORTS_PENDING) {
            return redirect()->route('process.assignments.index');
        }

        if ($process->status === MasterDatasetProcessStatus::WAITING_CONFIRMATION) {
            return redirect()->route('process.confirm.create');
        }

        if ($process->status === MasterDatasetProcessStatus::FAILED) {
            return redirect()->route('master.upload.create')->withErrors([
                'upload' => $process->failure_reason ?: 'Dataset processing failed. Please upload again.',
            ]);
        }

        return view('process.running', [
            'process' => $process,
        ]);
    }
}
