<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Jobs\ReassignCallCenterRows;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterInteraction;
use App\Models\CallCenterReport;
use App\Models\MasterDatasetRow;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
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

    public function index(Request $request)
    {
        $sessionUser = $request->session()->get('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            return Redirect::route('login');
        }

        $currentUserId = $sessionUser['id'] ?? null;
        if (! $currentUserId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $perPage = (int) $request->get('per_page', 25);

        $q = CallCenterAssignment::regionalBilling()
            ->where('assigned_user_id', $currentUserId)
            ->where('status', '<>', 'completed')
            ->with('row', 'interactions')
            ->orderBy('id', 'asc');

        return response()->json($q->paginate($perPage));
    }

    public function manage(Request $request)
    {
        $sessionUser = $request->session()->get('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb') {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            return Redirect::route('login');
        }

        if (($sessionUser['assignment'] ?? null) === 'super') {
            abort(403);
        }

        $reportId = $request->query('report');
        $currentUserId = $sessionUser['id'] ?? null;

        $latestReportId = null;
        $latestAcceptedReportId = null;
        $allUserAssignments = collect();

        if ($currentUserId) {
            $allUserAssignments = CallCenterAssignment::regionalBilling()
                ->where('assigned_user_id', $currentUserId)
                ->whereNotNull('call_center_report_id')
                ->get(['id', 'call_center_report_id', 'accepted', 'rejected', 'reassignment_origin_id']);

            $latestReportId = $allUserAssignments->max('call_center_report_id');
            $latestAcceptedReportId = $allUserAssignments->where('accepted', true)->max('call_center_report_id');
        }

        if (! $reportId) {
            $reportId = $latestAcceptedReportId ?: $latestReportId;
        }

        $q = CallCenterAssignment::regionalBilling()
            ->with(['row', 'agent', 'report'])
            ->whereNotNull('assigned_user_id');

        if ($currentUserId) {
            $q->where('assigned_user_id', $currentUserId);
        }

        if ($reportId) {
            $q->where('call_center_report_id', (int) $reportId);
        }

        $q->where('accepted', true);

        $assignments = $q->orderBy('assigned_user_id')->orderBy('id')->get();

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
            $row = $assignment->row;
            $acct = $row->account_num ?? $row->customer_ref ?? null;
            $callQBase = CallCenterInteraction::query();
            if ($acct) {
                $callQBase->where('account_number', $acct);
            } else {
                $callQBase->where('assignment_id', $assignment->id);
            }

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
                        // ignore
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
            $r = CallCenterReport::regionalBilling()->find((int) $reportId);
            if ($r) {
                $dm = $r->dataset_month;
                $reportLabel = ($dm && strlen($dm) === 6) ? substr($dm, 0, 4) . '/' . substr($dm, 4, 2) . ' report' : ($r->dataset_month ?: 'Unknown report');
            }
        }

        $userReportIds = $allUserAssignments->pluck('call_center_report_id')->filter()->unique()->values();
        $latestReportCount = $latestReportId ? $allUserAssignments->where('call_center_report_id', $latestReportId)->count() : 0;
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
            $lr = CallCenterReport::regionalBilling()->find((int) $latestReportId);
            if ($lr) {
                $dm = $lr->dataset_month;
                $latestReportLabel = ($dm && strlen($dm) === 6) ? substr($dm, 0, 4) . '/' . substr($dm, 4, 2) . ' report' : ($lr->dataset_month ?: 'Report #' . $lr->id);
            }
        }

        return view('regionalbilling.assignments.manage', [
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

    public function acceptAll(Request $request, $userId)
    {
        $reportId = $request->query('report');
        $query = CallCenterAssignment::regionalBilling()->where('assigned_user_id', $userId)->where('accepted', false);
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

        if (!empty($masterRowIds)) {
            $masterRowIds = array_values(array_unique($masterRowIds));
            $prevQuery = CallCenterAssignment::regionalBilling()->where('assigned_user_id', $userId)
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
            $totalPending = CallCenterAssignment::regionalBilling()->where('assigned_user_id', $userId)
                ->where('accepted', true)
                ->where('status', 'pending')
                ->count();
            $latestReportAccepted = 0;
            if ($reportId) {
                $latestReportAccepted = CallCenterAssignment::regionalBilling()->where('assigned_user_id', $userId)
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

        return Redirect::route('rb.assignments.manage', ['report' => $reportId])->with('status', 'Assignments updated.');
    }

    public function rejectAll(Request $request, $userId)
    {
        $reportId = $request->query('report');
        $query = CallCenterAssignment::regionalBilling()->where('assigned_user_id', $userId)->where('rejected', false);
        if ($reportId) $query->where('call_center_report_id', (int) $reportId);

        $requiresReasonRaw = $request->input('requires_reason', '1');
        $requiresReason = !in_array($requiresReasonRaw, ['0', 'false', 0, false], true);
        $payload = $request->validate([
            'requires_reason' => 'required|in:0,1',
            'rejection_note' => $requiresReason ? 'required|string|max:600' : 'nullable|string|max:600',
        ]);

        $assignments = $query->get();
        $rejectedBy = $request->session()->get('user.id');

        if (isset($payload['requires_reason']) && (string)$payload['requires_reason'] === '0') {
            foreach ($assignments as $assignment) {
                if (! empty($assignment->reassignment_origin_id) && empty($assignment->accepted)) {
                    $this->reopenOriginalRejectedAssignment($assignment);
                }
            }
        } else {
            foreach ($assignments as $assignment) {
                if (! empty($assignment->reassignment_origin_id)) {
                    if (empty($assignment->accepted)) {
                        $this->reopenOriginalRejectedAssignment($assignment);
                    }
                    continue;
                }
                $assignment->update([
                    'rejected' => true,
                    'rejected_at' => now(),
                    'rejected_by' => $rejectedBy,
                    'rejection_note' => $payload['rejection_note'] ?? 'Bulk rejected',
                    'status' => 'pending',
                    'locked_at' => null,
                    'locked_by' => null,
                ]);
            }
        }

        if ($request->ajax() || $request->wantsJson()) {
            $totalPending = CallCenterAssignment::regionalBilling()->where('assigned_user_id', $userId)
                ->where('accepted', true)
                ->where('status', 'pending')
                ->count();
            return response()->json([
                'rejected' => $assignments->count(),
                'total_pending' => $totalPending,
            ]);
        }

        return Redirect::route('rb.assignments.manage', ['report' => $reportId])->with('status', 'Assignments updated.');
    }

    public function userRows(Request $request, $userId)
    {
        $currentUserId = $request->session()->get('user.id');
        if ($currentUserId && (int) $userId !== (int) $currentUserId) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $reportId = $request->query('report');
        $includeCompleted = filter_var($request->query('include_completed', false), FILTER_VALIDATE_BOOLEAN);
        $perPage = max(1, min((int) $request->query('per_page', 50), 200));
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * $perPage;

        $q = CallCenterAssignment::regionalBilling()->with('row')
            ->where('assigned_user_id', $userId);
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
                $acct = $row->account_num ?? $row->customer_ref ?? null;
                $callQBase = CallCenterInteraction::query();
                if ($acct) {
                    $callQBase->where('account_number', $acct);
                } else {
                    $callQBase->where('assignment_id', $a->id);
                }

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
                $promiseOverdue = false;
                if ($latestOverall && in_array($latestOutcome, ['agreed to pay within 3 days', 'agreed to pay within 7 days'], true)) {
                    if (! empty($latestOverall->payment_expected_at)) {
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

    public function assignmentDetails(Request $request, $assignmentId)
    {
        $assignment = CallCenterAssignment::regionalBilling()->with('row')->findOrFail($assignmentId);
        $r = $assignment->row;
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

        $payments = [];
        try {
            $chron = $interactionsRaw->sortBy('created_at');
            $paymentEvents = $chron->filter(function ($i) {
                return (! empty($i->paid) || ! empty($i->payment_date) || ! empty($i->paid_amount));
            });
            foreach ($paymentEvents as $p) {
                $paymentDate = $p->payment_date ?? $p->created_at;
                $lastBefore = $chron->filter(function ($x) use ($paymentDate, $p) {
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

        $callCount = 0;
        try {
            $countQ = CallCenterInteraction::query();
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
        $userId = session('user.id');
        if (! $userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $updated = CallCenterAssignment::regionalBilling()->where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('status', 'pending')->orWhere(function ($q2) use ($userId) {
                    $q2->where('assigned_user_id', $userId)->where('status', 'claimed');
                });
            })
            ->update([
                'status' => 'claimed',
                'locked_at' => now(),
                'locked_by' => $userId,
                'assigned_user_id' => $userId,
            ]);

        return response()->json(['updated' => $updated]);
    }

    public function complete($id)
    {
        $userId = session('user.id');
        if (! $userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $updated = CallCenterAssignment::regionalBilling()->where('id', $id)
            ->where('assigned_user_id', $userId)
            ->update(['status' => 'completed', 'locked_at' => null, 'locked_by' => null]);

        return response()->json(['updated' => $updated]);
    }

    public function storeInteraction(Request $request, $assignmentId)
    {
        $agentId = session('user.id');
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

        $assignment = CallCenterAssignment::regionalBilling()->with('row')->find($assignmentId);
        $accountNumber = null;
        if ($assignment && $assignment->row) {
            $accountNumber = $assignment->row->account_num ?? $assignment->row->customer_ref ?? null;
        }

        $interaction = CallCenterInteraction::create(array_merge($payload, [
            'assignment_id' => $assignmentId,
            'agent_id' => $agentId,
            'account_number' => $accountNumber,
        ]));

        DB::transaction(function () use ($interaction, $payload) {
            $assignment = CallCenterAssignment::regionalBilling()->find($interaction->assignment_id);
            if (! $assignment) return;

            $assignment->locked_at = null;
            $assignment->locked_by = null;

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
        $userId = session('user.id');
        if (! $userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $assignment = CallCenterAssignment::regionalBilling()->where('id', $id)
            ->where('assigned_user_id', $userId)
            ->where('rejected', false)
            ->firstOrFail();

        $assignment->update([
            'accepted' => true,
            'accepted_at' => now(),
            'status' => 'pending',
            'locked_at' => null,
            'locked_by' => null,
        ]);

        if ($assignment->master_dataset_row_id) {
            CallCenterAssignment::regionalBilling()->where('assigned_user_id', $userId)
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

        return Redirect::route('rb.assignments.manage')->with('status', 'Assignment accepted.');
    }

    public function reject(Request $request, $id)
    {
        $userId = session('user.id');
        if (! $userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $assignment = CallCenterAssignment::regionalBilling()->where('id', $id)
            ->where('assigned_user_id', $userId)
            ->firstOrFail();

        if (! empty($assignment->reassignment_origin_id)) {
            if (! empty($assignment->accepted)) {
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json(['error' => 'Cannot reject an accepted row.'], 409);
                }
                return Redirect::route('rb.assignments.manage')->withErrors(['reject' => 'Cannot reject an accepted row.']);
            }

            $this->reopenOriginalRejectedAssignment($assignment);
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['rejected' => true, 'reassigned_copy_deleted' => true]);
            }
            return Redirect::route('rb.assignments.manage')->with('status', 'Reassigned row discarded; original reopened.');
        }

        $payload = $request->validate([
            'note' => 'nullable|string',
        ]);

        $assignment->update([
            'rejected' => true,
            'rejected_at' => now(),
            'rejected_by' => $userId,
            'rejection_note' => $payload['note'] ?? null,
            'status' => 'pending',
            'locked_at' => null,
            'locked_by' => null,
        ]);

        $assignment = $this->reopenOriginalRejectedAssignment($assignment);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['rejected' => true]);
        }

        return Redirect::route('rb.assignments.manage')->with('status', 'Assignment rejected.');
    }

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

        $userCounts = $request->input('user_counts', []);
        $manualCounts = [];
        foreach ((array) $userCounts as $uid => $count) {
            $uid = (int) $uid;
            $count = max(0, (int) $count);
            if ($count > 0 && in_array($uid, $inputUserIds, true)) {
                $manualCounts[$uid] = $count;
            }
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

        $selectedCallerIds = $callerIds;
        if (empty($selectedCallerIds)) {
            return Redirect::route('rb.reports.summary', ['report' => $report->id])
                ->withErrors(['distribute' => 'No valid callers selected for this RTOM admin.']);
        }

        $fixedCallerIds = array_keys($manualCounts);
        $freeCallerIds = array_values(array_diff($selectedCallerIds, $fixedCallerIds));

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

        $total = count($rowsToDistribute);
        $fixedTotal = array_sum($manualCounts);
        if ($fixedTotal > $total) {
            return Redirect::route('rb.reports.summary', ['report' => $report->id])
                ->withErrors(['distribute' => 'Manual assignment total (' . $fixedTotal . ') exceeds available rows (' . $total . ').']);
        }

        if (! empty($manualCounts) && empty($freeCallerIds) && $fixedTotal < $total) {
            return Redirect::route('rb.reports.summary', ['report' => $report->id])
                ->withErrors(['distribute' => 'Manual assignment totals must cover all distributable rows or leave at least one caller without a fixed quota.']);
        }

        $finalCounts = [];
        foreach ($selectedCallerIds as $callerId) {
            $finalCounts[$callerId] = $manualCounts[$callerId] ?? 0;
        }

        if (! empty($freeCallerIds)) {
            $remaining = $total - $fixedTotal;
            $freeCount = count($freeCallerIds);
            $base = intdiv($remaining, $freeCount);
            $remainder = $remaining % $freeCount;

            foreach ($freeCallerIds as $index => $callerId) {
                $finalCounts[$callerId] += $base + ($index < $remainder ? 1 : 0);
            }
        }

        DB::transaction(function () use ($report, $rowsToDistribute, $finalCounts) {
            $now = now();
            $inserts = [];
            $pos = 0;

            foreach ($finalCounts as $callerId => $count) {
                if ($count <= 0) {
                    continue;
                }
                for ($i = 0; $i < $count && $pos < count($rowsToDistribute); $i++, $pos++) {
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

    public function cancelDistribute(Request $request, $reportId, $token)
    {
        return Redirect::route('rb.reports', ['report' => $reportId])
            ->withErrors(['distribute' => 'Cancel distribution is not available.']);
    }

    public function recall(Request $request, $reportId)
    {
        $acceptedExists = CallCenterAssignment::regionalBilling()
            ->where('call_center_report_id', $reportId)
            ->where('accepted', true)
            ->exists();
        if ($acceptedExists) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['recalled' => false, 'error' => 'Cannot recall: some assignments have been accepted.'], 409);
            }

            return Redirect::route('rb.reports', ['report' => $reportId])->withErrors(['recall' => 'Cannot recall: some assignments have been accepted.']);
        }

        $assignmentIds = CallCenterAssignment::regionalBilling()
            ->where('call_center_report_id', $reportId)
            ->pluck('id')
            ->toArray();
        if (empty($assignmentIds)) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['recalled' => false, 'error' => 'No assignments found for this report.'], 404);
            }

            return Redirect::route('rb.reports', ['report' => $reportId])->with('status', 'No assignments to recall.');
        }

        $interactionExists = CallCenterInteraction::whereIn('assignment_id', $assignmentIds)->exists();
        if ($interactionExists) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['recalled' => false, 'error' => 'Cannot undo once interactions have been logged for this report.'], 409);
            }

            return Redirect::route('rb.reports', ['report' => $reportId])->withErrors(['recall' => 'Cannot undo once interactions have been logged for this report.']);
        }

        DB::transaction(function () use ($assignmentIds) {
            CallCenterInteraction::whereIn('assignment_id', $assignmentIds)->delete();
            CallCenterAssignment::regionalBilling()->whereIn('id', $assignmentIds)->delete();
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(['recalled' => true]);
        }

        return Redirect::route('rb.reports', ['report' => $reportId])->with('status', 'Assignments deleted.');
    }

    public function recallPreview(Request $request, $reportId)
    {
        $assignments = CallCenterAssignment::regionalBilling()->with(['row', 'agent'])
            ->where('call_center_report_id', $reportId)
            ->whereNotNull('assigned_user_id')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $count = CallCenterAssignment::regionalBilling()->where('call_center_report_id', $reportId)
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
            return Redirect::route('rb.reports', ['report' => $reportId])->withErrors(['reassign' => 'Select at least one rejected assignment to reassign.']);
        }

        Bus::dispatchSync(new ReassignCallCenterRows($assignmentIds, $userIds));

        return Redirect::route('rb.reports', ['report' => $reportId])->with('status', 'Reassignment queued.');
    }

    private function reopenOriginalRejectedAssignment(CallCenterAssignment $assignment): CallCenterAssignment
    {
        if (empty($assignment->reassignment_origin_id)) {
            return $assignment;
        }

        $candidate = CallCenterAssignment::regionalBilling()->find($assignment->reassignment_origin_id);

        if (! $candidate || ! $candidate->rejected || $candidate->status !== 'completed') {
            return $assignment;
        }

        DB::transaction(function () use ($candidate, $assignment) {
            $candidate->update([
                'status' => 'pending',
                'accepted' => false,
                'accepted_at' => null,
                'locked_at' => null,
                'locked_by' => null,
                'reassignment_origin_id' => null,
            ]);

            if (empty($assignment->accepted)) {
                $assignment->delete();
            }
        });

        return $candidate->refresh();
    }
}
