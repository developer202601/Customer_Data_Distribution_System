<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallCenterReport;
use App\Models\Configurations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerApiController extends Controller
{
    /**
     * Return account number and name for unpaid customers in the latest report
     * who are assigned to callers but not yet called.
     */
    public function getUncalledLatestReportCustomers(Request $request): JsonResponse
    {
        // Identify the latest report (optionally filtered by report_type e.g. 'regional-billing')
        $reportType = $request->query('report_type');

        $reportQuery = CallCenterReport::query()->orderBy('id', 'desc');
        if ($reportType) {
            $reportQuery->where('report_type', $reportType);
        }
        $latestReport = $reportQuery->first();

        if (!$latestReport) {
            return response()->json([
                'success' => false,
                'message' => 'No reports found.',
                'data'    => [],
            ], 404);
        }

        // 2. Fetch the system outstanding arrears threshold
        $threshold = (float) (Configurations::where('config_name', 'outstanding_threshold')->value('value') ?? 0);

        // 3. Build optimized query
        $query = DB::table('call_center_row_assignments as a')
            ->join('master_dataset_rows as r', 'a.master_dataset_row_id', '=', 'r.id')
            // Condition 1: Latest report
            ->where('a.call_center_report_id', $latestReport->id)
            // Condition 2: Assigned to a caller and not rejected
            ->whereNotNull('a.assigned_user_id')
            ->where('a.rejected', false)
            // Condition 3: Unpaid (Arrears - Payments >= threshold)
            ->whereRaw('(COALESCE(r.new_arrears_value, 0) - COALESCE(r.payments_value, 0)) >= ?', [$threshold])
            // Condition 4: Not yet called (No interaction recorded for assignment or account)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('call_center_interactions as i')
                    ->whereColumn('i.assignment_id', 'a.id')
                    ->orWhere(function ($orSub) {
                        $orSub->whereNotNull('r.account_num')
                              ->whereColumn('i.account_number', 'r.account_num');
                    });
            })
            ->select([
                DB::raw('COALESCE(r.account_num, r.customer_ref) as account_number'),
                'r.address_name as customer_name',
            ])
            ->whereNotNull(DB::raw('COALESCE(r.account_num, r.customer_ref)'))
            ->distinct();

        // 4. Handle pagination if requested, otherwise return complete collection
        if ($request->has('page') || $request->has('per_page')) {
            $perPage = min((int) $request->query('per_page', 100), 1000);
            $paginated = $query->paginate($perPage);

            return response()->json([
                'success'      => true,
                'report_id'    => $latestReport->id,
                'report_month' => $latestReport->dataset_month,
                'data'         => $paginated->items(),
                'pagination'   => [
                    'total'        => $paginated->total(),
                    'per_page'     => $paginated->perPage(),
                    'current_page' => $paginated->currentPage(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ]);
        }

        $customers = $query->get();

        return response()->json([
            'success'      => true,
            'report_id'    => $latestReport->id,
            'report_month' => $latestReport->dataset_month,
            'count'        => $customers->count(),
            'data'         => $customers,
        ]);
    }
}
