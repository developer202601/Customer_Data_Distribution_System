<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationChange;
use App\Models\Configurations;
use App\Models\DatasetExport;
use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use App\Support\MasterDatasetAssignmentConfiguration;
use App\Support\MasterDatasetExportCoordinator;
use App\Support\MasterDatasetExportService;
use App\Support\MasterDatasetProcessStatus;
use App\Support\MasterDatasetViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

    public function index(Request $request, MasterDatasetAssignmentConfiguration $assignmentConfiguration): View|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset to review assignments.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        // Enforce the new two-step workflow: do not show assignments until the
        // user has confirmed configuration and processing has completed.
        if ($process->status === MasterDatasetProcessStatus::WAITING_CONFIRMATION) {
            return redirect()->route('process.confirm.create');
        }

        if ($process->status !== MasterDatasetProcessStatus::READY && $process->status !== MasterDatasetProcessStatus::FAILED) {
            return redirect()->route('process.running.show');
        }

        // Backfill: older processes created before we stored a default snapshot.
        // Infer defaults at the time of confirmation using configuration change history.
        if (empty($process->assignment_config_default_snapshot) && ! empty($process->assignment_config_set_at)) {
            $snapshot = $this->inferAssignmentDefaultsAt($process->assignment_config_set_at);
            if (! empty($snapshot)) {
                $process->forceFill([
                    'assignment_config_default_snapshot' => $snapshot,
                ])->save();
                $process->refresh();
            }
        }

        // Backfill: store FTTH count used at confirmation time (older processes).
        if ($process->assignment_config_ftth_count === null) {
            $process->forceFill([
                'assignment_config_ftth_count' => $this->computeFtthCountForProcess($process->id),
            ])->save();
            $process->refresh();
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
            'assignmentConfigDefault' => $assignmentConfiguration->toArray(),
        ]);
    }

    private function inferAssignmentDefaultsAt(Carbon $at): array
    {
        $map = [
            'upper_range' => 'upper_range',
            'lower_range' => 'lower_range',
            'call_center_staff_quota' => 'ccs',
            'call_center_quota' => 'cc',
            'staff_quota' => 's',
        ];

        $snapshot = [];

        foreach ($map as $outputKey => $configKey) {
            $value = null;

            // If config changed after the process was confirmed, use the old value.
            $after = ConfigurationChange::query()
                ->where('config_key', $configKey)
                ->where('created_at', '>', $at)
                ->orderBy('created_at')
                ->first();

            if ($after && $after->old_value !== null) {
                $value = (int) $after->old_value;
            }

            // Otherwise, use the latest new value at/before the confirmation time.
            if ($value === null) {
                $before = ConfigurationChange::query()
                    ->where('config_key', $configKey)
                    ->where('created_at', '<=', $at)
                    ->orderByDesc('created_at')
                    ->first();

                if ($before && $before->new_value !== null) {
                    $value = (int) $before->new_value;
                }
            }

            // Final fallback: use the current value in the configurations table.
            if ($value === null) {
                $current = Configurations::query()
                    ->where('config_name', $configKey)
                    ->value('value');

                if ($current !== null) {
                    $value = (int) $current;
                }
            }

            if ($value === null) {
                $value = 0;
            }

            $snapshot[$outputKey] = max(0, (int) $value);
        }

        return $snapshot;
    }

    private function computeFtthCountForProcess(int $processId): int
    {
        return MasterDatasetRow::query()
            ->where('process_id', $processId)
            ->where('excluded', false)
            ->where(function ($query) {
                $query
                    ->whereRaw('LOWER(slt_gl_sub_segment) IN (?, ?, ?)', ['retail', 'micro business', 'microbusiness'])
                    ->orWhereIn('customer_segment', ['11', '35'])
                    ->orWhereRaw('LOWER(customer_segment) IN (?, ?, ?)', ['retail', 'micro business', 'microbusiness']);
            })
            ->whereRaw('LOWER(medium) IN (?, ?)', ['ftth', 'fiber'])
            ->count();
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
                Storage::disk($disk)->deleteDirectory('exclusions/' . $process->id);

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

    public function destroyBulk(Request $request): RedirectResponse
    {
        $isAdmin = session('user.is_admin') ?? false;

        if (! $isAdmin) {
            return redirect()->route('process.assignments.reports')->withErrors([
                'reports' => 'Only administrators can remove datasets.',
            ]);
        }

        $data = $request->validate([
            'process_ids' => ['required', 'array', 'min:1'],
            'process_ids.*' => ['integer', 'exists:master_dataset_processes,id'],
        ]);

        $processIds = collect($data['process_ids'])->map(static fn ($id) => (int) $id)->unique()->values();

        if ($processIds->isEmpty()) {
            throw ValidationException::withMessages([
                'reports' => 'Select at least one dataset to delete.',
            ]);
        }

        $deletedCount = 0;

        try {
            DB::transaction(function () use ($processIds, &$deletedCount) {
                $processes = MasterDatasetProcess::query()
                    ->whereIn('id', $processIds)
                    ->with('exports')
                    ->get();

                /** @var MasterDatasetProcess $process */
                foreach ($processes as $process) {
                    $disk = $process->storage_disk ?: config('filesystems.default', 'local');
                    $token = $process->token;

                    foreach ($process->exports as $export) {
                        if ($export->file_disk && $export->file_path) {
                            Storage::disk($export->file_disk)->delete($export->file_path);
                        }
                    }

                    Storage::disk($disk)->deleteDirectory('exports/' . $token);
                    Storage::disk($disk)->deleteDirectory('exclusions/' . $process->id);

                    if ($process->master_archive_path) {
                        Storage::disk($disk)->delete($process->master_archive_path);
                    }

                    if ($process->master_workbook_path) {
                        Storage::disk($disk)->delete($process->master_workbook_path);
                    }

                    $process->exports()->delete();
                    $process->delete();
                    $deletedCount++;
                }
            });
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('process.assignments.reports')->withErrors([
                'reports' => 'Unable to delete one or more selected datasets. Please try again.',
            ]);
        }

        return redirect()->route('process.assignments.reports')->with('status', sprintf('%d dataset(s) deleted.', $deletedCount));
    }

    public function report(MasterDatasetProcess $process): RedirectResponse
    {
        session(['master.dataset.process_id' => $process->id]);

        if ($process->status === MasterDatasetProcessStatus::AWAITING_EXCLUSIONS) {
            return redirect()->route('process.exclusions.create');
        }

        return redirect()->route('process.assignments.index');
    }

    public function download(Request $request, string $group, string $bucket): RedirectResponse|StreamedResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset before downloading assignments.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        if ($process->status === MasterDatasetProcessStatus::WAITING_CONFIRMATION) {
            return redirect()->route('process.confirm.create');
        }

        if ($process->status !== MasterDatasetProcessStatus::READY) {
            return redirect()->route('process.running.show');
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

        $process = MasterDatasetProcess::with(['assignmentConfigSetter'])->find($processId);

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
        $items = MasterDatasetProcess::with(['exports', 'user'])->get();

        // Sort each process by its run date (or created_at) descending so newest appear first
        $sorted = $items->sortByDesc(function (MasterDatasetProcess $item) {
            $reference = $item->run_date ?? $item->created_at ?? now();
            return Carbon::parse($reference)->getTimestamp();
        });

        // Group by month label (e.g. "December 2025")
        $grouped = $sorted->groupBy(function (MasterDatasetProcess $item) {
            $reference = $item->run_date ?? $item->dataset_month ?? $item->created_at ?? now();
            $date = Carbon::parse($reference)->startOfMonth();

            return $date->format('F Y');
        });

        // Ensure the month groups themselves are ordered newest-first
        $grouped = $grouped->sortByDesc(function ($group, $key) {
            // Parse the key (e.g. "December 2025") back to a date for reliable sorting
            return Carbon::parse($key)->getTimestamp();
        });

        return $grouped;
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
