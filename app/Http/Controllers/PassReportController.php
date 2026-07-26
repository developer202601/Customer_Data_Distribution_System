<?php

namespace App\Http\Controllers;

use App\Models\CallCenterReport;
use App\Models\MasterDatasetProcess;
use App\Models\MasterDatasetRow;
use App\Support\MasterDatasetProcessStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PassReportController extends Controller
{
    // -------------------------------------------------------------------------
    // Segment bucket labels — must match MasterDatasetAssignmentService constants
    // -------------------------------------------------------------------------

    private const CC_BUCKET_LABELS = [
        'call center staff',
        'call center',
        'staff',
    ];

    private const RB_BUCKET_LABEL = 'regional billing center';

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    /**
     * Abort with 403 unless the session belongs to an admin user.
     */
    private function ensureAdmin(): void
    {
        $isAdmin = (bool) (session('user.is_admin') ?? false);
        if (! $isAdmin) {
            abort(403, 'Only administrators can pass records to calling units.');
        }
    }

    /**
     * Abort with 409 if the process is not in a state where passing is valid.
     * Returns the freshly-loaded process.
     */
    private function ensureReady(MasterDatasetProcess $process): MasterDatasetProcess
    {
        $process->refresh();

        if (! in_array($process->status, [
            MasterDatasetProcessStatus::READY,
            MasterDatasetProcessStatus::EXPORTS_PENDING,
        ], true)) {
            abort(409, 'Process is not ready for passing.');
        }

        return $process;
    }

    // -------------------------------------------------------------------------
    // Shared helpers
    // -------------------------------------------------------------------------

    /**
     * Build a deduplicated array of master_dataset_row IDs for the given
     * assigned_to values, scoped to the process.
     *
     * @param  string[]  $bucketLabels
     */
    private function buildRowIds(int $processId, array $bucketLabels): array
    {
        return MasterDatasetRow::query()
            ->where('process_id', $processId)
            ->where('excluded', false)
            ->whereIn(DB::raw('LOWER(TRIM(assigned_to))'), array_map('strtolower', $bucketLabels))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Create or update the CallCenterReport for the given process and type,
     * using the supplied row IDs.
     */
    private function upsertReport(MasterDatasetProcess $process, string $reportType, array $rowIds): CallCenterReport
    {
        return CallCenterReport::updateOrCreate(
            [
                'master_dataset_process_id' => $process->id,
                'report_type'               => $reportType,
            ],
            [
                'token'         => $process->token,
                'dataset_month' => $process->dataset_month,
                'report_type'   => $reportType,
                'row_count'     => count($rowIds),
                'row_ids'       => $rowIds,
            ]
        );
    }

    /**
     * Seed unassigned call_center_row_assignments rows for the Regional Billing report.
     * Uses insertOrIgnore so it is safe to call more than once.
     */
    private function seedRbAssignments(CallCenterReport $report, array $rowIds): void
    {
        $cleanRowIds = collect($rowIds)
            ->filter(fn ($id) => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($cleanRowIds)) {
            return;
        }

        // Only seed if no assignments exist yet for this report.
        $alreadySeeded = DB::table('call_center_row_assignments')
            ->where('call_center_report_id', $report->id)
            ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
            ->exists();

        if ($alreadySeeded) {
            return;
        }

        $now   = now()->toDateTimeString();
        $batch = [];
        $batchSize = 1000;

        foreach ($cleanRowIds as $rowId) {
            $batch[] = [
                'call_center_report_id' => $report->id,
                'report_type'           => CallCenterReport::REPORT_TYPE_REGIONAL_BILLING,
                'master_dataset_row_id' => $rowId,
                'assigned_user_id'      => null,
                'status'                => 'pending',
                'created_at'            => $now,
                'updated_at'            => $now,
            ];

            if (count($batch) >= $batchSize) {
                DB::table('call_center_row_assignments')->insertOrIgnore($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('call_center_row_assignments')->insertOrIgnore($batch);
        }
    }

    // -------------------------------------------------------------------------
    // Pass actions — CC segments
    // -------------------------------------------------------------------------

    /**
     * Pass Call Center Staff records to CC.
     * Creates (or refreshes) the shared CallCenterReport that contains all three
     * CC bucket rows so every segment admin sees the same report record.
     */
    public function passCCS(Request $request, MasterDatasetProcess $process): RedirectResponse
    {
        $this->ensureAdmin();
        $process = $this->ensureReady($process);

        if ($process->passed_ccs_at !== null) {
            return back()->with('status', 'Call Center Staff records have already been passed.');
        }

        $rowIds = $this->buildRowIds($process->id, self::CC_BUCKET_LABELS);

        DB::transaction(function () use ($process, $rowIds) {
            $this->upsertReport($process, CallCenterReport::REPORT_TYPE_CALL_CENTER, $rowIds);
            $process->passed_ccs_at = now();
            $process->save();
        });

        return back()->with('status', 'Call Center Staff records passed to CC.');
    }

    /**
     * Pass Call Center records to CC.
     */
    public function passCC(Request $request, MasterDatasetProcess $process): RedirectResponse
    {
        $this->ensureAdmin();
        $process = $this->ensureReady($process);

        if ($process->passed_cc_at !== null) {
            return back()->with('status', 'Call Center records have already been passed.');
        }

        $rowIds = $this->buildRowIds($process->id, self::CC_BUCKET_LABELS);

        DB::transaction(function () use ($process, $rowIds) {
            $this->upsertReport($process, CallCenterReport::REPORT_TYPE_CALL_CENTER, $rowIds);
            $process->passed_cc_at = now();
            $process->save();
        });

        return back()->with('status', 'Call Center records passed to CC.');
    }

    /**
     * Pass Staff records to CC.
     */
    public function passS(Request $request, MasterDatasetProcess $process): RedirectResponse
    {
        $this->ensureAdmin();
        $process = $this->ensureReady($process);

        if ($process->passed_s_at !== null) {
            return back()->with('status', 'Staff records have already been passed.');
        }

        $rowIds = $this->buildRowIds($process->id, self::CC_BUCKET_LABELS);

        DB::transaction(function () use ($process, $rowIds) {
            $this->upsertReport($process, CallCenterReport::REPORT_TYPE_CALL_CENTER, $rowIds);
            $process->passed_s_at = now();
            $process->save();
        });

        return back()->with('status', 'Staff records passed to CC.');
    }

    // -------------------------------------------------------------------------
    // Pass action — Regional Billing
    // -------------------------------------------------------------------------

    /**
     * Pass Regional Billing records to RB.
     * Creates the RB CallCenterReport and seeds unassigned assignment rows so
     * RB region/RTOM admins can see and distribute their records.
     */
    public function passRB(Request $request, MasterDatasetProcess $process): RedirectResponse
    {
        $this->ensureAdmin();
        $process = $this->ensureReady($process);

        if ($process->passed_rb_at !== null) {
            return back()->with('status', 'Regional Billing records have already been passed.');
        }

        $rowIds = $this->buildRowIds($process->id, [self::RB_BUCKET_LABEL]);

        DB::transaction(function () use ($process, $rowIds) {
            $report = $this->upsertReport($process, CallCenterReport::REPORT_TYPE_REGIONAL_BILLING, $rowIds);
            $this->seedRbAssignments($report, $rowIds);
            $process->passed_rb_at = now();
            $process->save();
        });

        return back()->with('status', 'Regional Billing records passed to RB.');
    }
}
