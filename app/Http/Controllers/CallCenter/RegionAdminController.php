<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MasterDatasetRow;
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

        // rtom admins are users with assignment like 'rtom_<rtom>' and supervisor = current user's supervisor
        $currentSupervisor = session('user')['supervisor'] ?? null;
        $rtomAdmins = User::where('system', 'cc')
            ->where('admin_prev', 1)
            ->where('assignment', 'like', 'rtom_%')
            ->where('supervisor', $currentSupervisor)
            ->get();

        return view('cc.region.index', compact('rtoms', 'rtomAdmins', 'region'));
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
            'fixed' => $isSupervisor ? 1 : 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'rtom_' . preg_replace('/\s+/', '_', strtolower($rtom)),
            'supervisor' => session('user')['supervisor'] ?? null,
        ]);

        return redirect()->route('cc.region.index')->with('status', 'RTOM admin created');
    }

    public function storeSupervisor(Request $request)
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
            'fixed' => $isSupervisor ? 1 : 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'supervisor_' . preg_replace('/\s+/', '_', strtolower($rtom)),
            'supervisor' => session('user')['supervisor'] ?? null,
        ]);

        return redirect()->route('cc.region.index')->with('status', 'Supervisor created');
    }

    public function editAdminForm(User $user)
    {
        $region = $this->ensureRegionAdmin();

        // only allow editing RTOM admins they supervise
        if (! $user->assignment || stripos($user->assignment, 'rtom_') !== 0 || $user->supervisor !== (session('user')['supervisor'] ?? null)) {
            abort(404);
        }

        $rtoms = $this->regionRtoms($region);
        return view('cc.region.edit_admin', compact('user', 'rtoms'));
    }

    public function updateAdmin(Request $request, User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || stripos($user->assignment, 'rtom_') !== 0 || $user->supervisor !== (session('user')['supervisor'] ?? null)) {
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

        if (! $user->assignment || stripos($user->assignment, 'rtom_') !== 0 || $user->supervisor !== (session('user')['supervisor'] ?? null)) {
            abort(404);
        }

        if ($user->fixed) {
            return redirect()->route('cc.region.index')->withErrors(['delete' => 'This user is fixed and cannot be deleted.']);
        }

        $user->delete();
        return redirect()->route('cc.region.index')->with('status', 'RTOM admin deleted');
    }

    public function dashboard()
    {
        $region = $this->ensureRegionAdmin();

        $base = CallCenterAssignment::with(['row', 'agent', 'interactions'])
            ->whereHas('row', function($q) use ($region) {
                $q->where('region', $region);
            });

        $assignments = $base->get();

        $total = $assignments->count();
        $assigned = $assignments->whereNotNull('assigned_user_id')->count();
        $unassigned = $total - $assigned;
        $accepted = $assignments->where('accepted', true)->count();
        $rejected = $assignments->where('rejected', true)->count();
        $paidCount = $assignments->where('paid', true)->count();
        $paidAmount = $assignments->sum(fn($a) => $a->paid_amount ?? 0);

        $rtomBreakdown = $assignments->groupBy(fn($a) => $a->row->rtom ?? '—')->map(function($group, $rtom) {
            return [
                'rtom' => $rtom,
                'total' => $group->count(),
                'assigned' => $group->whereNotNull('assigned_user_id')->count(),
                'paid' => $group->where('paid', true)->count(),
                'paid_amount' => $group->sum(fn($x) => $x->paid_amount ?? 0),
            ];
        })->values();

        return view('cc.region.dashboard', compact(
            'region', 'total', 'assigned', 'unassigned', 'accepted', 'rejected', 'paidCount', 'paidAmount', 'rtomBreakdown'
        ));
    }

    public function indexAssign()
    {
        $region = $this->ensureRegionAdmin();

        // Show call-center users added by the logged-in supervisor, not super admins and not already RTOM admins
        $users = User::where('system', 'cc')
            ->where('supervisor', session('user')['supervisor'] ?? null)
            ->where(function ($q) {
                $q->whereNull('assignment')
                    ->orWhere(function ($sq) {
                        $sq->where('assignment', '<>', 'super')
                            ->where('assignment', 'not like', 'rtom_%');
                    });
            })
            ->orderBy('id')
            ->get();

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
        $user->supervisor = $rtom;
        $user->save();

        return redirect()->route('cc.region.assign.index')->with('status', 'Assignment updated');
    }
}
