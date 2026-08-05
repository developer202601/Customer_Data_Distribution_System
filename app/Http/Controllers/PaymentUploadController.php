<?php

namespace App\Http\Controllers;

use App\Models\PaymentUpload;
use App\Support\PaymentIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentUploadController extends Controller
{
    private const PROGRESS_CACHE_PREFIX = 'process:payment:upload:';
    private const USER_UPLOADS_PREFIX = 'user:payment:uploads:';

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload' => 'required|file|mimes:xlsx|max:51200',
        ]);

        $file = $data['upload'];

        if (! is_file($file->getRealPath()) || ! is_readable($file->getRealPath())) {
            throw ValidationException::withMessages([
                'upload' => 'The uploaded file could not be read. Please try again.',
            ]);
        }

        $originalName = $file->getClientOriginalName();
        $token = (string) Str::uuid();
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $originalName) ?: 'payment.xlsx';
        $directory = 'payments/staged';
        $storedName = sprintf('%s-%s', $token, $safeName);

        $path = Storage::disk('local')->putFileAs($directory, $file, $storedName);

        if (! $path) {
            throw ValidationException::withMessages([
                'upload' => 'Unable to store the uploaded payment file.',
            ]);
        }

        $userId = (int) data_get($request->session()->get('user'), 'id', 0);

        $upload = PaymentUpload::create([
            'token' => $token,
            'user_id' => $userId,
            'original_name' => $originalName,
            'status' => 'processing',
            'progress' => 1,
            'message' => 'Opening Excel workbook…',
            'processed_rows' => 0,
            'total_rows' => null,
            'matched' => 0,
            'updated' => 0,
            'not_found' => 0,
            'started_at' => time(),
        ]);

        Cache::put(self::PROGRESS_CACHE_PREFIX . $token, [
            'status' => 'processing',
            'progress' => 1,
            'message' => 'Opening Excel workbook…',
            'processed_rows' => 0,
            'total_rows' => null,
            'matched' => 0,
            'updated' => 0,
            'not_found' => 0,
            'original_filename' => $originalName,
            'started_at' => time(),
            'last_updated_at' => now()->toIso8601String(),
        ], now()->addMinutes(60));

        $service = new PaymentIngestionService();
        $storedPath = $path;
        $originalNameForService = $originalName;
        $userIdForService = $userId;
        $tokenForService = $token;

        register_shutdown_function(function () use ($service, $storedPath, $originalNameForService, $userIdForService, $tokenForService) {
            try {
                $service->run($tokenForService, $storedPath, $originalNameForService, $userIdForService);
            } catch (\Throwable $e) {
                Cache::put('process:payment:upload:' . $tokenForService, [
                    'status' => 'failed',
                    'progress' => 100,
                    'message' => 'Payment processing failed.',
                    'error' => $e->getMessage(),
                    'finished_at' => time(),
                ], now()->addMinutes(60));

                PaymentUpload::where('token', $tokenForService)->update([
                    'status' => 'failed',
                    'progress' => 100,
                    'message' => 'Payment processing failed.',
                    'error' => $e->getMessage(),
                    'finished_at' => time(),
                ]);
            }
        });

        return response()->json([
            'status' => 'ok',
            'token' => $token,
            'message' => 'Payment file uploaded. Processing started.',
        ]);
    }

    public function index(Request $request): JsonResponse
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

        $query = PaymentUpload::where('user_id', $userId)
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
            'matched' => $upload->matched ? (int) $upload->matched : null,
            'updated' => $upload->updated ? (int) $upload->updated : null,
            'not_found' => $upload->not_found ? (int) $upload->not_found : null,
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

    public function progress(string $token): JsonResponse
    {
        $state = Cache::get(self::PROGRESS_CACHE_PREFIX . $token);

        if (! $state) {
            $upload = PaymentUpload::where('token', $token)->first();

            if (! $upload) {
                return response()->json([
                    'status' => 'not-found',
                    'progress' => 0,
                    'message' => 'No progress available for this upload.',
                ], 404);
            }

            $state = [
                'status' => $upload->status,
                'progress' => (int) $upload->progress,
                'message' => $upload->message,
                'processed_rows' => (int) $upload->processed_rows,
                'total_rows' => $upload->total_rows ? (int) $upload->total_rows : null,
                'matched' => $upload->matched ? (int) $upload->matched : null,
                'updated' => $upload->updated ? (int) $upload->updated : null,
                'not_found' => $upload->not_found ? (int) $upload->not_found : null,
                'started_at' => (int) $upload->started_at,
                'last_updated_at' => $upload->updated_at?->toIso8601String(),
                'error' => $upload->error,
                'original_filename' => $upload->original_name,
            ];
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

        return response()->json([
            'status' => $state['status'] ?? (($state['progress'] ?? 0) >= 100 ? 'complete' : 'processing'),
            'progress' => $state['progress'] ?? 0,
            'message' => $state['message'] ?? 'Processing…',
            'processed_rows' => $state['processed_rows'] ?? 0,
            'total_rows' => $state['total_rows'] ?? 0,
            'matched' => $state['matched'] ?? 0,
            'updated' => $state['updated'] ?? 0,
            'not_found' => $state['not_found'] ?? 0,
            'eta_seconds' => $etaSeconds,
            'started_at' => $state['started_at'] ?? null,
            'last_updated_at' => $state['last_updated_at'] ?? null,
            'error' => $state['error'] ?? null,
            'file_name' => $state['original_filename'] ?? null,
        ]);
    }

    public function progressStream(string $token): StreamedResponse
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
                $state = Cache::get(self::PROGRESS_CACHE_PREFIX . $token);

                if (! $state) {
                    $payload = [
                        'status' => 'not-found',
                        'progress' => 0,
                        'message' => 'No progress available for this upload.',
                    ];
                } else {
                    $etaSeconds = null;
                    if (! empty($state['processed_rows']) && ! empty($state['total_rows']) && ! empty($state['started_at'])) {
                        $elapsed = time() - (int) $state['started_at'];
                        $rate = $state['processed_rows'] / max($elapsed, 1);
                        if ($rate > 0) {
                            $remaining = $state['total_rows'] - $state['processed_rows'];
                            $etaSeconds = (int) ($remaining / $rate);
                        }
                    }

                    $payload = [
                        'status' => $state['status'] ?? (($state['progress'] ?? 0) >= 100 ? 'complete' : 'processing'),
                        'progress' => $state['progress'] ?? 0,
                        'message' => $state['message'] ?? 'Processing…',
                        'processed_rows' => $state['processed_rows'] ?? 0,
                        'total_rows' => $state['total_rows'] ?? 0,
                        'matched' => $state['matched'] ?? 0,
                        'updated' => $state['updated'] ?? 0,
                        'not_found' => $state['not_found'] ?? 0,
                        'eta_seconds' => $etaSeconds,
                        'started_at' => $state['started_at'] ?? null,
                        'last_updated_at' => $state['last_updated_at'] ?? null,
                        'error' => $state['error'] ?? null,
                        'file_name' => $state['original_filename'] ?? null,
                    ];
                }

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
                if (in_array($status, ['complete', 'failed', 'canceled'], true)) {
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
}
