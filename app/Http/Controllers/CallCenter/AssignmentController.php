<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Jobs\DistributeCallCenterReport;
use App\Jobs\ReassignCallCenterRows;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterInteraction;
use Carbon\Carbon;
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

    /**
     * Show a manageable list of assigned rows with document details.
     * Visible to call-center users and admins.
     */
    public function manage(Request $request)
    {
        $reportId = $request->query('report');

        $q = CallCenterAssignment::with(['row', 'agent', 'report'])
            ->whereNotNull('assigned_user_id');

        // Always show only the current authenticated user's assignments (admins should not see other users' rows)
        $currentUserId = Auth::id() ?? session('user.id') ?? null;
        if ($currentUserId) {
            $q->where('assigned_user_id', $currentUserId);
        }

        if ($reportId) {
            $q->where('call_center_report_id', (int) $reportId);
        }

        $assignments = $q->orderBy('assigned_user_id')->orderBy('id')->get();

        // Build per-assignment metadata for filtering by call activity and promise status within the report period
        $assignmentMeta = [];
        $filterCounts = [
            'all' => 0,
            'called' => 0,
            'uncalled' => 0,
            'promise_overdue' => 0,
            'number_invalid' => 0,
            'not_answered' => 0,
        ];

        $now = Carbon::now();

        foreach ($assignments as $assignment) {
            $filterCounts['all']++;
            $row = $assignment->row;
            $startDate = null;
            if ($row && ! empty($row->new_arrears_column)) {
                if (preg_match('/(\d{8})/', $row->new_arrears_column, $m)) {
                    $d = $m[1];
                    try {
                        $startDate = Carbon::createFromFormat('Ymd', $d)->startOfDay();
                    } catch (\Exception $e) {
                        $startDate = null;
                    }
                }
            }

            $acct = $row->account_num ?? $row->customer_ref ?? null;
            $callQ = CallCenterInteraction::query();
            if ($acct) {
                $callQ->where('account_number', $acct);
            } else {
                $callQ->where('assignment_id', $assignment->id);
            }
            if ($startDate) {
                $callQ->where('created_at', '>=', $startDate->format('Y-m-d H:i:s'));
            }

            $latest = (clone $callQ)->orderBy('created_at', 'desc')->first();
            $callCount = (clone $callQ)->count();
            $calledInPeriod = $callCount > 0;

            $latestOutcome = $latest->outcome ?? null;
            $latestPaymentExpected = $latest && $latest->payment_expected_at ? Carbon::parse($latest->payment_expected_at)->toDateString() : null;
            $latestPaid = (bool) ($latest->paid ?? false);

            $promiseOverdue = false;
            if ($latest && in_array($latestOutcome, ['agreed to pay within 3 days', 'agreed to pay within 7 days'], true)) {
                if (! $latestPaid && $latest->payment_expected_at) {
                    try {
                        $due = Carbon::parse($latest->payment_expected_at)->endOfDay();
                        if ($due->lt($now)) {
                            $promiseOverdue = true;
                        }
                    } catch (\Exception $e) {
                        // ignore parse failures
                    }
                }
            }

            $assignmentMeta[$assignment->id] = [
                'call_count' => $callCount,
                'called_in_period' => $calledInPeriod,
                'latest_outcome' => $latestOutcome,
                'latest_payment_expected_at' => $latestPaymentExpected,
                'latest_paid' => $latestPaid,
                'promise_overdue' => $promiseOverdue,
            ];

            if ($calledInPeriod) {
                $filterCounts['called']++;
            } else {
                $filterCounts['uncalled']++;
            }
            if ($promiseOverdue) {
                $filterCounts['promise_overdue']++;
            }
            if ($latestOutcome === 'number invalid') {
                $filterCounts['number_invalid']++;
            }
            if ($latestOutcome === 'Not answered') {
                $filterCounts['not_answered']++;
            }
        }

        $grouped = $assignments->groupBy('assigned_user_id');

        $reportLabel = null;
        if ($reportId) {
            $r = \App\Models\CallCenterReport::find((int) $reportId);
            if ($r) {
                $dm = $r->dataset_month;
                $reportLabel = ($dm && strlen($dm) === 6) ? substr($dm,0,4).'/'.substr($dm,4,2).' report' : ($r->dataset_month ?: 'Unknown report');
            }
        }

        return view('callcenter.assignments.manage', [
            'assignments' => $assignments,
            'grouped' => $grouped,
            'reportId' => $reportId,
            'reportLabel' => $reportLabel,
            'assignmentMeta' => $assignmentMeta,
            'filterCounts' => $filterCounts,
        ]);
    }

    /**
     * Bulk accept all assignments for a specific user (optionally scoped to a report).
     */
    public function acceptAll(Request $request, $userId)
    {
        $reportId = $request->query('report');
        $query = CallCenterAssignment::where('assigned_user_id', $userId)->where('accepted', false);
        if ($reportId) $query->where('call_center_report_id', (int) $reportId);

        $assignments = $query->get();
        foreach ($assignments as $assignment) {
            $assignment->update([
                'accepted' => true,
                'accepted_at' => now(),
                'status' => 'completed',
                'locked_at' => null,
                'locked_by' => null,
            ]);
            CallCenterInteraction::create([
                'assignment_id' => $assignment->id,
                'agent_id' => Auth::id(),
                'outcome' => 'accepted',
                'note' => 'Bulk accepted by admin/user',
            ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['accepted' => $assignments->count()]);
        }

        return Redirect::route('cc.assignments.manage', ['report' => $reportId])->with('status', 'Accepted '.$assignments->count().' assignments.');
    }

    /**
     * Bulk reject all assignments for a specific user (optionally scoped to a report).
     */
    public function rejectAll(Request $request, $userId)
    {
        $reportId = $request->query('report');
        $query = CallCenterAssignment::where('assigned_user_id', $userId)->where('rejected', false);
        if ($reportId) $query->where('call_center_report_id', (int) $reportId);

        $assignments = $query->get();
        foreach ($assignments as $assignment) {
            $assignment->update([
                'rejected' => true,
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
                'rejection_note' => 'Bulk rejected',
                'status' => 'pending',
                'locked_at' => null,
                'locked_by' => null,
            ]);
            CallCenterInteraction::create([
                'assignment_id' => $assignment->id,
                'agent_id' => Auth::id(),
                'outcome' => 'rejected',
                'note' => 'Bulk rejected by admin/user',
            ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['rejected' => $assignments->count()]);
        }

        return Redirect::route('cc.assignments.manage', ['report' => $reportId])->with('status', 'Rejected '.$assignments->count().' assignments.');
    }

    /**
     * Return accepted assignments rows for a user (JSON) - used by AJAX to load details after accept.
     */
    public function userRows(Request $request, $userId)
    {
        $currentUserId = Auth::id() ?? session('user.id');
        if ($currentUserId && (int) $userId !== (int) $currentUserId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $reportId = $request->query('report');
        $perPage = max(1, min((int) $request->query('per_page', 50), 200));
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $q = CallCenterAssignment::with('row')
            ->where('assigned_user_id', $userId)
            ->where('accepted', true);
        if ($reportId) $q->where('call_center_report_id', (int) $reportId);

        $total = (clone $q)->count();

        $rows = $q->orderBy('id')
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(function ($a) {
                $row = $a->row;
                $startDate = null;
                if ($row && ! empty($row->new_arrears_column)) {
                    if (preg_match('/(\d{8})/', $row->new_arrears_column, $m)) {
                        try {
                            $startDate = Carbon::createFromFormat('Ymd', $m[1])->startOfDay();
                        } catch (\Exception $e) {
                            $startDate = null;
                        }
                    }
                }

                $acct = $row->account_num ?? $row->customer_ref ?? null;
                $callQ = CallCenterInteraction::query();
                if ($acct) {
                    $callQ->where('account_number', $acct);
                } else {
                    $callQ->where('assignment_id', $a->id);
                }
                if ($startDate) {
                    $callQ->where('created_at', '>=', $startDate->format('Y-m-d H:i:s'));
                }

                $latest = (clone $callQ)->orderBy('created_at', 'desc')->first();
                $callCount = (clone $callQ)->count();
                $calledInPeriod = $callCount > 0;

                $latestOutcome = $latest->outcome ?? null;
                $latestPaymentExpected = $latest && $latest->payment_expected_at ? Carbon::parse($latest->payment_expected_at)->toDateString() : null;
                $latestPaid = (bool) ($latest->paid ?? false);
                $promiseOverdue = false;
                if ($latest && in_array($latestOutcome, ['agreed to pay within 3 days', 'agreed to pay within 7 days'], true)) {
                    if (! $latestPaid && $latest->payment_expected_at) {
                        try {
                            $due = Carbon::parse($latest->payment_expected_at)->endOfDay();
                            if ($due->lt(Carbon::now())) {
                                $promiseOverdue = true;
                            }
                        } catch (\Exception $e) {
                            $promiseOverdue = false;
                        }
                    }
                }

                return [
                    'assignment_id' => $a->id,
                    'row_id' => $a->master_dataset_row_id,
                    'address_name' => optional($row)->address_name,
                    'arrears' => optional($row)->new_arrears_value,
                    'bill' => optional($row)->latest_bill_mny,
                    'call_count' => $callCount,
                    'called_in_period' => $calledInPeriod,
                    'latest_outcome' => $latestOutcome,
                    'latest_payment_expected_at' => $latestPaymentExpected,
                    'promise_overdue' => $promiseOverdue,
                ];
            });

        return response()->json([
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'has_more' => ($offset + $perPage) < $total,
            ],
        ]);
    }

    /**
     * Return full details for a specific assignment's row (AJAX)
     */
    public function assignmentDetails(Request $request, $assignmentId)
    {
        $assignment = CallCenterAssignment::with('row')->findOrFail($assignmentId);
        $r = $assignment->row;
        // load recent interactions. Prefer to load by account number (cross-assignment)
        $acct = $r->account_num ?? $r->customer_ref ?? null;
        $q = CallCenterInteraction::with('agent');
        if ($acct) {
            $q->where('account_number', $acct);
        } else {
            $q->where('assignment_id', $assignment->id);
        }

        $interactions = $q->orderBy('created_at', 'desc')->get()->map(function ($i) {
                return [
                    'id' => $i->id,
                    'agent_id' => $i->agent_id,
                    'agent_name' => $i->agent ? ($i->agent->name ?? $i->agent->username ?? null) : null,
                    'outcome' => $i->outcome,
                    'note' => $i->note,
                    'account_number' => $i->account_number ?? null,
                    'paid' => (bool) ($i->paid ?? false),
                    'paid_amount' => $i->paid_amount ? number_format($i->paid_amount, 2) : null,
                    'payment_expected_at' => $i->payment_expected_at ? $i->payment_expected_at->toDateString() : null,
                    'payment_date' => $i->payment_date ? $i->payment_date->toDateString() : null,
                    'created_at' => $i->created_at ? $i->created_at->toDateTimeString() : null,
                ];
            });

        // determine report period start from the row's new_arrears_column if present
        $callCount = 0;
        try {
            $startDate = null;
            if ($r && ! empty($r->new_arrears_column)) {
                // expect format like NEW_ARREARS_YYYYMMDD
                if (preg_match('/(\d{8})/', $r->new_arrears_column, $m)) {
                    $d = $m[1];
                    $startDate = \DateTime::createFromFormat('Ymd', $d);
                    if ($startDate) {
                        $startDate = $startDate->format('Y-m-d') . ' 00:00:00';
                    } else {
                        $startDate = null;
                    }
                }
            }

            $countQ = \App\Models\CallCenterInteraction::query();
            if ($acct) {
                $countQ->where('account_number', $acct);
            } else {
                $countQ->where('assignment_id', $assignment->id);
            }
            if ($startDate) {
                $countQ->where('created_at', '>=', $startDate);
            }
            $callCount = $countQ->count();
        } catch (\Exception $e) {
            $callCount = 0;
        }

        return response()->json([
            'assignment_id' => $assignment->id,
            'row_id' => $assignment->master_dataset_row_id ?? $r->id ?? null,
            'address_name' => $r->address_name ?? null,
            'arrears' => $r->new_arrears_value ?? null,
            'bill' => $r->latest_bill_mny ?? null,
            'phone' => $r->mobile_contact_tel ?? null,
            'address' => $r->full_address ?? null,
            'rtom' => $r->rtom ?? null,
            'customer_ref' => $r->customer_ref ?? null,
            'account_num' => $r->account_num ?? null,
            'sales_person' => $r->sales_person ?? null,
            'sales_channel' => $r->sales_channel ?? null,
            'interactions' => $interactions,
            'call_count' => $callCount,
        ]);
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
        $agentId = $user->id ?? session('user.id') ?? null;
        if (! $agentId) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            return Redirect::route('login');
        }

        $payload = $request->validate([
            'outcome' => 'nullable|string|max:100',
            'note' => 'nullable|string',
            'payment_expected_at' => 'nullable|date',
            'paid' => 'nullable|boolean',
            'payment_date' => 'nullable|date',
            'paid_amount' => 'nullable|numeric',
        ]);

        // attempt to attach the account number from the assignment's row if available
        $assignment = CallCenterAssignment::with('row')->find($assignmentId);
        $accountNumber = null;
        if ($assignment && $assignment->row) {
            $accountNumber = $assignment->row->account_num ?? $assignment->row->customer_ref ?? null;
        }

        $interaction = CallCenterInteraction::create(array_merge($payload, [
            'assignment_id' => $assignmentId,
            'agent_id' => $agentId,
            'account_number' => $accountNumber,
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

            // If the interaction includes a payment, mirror it on the assignment
            if (! empty($payload['paid'])) {
                $assignment->paid = true;
                if (! empty($payload['payment_date'])) {
                    $assignment->payment_date = $payload['payment_date'];
                }
                if (isset($payload['paid_amount']) && $payload['paid_amount'] !== null) {
                    $assignment->paid_amount = $payload['paid_amount'];
                }
            }

            $assignment->save();
        });

        return response()->json($interaction, 201);
    }

    public function accept(Request $request, $id)
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

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['accepted' => true]);
        }

        return Redirect::route('cc.assignments.manage')->with('status', 'Assignment accepted.');
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

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['rejected' => true]);
        }

        return Redirect::route('cc.assignments.manage')->with('status', 'Assignment rejected.');
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
