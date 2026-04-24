<?php

namespace App\Http\Controllers\RegionalBilling;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\MasterDatasetRow;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    public function createUserForm()
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $roles = ['region' => 'Region Admin'];
        $systems = ['cc' => 'Call Center', 'rb' => 'Regional Billing Centre'];

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

        return view('regionalbilling.super.create', compact('roles', 'regions', 'systems'));
    }

    public function storeUser(Request $request)
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $request->validate([
            'username' => 'required|string|size:6|unique:users,username',
            'name' => 'nullable|string|max:255',
            'region' => 'required|string|max:45',
            'system' => 'required|in:cc,rb',
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

        $system = $request->input('system');
        
        $user = new User();
        $user->username = $request->input('username');
        $user->name = $request->input('name');
        $user->system = $system;
        $user->admin_prev = 1;
        $user->assignment = $region;
        $user->status = 1;
        $user->supervisor = $sessionUser['id'] ?? null;
        $user->created_at = now();
        $user->save();

        $systemLabel = $system === 'cc' ? 'Call Center' : 'Regional Billing Centre';
        return redirect()->route('rb.regions.index')->with('status', "User created as {$systemLabel} region admin");
    }

    public function indexAssign()
    {
        $sessionUser = session('user');
        if (! $sessionUser || (($sessionUser['assignment'] ?? null) !== 'super')) {
            abort(403);
        }

        $users = User::whereIn('system', ['cc', 'rb'])
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

        return view('regionalbilling.super.index', compact('users'));
    }

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

        return view('regionalbilling.super.assign', compact('user', 'roles', 'regions'));
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

        return redirect()->route('rb.regions.index')->with('status', 'Assignment updated');
    }
}
