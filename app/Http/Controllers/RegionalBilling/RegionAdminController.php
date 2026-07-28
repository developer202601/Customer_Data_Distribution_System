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

        return strtoupper(trim(str_replace('_', ' ', str_replace('rtom_', '', $assignment))));
    }

    protected function getRtomAdminRegion(User $user): ?string
    {
        $currentUser = $user->supervisor ? User::find($user->supervisor) : null;
        while ($currentUser) {
            $currentAssignment = $currentUser->assignment ?? null;
            if ($currentAssignment && !str_starts_with($currentAssignment, 'caller_') && !str_starts_with($currentAssignment, 'rtom_') && !str_starts_with($currentAssignment, 'supervisor_') && $currentAssignment !== 'super') {
                return $currentAssignment;
            }
            $currentUser = $currentUser->supervisor ? User::find($currentUser->supervisor) : null;
        }

        $assignment = strtolower(trim((string) $user->assignment));
        $rtomValue = preg_replace('/^rtom_/', '', $assignment);
        return $rtomValue ? $this->deriveRegionFromRtom($rtomValue) : null;
    }

    protected function deriveRegionFromRtom(string $rtomValue): ?string
    {
        $query = MasterDatasetRow::query();

        $latestProcessId = MasterDatasetRow::query()->max('process_id');
        if ($latestProcessId) {
            $query->where('process_id', $latestProcessId);
        }

        $region = $query
            ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower(trim($rtomValue))])
            ->whereNotNull('region')
            ->where('region', '<>', '')
            ->value('region');

        if (! $region) {
            $region = MasterDatasetRow::query()
                ->whereRaw('LOWER(TRIM(rtom)) = ?', [strtolower(trim($rtomValue))])
                ->whereNotNull('region')
                ->where('region', '<>', '')
                ->value('region');
        }

        return $region;
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
        $this->ensureRtomAdmin();
        return view('regionalbilling.region.create_supervisor');
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

        if (! $isRtomAdmin) {
            abort(403);
        }

        $request->validate([
            'username' => 'required|digits:6|unique:users,username',
            'name' => 'nullable|string|max:45',
        ]);

        $user = User::create([
            'username' => $request->input('username'),
            'admin_prev' => 0,
            'system' => 'rb',
            'created_at' => now(),
            'fixed' => 0,
            'status' => 1,
            'name' => $request->input('name'),
            'assignment' => 'caller_' . preg_replace('/\s+/', '_', strtolower(str_replace('rtom_', '', $assignment))),
            'supervisor' => session('user')['id'] ?? null,
        ]);

        return redirect()->route('rb.users.index')->with('status', 'Caller created');
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

        $dbUser = User::find($rtomAdminId);
        $region = $dbUser ? $this->getRtomAdminRegion($dbUser) : null;

        $supervisorIds = [$rtomAdminId];
        if ($region) {
            $sessionAssignment = strtolower(trim((string) (session('user.assignment') ?? '')));
            $rtomValue = substr($sessionAssignment, 5);
            $sharedUsers = User::where('system', 'rb')
                ->where('status', 1)
                ->where('assignment', 'rtom_' . $rtomValue)
                ->get();
            foreach ($sharedUsers as $user) {
                if ($this->getRtomAdminRegion($user) === $region && $user->id !== $rtomAdminId) {
                    $supervisorIds[] = (int) $user->id;
                }
            }
        }

        $callers = User::where('system', 'rb')
            ->where('assignment', 'like', 'caller_%')
            ->whereIn('supervisor', $supervisorIds)
            ->get();

        return view('regionalbilling.region.rtom_dashboard', compact('rtom', 'callers'));
    }

    // -------------------------------------------------------------------------
    // RB super admin: region admin list/search/edit
    // These back the rb.regions.* routes used by the RB super admin side.
    // -------------------------------------------------------------------------

    protected function ensureRbSuper(): void
    {
        $sessionUser = session('user');
        if (! $sessionUser || ($sessionUser['system'] ?? null) !== 'rb' || ($sessionUser['assignment'] ?? null) !== 'super') {
            abort(403);
        }
    }

    public function indexRegions()
    {
        $this->ensureRbSuper();

        $q              = request()->query('q');
        $selectedRegion = request()->query('region');
        $selectedSystem = request()->query('system');

        $query = User::where('admin_prev', 1)
            ->whereIn('system', ['cc', 'rb'])
            ->where(function ($w) {
                $w->where(function ($sq) {
                    // RB region admins: assignment is a region string (not super/rtom_/caller_/segment_/supervisor_)
                    $sq->where('system', 'rb')
                       ->where('assignment', '!=', 'super')
                       ->where('assignment', 'not like', 'rtom_%')
                       ->where('assignment', 'not like', 'caller_%')
                       ->where('assignment', 'not like', 'supervisor_%');
                })->orWhere(function ($sq) {
                    // CC region-style admins: segment_ prefix
                    $sq->where('system', 'cc')
                       ->where('assignment', 'like', 'segment_%');
                });
            });

        if (! empty($q)) {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedSystem)) {
            $query->where('system', $selectedSystem);
        }

        if (! empty($selectedRegion)) {
            $query->where('assignment', $selectedRegion);
        }

        $regionAdmins = $query->orderBy('assignment')->orderBy('username')->get();

        $regions = User::where('system', 'rb')
            ->where('admin_prev', 1)
            ->where('assignment', '!=', 'super')
            ->where('assignment', 'not like', 'rtom_%')
            ->where('assignment', 'not like', 'caller_%')
            ->where('assignment', 'not like', 'supervisor_%')
            ->distinct()
            ->pluck('assignment')
            ->sort()
            ->values();

        return view('regionalbilling.super.regions', compact('regionAdmins', 'regions', 'q', 'selectedRegion', 'selectedSystem'));
    }

    public function searchRegions(Request $request)
    {
        $this->ensureRbSuper();

        $q              = $request->query('q');
        $selectedRegion = $request->query('region');
        $selectedSystem = $request->query('system');

        $query = User::where('admin_prev', 1)
            ->whereIn('system', ['cc', 'rb'])
            ->where(function ($w) {
                $w->where(function ($sq) {
                    $sq->where('system', 'rb')
                       ->where('assignment', '!=', 'super')
                       ->where('assignment', 'not like', 'rtom_%')
                       ->where('assignment', 'not like', 'caller_%')
                       ->where('assignment', 'not like', 'supervisor_%');
                })->orWhere(function ($sq) {
                    $sq->where('system', 'cc')
                       ->where('assignment', 'like', 'segment_%');
                });
            });

        if (! empty($q)) {
            $query->where(function ($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedSystem)) {
            $query->where('system', $selectedSystem);
        }

        if (! empty($selectedRegion)) {
            $query->where('assignment', $selectedRegion);
        }

        $regionAdmins = $query->orderBy('assignment')->orderBy('username')->get();

        return view('regionalbilling.super._rows', compact('regionAdmins'));
    }

    public function editRegionAdminForm(User $user)
    {
        $this->ensureRbSuper();

        // Only allow editing RB region admins (not super/rtom/caller/supervisor)
        if ($user->system !== 'rb'
            || ! $user->admin_prev
            || in_array($user->assignment, ['super'], true)
            || str_starts_with((string) ($user->assignment ?? ''), 'rtom_')
            || str_starts_with((string) ($user->assignment ?? ''), 'caller_')
            || str_starts_with((string) ($user->assignment ?? ''), 'supervisor_')) {
            abort(404);
        }

        return view('regionalbilling.super.edit_region', compact('user'));
    }

    public function updateRegionAdmin(Request $request, User $user)
    {
        $this->ensureRbSuper();

        if ($user->system !== 'rb'
            || ! $user->admin_prev
            || in_array($user->assignment, ['super'], true)
            || str_starts_with((string) ($user->assignment ?? ''), 'rtom_')
            || str_starts_with((string) ($user->assignment ?? ''), 'caller_')
            || str_starts_with((string) ($user->assignment ?? ''), 'supervisor_')) {
            abort(404);
        }

        $request->validate([
            'name' => 'nullable|string|max:45',
        ]);

        $user->name = $request->input('name');
        $user->save();

        return redirect()->route('rb.regions.index')->with('status', 'Region admin updated.');
    }
}
