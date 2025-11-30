<?php

namespace App\Http\Controllers;

use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetExclusionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class ExclusionUploadController extends Controller
{
    private const MAX_FILES = 3;

    public function __construct(private MasterDatasetExclusionService $exclusionService)
    {
    }

    public function create(): View|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Please upload the master dataset before managing exclusions.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        return view('process.exclusions', [
            'maxFiles' => self::MAX_FILES,
            'process' => $process,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Please upload the master dataset before managing exclusions.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        $request->validate([
            'exclusions' => 'required',
            'exclusions.*' => 'file|mimes:zip|max:20480',
        ]);

        /** @var UploadedFile[] $files */
        $files = $request->file('exclusions', []);

        if (! is_array($files)) {
            $files = array_filter([$files]);
        }

        if (count($files) === 0) {
            return back()
                ->withErrors(['exclusions' => 'Please add at least one exclusion file before submitting.'])
                ->withInput();
        }

        if (count($files) > self::MAX_FILES) {
            return back()
                ->withErrors(['exclusions' => sprintf('You can upload a maximum of %d exclusion files at once.', self::MAX_FILES)])
                ->withInput();
        }

        try {
            $result = $this->exclusionService->apply($process, $files);
        } catch (Throwable $exception) {
            throw $exception instanceof \Illuminate\Validation\ValidationException ? $exception : \Illuminate\Validation\ValidationException::withMessages([
                'exclusions' => $exception->getMessage() ?: 'Unable to process the exclusion files.',
            ]);
        }

        if (($result['matched'] ?? 0) === 0) {
            return back()->with('status', 'No matching records were removed by the exclusion files.');
        }

        $message = sprintf(
            '%d records were marked as excluded. %d were already excluded.',
            (int) ($result['matched'] ?? 0),
            (int) ($result['already_excluded'] ?? 0)
        );

        return redirect()
            ->route('process.assignments.index')
            ->with('status', $message . ' Review the assignment overview for updated totals.');
    }

    private function resolveProcessOrRedirect(string $message): MasterDatasetProcess|RedirectResponse
    {
        $processId = session('master.dataset.process_id');

        if (! $processId) {
            return redirect()->route('master.upload.create')->withErrors([
                'upload' => $message,
            ]);
        }

        $process = MasterDatasetProcess::find($processId);

        if (! $process) {
            session()->forget('master.dataset.process_id');

            return redirect()->route('master.upload.create')->withErrors([
                'upload' => $message,
            ]);
        }

        return $process;
    }
}
