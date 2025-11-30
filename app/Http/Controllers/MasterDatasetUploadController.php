<?php

namespace App\Http\Controllers;

use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MasterDatasetUploadController extends Controller
{
    public function create(Request $request): View
    {
        $process = null;
        $processId = $request->session()->get('master.dataset.process_id');

        if ($processId) {
            $process = MasterDatasetProcess::find($processId);
        }

        return view('process.master-upload', [
            'process' => $process,
        ]);
    }

    public function store(Request $request, MasterDatasetImporter $importer): RedirectResponse
    {
        $data = $request->validate([
            'upload' => 'required|file|mimes:zip|max:51200',
        ]);

        try {
            $user = $request->user();
            $userContext = [
                'id' => $user?->getAuthIdentifier(),
                'name' => $user?->username ?? $user?->name ?? $user?->email ?? null,
            ];

            $process = $importer->import($request->file('upload'), $userContext);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'upload' => $exception->getMessage() ?: 'Unable to import the uploaded archive.',
            ]);
        }

        $request->session()->put('master.dataset.process_id', $process->id);

        return redirect()
            ->route('process.exclusions.create')
            ->with('status', 'Master dataset uploaded successfully. Continue with exclusions.');
    }
}
