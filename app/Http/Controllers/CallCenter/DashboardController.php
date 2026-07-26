<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterInteraction;
use App\Models\CallCenterReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $sessionUser = session('user');
        $assignment = $sessionUser['assignment'] ?? null;
        
        if ($assignment !== 'super') {
            if ($assignment && str_starts_with($assignment, 'caller_')) {
                return redirect()->route('cc.assignments.manage');
            }
            if ($assignment) {
                return redirect()->route('cc.region.dashboard');
            }
            abort(403, 'Only super admins can access the overview dashboard.');
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $totalAssignedRows = CallCenterAssignment::callCenter()->whereNotNull('assigned_user_id')->count();

        $distinctExpr = DB::raw("DISTINCT COALESCE(account_number, CONCAT('assignment:', assignment_id))");

        $userStats = User::where('system', 'cc')
            ->where('assignment', 'like', 'caller_%')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($monthStart, $totalAssignedRows, $distinctExpr) {
                $base = CallCenterInteraction::where('agent_id', $user->id);
                $monthly = (clone $base)->where('created_at', '>=', $monthStart);
                $assignmentBase = CallCenterAssignment::callCenter()->where('assigned_user_id', $user->id);

                $callsMonth = (clone $monthly)->count();
                $customersMonth = (clone $monthly)->count($distinctExpr);
                $monthlyPaymentsByDate = (clone $base)->where('paid', true)->where('payment_date', '>=', $monthStart);
                    $paymentsMonth = (clone $monthlyPaymentsByDate)->count();
                    $customersWithPaymentsMonth = (clone $monthlyPaymentsByDate)->count($distinctExpr);
                    $paymentsAmountMonth = (clone $monthlyPaymentsByDate)->sum('paid_amount');

                $callsAll = (clone $base)->count();
                $customersAll = (clone $base)->count($distinctExpr);
                    $paymentsAll = (clone $base)->where('paid', true)->count();
                    $customersWithPaymentsAll = (clone $base)->where('paid', true)->count($distinctExpr);
                    $paymentsAmountAll = (clone $base)->where('paid', true)->sum('paid_amount');

                $assignedRowsAll = (clone $assignmentBase)->count();
                $assignedRowsMonth = (clone $assignmentBase)->where('created_at', '>=', $monthStart)->count();

                $coverageAll = $totalAssignedRows > 0 ? round(($customersAll / $totalAssignedRows) * 100, 1) : 0;
                $coverageMonth = $totalAssignedRows > 0 ? round(($customersMonth / $totalAssignedRows) * 100, 1) : 0;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'customers_month' => $customersMonth,
                    'customers_all' => $customersAll,
                    'payments_month' => $paymentsMonth,
                    'payments_all' => $paymentsAll,
                    'customers_with_payments_month' => $customersWithPaymentsMonth,
                    'customers_with_payments_all' => $customersWithPaymentsAll,
                    'calls_month' => $callsMonth,
                    'calls_all' => $callsAll,
                    'coverage_all' => $coverageAll,
                    'coverage_month' => $coverageMonth,
                    'assigned_rows_month' => $assignedRowsMonth,
                    'assigned_rows_all' => $assignedRowsAll,
                        'payments_amount_month' => $paymentsAmountMonth ?? 0,
                        'payments_amount_all' => $paymentsAmountAll ?? 0,
                    'conversion_all' => $callsAll ? round(($paymentsAll / $callsAll) * 100, 1) : 0,
                    'conversion_month' => $callsMonth ? round(($paymentsMonth / $callsMonth) * 100, 1) : 0,
                ];
            });

        $unassignedThisMonth = $userStats->filter(fn($s) => ($s['assigned_rows_month'] ?? 0) == 0)->values();

        return view('callcenter.dashboard', [
            'userStats' => $userStats,
            'totalAssignedRows' => $totalAssignedRows,
            'monthLabel' => $monthStart->format('F Y'),
            'pendingPaymentsThisMonth' => CallCenterInteraction::where('paid', false)
                ->whereNotNull('payment_expected_at')
                ->whereBetween('payment_expected_at', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->count($distinctExpr),
            'overduePayments' => CallCenterInteraction::where('paid', false)
                ->whereNotNull('payment_expected_at')
                ->where('payment_expected_at', '<', Carbon::now())
                ->count($distinctExpr),
            'unassigned_callers_month' => $unassignedThisMonth,
            'unassigned_callers_month_count' => $unassignedThisMonth->count(),
        ]);
    }

    public function listPayments(Request $request): JsonResponse
    {
        $type = $request->query('type', 'pending');
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $query = CallCenterInteraction::query()
            ->where('paid', false)
            ->whereNotNull('payment_expected_at')
            ->with(['agent'])
            ->orderBy('payment_expected_at');

        if ($type === 'overdue') {
            $query->where('payment_expected_at', '<', $now);
        } else {
            $query->whereBetween('payment_expected_at', [$monthStart->toDateString(), $monthEnd->toDateString()]);
        }

        $items = $query->limit(200)->get()->map(function ($interaction) {
            return [
                'account'            => $interaction->account_number,
                'assignment_id'      => $interaction->assignment_id,
                'payment_expected_at'=> optional($interaction->payment_expected_at)->toDateString(),
                'assigned_user_id'   => $interaction->agent_id,
                'assigned_user_name' => optional($interaction->agent)->name,
            ];
        });

        return response()->json(['items' => $items]);
    }

    public function callerDashboard(): View
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }

        $assignment = strtolower(trim((string) ($sessionUser['assignment'] ?? '')));
        if (! str_starts_with($assignment, 'caller_')) {
            abort(403);
        }

        $userId = $sessionUser['id'] ?? null;
        if (! $userId) {
            abort(403);
        }

        $base = CallCenterAssignment::callCenter()->where('assigned_user_id', $userId);
        $totalAssigned = (clone $base)->count();
        $pendingAccepted = (clone $base)->where('accepted', true)->where('status', 'pending')->count();
        $pendingAcceptance = (clone $base)->where('accepted', false)->where('rejected', false)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $rejected = (clone $base)->where('rejected', true)->count();

        $latestReportId = (clone $base)->max('call_center_report_id');
        $latestReportLabel = null;
        if ($latestReportId) {
            $report = CallCenterReport::callCenter()->find((int) $latestReportId);
            if ($report) {
                $dm = $report->dataset_month;
                $latestReportLabel = ($dm && strlen($dm) === 6)
                    ? substr($dm, 0, 4) . '/' . substr($dm, 4, 2) . ' report'
                    : ($report->dataset_month ?: 'Report #' . $report->id);
            }
        }

        $recentAssignments = CallCenterAssignment::callCenter()
            ->with(['row', 'report'])
            ->where('assigned_user_id', $userId)
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('callcenter.caller.dashboard', [
            'totalAssigned' => $totalAssigned,
            'pendingAccepted' => $pendingAccepted,
            'pendingAcceptance' => $pendingAcceptance,
            'completed' => $completed,
            'rejected' => $rejected,
            'latestReportLabel' => $latestReportLabel,
            'recentAssignments' => $recentAssignments,
        ]);
    }
}
