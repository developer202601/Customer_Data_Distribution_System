<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterReport;
use App\Models\MasterDatasetRow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class AssignmentController extends Controller
{
    private function ensureRtomAdmin(): array
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb') {
            abort(403);
        }

        $assignment = strtolower(trim((string) ($sessionUser['assignment'] ?? '')));
        if (! str_starts_with($assignment, 'rtom_')) {
            abort(403);
        }

        $rtom = trim(substr($assignment, 5));
        if ($rtom === '') {
            abort(403);
        }

        return [
            'sessionUser' => $sessionUser,
            'rtom' => $rtom,
        ];
    }

    public function index(Request $request) { abort(501); }
    public function manage(Request $request) { abort(501); }
    public function acceptAll(Request $request, $userId) { abort(501); }
    public function rejectAll(Request $request, $userId) { abort(501); }
    public function userRows(Request $request, $userId) { abort(501); }
    public function assignmentDetails(Request $request, $assignmentId) { abort(501); }
    public function claim($id) { abort(501); }
    public function complete($id) { abort(501); }
    public function storeInteraction(Request $request, $assignmentId) { abort(501); }
    public function accept(Request $request, $id) { abort(501); }
    public function reject(Request $request, $id) { abort(501); }

    public function distribute(Request $request, $reportId)
    {
        $ctx = $this->ensureRtomAdmin();
        $sessionUser = $ctx['sessionUser'];
        $rtom = $ctx['rtom'];

        $report = CallCenterReport::regionalBilling()->findOrFail((int) $reportId);

        $inputUserIds = $request->input('user_ids', []);
        $inputUserIds = array_values(array_filter(array_map('intval', (array) $inputUserIds), fn ($id) => $id > 0));
        if (empty($inputUserIds)) {
            return Redirect::route('rb.reports.summary', ['report' => $report->id])
                ->withErrors(['distribute' => 'Select at least one caller to distribute rows.']);
        }

        $callerIds = User::query()
            ->where('system', 'rb')
            ->where('status', 1)
            ->where('assignment', 'like', 'caller_%')
            ->where('supervisor', (int) ($sessionUser['id'] ?? 0))
            ->whereIn('id', $inputUserIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($callerIds)) {
            return Redirect::route('rb.reports.summary', ['report' => $report->id])
                ->withErrors(['distribute' => 'No valid callers selected for this RTOM admin.']);
        }

        $reportRowIds = collect($report->row_ids ?? [])->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values()->all();
        if (empty($reportRowIds)) {
            return Redirect::route('rb.reports.summary', ['report' => $report->id])
                ->withErrors(['distribute' => 'This report has no rows to distribute.']);
        }

        $hiddenRowIds = DB::table('call_center_report_hidden_rows')
            ->where('call_center_report_id', $report->id)
            ->where('report_type', CallCenterReport::REPORT_TYPE_REGIONAL_BILLING)
            ->pluck('master_dataset_row_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $candidateIds = array_values(array_diff($reportRowIds, $hiddenRowIds));
        if (empty($candidateIds)) {
            return Redirect::route('rb.reports.summary', ['report' => $report->id])
                ->withErrors(['distribute' => 'No visible rows available to distribute.']);
        }

        $rtomRowIds = MasterDatasetRow::query()
            ->whereIn('id', $candidateIds)
            ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower($rtom)])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($rtomRowIds)) {
            return Redirect::route('rb.reports.summary', ['report' => $report->id])
                ->withErrors(['distribute' => 'No rows matched your RTOM in this report.']);
        }

        $alreadyAssignedRowIds = CallCenterAssignment::regionalBilling()
            ->where('call_center_report_id', $report->id)
            ->whereIn('master_dataset_row_id', $rtomRowIds)
            ->whereNotNull('assigned_user_id')
            ->pluck('master_dataset_row_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $rowsToDistribute = array_values(array_diff($rtomRowIds, $alreadyAssignedRowIds));
        if (empty($rowsToDistribute)) {
            return Redirect::route('rb.reports.summary', ['report' => $report->id])
                ->with('status', 'All visible rows for this RTOM are already assigned.');
        }

        DB::transaction(function () use ($report, $rowsToDistribute, $callerIds) {
            $now = now();
            $total = count($rowsToDistribute);
            $userCount = count($callerIds);
            $base = intdiv($total, $userCount);
            $remainder = $total % $userCount;

            $inserts = [];
            $pos = 0;

            foreach ($callerIds as $index => $callerId) {
                $take = $base + ($index < $remainder ? 1 : 0);
                for ($i = 0; $i < $take && $pos < $total; $i++, $pos++) {
                    $inserts[] = [
                        'call_center_report_id' => $report->id,
                        'report_type' => CallCenterReport::REPORT_TYPE_REGIONAL_BILLING,
                        'master_dataset_row_id' => $rowsToDistribute[$pos],
                        'assigned_user_id' => $callerId,
                        'status' => 'pending',
                        'accepted' => false,
                        'rejected' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (! empty($inserts)) {
                DB::table('call_center_row_assignments')->insert($inserts);
            }
        });

        return Redirect::route('rb.reports.summary', ['report' => $report->id])
            ->with('status', 'Rows distributed to selected callers.');
    }

    public function cancelDistribute(Request $request, $reportId, $token) { abort(501); }
    public function recall(Request $request, $reportId) { abort(501); }
    public function recallPreview(Request $request, $reportId) { abort(501); }
    public function reassign(Request $request, $reportId) { abort(501); }
}
