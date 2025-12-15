<?php

namespace App\Http\Controllers;

use App\Models\DatasetExport;
use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetExportCoordinator;
use App\Support\MasterDatasetExportService;
use App\Support\MasterDatasetViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

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
                $builder->where('account_num', 'like', $like);
            });

            $rows = $query
                ->orderBy('customer_ref')
                ->orderBy('account_num')
                ->paginate(50)
                ->withQueryString();
        }

        $exports = $this->exportCoordinator->ensureFresh($process);

        $reportGroups = $this->reportGroups();

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
            'reportGroups' => $reportGroups,
        ]);
    }

    public function reports(Request $request): View
    {
        $reportGroups = $this->reportGroups();
        $monthOptions = $reportGroups->keys();
        $selectedMonth = null;

        if ($monthOptions->isNotEmpty()) {
            $requestedMonth = $request->query('month');

            if ($requestedMonth && $monthOptions->contains($requestedMonth)) {
                $selectedMonth = $requestedMonth;
            } else {
                $selectedMonth = $monthOptions->first();
            }
        }

        $filteredProcesses = $selectedMonth ? $reportGroups->get($selectedMonth, collect()) : collect();

        $generatorOptions = $filteredProcesses
            ->mapWithKeys(function (MasterDatasetProcess $entry) {
                $key = (string) ($entry->user_id ?? 0);
                $label = $entry->user?->username ?? $entry->user_name ?? 'System';

                return [$key => $label];
            })
            ->unique();

        $selectedGenerator = $request->query('generator');

        if ($selectedGenerator && $generatorOptions->has($selectedGenerator)) {
            $filteredProcesses = $filteredProcesses->filter(function (MasterDatasetProcess $entry) use ($selectedGenerator) {
                return (string) ($entry->user_id ?? 0) === $selectedGenerator;
            });
        } else {
            $selectedGenerator = null;
        }

        return view('process.assignments.reports', [
            'reportGroups' => $reportGroups,
            'monthOptions' => $monthOptions,
            'selectedMonth' => $selectedMonth,
            'filteredProcesses' => $filteredProcesses,
            'generatorOptions' => $generatorOptions,
            'selectedGenerator' => $selectedGenerator,
        ]);
    }

    public function destroy(MasterDatasetProcess $process): RedirectResponse
    {
        $isAdmin = session('user.is_admin') ?? false;

        if (! $isAdmin) {
            return redirect()->route('process.assignments.reports')->withErrors([
                'reports' => 'Only administrators can remove datasets.',
            ]);
        }

        $disk = $process->storage_disk ?: config('filesystems.default', 'local');
        $token = $process->token;

        try {
            DB::transaction(function () use ($process, $disk, $token) {
                foreach ($process->exports as $export) {
                    if ($export->file_disk && $export->file_path) {
                        Storage::disk($export->file_disk)->delete($export->file_path);
                    }
                }

                Storage::disk($disk)->deleteDirectory('exports/' . $token);

                if ($process->master_archive_path) {
                    Storage::disk($disk)->delete($process->master_archive_path);
                }

                if ($process->master_workbook_path) {
                    Storage::disk($disk)->delete($process->master_workbook_path);
                }

                $process->exports()->delete();
                $process->delete();
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('process.assignments.reports')->withErrors([
                'reports' => 'Unable to delete the selected dataset. Please try again.',
            ]);
        }

        return redirect()->route('process.assignments.reports')->with('status', 'Dataset and exports deleted.');
    }

    public function report(MasterDatasetProcess $process): RedirectResponse
    {
        session(['master.dataset.process_id' => $process->id]);

        return redirect()->route('process.assignments.index');
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

    private function reportGroups(): Collection
    {
        return MasterDatasetProcess::with(['exports', 'user'])
            ->orderByDesc('run_date')
            ->get()
            ->groupBy(function (MasterDatasetProcess $item) {
                $reference = $item->run_date ?? $item->dataset_month ?? $item->created_at ?? now();
                $date = Carbon::parse($reference)->startOfMonth();

                return $date->format('F Y');
            });
    }

    private function bucketAllowed(string $group, string $bucket): bool
    {
        $map = [
            'group-a' => ['call-center-staff', 'call-center', 'staff'],
            'group-b' => ['enterprise-wholesale', 'sme'],
            'exclusions' => ['excluded', 'excluded-copper-retail-micro'],
            'vip' => ['vip'],
            'region' => ['region-billing'],
        ];

        return in_array($bucket, $map[$group] ?? [], true);
    }

}
