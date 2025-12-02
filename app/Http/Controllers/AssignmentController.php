<?php

namespace App\Http\Controllers;

use App\Models\DatasetExport;
use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetExportCoordinator;
use App\Support\MasterDatasetExportService;
use App\Support\MasterDatasetViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function __construct(
        private MasterDatasetViewService $viewService,
        private MasterDatasetExportService $exportService,
        private MasterDatasetExportCoordinator $exportCoordinator,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset to review assignments.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        $search = trim((string) $request->query('search', ''));
        $searching = $search !== '';
        $rows = null;

        if ($searching) {
            $query = $this->viewService->overviewQuery($process);
            $like = '%' . $search . '%';

            $query->where(function ($builder) use ($like) {
                $builder
                    ->where('customer_ref', 'like', $like)
                    ->orWhere('account_num', 'like', $like)
                    ->orWhere('product_label', 'like', $like)
                    ->orWhere('slt_business_line_value', 'like', $like)
                    ->orWhere('assigned_to', 'like', $like);
            });

            $rows = $query
                ->orderBy('customer_ref')
                ->orderBy('account_num')
                ->paginate(50)
                ->withQueryString();
        }

        $exports = $this->exportCoordinator->ensureFresh($process);

        // AJAX fragment for search results (pagination without full reload)
        if ($request->ajax()) {
            return view('process.assignments.partials.overview-results', [
                'rows' => $rows,
                'assignmentLabels' => $this->viewService->assignmentLabelMap(),
            ]);
        }

        return view('process.assignments.overview', [
            'process' => $process,
            'dataset' => $this->viewService->datasetSummary($process),
            'groupA' => $this->viewService->groupASummary($process),
            'groupB' => $this->viewService->groupBSummary($process),
            'exclusions' => $this->viewService->exclusionSummary($process),
            'vip' => $this->viewService->vipSummary($process),
            'region' => $this->viewService->regionSummary($process),
            'exports' => $exports,
            'search' => $search,
            'rows' => $rows,
            'assignmentLabels' => $this->viewService->assignmentLabelMap(),
        ]);
    }

    public function download(Request $request, string $group, string $bucket): RedirectResponse|StreamedResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset before downloading assignments.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        if (! $this->bucketAllowed($group, $bucket)) {
            return redirect()->route('process.assignments.index')->withErrors([
                'assignments' => 'The requested download is not available.',
            ]);
        }

        if ($request->boolean('fresh')) {
            $query = $this->viewService->bucketQuery($process, $bucket);
            $count = (clone $query)->count();

            if ($count === 0) {
                return redirect()->route('process.assignments.index')->withErrors([
                    'assignments' => 'No records are available for the selected download.',
                ]);
            }

            $label = $this->viewService->bucketLabel($bucket);
            $filename = $this->viewService->bucketFilename($bucket);

            return $this->exportService->stream($process, $bucket, $label, $filename, $query);
        }

        $export = DatasetExport::where('token', $process->token)
            ->where('bucket', $bucket)
            ->first();

        if ($export && $export->status === 'failed') {
            $this->exportCoordinator->ensureFresh($process);

            return redirect()->route('process.assignments.index')->withErrors([
                'assignments' => 'The last export attempt failed and is being regenerated. Please try again shortly.',
            ]);
        }

        if (! $export || $export->status !== 'ready') {
            $this->exportCoordinator->ensureFresh($process);

            return redirect()->route('process.assignments.index')->withErrors([
                'assignments' => 'That export is still being generated. Please wait and try again shortly.',
            ]);
        }

        $disk = Storage::disk($export->file_disk);

        if (! $disk->exists($export->file_path)) {
            $export->update(['status' => 'pending']);
            $this->exportCoordinator->ensureFresh($process);

            return redirect()->route('process.assignments.index')->withErrors([
                'assignments' => 'The export file is being regenerated. Please try again once it finishes.',
            ]);
        }

        return $disk->download($export->file_path, $export->filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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

    private function bucketAllowed(string $group, string $bucket): bool
    {
        $map = [
            'group-a' => ['call-center-staff', 'call-center', 'staff'],
            'group-b' => ['enterprise-wholesale', 'sme'],
            'exclusions' => ['excluded'],
            'vip' => ['vip'],
            'region' => ['region-billing'],
        ];

        return in_array($bucket, $map[$group] ?? [], true);
    }

}
