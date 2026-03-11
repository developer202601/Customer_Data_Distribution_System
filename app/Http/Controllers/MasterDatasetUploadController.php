<?php

namespace App\Http\Controllers;

use App\Models\MasterDatasetProcess;
use App\Support\ChunkedUploadManager;
use App\Support\MasterDatasetAssignmentConfiguration;
use App\Support\MasterDatasetWorkflowService;
use App\Support\SessionUserResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile as IlluminateUploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class MasterDatasetUploadController extends Controller
{
    private const MASTER_MAX_BYTES = 52428800;
    private const CHUNK_BYTES = 2097152;
    private const STAGED_UPLOAD_SESSION_KEY = 'master.dataset.staged_upload';

    public function create(Request $request, MasterDatasetAssignmentConfiguration $configuration): View
    {
        $process = null;
        $processId = $request->session()->get('master.dataset.process_id');

        if ($processId) {
            $process = MasterDatasetProcess::find($processId);
        }

        return view('process.master-upload', [
            'process' => $process,
            'assignmentConfig' => $configuration->toArray(),
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

        if (! Str::endsWith(strtolower($data['file_name']), '.zip')) {
            throw ValidationException::withMessages([
                'upload' => 'Please upload a ZIP file that contains your master Excel workbook.',
            ]);
        }

        $upload = $chunks->start('master', $data['file_name'], (int) $data['file_size'], $data['mime_type'] ?? null);

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
            $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) ($metadata['original_name'] ?? 'master.zip'));
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
                'original_name' => (string) ($metadata['original_name'] ?? 'master.zip'),
                'mime_type' => (string) ($metadata['mime_type'] ?? 'application/zip'),
            ]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Upload complete. Click Submit to continue.',
                'staged_upload_token' => $token,
                'file_name' => (string) ($metadata['original_name'] ?? 'master.zip'),
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
            $userContext = $resolver->resolve($request);

            $uploadedFile = new IlluminateUploadedFile(
                $disk->path((string) $staged['relative_path']),
                (string) $staged['original_name'],
                (string) ($staged['mime_type'] ?? 'application/zip'),
                null,
                true
            );

            $process = $workflow->queueMasterArchive($uploadedFile, $userContext);

            $request->session()->put('master.dataset.process_id', $process->id);
            $request->session()->forget('master.dataset.staged_exclusions');

            return response()->json([
                'status' => 'ok',
                'message' => 'Master dataset uploaded. Continue by adding exclusion files to begin validation.',
                'process_id' => $process->id,
                'redirect_url' => route('process.exclusions.create'),
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
        $staged = $request->session()->get(self::STAGED_UPLOAD_SESSION_KEY);

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
            'upload' => 'required|file|mimes:zip|max:51200',
        ]);

        try {
            $userContext = $resolver->resolve($request);

            $process = $workflow->queueMasterArchive($request->file('upload'), $userContext);
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
            ->with('status', 'Master dataset uploaded. Continue by adding exclusion files to begin validation.')
            ->with('hide_dataset_info', true);
    }
}
