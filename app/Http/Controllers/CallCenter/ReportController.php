<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterInteraction;
use App\Models\CallCenterReport;
use App\Models\CallCenter\CallCenterUser;
use App\Models\DatasetExport;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
                ->get()
            : collect();

        $acceptedAssignments = $selectedReport
            ? CallCenterAssignment::with(['row', 'agent', 'interactions.agent'])
                ->where('call_center_report_id', $selectedReport->id)
                ->where('accepted', true)
                ->orderByDesc('accepted_at')
                ->get()
            : collect();

        $ccUsers = CallCenterUser::active()->orderBy('username')->get();

        $pendingCounts = [];
        foreach ($ccUsers as $u) {
            $pendingCounts[$u->id] = CallCenterAssignment::where('assigned_user_id', $u->id)
                ->pendingApproval()
                ->count();
        }
        $assignedCount = $selectedReport
            ? CallCenterAssignment::where('call_center_report_id', $selectedReport->id)->whereNotNull('assigned_user_id')->count()
            : 0;

        $anyAssigned = $assignedCount > 0;
        $allAssigned = $selectedReport ? ($selectedReport->row_count > 0 && $assignedCount >= $selectedReport->row_count) : false;
        $distributableRows = $selectedReport ? max(0, $selectedReport->row_count - $assignedCount) : 0;

        $hasInteractions = false;
        if ($selectedReport) {
            $hasInteractions = CallCenterInteraction::whereHas('assignment', fn ($query) => $query->where('call_center_report_id', $selectedReport->id))->exists();
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
            'rejectedAssignments' => $rejectedAssignments,
            'acceptedAssignments' => $acceptedAssignments,
            'pendingCounts' => $pendingCounts,
            'anyAssigned' => $anyAssigned,
            'allAssigned' => $allAssigned,
            'distributableRows' => $distributableRows,
            'hasInteractions' => $hasInteractions,
            'rejectedSummary' => $rejectedSummary,
            'reassignPendingUserIds' => $pendingUserIds,
            'reassignAcceptedUserIds' => $acceptedUserIds,
            'nonRejectingUsers' => $nonRejectingUsers,
        ]);
    }

    public function download($id): StreamedResponse
    {
        $report = CallCenterReport::findOrFail($id);

        $export = DatasetExport::where('token', $report->token)
            ->where('bucket', 'call-center')
            ->firstOrFail();

        $disk = $export->file_disk ?: config('filesystems.default', 'local');
        $path = $export->file_path;

        // If file exists on disk, stream a download of the existing export file.
        if (Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->download($path, $export->filename);
        }

        abort(404, 'Export file not found on disk.');
    }

    public function history(): View
    {
        $reports = CallCenterReport::with('process')
            ->orderByDesc('created_at')
            ->get();

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
            $totalPayments = round($assignmentPayments + $interactionPayments, 2);
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

        $assignedCount = $assignments->whereNotNull('assigned_user_id')->count();
        $acceptedCount = $assignments->where('accepted', true)->count();
        $acceptanceRate = $assignedCount ? round(($acceptedCount / $assignedCount) * 100, 1) : 0;
        $interactionCollection = $assignments->flatMap->interactions;
        $totalInteractions = $interactionCollection->count();
        $totalPayments = $interactionCollection->sum(fn (CallCenterInteraction $interaction) => $interaction->paid_amount ?? 0)
            + $assignments->sum(fn (CallCenterAssignment $assignment) => $assignment->paid_amount ?? 0);

        $agentMetrics = $assignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function ($group) {
            $assignmentCount = $group->count();
            $accepted = $group->where('accepted', true)->count();
            $rejected = $group->where('rejected', true)->count();
            $interactions = $group->flatMap->interactions;
            $callCount = $interactions->count();
            $paymentAmount = $interactions->sum(fn (CallCenterInteraction $interaction) => $interaction->paid_amount ?? 0);
            $agent = $group->first()->agent;
            return [
                'user_id' => $group->first()->assigned_user_id,
                'name' => $this->formatAgentLabel($agent),
                'assigned_rows' => $assignmentCount,
                'accepted_rows' => $accepted,
                'rejected_rows' => $rejected,
                'acceptance_rate' => $assignmentCount ? round(($accepted / $assignmentCount) * 100, 1) : 0,
                'coverage' => $assignmentCount ? round(($accepted / $assignmentCount) * 100, 1) : 0,
                'call_count' => $callCount,
                'payment_amount' => round($paymentAmount, 2),
            ];
        })->values();

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
            return substr($month, 0, 4).'/'.substr($month, 4, 2).' report';
        }

        return 'Report #'.$report->id;
    }

    private function formatAgentLabel(?User $agent): string
    {
        if (! $agent) {
            return 'Unassigned';
        }

        $name = trim((string) ($agent->name ?? ''));
        $username = trim((string) ($agent->username ?? ''));

        if ($name && $username) {
            return $name.' ('.$username.')';
        }

        if ($name) {
            return $name;
        }

        if ($username) {
            return $username;
        }

        return 'Agent '.($agent->id ?? 'unknown');
    }

    private function groupCountsByDate(Collection $interactions): array
    {
        return $interactions
            ->filter(fn ($interaction) => $interaction->created_at)
            ->groupBy(fn ($interaction) => $interaction->created_at->toDateString())
            ->map(fn ($group) => $group->count())
            ->all();
    }

    private function buildDailyBestAgents(Collection $interactions): array
    {
        return $interactions
            ->filter(fn ($interaction) => $interaction->created_at)
            ->groupBy(fn ($interaction) => $interaction->created_at->toDateString())
            ->mapWithKeys(function (Collection $group, string $date) {
                $agentBuckets = $group->groupBy(fn ($interaction) => $interaction->agent?->id ?? 'unassigned');
                $best = $agentBuckets->map(function (Collection $bucket, $agentId) {
                    $interaction = $bucket->first();
                    $name = optional($interaction->agent)->username
                        ?? ($agentId === 'unassigned' ? 'Unassigned' : 'Agent '.$agentId);
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
                'label' => $weekStart->format('M j').' – '.$weekEnd->format('M j'),
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
}
