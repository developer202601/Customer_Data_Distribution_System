<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Find all CC region admin user IDs:
        //    system='cc', assignment is not 'super', not starting with
        //    caller_/supervisor_/rtom_/segment_
        $ccRegionAdminIds = DB::table('users')
            ->where('system', 'cc')
            ->where('assignment', '<>', 'super')
            ->where('assignment', 'not like', 'caller_%')
            ->where('assignment', 'not like', 'supervisor_%')
            ->where('assignment', 'not like', 'rtom_%')
            ->where('assignment', 'not like', 'segment_%')
            ->whereNotNull('assignment')
            ->pluck('id')
            ->toArray();

        if (! empty($ccRegionAdminIds)) {
            // 2. Delete CC region review records (not RB — scoped to call-center report type)
            $ccReportIds = DB::table('call_center_reports')
                ->where('report_type', 'call-center')
                ->pluck('id')
                ->toArray();

            if (! empty($ccReportIds)) {
                DB::table('call_center_report_region_reviews')
                    ->whereIn('call_center_report_id', $ccReportIds)
                    ->delete();

                DB::table('call_center_report_hidden_rows')
                    ->whereIn('call_center_report_id', $ccReportIds)
                    ->where('report_type', 'call-center')
                    ->delete();
            }

            // 3. Delete the CC region admin users themselves
            DB::table('users')
                ->whereIn('id', $ccRegionAdminIds)
                ->delete();
        }
    }

    public function down(): void
    {
        // Intentionally not reversible — deleted users cannot be restored.
    }
};
