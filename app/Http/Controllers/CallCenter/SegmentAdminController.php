<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\CallCenter\Concerns\InteractsWithSharedSegmentAdmins;
use App\Http\Controllers\Controller;
use App\Models\CallCenterAssignment;
use App\Models\CallCenterReport;
use App\Models\User;
use Illuminate\Http\Request;

class SegmentAdminController extends Controller
{
    use InteractsWithSharedSegmentAdmins;
    /**
     * Maps assignment slug → dataset assigned_to label.
     * These match MasterDatasetAssignmentService constants.
     */
    private const SEGMENT_BUCKET_MAP = [
        'segment_ccs' => 'call center staff',
        'segment_cc'  => 'call center',
        'segment_s'   => 'staff',
    ];

    private const SEGMENT_LABEL_MAP = [
        'segment_ccs' => 'Call Center Staff',
        'segment_cc'  => 'Call Center',
        'segment_s'   => 'Staff',
    ];

    // -------------------------------------------------------------------------
    // Guards
    // -------------------------------------------------------------------------

    protected function ensureSegmentAdmin(): string
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }

        $assignment = (string) ($sessionUser['assignment'] ?? '');
        if (! str_starts_with($assignment, 'segment_')) {
            abort(403);
        }

        return $assignment;
    }

    protected function segmentBucketLabel(string $assignment): string
    {
        return self::SEGMENT_BUCKET_MAP[$assignment] ?? '';
    }

    protected function segmentLabel(string $assignment): string
    {
        return self::SEGMENT_LABEL_MAP[$assignment] ?? $assignment;
    }

    protected function callerAssignment(string $segmentAssignment): string
    {
        // segment_ccs → caller_ccs, segment_cc → caller_cc, segment_s → caller_s
        return 'caller_' . substr($segmentAssignment, strlen('segment_'));
    }

    // -------------------------------------------------------------------------
    // Dashboard
    // -------------------------------------------------------------------------

    public function dashboard()
    {
        $assignment  = $this->ensureSegmentAdmin();
        $bucketLabel = $this->segmentBucketLabel($assignment);
        $segmentLabel = $this->segmentLabel($assignment);
        $segmentAdminId = (int) (session('user')['id'] ?? 0);

        $latestReport = CallCenterReport::callCenter()
            ->latest('created_at')
            ->first();

        $latestTotal    = 0;
        $latestAssigned = 0;
        $latestUnassigned = 0;
        $latestPaidCount  = 0;
        $latestPaidAmount = 0;
        $latestCallerBreakdown = collect();

        $allTimeTotal    = 0;
        $allTimeAssigned = 0;
        $allTimeUnassigned = 0;
        $allTimePaidCount  = 0;
        $allTimePaidAmount = 0;
        $allTimeCallerBreakdown = collect();

        if ($latestReport && $bucketLabel !== '') {
            $latestBase = CallCenterAssignment::callCenter()
                ->with(['row', 'agent'])
                ->where('call_center_report_id', $latestReport->id)
                ->whereHas('row', fn($q) => $q->whereRaw('LOWER(TRIM(assigned_to)) = ?', [$bucketLabel]));

            $latestAssignments = $latestBase->get();
            $latestTotal       = $latestAssignments->count();
            $latestAssigned    = $latestAssignments->whereNotNull('assigned_user_id')->count();
            $latestUnassigned  = $latestTotal - $latestAssigned;
            $latestPaidCount   = $latestAssignments->where('paid', true)->count();
            $latestPaidAmount  = $latestAssignments->sum(fn($a) => $a->paid_amount ?? 0);

            $latestCallerBreakdown = $latestAssignments
                ->whereNotNull('assigned_user_id')
                ->groupBy('assigned_user_id')
                ->map(function ($group) {
                    $agent = $group->first()->agent;
                    return [
                        'agent'      => $agent ? ($agent->name ?? $agent->username) : 'Unknown',
                        'total'      => $group->count(),
                        'assigned'   => $group->count(),
                        'paid'       => $group->where('paid', true)->count(),
                        'paid_amount' => $group->sum(fn($x) => $x->paid_amount ?? 0),
                    ];
                })->values();
        }

        if ($bucketLabel !== '') {
            $allTimeBase = CallCenterAssignment::callCenter()
                ->with(['row', 'agent'])
                ->whereHas('row', fn($q) => $q->whereRaw('LOWER(TRIM(assigned_to)) = ?', [$bucketLabel]));

            $allTimeAssignments  = $allTimeBase->get();
            $allTimeTotal        = $allTimeAssignments->count();
            $allTimeAssigned     = $allTimeAssignments->whereNotNull('assigned_user_id')->count();
            $allTimeUnassigned   = $allTimeTotal - $allTimeAssigned;
            $allTimePaidCount    = $allTimeAssignments->where('paid', true)->count();
            $allTimePaidAmount   = $allTimeAssignments->sum(fn($a) => $a->paid_amount ?? 0);

            $allTimeCallerBreakdown = $allTimeAssignments
                ->whereNotNull('assigned_user_id')
                ->groupBy('assigned_user_id')
                ->map(function ($group) {
                    $agent = $group->first()->agent;
                    return [
                        'agent'      => $agent ? ($agent->name ?? $agent->username) : 'Unknown',
                        'total'      => $group->count(),
                        'assigned'   => $group->count(),
                        'paid'       => $group->where('paid', true)->count(),
                        'paid_amount' => $group->sum(fn($x) => $x->paid_amount ?? 0),
                    ];
                })->values();
        }

        return view('cc.segment.dashboard', compact(
            'assignment',
            'segmentLabel',
            'latestReport',
            'latestTotal', 'latestAssigned', 'latestUnassigned',
            'latestPaidCount', 'latestPaidAmount', 'latestCallerBreakdown',
            'allTimeTotal', 'allTimeAssigned', 'allTimeUnassigned',
            'allTimePaidCount', 'allTimePaidAmount', 'allTimeCallerBreakdown'
        ));
    }

    // -------------------------------------------------------------------------
    // Caller management
    // -------------------------------------------------------------------------

    public function callers(Request $request)
    {
        $assignment      = $this->ensureSegmentAdmin();
        $segmentLabel    = $this->segmentLabel($assignment);
        $callerAssign    = $this->callerAssignment($assignment);

        $q            = trim((string) $request->query('q', ''));
        $statusFilter = $request->query('status', 'all');

        $sharedIds = $this->getSharedSegmentAdminIds();
        $query = User::where('system', 'cc')
            ->where('assignment', $callerAssign)
            ->whereIn('supervisor', $sharedIds)
            ->withCount(['interactionsAsAgent', 'rowAssignments']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if ($statusFilter === 'active') {
            $query->where('status', 1);
        } elseif ($statusFilter === 'disabled') {
            $query->where('status', 0);
        }

        $callers = $query->orderBy('username')->get();

        return view('cc.segment.callers', compact('assignment', 'segmentLabel', 'callers', 'q', 'statusFilter'));
    }

    public function createCallerForm()
    {
        $assignment   = $this->ensureSegmentAdmin();
        $segmentLabel = $this->segmentLabel($assignment);

        return view('cc.segment.create_caller', compact('assignment', 'segmentLabel'));
    }

    public function storeCaller(Request $request)
    {
        $assignment     = $this->ensureSegmentAdmin();
        $segmentAdminId = session('user')['id'] ?? null;
        $callerAssign   = $this->callerAssignment($assignment);

        $request->validate([
            'username' => 'required|digits:6|unique:users,username',
            'name'     => 'nullable|string|max:45',
        ]);

        User::create([
            'username'   => $request->input('username'),
            'admin_prev' => 0,
            'system'     => 'cc',
            'created_at' => now(),
            'fixed'      => 0,
            'status'     => 1,
            'name'       => $request->input('name'),
            'assignment' => $callerAssign,
            'supervisor' => $segmentAdminId,
        ]);

        return redirect()->route('cc.segment.callers')
            ->with('status', 'Caller created.');
    }

    public function editCaller(User $user)
    {
        $assignment     = $this->ensureSegmentAdmin();
        $segmentLabel   = $this->segmentLabel($assignment);
        $callerAssign   = $this->callerAssignment($assignment);

        if ($user->assignment !== $callerAssign || !in_array((int) $user->supervisor, $this->getSharedSegmentAdminIds(), true)) {
            abort(404);
        }

        return view('cc.segment.edit_caller', compact('user', 'assignment', 'segmentLabel'));
    }

    public function updateCaller(Request $request, User $user)
    {
        $assignment     = $this->ensureSegmentAdmin();
        $callerAssign   = $this->callerAssignment($assignment);

        if ($user->assignment !== $callerAssign || !in_array((int) $user->supervisor, $this->getSharedSegmentAdminIds(), true)) {
            abort(404);
        }

        $request->validate(['name' => 'nullable|string|max:45']);
        $user->name = $request->input('name');
        $user->save();

        return redirect()->route('cc.segment.callers')
            ->with('status', 'Caller updated.');
    }

    public function destroyCaller(User $user)
    {
        $assignment     = $this->ensureSegmentAdmin();
        $callerAssign   = $this->callerAssignment($assignment);

        if ($user->assignment !== $callerAssign || !in_array((int) $user->supervisor, $this->getSharedSegmentAdminIds(), true)) {
            abort(404);
        }

        if ($user->fixed) {
            return redirect()->route('cc.segment.callers')
                ->withErrors(['delete' => 'This user is fixed and cannot be deleted.']);
        }

        if ($user->interactionsAsAgent()->exists() || $user->rowAssignments()->exists()) {
            return redirect()->route('cc.segment.callers')
                ->withErrors(['delete' => 'This user has related records and cannot be deleted. Disable instead.']);
        }

        $user->delete();

        return redirect()->route('cc.segment.callers')
            ->with('status', 'Caller deleted.');
    }

    public function enableCaller(User $user)
    {
        $assignment     = $this->ensureSegmentAdmin();
        $callerAssign   = $this->callerAssignment($assignment);

        if ($user->assignment !== $callerAssign || !in_array((int) $user->supervisor, $this->getSharedSegmentAdminIds(), true)) {
            abort(404);
        }

        $user->status = 1;
        $user->save();

        return redirect()->route('cc.segment.callers')
            ->with('status', 'Caller enabled.');
    }

    public function disableCaller(User $user)
    {
        $assignment     = $this->ensureSegmentAdmin();
        $callerAssign   = $this->callerAssignment($assignment);

        if ($user->assignment !== $callerAssign || !in_array((int) $user->supervisor, $this->getSharedSegmentAdminIds(), true)) {
            abort(404);
        }

        $user->status = 0;
        $user->save();

        return redirect()->route('cc.segment.callers')
            ->with('status', 'Caller disabled.');
    }
}
