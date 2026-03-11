<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExclusionUpload;
use App\Models\MasterDatasetProcess;
use App\Support\ChunkedUploadManager;
use App\Support\MasterDatasetExportCoordinator;
use App\Support\MasterDatasetWorkflowService;
use App\Support\SessionUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Http\UploadedFile as IlluminateUploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;
use ZipArchive;

class ExclusionUploadController extends Controller
{
    private const MAX_FILES = 3;
    private const MAX_WORKBOOKS = 3;
    private const EXCLUSION_MAX_BYTES = 20971520;
    private const CHUNK_BYTES = 2097152;

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
            'stagedUploads' => array_values(session('master.dataset.staged_exclusions', [])),
        ]);
    }

    public function startChunkUpload(Request $request, ChunkedUploadManager $chunks): JsonResponse|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Please upload the master dataset before managing exclusions.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        $existing = session('master.dataset.staged_exclusions', []);
        if (count($existing) >= self::MAX_FILES) {
            throw ValidationException::withMessages([
                'exclusions' => sprintf('You can upload a maximum of %d exclusion files at once.', self::MAX_FILES),
            ]);
        }

        if ($this->totalStagedWorkbookCount($existing) >= self::MAX_WORKBOOKS) {
            throw ValidationException::withMessages([
                'exclusions' => sprintf('%d Excel workbooks already received. Remove one before adding another file.', self::MAX_WORKBOOKS),
            ]);
        }

        $data = $request->validate([
            'file_name' => 'required|string',
            'file_size' => 'required|integer|min:1|max:' . self::EXCLUSION_MAX_BYTES,
            'mime_type' => 'nullable|string',
        ]);

        if (! Str::endsWith(strtolower($data['file_name']), '.zip')) {
            throw ValidationException::withMessages([
                'exclusions' => 'Only .zip archives are allowed for exclusions.',
            ]);
        }

        $upload = $chunks->start('exclusions', $data['file_name'], (int) $data['file_size'], $data['mime_type'] ?? null, [
            'process_id' => $process->id,
        ]);

        return response()->json([
            'status' => 'ok',
            'upload_token' => $upload['token'],
            'chunk_size' => self::CHUNK_BYTES,
        ]);
    }

    public function uploadChunk(Request $request, ChunkedUploadManager $chunks): JsonResponse
    {
        $data = $request->validate([
            'upload_token' => 'required|string',
            'chunk_index' => 'required|integer|min:0',
            'chunk' => 'required|file|max:20480',
        ]);

        $chunks->append('exclusions', $data['upload_token'], (int) $data['chunk_index'], $request->file('chunk'));

        return response()->json([
            'status' => 'ok',
            'chunk_index' => (int) $data['chunk_index'],
        ]);
    }

    public function finishChunkUpload(Request $request, ChunkedUploadManager $chunks): JsonResponse|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Please upload the master dataset before managing exclusions.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        $data = $request->validate([
            'upload_token' => 'required|string',
            'total_chunks' => 'required|integer|min:1',
        ]);

        try {
            $assembled = $chunks->assemble('exclusions', $data['upload_token'], (int) $data['total_chunks']);
            $metadata = $assembled['metadata'];
            $originalName = $metadata['original_name'];
            $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $originalName) ?: 'exclusion.zip';
            $relativePath = sprintf('exclusions/%d/staged/%s-%s', $process->id, $data['upload_token'], $safeName);
            $workbookCount = $this->countExcelWorkbooksInZip($assembled['absolute_path']);

            if ($workbookCount < 1) {
                throw ValidationException::withMessages([
                    'exclusions' => 'Uploaded ZIP must contain at least one Excel workbook (.xlsx, .xlsm, or .xls).',
                ]);
            }

            $stagedUploads = session('master.dataset.staged_exclusions', []);
            $currentWorkbookCount = $this->totalStagedWorkbookCount($stagedUploads);
            if (($currentWorkbookCount + $workbookCount) > self::MAX_WORKBOOKS) {
                throw ValidationException::withMessages([
                    'exclusions' => sprintf('%d Excel workbooks already received. This ZIP contains %d workbook(s), which exceeds the limit of %d total.', $currentWorkbookCount, $workbookCount, self::MAX_WORKBOOKS),
                ]);
            }

            Storage::disk('local')->put($relativePath, fopen($assembled['absolute_path'], 'rb'));

            $stagedUploads[$data['upload_token']] = [
                'id' => $data['upload_token'],
                'path' => $relativePath,
                'name' => $originalName,
                'mime' => $metadata['mime_type'] ?? 'application/zip',
                'size' => (int) ($metadata['file_size'] ?? 0),
                'excel_count' => $workbookCount,
            ];
            session()->put('master.dataset.staged_exclusions', $stagedUploads);

            $totalWorkbooks = $this->totalStagedWorkbookCount($stagedUploads);

            return response()->json([
                'status' => 'ok',
                'file' => $stagedUploads[$data['upload_token']],
                'totals' => [
                    'files' => count($stagedUploads),
                    'workbooks' => $totalWorkbooks,
                    'max_workbooks' => self::MAX_WORKBOOKS,
                ],
            ]);
        } finally {
            $chunks->delete('exclusions', $data['upload_token']);
        }
    }

    public function destroyStagedUpload(string $token): JsonResponse
    {
        $stagedUploads = session('master.dataset.staged_exclusions', []);
        $entry = $stagedUploads[$token] ?? null;

        if ($entry) {
            Storage::disk('local')->delete($entry['path'] ?? '');
            unset($stagedUploads[$token]);
            session()->put('master.dataset.staged_exclusions', $stagedUploads);
        }

        return response()->json([
            'status' => 'ok',
        ]);
    }

    private function totalStagedWorkbookCount(array $stagedUploads): int
    {
        $total = 0;
        foreach ($stagedUploads as $entry) {
            $count = (int) ($entry['excel_count'] ?? 1);
            $total += max($count, 1);
        }

        return $total;
    }

    private function countExcelWorkbooksInZip(string $zipPath): int
    {
        $zip = new ZipArchive();
        $opened = $zip->open($zipPath);

        if ($opened !== true) {
            throw ValidationException::withMessages([
                'exclusions' => 'Unable to read the uploaded ZIP archive. Please upload a valid ZIP file.',
            ]);
        }

        try {
            $count = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);
                if (str_ends_with($name, '/')) {
                    continue;
                }

                if (preg_match('/\.(xlsx|xlsm|xls)$/i', $name) === 1) {
                    $count++;
                }
            }

            return $count;
        } finally {
            $zip->close();
        }
    }

    public function store(Request $request, SessionUserResolver $resolver): RedirectResponse|JsonResponse
    {
        $process = $this->resolveProcessOrRedirect('Please upload the master dataset before managing exclusions.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        /** @var UploadedFile[] $files */
        $files = $request->file('exclusions', []);
        $stagedIds = array_values(array_filter((array) $request->input('staged_upload_ids', []), static fn ($value) => is_string($value) && $value !== ''));

        if (! is_array($files)) {
            $files = array_filter([$files]);
        }

        if (count($files) === 0 && count($stagedIds) === 0) {
            $message = ['exclusions' => 'Please add at least one exclusion file before submitting.'];

            if ($request->expectsJson()) {
                throw ValidationException::withMessages($message);
            }

            return back()
                ->withErrors($message)
                ->withInput();
        }

        if (count($files) > 0) {
            $request->validate([
                'exclusions' => 'required',
                'exclusions.*' => 'file|mimes:zip|max:20480',
            ]);
        }

        if (max(count($files), count($stagedIds)) > self::MAX_FILES) {
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

        if (count($stagedIds) > 0) {
            $stagedUploads = session('master.dataset.staged_exclusions', []);

            foreach ($stagedIds as $stagedId) {
                $entry = $stagedUploads[$stagedId] ?? null;
                if (! $entry || empty($entry['path']) || ! Storage::disk('local')->exists($entry['path'])) {
                    throw ValidationException::withMessages([
                        'exclusions' => 'One of the staged exclusion files is missing. Please upload it again.',
                    ]);
                }

                $storedFiles[] = [
                    'path' => $entry['path'],
                    'name' => $entry['name'] ?? basename($entry['path']),
                    'mime' => $entry['mime'] ?? null,
                ];
            }
        } else {
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

        if (count($stagedIds) > 0) {
            $stagedUploads = session('master.dataset.staged_exclusions', []);
            foreach ($stagedIds as $stagedId) {
                unset($stagedUploads[$stagedId]);
            }
            session()->put('master.dataset.staged_exclusions', $stagedUploads);
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
