<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExclusionUpload;
use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetExportCoordinator;
use App\Support\MasterDatasetWorkflowService;
use App\Support\SessionUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class ExclusionUploadController extends Controller
{
    private const MAX_FILES = 3;

    public function __construct(
        private MasterDatasetWorkflowService $workflowService,
        private MasterDatasetExportCoordinator $exportCoordinator
    )
    {
    }

    public function create(Request $request): View|RedirectResponse|JsonResponse
    {
        $process = $this->resolveProcessOrRedirect('Please upload the master dataset before managing exclusions.');

        if ($process instanceof RedirectResponse) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'missing-process',
                    'message' => 'Please upload the master dataset before managing exclusions.',
                    'redirect_url' => route('master.upload.create'),
                ], 409);
            }

            return $process;
        }

        return view('process.exclusions', [
            'maxFiles' => self::MAX_FILES,
            'process' => $process,
        ]);
    }

    public function store(Request $request, SessionUserResolver $resolver): RedirectResponse|JsonResponse
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
            $message = ['exclusions' => 'Please add at least one exclusion file before submitting.'];

            if ($request->expectsJson()) {
                throw ValidationException::withMessages($message);
            }

            return back()
                ->withErrors($message)
                ->withInput();
        }

        if (count($files) > self::MAX_FILES) {
            $message = ['exclusions' => sprintf('You can upload a maximum of %d exclusion files at once.', self::MAX_FILES)];

            if ($request->expectsJson()) {
                throw ValidationException::withMessages($message);
            }

            return back()
                ->withErrors($message)
                ->withInput();
        }

        $userContext = $resolver->resolve($request);

        // Release the PHP session lock so concurrent polling requests can read
        // the process id while the queued job is running.
        $sessionId = $request->session()->getId();
        $request->session()->save();
        if (function_exists('session_write_close')) {
            \session_write_close();
        }

        $storedFiles = [];
        $directory = sprintf('exclusions/%s/%s', $process->id, now()->format('YmdHis'));

        foreach ($files as $file) {
            $originalName = $file->getClientOriginalName();
            $storedName = uniqid('exclusion_', true) . '-' . $originalName;
            $path = Storage::disk('local')->putFileAs($directory, $file, $storedName);

            if ($path) {
                $storedFiles[] = [
                    'path' => $path,
                    'name' => $originalName,
                    'mime' => $file->getClientMimeType(),
                ];
            }
        }

        if (empty($storedFiles)) {
            throw ValidationException::withMessages([
                'exclusions' => 'Unable to store the uploaded exclusion files.',
            ]);
        }

        try {
            ProcessExclusionUpload::dispatch($process->id, $storedFiles, $userContext)
                ->onQueue('exports');
        } catch (ValidationException $exception) {
            foreach ($storedFiles as $file) {
                Storage::disk('local')->delete($file['path']);
            }

            if ($request->expectsJson()) {
                throw $exception;
            }

            return back()
                ->withErrors($exception->errors())
                ->withInput();
        } catch (Throwable $exception) {
            foreach ($storedFiles as $file) {
                Storage::disk('local')->delete($file['path']);
            }

            $wrapped = ValidationException::withMessages([
                'exclusions' => $exception->getMessage() ?: 'Unable to queue exclusion processing.',
            ]);

            if ($request->expectsJson()) {
                throw $wrapped;
            }

            throw $wrapped;
        }

        $message = 'Exclusion files were queued for processing. Monitor the loader for live updates.';

        if ($request->expectsJson()) {
            session()->flash('status', $message);

            return response()->json([
                'status' => 'ok',
                'message' => $message,
                'redirect_url' => route('process.confirm.create'),
            ]);
        }

        if (! Session::isStarted()) {
            if ($sessionId) {
                Session::setId($sessionId);
            }
            Session::start();
        }

        return redirect()
            ->route('process.confirm.create')
            ->with('status', $message);
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
