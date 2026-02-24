<?php

namespace App\Http\Controllers;

use App\Models\MasterDatasetProcess;
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

        return view('process.running', [
            'process' => $process,
        ]);
    }
}
