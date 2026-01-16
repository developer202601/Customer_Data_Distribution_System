<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MasterDatasetRow;
use App\Models\CallCenterReport;
use Illuminate\Http\Request;
use App\Models\CallCenterAssignment;

class RegionAdminController extends Controller
{
    protected function ensureRegionAdmin()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }
        $assignment = $sessionUser['assignment'] ?? null;
        if (! $assignment || $assignment === 'super') {
            abort(403);
        }
        return $assignment; // region name
    }

    public function index()
    {
        $region = $this->ensureRegionAdmin();

        $lastTwo = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        $rtoms = [];
        if (! empty($lastTwo)) {
            $rtoms = MasterDatasetRow::whereIn('process_id', $lastTwo)
                ->where('region', $region)
                ->whereNotNull('rtom')
                ->distinct()
                ->pluck('rtom')
                ->values();
        }

        // rtom admins are users with assignment like 'rtom_<rtom>' and supervisor = current user's id
        $currentSupervisor = session('user')['id'] ?? null;

        $q = request()->query('q');
        $selectedRtom = request()->query('rtom');

        $query = User::where('system', 'cc')
            ->where('admin_prev', 1)
            ->where('assignment', 'like', 'rtom_%')
            ->where('supervisor', $currentSupervisor);

        if (! empty($q)) {
            $query->where(function($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedRtom)) {
            $assignmentValue = 'rtom_' . preg_replace('/\s+/', '_', strtolower($selectedRtom));
            $query->where('assignment', $assignmentValue);
        }

        $rtomAdmins = $query->get();

        return view('cc.region.index', compact('rtoms', 'rtomAdmins', 'region', 'q', 'selectedRtom'));
    }

    /**
     * AJAX search endpoint returning table rows for RTOM admins.
     */
    public function search(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $currentSupervisor = session('user')['id'] ?? null;

        $q = $request->query('q');
        $selectedRtom = $request->query('rtom');

        $query = User::where('system', 'cc')
            ->where('admin_prev', 1)
            ->where('assignment', 'like', 'rtom_%')
            ->where('supervisor', $currentSupervisor);

        if (! empty($q)) {
            $query->where(function($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedRtom)) {
            $assignmentValue = 'rtom_' . preg_replace('/\s+/', '_', strtolower($selectedRtom));
            $query->where('assignment', $assignmentValue);
        }

        $rtomAdmins = $query->get();

        return view('cc.region._rows', compact('rtomAdmins'));
    }

    public function createAdminForm(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $rtoms = $this->regionRtoms($region);

        return view('cc.region.create_admin', [
            'rtoms' => $rtoms,
            'isSupervisor' => false,
        ]);
    }

    /**
     * Show the create supervisor form (separate view with supervisor select and fixed=1).
     */
    public function createSupervisorForm(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $rtoms = $this->regionRtoms($region);

        return view('cc.region.create_supervisor', [
            'rtoms' => $rtoms,
        ]);
    }

    protected function regionRtoms($region)
    {
        $lastTwo = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        if (empty($lastTwo)) return collect();

        return MasterDatasetRow::whereIn('process_id', $lastTwo)
            ->where('region', $region)
            ->whereNotNull('rtom')
            ->distinct()
            ->pluck('rtom')
            ->values();
    }

    public function storeAdmin(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $request->validate([
            'username' => 'required|digits:6|unique:users,username',
            'rtom' => 'required_without:supervisor|string|max:255',
            'supervisor' => 'required_without:rtom|string|max:255',
            'name' => 'nullable|string|max:45',
        ]);

        // Accept value from either the RTOM field (create admin) or the supervisor field (create supervisor form)
        $rtom = $request->input('rtom') ?? $request->input('supervisor');

        // allow marking created users as "supervisors" via fixed=1 in the form (they are fixed and cannot be deleted)
        $isSupervisor = ! empty($request->input('fixed'));

        $user = User::create([
            'username' => $request->input('username'),
            'admin_prev' => 1,
            'system' => 'cc',
            'created_at' => now(),
            'fixed' => 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'rtom_' . preg_replace('/\s+/', '_', strtolower($rtom)),
            'supervisor' => session('user')['id'] ?? null,
        ]);

        return redirect()->route('cc.region.index')->with('status', 'RTOM admin created');
    }

    public function storeSupervisor(Request $request)
    {
        $region = $this->ensureRegionAdmin();

        $request->validate([
            'username' => 'required|digits:6|unique:users,username',
            'name' => 'nullable|string|max:45',
        ]);

        // If RTOM user, use their RTOM from session for assignment and supervisor fields
        if (\Illuminate\Support\Str::startsWith(session('user.assignment'), 'rtom_')) {
            $rtom = session('user.assignment');
            $supervisorId = session('user')['id'] ?? null;
        } else {
            $rtom = $request->input('rtom') ?? $request->input('supervisor');
            $supervisorId = session('user')['id'] ?? null;
        }

        $isSupervisor = ! empty($request->input('fixed'));

        $user = User::create([
            'username' => $request->input('username'),
            'admin_prev' => 1,
            'system' => 'cc',
            'created_at' => now(),
            'fixed' => 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'supervisor_' . preg_replace('/\s+/', '_', strtolower($rtom)),
            'supervisor' => $supervisorId,
        ]);

        // Redirect to supervisor list for RTOM users, else to RTOM list
        if (\Illuminate\Support\Str::startsWith(session('user.assignment'), 'rtom_')) {
            return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor created');
        } else {
            return redirect()->route('cc.region.index')->with('status', 'Supervisor created');
        }
    }

    public function editAdminForm(User $user)
    {
        $region = $this->ensureRegionAdmin();

        // only allow editing RTOM admins they supervise
        if (! $user->assignment || stripos($user->assignment, 'rtom_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $rtoms = $this->regionRtoms($region);
        return view('cc.region.edit_admin', compact('user', 'rtoms'));
    }

    public function updateAdmin(Request $request, User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'rtom_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:45',
            'rtom' => 'required|string|max:255',
        ]);

        $user->name = $request->input('name');
        $user->assignment = 'rtom_' . preg_replace('/\s+/', '_', strtolower($request->input('rtom')));
        $user->save();

        return redirect()->route('cc.region.index')->with('status', 'RTOM admin updated');
    }

    public function destroyAdmin(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'rtom_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        if ($user->fixed) {
            return redirect()->route('cc.region.index')->withErrors(['delete' => 'This user is fixed and cannot be deleted.']);
        }

        $user->delete();
        return redirect()->route('cc.region.index')->with('status', 'RTOM admin deleted');
    }

    // --- Supervisor management for RTOM users ---
    public function editSupervisorForm(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        return view('cc.region.edit_supervisor', compact('user'));
    }

    public function updateSupervisor(Request $request, User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:45',
        ]);

        $user->name = $request->input('name');
        $user->save();

        return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor updated');
    }

    public function disableSupervisor(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $user->status = 0;
        $user->save();

        return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor disabled');
    }

    public function enableSupervisor(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $user->status = 1;
        $user->save();

        return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor enabled');
    }

    public function destroySupervisor(User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'supervisor_') !== 0 || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        if ($user->fixed) {
            return redirect()->route('cc.region.assign.index')->withErrors(['delete' => 'This user is fixed and cannot be deleted.']);
        }

        $user->delete();
        return redirect()->route('cc.region.assign.index')->with('status', 'Supervisor deleted');
    }

    public function dashboard()
    {
        $region = $this->ensureRegionAdmin();

        // Get latest report data (most recent report with assignments)
        $latestReport = CallCenterReport::whereHas('assignments', function($q) use ($region) {
            $q->whereHas('row', function($rq) use ($region) {
                $rq->where('region', $region);
            });
        })->latest('created_at')->first();

        // Latest report data
        $latestBase = CallCenterAssignment::with(['row', 'agent', 'interactions'])
            ->where('call_center_report_id', $latestReport?->id)
            ->whereHas('row', function($q) use ($region) {
                $q->where('region', $region);
            });

        $latestAssignments = $latestBase->get();
        $latestTotal = $latestAssignments->count();
        $latestAssigned = $latestAssignments->whereNotNull('assigned_user_id')->count();
        $latestUnassigned = $latestTotal - $latestAssigned;
        $latestPaidCount = $latestAssignments->where('paid', true)->count();
        $latestPaidAmount = $latestAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        $latestRtomBreakdown = $latestAssignments->groupBy(fn($a) => $a->row->rtom ?? '—')->map(function($group, $rtom) use ($region, $latestReport) {
            $total = $group->count();
            $assigned = $group->whereNotNull('assigned_user_id')->count();
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            // Get supervisors for this RTOM
            $supervisorAssignment = 'supervisor_rtom_' . strtolower(str_replace(' ', '_', $rtom));
            $supervisors = User::where('assignment', $supervisorAssignment)->get();

            $supervisorProfits = [];
            foreach ($supervisors as $supervisor) {
                // Sum paid_amount from assignments of users supervised by this supervisor
                $profit = CallCenterAssignment::where('call_center_report_id', $latestReport?->id)
                    ->whereHas('row', function($q) use ($region, $rtom) {
                        $q->where('region', $region)->where('rtom', $rtom);
                    })->whereHas('agent', function($q) use ($supervisor) {
                        $q->where('supervisor', $supervisor->id);
                    })->sum('paid_amount');

                $supervisorProfits[] = [
                    'name' => $supervisor->name ?? $supervisor->username,
                    'profit' => $profit,
                ];
            }

            return [
                'rtom' => $rtom,
                'total' => $total,
                'assigned' => $assigned,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
                'supervisor_profits' => $supervisorProfits,
            ];
        })->values();

        // All-time data
        $allTimeBase = CallCenterAssignment::with(['row', 'agent', 'interactions'])
            ->whereHas('row', function($q) use ($region) {
                $q->where('region', $region);
            });

        $allTimeAssignments = $allTimeBase->get();
        $allTimeTotal = $allTimeAssignments->count();
        $allTimeAssigned = $allTimeAssignments->whereNotNull('assigned_user_id')->count();
        $allTimeUnassigned = $allTimeTotal - $allTimeAssigned;
        $allTimePaidCount = $allTimeAssignments->where('paid', true)->count();
        $allTimePaidAmount = $allTimeAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        $allTimeRtomBreakdown = $allTimeAssignments->groupBy(fn($a) => $a->row->rtom ?? '—')->map(function($group, $rtom) use ($region) {
            $total = $group->count();
            $assigned = $group->whereNotNull('assigned_user_id')->count();
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            // Get supervisors for this RTOM
            $supervisorAssignment = 'supervisor_rtom_' . strtolower(str_replace(' ', '_', $rtom));
            $supervisors = User::where('assignment', $supervisorAssignment)->get();

            $supervisorProfits = [];
            foreach ($supervisors as $supervisor) {
                // Sum paid_amount from assignments of users supervised by this supervisor
                $profit = CallCenterAssignment::whereHas('row', function($q) use ($region, $rtom) {
                    $q->where('region', $region)->where('rtom', $rtom);
                })->whereHas('agent', function($q) use ($supervisor) {
                    $q->where('supervisor', $supervisor->id);
                })->sum('paid_amount');

                $supervisorProfits[] = [
                    'name' => $supervisor->name ?? $supervisor->username,
                    'profit' => $profit,
                ];
            }

            return [
                'rtom' => $rtom,
                'total' => $total,
                'assigned' => $assigned,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
                'supervisor_profits' => $supervisorProfits,
            ];
        })->values();

        return view('cc.region.dashboard', compact(
            'region', 'latestReport',
            'latestTotal', 'latestAssigned', 'latestUnassigned', 'latestPaidCount', 'latestPaidAmount', 'latestRtomBreakdown',
            'allTimeTotal', 'allTimeAssigned', 'allTimeUnassigned', 'allTimePaidCount', 'allTimePaidAmount', 'allTimeRtomBreakdown'
        ));
    }

    public function indexAssign()
    {
        $region = $this->ensureRegionAdmin();

        // For RTOM users, show only supervisor users they created
        if (\Illuminate\Support\Str::startsWith(session('user.assignment'), 'rtom_')) {
            $users = User::where('system', 'cc')
                ->where('supervisor', session('user')['id'] ?? null)
                ->where('assignment', 'like', 'supervisor_%')
                ->orderBy('id')
                ->get();
        } else {
            // Show call-center users added by the logged-in supervisor, not super admins and not already RTOM admins
            $users = User::where('system', 'cc')
                ->where('supervisor', session('user')['id'] ?? null)
                ->where(function ($q) {
                    $q->whereNull('assignment')
                        ->orWhere(function ($sq) {
                            $sq->where('assignment', '<>', 'super')
                                ->where('assignment', 'not like', 'rtom_%');
                        });
                })
                ->orderBy('id')
                ->get();
        }

        return view('cc.region.assign_index', compact('users', 'region'));
    }

    public function showAssignForm(User $user)
    {
        $region = $this->ensureRegionAdmin();

        $rtoms = $this->regionRtoms($region);

        return view('cc.region.assign', compact('user', 'rtoms', 'region'));
    }

    public function storeAssignment(Request $request, User $user)
    {
        $region = $this->ensureRegionAdmin();

        $request->validate([
            'rtom' => 'required|string|max:255',
        ]);

        $rtom = $request->input('rtom');

        $allowedRtoms = $this->regionRtoms($region);
        if (! $allowedRtoms->contains($rtom)) {
            return back()->withErrors(['rtom' => 'Selected RTOM is not available in your region.']);
        }

        $user->admin_prev = 1;
        $user->assignment = 'rtom_' . preg_replace('/\s+/', '_', strtolower($rtom));
        $user->supervisor = session('user')['id'] ?? null;
        $user->save();

        return redirect()->route('cc.region.assign.index')->with('status', 'Assignment updated');
    }

    protected function ensureSupervisor()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }
        $assignment = $sessionUser['assignment'] ?? null;
        if (! $assignment || !str_starts_with($assignment, 'supervisor_')) {
            abort(403);
        }
        // Extract RTOM from assignment like 'supervisor_rtom_CODE'
        $rtom = str_replace('supervisor_rtom_', '', $assignment);
        $rtom = str_replace('_', ' ', $rtom);
        return $rtom; // RTOM name
    }

    public function supervisorDashboard()
    {
        $rtom = $this->ensureSupervisor();
        $supervisorId = session('user')['id'];

        // Get latest report data (most recent report with assignments for this RTOM)
        $latestReport = CallCenterReport::whereHas('assignments', function($q) use ($rtom) {
            $q->whereHas('row', function($rq) use ($rtom) {
                $rq->where('rtom', $rtom);
            });
        })->latest('created_at')->first();

        // Latest report data for this supervisor's RTOM
        $latestBase = CallCenterAssignment::with(['row', 'agent.supervisorUser', 'interactions'])
            ->where('call_center_report_id', $latestReport?->id)
            ->whereHas('row', function($q) use ($rtom) {
                $q->where('rtom', $rtom);
            });

        $latestAssignments = $latestBase->get();
        $latestTotal = $latestAssignments->count();
        $latestAssigned = $latestAssignments->whereNotNull('assigned_user_id')->count();
        $latestUnassigned = $latestTotal - $latestAssigned;
        $latestPaidCount = $latestAssignments->where('paid', true)->count();
        $latestPaidAmount = $latestAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        // For supervisor, breakdown by callers (agents in this RTOM)
        $latestCallerBreakdown = $latestAssignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function($group) {
            $agent = $group->first()->agent;
            $total = $group->count();
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            // Get supervisor info
            $supervisor = $agent ? $agent->supervisorUser : null;
            $supervisorLabel = $supervisor ? ($supervisor->id === session('user')['id'] ? 'Me' : ($supervisor->name ?? $supervisor->username)) : 'Unknown';

            return [
                'agent' => $this->formatAgentLabel($agent),
                'supervisor' => $supervisorLabel,
                'total' => $total,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
            ];
        })->values();

        // All-time data
        $allTimeBase = CallCenterAssignment::with(['row', 'agent.supervisorUser', 'interactions'])
            ->whereHas('row', function($q) use ($rtom) {
                $q->where('rtom', $rtom);
            });

        $allTimeAssignments = $allTimeBase->get();
        $allTimeTotal = $allTimeAssignments->count();
        $allTimeAssigned = $allTimeAssignments->whereNotNull('assigned_user_id')->count();
        $allTimeUnassigned = $allTimeTotal - $allTimeAssigned;
        $allTimePaidCount = $allTimeAssignments->where('paid', true)->count();
        $allTimePaidAmount = $allTimeAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        $allTimeCallerBreakdown = $allTimeAssignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function($group) {
            $agent = $group->first()->agent;
            $total = $group->count();
            $paid = $group->where('paid', true)->count();
            $paid_amount = $group->sum(fn($x) => $x->paid_amount ?? 0);

            // Get supervisor info
            $supervisor = $agent ? $agent->supervisorUser : null;
            $supervisorLabel = $supervisor ? ($supervisor->id === session('user')['id'] ? 'Me' : ($supervisor->name ?? $supervisor->username)) : 'Unknown';

            return [
                'agent' => $this->formatAgentLabel($agent),
                'supervisor' => $supervisorLabel,
                'total' => $total,
                'paid' => $paid,
                'paid_amount' => $paid_amount,
            ];
        })->values();

        return view('cc.supervisor.dashboard', compact(
            'rtom',
            'latestReport',
            'latestTotal',
            'latestAssigned',
            'latestUnassigned',
            'latestPaidCount',
            'latestPaidAmount',
            'latestCallerBreakdown',
            'allTimeTotal',
            'allTimeAssigned',
            'allTimeUnassigned',
            'allTimePaidCount',
            'allTimePaidAmount',
            'allTimeCallerBreakdown'
        ));
    }

    protected function ensureRtomAdmin()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'cc') {
            abort(403);
        }
        $assignment = $sessionUser['assignment'] ?? null;
        if (! $assignment || !str_starts_with($assignment, 'rtom_')) {
            abort(403);
        }
        // Extract RTOM from assignment like 'rtom_rtom_CODE'
        $rtom = str_replace('rtom_', '', $assignment);
        $rtom = str_replace('_', ' ', $rtom);
        return $rtom; // RTOM name
    }

    public function rtomDashboard()
    {
        $rtom = $this->ensureRtomAdmin();
        $rtomAdminId = session('user')['id'];

        // Get latest report data (most recent report with assignments for this RTOM)
        $latestReport = CallCenterReport::whereHas('assignments', function($q) use ($rtom) {
            $q->whereHas('row', function($rq) use ($rtom) {
                $rq->where('rtom', $rtom);
            });
        })->latest('created_at')->first();

        // Latest report data for this RTOM
        $latestBase = CallCenterAssignment::with(['row', 'agent', 'interactions'])
            ->where('call_center_report_id', $latestReport?->id)
            ->whereHas('row', function($q) use ($rtom) {
                $q->where('rtom', $rtom);
            });

        $latestAssignments = $latestBase->get();
        $latestTotal = $latestAssignments->count();
        $latestAssigned = $latestAssignments->whereNotNull('assigned_user_id')->count();
        $latestUnassigned = $latestTotal - $latestAssigned;
        $latestPaidCount = $latestAssignments->where('paid', true)->count();
        $latestPaidAmount = $latestAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        // For RTOM admin, breakdown by supervisors (supervisors they created)
        $latestSupervisorBreakdown = User::where('assignment', 'like', 'supervisor_%')
            ->where('supervisor', $rtomAdminId)
            ->get()
            ->map(function($supervisor) use ($latestReport, $rtom) {
                // Sum assignments handled by callers under this supervisor
                $assignments = CallCenterAssignment::where('call_center_report_id', $latestReport?->id)
                    ->whereHas('row', function($q) use ($rtom) {
                        $q->where('rtom', $rtom);
                    })
                    ->whereHas('agent', function($q) use ($supervisor) {
                        $q->where('supervisor', $supervisor->id);
                    })
                    ->get();

                $total = $assignments->count();
                $paid = $assignments->where('paid', true)->count();
                $paid_amount = $assignments->sum(fn($x) => $x->paid_amount ?? 0);

                // Get caller breakdown for this supervisor
                $callerProfits = $assignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function($group) {
                    $agent = $group->first()->agent;
                    $profit = $group->sum(fn($x) => $x->paid_amount ?? 0);
                    return [
                        'name' => $this->formatAgentLabel($agent),
                        'profit' => $profit,
                    ];
                })->values();

                return [
                    'supervisor' => $supervisor->name ?? $supervisor->username,
                    'total' => $total,
                    'paid' => $paid,
                    'paid_amount' => $paid_amount,
                    'caller_profits' => $callerProfits,
                ];
            })->values();

        // All-time data
        $allTimeBase = CallCenterAssignment::with(['row', 'agent', 'interactions'])
            ->whereHas('row', function($q) use ($rtom) {
                $q->where('rtom', $rtom);
            });

        $allTimeAssignments = $allTimeBase->get();
        $allTimeTotal = $allTimeAssignments->count();
        $allTimeAssigned = $allTimeAssignments->whereNotNull('assigned_user_id')->count();
        $allTimeUnassigned = $allTimeTotal - $allTimeAssigned;
        $allTimePaidCount = $allTimeAssignments->where('paid', true)->count();
        $allTimePaidAmount = $allTimeAssignments->sum(fn($a) => $a->paid_amount ?? 0);

        $allTimeSupervisorBreakdown = User::where('assignment', 'like', 'supervisor_%')
            ->where('supervisor', $rtomAdminId)
            ->get()
            ->map(function($supervisor) use ($rtom) {
                // Sum assignments handled by callers under this supervisor
                $assignments = CallCenterAssignment::whereHas('row', function($q) use ($rtom) {
                    $q->where('rtom', $rtom);
                })
                ->whereHas('agent', function($q) use ($supervisor) {
                    $q->where('supervisor', $supervisor->id);
                })
                ->get();

                $total = $assignments->count();
                $paid = $assignments->where('paid', true)->count();
                $paid_amount = $assignments->sum(fn($x) => $x->paid_amount ?? 0);

                // Get caller breakdown for this supervisor
                $callerProfits = $assignments->whereNotNull('assigned_user_id')->groupBy('assigned_user_id')->map(function($group) {
                    $agent = $group->first()->agent;
                    $profit = $group->sum(fn($x) => $x->paid_amount ?? 0);
                    return [
                        'name' => $this->formatAgentLabel($agent),
                        'profit' => $profit,
                    ];
                })->values();

                return [
                    'supervisor' => $supervisor->name ?? $supervisor->username,
                    'total' => $total,
                    'paid' => $paid,
                    'paid_amount' => $paid_amount,
                    'caller_profits' => $callerProfits,
                ];
            })->values();

        return view('cc.rtom.dashboard', compact(
            'rtom',
            'latestReport',
            'latestTotal',
            'latestAssigned',
            'latestUnassigned',
            'latestPaidCount',
            'latestPaidAmount',
            'latestSupervisorBreakdown',
            'allTimeTotal',
            'allTimeAssigned',
            'allTimeUnassigned',
            'allTimePaidCount',
            'allTimePaidAmount',
            'allTimeSupervisorBreakdown'
        ));
    }

    protected function formatAgentLabel($agent)
    {
        if (!$agent) return 'Unknown';
        
        $username = $agent->username;
        $name = $agent->name;
        
        return $name ? "{$username} ({$name})" : $username;
    }
}
