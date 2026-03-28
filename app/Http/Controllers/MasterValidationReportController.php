<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterValidationReportController extends Controller
{
    private const REPORT_CACHE_PREFIX = 'master.dataset.validation_report.';

    public function download(string $token): StreamedResponse|RedirectResponse
    {
        $token = trim($token);
        if ($token === '') {
            return redirect()->route('master.upload.create')->withErrors([
                'upload' => 'Validation report token is missing.',
            ]);
        }

        $userId = (int) data_get(session('user'), 'id', 0);
        if ($userId <= 0) {
            return redirect()->route('login');
        }

        $entry = Cache::get(self::REPORT_CACHE_PREFIX . $token);
        if (! is_array($entry)) {
            return redirect()->route('master.upload.create')->withErrors([
                'upload' => 'Validation report is no longer available. Please re-upload and validate again.',
            ]);
        }

        $ownerId = (int) ($entry['user_id'] ?? 0);
        if ($ownerId <= 0 || $ownerId !== $userId) {
            return redirect()->route('master.upload.create')->withErrors([
                'upload' => 'You are not authorized to download this validation report.',
            ]);
        }

        $disk = (string) ($entry['disk'] ?? config('filesystems.default', 'local'));
        $path = (string) ($entry['path'] ?? '');

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return redirect()->route('master.upload.create')->withErrors([
                'upload' => 'Validation report file could not be found. Please re-upload and validate again.',
            ]);
        }

        $downloadName = 'master-validation-errors-' . $token . '.csv';

        return Storage::disk($disk)->download($path, $downloadName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
