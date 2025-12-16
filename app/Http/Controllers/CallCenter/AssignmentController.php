<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Jobs\DistributeCallCenterReport;
use App\Jobs\ReassignCallCenterRows;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterInteraction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Bus;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (! $user) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            return Redirect::route('login');
        }
        $perPage = (int) $request->get('per_page', 25);

        $q = CallCenterAssignment::where('assigned_user_id', $user->id)
            ->with('row', 'interactions')
            ->orderBy('id', 'asc');

        return response()->json($q->paginate($perPage));
    }

    public function claim($id)
    {
        $user = Auth::user();

        $updated = CallCenterAssignment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('status', 'pending')->orWhere(function ($q2) use ($user) {
                    $q2->where('assigned_user_id', $user->id)->where('status','claimed');
                });
            })
            ->update([
                'status' => 'claimed',
                'locked_at' => now(),
                'locked_by' => $user->id,
                'assigned_user_id' => $user->id,
            ]);

        return response()->json(['updated' => $updated]);
    }

    public function complete($id)
    {
        $user = Auth::user();

        $updated = CallCenterAssignment::where('id', $id)
            ->where('assigned_user_id', $user->id)
            ->update(['status' => 'completed', 'locked_at' => null, 'locked_by' => null]);

        return response()->json(['updated' => $updated]);
    }

    public function storeInteraction(Request $request, $assignmentId)
    {
        $user = Auth::user();

        $payload = $request->validate([
            'outcome' => 'nullable|string|max:100',
            'note' => 'nullable|string',
            'payment_expected_at' => 'nullable|date',
            'paid' => 'nullable|boolean',
            'payment_date' => 'nullable|date',
        ]);

        $interaction = CallCenterInteraction::create(array_merge($payload, [
            'assignment_id' => $assignmentId,
            'agent_id' => $user->id,
        ]));

        // update assignment summary/status in a transaction
        DB::transaction(function () use ($interaction, $payload) {
            $assignment = CallCenterAssignment::find($interaction->assignment_id);
            if (! $assignment) return;

            $assignment->locked_at = null;
            $assignment->locked_by = null;
            // If the interaction indicates paid or a terminal outcome, mark completed
            if (! empty($payload['paid']) || in_array(($payload['outcome'] ?? ''), ['paid','promise_to_pay'])) {
                $assignment->status = 'completed';
            }
            $assignment->save();
        });

        return response()->json($interaction, 201);
    }

    public function accept($id)
    {
        $user = Auth::user();

        $assignment = CallCenterAssignment::where('id', $id)
            ->where('assigned_user_id', $user->id)
            ->where('rejected', false)
            ->firstOrFail();

        $assignment->update([
            'accepted' => true,
            'accepted_at' => now(),
            'status' => 'completed',
            'locked_at' => null,
            'locked_by' => null,
        ]);

        CallCenterInteraction::create([
            'assignment_id' => $assignment->id,
            'agent_id' => $user->id,
            'outcome' => 'accepted',
            'note' => 'Assignment approved by staff',
        ]);

        return response()->json(['accepted' => true]);
    }

    public function reject(Request $request, $id)
    {
        $user = Auth::user();

        $assignment = CallCenterAssignment::where('id', $id)
            ->where('assigned_user_id', $user->id)
            ->firstOrFail();

        $payload = $request->validate([
            'note' => 'nullable|string',
        ]);

        $assignment->update([
            'rejected' => true,
            'rejected_at' => now(),
            'rejected_by' => $user->id,
            'rejection_note' => $payload['note'] ?? null,
            'status' => 'pending',
            'locked_at' => null,
            'locked_by' => null,
        ]);

        CallCenterInteraction::create([
            'assignment_id' => $assignment->id,
            'agent_id' => $user->id,
            'outcome' => 'rejected',
            'note' => $payload['note'] ?? 'Assignment rejected by staff',
        ]);

        return response()->json(['rejected' => true]);
    }

    public function reassign(Request $request, $reportId)
    {
        $assignmentIds = $request->input('assignment_ids', []);
        $userIds = $request->input('user_ids', []);
        $action = $request->input('action');

        $assignmentIds = array_map('intval', array_filter($assignmentIds, fn ($id) => is_numeric($id) && $id > 0));
        $userIds = array_map('intval', array_filter($userIds, fn ($id) => is_numeric($id) && $id > 0));

        if ($action === 'pool') {
            $userIds = [];
        }

        if (empty($assignmentIds)) {
            return Redirect::route('cc.reports', ['report' => $reportId])->withErrors(['reassign' => 'Select at least one rejected assignment to reassign.']);
        }

        Bus::dispatchSync(new ReassignCallCenterRows($assignmentIds, $userIds));

        return Redirect::route('cc.reports', ['report' => $reportId])->with('status', 'Reassignment queued.');
    }

    // Admin helper to dispatch distribution job
    public function distribute(Request $request, $reportId)
    {
        $userIds = $request->input('user_ids', []);
        $perUser = $request->input('per_user', null);
        $user = Auth::user();
        // Always run synchronously so the page displays updated assignments immediately after form submission.
        Bus::dispatchSync(new DistributeCallCenterReport($reportId, $userIds, $perUser));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['dispatched' => true]);
        }

        return Redirect::route('cc.reports', ['report' => $reportId])->with('status', 'Distribution queued.');
    }

    public function recall(Request $request, $reportId)
    {
        // Only allow recall if no assignments have been accepted for this report
        $acceptedExists = CallCenterAssignment::where('call_center_report_id', $reportId)->where('accepted', true)->exists();
        if ($acceptedExists) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['recalled' => false, 'error' => 'Cannot recall: some assignments have been accepted.'], 409);
            }

            return Redirect::route('cc.reports', ['report' => $reportId])->withErrors(['recall' => 'Cannot recall: some assignments have been accepted.']);
        }

        $assignmentIds = CallCenterAssignment::where('call_center_report_id', $reportId)->pluck('id')->toArray();
        if (empty($assignmentIds)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['recalled' => false, 'error' => 'No assignments found for this report.'], 404);
            }

            return Redirect::route('cc.reports', ['report' => $reportId])->with('status', 'No assignments to recall.');
        }

        // Use the existing reassignment job to reset assignments back to the pool
        Bus::dispatchSync(new ReassignCallCenterRows($assignmentIds, []));

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['recalled' => true]);
        }

        return Redirect::route('cc.reports', ['report' => $reportId])->with('status', 'Assignments recalled to pool.');
    }

    public function recallPreview(Request $request, $reportId)
    {
        $assignments = CallCenterAssignment::with(['row', 'agent'])
            ->where('call_center_report_id', $reportId)
            ->whereNotNull('assigned_user_id')
            ->orderBy('id')
            ->limit(200)
            ->get();

        $count = CallCenterAssignment::where('call_center_report_id', $reportId)->whereNotNull('assigned_user_id')->count();

        $payload = [
            'count' => $count,
            'sample' => $assignments->map(fn($a) => [
                'assignment_id' => $a->id,
                'row_id' => $a->master_dataset_row_id,
                'phone' => optional($a->row)->phone,
                'assigned_user' => optional($a->agent)->username,
            ]),
        ];

        return response()->json($payload);
    }

    public function cancelDistribute(\Illuminate\Http\Request $request, $reportId, $token)
    {
        $cacheKey = 'cc:pending:distribute:'.$token;
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['cancelled' => true]);
            }

            return Redirect::route('cc.reports', ['report' => $reportId])->with('status', 'Distribution cancelled.');
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['cancelled' => false, 'error' => 'No pending distribution found or it already ran.'], 404);
        }

        return Redirect::route('cc.reports', ['report' => $reportId])->withErrors(['distribute' => 'No pending distribution found or it already ran.']);
    }
}
