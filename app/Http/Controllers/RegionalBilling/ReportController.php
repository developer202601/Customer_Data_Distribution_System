<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Models\CallCenterReport;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    protected function ensureRegionalBillingUser()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb') {
            abort(403);
        }

        return $sessionUser;
    }

    public function index(Request $request): View
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $region = $sessionUser['assignment'] ?? null;

        $reports = CallCenterReport::regionalBilling()
            ->whereHas('assignments.row', function ($query) use ($region) {
                $query->where('region', $region);
            })
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('regionalbilling.reports.index', compact('reports', 'region'));
    }

    public function history(Request $request): View
    {
        $sessionUser = $this->ensureRegionalBillingUser();
        $region = $sessionUser['assignment'] ?? null;

        $reports = CallCenterReport::regionalBilling()
            ->whereHas('assignments.row', function ($query) use ($region) {
                $query->where('region', $region);
            })
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('regionalbilling.reports.history', compact('reports', 'region'));
    }

    public function summary(CallCenterReport $report): View
    {
        $this->ensureRegionalBillingUser();

        abort_if($report->report_type !== CallCenterReport::REPORT_TYPE_REGIONAL_BILLING, 404);

        $reportRows = $report->assignments()->with('row', 'agent')->whereHas('row', function ($query) use ($report) {
            $query->where('region', session('user.assignment'));
        })->get();

        $assigned = $reportRows->whereNotNull('assigned_user_id')->count();
        $unassigned = $reportRows->whereNull('assigned_user_id')->count();
        $hidden = $report->hiddenRows()->count();
        $reviews = $report->regionReviews()->count();

        return view('regionalbilling.reports.summary', compact('report', 'reportRows', 'assigned', 'unassigned', 'hidden', 'reviews'));
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
}
