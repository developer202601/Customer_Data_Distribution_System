<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterInteraction;
use App\Models\CallCenter\CallCenterUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(): View
    {
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $totalAssignedRows = CallCenterAssignment::whereNotNull('assigned_user_id')->count();

        $distinctExpr = DB::raw("DISTINCT COALESCE(account_number, CONCAT('assignment:', assignment_id))");

        $userStats = CallCenterUser::active()
            ->where('assignment', 'like', 'caller_%')
            ->orderBy('name')
            ->get()
            ->map(function (CallCenterUser $user) use ($monthStart, $totalAssignedRows, $distinctExpr) {
                $base = CallCenterInteraction::where('agent_id', $user->id);
                $monthly = (clone $base)->where('created_at', '>=', $monthStart);
                $assignmentBase = CallCenterAssignment::where('assigned_user_id', $user->id);

                $callsMonth = (clone $monthly)->count();
                $customersMonth = (clone $monthly)->count($distinctExpr);
                // Count payments by payment_date so "payments gathered this month" reflects actual payment dates
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

        // callers with zero assigned rows this month
        $unassignedThisMonth = $userStats->filter(fn($s) => ($s['assigned_rows_month'] ?? 0) == 0)->values();

        return view('callcenter.dashboard', [
            'userStats' => $userStats,
            'totalAssignedRows' => $totalAssignedRows,
            'monthLabel' => $monthStart->format('F Y'),
            // overview aggregates
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

    /**
     * Return last 7 days of calls taken by a caller (agent).
     */
    public function callerCalls7(Request $request, $id)
    {
        // Optionally restrict to admins or cc users; session.cc_user middleware applied to group
        $end = Carbon::now()->endOfDay();
        $start = Carbon::now()->subDays(6)->startOfDay();

        $rows = CallCenterInteraction::where('agent_id', (int) $id)
            ->whereBetween('created_at', [$start, $end])
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as cnt'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->pluck('cnt', 'day')
            ->toArray();

        // Build ordered array for the 7 days
        $labels = [];
        $data = [];
        $d = clone $start;
        while ($d->lte($end)) {
            $key = $d->format('Y-m-d');
            $labels[] = $d->format('M j');
            $data[] = isset($rows[$key]) ? (int) $rows[$key] : 0;
            $d->addDay();
        }

        return response()->json(['labels' => $labels, 'data' => $data]);
    }

    /**
     * Return a JSON list of pending or overdue payment customers.
     * Query param: type=pending|overdue
     */
    public function paymentList(Request $request)
    {
        $type = $request->query('type', 'pending');
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $q = CallCenterInteraction::with(['assignment.agent'])
            ->where('paid', false)
            ->whereNotNull('payment_expected_at');

        if ($type === 'pending') {
            $q->whereBetween('payment_expected_at', [$monthStart->toDateString(), $monthEnd->toDateString()]);
        } elseif ($type === 'overdue') {
            $q->where('payment_expected_at', '<', Carbon::now());
        } else {
            return response()->json(['error' => 'Invalid type'], 400);
        }

        $items = $q->orderBy('payment_expected_at')->orderBy('created_at', 'desc')->get()
            ->groupBy(function (CallCenterInteraction $i) {
                return $i->account_number ? $i->account_number : ('assignment:'.$i->assignment_id);
            })->map(function ($group) {
                /** @var CallCenterInteraction $i */
                $i = $group->first();
                return [
                    'account' => $i->account_number ?? null,
                    'assignment_id' => $i->assignment_id,
                    'payment_expected_at' => $i->payment_expected_at ? $i->payment_expected_at->toDateString() : null,
                    'assigned_user_id' => optional($i->assignment)->assigned_user_id,
                    'assigned_user_name' => optional($i->assignment->agent)->name ?? optional($i->assignment->agent)->username ?? null,
                ];
            })->values();

        return response()->json(['items' => $items]);
    }
}
