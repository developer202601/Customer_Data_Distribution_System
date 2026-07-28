<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessExclusionUpload;
use App\Jobs\ProcessMasterIngestion;
use App\Models\MasterDatasetProcess;
use App\Models\MasterUpload;
use App\Support\ChunkedUploadManager;
use App\Support\MasterDatasetAssignmentConfiguration;
use App\Support\MasterDatasetProcessStatus;
use App\Support\MasterDatasetWorkflowService;
use App\Support\SessionUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile as IlluminateUploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MasterDatasetUploadController extends Controller
{
    private const MASTER_MAX_BYTES = 72428800;
    private const CHUNK_BYTES = 2097152;
    private const STAGED_UPLOAD_SESSION_KEY = 'master.dataset.staged_upload';
    private const FAILURE_CACHE_PREFIX = 'master.dataset.failure.user.';
    private const HISTORY_CACHE_PREFIX = 'master.upload.history.';

    private function recordUploadHistory(string $token, int $userId, array $data): void
    {
        Cache::put(self::HISTORY_CACHE_PREFIX . $token, array_merge([
            'user_id' => $userId,
            'status' => 'processing',
            'progress' => 0,
            'message' => 'Uploading…',
            'processed_rows' => 0,
            'total_rows' => null,
            'file_size' => null,
            'mime_type' => null,
            'error' => null,
            'started_at' => time(),
            'finished_at' => null,
            'last_updated_at' => now()->toIso8601String(),
        ], $data), now()->addDays(30));
    }

    private function syncUploadHistoryToDb(string $token, array $overrides = []): void
    {
        $cached = Cache::get(self::HISTORY_CACHE_PREFIX . $token);
        if (!is_array($cached)) {
            return;
        }

        MasterUpload::updateOrCreate(
            ['token' => $token],
            array_merge($cached, $overrides)
        );
    }

    private function replaceCurrentSessionProcess(Request $request): void
    {
        $existingProcessId = (int) $request->session()->get('master.dataset.process_id', 0);
        if ($existingProcessId <= 0) {
            return;
        }

        $existing = MasterDatasetProcess::find($existingProcessId);
        if (! $existing) {
            $request->session()->forget('master.dataset.process_id');
            $request->session()->forget('master.dataset.staged_exclusions');
            return;
        }

        // Keep completed datasets for archive/reports, but clean up unfinished runs.
        if (! in_array((string) ($existing->status ?? ''), [
            MasterDatasetProcessStatus::READY,
            MasterDatasetProcessStatus::FAILED,
            MasterDatasetProcessStatus::CANCELED,
        ], true)) {
            $diskName = $existing->storage_disk ?: config('filesystems.default', 'local');
            $disk = Storage::disk($diskName);

            if ($existing->master_archive_path) {
                $disk->delete($existing->master_archive_path);
            }
            if ($existing->master_workbook_path) {
                $disk->delete($existing->master_workbook_path);
            }
            if ($existing->token) {
                $disk->deleteDirectory('exports/' . $existing->token);
                $disk->deleteDirectory('validation-reports/master/' . $existing->token);
            }

            $existing->delete();
        }

        $request->session()->forget('master.dataset.process_id');
        $request->session()->forget('master.dataset.staged_exclusions');
    }

    public function create(Request $request, MasterDatasetAssignmentConfiguration $configuration): View|RedirectResponse
    {
        $isAdmin = (bool) ($request->session()->get('user.is_admin') ?? false);
        if ($isAdmin) {
            return redirect()->route('process.assignments.reports')->with('status', 'File uploads are reserved for normal users.');
        }
        $process = null;
        $showProcessBanner = false;
        $failurePayload = null;
        $processId = $request->session()->get('master.dataset.process_id');

        if ($processId) {
            $process = MasterDatasetProcess::find($processId);
            if (! $process) {
                $request->session()->forget('master.dataset.process_id');
                $request->session()->forget('master.dataset.staged_exclusions');
            } else {
                $status = (string) ($process->status ?? '');

                if (in_array($status, [MasterDatasetProcessStatus::READY, MasterDatasetProcessStatus::FAILED, MasterDatasetProcessStatus::CANCELED], true)) {
                    // Completed/failed processes should not block a fresh upload flow.
                    $request->session()->forget('master.dataset.process_id');
                    $request->session()->forget('master.dataset.staged_exclusions');
                    $process = null;
                } else {
                    $showProcessBanner = true;
                }
            }
        }

        $userId = (int) data_get($request->session()->get('user'), 'id', 0);
        if ($userId > 0) {
            $failurePayload = Cache::pull(self::FAILURE_CACHE_PREFIX . $userId);
        }

        return view('process.master-upload', [
            'process' => $process,
            'showProcessBanner' => $showProcessBanner,
            'assignmentConfig' => $configuration->toArray(),
            'processFailurePayload' => $failurePayload,
        ]);
    }

    public function assignmentConfig(MasterDatasetAssignmentConfiguration $configuration): JsonResponse
    {
        return response()->json([
            'assignmentConfig' => $configuration->toArray(),
        ]);
    }

    public function startChunkUpload(Request $request, ChunkedUploadManager $chunks): JsonResponse
    {
        $data = $request->validate([
            'file_name' => 'required|string',
            'file_size' => 'required|integer|min:1|max:' . self::MASTER_MAX_BYTES,
            'mime_type' => 'nullable|string',
        ]);

        if (! Str::endsWith(strtolower($data['file_name']), '.xlsx')) {
            throw ValidationException::withMessages([
                'upload' => 'Please upload the master Excel workbook (.xlsx).',
            ]);
        }

        $upload = $chunks->start('master', $data['file_name'], (int) $data['file_size'], $data['mime_type'] ?? null);

        $userId = (int) data_get($request->session()->get('user'), 'id', 0);
        $this->recordUploadHistory($upload['token'], $userId, [
            'original_name' => $data['file_name'],
            'file_size' => (int) $data['file_size'],
            'mime_type' => $data['mime_type'] ?? null,
            'started_at' => time(),
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
            'chunk' => 'required|file|max:51200',
        ]);

        $chunks->append('master', $data['upload_token'], (int) $data['chunk_index'], $request->file('chunk'));

        return response()->json([
            'status' => 'ok',
            'chunk_index' => (int) $data['chunk_index'],
        ]);
    }

    public function finishChunkUpload(
        Request $request,
        ChunkedUploadManager $chunks
    ): JsonResponse {
        $data = $request->validate([
            'upload_token' => 'required|string',
            'total_chunks' => 'required|integer|min:1',
        ]);

        try {
            $assembled = $chunks->assemble('master', $data['upload_token'], (int) $data['total_chunks']);
            $metadata = $assembled['metadata'];

            $previousStaged = $request->session()->get(self::STAGED_UPLOAD_SESSION_KEY);
            if (is_array($previousStaged) && !empty($previousStaged['relative_path'])) {
                Storage::disk('local')->delete((string) $previousStaged['relative_path']);
            }

            $token = (string) Str::uuid();
            $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($metadata['original_name'] ?? 'master.xlsx'));
            $relativePath = 'master/staged/' . $token . '-' . ltrim((string) $safeName, '.');
            $disk = Storage::disk('local');
            $disk->makeDirectory('master/staged');

            if (! copy($assembled['absolute_path'], $disk->path($relativePath))) {
                throw ValidationException::withMessages([
                    'upload' => 'Unable to prepare the uploaded archive for submission.',
                ]);
            }

            $request->session()->put(self::STAGED_UPLOAD_SESSION_KEY, [
                'token' => $token,
                'relative_path' => $relativePath,
                'original_name' => (string) ($metadata['original_name'] ?? 'master.xlsx'),
                'mime_type' => (string) ($metadata['mime_type'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            ]);

            $userId = (int) data_get($request->session()->get('user'), 'id', 0);
            $this->recordUploadHistory($token, $userId, [
                'original_name' => (string) ($metadata['original_name'] ?? 'master.xlsx'),
                'file_size' => (int) ($metadata['file_size'] ?? 0),
                'mime_type' => (string) ($metadata['mime_type'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                'status' => 'awaiting_submission',
                'progress' => 100,
                'message' => 'Upload complete. Ready to submit.',
            ]);

            // Initialize progress in cache so polling knows about this upload
            Cache::put('process:upload:' . $token, [
                'status' => 'awaiting_exclusions',
                'progress' => 100,
                'message' => 'Upload complete. Please upload exclusions to begin processing.',
                'processed_rows' => 0,
                'total_rows' => null,
                'started_at' => time(),
                'last_updated_at' => now()->toIso8601String(),
            ], now()->addMinutes(120));

            return response()->json([
                'status' => 'ok',
                'message' => 'Upload complete. Please submit and add exclusions to begin processing.',
                'staged_upload_token' => $token,
                'file_name' => (string) ($metadata['original_name'] ?? 'master.xlsx'),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'upload' => $exception->getMessage() ?: 'Unable to prepare the uploaded archive.',
            ]);
        } finally {
            $chunks->delete('master', $data['upload_token']);
        }
    }

    public function submitChunkUpload(
        Request $request,
        MasterDatasetWorkflowService $workflow,
        SessionUserResolver $resolver
    ): JsonResponse {
        $data = $request->validate([
            'staged_upload_token' => 'required|string',
        ]);

        $staged = $request->session()->get(self::STAGED_UPLOAD_SESSION_KEY);
        if (! is_array($staged) || empty($staged['token']) || empty($staged['relative_path'])) {
            throw ValidationException::withMessages([
                'upload' => 'No uploaded master file was found. Please upload the file again.',
            ]);
        }

        if (! hash_equals((string) $staged['token'], (string) $data['staged_upload_token'])) {
            throw ValidationException::withMessages([
                'upload' => 'The uploaded file reference is no longer valid. Please upload again.',
            ]);
        }

        $disk = Storage::disk('local');
        if (! $disk->exists((string) $staged['relative_path'])) {
            throw ValidationException::withMessages([
                'upload' => 'Uploaded file could not be found. Please upload the file again.',
            ]);
        }

        try {
            $this->replaceCurrentSessionProcess($request);
            $userContext = $resolver->resolve($request);

            $uploadedFile = new IlluminateUploadedFile(
                $disk->path((string) $staged['relative_path']),
                (string) $staged['original_name'],
                (string) ($staged['mime_type'] ?? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                null,
                true
            );

            $process = $workflow->queueMasterArchive($uploadedFile, $userContext);

            // Dispatch background master ingestion (Phase 1) job
            ProcessMasterIngestion::dispatch($process->id, $userContext);

            $userId = (int) data_get($request->session()->get('user'), 'id', 0);
            $this->syncUploadHistoryToDb($data['staged_upload_token'], [
                'user_id' => $userId,
                'status' => 'submitted',
                'progress' => 100,
                'message' => 'Master dataset file uploaded successfully. Passed to Credit Control Section for processing.',
                'finished_at' => time(),
            ]);

            $request->session()->put('master.dataset.process_id', $process->id);
            $request->session()->forget('master.dataset.staged_exclusions');

            $request->session()->flash('status', 'Master dataset file uploaded successfully. Passed to Credit Control Section for processing.');

            return response()->json([
                'status' => 'ok',
                'message' => 'Master dataset file uploaded successfully. Passed to Credit Control Section for processing.',
                'process_id' => $process->id,
                'redirect_url' => route('dashboard'),
                // Previous redirect:
                // 'redirect_url' => route('process.exclusions.create'),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'upload' => $exception->getMessage() ?: 'Unable to import the uploaded archive.',
            ]);
        } finally {
            $disk->delete((string) $staged['relative_path']);
            $request->session()->forget(self::STAGED_UPLOAD_SESSION_KEY);
        }
    }

    public function cancelChunkUpload(string $uploadToken, ChunkedUploadManager $chunks): JsonResponse
    {
        $chunks->delete('master', $uploadToken);

        return response()->json([
            'status' => 'ok',
        ]);
    }

    public function destroyStagedUpload(Request $request, string $token): JsonResponse
    {
        $userId = (int) data_get($request->session()->get('user'), 'id', 0);
        $this->syncUploadHistoryToDb($token, [
            'user_id' => $userId,
            'status' => 'canceled',
            'progress' => 0,
            'message' => 'Upload canceled by user.',
            'finished_at' => time(),
        ]);

        $staged = $request->session()->get(self::STAGED_UPLOAD_SESSION_KEY);

        // Signal background validation job to stop as soon as possible.
        Cache::put('process:upload:' . $token . ':abort', true, now()->addMinutes(30));
        Cache::put('process:upload:' . $token, [
            'status' => 'canceled',
            'progress' => 0,
            'message' => 'Validation canceled by user.',
            'stage' => 'validation',
            'processed_rows' => 0,
            'total_rows' => 0,
            'started_at' => time(),
            'errors' => [],
            'file_name' => is_array($staged) ? ($staged['original_name'] ?? null) : null,
        ], now()->addMinutes(120));

        if (is_array($staged) && !empty($staged['token']) && hash_equals((string) $staged['token'], $token)) {
            if (!empty($staged['relative_path'])) {
                Storage::disk('local')->delete((string) $staged['relative_path']);
            }

            $request->session()->forget(self::STAGED_UPLOAD_SESSION_KEY);
        }

        return response()->json([
            'status' => 'ok',
        ]);
    }

    public function store(Request $request, MasterDatasetWorkflowService $workflow, SessionUserResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'upload' => 'required|file|mimes:xlsx|max:51200',
        ]);

        $token = (string) Str::uuid();

        try {
            $this->replaceCurrentSessionProcess($request);
            $userContext = $resolver->resolve($request);

            $process = $workflow->queueMasterArchive($request->file('upload'), $userContext);

            $userId = (int) data_get($request->session()->get('user'), 'id', 0);
            $this->recordUploadHistory($token, $userId, [
                'original_name' => $request->file('upload')->getClientOriginalName(),
                'file_size' => $request->file('upload')->getSize(),
                'mime_type' => $request->file('upload')->getMimeType(),
                'status' => 'submitted',
                'progress' => 100,
                'message' => 'Master dataset file uploaded successfully. Passed to download section for processing.',
                'finished_at' => time(),
            ]);

            $this->syncUploadHistoryToDb($token);

        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'upload' => $exception->getMessage() ?: 'Unable to import the uploaded archive.',
            ]);
        }

        $request->session()->put('master.dataset.process_id', $process->id);

        $request->session()->flash('status', 'Master dataset file uploaded successfully. Passed to download section for processing.');

        return redirect()
            ->route('dashboard');
        /*
        return redirect()
            ->route('process.exclusions.create')
            ->with('status', 'Master dataset uploaded. Continue by adding exclusion files to begin validation.')
            ->with('hide_dataset_info', true);
         */
    }

    public function history(Request $request): View
    {
        $userId = (int) data_get($request->session()->get('user'), 'id', 0);

        return view('process.master-upload-history', [
            'userId' => $userId,
        ]);
    }

    public function historyJson(Request $request): JsonResponse
    {
        $userId = (int) data_get($request->session()->get('user'), 'id', 0);

        if ($userId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated.',
            ], 401);
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 50) : 10;

        $query = MasterUpload::where('user_id', $userId)
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage);

        $uploads = $paginator->getCollection()->map(fn ($upload) => [
            'token' => $upload->token,
            'original_name' => $upload->original_name,
            'status' => $upload->status,
            'progress' => (int) $upload->progress,
            'message' => $upload->message,
            'processed_rows' => (int) $upload->processed_rows,
            'total_rows' => $upload->total_rows ? (int) $upload->total_rows : null,
            'file_size' => $upload->file_size ? (int) $upload->file_size : null,
            'mime_type' => $upload->mime_type,
            'started_at' => $upload->started_at ? (int) $upload->started_at : $upload->created_at->timestamp,
            'last_updated_at' => $upload->updated_at?->toIso8601String(),
        ])->values()->all();

        return response()->json([
            'status' => 'ok',
            'uploads' => $uploads,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
