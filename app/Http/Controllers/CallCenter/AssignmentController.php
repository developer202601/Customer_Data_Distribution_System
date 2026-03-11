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
use Illuminate\Support\Collection;

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
            ->where('status', '<>', 'completed')
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
        // Prevent super-assigned admin users from accessing the assign/manage rows page
        $sessionUser = $request->session()->get('user');
        if (! empty($sessionUser) && (($sessionUser['assignment'] ?? null) === 'super')) {
            abort(403);
        }

        $reportId = $request->query('report');

        $currentUserId = auth()->id() ?? session('user.id') ?? null;

        $latestReportId = null;
        $latestAcceptedReportId = null;
        $allUserAssignments = collect();
        if ($currentUserId) {
            // Keep two report markers:
            // - latestReportId: newest report assigned to the user (including pending)
            // - latestAcceptedReportId: newest report with at least one accepted row
            // Default view should use accepted rows so existing work does not disappear.
            $allUserAssignments = CallCenterAssignment::where('assigned_user_id', $currentUserId)
                ->whereNotNull('call_center_report_id')
                ->get(['id', 'call_center_report_id', 'accepted', 'rejected', 'reassignment_origin_id']);

            $latestReportId = $allUserAssignments->max('call_center_report_id');
            $latestAcceptedReportId = $allUserAssignments
                ->where('accepted', true)
                ->max('call_center_report_id');
        }

        // If no specific report is requested, prefer latest report with accepted rows.
        // Fall back to newest assigned report when there are no accepted rows yet.
        if (! $reportId) {
            $reportId = $latestAcceptedReportId ?: $latestReportId;
        }

        $q = CallCenterAssignment::with(['row', 'agent', 'report'])
            ->whereNotNull('assigned_user_id');

        // Always show only the current authenticated user's assignments
        if ($currentUserId) {
            $q->where('assigned_user_id', $currentUserId);
        }

        // Only show assignments from the latest/current report
        if ($reportId) {
            $q->where('call_center_report_id', (int) $reportId);
        }

        // Only show accepted assignments (hide pending/rejected)
        $q->where('accepted', true);

        // Include all accepted assignments from current report, even if marked completed (paid)
        // Completed status just means the interaction was recorded, not that it should be hidden

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
            'not_relevant_person' => 0,
        ];

        $now = Carbon::now();

        foreach ($assignments as $assignment) {
            $filterCounts['all']++;
            // Count calls only after the user explicitly accepted this assignment.
            // If the assignment is not yet accepted, calls-since count is zero.
            $row = $assignment->row;
            $acct = $row->account_num ?? $row->customer_ref ?? null;
            $callQBase = CallCenterInteraction::query();
            if ($acct) {
                $callQBase->where('account_number', $acct);
            } else {
                $callQBase->where('assignment_id', $assignment->id);
            }

            // Latest overall interaction (for display purposes)
            $latestOverall = (clone $callQBase)->orderBy('created_at', 'desc')->first();

            $callCount = 0;
            $calledInPeriod = false;
            if (! empty($assignment->accepted_at)) {
                try {
                    $startDate = Carbon::parse($assignment->accepted_at);
                    $callQSince = (clone $callQBase)->where('created_at', '>=', $startDate->format('Y-m-d H:i:s'));
                    $callCount = (clone $callQSince)->count();
                    $calledInPeriod = $callCount > 0;
                } catch (\Exception $e) {
                    $callCount = 0;
                    $calledInPeriod = false;
                }
            }

            $latestOutcome = $latestOverall->outcome ?? null;
            $latestPaymentExpected = $latestOverall && $latestOverall->payment_expected_at ? Carbon::parse($latestOverall->payment_expected_at)->toDateString() : null;
            $latestPaid = (bool) ($latestOverall->paid ?? false);

            $promiseOverdue = false;
            if ($latestOverall && in_array($latestOutcome, ['agreed to pay within 3 days', 'agreed to pay within 7 days'], true)) {
                if (! $latestPaid && $latestOverall->payment_expected_at) {
                    try {
                        $due = Carbon::parse($latestOverall->payment_expected_at)->endOfDay();
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
            if ($latestOutcome === 'not relevant person contacted') {
                $filterCounts['not_relevant_person']++;
            }
        }

        $grouped = $assignments->groupBy('assigned_user_id');

        $reportLabel = null;
        if ($reportId) {
            $r = \App\Models\CallCenterReport::find((int) $reportId);
            if ($r) {
                $dm = $r->dataset_month;
                $reportLabel = ($dm && strlen($dm) === 6) ? substr($dm, 0, 4) . '/' . substr($dm, 4, 2) . ' report' : ($r->dataset_month ?: 'Unknown report');
            }
        }

        // Determine report-level metadata using all assignments (accepted + pending + rejected),
        // not only the currently displayed accepted subset.
        $userReportIds = $allUserAssignments->pluck('call_center_report_id')->filter()->unique()->values();
        $latestReportCount = $latestReportId ? $allUserAssignments->where('call_center_report_id', $latestReportId)->count() : 0;
        // count only unaccepted (pending) rows from the latest report for the banner
        $latestReportPending = $latestReportId
            ? $allUserAssignments->where('call_center_report_id', $latestReportId)->where('accepted', false)->where('rejected', false)->count()
            : 0;
        $latestReportAllReassigned = false;
        if ($latestReportId && $latestReportPending > 0) {
            $latestPendingItems = $allUserAssignments->where('call_center_report_id', $latestReportId)
                ->where('accepted', false)
                ->where('rejected', false);
            if ($latestPendingItems->count() > 0) {
                $latestReportAllReassigned = $latestPendingItems->every(function ($a) {
                    return !empty($a->reassignment_origin_id);
                });
            }
        }
        $latestReportLabel = null;
        if ($latestReportId) {
            $lr = \App\Models\CallCenterReport::find((int) $latestReportId);
            if ($lr) {
                $dm = $lr->dataset_month;
                $latestReportLabel = ($dm && strlen($dm) === 6) ? substr($dm, 0, 4) . '/' . substr($dm, 4, 2) . ' report' : ($lr->dataset_month ?: 'Report #' . $lr->id);
            }
        }

        return view('callcenter.assignments.manage', [
            'assignments' => $assignments,
            'grouped' => $grouped,
            'reportId' => $reportId,
            'reportLabel' => $reportLabel,
            'assignmentMeta' => $assignmentMeta,
            'filterCounts' => $filterCounts,
            'userReportIds' => $userReportIds,
            'latestReportId' => $latestReportId,
            'latestReportCount' => $latestReportCount,
            'latestReportPending' => $latestReportPending,
            'latestReportLabel' => $latestReportLabel,
            'latestReportAllReassigned' => $latestReportAllReassigned,
            'currentUserId' => $currentUserId,
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
        $acceptedIds = [];
        $masterRowIds = [];
        foreach ($assignments as $assignment) {
            $assignment->update([
                'accepted' => true,
                'accepted_at' => now(),
                'status' => 'pending',
                'locked_at' => null,
                'locked_by' => null,
            ]);
            $acceptedIds[] = $assignment->id;
            if ($assignment->master_dataset_row_id) $masterRowIds[] = $assignment->master_dataset_row_id;
        }

        // If these accepted assignments correspond to master dataset rows that were
        // previously assigned from earlier reports, mark those older assignments
        // as completed so they no longer appear as active/pending rows.
        if (!empty($masterRowIds)) {
            $masterRowIds = array_values(array_unique($masterRowIds));
            $prevQuery = CallCenterAssignment::where('assigned_user_id', $userId)
                ->whereIn('master_dataset_row_id', $masterRowIds)
                ->where(function ($q) use ($reportId) {
                    if ($reportId) {
                        $q->where('call_center_report_id', '<>', (int) $reportId);
                    }
                })
                ->whereNotIn('id', $acceptedIds)
                ->where('status', '<>', 'completed');

            $prevQuery->update([
                'status' => 'completed',
                'locked_at' => null,
                'locked_by' => null,
            ]);
        }

        if ($request->ajax() || $request->wantsJson()) {
            $totalPending = CallCenterAssignment::where('assigned_user_id', $userId)
                ->where('accepted', true)
                ->where('status', 'pending')
                ->count();
            $latestReportAccepted = 0;
            if ($reportId) {
                $latestReportAccepted = CallCenterAssignment::where('assigned_user_id', $userId)
                    ->where('call_center_report_id', (int) $reportId)
                    ->where('accepted', true)
                    ->where('status', 'pending')
                    ->count();
            }
            return response()->json([
                'accepted' => $assignments->count(),
                'total_pending' => $totalPending,
                'latest_report_id' => $reportId,
                'latest_report_accepted' => $latestReportAccepted,
            ]);
        }

        // Non-AJAX redirect: use a neutral flash message to avoid exposing
        // implementation or host details in the UI toast.
        return Redirect::route('cc.assignments.manage', ['report' => $reportId])->with('status', 'Assignments updated.');
    }

    /**
     * Bulk reject all assignments for a specific user (optionally scoped to a report).
     */
    public function rejectAll(Request $request, $userId)
    {
        $reportId = $request->query('report');
        $query = CallCenterAssignment::where('assigned_user_id', $userId)->where('rejected', false);
        if ($reportId) $query->where('call_center_report_id', (int) $reportId);

        $requiresReasonRaw = $request->input('requires_reason', '1');
        $requiresReason = !in_array($requiresReasonRaw, ['0', 'false', 0, false], true);
        $payload = $request->validate([
            'requires_reason' => 'required|in:0,1',
            'rejection_note' => $requiresReason ? 'required|string|max:600' : 'nullable|string|max:600',
        ]);

        $assignments = $query->get();
        // If the UI indicated no reason is required, treat this as "discard reassigned copies" flow:
        if (isset($payload['requires_reason']) && (string)$payload['requires_reason'] === '0') {
            foreach ($assignments as $assignment) {
                // Only operate on reassigned copies that are NOT accepted
                if (! empty($assignment->reassignment_origin_id) && empty($assignment->accepted)) {
                    $this->reopenOriginalRejectedAssignment($assignment);
                }
            }
        } else {
            foreach ($assignments as $assignment) {
                if (! empty($assignment->reassignment_origin_id)) {
                    // Discard reassigned copies and restore originals; skip note
                    if (empty($assignment->accepted)) {
                        $this->reopenOriginalRejectedAssignment($assignment);
                    }
                    continue;
                }
                $assignment->update([
                    'rejected' => true,
                    'rejected_at' => now(),
                    'rejected_by' => Auth::id(),
                    'rejection_note' => $payload['rejection_note'] ?? 'Bulk rejected',
                    'status' => 'pending',
                    'locked_at' => null,
                    'locked_by' => null,
                ]);
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            $totalPending = CallCenterAssignment::where('assigned_user_id', $userId)
                ->where('accepted', true)
                ->where('status', 'pending')
                ->count();
            return response()->json([
                'rejected' => $assignments->count(),
                'total_pending' => $totalPending,
            ]);
        }

        // Non-AJAX redirect: neutral flash message
        return Redirect::route('cc.assignments.manage', ['report' => $reportId])->with('status', 'Assignments updated.');
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
        $includeCompleted = filter_var($request->query('include_completed', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = max(1, min((int) $request->query('per_page', 50), 200));
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $q = CallCenterAssignment::with('row')
            ->where('assigned_user_id', $userId);
        // When include_completed is requested we want to return either accepted
        // assignments (active) or historical completed assignments so previous
        // report rows remain visible. Otherwise only return accepted+pending rows.
        if ($includeCompleted) {
            $q->where(function ($qq) {
                $qq->where('accepted', true)->orWhere('status', 'completed');
            });
        } else {
            $q->where('accepted', true)->where('status', 'pending');
        }
        if ($reportId) $q->where('call_center_report_id', (int) $reportId);

        $total = (clone $q)->count();

        $rows = $q->orderBy('id')
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(function ($a) {
                $row = $a->row;
                // Count calls only after the assignment was accepted by the user.
                $acct = $row->account_num ?? $row->customer_ref ?? null;
                $callQBase = CallCenterInteraction::query();
                if ($acct) {
                    $callQBase->where('account_number', $acct);
                } else {
                    $callQBase->where('assignment_id', $a->id);
                }

                // latest overall for display purposes
                $latestOverall = (clone $callQBase)->orderBy('created_at', 'desc')->first();

                $callCount = 0;
                $calledInPeriod = false;
                if (! empty($a->accepted_at)) {
                    try {
                        $startDate = Carbon::parse($a->accepted_at);
                        $callQSince = (clone $callQBase)->where('created_at', '>=', $startDate->format('Y-m-d H:i:s'));
                        $callCount = (clone $callQSince)->count();
                        $calledInPeriod = $callCount > 0;
                    } catch (\Exception $e) {
                        $callCount = 0;
                        $calledInPeriod = false;
                    }
                }

                $latestOutcome = $latestOverall->outcome ?? null;
                $latestPaymentExpected = $latestOverall && $latestOverall->payment_expected_at ? Carbon::parse($latestOverall->payment_expected_at)->toDateString() : null;
                $latestPaid = (bool) ($latestOverall->paid ?? false);
                $promiseOverdue = false;
                if ($latestOverall && in_array($latestOutcome, ['agreed to pay within 3 days', 'agreed to pay within 7 days'], true)) {
                    if (! $latestPaid && $latestOverall->payment_expected_at) {
                        try {
                            $due = Carbon::parse($latestOverall->payment_expected_at)->endOfDay();
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
                    'report_id' => $a->call_center_report_id,
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

        $interactionsRaw = $q->orderBy('created_at', 'desc')->get();
        $interactions = $interactionsRaw->map(function ($i) {
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

        // Compute payment events separately: find interactions that recorded a payment
        $payments = [];
        try {
            // iterate in chronological order (oldest first) to build sensible 'last contact before payment'
            $chron = $interactionsRaw->sortBy('created_at');
            $paymentEvents = $chron->filter(function ($i) {
                return (! empty($i->paid) || ! empty($i->payment_date) || ! empty($i->paid_amount));
            });
            foreach ($paymentEvents as $p) {
                $paymentDate = $p->payment_date ?? $p->created_at;
                $lastBefore = $chron->filter(function ($x) use ($paymentDate, $p) {
                    // strictly before the payment event time and not the payment event itself
                    return ($x->created_at < $paymentDate) && ($x->id !== $p->id);
                })->sortByDesc('created_at')->first();

                $payments[] = [
                    'interaction_id' => $p->id,
                    'paid_amount' => $p->paid_amount ? number_format($p->paid_amount, 2) : null,
                    'payment_date' => $p->payment_date ? Carbon::parse($p->payment_date)->toDateString() : ($p->created_at ? Carbon::parse($p->created_at)->toDateString() : null),
                    'paid_by_agent' => $p->agent ? ($p->agent->name ?? $p->agent->username ?? null) : null,
                    'last_contact_before_payment' => $lastBefore && $lastBefore->agent ? ($lastBefore->agent->name ?? $lastBefore->agent->username ?? null) : null,
                    'last_contact_before_payment_at' => $lastBefore && $lastBefore->created_at ? Carbon::parse($lastBefore->created_at)->toDateTimeString() : null,
                ];
            }
        } catch (\Exception $e) {
            // ignore
        }

        // Compute call_count as interactions since the assignment was accepted.
        // Show all interactions in the modal, but only count those after
        // `accepted_at`. If not accepted yet, count is zero.
        $callCount = 0;
        try {
            $countQ = \App\Models\CallCenterInteraction::query();
            if ($acct) {
                $countQ->where('account_number', $acct);
            } else {
                $countQ->where('assignment_id', $assignment->id);
            }

            if (! empty($assignment->accepted_at)) {
                $startDate = Carbon::parse($assignment->accepted_at)->format('Y-m-d H:i:s');
                $countQ->where('created_at', '>=', $startDate);
                $callCount = $countQ->count();
            } else {
                $callCount = 0;
            }
        } catch (\Exception $e) {
            $callCount = 0;
        }

        return response()->json([
            'assignment_id' => $assignment->id,
            'row_id' => $assignment->master_dataset_row_id ?? $r->id ?? null,
            'report_id' => $assignment->call_center_report_id ?? null,
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
            'payments' => $payments,
            'call_count' => $callCount,
        ]);
    }

    public function claim($id)
    {
        $user = Auth::user();

        $updated = CallCenterAssignment::where('id', $id)
            ->where(function ($q) use ($user) {
                $q->where('status', 'pending')->orWhere(function ($q2) use ($user) {
                    $q2->where('assigned_user_id', $user->id)->where('status', 'claimed');
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
            // Do NOT auto-complete assignments when paid - they should remain visible in current report

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
            'status' => 'pending',
            'locked_at' => null,
            'locked_by' => null,
        ]);

        // mark any previous assignments for the same master row as completed
        if ($assignment->master_dataset_row_id) {
            CallCenterAssignment::where('assigned_user_id', $user->id)
                ->where('master_dataset_row_id', $assignment->master_dataset_row_id)
                ->where('id', '<>', $assignment->id)
                ->where('call_center_report_id', '<>', $assignment->call_center_report_id)
                ->where('status', '<>', 'completed')
                ->update([
                    'status' => 'completed',
                    'locked_at' => null,
                    'locked_by' => null,
                ]);
        }

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

        // If this is a reassigned copy and NOT accepted, immediately reopen the origin and delete this copy
        if (! empty($assignment->reassignment_origin_id)) {
            if (! empty($assignment->accepted)) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['error' => 'Cannot reject an accepted row.'], 409);
                }
                return Redirect::route('cc.assignments.manage')->withErrors(['reject' => 'Cannot reject an accepted row.']);
            }

            $this->reopenOriginalRejectedAssignment($assignment);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['rejected' => true, 'reassigned_copy_deleted' => true]);
            }
            return Redirect::route('cc.assignments.manage')->with('status', 'Reassigned row discarded; original reopened.');
        }

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

        $assignment = $this->reopenOriginalRejectedAssignment($assignment);

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

        $assignmentIds = array_map('intval', array_filter($assignmentIds, fn($id) => is_numeric($id) && $id > 0));
        $userIds = array_map('intval', array_filter($userIds, fn($id) => is_numeric($id) && $id > 0));

        if ($action === 'pool') {
            $userIds = [];
        }

        if (empty($assignmentIds)) {
            return Redirect::route('cc.reports', ['report' => $reportId])->withErrors(['reassign' => 'Select at least one rejected assignment to reassign.']);
        }

        Bus::dispatchSync(new ReassignCallCenterRows($assignmentIds, $userIds));

        return Redirect::route('cc.reports', ['report' => $reportId])->with('status', 'Reassignment queued.');
    }

    private function reopenOriginalRejectedAssignment(CallCenterAssignment $assignment): CallCenterAssignment
    {
        if (empty($assignment->reassignment_origin_id)) {
            return $assignment;
        }

        $candidate = CallCenterAssignment::find($assignment->reassignment_origin_id);

        if (! $candidate || ! $candidate->rejected || $candidate->status !== 'completed') {
            return $assignment;
        }

        DB::transaction(function () use ($candidate, $assignment) {
            // Reopen the original assignment as pending but do NOT clear its rejection metadata.
            // We only update status/accept/lock fields so analytics about who rejected remain intact.
            $candidate->update([
                'status' => 'pending',
                'accepted' => false,
                'accepted_at' => null,
                'locked_at' => null,
                'locked_by' => null,
                'reassignment_origin_id' => null,
            ]);

            // Only delete the reassigned copy if it is not accepted
            if (empty($assignment->accepted)) {
                $assignment->delete();
            }
        });

        return $candidate->refresh();
    }

    // Admin helper to dispatch distribution job
    public function distribute(Request $request, $reportId)
    {
        $userIds = $request->input('user_ids', []);
        $perUser = $request->input('per_user', null);
        $user = Auth::user();
        // sanitize user ids
        $userIds = array_values(array_filter($userIds, fn($id) => is_numeric($id) && (int)$id > 0));

        // Mark selected users as fixed in the users table
        if (!empty($userIds)) {
            try {
                \App\Models\User::whereIn('id', $userIds)->update(['fixed' => 1]);
            } catch (\Exception $e) {
                // don't block distribution if this fails; log if needed
            }
        }

        // Finalize past assignments for selected agents without deleting.
        // Only target assignments from reports other than the current one
        // and not yet accepted or rejected. Mark them completed so they
        // no longer appear as active, but preserve their historical status.
        if (!empty($userIds)) {
            DB::transaction(function () use ($userIds, $reportId) {
                foreach ($userIds as $uid) {
                    $priorIds = CallCenterAssignment::where('assigned_user_id', (int) $uid)
                        ->where('call_center_report_id', '<>', (int) $reportId)
                        ->where('accepted', false)
                        ->where('rejected', false)
                        ->pluck('id')
                        ->all();

                    if (empty($priorIds)) {
                        continue;
                    }

                    CallCenterAssignment::whereIn('id', $priorIds)
                        ->update(['status' => 'completed', 'locked_at' => null, 'locked_by' => null]);
                }
            });
        }

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

        $interactionExists = CallCenterInteraction::whereIn('assignment_id', $assignmentIds)->exists();
        if ($interactionExists) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['recalled' => false, 'error' => 'Cannot undo once interactions have been logged for this report.'], 409);
            }

            return Redirect::route('cc.reports', ['report' => $reportId])->withErrors(['recall' => 'Cannot undo once interactions have been logged for this report.']);
        }

        DB::transaction(function () use ($assignmentIds) {
            CallCenterInteraction::whereIn('assignment_id', $assignmentIds)->delete();
            CallCenterAssignment::whereIn('id', $assignmentIds)->delete();
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['recalled' => true]);
        }

        return Redirect::route('cc.reports', ['report' => $reportId])->with('status', 'Assignments deleted.');
    }

    public function recallPreview(Request $request, $reportId)
    {
        $assignments = CallCenterAssignment::with(['row', 'agent'])
            ->where('call_center_report_id', $reportId)
            ->whereNotNull('assigned_user_id')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $count = CallCenterAssignment::where('call_center_report_id', $reportId)
            ->whereNotNull('assigned_user_id')
            ->count();

        $sample = $assignments->map(function ($a) {
            return [
                'assignment_id' => $a->id,
                'row_id' => $a->master_dataset_row_id,
                'agent' => $a->agent ? ($a->agent->name ?? $a->agent->username ?? null) : null,
                'status' => $a->status,
                'accepted' => (bool) $a->accepted,
                'rejected' => (bool) $a->rejected,
            ];
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['count' => $count, 'sample' => $sample]);
        }

        return view('callcenter.reports.recall_preview', [
            'count' => $count,
            'sample' => $sample,
        ]);
    }
}
