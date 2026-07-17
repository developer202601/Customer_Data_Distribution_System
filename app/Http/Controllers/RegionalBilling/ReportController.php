<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Models\CallCenterReportHiddenRow;
use App\Models\CallCenterReportRegionReview;
use App\Models\CallCenterReport;
use App\Models\MasterDatasetRow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReportController extends Controller
{
    protected function normalizeAssignment(?string $assignment): string
    {
        return strtolower(trim((string) $assignment));
    }

    protected function applyRegionFilter($query, ?string $region): void
    {
        $normalizedRegion = strtolower(trim((string) $region));
        $query->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion]);
    }

    protected function ensureRegionalBillingUser()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb') {
            abort(403);
        }

        return $sessionUser;
    }

    /**
     * Detect if user is an RTOM admin (assignment starts with 'rtom_')
     */
    protected function isRtomAdmin(?string $assignment): bool
    {
        $normalized = $this->normalizeAssignment($assignment);
        return $normalized !== '' && str_starts_with($normalized, 'rtom_');
    }

    /**
     * Detect if user is a region admin (not caller, not supervisor, not rtom, not super)
     */
    protected function isRegionAdmin(?string $assignment): bool
    {
        $normalized = $this->normalizeAssignment($assignment);
        if ($normalized === '') {
            return false;
        }
        return !str_starts_with($normalized, 'caller_')
            && !str_starts_with($normalized, 'supervisor_')
            && !str_starts_with($normalized, 'rtom_')
            && $normalized !== 'super';
    }

    /**
     * Extract RTOM value from assignment (e.g., 'rtom_kx' -> 'kx')
     */
    protected function extractRtomValue(string $assignment): ?string
    {
        $normalized = $this->normalizeAssignment($assignment);
        if (!str_starts_with($normalized, 'rtom_')) {
            return null;
        }
        return substr($normalized, 5); // remove 'rtom_' prefix
    }

    protected function isReviewLocked(?CallCenterReportRegionReview $reviewRecord): bool
    {
        return ! empty($reviewRecord?->reviewed_at);
    }

    protected function respondError(Request $request, string $message, int $status = 422)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => $message, 'errors' => ['rows' => [$message]]], $status);
        }

        return back()->withErrors(['rows' => $message]);
    }

    /**
     * Derive region from RTOM by looking up master_dataset_rows where rtom matches
     */
    protected function deriveRegionFromRtom(string $rtomValue): ?string
    {
        $query = MasterDatasetRow::query();

        // Get the latest process ID to restrict the search and avoid a full-table scan on millions of rows
        $latestProcessId = MasterDatasetRow::query()->max('process_id');
        if ($latestProcessId) {
            $query->where('process_id', $latestProcessId);
        }

        $region = $query
            ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower(trim($rtomValue))])
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->value('region');

        // Fallback to full table search in case it's not found in the latest process
        if (!$region) {
            $region = MasterDatasetRow::query()
                ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower(trim($rtomValue))])
                ->whereNotNull('region')
                ->where('region', '<>', '')
                ->value('region');
        }

        return $region;
    }

    protected function getRtomAdminRegion(User $user): ?string
    {
        $currentUser = $user->supervisor ? User::find($user->supervisor) : null;
        while ($currentUser) {
            $currentAssignment = $currentUser->assignment ?? null;
            if ($currentAssignment && !str_starts_with($currentAssignment, 'caller_') && !str_starts_with($currentAssignment, 'rtom_') && !str_starts_with($currentAssignment, 'supervisor_') && $currentAssignment !== 'super') {
                return $currentAssignment;
            }
            $currentUser = $currentUser->supervisor ? User::find($currentUser->supervisor) : null;
        }

        $assignment = strtolower(trim((string) $user->assignment));
        $rtomValue = preg_replace('/^rtom_/', '', $assignment);
        return $rtomValue ? $this->deriveRegionFromRtom($rtomValue) : null;
    }

    public function index(Request $request): View|JsonResponse
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);

        // Route RTOM admins to their allocation flow (not regional review)
        if ($this->isRtomAdmin($assignment)) {
            return $this->rtomReportsIndex($request);
        }

        // Route region admins (and other roles) to regional review
        return $this->reviewReport($request);
    }

    /**
     * Reports page for RTOM admins: show allocation interface for distributing to callers
     */
    protected function rtomReportsIndex(Request $request): View
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);
        $rtomValue = $this->extractRtomValue($assignment);

        $dbUser = User::find($sessionUser['id'] ?? 0);
        $region = $dbUser ? $this->getRtomAdminRegion($dbUser) : null;

        if (!$region || !$rtomValue) {
            return view('regionalbilling.reports.index', [
                'region' => $region,
                'rtom' => $rtomValue,
                'reports' => collect(),
                'selectedReport' => null,
            ]);
        }

        // Fetch regional billing reports that have rows for this RTOM and region
        $reports = CallCenterReport::regionalBilling()
            ->with('process')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (CallCenterReport $report) use ($rtomValue, $region) {
                $rowIds = collect($report->row_ids ?? [])->map(fn($id) => (int) $id)->filter(fn($id) => $id > 0)->values()->all();
                if (empty($rowIds)) {
                    return false;
                }
                // Check if any row has this RTOM and region
                $hasRows = MasterDatasetRow::whereIn('id', $rowIds)
                    ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower($rtomValue)])
                    ->whereRaw('LOWER(TRIM(region)) = ?', [strtolower($region)])
                    ->exists();

                if (!$hasRows) {
                    return false;
                }

                // Check if regional review is enabled for this region
                $gateUser = User::where('system', 'rb')
                    ->where('assignment', $region)
                    ->where('enable_regional_review', 1)
                    ->first();

                if ($gateUser) {
                    $enabledAt = $gateUser->enable_regional_review_enabled_at;
                    if ($enabledAt && $report->created_at && $report->created_at->greaterThanOrEqualTo($enabledAt)) {
                        // Check if the report has been passed for this specific RTOM
                        return \App\Models\CallCenterReportRtomPass::where('call_center_report_id', $report->id)
                            ->whereRaw('LOWER(TRIM(region_name)) = ?', [strtolower(trim($region))])
                            ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower(trim($rtomValue))])
                            ->exists();
                    }
                }

                return true;
            })
            ->values();

        $selectedReport = null;
        $requested = $request->query('report');
        if ($reports->isNotEmpty()) {
            if ($requested !== null) {
                $selectedReport = $reports->firstWhere('id', (int) $requested);
            }
            $selectedReport ??= $reports->first();
        }

        if (! $selectedReport) {
            return view('regionalbilling.reports.index', [
                'region' => $region,
                'rtom' => $rtomValue,
                'reports' => $reports,
                'selectedReport' => null,
            ]);
        }

        return $this->rtomReportSummary($request, $selectedReport, $assignment, $reports);
    }

    public function history(Request $request): View
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $region = $sessionUser['assignment'] ?? null;

        $reports = CallCenterReport::regionalBilling()
            ->whereHas('assignments.row', function ($query) use ($region) {
                $this->applyRegionFilter($query, $region);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('regionalbilling.reports.history', compact('reports', 'region'));
    }

    public function summary(Request $request, CallCenterReport $report): View|RedirectResponse|\Illuminate\Http\JsonResponse
    {
        abort_if($report->report_type !== CallCenterReport::REPORT_TYPE_REGIONAL_BILLING, 404);
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);

        if ($this->isRtomAdmin($assignment)) {
            return redirect()->route('rb.reports', ['report' => $report->id]);
        }

        $request->merge(['report' => (string) $report->id]);
        return $this->reviewReport($request);
    }

    protected function rtomReportSummary(Request $request, CallCenterReport $report, string $assignment, $reports = null): View
    {
        $rtom = $this->extractRtomValue($assignment);
        abort_if(! $rtom, 403, 'Invalid RTOM admin assignment.');

        $dbUser = User::find(session('user.id') ?? session('user')['id'] ?? 0);
        $region = $dbUser ? $this->getRtomAdminRegion($dbUser) : null;

        if (!$region) {
            abort(403, 'Could not determine region for your RTOM.');
        }

        // Check if regional review is enabled for this region
        $gateUser = User::where('system', 'rb')
            ->where('assignment', $region)
            ->where('enable_regional_review', 1)
            ->first();

        if ($gateUser) {
            $enabledAt = $gateUser->enable_regional_review_enabled_at;
            if ($enabledAt && $report->created_at && $report->created_at->greaterThanOrEqualTo($enabledAt)) {
                // Check if the report has been passed for this specific RTOM
                $passed = \App\Models\CallCenterReportRtomPass::where('call_center_report_id', $report->id)
                    ->whereRaw('LOWER(TRIM(region_name)) = ?', [strtolower(trim($region))])
                    ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower(trim($rtom))])
                    ->exists();
                if (!$passed) {
                    abort(403, 'This report is currently pending regional admin review and has not been passed to your RTOM yet.');
                }
            }
        }

        $search = trim((string) $request->query('q', ''));
        $reportRowIds = collect($report->row_ids ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();

        $hiddenRowIds = DB::table('call_center_report_hidden_rows')
            ->where('call_center_report_id', $report->id)
            ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
            ->pluck('master_dataset_row_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $visibleRowIds = array_values(array_diff($reportRowIds, $hiddenRowIds));

        $rtomScopedRowIds = empty($visibleRowIds)
            ? []
            : MasterDatasetRow::query()
                ->whereIn('id', $visibleRowIds)
                ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower($rtom)])
                ->whereRaw('LOWER(TRIM(region)) = ?', [strtolower($region)])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

        $rowsQuery = MasterDatasetRow::query()->whereIn('id', $rtomScopedRowIds);
        if ($search !== '') {
            $rowsQuery->where(function ($q) use ($search) {
                $q->where('account_num', 'like', '%' . $search . '%')
                    ->orWhere('customer_ref', 'like', '%' . $search . '%')
                    ->orWhere('mobile_contact_tel', 'like', '%' . $search . '%')
                    ->orWhere('new_arrears_value', 'like', '%' . $search . '%');
            });
        }

        $rows = $rowsQuery->orderBy('id')->paginate(15)->withQueryString();

        $assignmentRows = empty($rtomScopedRowIds)
            ? collect()
            : DB::table('call_center_row_assignments as a')
                ->leftJoin('users as u', 'u.id', '=', 'a.assigned_user_id')
                ->where('a.call_center_report_id', $report->id)
                ->where('a.report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
                ->whereIn('a.master_dataset_row_id', $rtomScopedRowIds)
                ->select([
                    'a.master_dataset_row_id',
                    'a.assigned_user_id',
                    'a.accepted',
                    'a.rejected',
                    'a.status',
                    'u.username as agent_username',
                ])
                ->get()
                ->keyBy('master_dataset_row_id');

        $rows->getCollection()->transform(function ($row) use ($assignmentRows) {
            $a = $assignmentRows->get((int) $row->id);
            $row->assigned_user_id = $a->assigned_user_id ?? null;
            $row->assigned_username = $a->agent_username ?? null;
            $row->accepted = (bool) ($a->accepted ?? false);
            $row->rejected = (bool) ($a->rejected ?? false);
            $row->assignment_status = $a->status ?? null;
            return $row;
        });

        $assigned = $assignmentRows->filter(fn ($a) => ! empty($a->assigned_user_id))->count();
        $total = count($rtomScopedRowIds);
        $unassigned = max(0, $total - $assigned);
        $hidden = empty($reportRowIds)
            ? 0
            : MasterDatasetRow::query()
                ->whereIn('id', $hiddenRowIds)
                ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower($rtom)])
                ->count();

        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        $callers = User::query()
            ->where('system', 'rb')
            ->where('status', 1)
            ->where('assignment', 'like', 'caller_%')
            ->where('supervisor', $sessionUserId)
            ->orderBy('username')
            ->get();

        $pendingCounts = [];
        foreach ($callers as $caller) {
            $pendingCounts[$caller->id] = DB::table('call_center_row_assignments')
                ->where('call_center_report_id', $report->id)
                ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
                ->whereIn('master_dataset_row_id', $rtomScopedRowIds)
                ->where('assigned_user_id', $caller->id)
                ->where(function ($q) {
                    $q->whereNull('accepted')->orWhere('accepted', false);
                })
                ->where(function ($q) {
                    $q->whereNull('rejected')->orWhere('rejected', false);
                })
                ->count();
        }

        $acceptedCounts = DB::table('call_center_row_assignments')
            ->select('assigned_user_id', DB::raw('COUNT(*) as count'))
            ->where('call_center_report_id', $report->id)
            ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
            ->whereIn('master_dataset_row_id', $rtomScopedRowIds)
            ->whereNotNull('assigned_user_id')
            ->where('accepted', true)
            ->groupBy('assigned_user_id')
            ->pluck('count', 'assigned_user_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        $rejectedCounts = DB::table('call_center_row_assignments')
            ->select('assigned_user_id', DB::raw('COUNT(*) as count'))
            ->where('call_center_report_id', $report->id)
            ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
            ->whereIn('master_dataset_row_id', $rtomScopedRowIds)
            ->whereNotNull('assigned_user_id')
            ->where('rejected', true)
            ->groupBy('assigned_user_id')
            ->pluck('count', 'assigned_user_id')
            ->map(fn ($count) => (int) $count)
            ->all();

        $anyAssigned = $assigned > 0;

        return view('regionalbilling.reports.summary', [
            'report' => $report,
            'reports' => $reports,
            'region' => $region,
            'rtom' => $rtom,
            'assigned' => $assigned,
            'unassigned' => $unassigned,
            'hidden' => $hidden,
            'reviews' => 0,
            'rows' => $rows,
            'search' => $search,
            'callers' => $callers,
            'pendingCounts' => $pendingCounts,
            'acceptedCounts' => $acceptedCounts,
            'rejectedCounts' => $rejectedCounts,
            'anyAssigned' => $anyAssigned,
        ]);
    }

    public function updateReviewPreference(Request $request): RedirectResponse
    {
        $this->ensureRegionalBillingUser();

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

    public function reviewReport(Request $request): View|\Illuminate\Http\JsonResponse
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);

        // Enforce: only region admins can access regional review (not RTOM admins, callers, supervisors)
        if (!$this->isRegionAdmin($assignment)) {
            abort(403, 'Regional review is only available for region admins.');
        }

        $region = $assignment;
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
            $reports = CallCenterReport::regionalBilling()->with('process')
                ->where('created_at', '>=', $reviewEnabledAt)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->filter(function (CallCenterReport $candidate) use ($normalizedRegion) {
                    $ids = collect($candidate->row_ids ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
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

            $rowIds = collect($selectedReport->row_ids ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
            if (! empty($rowIds)) {
                if ($isLocked) {
                    $hiddenRowIds = CallCenterReportHiddenRow::where('call_center_report_id', $selectedReport->id)
                        ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
                        ->pluck('master_dataset_row_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                } else {
                    $hiddenRowIds = $this->getDraftHiddenRowIds($selectedReport->id, $normalizedRegion);
                }

                $regionRowIds = MasterDatasetRow::query()
                    ->whereIn('id', $rowIds)
                    ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $hiddenRowIds = array_values(array_intersect($hiddenRowIds, $regionRowIds));
                $counts['total'] = count($regionRowIds);
                $counts['hidden'] = count($hiddenRowIds);
                $counts['visible'] = max(0, $counts['total'] - $counts['hidden']);

                if (! empty($regionRowIds)) {
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

        $rtomsWithDetails = [];
        if ($selectedReport && isset($regionRowIds) && !empty($regionRowIds)) {
            // Get unique RTOMs and their record counts for this region in this report
            $rtomCounts = MasterDatasetRow::whereIn('id', $regionRowIds)
                ->whereNotNull('rtom')
                ->where('rtom', '<>', '')
                ->select('rtom', DB::raw('count(*) as count'))
                ->groupBy('rtom')
                ->pluck('count', 'rtom')
                ->all();

            // Fetch existing passes for these RTOMs
            $rtomPasses = \App\Models\CallCenterReportRtomPass::with('passedBy')
                ->where('call_center_report_id', $selectedReport->id)
                ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
                ->get()
                ->keyBy(fn($p) => strtolower(trim($p->rtom)));

            foreach ($rtomCounts as $rtomName => $count) {
                $passRecord = $rtomPasses->get(strtolower(trim($rtomName)));
                $rtomsWithDetails[] = [
                    'name' => $rtomName,
                    'count' => $count,
                    'is_passed' => $passRecord !== null,
                    'passed_at' => $passRecord?->passed_at,
                    'passed_by' => $passRecord?->passedBy?->username ?? $passRecord?->passedBy?->name ?? 'System',
                ];
            }

            // Sort RTOMs alphabetically
            usort($rtomsWithDetails, fn($a, $b) => strcmp($a['name'], $b['name']));
        }

        $passedRtomNames = [];
        if (!empty($rtomsWithDetails)) {
            foreach ($rtomsWithDetails as $rwd) {
                if ($rwd['is_passed']) {
                    $passedRtomNames[] = strtolower(trim($rwd['name']));
                }
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            $canUnlockReview = $this->isRegionAdmin($assignment);
            $tableHtml = view('regionalbilling.reports._review_table', [
                'selectedReport' => $selectedReport,
                'rows' => $reportRows,
                'showHidden' => $showHidden,
                'showHiddenOnly' => $showHiddenOnly,
                'isLocked' => $isLocked,
                'search' => $search,
                'passedRtomNames' => $passedRtomNames,
                'rtomsWithDetails' => $rtomsWithDetails,
                'canUnlockReview' => $canUnlockReview,
            ])->render();

            return response()->json([
                'table_html' => $tableHtml,
                'counts' => $counts,
                'reviewed_at' => optional($reviewRecord?->reviewed_at)?->toDateTimeString(),
                'is_locked' => $isLocked,
            ]);
        }

        $canUnlockReview = $this->isRegionAdmin($assignment);

        return view('regionalbilling.reports.review', [
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
            'canUnlockReview' => $canUnlockReview,
            'rtomsWithDetails' => $rtomsWithDetails,
            'passedRtomNames' => $passedRtomNames,
        ]);
    }

    public function hideRows(Request $request, int $reportId): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);
        
        // Only region admins can hide/unhide rows
        if (!$this->isRegionAdmin($assignment)) {
            abort(403, 'Only region admins can modify row visibility.');
        }
        
        $region = $assignment;
        $normalizedRegion = $this->normalizeRegionName($region);
        $report = CallCenterReport::regionalBilling()->findOrFail($reportId);

        $respondError = function (string $message, int $status = 422) use ($request) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['message' => $message, 'errors' => ['rows' => [$message]]], $status);
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
        $rowIds = collect($data['row_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
        $reportRowIds = collect($report->row_ids ?? [])->map(fn ($id) => (int) $id)->all();
        $validIds = array_values(array_intersect($rowIds, $reportRowIds));

        if (empty($validIds)) {
            return $respondError('No valid rows were selected.');
        }

        $regionScopedIds = MasterDatasetRow::whereIn('id', $validIds)
            ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($regionScopedIds)) {
            return $respondError('Selected rows are outside your region.');
        }

        $reviewRecord = CallCenterReportRegionReview::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->first();
        if ($this->isReviewLocked($reviewRecord)) {
            // If the whole region is locked, block
            return $this->respondError($request, 'This review is already passed and locked.', 423);
        }

        // Get passed RTOMs for this report and region
        $passedRtoms = \App\Models\CallCenterReportRtomPass::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->pluck('rtom')
            ->map(fn($r) => strtolower(trim($r)))
            ->all();

        if (!empty($passedRtoms)) {
            // Check if any of the regionScopedIds belong to a passed RTOM
            $hasPassedRtomRows = MasterDatasetRow::whereIn('id', $regionScopedIds)
                ->whereIn(DB::raw('LOWER(TRIM(rtom))'), $passedRtoms)
                ->exists();
            if ($hasPassedRtomRows) {
                return $respondError('Some selected rows belong to RTOMs that have already been passed and locked.', 423);
            }
        }

        $draftHiddenIds = $this->getDraftHiddenRowIds($report->id, $normalizedRegion);

        if ($action === 'unhide') {
            $draftHiddenIds = array_values(array_diff($draftHiddenIds, $regionScopedIds));
            $this->putDraftHiddenRowIds($report->id, $normalizedRegion, $draftHiddenIds);

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'message' => count($regionScopedIds) . ' row(s) set as visible in draft review. Use Pass to make it permanent.',
                ]);
            }

            return back()->with('status', count($regionScopedIds) . ' row(s) set as visible in draft review. Use Pass to make it permanent.');
        }

        $draftHiddenIds = collect($draftHiddenIds)
            ->merge($regionScopedIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $this->putDraftHiddenRowIds($report->id, $normalizedRegion, $draftHiddenIds);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => count($regionScopedIds) . ' row(s) hidden in draft review. Use Pass to make it permanent.',
            ]);
        }

        return back()->with('status', count($regionScopedIds) . ' row(s) hidden in draft review. Use Pass to make it permanent.');
    }

    public function submitExcludeFile(Request $request, int $reportId): RedirectResponse|JsonResponse
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);

        if (! $this->isRegionAdmin($assignment)) {
            abort(403, 'Only region admins can upload exclusion files.');
        }

        $region = $assignment;
        $normalizedRegion = $this->normalizeRegionName($region);
        $report = CallCenterReport::regionalBilling()->findOrFail($reportId);

        $gate = $this->currentRegionAdminReviewGate();
        $reviewOptIn = (bool) ($gate['opt_in'] ?? false);
        /** @var Carbon|null $reviewEnabledAt */
        $reviewEnabledAt = $gate['enabled_at'] ?? null;
        if (! $reviewOptIn) {
            return $this->respondError($request, 'Regional Review Gate is disabled.');
        }
        if (! $this->isReportEligibleForCurrentGate($report, $reviewEnabledAt)) {
            return $this->respondError($request, 'This report was generated before Regional Review Gate was enabled and cannot be reviewed.');
        }

        $reviewRecord = CallCenterReportRegionReview::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->first();
        if ($this->isReviewLocked($reviewRecord)) {
            return $this->respondError($request, 'This review is already passed and locked.', 423);
        }

        $data = $request->validate([
            'exclude_file' => 'required|file|mimes:xlsx|max:20480',
        ]);

        /** @var UploadedFile $file */
        $file = $data['exclude_file'];
        $accountNumbers = $this->extractExcludeAccountNumbers($file);

        if (empty($accountNumbers)) {
            return $this->respondError($request, 'The uploaded exclusion file did not contain any account numbers.');
        }

        $reportRowIds = collect($report->row_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (empty($reportRowIds)) {
            return $this->respondError($request, 'This report has no rows available for exclusion.');
        }

        $matchingRowIds = MasterDatasetRow::query()
            ->whereIn('id', $reportRowIds)
            ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
            ->whereIn('account_num', $accountNumbers)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($matchingRowIds)) {
            return $this->respondError($request, 'No rows in this report matched the uploaded exclusion file.');
        }

        $draftHiddenIds = array_values(array_unique(array_merge(
            $this->getDraftHiddenRowIds($report->id, $normalizedRegion),
            $matchingRowIds
        )));
        $this->putDraftHiddenRowIds($report->id, $normalizedRegion, $draftHiddenIds);

        $message = count($matchingRowIds) . ' row(s) excluded from draft review.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'matched_rows' => count($matchingRowIds),
            ]);
        }

        return redirect()->route('rb.reports', ['report' => $report->id])
            ->with('status', $message . ' Use Pass to make it permanent.');
    }

    public function submitIncludeFile(Request $request, int $reportId): RedirectResponse|JsonResponse
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);

        if (! $this->isRegionAdmin($assignment)) {
            abort(403, 'Only region admins can upload inclusion files.');
        }

        $region = $assignment;
        $normalizedRegion = $this->normalizeRegionName($region);
        $report = CallCenterReport::regionalBilling()->findOrFail($reportId);

        $gate = $this->currentRegionAdminReviewGate();
        $reviewOptIn = (bool) ($gate['opt_in'] ?? false);
        /** @var Carbon|null $reviewEnabledAt */
        $reviewEnabledAt = $gate['enabled_at'] ?? null;
        if (! $reviewOptIn) {
            return $this->respondError($request, 'Regional Review Gate is disabled.');
        }
        if (! $this->isReportEligibleForCurrentGate($report, $reviewEnabledAt)) {
            return $this->respondError($request, 'This report was generated before Regional Review Gate was enabled and cannot be reviewed.');
        }

        $reviewRecord = CallCenterReportRegionReview::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->first();
        if ($this->isReviewLocked($reviewRecord)) {
            return $this->respondError($request, 'This review is already passed and locked.', 423);
        }

        $data = $request->validate([
            'include_file' => 'required|file|mimes:xlsx|max:20480',
        ]);

        /** @var UploadedFile $file */
        $file = $data['include_file'];
        $identifiers = $this->extractIncludeIdentifiers($file);

        if (empty($identifiers)) {
            return $this->respondError($request, 'The uploaded inclusion file did not contain any usable identifiers.');
        }

        $reportRowIds = collect($report->row_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (empty($reportRowIds)) {
            return $this->respondError($request, 'This report has no rows available for inclusion.');
        }

        $reportRows = MasterDatasetRow::query()
            ->whereIn('id', $reportRowIds)
            ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
            ->get(['id', 'customer_ref', 'account_num', 'product_label', 'mobile_contact_tel', 'new_arrears_value', 'region', 'rtom']);

        $matchingRows = $reportRows
            ->filter(function (MasterDatasetRow $row) use ($identifiers) {
                return in_array($this->normalizeLookupValue($row->customer_ref), $identifiers, true)
                    || in_array($this->normalizeLookupValue($row->account_num), $identifiers, true)
                    || in_array($this->normalizeLookupValue($row->product_label), $identifiers, true);
            })
            ->values();

        $matchingRowIds = $matchingRows
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($matchingRowIds)) {
            return $this->respondError($request, 'No rows in this report matched the uploaded inclusion file.');
        }

        // Hide all rows that are NOT in the inclusion file; unhide those that ARE in it
        $draftHiddenIds = array_values(array_diff($reportRowIds, $matchingRowIds));
        $this->putDraftHiddenRowIds($report->id, $normalizedRegion, $draftHiddenIds);

        $previewRows = $matchingRows->map(function (MasterDatasetRow $row) use ($identifiers) {
            $matchedBy = null;
            
            $customerRefNorm = $this->normalizeLookupValue($row->customer_ref);
            $accountNumNorm = $this->normalizeLookupValue($row->account_num);
            $productLabelNorm = $this->normalizeLookupValue($row->product_label);
            
            if ($customerRefNorm !== '' && in_array($customerRefNorm, $identifiers, true)) {
                $matchedBy = 'Customer Ref';
            } elseif ($accountNumNorm !== '' && in_array($accountNumNorm, $identifiers, true)) {
                $matchedBy = 'Account Num';
            } elseif ($productLabelNorm !== '' && in_array($productLabelNorm, $identifiers, true)) {
                $matchedBy = 'Product Label';
            }

            return [
                'id' => (int) $row->id,
                'account_num' => $row->account_num,
                'customer_ref' => $row->customer_ref,
                'product_label' => $row->product_label,
                'mobile_contact_tel' => $row->mobile_contact_tel,
                'new_arrears_value' => $row->new_arrears_value,
                'region' => $row->region,
                'rtom' => $row->rtom,
                'matched_by' => $matchedBy,
            ];
        })->all();

        $message = count($matchingRowIds) . ' row(s) retained by inclusion file.';

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'message' => $message,
                'matched_rows' => count($matchingRowIds),
                'preview_rows' => $previewRows,
            ]);
        }

        return redirect()->route('rb.reports', ['report' => $report->id])
            ->with('status', $message . ' Use Pass to make it permanent.')
            ->with('rb.reports.include_preview', $previewRows)
            ->with('rb.reports.include_preview_count', count($previewRows));
    }

    public function passReport(Request $request, int $reportId): RedirectResponse
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);
        
        // Only region admins can pass reports
        if (!$this->isRegionAdmin($assignment)) {
            abort(403, 'Only region admins can pass reports for regional review.');
        }

        $rtomInput = trim((string) $request->input('rtom'));
        if ($rtomInput === '') {
            return redirect()->route('rb.reports', ['report' => $reportId])
                ->withErrors(['review' => 'RTOM is required to pass records.']);
        }
        
        $region = $assignment;
        $normalizedRegion = $this->normalizeRegionName($region);
        $report = CallCenterReport::regionalBilling()->findOrFail($reportId);

        $gate = $this->currentRegionAdminReviewGate();
        $reviewOptIn = (bool) ($gate['opt_in'] ?? false);
        /** @var Carbon|null $reviewEnabledAt */
        $reviewEnabledAt = $gate['enabled_at'] ?? null;
        if (! $reviewOptIn) {
            return redirect()->route('rb.reports', ['report' => $report->id])
                ->withErrors(['review' => 'Regional Review Gate is disabled.']);
        }
        if (! $this->isReportEligibleForCurrentGate($report, $reviewEnabledAt)) {
            return redirect()->route('rb.reports', ['report' => $report->id])
                ->withErrors(['review' => 'This report was generated before Regional Review Gate was enabled and cannot be reviewed.']);
        }

        // Check if this specific RTOM has already been passed
        $existingPass = \App\Models\CallCenterReportRtomPass::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower($rtomInput)])
            ->first();
        if ($existingPass) {
            return redirect()->route('rb.reports', ['report' => $report->id])
                ->withErrors(['review' => "Records for RTOM {$rtomInput} have already been passed and cannot be changed."]);
        }

        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        $reportRowIds = collect($report->row_ids ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
        
        // Find row IDs belonging to this region AND this specific RTOM
        $rtomRowIds = empty($reportRowIds)
            ? []
            : MasterDatasetRow::whereIn('id', $reportRowIds)
                ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
                ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower($rtomInput)])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

        $draftHiddenIds = $this->getDraftHiddenRowIds($report->id, $normalizedRegion);
        
        // Only finalize hidden rows that belong to this RTOM
        $hiddenIdsToCommit = array_values(array_intersect($draftHiddenIds, $rtomRowIds));

        DB::transaction(function () use ($report, $region, $normalizedRegion, $sessionUserId, $rtomRowIds, $hiddenIdsToCommit, $rtomInput) {
            // Delete existing committed exclusions for this RTOM
            if (! empty($rtomRowIds)) {
                CallCenterReportHiddenRow::where('call_center_report_id', $report->id)
                    ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
                    ->whereIn('master_dataset_row_id', $rtomRowIds)
                    ->delete();
            }

            $now = now();
            // Commit new exclusions for this RTOM
            foreach ($hiddenIdsToCommit as $rowId) {
                CallCenterReportHiddenRow::create([
                    'call_center_report_id' => $report->id,
                    'report_type' => CallCenterReport::REPORT_TYPE_REGIONAL_BILLING,
                    'master_dataset_row_id' => (int) $rowId,
                    'hidden_by_user_id' => $sessionUserId > 0 ? $sessionUserId : null,
                    'hidden_at' => $now,
                ]);
            }

            if (! empty($hiddenIdsToCommit)) {
                $auditRows = array_map(function (int $rowId) use ($report, $sessionUserId, $now) {
                    return [
                        'call_center_report_id' => $report->id,
                        'report_type' => CallCenterReport::REPORT_TYPE_REGIONAL_BILLING,
                        'master_dataset_row_id' => $rowId,
                        'action' => 'hide',
                        'acted_by_user_id' => $sessionUserId > 0 ? $sessionUserId : null,
                        'acted_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $hiddenIdsToCommit);
                DB::table('call_center_report_row_actions')->insert($auditRows);
            }

            // Create the RTOM pass record
            \App\Models\CallCenterReportRtomPass::create([
                'call_center_report_id' => $report->id,
                'region_name' => $region,
                'rtom' => $rtomInput,
                'passed_by_user_id' => $sessionUserId > 0 ? $sessionUserId : null,
                'passed_at' => $now,
            ]);

            // Create a general region review record if it doesn't exist, to keep it compatible with general queries
            $review = CallCenterReportRegionReview::firstOrCreate([
                'call_center_report_id' => $report->id,
                'region_name' => $region,
            ]);
            $review->report_type = CallCenterReport::REPORT_TYPE_REGIONAL_BILLING;
            $review->reviewed_by_user_id = $sessionUserId > 0 ? $sessionUserId : null;
            $review->reviewed_at = $now;
            $review->save();
        });

        // Remove these committed row IDs from the draft cache
        if (!empty($rtomRowIds)) {
            $remainingDraftHiddenIds = array_values(array_diff($draftHiddenIds, $rtomRowIds));
            $this->putDraftHiddenRowIds($report->id, $normalizedRegion, $remainingDraftHiddenIds);
        }

        return redirect()->route('rb.reports', ['report' => $report->id])
            ->with('status', "Records for RTOM " . strtoupper($rtomInput) . " passed successfully.");
    }

    public function unlockReview(Request $request, int $reportId): RedirectResponse|JsonResponse
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);

        if (!$this->isRegionAdmin($assignment)) {
            abort(403, 'Only region admins can unlock reviews.');
        }

        $rtomInput = trim((string) $request->input('rtom'));
        if ($rtomInput === '') {
            return redirect()->route('rb.reports', ['report' => $reportId])
                ->withErrors(['review' => 'RTOM is required to unlock records.']);
        }

        $region = $assignment;
        $normalizedRegion = $this->normalizeRegionName($region);
        $report = CallCenterReport::regionalBilling()->findOrFail($reportId);

        $gate = $this->currentRegionAdminReviewGate();
        $reviewOptIn = (bool) ($gate['opt_in'] ?? false);
        /** @var Carbon|null $reviewEnabledAt */
        $reviewEnabledAt = $gate['enabled_at'] ?? null;
        if (! $reviewOptIn) {
            return $this->respondError($request, 'Regional Review Gate is disabled.');
        }
        if (! $this->isReportEligibleForCurrentGate($report, $reviewEnabledAt)) {
            return $this->respondError($request, 'This report was generated before Regional Review Gate was enabled and cannot be reviewed.');
        }

        // Delete the pass record
        \App\Models\CallCenterReportRtomPass::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower($rtomInput)])
            ->delete();

        // Get committed exclusions for this RTOM
        $reportRowIds = collect($report->row_ids ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
        $rtomRowIds = empty($reportRowIds)
            ? []
            : MasterDatasetRow::whereIn('id', $reportRowIds)
                ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
                ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower($rtomInput)])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

        $committedHiddenIds = CallCenterReportHiddenRow::where('call_center_report_id', $report->id)
            ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
            ->whereIn('master_dataset_row_id', $rtomRowIds)
            ->pluck('master_dataset_row_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Move them back to draft cache
        $draftHiddenIds = $this->getDraftHiddenRowIds($report->id, $normalizedRegion);
        $newDraftHiddenIds = array_values(array_unique(array_merge($draftHiddenIds, $committedHiddenIds)));
        $this->putDraftHiddenRowIds($report->id, $normalizedRegion, $newDraftHiddenIds);

        // Delete from database committed table
        if (!empty($committedHiddenIds)) {
            CallCenterReportHiddenRow::where('call_center_report_id', $report->id)
                ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
                ->whereIn('master_dataset_row_id', $committedHiddenIds)
                ->delete();
        }

        // Also check if any RTOMs are still passed. If none are, delete the regional review record
        $anyPassed = \App\Models\CallCenterReportRtomPass::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->exists();
        if (!$anyPassed) {
            CallCenterReportRegionReview::where('call_center_report_id', $report->id)
                ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
                ->delete();
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => 'Review unlocked for RTOM ' . strtoupper($rtomInput)]);
        }

        return redirect()->route('rb.reports', ['report' => $report->id])
            ->with('status', 'Review unlocked for RTOM ' . strtoupper($rtomInput));
    }

    public function getAgentDetails(Request $request)
    {
        $this->ensureRegionalBillingUser();

        return response()->json([]);
    }

    public function download(): RedirectResponse
    {
        $this->ensureRegionalBillingUser();

        return redirect()->back()->withErrors(['download' => 'Report download is not yet implemented for RBC.']);
    }

    public function distributeSupervisor(): RedirectResponse
    {
        $this->ensureRegionalBillingUser();

        return redirect()->back()->withErrors(['distribute' => 'Supervisor distribution is not yet implemented for RBC.']);
    }

    private function draftHiddenRowsSessionKey(int $reportId, string $normalizedRegion): string
    {
        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        $userKey = $sessionUserId > 0 ? (string) $sessionUserId : 'guest';

        return 'rb.region.review.draft_hidden.' . $userKey . '.' . $reportId . '.' . md5($normalizedRegion);
    }

    private function getDraftHiddenRowIds(int $reportId, string $normalizedRegion): array
    {
        $raw = session($this->draftHiddenRowsSessionKey($reportId, $normalizedRegion), []);

        return collect(is_array($raw) ? $raw : [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function putDraftHiddenRowIds(int $reportId, string $normalizedRegion, array $rowIds): void
    {
        session([
            $this->draftHiddenRowsSessionKey($reportId, $normalizedRegion) => collect($rowIds)
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
        ]);
    }

    private function clearDraftHiddenRowIds(int $reportId, string $normalizedRegion): void
    {
        session()->forget($this->draftHiddenRowsSessionKey($reportId, $normalizedRegion));
    }

    /**
     * @return array<int, string>
     */
    private function extractExcludeAccountNumbers(UploadedFile $file): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());

        $accounts = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $accountColumn = null;
            $highestRow = $sheet->getHighestDataRow();

            foreach ($sheet->getRowIterator(1, $highestRow) as $row) {
                $rowIndex = (int) $row->getRowIndex();
                $cells = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                foreach ($cellIterator as $cell) {
                    $cells[$cell->getColumn()] = $cell->getFormattedValue();
                }

                if ($rowIndex === 1) {
                    foreach ($cells as $column => $value) {
                        if ($this->normalizeHeaderLabel((string) $value) === 'ACCOUNTNUM') {
                            $accountColumn = $column;
                            break;
                        }
                    }

                    if (! $accountColumn) {
                        throw ValidationException::withMessages([
                            'exclude_file' => 'The uploaded file must contain an ACCOUNT_NUM column.',
                        ]);
                    }

                    continue;
                }

                if (! $accountColumn) {
                    continue;
                }

                $account = trim((string) ($cells[$accountColumn] ?? ''));
                if ($account !== '') {
                    $accounts[] = $account;
                }
            }
        }

        return array_values(array_unique($accounts));
    }

    private function extractIncludeIdentifiers(UploadedFile $file): array
    {
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());

        $identifiers = [];
        // Match the normalized header format used below: uppercase alphanumeric only.
        $allowedHeaders = ['CUSTOMERREF', 'ACCOUNTNUM', 'PRODUCTLABEL'];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $headerColumns = [];
            $highestRow = $sheet->getHighestDataRow();

            foreach ($sheet->getRowIterator(1, $highestRow) as $row) {
                $rowIndex = (int) $row->getRowIndex();
                $cells = [];
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                foreach ($cellIterator as $cell) {
                    $cells[$cell->getColumn()] = $cell->getFormattedValue();
                }

                if ($rowIndex === 1) {
                    foreach ($cells as $column => $value) {
                        $normalizedHeader = $this->normalizeHeaderLabel((string) $value);
                        if (in_array($normalizedHeader, $allowedHeaders, true)) {
                            $headerColumns[$column] = $normalizedHeader;
                        }
                    }

                    if (empty($headerColumns)) {
                        throw ValidationException::withMessages([
                            'include_file' => 'The uploaded file must contain at least one of CUSTOMER_REF, ACCOUNT_NUM, or PRODUCT_LABEL columns.',
                        ]);
                    }

                    continue;
                }

                foreach ($headerColumns as $column => $headerName) {
                    $value = $this->normalizeLookupValue((string) ($cells[$column] ?? ''));
                    if ($value !== '') {
                        $identifiers[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($identifiers));
    }

    private function normalizeLookupValue(string $value): string
    {
        return Str::lower(trim($value));
    }

    private function normalizeHeaderLabel(string $value): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]+/', '', trim($value)) ?: '');
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
}
