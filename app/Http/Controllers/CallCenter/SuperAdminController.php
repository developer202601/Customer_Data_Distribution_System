<?php

namespace App\Http\Controllers\CallCenter;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MasterDatasetRow;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    public function showAssignForm(User $user)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $roles = ['super' => 'Super Admin', 'region' => 'Region Admin'];

        $lastTwoProcessIds = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        $regions = collect();
        if (! empty($lastTwoProcessIds)) {
            $regions = MasterDatasetRow::whereIn('process_id', $lastTwoProcessIds)
                ->whereNotNull('region')
                ->pluck('region')
                ->unique()
                ->values();
        }

        return view('cc.super.assign', compact('user', 'roles', 'regions'));
    }

    public function indexAssign()
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        // only show call-center users with region assignments and super admins with admin_prev
        $users = User::where('system', 'cc')
            ->where(function ($q) {
                $q->where('assignment', 'like', 'REGION %')
                  ->orWhere(function ($sq) {
                      $sq->where('assignment', 'super')
                         ->where('admin_prev', 1);
                  });
            })
            ->where('id', '!=', $sessionUser['id'])
            ->orderBy('id')
            ->get();

        return view('cc.super.index', compact('users'));
    }

    public function storeAssignment(Request $request, User $user)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $request->validate([
            'role' => 'required|in:super,region',
            'region' => 'required_if:role,region|nullable|string|max:45',
        ]);

        $role = $request->input('role');

        if ($role === 'region') {
            $lastTwoProcessIds = MasterDatasetRow::select('process_id')
                ->distinct()
                ->orderBy('process_id', 'desc')
                ->limit(2)
                ->pluck('process_id')
                ->toArray();

            $allowedRegions = [];
            if (! empty($lastTwoProcessIds)) {
                $allowedRegions = MasterDatasetRow::whereIn('process_id', $lastTwoProcessIds)
                    ->whereNotNull('region')
                    ->pluck('region')
                    ->unique()
                    ->values()
                    ->toArray();
            }

            $region = $request->input('region');
            if (! in_array($region, $allowedRegions, true)) {
                return back()->withErrors(['region' => 'Selected region is not available from the last two reports.']);
            }

            $user->admin_prev = 1;
            $user->assignment = $region;
        } else {
            $user->admin_prev = 1;
            $user->assignment = 'super';
        }

        $user->save();

        return redirect()->route('cc.users.assign.index')->with('status', 'Assignment updated');
    }

    public function createUserForm()
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $roles = ['region' => 'Region Admin'];

        $lastTwoProcessIds = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        $regions = collect();
        if (! empty($lastTwoProcessIds)) {
            $regions = MasterDatasetRow::whereIn('process_id', $lastTwoProcessIds)
                ->whereNotNull('region')
                ->pluck('region')
                ->unique()
                ->values();
        }

        return view('cc.super.create', compact('roles', 'regions'));
    }

    public function storeUser(Request $request)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $request->validate([
            'username' => 'required|string|size:6|unique:users,username',
            'region' => 'required|string|max:45',
        ]);

        $lastTwoProcessIds = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        $allowedRegions = [];
        if (! empty($lastTwoProcessIds)) {
            $allowedRegions = MasterDatasetRow::whereIn('process_id', $lastTwoProcessIds)
                ->whereNotNull('region')
                ->pluck('region')
                ->unique()
                ->values()
                ->toArray();
        }

        $region = $request->input('region');
        if (! in_array($region, $allowedRegions, true)) {
            return back()->withErrors(['region' => 'Selected region is not available from the last two reports.']);
        }

        $user = new User();
        $user->username = $request->input('username');
        $user->system = 'cc';
        $user->admin_prev = 1;
        $user->assignment = $region;
        $user->status = 1;
        // mark supervisor as the current super admin (session user id)
        $user->supervisor = $sessionUser['id'] ?? null;
        // ensure created_at is populated (model has timestamps disabled)
        $user->created_at = now();
        $user->save();

        return redirect()->route('cc.super.regions')->with('status', 'User created and assigned');
    }

    public function indexRegions()
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $lastTwo = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        $allRegions = MasterDatasetRow::whereNotNull('region')
            ->distinct()
            ->pluck('region')
            ->values();

        $q = request()->query('q');
        $selectedRegion = request()->query('region');

        $query = User::where('system', 'cc');
        if (! $allRegions->isEmpty()) {
            $query->whereIn('assignment', $allRegions->toArray());
        } else {
            $query->where('assignment', 'like', 'REGION %');
        }

        if (! empty($q)) {
            $query->where(function($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedRegion)) {
            $query->where('assignment', $selectedRegion);
        }

        $regionAdmins = $query->get();

        $regions = $regionAdmins->pluck('assignment')
            ->filter(fn ($assignment) => $assignment && str_starts_with($assignment, 'REGION'))
            ->unique()
            ->sort()
            ->values();

        return view('cc.super.regions', compact('regions', 'regionAdmins', 'q', 'selectedRegion'));
    }

    public function searchRegions(Request $request)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $allRegions = MasterDatasetRow::whereNotNull('region')
            ->distinct()
            ->pluck('region')
            ->values();

        $query = User::where('system', 'cc');
        if (! $allRegions->isEmpty()) {
            $query->whereIn('assignment', $allRegions->toArray());
        } else {
            $query->where('assignment', 'like', 'REGION %');
        }

        $q = $request->query('q');
        $selectedRegion = $request->query('region');

        if (! empty($q)) {
            $query->where(function($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (! empty($selectedRegion)) {
            $query->where('assignment', $selectedRegion);
        }

        $regionAdmins = $query->get();

        return view('cc.super._rows', compact('regionAdmins'));
    }

    public function editRegionAdminForm(User $user)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $allRegions = MasterDatasetRow::whereNotNull('region')
            ->distinct()
            ->pluck('region')
            ->values();

        // only allow editing region admins
        if (! $user->assignment || ! $allRegions->contains($user->assignment)) {
            abort(404);
        }

        $lastTwoProcessIds = MasterDatasetRow::select('process_id')
            ->distinct()
            ->orderBy('process_id', 'desc')
            ->limit(2)
            ->pluck('process_id')
            ->toArray();

        $regions = collect();
        if (! empty($lastTwoProcessIds)) {
            $regions = MasterDatasetRow::whereIn('process_id', $lastTwoProcessIds)
                ->whereNotNull('region')
                ->pluck('region')
                ->unique()
                ->values();
        }

        if (! $regions->contains($user->assignment)) {
            // gracefully allow editing even if the region isn’t in the latest two, fall back to all regions
            $regions = $allRegions;
        }
        if (! $regions->contains($user->assignment)) {
            abort(404);
        }

        return view('cc.super.edit_region', compact('user', 'regions'));
    }

    public function updateRegionAdmin(Request $request, User $user)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        if (! $user->assignment) {
            abort(404);
        }

        $allRegions = MasterDatasetRow::whereNotNull('region')
            ->distinct()
            ->pluck('region')
            ->values();

        $request->validate([
            'name' => 'nullable|string|max:45',
            'region' => 'required|string|max:45',
        ]);

        $allowedRegions = $allRegions->toArray();

        $region = $request->input('region');
        if (! in_array($region, $allowedRegions, true)) {
            return back()->withErrors(['region' => 'Selected region is not available.']);
        }

        $user->name = $request->input('name');
        if (! $user->fixed) {
            $user->assignment = $region;
        }
        $user->save();

        return redirect()->route('cc.super.regions')->with('status', 'Region admin updated successfully.');
    }
}
