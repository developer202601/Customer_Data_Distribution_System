<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterReport;
use App\Models\CallCenter\CallCenterUser;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\MasterDatasetRow;
use App\Models\DatasetExport;
use Illuminate\Support\Facades\Storage;

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
}
