<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterReport;
use App\Models\CallCenterReportHiddenRow;
use App\Models\CallCenterReportRegionReview;
use App\Models\MasterDatasetRow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegionAdminController extends Controller
{
    protected function ensureRegionAdmin()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }

        $assignment = $sessionUser['assignment'] ?? null;
        if (! $assignment || $assignment === 'super'
            || str_starts_with($assignment, 'caller_')
            || str_starts_with($assignment, 'supervisor_')
            || str_starts_with($assignment, 'rtom_')) {
            abort(403);
        }

        return $assignment;
    }

    protected function regionRtoms(string $region)
    {
        $lastTwo = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        if (empty($lastTwo)) {
            return collect();
        }

        return MasterDatasetRow::whereIn('process_id', $lastTwo)
            ->where('region', $region)
            ->whereNotNull('rtom')
            ->distinct()
            ->pluck('rtom')
            ->values();
    }

    public function callers(Request $request)
    {
        $region = $this->ensureRegionAdmin();
        $regionAdminId = session('user')['id'] ?? null;

        $q = trim((string) $request->query('q', ''));
        $statusFilter = $request->query('status', 'all');

        $query = User::where('system', 'cc')
            ->where('assignment', 'like', 'caller_%')
            ->where('supervisor', $regionAdminId)
            ->withCount(['supervisedUsers', 'interactionsAsAgent', 'rowAssignments']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if ($statusFilter === 'active') {
            $query->where('status', 1);
        } elseif ($statusFilter === 'disabled') {
            $query->where('status', 0);
        }

        $callers = $query->orderBy('username')->get();

        return view('cc.region.callers', compact('region', 'callers', 'q', 'statusFilter'));
    }

    public function createCallerForm()
    {
        $region = $this->ensureRegionAdmin();

        return view('cc.region.create_caller', [
            'region' => $region,
        ]);
    }

    public function storeCaller(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $request->validate([
            'username' => 'required|digits:6|unique:users,username',
            'name' => 'nullable|string|max:45',
        ]);

        $user = User::create([
            'username' => $request->input('username'),
            'admin_prev' => 0,
            'system' => 'cc',
            'created_at' => now(),
            'fixed' => 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'caller_' . preg_replace('/\s+/', '_', strtolower($region)),
            'supervisor' => session('user')['id'] ?? null,
        ]);

        return redirect()->route('cc.region.callers')->with('status', 'Caller created');
    }

    public function editCaller(User $user)
    {
        $region = $this->ensureRegionAdmin();

        if (! $user->assignment || ! str_starts_with($user->assignment, 'caller_') || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        return view('cc.region.edit_caller', compact('user', 'region'));
    }

    public function updateCaller(Request $request, User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || ! str_starts_with($user->assignment, 'caller_') || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:45',
        ]);

        $user->name = $request->input('name');
        $user->save();

        return redirect()->route('cc.region.callers')->with('status', 'Caller updated');
    }

    public function destroyCaller(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || ! str_starts_with($user->assignment, 'caller_') || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        if ($user->fixed) {
            return redirect()->route('cc.region.callers')->withErrors(['delete' => 'This user is fixed and cannot be deleted.']);
        }

        if ($user->supervisedUsers()->exists() || $user->interactionsAsAgent()->exists() || $user->rowAssignments()->exists()) {
            return redirect()->route('cc.region.callers')->withErrors(['delete' => 'This user has related records and cannot be deleted. Disable instead.']);
        }

        $user->delete();
        return redirect()->route('cc.region.callers')->with('status', 'Caller deleted');
    }

    public function disableCaller(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || ! str_starts_with($user->assignment, 'caller_') || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $user->status = 0;
        $user->save();

        return redirect()->route('cc.region.callers')->with('status', 'Caller disabled');
    }

    public function enableCaller(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || ! str_starts_with($user->assignment, 'caller_') || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $user->status = 1;
        $user->save();

        return redirect()->route('cc.region.callers')->with('status', 'Caller enabled');
    }

    public function dashboard()
    {
        $region = $this->ensureRegionAdmin();
        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        $reviewOptIn = false;
        if ($sessionUserId > 0) {
            $reviewOptIn = (bool) User::where('id', $sessionUserId)->value('enable_regional_review');
        }

        $latestReport = CallCenterReport::callCenter()->whereHas('assignments', function($q) use ($region) {
            $q->whereHas('row', function($rq) use ($region) {
                $rq->where('region', $region);
            });
        })->latest('created_at')->first();

        $latestBase = CallCenterAssignment::callCenter()->with(['row', 'agent', 'interactions'])
            ->where('call_center_report_id', $latestReport?->id)
            ->whereHas('row', function($q) use ($region) {
                $q->where('region', $region);
            });

        $latestAssignments = $latestBase->get();
        $latestTotal = $latestAssignments->count();
        $latestAssigned = $latestAssignments->whereNotNull('assigned_user_id')->count();
        $latestUnassigned = $latestTotal - $latestAssigned;
        $latestPaidCount = $latestAssignments->where('paid', true)->count();
        $latestPaidAmount = $latestAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        $latestCallerBreakdown = $latestAssignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function($group) {
            $agent = $group->first()->agent;
            $total = $group->count();
            $assigned = $total;
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            return [
                'agent' => $agent ? ($agent->name ?? $agent->username) : 'Unknown',
                'total' => $total,
                'assigned' => $assigned,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
            ];
        })->values();

        $allTimeBase = CallCenterAssignment::callCenter()->with(['row', 'agent', 'interactions'])
            ->whereHas('row', function($q) use ($region) {
                $q->where('region', $region);
            });

        $allTimeAssignments = $allTimeBase->get();
        $allTimeTotal = $allTimeAssignments->count();
        $allTimeAssigned = $allTimeAssignments->whereNotNull('assigned_user_id')->count();
        $allTimeUnassigned = $allTimeTotal - $allTimeAssigned;
        $allTimePaidCount = $allTimeAssignments->where('paid', true)->count();
        $allTimePaidAmount = $allTimeAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        $allTimeCallerBreakdown = $allTimeAssignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function($group) {
            $agent = $group->first()->agent;
            $total = $group->count();
            $assigned = $total;
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            return [
                'agent' => $agent ? ($agent->name ?? $agent->username) : 'Unknown',
                'total' => $total,
                'assigned' => $assigned,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
            ];
        })->values();

        return view('cc.region.dashboard', compact(
            'region',
            'latestReport',
            'latestTotal', 'latestAssigned', 'latestUnassigned', 'latestPaidCount', 'latestPaidAmount', 'latestCallerBreakdown',
            'allTimeTotal', 'allTimeAssigned', 'allTimeUnassigned', 'allTimePaidCount', 'allTimePaidAmount', 'allTimeCallerBreakdown',
            'reviewOptIn'
        ));
    }

    public function updateReviewPreference(Request $request)
    {
        $this->ensureRegionAdmin();

        $data = $request->validate([
            'enable_regional_review' => 'required|in:0,1',
        ]);

        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        if ($sessionUserId <= 0) {
            abort(403);
        }

        $enable = (string) $data['enable_regional_review'] === '1';

        User::where('id', $sessionUserId)->update([
            'enable_regional_review' => $enable,
            'enable_regional_review_enabled_at' => $enable ? now() : null,
        ]);

        return redirect()->back()->with('status', 'Review preference updated.');
    }

    public function reviewReport(Request $request)
    {
        $region = $this->ensureRegionAdmin();
        $normalizedRegion = $this->normalizeRegionName($region);
        $gate = $this->currentRegionAdminReviewGate();
        $reviewOptIn = (bool) ($gate['opt_in'] ?? false);
        /** @var Carbon|null $reviewEnabledAt */
        $reviewEnabledAt = $gate['enabled_at'] ?? null;
        $search = trim((string) $request->query('q', ''));
        $reportId = (int) $request->query('report', 0);
        $showHidden = filter_var($request->query('show_hidden', false), FILTER_VALIDATE_BOOLEAN);
        $showHiddenOnly = filter_var($request->query('show_hidden_only', false), FILTER_VALIDATE_BOOLEAN);

        if ($showHiddenOnly) {
            $showHidden = true;
        }

        $reports = collect();
        if ($reviewOptIn && $reviewEnabledAt) {
            $reports = CallCenterReport::callCenter()->with('process')
                ->where('created_at', '>=', $reviewEnabledAt)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->filter(function (CallCenterReport $candidate) use ($normalizedRegion) {
                    $ids = collect($candidate->row_ids ?? [])->map(fn($id) => (int) $id)->filter(fn($id) => $id > 0)->values()->all();
                    if (empty($ids)) {
                        return false;
                    }

                    return MasterDatasetRow::whereIn('id', $ids)
                        ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
                        ->exists();
                })
                ->values();
        }

        $selectedReport = $reportId > 0
            ? $reports->firstWhere('id', $reportId)
            : $reports->first();

        $reportRows = null;
        $hiddenRowIds = [];
        $reviewRecord = null;
        $isLocked = false;
        $counts = [
            'total' => 0,
            'hidden' => 0,
            'visible' => 0,
        ];

        if ($selectedReport) {
            $reviewRecord = CallCenterReportRegionReview::where('call_center_report_id', $selectedReport->id)
                ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
                ->first();
            $isLocked = ! empty($reviewRecord?->reviewed_at);

            $rowIds = collect($selectedReport->row_ids ?? [])->map(fn($id) => (int) $id)->filter(fn($id) => $id > 0)->values()->all();
            if (! empty($rowIds)) {
                if ($isLocked) {
                    $hiddenRowIds = CallCenterReportHiddenRow::where('call_center_report_id', $selectedReport->id)
                        ->where('report_type', CallCenterReport::REPORT_TYPE_CALL_CENTER)
                        ->pluck('master_dataset_row_id')
                        ->map(fn($id) => (int) $id)
                        ->all();
                } else {
                    $hiddenRowIds = $this->getDraftHiddenRowIds($selectedReport->id, $normalizedRegion);
                }

                $regionRowIds = MasterDatasetRow::query()
                    ->whereIn('id', $rowIds)
                    ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
                    ->pluck('id')
                    ->map(fn($id) => (int) $id)
                    ->all();

                $hiddenRowIds = array_values(array_intersect($hiddenRowIds, $regionRowIds));

                $counts['total'] = count($regionRowIds);
                $counts['hidden'] = count($hiddenRowIds);
                $counts['visible'] = max(0, $counts['total'] - $counts['hidden']);

                if (empty($regionRowIds)) {
                    $reportRows = null;
                } else {
                    $query = MasterDatasetRow::query()
                        ->whereIn('id', $regionRowIds);

                    if ($showHiddenOnly) {
                        if (! empty($hiddenRowIds)) {
                            $query->whereIn('id', $hiddenRowIds);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    } elseif (! $showHidden) {
                        $query->whereNotIn('id', $hiddenRowIds);
                    }

                    if ($search !== '') {
                        $query->where(function ($q) use ($search) {
                            $q->where('account_num', 'like', '%' . $search . '%')
                                ->orWhere('customer_ref', 'like', '%' . $search . '%')
                                ->orWhere('mobile_contact_tel', 'like', '%' . $search . '%')
                                ->orWhere('new_arrears_value', 'like', '%' . $search . '%');
                        });
                    }

                    $reportRows = $query->orderBy('id')->paginate(10)->withQueryString();
                    $reportRows->getCollection()->transform(function ($row) use ($hiddenRowIds) {
                        $row->is_hidden_for_distribution = in_array((int) $row->id, $hiddenRowIds, true);
                        return $row;
                    });
                }
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            $tableHtml = view('cc.region._report_review_table', [
                'selectedReport' => $selectedReport,
                'rows' => $reportRows,
                'showHidden' => $showHidden,
                'showHiddenOnly' => $showHiddenOnly,
                'isLocked' => $isLocked,
            ])->render();

            return response()->json([
                'table_html' => $tableHtml,
                'counts' => $counts,
                'reviewed_at' => optional($reviewRecord?->reviewed_at)?->toDateTimeString(),
                'is_locked' => $isLocked,
            ]);
        }

        return view('cc.region.report_review', [
            'region' => $region,
            'reviewOptIn' => $reviewOptIn,
            'reviewEnabledAt' => $reviewEnabledAt,
            'reports' => $reports,
            'selectedReport' => $selectedReport,
            'rows' => $reportRows,
            'hiddenRowIds' => $hiddenRowIds,
            'search' => $search,
            'showHidden' => $showHidden,
            'showHiddenOnly' => $showHiddenOnly,
            'counts' => $counts,
            'reviewRecord' => $reviewRecord,
        ]);
    }

    public function hideRows(Request $request, $reportId)
    {
        $region = $this->ensureRegionAdmin();
        $normalizedRegion = $this->normalizeRegionName($region);
        $report = CallCenterReport::callCenter()->findOrFail((int) $reportId);

        $respondError = function (string $message, int $status = 422) use ($request) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['errors' => ['rows' => [$message]]], $status);
            }
            return back()->withErrors(['rows' => $message]);
        };

        $gate = $this->currentRegionAdminReviewGate();
        $reviewOptIn = (bool) ($gate['opt_in'] ?? false);
        /** @var Carbon|null $reviewEnabledAt */
        $reviewEnabledAt = $gate['enabled_at'] ?? null;
        if (! $reviewOptIn) {
            return $respondError('Regional Review Gate is disabled.');
        }
        if (! $this->isReportEligibleForCurrentGate($report, $reviewEnabledAt)) {
            return $respondError('This report was generated before Regional Review Gate was enabled and cannot be reviewed.');
        }

        $data = $request->validate([
            'row_ids' => 'required|array|min:1',
            'row_ids.*' => 'integer|min:1',
            'action' => 'nullable|in:hide,unhide',
        ]);

        $action = (string) ($data['action'] ?? 'hide');
        $rowIds = collect($data['row_ids'])->map(fn($id) => (int) $id)->unique()->values()->all();
        $reportRowIds = collect($report->row_ids ?? [])->map(fn($id) => (int) $id)->all();
        $validIds = array_values(array_intersect($rowIds, $reportRowIds));

        if (empty($validIds)) {
            return back()->withErrors(['rows' => 'No valid rows were selected.']);
        }

        $regionScopedIds = MasterDatasetRow::whereIn('id', $validIds)
            ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->all();

        if (empty($regionScopedIds)) {
            return back()->withErrors(['rows' => 'Selected rows are outside your region.']);
        }

        $reviewRecord = CallCenterReportRegionReview::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->first();
        if (! empty($reviewRecord?->reviewed_at)) {
            return back()->withErrors(['rows' => 'This review is already passed and locked.']);
        }

        $draftHiddenIds = $this->getDraftHiddenRowIds($report->id, $normalizedRegion);

        if ($action === 'unhide') {
            $draftHiddenIds = array_values(array_diff($draftHiddenIds, $regionScopedIds));
            $this->putDraftHiddenRowIds($report->id, $normalizedRegion, $draftHiddenIds);

            return back()->with('status', count($regionScopedIds) . ' row(s) set as visible in draft review. Use Pass to make it permanent.');
        }

        $draftHiddenIds = collect($draftHiddenIds)
            ->merge($regionScopedIds)
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $this->putDraftHiddenRowIds($report->id, $normalizedRegion, $draftHiddenIds);

        return back()->with('status', count($regionScopedIds) . ' row(s) hidden in draft review. Use Pass to make it permanent.');
    }

    public function passReport(Request $request, $reportId)
    {
        $region = $this->ensureRegionAdmin();
        $normalizedRegion = $this->normalizeRegionName($region);
        $report = CallCenterReport::callCenter()->findOrFail((int) $reportId);

        $gate = $this->currentRegionAdminReviewGate();
        $reviewOptIn = (bool) ($gate['opt_in'] ?? false);
        /** @var Carbon|null $reviewEnabledAt */
        $reviewEnabledAt = $gate['enabled_at'] ?? null;
        if (! $reviewOptIn) {
            return redirect()->route('cc.region.review', ['report' => $report->id])
                ->withErrors(['review' => 'Regional Review Gate is disabled.']);
        }
        if (! $this->isReportEligibleForCurrentGate($report, $reviewEnabledAt)) {
            return redirect()->route('cc.region.review', ['report' => $report->id])
                ->withErrors(['review' => 'This report was generated before Regional Review Gate was enabled and cannot be reviewed.']);
        }

        $existingReview = CallCenterReportRegionReview::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->first();
        if (! empty($existingReview?->reviewed_at)) {
            return redirect()->route('cc.region.review', ['report' => $report->id])
                ->withErrors(['review' => 'This report has already been passed for your region and cannot be changed.']);
        }

        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        $reportRowIds = collect($report->row_ids ?? [])->map(fn($id) => (int) $id)->filter(fn($id) => $id > 0)->values()->all();
        $regionReportRowIds = empty($reportRowIds)
            ? []
            : MasterDatasetRow::whereIn('id', $reportRowIds)
                ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
                ->pluck('id')
                ->map(fn($id) => (int) $id)
                ->values()
                ->all();

        $draftHiddenIds = array_values(array_intersect(
            $this->getDraftHiddenRowIds($report->id, $normalizedRegion),
            $regionReportRowIds
        ));

        $reportType = $report->report_type ?? CallCenterReport::REPORT_TYPE_CALL_CENTER;

        DB::transaction(function () use ($report, $region, $normalizedRegion, $sessionUserId, $regionReportRowIds, $draftHiddenIds, $reportType) {
            if (! empty($regionReportRowIds)) {
                CallCenterReportHiddenRow::where('call_center_report_id', $report->id)
                    ->whereIn('master_dataset_row_id', $regionReportRowIds)
                    ->delete();
            }

            $now = now();
            foreach ($draftHiddenIds as $rowId) {
                CallCenterReportHiddenRow::create([
                    'call_center_report_id' => $report->id,
                    'report_type' => $reportType,
                    'master_dataset_row_id' => (int) $rowId,
                    'hidden_by_user_id' => $sessionUserId > 0 ? $sessionUserId : null,
                    'hidden_at' => $now,
                ]);
            }

            $review = CallCenterReportRegionReview::firstOrNew([
                'call_center_report_id' => $report->id,
                'region_name' => $region,
            ]);

            $review->report_type = $reportType;
            $review->reviewed_by_user_id = $sessionUserId > 0 ? $sessionUserId : null;
            $review->reviewed_at = $now;
            $review->save();

            if ($this->normalizeRegionName((string) $review->region_name) !== $normalizedRegion) {
                $review->region_name = $region;
                $review->save();
            }
        });

        $this->clearDraftHiddenRowIds($report->id, $normalizedRegion);

        return redirect()->route('cc.region.review', ['report' => $report->id])
            ->with('status', 'Region review passed and locked. Distribution can continue for this report.');
    }

    private function draftHiddenRowsSessionKey(int $reportId, string $normalizedRegion): string
    {
        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        $userKey = $sessionUserId > 0 ? (string) $sessionUserId : 'guest';

        return 'cc.region.review.draft_hidden.' . $userKey . '.' . $reportId . '.' . md5($normalizedRegion);
    }

    private function getDraftHiddenRowIds(int $reportId, string $normalizedRegion): array
    {
        $raw = session($this->draftHiddenRowsSessionKey($reportId, $normalizedRegion), []);

        return collect(is_array($raw) ? $raw : [])
            ->map(fn($id) => (int) $id)
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function putDraftHiddenRowIds(int $reportId, string $normalizedRegion, array $rowIds): void
    {
        session([
            $this->draftHiddenRowsSessionKey($reportId, $normalizedRegion) => collect($rowIds)
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    private function clearDraftHiddenRowIds(int $reportId, string $normalizedRegion): void
    {
        session()->forget($this->draftHiddenRowsSessionKey($reportId, $normalizedRegion));
    }

    private function normalizeRegionName(?string $value): string
    {
        $normalized = Str::lower(trim((string) $value));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: '';

        return $normalized;
    }

    private function currentRegionAdminReviewGate(): array
    {
        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        if ($sessionUserId <= 0) {
            return ['opt_in' => false, 'enabled_at' => null];
        }

        $user = User::select('enable_regional_review', 'enable_regional_review_enabled_at')
            ->where('id', $sessionUserId)
            ->first();

        return [
            'opt_in' => (bool) ($user?->enable_regional_review ?? false),
            'enabled_at' => $user?->enable_regional_review_enabled_at,
        ];
    }

    private function isReportEligibleForCurrentGate(CallCenterReport $report, ?Carbon $enabledAt): bool
    {
        if (! $enabledAt || ! $report->created_at) {
            return false;
        }

        return $report->created_at->greaterThanOrEqualTo($enabledAt);
    }

    protected function formatAgentLabel($agent)
    {
        if (!$agent) return 'Unknown';
        
        $username = $agent->username;
        $name = $agent->name;
        
        return $name ? "{$username} ({$name})" : $username;
    }

    public function searchCallers(Request $request)
    {
        $region = $this->ensureRegionAdmin();
        $regionAdminId = session('user')['id'] ?? null;

        $q = trim((string) $request->query('q', ''));

        $query = User::where('system', 'cc')
            ->where('assignment', 'like', 'caller_%')
            ->where('supervisor', $regionAdminId)
            ->withCount(['supervisedUsers', 'interactionsAsAgent', 'rowAssignments']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        $callers = $query->orderBy('username')->get();

        return view('cc.region._caller_rows', compact('callers'));
    }
}
