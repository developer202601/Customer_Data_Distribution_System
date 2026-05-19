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
        $region = MasterDatasetRow::query()
            ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower(trim($rtomValue))])
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->distinct()
            ->pluck('region')
            ->first();

        return $region;
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
        $region = $rtomValue ? $this->deriveRegionFromRtom($rtomValue) : null;

        if (!$region || !$rtomValue) {
            return view('regionalbilling.reports.index', [
                'region' => $region,
                'rtom' => $rtomValue,
                'reports' => collect(),
                'selectedReport' => null,
            ]);
        }

        // Fetch regional billing reports that have rows for this RTOM
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
                return MasterDatasetRow::whereIn('id', $rowIds)
                    ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower($rtomValue)])
                    ->whereRaw('LOWER(TRIM(region)) = ?', [strtolower($region)])
                    ->exists();
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
        $region = $rtom ? $this->deriveRegionFromRtom($rtom) : null;

        abort_if(! $rtom, 403, 'Invalid RTOM admin assignment.');

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
                ->when(! empty($region), function ($query) use ($region) {
                    $query->whereRaw('LOWER(TRIM(region)) = ?', [strtolower((string) $region)]);
                })
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

        if ($request->ajax() || $request->wantsJson()) {
            $tableHtml = view('regionalbilling.reports._review_table', [
                'selectedReport' => $selectedReport,
                'rows' => $reportRows,
                'showHidden' => $showHidden,
                'showHiddenOnly' => $showHiddenOnly,
                'isLocked' => $isLocked,
                'search' => $search,
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
            return $this->respondError($request, 'This review is already passed and locked.', 423);
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

        $existingReview = CallCenterReportRegionReview::where('call_center_report_id', $report->id)
            ->whereRaw('LOWER(TRIM(region_name)) = ?', [$normalizedRegion])
            ->first();
        if (! empty($existingReview?->reviewed_at)) {
            return redirect()->route('rb.reports', ['report' => $report->id])
                ->withErrors(['review' => 'This report has already been passed for your region and cannot be changed.']);
        }

        $sessionUserId = (int) (session('user.id') ?? session('user')['id'] ?? 0);
        $reportRowIds = collect($report->row_ids ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
        $regionReportRowIds = empty($reportRowIds)
            ? []
            : MasterDatasetRow::whereIn('id', $reportRowIds)
                ->whereRaw('LOWER(TRIM(region)) = ?', [$normalizedRegion])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

        $draftHiddenIds = array_values(array_intersect(
            $this->getDraftHiddenRowIds($report->id, $normalizedRegion),
            $regionReportRowIds
        ));

        DB::transaction(function () use ($report, $region, $normalizedRegion, $sessionUserId, $regionReportRowIds, $draftHiddenIds) {
            if (! empty($regionReportRowIds)) {
                CallCenterReportHiddenRow::where('call_center_report_id', $report->id)
                    ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
                    ->whereIn('master_dataset_row_id', $regionReportRowIds)
                    ->delete();
            }

            $now = now();
            foreach ($draftHiddenIds as $rowId) {
                CallCenterReportHiddenRow::create([
                    'call_center_report_id' => $report->id,
                    'report_type' => CallCenterReport::REPORT_TYPE_REGIONAL_BILLING,
                    'master_dataset_row_id' => (int) $rowId,
                    'hidden_by_user_id' => $sessionUserId > 0 ? $sessionUserId : null,
                    'hidden_at' => $now,
                ]);
            }

            if (! empty($draftHiddenIds)) {
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
                }, $draftHiddenIds);
                DB::table('call_center_report_row_actions')->insert($auditRows);
            }

            $review = CallCenterReportRegionReview::firstOrNew([
                'call_center_report_id' => $report->id,
                'region_name' => $region,
            ]);

            $review->report_type = CallCenterReport::REPORT_TYPE_REGIONAL_BILLING;
            $review->reviewed_by_user_id = $sessionUserId > 0 ? $sessionUserId : null;
            $review->reviewed_at = $now;
            $review->save();

            if ($this->normalizeRegionName((string) $review->region_name) !== $normalizedRegion) {
                $review->region_name = $region;
                $review->save();
            }
        });

        $this->clearDraftHiddenRowIds($report->id, $normalizedRegion);

        return redirect()->route('rb.reports', ['report' => $report->id])
            ->with('status', 'Region review passed and locked. Report can now be handled by RTO admins.');
    }

    public function unlockReview(Request $request, int $reportId): RedirectResponse|JsonResponse
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $assignment = $this->normalizeAssignment($sessionUser['assignment'] ?? null);

        if (!$this->isRegionAdmin($assignment)) {
            abort(403, 'Only region admins can unlock reviews.');
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

        if (! $this->isReviewLocked($reviewRecord)) {
            return $this->respondError($request, 'Review is not locked. No unlock required.');
        }

        $hiddenRowIds = CallCenterReportHiddenRow::where('call_center_report_id', $report->id)
            ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
            ->pluck('master_dataset_row_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->putDraftHiddenRowIds($report->id, $normalizedRegion, $hiddenRowIds);

        $reviewRecord->reviewed_at = null;
        $reviewRecord->reviewed_by_user_id = null;
        $reviewRecord->save();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['message' => 'Review unlocked. Hidden rows can now be managed again.']);
        }

        return redirect()->route('rb.reports', ['report' => $report->id])
            ->with('status', 'Review unlocked. Hidden rows can now be managed again.');
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
        $allowedHeaders = ['CUSTOMER_REF', 'ACCOUNT_NUM', 'PRODUCT_LABEL'];

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
