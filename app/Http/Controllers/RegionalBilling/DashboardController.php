<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterInteraction;
use App\Models\CallCenterReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $sessionUser = session('user');
        $assignment = $sessionUser['assignment'] ?? null;
        
        // Redirect non-super users to their respective dashboards
        if ($assignment !== 'super') {
            if ($assignment && str_starts_with($assignment, 'caller_')) {
                return redirect()->route('rb.assignments.list');
            }
            if ($assignment && str_starts_with($assignment, 'supervisor_')) {
                return redirect()->route('rb.supervisor.dashboard');
            }
            if ($assignment && str_starts_with($assignment, 'rtom_')) {
                return redirect()->route('rb.rtom.dashboard');
            }
            if ($assignment) {
                return redirect()->route('rb.region.dashboard');
            }
            abort(403, 'Only super admins can access the overview dashboard.');
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $totalAssignedRows = CallCenterAssignment::where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
            ->whereNotNull('assigned_user_id')->count();

        $distinctExpr = DB::raw("DISTINCT COALESCE(account_number, CONCAT('assignment:', assignment_id))");

        $userStats = User::where('system', 'rb')
            ->where('assignment', 'like', 'caller_%')
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($monthStart, $totalAssignedRows, $distinctExpr) {
                $base = CallCenterInteraction::where('agent_id', $user->id);
                $monthly = (clone $base)->where('created_at', '>=', $monthStart);
                $assignmentBase = CallCenterAssignment::where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
                    ->where('assigned_user_id', $user->id);

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

        // callers with zero assigned rows this month
        $unassignedThisMonth = $userStats->filter(fn($s) => ($s['assigned_rows_month'] ?? 0) == 0)->values();

        return view('regionalbilling.dashboard', [
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
}
