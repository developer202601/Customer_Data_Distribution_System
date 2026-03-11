<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MasterDatasetRow;
use App\Models\CallCenterReport;
use App\Models\CallCenterReportHiddenRow;
use App\Models\CallCenterReportRegionReview;
use Illuminate\Http\Request;
use App\Models\CallCenterAssignment;
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
        if (! $assignment || $assignment === 'super') {
            abort(403);
        }
        return $assignment; // region name
    }

    public function index()
    {
        $region = $this->ensureRegionAdmin();

        $lastTwo = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        $rtoms = [];
        if (! empty($lastTwo)) {
            $rtoms = MasterDatasetRow::whereIn('process_id', $lastTwo)
                ->where('region', $region)
                ->whereNotNull('rtom')
                ->distinct()
                ->pluck('rtom')
                ->values();
        }

        // rtom admins are users with assignment like 'rtom_<rtom>' and supervisor = current user's id
        $currentSupervisor = session('user')['id'] ?? null;

        $q = request()->query('q');
        $selectedRtom = request()->query('rtom');

        $query = User::where('system', 'cc')
            ->where('admin_prev', 1)
            ->where('assignment', 'like', 'rtom_%')
            ->where('supervisor', $currentSupervisor)
            ->withCount('supervisedUsers');

        if (! empty($q)) {
            $query->where(function($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedRtom)) {
            $assignmentValue = 'rtom_' . preg_replace('/\s+/', '_', strtolower($selectedRtom));
            $query->where('assignment', $assignmentValue);
        }

        $rtomAdmins = $query->get();

        return view('cc.region.index', compact('rtoms', 'rtomAdmins', 'region', 'q', 'selectedRtom'));
    }

    /**
     * AJAX search endpoint returning table rows for RTOM admins.
     */
    public function search(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $currentSupervisor = session('user')['id'] ?? null;

        $q = $request->query('q');
        $selectedRtom = $request->query('rtom');

        $query = User::where('system', 'cc')
            ->where('admin_prev', 1)
            ->where('assignment', 'like', 'rtom_%')
            ->where('supervisor', $currentSupervisor)
            ->withCount('supervisedUsers');

        if (! empty($q)) {
            $query->where(function($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedRtom)) {
            $assignmentValue = 'rtom_' . preg_replace('/\s+/', '_', strtolower($selectedRtom));
            $query->where('assignment', $assignmentValue);
        }

        $rtomAdmins = $query->get();

        return view('cc.region._rows', compact('rtomAdmins'));
    }

    public function createAdminForm(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $rtoms = $this->regionRtoms($region);

        return view('cc.region.create_admin', [
            'rtoms' => $rtoms,
            'isSupervisor' => false,
        ]);
    }

    /**
     * Show the create supervisor form (separate view with supervisor select and fixed=1).
     */
    public function createSupervisorForm(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $rtoms = $this->regionRtoms($region);

        return view('cc.region.create_supervisor', [
            'rtoms' => $rtoms,
        ]);
    }

    protected function regionRtoms($region)
    {
        $lastTwo = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        if (empty($lastTwo)) return collect();

        return MasterDatasetRow::whereIn('process_id', $lastTwo)
            ->where('region', $region)
            ->whereNotNull('rtom')
            ->distinct()
            ->pluck('rtom')
            ->values();
    }

    public function storeAdmin(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $request->validate([
            'username' => 'required|digits:6|unique:users,username',
            'rtom' => 'required_without:supervisor|string|max:255',
            'supervisor' => 'required_without:rtom|string|max:255',
            'name' => 'nullable|string|max:45',
        ]);

        // Accept value from either the RTOM field (create admin) or the supervisor field (create supervisor form)
        $rtom = $request->input('rtom') ?? $request->input('supervisor');

        // allow marking created users as "supervisors" via fixed=1 in the form (they are fixed and cannot be deleted)
        $isSupervisor = ! empty($request->input('fixed'));

        $user = User::create([
            'username' => $request->input('username'),
            'admin_prev' => 1,
            'system' => 'cc',
            'created_at' => now(),
            'fixed' => 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'rtom_' . preg_replace('/\s+/', '_', strtolower($rtom)),
            'supervisor' => session('user')['id'] ?? null,
        ]);

        return redirect()->route('cc.region.index')->with('status', 'RTO admin created');
    }

    public function storeSupervisor(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $request->validate([
            'username' => 'required|digits:6|unique:users,username',
            'name' => 'nullable|string|max:45',
        ]);

        // If RTOM user, use their RTOM from session for assignment and supervisor fields
        if (\Illuminate\Support\Str::startsWith(session('user.assignment'), 'rtom_')) {
            $rtom = session('user.assignment');
            $supervisorId = session('user')['id'] ?? null;
        } else {
            $rtom = $request->input('rtom') ?? $request->input('supervisor');
            $supervisorId = session('user')['id'] ?? null;
        }

        $isSupervisor = ! empty($request->input('fixed'));

        $user = User::create([
            'username' => $request->input('username'),
            'admin_prev' => 1,
            'system' => 'cc',
            'created_at' => now(),
            'fixed' => 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'supervisor_' . preg_replace('/\s+/', '_', strtolower($rtom)),
            'supervisor' => $supervisorId,
        ]);

        // Redirect to supervisor list for RTOM users, else to RTOM list
        if (\Illuminate\Support\Str::startsWith(session('user.assignment'), 'rtom_')) {
            return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor created');
        } else {
            return redirect()->route('cc.region.index')->with('status', 'Supervisor created');
        }
    }

    public function editAdminForm(User $user)
    {
        $region = $this->ensureRegionAdmin();

        // only allow editing RTOM admins they supervise
        if (! $user->assignment || stripos($user->assignment, 'rtom_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $rtoms = $this->regionRtoms($region);
        return view('cc.region.edit_admin', compact('user', 'rtoms'));
    }

    public function updateAdmin(Request $request, User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'rtom_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:45',
            'rtom' => 'required|string|max:255',
        ]);

        $user->name = $request->input('name');
        $user->assignment = 'rtom_' . preg_replace('/\s+/', '_', strtolower($request->input('rtom')));
        $user->save();

        return redirect()->route('cc.region.index')->with('status', 'RTO admin updated');
    }

    public function destroyAdmin(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'rtom_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        if ($user->fixed) {
            return redirect()->route('cc.region.index')->withErrors(['delete' => 'This user is fixed and cannot be deleted.']);
        }

        if ($user->supervisedUsers()->exists()) {
            return redirect()->route('cc.region.index')->withErrors(['delete' => 'This RTO admin has supervised employees and cannot be deleted.']);
        }

        $user->delete();
        return redirect()->route('cc.region.index')->with('status', 'RTO admin deleted');
    }

    // --- Supervisor management for RTOM users ---
    public function editSupervisorForm(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        return view('cc.region.edit_supervisor', compact('user'));
    }

    public function updateSupervisor(Request $request, User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:45',
        ]);

        $user->name = $request->input('name');
        $user->save();

        return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor updated');
    }

    public function disableSupervisor(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $user->status = 0;
        $user->save();

        return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor disabled');
    }

    public function enableSupervisor(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $user->status = 1;
        $user->save();

        return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor enabled');
    }

    public function destroySupervisor(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        if ($user->fixed) {
            return redirect()->route('cc.region.assign.index')->withErrors(['delete' => 'This user is fixed and cannot be deleted.']);
        }

        $user->delete();
        return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor deleted');
    }

    public function dashboard()
    {
        $region = $this->ensureRegionAdmin();
        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        $reviewOptIn = false;
        if ($sessionUserId > 0) {
            $reviewOptIn = (bool) User::where('id', $sessionUserId)->value('enable_regional_review');
        }

        // Get latest report data (most recent report with assignments)
        $latestReport = CallCenterReport::whereHas('assignments', function($q) use ($region) {
            $q->whereHas('row', function($rq) use ($region) {
                $rq->where('region', $region);
            });
        })->latest('created_at')->first();

        // Latest report data
        $latestBase = CallCenterAssignment::with(['row', 'agent', 'interactions'])
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

        $latestRtomBreakdown = $latestAssignments->groupBy(fn($a) => $a->row->rtom ?? '—')->map(function($group, $rtom) use ($region, $latestReport) {
            $total = $group->count();
            $assigned = $group->whereNotNull('assigned_user_id')->count();
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            // Get supervisors for this RTOM
            $supervisorAssignment = 'supervisor_rtom_' . strtolower(str_replace(' ', '_', $rtom));
            $supervisors = User::where('assignment', $supervisorAssignment)->get();

            $supervisorProfits = [];
            foreach ($supervisors as $supervisor) {
                // Sum paid_amount from assignments of users supervised by this supervisor
                $profit = CallCenterAssignment::where('call_center_report_id', $latestReport?->id)
                    ->whereHas('row', function($q) use ($region, $rtom) {
                        $q->where('region', $region)->where('rtom', $rtom);
                    })->whereHas('agent', function($q) use ($supervisor) {
                        $q->where('supervisor', $supervisor->id);
                    })->sum('paid_amount');

                $supervisorProfits[] = [
                    'name' => $supervisor->name ?? $supervisor->username,
                    'profit' => $profit,
                ];
            }

            return [
                'rtom' => $rtom,
                'total' => $total,
                'assigned' => $assigned,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
                'supervisor_profits' => $supervisorProfits,
            ];
        })->values();

        // All-time data
        $allTimeBase = CallCenterAssignment::with(['row', 'agent', 'interactions'])
            ->whereHas('row', function($q) use ($region) {
                $q->where('region', $region);
            });

        $allTimeAssignments = $allTimeBase->get();
        $allTimeTotal = $allTimeAssignments->count();
        $allTimeAssigned = $allTimeAssignments->whereNotNull('assigned_user_id')->count();
        $allTimeUnassigned = $allTimeTotal - $allTimeAssigned;
        $allTimePaidCount = $allTimeAssignments->where('paid', true)->count();
        $allTimePaidAmount = $allTimeAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        $allTimeRtomBreakdown = $allTimeAssignments->groupBy(fn($a) => $a->row->rtom ?? '—')->map(function($group, $rtom) use ($region) {
            $total = $group->count();
            $assigned = $group->whereNotNull('assigned_user_id')->count();
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            // Get supervisors for this RTOM
            $supervisorAssignment = 'supervisor_rtom_' . strtolower(str_replace(' ', '_', $rtom));
            $supervisors = User::where('assignment', $supervisorAssignment)->get();

            $supervisorProfits = [];
            foreach ($supervisors as $supervisor) {
                // Sum paid_amount from assignments of users supervised by this supervisor
                $profit = CallCenterAssignment::whereHas('row', function($q) use ($region, $rtom) {
                    $q->where('region', $region)->where('rtom', $rtom);
                })->whereHas('agent', function($q) use ($supervisor) {
                    $q->where('supervisor', $supervisor->id);
                })->sum('paid_amount');

                $supervisorProfits[] = [
                    'name' => $supervisor->name ?? $supervisor->username,
                    'profit' => $profit,
                ];
            }

            return [
                'rtom' => $rtom,
                'total' => $total,
                'assigned' => $assigned,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
                'supervisor_profits' => $supervisorProfits,
            ];
        })->values();

        return view('cc.region.dashboard', compact(
            'region', 'latestReport',
            'latestTotal', 'latestAssigned', 'latestUnassigned', 'latestPaidCount', 'latestPaidAmount', 'latestRtomBreakdown',
            'allTimeTotal', 'allTimeAssigned', 'allTimeUnassigned', 'allTimePaidCount', 'allTimePaidAmount', 'allTimeRtomBreakdown',
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
            // Cutoff is reset every time gate is enabled.
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
            $reports = CallCenterReport::with('process')
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
                        ->pluck('master_dataset_row_id')
                        ->map(fn($id) => (int) $id)
                        ->all();
                } else {
                    $hiddenRowIds = $this->getDraftHiddenRowIds($selectedReport->id, $normalizedRegion);
                }

                $counts['total'] = count($rowIds);
                $counts['hidden'] = count(array_intersect($rowIds, $hiddenRowIds));
                $counts['visible'] = max(0, $counts['total'] - $counts['hidden']);

                $query = MasterDatasetRow::query()
                    ->whereIn('id', $rowIds)
                    ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion]);

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

                $reportRows = $query->orderBy('id')->paginate(50)->withQueryString();
                $reportRows->getCollection()->transform(function ($row) use ($hiddenRowIds) {
                    $row->is_hidden_for_distribution = in_array((int) $row->id, $hiddenRowIds, true);
                    return $row;
                });
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
        $report = CallCenterReport::findOrFail((int) $reportId);

        $gate = $this->currentRegionAdminReviewGate();
        $reviewOptIn = (bool) ($gate['opt_in'] ?? false);
        /** @var Carbon|null $reviewEnabledAt */
        $reviewEnabledAt = $gate['enabled_at'] ?? null;
        if (! $reviewOptIn) {
            return back()->withErrors(['rows' => 'Regional Review Gate is disabled.']);
        }
        if (! $this->isReportEligibleForCurrentGate($report, $reviewEnabledAt)) {
            return back()->withErrors(['rows' => 'This report was generated before Regional Review Gate was enabled and cannot be reviewed.']);
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
        $report = CallCenterReport::findOrFail((int) $reportId);

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

        DB::transaction(function () use ($report, $region, $normalizedRegion, $sessionUserId, $regionReportRowIds, $draftHiddenIds) {
            if (! empty($regionReportRowIds)) {
                CallCenterReportHiddenRow::where('call_center_report_id', $report->id)
                    ->whereIn('master_dataset_row_id', $regionReportRowIds)
                    ->delete();
            }

            $now = now();
            foreach ($draftHiddenIds as $rowId) {
                CallCenterReportHiddenRow::create([
                    'call_center_report_id' => $report->id,
                    'master_dataset_row_id' => (int) $rowId,
                    'hidden_by_user_id' => $sessionUserId > 0 ? $sessionUserId : null,
                    'hidden_at' => $now,
                ]);
            }

            if (! empty($draftHiddenIds)) {
                $auditRows = array_map(function (int $rowId) use ($report, $sessionUserId, $now) {
                    return [
                        'call_center_report_id' => $report->id,
                        'master_dataset_row_id' => $rowId,
                        'action' => 'hide',
                        'acted_by_user_id' => $sessionUserId > 0 ? $sessionUserId : null,
                        'acted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $draftHiddenIds);
                DB::table('call_center_report_row_actions')->insert($auditRows);
            }

            $review = CallCenterReportRegionReview::firstOrNew([
                'call_center_report_id' => $report->id,
                'region_name' => $region,
            ]);

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

    public function indexAssign()
    {
        $region = $this->ensureRegionAdmin();

        // For RTOM users, show only supervisor users they created
        if (\Illuminate\Support\Str::startsWith(session('user.assignment'), 'rtom_')) {
            $users = User::where('system', 'cc')
                ->where('supervisor', session('user')['id'] ?? null)
                ->where('assignment', 'like', 'supervisor_%')
                ->orderBy('id')
                ->get();
        } else {
            // Show call-center users added by the logged-in supervisor, not super admins and not already RTOM admins
            $users = User::where('system', 'cc')
                ->where('supervisor', session('user')['id'] ?? null)
                ->where(function ($q) {
                    $q->whereNull('assignment')
                        ->orWhere(function ($sq) {
                            $sq->where('assignment', '<>', 'super')
                                ->where('assignment', 'not like', 'rtom_%');
                        });
                })
                ->orderBy('id')
                ->get();
        }

        return view('cc.region.assign_index', compact('users', 'region'));
    }

    public function showAssignForm(User $user)
    {
        $region = $this->ensureRegionAdmin();

        $rtoms = $this->regionRtoms($region);

        return view('cc.region.assign', compact('user', 'rtoms', 'region'));
    }

    public function storeAssignment(Request $request, User $user)
    {
        $region = $this->ensureRegionAdmin();

        $request->validate([
            'rtom' => 'required|string|max:255',
        ]);

        $rtom = $request->input('rtom');

        $allowedRtoms = $this->regionRtoms($region);
        if (! $allowedRtoms->contains($rtom)) {
            return back()->withErrors(['rtom' => 'Selected RTO is not available in your region.']);
        }

        $user->admin_prev = 1;
        $user->assignment = 'rtom_' . preg_replace('/\s+/', '_', strtolower($rtom));
        $user->supervisor = session('user')['id'] ?? null;
        $user->save();

        return redirect()->route('cc.region.assign.index')->with('status', 'Assignment updated');
    }

    protected function ensureSupervisor()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }
        $assignment = $sessionUser['assignment'] ?? null;
        if (! $assignment || !str_starts_with($assignment, 'supervisor_')) {
            abort(403);
        }
        // Extract RTOM from assignment like 'supervisor_rtom_CODE'
        $rtom = str_replace('supervisor_rtom_', '', $assignment);
        $rtom = str_replace('_', ' ', $rtom);
        return $rtom; // RTOM name
    }

    public function supervisorDashboard()
    {
        $rtom = $this->ensureSupervisor();
        $supervisorId = session('user')['id'];

        // Get latest report data (most recent report with assignments for this RTOM)
        $latestReport = CallCenterReport::whereHas('assignments', function($q) use ($rtom) {
            $q->whereHas('row', function($rq) use ($rtom) {
                $rq->where('rtom', $rtom);
            });
        })->latest('created_at')->first();

        // Latest report data for this supervisor's RTOM
        $latestBase = CallCenterAssignment::with(['row', 'agent.supervisorUser', 'interactions'])
            ->where('call_center_report_id', $latestReport?->id)
            ->whereHas('row', function($q) use ($rtom) {
                $q->where('rtom', $rtom);
            });

        $latestAssignments = $latestBase->get();
        $latestTotal = $latestAssignments->count();
        $latestAssigned = $latestAssignments->whereNotNull('assigned_user_id')->count();
        $latestUnassigned = $latestTotal - $latestAssigned;
        $latestPaidCount = $latestAssignments->where('paid', true)->count();
        $latestPaidAmount = $latestAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        // For supervisor, breakdown by callers (agents in this RTOM)
        $latestCallerBreakdown = $latestAssignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function($group) {
            $agent = $group->first()->agent;
            $total = $group->count();
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            // Get supervisor info
            $supervisor = $agent ? $agent->supervisorUser : null;
            $supervisorLabel = $supervisor ? ($supervisor->id === session('user')['id'] ? 'Me' : ($supervisor->name ?? $supervisor->username)) : 'Unknown';

            return [
                'agent' => $this->formatAgentLabel($agent),
                'supervisor' => $supervisorLabel,
                'total' => $total,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
            ];
        })->values();

        // All-time data
        $allTimeBase = CallCenterAssignment::with(['row', 'agent.supervisorUser', 'interactions'])
            ->whereHas('row', function($q) use ($rtom) {
                $q->where('rtom', $rtom);
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
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            // Get supervisor info
            $supervisor = $agent ? $agent->supervisorUser : null;
            $supervisorLabel = $supervisor ? ($supervisor->id === session('user')['id'] ? 'Me' : ($supervisor->name ?? $supervisor->username)) : 'Unknown';

            return [
                'agent' => $this->formatAgentLabel($agent),
                'supervisor' => $supervisorLabel,
                'total' => $total,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
            ];
        })->values();

        return view('cc.supervisor.dashboard', compact(
            'rtom',
            'latestReport',
            'latestTotal',
            'latestAssigned',
            'latestUnassigned',
            'latestPaidCount',
            'latestPaidAmount',
            'latestCallerBreakdown',
            'allTimeTotal',
            'allTimeAssigned',
            'allTimeUnassigned',
            'allTimePaidCount',
            'allTimePaidAmount',
            'allTimeCallerBreakdown'
        ));
    }

    protected function ensureRtomAdmin()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }
        $assignment = $sessionUser['assignment'] ?? null;
        if (! $assignment || !str_starts_with($assignment, 'rtom_')) {
            abort(403);
        }
        // Extract RTOM from assignment like 'rtom_rtom_CODE'
        $rtom = str_replace('rtom_', '', $assignment);
        $rtom = str_replace('_', ' ', $rtom);
        return $rtom; // RTOM name
    }

    public function rtomDashboard()
    {
        $rtom = $this->ensureRtomAdmin();
        $rtomAdminId = session('user')['id'];

        // Get latest report data (most recent report with assignments for this RTOM)
        $latestReport = CallCenterReport::whereHas('assignments', function($q) use ($rtom) {
            $q->whereHas('row', function($rq) use ($rtom) {
                $rq->where('rtom', $rtom);
            });
        })->latest('created_at')->first();

        // Latest report data for this RTOM
        $latestBase = CallCenterAssignment::with(['row', 'agent', 'interactions'])
            ->where('call_center_report_id', $latestReport?->id)
            ->whereHas('row', function($q) use ($rtom) {
                $q->where('rtom', $rtom);
            });

        $latestAssignments = $latestBase->get();
        $latestTotal = $latestAssignments->count();
        $latestAssigned = $latestAssignments->whereNotNull('assigned_user_id')->count();
        $latestUnassigned = $latestTotal - $latestAssigned;
        $latestPaidCount = $latestAssignments->where('paid', true)->count();
        $latestPaidAmount = $latestAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        // For RTOM admin, breakdown by supervisors (supervisors they created)
        $latestSupervisorBreakdown = User::where('assignment', 'like', 'supervisor_%')
            ->where('supervisor', $rtomAdminId)
            ->get()
            ->map(function($supervisor) use ($latestReport, $rtom) {
                // Sum assignments handled by callers under this supervisor
                $assignments = CallCenterAssignment::where('call_center_report_id', $latestReport?->id)
                    ->whereHas('row', function($q) use ($rtom) {
                        $q->where('rtom', $rtom);
                    })
                    ->whereHas('agent', function($q) use ($supervisor) {
                        $q->where('supervisor', $supervisor->id);
                    })
                    ->get();

                $total = $assignments->count();
                $paid = $assignments->where('paid', true)->count();
                $paid_amount = $assignments->sum(fn($x) => $x->paid_amount ?? 0);

                // Get caller breakdown for this supervisor
                $callerProfits = $assignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function($group) {
                    $agent = $group->first()->agent;
                    $profit = $group->sum(fn($x) => $x->paid_amount ?? 0);
                    return [
                        'name' => $this->formatAgentLabel($agent),
                        'profit' => $profit,
                    ];
                })->values();

                return [
                    'supervisor' => $supervisor->name ?? $supervisor->username,
                    'total' => $total,
                    'paid' => $paid,
                    'paid_amount' => $paid_amount,
                    'caller_profits' => $callerProfits,
                ];
            })->values();

        // All-time data
        $allTimeBase = CallCenterAssignment::with(['row', 'agent', 'interactions'])
            ->whereHas('row', function($q) use ($rtom) {
                $q->where('rtom', $rtom);
            });

        $allTimeAssignments = $allTimeBase->get();
        $allTimeTotal = $allTimeAssignments->count();
        $allTimeAssigned = $allTimeAssignments->whereNotNull('assigned_user_id')->count();
        $allTimeUnassigned = $allTimeTotal - $allTimeAssigned;
        $allTimePaidCount = $allTimeAssignments->where('paid', true)->count();
        $allTimePaidAmount = $allTimeAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        $allTimeSupervisorBreakdown = User::where('assignment', 'like', 'supervisor_%')
            ->where('supervisor', $rtomAdminId)
            ->get()
            ->map(function($supervisor) use ($rtom) {
                // Sum assignments handled by callers under this supervisor
                $assignments = CallCenterAssignment::whereHas('row', function($q) use ($rtom) {
                    $q->where('rtom', $rtom);
                })
                ->whereHas('agent', function($q) use ($supervisor) {
                    $q->where('supervisor', $supervisor->id);
                })
                ->get();

                $total = $assignments->count();
                $paid = $assignments->where('paid', true)->count();
                $paid_amount = $assignments->sum(fn($x) => $x->paid_amount ?? 0);

                // Get caller breakdown for this supervisor
                $callerProfits = $assignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function($group) {
                    $agent = $group->first()->agent;
                    $profit = $group->sum(fn($x) => $x->paid_amount ?? 0);
                    return [
                        'name' => $this->formatAgentLabel($agent),
                        'profit' => $profit,
                    ];
                })->values();

                return [
                    'supervisor' => $supervisor->name ?? $supervisor->username,
                    'total' => $total,
                    'paid' => $paid,
                    'paid_amount' => $paid_amount,
                    'caller_profits' => $callerProfits,
                ];
            })->values();

        return view('cc.rtom.dashboard', compact(
            'rtom',
            'latestReport',
            'latestTotal',
            'latestAssigned',
            'latestUnassigned',
            'latestPaidCount',
            'latestPaidAmount',
            'latestSupervisorBreakdown',
            'allTimeTotal',
            'allTimeAssigned',
            'allTimeUnassigned',
            'allTimePaidCount',
            'allTimePaidAmount',
            'allTimeSupervisorBreakdown'
        ));
    }

    protected function formatAgentLabel($agent)
    {
        if (!$agent) return 'Unknown';
        
        $username = $agent->username;
        $name = $agent->name;
        
        return $name ? "{$username} ({$name})" : $username;
    }
}
