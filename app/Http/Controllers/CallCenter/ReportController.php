<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterInteraction;
use App\Models\CallCenterReport;
use App\Models\CallCenter\CallCenterUser;
use App\Models\DatasetExport;
use App\Models\User;
use App\Support\MasterDatasetExportService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $reports = CallCenterReport::with('process')
            ->orderByDesc('created_at')
            ->get();

        $selectedReport = null;
        $requested = $request->query('report');

        if ($reports->isNotEmpty()) {
            if ($requested !== null) {
                $selectedReport = $reports->firstWhere('id', (int) $requested);
            }

            $selectedReport ??= $reports->first();
        }

        $rejectedAssignments = $selectedReport
            ? CallCenterAssignment::with(['row', 'rejectedBy', 'interactions.agent'])
            ->where('call_center_report_id', $selectedReport->id)
            ->rejected()
            ->where('status', 'pending')
            ->orderByDesc('rejected_at')
            ->when(\Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_'), function ($q) {
                $assign = session('user.assignment') ?? '';
                $rtomPart = preg_replace('/^supervisor_/', '', $assign);
                $rtomVal = preg_replace('/^rtom_/', '', $rtomPart);
                return $q->whereHas('row', fn($rowQ) => $rowQ->where('rtom', $rtomVal));
            })
            ->get()
            : collect();

        $acceptedAssignments = $selectedReport
            ? CallCenterAssignment::with(['row', 'agent', 'interactions.agent'])
            ->where('call_center_report_id', $selectedReport->id)
            ->where('accepted', true)
            ->orderByDesc('accepted_at')
            ->when(\Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_'), function ($q) {
                $assign = session('user.assignment') ?? '';
                $rtomPart = preg_replace('/^supervisor_/', '', $assign);
                $rtomVal = preg_replace('/^rtom_/', '', $rtomPart);
                return $q->whereHas('row', fn($rowQ) => $rowQ->where('rtom', $rtomVal));
            })
            ->get()
            : collect();

        // If logged-in user is a supervisor, show callers for the supervisor's RTOM (from assignment)
        // Supervisors will see other supervisors' callers in the same RTOM; their own callers will be marked in the view.
        $currentSupervisorId = session('user')['id'] ?? null;
        if (\Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_')) {
            $assign = session('user.assignment') ?? '';
            $rtomPart = preg_replace('/^supervisor_/', '', $assign);
            $rtomVal = preg_replace('/^rtom_/', '', $rtomPart);
            if ($rtomVal !== '') {
                $ccUsers = CallCenterUser::where('assignment', 'caller_rtom_' . $rtomVal)
                    ->active()
                    ->orderBy('username')
                    ->get();
            } else {
                // fallback: show callers created by this supervisor
                $ccUsers = CallCenterUser::where('supervisor', $currentSupervisorId)
                    ->active()
                    ->orderBy('username')
                    ->get();
            }
        } else {
            $ccUsers = CallCenterUser::active()->orderBy('username')->get();
        }

        $pendingCounts = [];
        $isSupervisor = \Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_');
        $rtomVal = null;
        if ($isSupervisor) {
            $assign = session('user.assignment') ?? '';
            $rtomPart = preg_replace('/^supervisor_/', '', $assign);
            $rtomVal = preg_replace('/^rtom_/', '', $rtomPart);
        }
        foreach ($ccUsers as $u) {
            $query = CallCenterAssignment::where('assigned_user_id', $u->id)
                ->where('call_center_report_id', $selectedReport->id)
                ->pendingApproval();
            if ($isSupervisor && $rtomVal) {
                $query->whereHas('row', fn($rowQ) => $rowQ->where('rtom', $rtomVal));
            }
            $pendingCounts[$u->id] = $selectedReport ? $query->count() : 0;
        }

        // Also compute previous-report pending counts for each user so admins can see carry-overs
        $prevPendingCounts = [];
        if ($selectedReport) {
            $previousReport = CallCenterReport::where('created_at', '<', $selectedReport->created_at)
                ->orderByDesc('created_at')
                ->first();

            if (! $previousReport && $selectedReport->dataset_month) {
                $prevMonth = (string) $selectedReport->dataset_month;
                $previousReport = CallCenterReport::whereNotNull('dataset_month')
                    ->where('dataset_month', '<', $prevMonth)
                    ->orderByDesc('dataset_month')
                    ->first();
            }

            if ($previousReport) {
                foreach ($ccUsers as $u) {
                    $prevPendingCounts[$u->id] = CallCenterAssignment::where('assigned_user_id', $u->id)
                        ->where('call_center_report_id', $previousReport->id)
                        ->pendingApproval()
                        ->count();
                }
            }
        }
        $hiddenRowIds = [];
        if ($selectedReport) {
            $hiddenRowIds = DB::table('call_center_report_hidden_rows')
                ->where('call_center_report_id', $selectedReport->id)
                ->pluck('master_dataset_row_id')
                ->map(fn($id) => (int) $id)
                ->all();
        }

        // Compute assigned / distributable counts. Supervisors should only be able to distribute rows
        // that match their RTOM (and by extension the region(s) where that RTOM appears).
        $assignedCount = 0;
        $allowedRowIds = [];
        $effectiveRowCount = $selectedReport ? max(0, (int) $selectedReport->row_count - count($hiddenRowIds)) : 0;
        if ($selectedReport) {
            $allAssignedForReport = CallCenterAssignment::where('call_center_report_id', $selectedReport->id)->whereNotNull('assigned_user_id');
            // Default behavior: everything
            $assignedCount = $allAssignedForReport->count();

            // If supervisor, narrow to rows matching their RTOM
            if (\Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_')) {
                $assignStr = session('user.assignment') ?? '';
                $rtomPart = preg_replace('/^supervisor_/', '', $assignStr);
                // strip leading rtom_ if present
                $rtomVal = preg_replace('/^rtom_/', '', $rtomPart);
                // find row ids that match this rtom (case-insensitive) within the report's visible row list
                $rowIds = array_values(array_diff($selectedReport->row_ids ?? [], $hiddenRowIds));
                $allowedRowIds = collect($rowIds)->chunk(1000)->flatMap(function($chunk) use ($rtomVal) {
                    return DB::table('master_dataset_rows')
                        ->whereIn('id', $chunk->toArray())
                        ->whereRaw('LOWER(rtom) = ?', [strtolower($rtomVal)])
                        ->pluck('id');
                })->values()->all();

                $assignedCount = CallCenterAssignment::where('call_center_report_id', $selectedReport->id)
                    ->whereIn('master_dataset_row_id', $allowedRowIds)
                    ->whereNotNull('assigned_user_id')
                    ->count();

                $effectiveRowCount = count($allowedRowIds);
            }
        }

        $anyAssigned = $assignedCount > 0;
        $allAssigned = $selectedReport ? ($effectiveRowCount > 0 && $assignedCount >= $effectiveRowCount) : false;
        $distributableRows = $selectedReport ? max(0, $effectiveRowCount - $assignedCount) : 0;

        $regionalReviewStatus = [
            'hidden_count' => count($hiddenRowIds),
            'pending_regions' => [],
        ];
        if ($selectedReport) {
            $regionalReviewStatus['pending_regions'] = $this->pendingRegionalReviews($selectedReport);
        }

        $hasInteractions = false;
        if ($selectedReport) {
            $hasInteractions = CallCenterInteraction::whereHas('assignment', fn($query) => $query->where('call_center_report_id', $selectedReport->id))->exists();
        }

        $acceptedUserIds = $acceptedAssignments->pluck('assigned_user_id')->filter()->unique()->values()->all();
        $pendingUserIds = array_keys(array_filter($pendingCounts, function ($count, $uid) use ($acceptedUserIds) {
            return $count > 0 && !in_array($uid, $acceptedUserIds, true);
        }, ARRAY_FILTER_USE_BOTH));

        $rejectedSummary = $rejectedAssignments->groupBy('assigned_user_id')->map(function ($group, $uid) {
            $first = $group->first();
            return [
                'user_id' => $uid,
                'label' => $this->formatAgentLabel(optional($first)->agent ?? null),
                'count' => $group->count(),
                'reason' => optional($first)->rejection_note,
                'last_rejected_at' => optional($first)->rejected_at ? optional($first->rejected_at)->diffForHumans() : null,
            ];
        })->values();

        $rejectedUserIds = $rejectedAssignments->pluck('assigned_user_id')->filter()->unique()->values()->all();
        $nonRejectingUsers = $ccUsers->whereNotIn('id', $rejectedUserIds);

        return view('callcenter.reports.index', [
            'reports' => $reports,
            'selectedReport' => $selectedReport,
            'ccUsers' => $ccUsers,
            'currentSupervisorId' => $currentSupervisorId,
            'allowedRowIds' => $allowedRowIds,
            'rejectedAssignments' => $rejectedAssignments,
            'acceptedAssignments' => $acceptedAssignments,
            'pendingCounts' => $pendingCounts,
            'prevPendingCounts' => $prevPendingCounts ?? [],
            'anyAssigned' => $anyAssigned,
            'allAssigned' => $allAssigned,
            'distributableRows' => $distributableRows,
            'regionalReviewStatus' => $regionalReviewStatus,
            'hasInteractions' => $hasInteractions,
            'rejectedSummary' => $rejectedSummary,
            'reassignPendingUserIds' => $pendingUserIds,
            'reassignAcceptedUserIds' => $acceptedUserIds,
            'nonRejectingUsers' => $nonRejectingUsers,
        ]);
    }

    public function download(Request $request, int $id, MasterDatasetExportService $exportService): StreamedResponse
    {
        $report = CallCenterReport::findOrFail($id);

        $export = DatasetExport::where('token', $report->token)
            ->where('bucket', 'call-center')
            ->firstOrFail();

        $disk = $export->file_disk ?: config('filesystems.default', 'local');
        $path = $export->file_path;
        $format = strtolower((string) $request->query('format', 'xlsx'));

        if (! in_array($format, ['csv', 'xlsx'], true)) {
            abort(400, 'Invalid download format.');
        }

        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'Export file not found on disk.');
        }

        $storedExtension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($format === 'csv') {
            if ($storedExtension === 'csv') {
                return Storage::disk($disk)->download($path, $export->filename, [
                    'Content-Type' => 'text/csv',
                ]);
            }

            return Storage::disk($disk)->download($path, $export->filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        if ($storedExtension === 'csv') {
            $downloadName = preg_replace('/\.csv$/i', '.xlsx', $export->filename) ?: 'call_center.xlsx';

            return $exportService->streamCsvAsXlsx(Storage::disk($disk), $path, $downloadName);
        }

        return Storage::disk($disk)->download($path, $export->filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function history(): View
    {
        $reportsQuery = CallCenterReport::with('process')
            ->orderByDesc('created_at');

        // Filter for supervisors: only show reports that have assignments to callers in their RTOM
        if (\Illuminate\Support\Str::startsWith(session('user.assignment') ?? '', 'supervisor_')) {
            $assign = session('user.assignment') ?? '';
            $rtomPart = preg_replace('/^supervisor_/', '', $assign);
            $rtomVal = preg_replace('/^rtom_/', '', $rtomPart);
            if ($rtomVal !== '') {
                $reportsQuery->whereHas('assignments', function ($q) use ($rtomVal) {
                    $q->whereHas('agent', function ($uq) use ($rtomVal) {
                        $uq->where('assignment', 'caller_rtom_' . $rtomVal);
                    });
                });
            }
        }

        $reports = $reportsQuery->get();

        $assignmentStats = CallCenterAssignment::selectRaw(
            'call_center_report_id,
            SUM(CASE WHEN assigned_user_id IS NOT NULL THEN 1 ELSE 0 END) as assigned_rows,
            SUM(CASE WHEN accepted = 1 THEN 1 ELSE 0 END) as accepted_rows,
            SUM(CASE WHEN rejected = 1 THEN 1 ELSE 0 END) as rejected_rows,
            SUM(COALESCE(paid_amount, 0)) as assignment_payments'
        )
            ->groupBy('call_center_report_id')
            ->get()
            ->keyBy('call_center_report_id');

        $interactionStats = CallCenterInteraction::selectRaw(
            'call_center_row_assignments.call_center_report_id as report_id,
            COUNT(*) as interactions,
            SUM(COALESCE(call_center_interactions.paid_amount, 0)) as interaction_payments'
        )
            ->join('call_center_row_assignments', 'call_center_interactions.assignment_id', '=', 'call_center_row_assignments.id')
            ->groupBy('call_center_row_assignments.call_center_report_id')
            ->get()
            ->keyBy('report_id');

        $history = $reports->map(function (CallCenterReport $report) use ($assignmentStats, $interactionStats) {
            $assignmentSummary = $assignmentStats->get($report->id);
            $interactionSummary = $interactionStats->get($report->id);
            $assignedRows = $assignmentSummary?->assigned_rows ?? 0;
            $acceptedRows = $assignmentSummary?->accepted_rows ?? 0;
            $rejectedRows = $assignmentSummary?->rejected_rows ?? 0;
            $assignmentPayments = $assignmentSummary?->assignment_payments ?? 0;
            $interactionPayments = $interactionSummary?->interaction_payments ?? 0;
            $totalPayments = round($interactionPayments, 2);
            $interactions = $interactionSummary?->interactions ?? 0;
            $acceptanceRate = $assignedRows ? round(($acceptedRows / $assignedRows) * 100, 1) : 0;

            return [
                'id' => $report->id,
                'label' => $this->formatReportLabel($report),
                'row_count' => $report->row_count,
                'assigned_rows' => $assignedRows,
                'accepted_rows' => $acceptedRows,
                'rejected_rows' => $rejectedRows,
                'interactions' => $interactions,
                'payment_amount' => $totalPayments,
                'acceptance_rate' => $acceptanceRate,
                'created_at' => $report->created_at,
                'assigner' => optional($report->process)->user_name ?? optional(optional($report->process)->user)->username ?? 'System',
            ];
        });

        $currentYear = Carbon::now()->year;
        $yearPayments = $history->filter(function ($entry) use ($currentYear) {
            $created = $entry['created_at'];
            if (! $created) {
                return false;
            }
            return Carbon::parse($created)->year === $currentYear;
        })->sum('payment_amount');

        return view('callcenter.reports.history', [
            'history' => $history,
            'yearPayments' => $yearPayments,
        ]);
    }

    public function summary(CallCenterReport $report): View
    {
        $label = $this->formatReportLabel($report);
        $assignments = CallCenterAssignment::with(['agent', 'row', 'interactions.agent'])
            ->where('call_center_report_id', $report->id)
            ->get();

        $hiddenRows = DB::table('call_center_report_hidden_rows as h')
            ->leftJoin('master_dataset_rows as r', 'h.master_dataset_row_id', '=', 'r.id')
            ->leftJoin('users as u', 'h.hidden_by_user_id', '=', 'u.id')
            ->where('h.call_center_report_id', $report->id)
            ->orderByDesc('h.hidden_at')
            ->get([
                'h.master_dataset_row_id',
                'h.hidden_at',
                'u.name as hidden_by_name',
                'u.username as hidden_by_username',
                'r.account_num',
                'r.customer_ref',
                'r.mobile_contact_tel',
                'r.region',
                'r.rtom',
            ])
            ->map(function ($item) {
                $actor = trim((string) ($item->hidden_by_name ?? ''));
                if ($actor === '') {
                    $actor = trim((string) ($item->hidden_by_username ?? ''));
                }
                if ($actor === '') {
                    $actor = 'Unknown';
                }

                return [
                    'row_id' => (int) $item->master_dataset_row_id,
                    'account_num' => $item->account_num,
                    'customer_ref' => $item->customer_ref,
                    'phone' => $item->mobile_contact_tel,
                    'region' => $item->region,
                    'rtom' => $item->rtom,
                    'hidden_by' => $actor,
                    'hidden_at' => $item->hidden_at ? Carbon::parse($item->hidden_at) : null,
                ];
            })
            ->values();


        $assignedCount = $assignments->whereNotNull('assigned_user_id')->count();
        $acceptedCount = $assignments->where('accepted', true)->count();
        $acceptanceRate = $assignedCount ? round(($acceptedCount / $assignedCount) * 100, 1) : 0;
        $interactionCollection = $assignments->flatMap->interactions;
        $totalInteractions = $interactionCollection->count();
        $totalPayments = $interactionCollection->sum(fn(CallCenterInteraction $interaction) => $interaction->paid_amount ?? 0);

        $agentMetrics = $assignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function ($group) {
            $acceptedAssignments = $group->where('accepted', true);
            $assignmentCount = $acceptedAssignments->count();
            $paid = $acceptedAssignments->where('paid', true)->count();
            $rejected = $group->where('rejected', true)->count();
            $interactions = $acceptedAssignments->flatMap->interactions;
            $callCount = $interactions->count();
            $paymentAmount = $interactions->sum(fn(CallCenterInteraction $interaction) => $interaction->paid_amount ?? 0);
            $agent = $group->first()->agent;

            return [
                'user_id' => $group->first()->assigned_user_id,
                'name' => $this->formatAgentLabel($agent),
                'assigned_rows' => $assignmentCount,
                'accepted_rows' => $assignmentCount, // since already filtered
                'paid_rows' => $paid,
                'rejected_rows' => $rejected,
                'acceptance_rate' => $group->count() ? round(($assignmentCount / $group->count()) * 100, 1) : 0,
                'coverage' => $assignmentCount ? round(($paid / $assignmentCount) * 100, 1) : 0,
                'call_count' => $callCount,
                'payment_amount' => round($paymentAmount, 2),
            ];
        })->values()->filter(fn($m) => $m['assigned_rows'] > 0);

        $agentCollection = $agentMetrics->sortByDesc('payment_amount')->values();
        $topAgentByPayment = $agentCollection->first();
        $topAgentByCoverage = $agentCollection->sortByDesc('coverage')->first();
        $topAgentByCalls = $agentCollection->sortByDesc('call_count')->first();

        $earliestAssignment = $assignments->whereNotNull('created_at')->min('created_at');
        $reportStart = Carbon::parse($earliestAssignment ?? $report->created_at ?? Carbon::now())->startOfDay();

        $nextReport = CallCenterReport::where('created_at', '>', $report->created_at)
            ->orderBy('created_at')
            ->first();

        $nextAssignmentStart = $nextReport
            ? CallCenterAssignment::where('call_center_report_id', $nextReport->id)
            ->whereNotNull('created_at')
            ->orderBy('created_at')
            ->value('created_at')
            : null;

        $reportEndCandidate = $nextAssignmentStart
            ? Carbon::parse($nextAssignmentStart)
            : Carbon::parse($report->updated_at ?? $report->created_at ?? Carbon::now());

        $reportEnd = $reportEndCandidate->copy()->endOfDay();
        if ($reportEnd->lessThan($reportStart)) {
            $reportEnd = $reportStart->copy();
        }

        $interactionCounts = $this->groupCountsByDate($interactionCollection);
        $dailyBestAgents = $this->buildDailyBestAgents($interactionCollection);
        $callsCalendar = $this->buildDailyCalendar($interactionCounts, $reportStart, $reportEnd);
        $callsPerWeek = $this->summarizeWeeklyFrom($interactionCounts, $reportStart, $reportEnd);

        $nonAcceptedAssignments = CallCenterAssignment::with(['agent', 'row', 'rejectedBy'])
            ->where('call_center_report_id', $report->id)
            ->whereNotNull('assigned_user_id')
            ->where('accepted', false)
            ->orderByDesc('created_at')
            ->get();

        return view('callcenter.reports.summary', [
            'report' => $report,
            'label' => $label,
            'assigner' => optional($report->process)->user_name ?? optional(optional($report->process)->user)->username ?? 'System',
            'hiddenRows' => $hiddenRows,
            'assignedCount' => $assignedCount,
            'acceptedCount' => $acceptedCount,
            'acceptanceRate' => $acceptanceRate,
            'totalInteractions' => $totalInteractions,
            'totalPayments' => round($totalPayments, 2),
            'agentMetrics' => $agentCollection,
            'topAgentByPayment' => $topAgentByPayment,
            'topAgentByCoverage' => $topAgentByCoverage,
            'topAgentByCalls' => $topAgentByCalls,
            'callsCalendar' => $callsCalendar,
            'callsPerWeek' => $callsPerWeek,
            'reportStart' => $reportStart,
            'reportEnd' => $reportEnd,
            'dailyBestAgents' => $dailyBestAgents,
            'nonAcceptedAssignments' => $nonAcceptedAssignments,
        ]);
    }

    private function formatReportLabel(CallCenterReport $report): string
    {
        $month = $report->dataset_month;
        if ($month && strlen($month) === 6) {
            return substr($month, 0, 4) . '/' . substr($month, 4, 2) . ' report';
        }

        return 'Report #' . $report->id;
    }

    private function formatAgentLabel(?User $agent): string
    {
        if (! $agent) {
            return 'Unassigned';
        }

        $name = trim((string) ($agent->name ?? ''));
        $username = trim((string) ($agent->username ?? ''));

        if ($name && $username) {
            return $name . ' (' . $username . ')';
        }

        if ($name) {
            return $name;
        }

        if ($username) {
            return $username;
        }

        return 'Agent ' . ($agent->id ?? 'unknown');
    }

    private function groupCountsByDate(Collection $interactions): array
    {
        return $interactions
            ->filter(fn($interaction) => $interaction->created_at)
            ->groupBy(fn($interaction) => $interaction->created_at->toDateString())
            ->map(fn($group) => $group->count())
            ->all();
    }

    private function buildDailyBestAgents(Collection $interactions): array
    {
        return $interactions
            ->filter(fn($interaction) => $interaction->created_at)
            ->groupBy(fn($interaction) => $interaction->created_at->toDateString())
            ->mapWithKeys(function (Collection $group, string $date) {
                $agentBuckets = $group->groupBy(fn($interaction) => $interaction->agent?->id ?? 'unassigned');
                $best = $agentBuckets->map(function (Collection $bucket, $agentId) {
                    $interaction = $bucket->first();
                    $name = optional($interaction->agent)->username
                        ?? ($agentId === 'unassigned' ? 'Unassigned' : 'Agent ' . $agentId);
                    return [
                        'name' => $name,
                        'calls' => $bucket->count(),
                    ];
                })->sortByDesc('calls')->values()->first();

                return [$date => $best];
            })
            ->filter()
            ->all();
    }

    private function buildDailyCalendar(array $counts, Carbon $start, Carbon $end): array
    {
        $startOfWeek = $start->copy()->startOfWeek();
        $endDate = $end->copy()->endOfWeek();
        $period = CarbonPeriod::create($startOfWeek, '1 day', $endDate);
        $weeks = [];
        $currentWeek = [];

        foreach ($period as $date) {
            $currentWeek[] = [
                'date' => $date->copy(),
                'count' => $counts[$date->toDateString()] ?? 0,
                'isStartDate' => $date->isSameDay($start),
                'in_range' => $date->between($start, $end, true),
            ];

            if (count($currentWeek) === 7) {
                $weeks[] = $currentWeek;
                $currentWeek = [];
            }
        }

        return [
            'weeks' => $weeks,
            'startLabel' => $start->format('M j, Y'),
            'endLabel' => $end->format('M j, Y'),
        ];
    }

    private function summarizeWeeklyFrom(array $counts, Carbon $start, Carbon $end): array
    {
        $firstWeekStart = $start->copy()->startOfWeek();
        $lastWeekStart = $end->copy()->startOfWeek();
        $weekSpan = (int) floor($firstWeekStart->diffInDays($lastWeekStart) / 7) + 1;
        return collect(range(0, max($weekSpan - 1, 0)))->map(function (int $offset) use ($counts, $firstWeekStart) {
            $weekStart = $firstWeekStart->copy()->addWeeks($offset);
            $weekEnd = $weekStart->copy()->endOfWeek();

            return [
                'label' => $weekStart->format('M j') . ' – ' . $weekEnd->format('M j'),
                'count' => $this->sumCountsBetween($counts, $weekStart, $weekEnd),
            ];
        })->values()->all();
    }

    private function sumCountsBetween(array $counts, Carbon $start, Carbon $end): int
    {
        $total = 0;
        foreach ($counts as $date => $value) {
            $current = Carbon::parse($date);
            if ($current->between($start, $end, true)) {
                $total += $value;
            }
        }

        return $total;
    }

    private function pendingRegionalReviews(CallCenterReport $report): array
    {
        $rowIds = collect($report->row_ids ?? [])->map(fn($id) => (int) $id)->filter(fn($id) => $id > 0)->values();
        if ($rowIds->isEmpty()) {
            return [];
        }

        $regionsInReport = DB::table('master_dataset_rows')
            ->whereIn('id', $rowIds->all())
            ->whereNotNull('region')
            ->selectRaw('LOWER(TRIM(region)) as region_key')
            ->distinct()
            ->pluck('region_key')
            ->filter()
            ->values()
            ->all();

        if (empty($regionsInReport)) {
            return [];
        }

        $enabledRegions = DB::table('users')
            ->where('system', 'cc')
            ->where('admin_prev', 1)
            ->where('status', 1)
            ->where('enable_regional_review', 1)
            ->whereNotNull('enable_regional_review_enabled_at')
            ->where('enable_regional_review_enabled_at', '<=', $report->created_at)
            ->whereNotNull('assignment')
            ->where('assignment', '<>', 'super')
            ->where('assignment', 'not like', 'rtom_%')
            ->where('assignment', 'not like', 'supervisor_%')
            ->where('assignment', 'not like', 'caller_%')
            ->select('assignment')
            ->distinct()
            ->get();

        $regionLabelByKey = [];
        foreach ($enabledRegions as $regionRow) {
            $label = trim((string) ($regionRow->assignment ?? ''));
            if ($label === '') {
                continue;
            }

            $key = strtolower($label);
            $regionLabelByKey[$key] = $label;
        }
        $enabledRegionKeys = array_keys($regionLabelByKey);

        $required = array_values(array_unique(array_intersect($regionsInReport, $enabledRegionKeys)));
        if (empty($required)) {
            return [];
        }

        $reviewed = DB::table('call_center_report_region_reviews')
            ->where('call_center_report_id', $report->id)
            ->whereNotNull('reviewed_at')
            ->selectRaw('LOWER(TRIM(region_name)) as region_key')
            ->pluck('region_key')
            ->filter()
            ->values()
            ->all();

        $pendingKeys = array_values(array_diff($required, $reviewed));
        if (empty($pendingKeys)) {
            return [];
        }

        $pendingLabels = [];
        foreach ($pendingKeys as $key) {
            $pendingLabels[] = $regionLabelByKey[$key] ?? $key;
        }

        return array_values(array_unique($pendingLabels));
    }

    /**
     * Supervisor-specific distribution: only allow assigning rows that match
     * the supervisor's RTOM and only to callers they created.
     */
    public function distributeSupervisor(Request $request, $reportId)
    {
        $session = session('user');
        if (! $session || ! \Illuminate\Support\Str::startsWith($session['assignment'] ?? '', 'supervisor_')) {
            abort(403);
        }

        $report = CallCenterReport::findOrFail($reportId);

        $pendingRegions = $this->pendingRegionalReviews($report);
        if (! empty($pendingRegions)) {
            return redirect()->route('cc.reports', ['report' => $reportId])
                ->withErrors(['reassign' => 'Distribution is blocked. Pending regional review: ' . implode(', ', $pendingRegions)]);
        }

        $userIds = array_values(array_filter((array) $request->input('user_ids', []), fn($v) => is_numeric($v) && (int)$v > 0));
        if (empty($userIds)) {
            return redirect()->route('cc.reports', ['report' => $reportId])->withErrors(['reassign' => 'Select at least one user to distribute to.']);
        }

        // Determine RTOM value for this supervisor
        $assignStr = $session['assignment'] ?? '';
        $rtomPart = preg_replace('/^supervisor_/', '', $assignStr);
        $rtomVal = preg_replace('/^rtom_/', '', $rtomPart);

        if ($rtomVal === '') {
            return redirect()->route('cc.reports', ['report' => $reportId])->withErrors(['reassign' => 'Unable to determine your RTO assignment.']);
        }

        // Only allow selecting callers that belong to this RTOM (callers may be owned by any supervisor)
        $allowedUserIds = \App\Models\CallCenter\CallCenterUser::whereIn('id', $userIds)
            ->where('assignment', 'caller_rtom_' . $rtomVal)
            ->pluck('id')
            ->toArray();

        if (empty($allowedUserIds)) {
            return redirect()->route('cc.reports', ['report' => $reportId])->withErrors(['reassign' => 'No valid users selected.']);
        }

        $hiddenIds = DB::table('call_center_report_hidden_rows')
            ->where('call_center_report_id', $report->id)
            ->pluck('master_dataset_row_id')
            ->map(fn($id) => (int) $id)
            ->all();

        $rowIds = array_values(array_diff($report->row_ids ?? [], $hiddenIds));
        if (empty($rowIds)) {
            return redirect()->route('cc.reports', ['report' => $reportId])->withErrors(['reassign' => 'No rows available for this report.']);
        }

        // Find allowed master row ids matching RTOM (case-insensitive)
        $allowedRowIds = collect($rowIds)->chunk(1000)->flatMap(function($chunk) use ($rtomVal) {
            return \Illuminate\Support\Facades\DB::table('master_dataset_rows')
                ->whereIn('id', $chunk->toArray())
                ->whereRaw('LOWER(rtom) = ?', [strtolower($rtomVal)])
                ->pluck('id');
        })->values()->all();

        if (empty($allowedRowIds)) {
            return redirect()->route('cc.reports', ['report' => $reportId])->withErrors(['reassign' => 'No rows match your RTO.']);
        }

        // Exclude rows already assigned for this report
        $alreadyAssigned = CallCenterAssignment::where('call_center_report_id', $report->id)
            ->whereIn('master_dataset_row_id', $allowedRowIds)
            ->whereNotNull('assigned_user_id')
            ->pluck('master_dataset_row_id')
            ->toArray();

        $available = array_values(array_diff($allowedRowIds, $alreadyAssigned));
        if (empty($available)) {
            return redirect()->route('cc.reports', ['report' => $reportId])->withErrors(['reassign' => 'No distributable rows available for your RTO.']);
        }

        // Distribute evenly among allowed users
        $total = count($available);
        $users = array_values($allowedUserIds);
        $userCount = count($users);
        $basePerUser = $userCount ? (int) floor($total / $userCount) : 0;
        $remainder = $userCount ? $total % $userCount : 0;

        $now = Carbon::now()->toDateTimeString();
        $batch = [];
        $pos = 0;
        foreach ($users as $index => $uid) {
            $take = $basePerUser + ($index < $remainder ? 1 : 0);
            for ($i = 0; $i < $take && $pos < $total; $i++, $pos++) {
                $batch[] = [
                    'call_center_report_id' => $report->id,
                    'master_dataset_row_id' => $available[$pos],
                    'assigned_user_id' => $uid,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($batch) >= 500) {
                    \Illuminate\Support\Facades\DB::table('call_center_row_assignments')->insert($batch);
                    $batch = [];
                }
            }
        }

        if (! empty($batch)) {
            \Illuminate\Support\Facades\DB::table('call_center_row_assignments')->insert($batch);
        }

        return redirect()->route('cc.reports', ['report' => $reportId])->with('status', 'Distribution completed for your RTO.');
    }

    public function getAgentDetails(Request $request)
    {
        $userId = $request->query('user_id');
        $agent = User::find($userId);

        if (!$agent) {
            return response()->json(['error' => 'Agent not found'], 404);
        }

        $supervisor = null;
        $rtom = null;
        $region = null;

        $supervisorUser = $agent->supervisor ? User::find($agent->supervisor) : null;
        $supervisor = $supervisorUser ? $this->formatAgentLabel($supervisorUser) : 'N/A';

        $assignment = $agent->assignment ?? null;
        if ($assignment && str_starts_with($assignment, 'caller_')) {
            $rtom = substr($assignment, 7); // after 'caller_'
        }

        // Find region by traversing supervisor chain
        $currentUser = $supervisorUser;
        while ($currentUser) {
            $currentAssignment = $currentUser->assignment ?? null;
            if ($currentAssignment && !str_starts_with($currentAssignment, 'caller_') && !str_starts_with($currentAssignment, 'rtom_') && !str_starts_with($currentAssignment, 'supervisor_') && $currentAssignment !== 'super') {
                $region = $currentAssignment;
                break;
            }
            $currentUser = $currentUser->supervisor ? User::find($currentUser->supervisor) : null;
        }

        return response()->json([
            'name' => $this->formatAgentLabel($agent),
            'supervisor' => $supervisor,
            'rtom' => $rtom ?? 'N/A',
            'region' => $region ?? 'N/A',
        ]);
    }
}
