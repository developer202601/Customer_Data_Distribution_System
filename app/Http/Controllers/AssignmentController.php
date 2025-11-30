<?php

namespace App\Http\Controllers;

use App\Models\MasterDatasetProcess;
use App\Support\MasterDatasetAssignmentService;
use App\Support\MasterDatasetExportService;
use App\Support\MasterDatasetViewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function __construct(
        private MasterDatasetViewService $viewService,
        private MasterDatasetAssignmentService $assignmentService,
        private MasterDatasetExportService $exportService,
    ) {
    }

    public function index(): View|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset to review assignments.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        return view('process.assignments.overview', [
            'process' => $process,
            'dataset' => $this->viewService->datasetSummary($process),
            'groupA' => $this->viewService->groupASummary($process),
            'groupB' => $this->viewService->groupBSummary($process),
            'exclusions' => $this->viewService->exclusionSummary($process),
            'vip' => $this->viewService->vipSummary($process),
            'region' => $this->viewService->regionSummary($process),
        ]);
    }

    public function vip(Request $request): View|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset to review VIP records.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        $search = trim((string) $request->query('search', ''));

        $query = $this->viewService->vipQuery($process);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->where('customer_ref', 'like', $like)
                    ->orWhere('account_num', 'like', $like)
                    ->orWhere('product_label', 'like', $like);
            });
        }

        $rows = $query
            ->orderBy('customer_ref')
            ->orderBy('account_num')
            ->paginate(50)
            ->withQueryString();

        return view('process.assignments.vip', [
            'process' => $process,
            'dataset' => $this->viewService->datasetSummary($process),
            'summary' => $this->viewService->vipSummary($process),
            'rows' => $rows,
            'search' => $search,
        ]);
    }

    public function groupA(): View|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset to review assignments.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        return view('process.assignments.group-a', [
            'process' => $process,
            'dataset' => $this->viewService->datasetSummary($process),
            'group' => $this->viewService->groupASummary($process),
        ]);
    }

    public function groupB(): View|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset to review assignments.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        return view('process.assignments.group-b', [
            'process' => $process,
            'dataset' => $this->viewService->datasetSummary($process),
            'group' => $this->viewService->groupBSummary($process),
        ]);
    }

    public function regionBilling(Request $request): View|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset to review Region Billing Centre records.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        $search = trim((string) $request->query('search', ''));

        $query = $this->viewService->regionQuery($process);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($builder) use ($like) {
                $builder
                    ->where('customer_ref', 'like', $like)
                    ->orWhere('account_num', 'like', $like)
                    ->orWhere('product_label', 'like', $like);
            });
        }

        $rows = $query
            ->orderBy('customer_ref')
            ->orderBy('account_num')
            ->paginate(50)
            ->withQueryString();

        return view('process.assignments.region', [
            'process' => $process,
            'dataset' => $this->viewService->datasetSummary($process),
            'summary' => $this->viewService->regionSummary($process),
            'rows' => $rows,
            'search' => $search,
        ]);
    }

    public function exclusions(): View|RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset before reviewing exclusions.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        return view('process.assignments.exclusions', [
            'process' => $process,
            'dataset' => $this->viewService->datasetSummary($process),
            'summary' => $this->viewService->exclusionSummary($process),
        ]);
    }

    public function filteredOut(): RedirectResponse
    {
        return redirect()->route('process.assignments.exclusions')->withErrors([
            'assignments' => 'Filtered-out records are included in the exclusions download.',
        ]);
    }

    public function regenerate(): RedirectResponse
    {
        $process = $this->resolveProcessOrRedirect('Upload the master dataset before regenerating assignments.');

        if ($process instanceof RedirectResponse) {
            return $process;
        }

        $this->assignmentService->assign($process);

        return redirect()->route('process.assignments.index')->with('status', 'Assignments recalculated successfully.');
    }

    public function download(string $group, string $bucket): RedirectResponse|StreamedResponse
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

        $query = $this->viewService->bucketQuery($process, $bucket);
        $count = (clone $query)->count();

        if ($count === 0) {
            return $this->redirectForGroup($group)->withErrors([
                'assignments' => 'No records are available for the selected download.',
            ]);
        }

        $label = $this->viewService->bucketLabel($bucket);
        $filename = $this->viewService->bucketFilename($bucket);

        return $this->exportService->stream($process, $bucket, $label, $filename, $query);
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

    private function redirectForGroup(string $group): RedirectResponse
    {
        $route = match ($group) {
            'group-a' => 'process.assignments.group-a',
            'group-b' => 'process.assignments.group-b',
            'exclusions' => 'process.assignments.exclusions',
            'vip' => 'process.assignments.vip',
            'region' => 'process.assignments.region',
            default => 'process.assignments.index',
        };

        return redirect()->route($route);
    }
}
