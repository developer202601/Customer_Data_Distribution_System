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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ExclusionUploadController extends Controller
{
    private const MAX_FILES = 3;
    private const MAX_WORKBOOKS = 3;
    private const EXCLUSION_MAX_BYTES = 20971520;
    private const CHUNK_BYTES = 2097152;
    private const PROGRESS_CACHE_PREFIX = 'process:exclusion:upload:';

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

    public function progressStream(Request $request, string $token)
    {
        $response = response()->stream(function () use ($token) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('implicit_flush', '1');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            ob_implicit_flush(true);

            $lastPayload = null;
            $start = time();
            $timeoutSeconds = 600;

            while ((time() - $start) < $timeoutSeconds) {
                [$payload, $statusCode] = $this->buildProgressPayload($token);
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
                if (in_array($status, ['ready', 'failed', 'canceled'], true)) {
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

    public function progress(string $token): JsonResponse
    {
        [$payload, $statusCode] = $this->buildProgressPayload($token);

        return response()->json($payload, $statusCode);
    }

    public function startChunkUpload(Request $request): JsonResponse
    {
        Log::warning('Exclusion chunk start called while chunk flow is disabled', [
            'process_id' => session('master.dataset.process_id'),
            'file_name' => (string) $request->input('file_name', ''),
            'file_size' => (int) $request->input('file_size', 0),
        ]);

        return response()->json([
            'status' => 'unsupported',
            'message' => 'Chunk upload is disabled. Refresh the page and retry.',
        ], 410);
    }

    public function uploadChunk(Request $request): JsonResponse
    {
        Log::warning('Exclusion chunk part called while chunk flow is disabled', [
            'process_id' => session('master.dataset.process_id'),
            'upload_token' => (string) $request->input('upload_token', ''),
            'chunk_index' => (int) $request->input('chunk_index', -1),
        ]);

        return response()->json([
            'status' => 'unsupported',
            'message' => 'Chunk upload is disabled. Refresh the page and retry.',
        ], 410);
    }

    public function finishChunkUpload(Request $request): JsonResponse
    {
        Log::warning('Exclusion chunk finish called while chunk flow is disabled', [
            'process_id' => session('master.dataset.process_id'),
            'upload_token' => (string) $request->input('upload_token', ''),
        ]);

        return response()->json([
            'status' => 'unsupported',
            'message' => 'Chunk upload is disabled. Refresh the page and retry.',
        ], 410);
    }

    public function uploadSingle(Request $request): JsonResponse|RedirectResponse
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
            'exclusion' => 'required|file|mimes:xlsx|max:20480',
        ]);

        /** @var UploadedFile $file */
        $file = $data['exclusion'];
        $originalName = $file->getClientOriginalName();
        $size = (int) $file->getSize();
        $mime = (string) ($file->getClientMimeType() ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        Log::info('Exclusion single upload request received', [
            'process_id' => $process->id,
            'file_name' => $originalName,
            'file_size' => $size,
            'mime_type' => $mime,
        ]);

        $uploadToken = (string) Str::uuid();
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $originalName) ?: 'exclusion.xlsx';
        $directory = sprintf('exclusions/%d/staged', $process->id);
        $storedName = sprintf('%s-%s', $uploadToken, $safeName);

        $path = Storage::disk('local')->putFileAs($directory, $file, $storedName);

    // Save latest_exclusion_token to process
    $process->latest_exclusion_token = $uploadToken;
    $process->save();

        if (! $path) {
            throw ValidationException::withMessages([
                'exclusions' => 'Unable to store the uploaded exclusion file.',
            ]);
        }

        $stagedUploads = session('master.dataset.staged_exclusions', []);
        $stagedUploads[$uploadToken] = [
            'id' => $uploadToken,
            'path' => $path,
            'name' => $originalName,
            'mime' => $mime,
            'size' => $size,
            'excel_count' => 1,
        ];
        session()->put('master.dataset.staged_exclusions', $stagedUploads);

        $cacheKey = self::PROGRESS_CACHE_PREFIX . $uploadToken;
        Cache::put($cacheKey, [
            'status' => 'ready',
            'progress' => 100,
            'message' => 'Staged (validation deferred until Apply exclusions).',
            'processed_rows' => 0,
            'total_rows' => 0,
            'stage' => 'staged',
            'started_at' => time(),
            'last_updated_at' => now()->toIso8601String(),
            'errors' => [],
            'file_name' => $originalName,
        ], now()->addMinutes(30));

        Log::info('Exclusion single upload staged (validation deferred)', [
            'process_id' => $process->id,
            'upload_token' => $uploadToken,
            'path' => $path,
        ]);

        $totalWorkbooks = $this->totalStagedWorkbookCount($stagedUploads);

        return response()->json([
            'status' => 'ok',
            'file' => $stagedUploads[$uploadToken],
            'totals' => [
                'files' => count($stagedUploads),
                'workbooks' => $totalWorkbooks,
                'max_workbooks' => self::MAX_WORKBOOKS,
            ],
        ]);
    }

    public function destroyStagedUpload(string $token): JsonResponse
    {
        $stagedUploads = session('master.dataset.staged_exclusions', []);
        $entry = $stagedUploads[$token] ?? null;

        if ($entry) {
            Storage::disk('local')->delete((string) ($entry['path'] ?? ''));
            unset($stagedUploads[$token]);
            session()->put('master.dataset.staged_exclusions', $stagedUploads);
        }

        Cache::forget(self::PROGRESS_CACHE_PREFIX . $token);

        return response()->json([
            'status' => 'ok',
        ]);
    }

    private function buildProgressPayload(string $token): array
    {
        $state = Cache::get(self::PROGRESS_CACHE_PREFIX . $token);

        if (! $state) {
            return [[
                'status' => 'not-found',
                'progress' => 0,
                'message' => 'No progress available for this exclusion upload.',
            ], 404];
        }

        $etaSeconds = null;
        if (! empty($state['processed_rows']) && ! empty($state['total_rows']) && ! empty($state['started_at'])) {
            $elapsed = time() - (int) $state['started_at'];
            $rate = $state['processed_rows'] / max($elapsed, 1);
            if ($rate > 0) {
                $remaining = $state['total_rows'] - $state['processed_rows'];
                $etaSeconds = (int) ($remaining / $rate);
            }
        }

        $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
        $primaryError = (string) ($state['error'] ?? '');
        if ($primaryError === '' && ! empty($errors)) {
            $primaryError = (string) ($errors[0] ?? '');
        }

        $message = (string) ($state['message'] ?? 'Validating…');
        if (($state['status'] ?? null) === 'failed' && $primaryError !== '' && stripos($message, $primaryError) === false) {
            $message .= ': ' . $primaryError;
        }

        return [[
            'status' => $state['status'] ?? (($state['progress'] ?? 0) >= 100 ? 'ready' : 'processing'),
            'progress' => $state['progress'] ?? 0,
            'message' => $message,
            'processed_rows' => $state['processed_rows'] ?? 0,
            'total_rows' => $state['total_rows'] ?? 0,
            'eta_seconds' => $etaSeconds,
            'stage' => $state['stage'] ?? null,
            'started_at' => $state['started_at'] ?? null,
            'last_updated_at' => $state['last_updated_at'] ?? null,
            'error' => $primaryError !== '' ? $primaryError : null,
            'errors' => $errors,
            'file_name' => $state['file_name'] ?? null,
        ], 200];
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
                'exclusions.*' => 'file|mimes:xlsx|max:20480',
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
