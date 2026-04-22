<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Models\CallCenterReport;
use App\Models\MasterDatasetRow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegionAdminController extends Controller
{
    protected function ensureRegionAdmin()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb') {
            abort(403);
        }

        $assignment = $sessionUser['assignment'] ?? null;
        if (! $assignment || $assignment === 'super' || str_starts_with($assignment, 'supervisor_') || str_starts_with($assignment, 'rtom_') || str_starts_with($assignment, 'caller_')) {
            abort(403);
        }

        return $assignment;
    }

    protected function ensureSupervisor()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb') {
            abort(403);
        }

        $assignment = $sessionUser['assignment'] ?? null;
        if (! $assignment || !str_starts_with($assignment, 'supervisor_')) {
            abort(403);
        }

        return str_replace('_', ' ', str_replace('supervisor_', '', $assignment));
    }

    protected function ensureRtomAdmin()
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb') {
            abort(403);
        }

        $assignment = $sessionUser['assignment'] ?? null;
        if (! $assignment || !str_starts_with($assignment, 'rtom_')) {
            abort(403);
        }

        return str_replace('_', ' ', str_replace('rtom_', '', $assignment));
    }

    protected function regionRtoms(string $region)
    {
        $lastTwo = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        if (empty($lastTwo)) {
            return collect();
        }

        return MasterDatasetRow::whereIn('process_id', $lastTwo)
            ->where('region', $region)
            ->whereNotNull('rtom')
            ->distinct()
            ->pluck('rtom')
            ->values();
    }

    public function index()
    {
        $region = $this->ensureRegionAdmin();
        $currentSupervisor = session('user')['id'] ?? null;

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

        $query = User::where('system', 'rb')
            ->where('admin_prev', 1)
            ->where('assignment', 'like', 'rtom_%')
            ->where('supervisor', $currentSupervisor);

        $q = request()->query('q');
        $selectedRtom = request()->query('rtom');

        if (! empty($q)) {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedRtom)) {
            $assignmentValue = 'rtom_' . preg_replace('/\s+/', '_', strtolower($selectedRtom));
            $query->where('assignment', $assignmentValue);
        }

        $rtomAdmins = $query->get();

        return view('regionalbilling.region.index', compact('rtoms', 'rtomAdmins', 'region', 'q', 'selectedRtom'));
    }

    public function dashboard()
    {
        $region = $this->ensureRegionAdmin();

        $rtomCount = User::where('system', 'rb')
            ->where('admin_prev', 1)
            ->where('assignment', 'like', 'rtom_%')
            ->where('supervisor', session('user')['id'] ?? null)
            ->count();

        $reportCount = CallCenterReport::regionalBilling()
            ->whereHas('assignments.row', function ($query) use ($region) {
                $query->where('region', $region);
            })
            ->count();

        $recentReports = CallCenterReport::regionalBilling()
            ->whereHas('assignments.row', function ($query) use ($region) {
                $query->where('region', $region);
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('regionalbilling.region.dashboard', compact('region', 'rtomCount', 'reportCount', 'recentReports'));
    }

    public function search(Request $request)
    {
        $region = $this->ensureRegionAdmin();
        $currentSupervisor = session('user')['id'] ?? null;

        $query = User::where('system', 'rb')
            ->where('admin_prev', 1)
            ->where('assignment', 'like', 'rtom_%')
            ->where('supervisor', $currentSupervisor);

        $q = $request->query('q');
        $selectedRtom = $request->query('rtom');

        if (! empty($q)) {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedRtom)) {
            $assignmentValue = 'rtom_' . preg_replace('/\s+/', '_', strtolower($selectedRtom));
            $query->where('assignment', $assignmentValue);
        }

        $rtomAdmins = $query->get();

        return view('regionalbilling.region._rows', compact('rtomAdmins'));
    }

    public function createAdminForm()
    {
        $region = $this->ensureRegionAdmin();
        $rtoms = $this->regionRtoms($region);

        return view('regionalbilling.region.create_admin', [
            'rtoms' => $rtoms,
            'region' => $region,
            'isSupervisor' => false,
        ]);
    }

    public function createSupervisorForm()
    {
        $region = $this->ensureRegionAdmin();
        $rtoms = $this->regionRtoms($region);

        return view('regionalbilling.region.create_supervisor', compact('rtoms', 'region'));
    }

    public function storeAdmin(Request $request)
    {
        $this->ensureRegionAdmin();

        $request->validate([
            'username' => 'required|digits:6|unique:users,username',
            'rtom' => 'required|string|max:255',
            'name' => 'nullable|string|max:45',
        ]);

        $rtom = $request->input('rtom');

        $user = User::create([
            'username' => $request->input('username'),
            'admin_prev' => 1,
            'system' => 'rb',
            'created_at' => now(),
            'fixed' => 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'rtom_' . preg_replace('/\s+/', '_', strtolower($rtom)),
            'supervisor' => session('user')['id'] ?? null,
        ]);

        return redirect()->route('rb.region.index')->with('status', 'RTO admin created');
    }

    public function storeSupervisor(Request $request)
    {
        $assignment = session('user.assignment') ?? '';
        $isRtomAdmin = str_starts_with($assignment, 'rtom_');

        $request->validate([
            'username' => 'required|digits:6|unique:users,username',
            'name' => 'nullable|string|max:45',
            'supervisor' => $isRtomAdmin ? 'nullable|string|max:255' : 'required|string|max:255',
        ]);

        $rtom = $isRtomAdmin
            ? $assignment
            : ('rtom_' . preg_replace('/\s+/', '_', strtolower($request->input('supervisor'))));

        $user = User::create([
            'username' => $request->input('username'),
            'admin_prev' => 1,
            'system' => 'rb',
            'created_at' => now(),
            'fixed' => 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'supervisor_' . preg_replace('/\s+/', '_', strtolower(str_replace('rtom_', '', $rtom))),
            'supervisor' => session('user')['id'] ?? null,
        ]);

        return redirect()->route($isRtomAdmin ? 'rb.rtom.dashboard' : 'rb.region.index')->with('status', 'Supervisor created');
    }

    public function editAdminForm(User $user)
    {
        $region = $this->ensureRegionAdmin();

        if (! $user->assignment || ! str_starts_with($user->assignment, 'rtom_') || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $rtoms = $this->regionRtoms($region);
        return view('regionalbilling.region.edit_admin', compact('user', 'rtoms', 'region'));
    }

    public function updateAdmin(Request $request, User $user)
    {
        $this->ensureRegionAdmin();

        if (! $user->assignment || ! str_starts_with($user->assignment, 'rtom_') || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:45',
            'rtom' => 'required|string|max:255',
        ]);

        $user->name = $request->input('name');
        $user->assignment = 'rtom_' . preg_replace('/\s+/', '_', strtolower($request->input('rtom')));
        $user->save();

        return redirect()->route('rb.region.index')->with('status', 'RTO admin updated');
    }

    public function editSupervisorForm(User $user)
    {
        $supervisor = $this->ensureSupervisor();

        if (! $user->assignment || ! str_starts_with($user->assignment, 'supervisor_') || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $rtoms = $this->regionRtoms($supervisor);
        return view('regionalbilling.region.edit_supervisor', compact('user', 'rtoms', 'supervisor'));
    }

    public function updateSupervisor(Request $request, User $user)
    {
        $this->ensureSupervisor();

        if (! $user->assignment || ! str_starts_with($user->assignment, 'supervisor_') || $user->supervisor !== (session('user')['id'] ?? null)) {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:45',
        ]);

        $user->name = $request->input('name');
        $user->save();

        return redirect()->route('rb.supervisor.dashboard')->with('status', 'Supervisor updated');
    }

    public function supervisorDashboard()
    {
        $rtom = $this->ensureSupervisor();
        $supervisorId = session('user')['id'] ?? null;

        $callers = User::where('system', 'rb')
            ->where('assignment', 'like', 'caller_%')
            ->where('supervisor', $supervisorId)
            ->get();

        return view('regionalbilling.region.supervisor_dashboard', compact('rtom', 'callers'));
    }

    public function rtomDashboard()
    {
        $rtom = $this->ensureRtomAdmin();
        $rtomAdminId = session('user')['id'] ?? null;

        $supervisors = User::where('system', 'rb')
            ->where('assignment', 'like', 'supervisor_%')
            ->where('supervisor', $rtomAdminId)
            ->get();

        return view('regionalbilling.region.rtom_dashboard', compact('rtom', 'supervisors'));
    }
}
